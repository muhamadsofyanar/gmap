<?php
namespace App\Models;

use App\Core\Database;

class WorkspaceExtras
{
    public function attachments(int $id): array
    {
        $q=Database::getInstance()->prepare('SELECT id,name,mime,size,created_at FROM crm_attachments WHERE lead_id=? ORDER BY id DESC'); $q->execute([$id]); return $q->fetchAll();
    }

    public function attach(int $lead, string $path, string $original): int
    {
        $size = is_file($path) ? filesize($path) : 0;
        if ($size < 1 || $size > 5*1024*1024) throw new \InvalidArgumentException('Ukuran lampiran harus 1 byte sampai 5 MB.');
        $name = basename(str_replace('\\','/',$original));
        $name = preg_replace('/[\x00-\x1f\x7f]/u','', $name);
        if (!$name || mb_strlen($name)>200) throw new \InvalidArgumentException('Nama file maksimal 200 karakter.');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $allowed = ['pdf'=>'application/pdf','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg'];
        if (isset($allowed[$ext])) {
            if ($mime !== $allowed[$ext]) throw new \InvalidArgumentException('Isi file tidak sesuai jenis lampiran.');
            if ($ext !== 'pdf' && !@getimagesize($path)) throw new \InvalidArgumentException('Gambar tidak valid.');
        } elseif (in_array($ext,['docx','xlsx'],true)) {
            $zip = new \ZipArchive();
            if ($zip->open($path)!==true) throw new \InvalidArgumentException('Dokumen Office tidak valid.');
            try {
                $entry = $ext==='docx' ? 'word/document.xml' : 'xl/workbook.xml';
                if ($zip->locateName($entry)===false || $zip->locateName('[Content_Types].xml')===false || $zip->numFiles>2000) throw new \InvalidArgumentException('Dokumen Office tidak valid.');
                $expanded=0;
                for ($i=0;$i<$zip->numFiles;$i++) {
                    $stat=$zip->statIndex($i);
                    $expanded += $stat['size'];
                    if ($expanded>20*1024*1024 || stripos($stat['name'],'vbaProject')!==false) throw new \InvalidArgumentException('Dokumen terlalu besar atau memiliki makro.');
                }
            } finally { $zip->close(); }
            $mime = $ext==='docx' ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        } else throw new \InvalidArgumentException('Gunakan PDF, JPG, PNG, DOCX, atau XLSX.');
        $db=Database::getInstance(); $db->beginTransaction();
        try {
            $q=$db->prepare('SELECT lead_status FROM project_leads WHERE id=? AND in_crm=1 FOR UPDATE'); $q->execute([$lead]); $status=$q->fetchColumn();
            if ($status===false) throw new \InvalidArgumentException('Prospek tidak ditemukan di CRM.');
            $q=$db->prepare('SELECT COUNT(*) FROM crm_attachments WHERE lead_id=?'); $q->execute([$lead]);
            if ((int)$q->fetchColumn()>=20) throw new \InvalidArgumentException('Maksimal 20 lampiran per prospek.');
            $q=$db->prepare('INSERT INTO crm_attachments (lead_id,name,mime,size,content) VALUES (?,?,?,?,?)');
            $q->bindValue(1,$lead,\PDO::PARAM_INT); $q->bindValue(2,$name); $q->bindValue(3,$mime); $q->bindValue(4,$size,\PDO::PARAM_INT); $q->bindValue(5,'',\PDO::PARAM_LOB); $q->execute();
            $id=(int)$db->lastInsertId();
            \App\Services\PrivateStorage::write($db,'attachment',$id,file_get_contents($path));
            (new CrmModel())->addHistory($lead,$status,$status,'attachment_added','Lampiran ditambahkan: '.$name);
            $db->commit(); return $id;
        } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }

    public function deleteAttachment(int $id): int
    {
        $db=Database::getInstance(); $db->beginTransaction();
        try {
            $q=$db->prepare('SELECT a.id,a.lead_id,a.name,l.lead_status FROM crm_attachments a JOIN project_leads l ON l.id=a.lead_id WHERE a.id=? AND l.in_crm=1 FOR UPDATE'); $q->execute([$id]); $row=$q->fetch();
            if (!$row) throw new \InvalidArgumentException('Lampiran tidak ditemukan.');
            $db->prepare('DELETE FROM crm_attachments WHERE id=?')->execute([$id]);
            (new CrmModel())->addHistory((int)$row['lead_id'],$row['lead_status'],$row['lead_status'],'attachment_removed','Lampiran dihapus: '.$row['name']);
            $db->commit(); return (int)$row['lead_id'];
        } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }
}
