<?php
namespace App\Services;

class ProspectFile
{
    public static function read(string $path, string $name): array
    {
        if (!is_file($path) || filesize($path)<1 || filesize($path)>5*1024*1024) throw new \InvalidArgumentException('File impor maksimal 5 MB.');
        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        // The extension is only a hint; the real content type must match it.
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $allowed=['csv'=>['text/plain','text/csv','application/csv','text/x-csv','application/vnd.ms-excel'],'xlsx'=>['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip','application/octet-stream']];
        if (isset($allowed[$ext]) && !in_array($mime,$allowed[$ext],true)) throw new \InvalidArgumentException('Isi file tidak sesuai dengan format '.strtoupper($ext).'.');
        if ($ext==='xlsx' && file_get_contents($path,false,null,0,4)!=="PK\x03\x04") throw new \InvalidArgumentException('File XLSX tidak valid.');
        if ($ext==='csv') $rows=self::csv($path);
        elseif ($ext==='xlsx') $rows=self::xlsx($path);
        else throw new \InvalidArgumentException('Gunakan CSV atau XLSX. Simpan file XLS lama sebagai XLSX terlebih dahulu.');
        $rows=array_values(array_filter($rows,static fn($r)=>count(array_filter($r,static fn($v)=>trim($v)!==''))>0));
        if (count($rows)<2 || count($rows)>1001) throw new \InvalidArgumentException('File harus memiliki judul kolom dan 1–1000 baris data.');
        foreach ($rows as $row) {
            if (count($row)>32) throw new \InvalidArgumentException('Maksimal 32 kolom per file.');
            foreach ($row as $value) if (mb_strlen($value)>4000) throw new \InvalidArgumentException('Isi satu sel maksimal 4000 karakter.');
        }
        return ['headers'=>array_shift($rows),'rows'=>$rows,'source'=>$ext];
    }

    private static function csv(string $path): array
    {
        $text=file_get_contents($path);
        if (str_starts_with($text,"\xff\xfe") || str_starts_with($text,"\xfe\xff")) $text=mb_convert_encoding($text,'UTF-8','UTF-16');
        if (!mb_check_encoding($text,'UTF-8')) $text=mb_convert_encoding($text,'UTF-8','Windows-1252');
        $text=preg_replace('/^\xEF\xBB\xBF/','',$text);
        $line=strtok($text,"\r\n")?:'';
        $delimiter=','; $best=0;
        foreach ([',',';',"\t"] as $candidate) { $count=count(str_getcsv($line,$candidate,'"','')); if ($count>$best) { $delimiter=$candidate; $best=$count; } }
        $stream=fopen('php://temp','r+'); fwrite($stream,$text); rewind($stream); $rows=[];
        try {
            while (($row=fgetcsv($stream,0,$delimiter,'"',''))!==false) {
                $row=array_map(static fn($v)=>(string)$v,$row);
                if (!array_filter($row,static fn($v)=>trim($v)!=='')) continue;
                $rows[]=$row;
                if (count($rows)>1001) throw new \InvalidArgumentException('Maksimal 1000 baris data.');
            }
        } finally { fclose($stream); }
        return $rows;
    }

    private static function xml(string $text): \SimpleXMLElement
    {
        if (stripos($text,'<!DOCTYPE')!==false || stripos($text,'<!ENTITY')!==false) throw new \InvalidArgumentException('XML dokumen tidak didukung.');
        $old=libxml_use_internal_errors(true);
        try { $xml=simplexml_load_string($text,'SimpleXMLElement',LIBXML_NONET); if (!$xml) throw new \InvalidArgumentException('Struktur XLSX tidak valid.'); return $xml; }
        finally { libxml_clear_errors(); libxml_use_internal_errors($old); }
    }

    private static function xlsx(string $path): array
    {
        $zip=new \ZipArchive(); if ($zip->open($path)!==true) throw new \InvalidArgumentException('XLSX tidak valid.');
        try {
            $size=0;
            if ($zip->numFiles>2000) throw new \InvalidArgumentException('Dokumen terlalu kompleks.');
            for ($i=0;$i<$zip->numFiles;$i++) { $s=$zip->statIndex($i); $size+=$s['size']; if ($size>20*1024*1024) throw new \InvalidArgumentException('Ukuran isi XLSX maksimal 20 MB.'); }
            $workbook=self::xml($zip->getFromName('xl/workbook.xml')?:'');
            $sheets=$workbook->xpath('//*[local-name()="sheet"]');
            if (!$sheets) throw new \InvalidArgumentException('Sheet tidak ditemukan.');
            $relId=(string)$sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
            $rels=self::xml($zip->getFromName('xl/_rels/workbook.xml.rels')?:''); $target='';
            foreach ($rels->xpath('//*[local-name()="Relationship"]') as $rel) if ((string)$rel['Id']===$relId && (string)$rel['TargetMode']!=='External') $target=(string)$rel['Target'];
            $target=ltrim($target,'/'); if (str_starts_with($target,'xl/')) $target=substr($target,3);
            if (!preg_match('~^worksheets/[a-zA-Z0-9_-]+\.xml$~D',$target)) throw new \InvalidArgumentException('Lokasi sheet tidak didukung.');
            $shared=[]; $strings=$zip->getFromName('xl/sharedStrings.xml');
            if ($strings!==false) foreach (self::xml($strings)->xpath('//*[local-name()="si"]') as $si) $shared[]=implode('',array_map('strval',$si->xpath('.//*[local-name()="t"]')));
            $sheet=self::xml($zip->getFromName('xl/'.$target)?:''); $rows=[];
            foreach ($sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                $values=[];
                foreach ($row->xpath('./*[local-name()="c"]') as $cell) {
                    if ($cell->xpath('./*[local-name()="f"]')) throw new \InvalidArgumentException('File berisi rumus. Salin dan tempel sebagai nilai sebelum impor.');
                    if (!preg_match('/^([A-Z]+)[0-9]+$/D',(string)$cell['r'],$m)) throw new \InvalidArgumentException('Posisi sel tidak valid.');
                    $index=0; foreach (str_split($m[1]) as $letter) $index=$index*26+ord($letter)-64; $index--;
                    if ($index>31) throw new \InvalidArgumentException('Maksimal 32 kolom.');
                    while (count($values)<=$index) $values[]='';
                    $v=$cell->xpath('./*[local-name()="v"]'); $value=isset($v[0])?(string)$v[0]:'';
                    if ((string)$cell['t']==='s') $value=$shared[(int)$value]??'';
                    if ((string)$cell['t']==='inlineStr') $value=implode('',array_map('strval',$cell->xpath('.//*[local-name()="t"]')));
                    $values[$index]=$value;
                }
                if (!array_filter($values,static fn($v)=>trim($v)!=='')) continue;
                $rows[]=$values;
                if (count($rows)>1001) throw new \InvalidArgumentException('Maksimal 1000 baris data.');
            }
            return $rows;
        } finally { $zip->close(); }
    }
}
