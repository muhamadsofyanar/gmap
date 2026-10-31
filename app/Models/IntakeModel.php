<?php
namespace App\Models;

use App\Core\Database;
use App\Services\MapsIdentity;

class IntakeModel
{
    public const FIELDS=['shop_name'=>'Nama bisnis','phone'=>'Telepon / WhatsApp','address'=>'Alamat','category'=>'Kategori','website'=>'Website','gmap_url'=>'Google Maps','notes'=>'Catatan'];
    private \PDO $db;
    public function __construct() { $this->db=Database::getInstance(); }

    public function draft(string $token, int $project, string $name, array $payload, string $source): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/D',$token)) throw new \InvalidArgumentException('Muat ulang formulir.');
        if ($project) {
            $target=(new ProjectModel())->getProjectById($project);
            if (!$target || (int)($target['workspace_id']??0)!==1)throw new \InvalidArgumentException('Proyek tidak ditemukan.');
        }
        if (!$project && ($name==='' || mb_strlen($name)>200)) throw new \InvalidArgumentException('Isi nama proyek baru (maksimal 200 karakter).');
        $json=json_encode($payload,JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if (strlen($json)>8*1024*1024) throw new \InvalidArgumentException('Isi data terlalu besar. Pecah file menjadi beberapa bagian.');
        $this->db->prepare('DELETE FROM crm_intake_batches WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) OR (result IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY))')->execute();
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('INSERT IGNORE INTO crm_intake_batches (token,project_id,project_name,source,payload) VALUES (?,?,?,?,?)'); $q->execute([$token,$project,$name,$source,'']);
            if ($q->rowCount()===1) \App\Services\PrivateStorage::write($this->db,'intake',$token,$json);
            $old=$this->batch($token,true);
            if ($old['payload']!==$json || (int)$old['project_id']!==$project || $old['project_name']!==$name || $old['source']!==$source) throw new \InvalidArgumentException('Permintaan berubah. Mulai impor baru.');
            $this->db->commit();
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function batch(string $token, bool $lock=false): array
    {
        $q=$this->db->prepare('SELECT * FROM crm_intake_batches WHERE token=?'.($lock?' FOR UPDATE':'')); $q->execute([$token]); $batch=$q->fetch();
        if (!$batch || (!$batch['result'] && strtotime($batch['created_at'])<time()-3600)) throw new \InvalidArgumentException('Sesi impor berakhir. Unggah ulang file.');
        if ($batch['payload']==='') $batch['payload']=\App\Services\PrivateStorage::read($this->db,'intake',$token);
        return $batch;
    }

    public function map(string $token, array $mapping): void
    {
        $batch=$this->batch($token); $payload=json_decode($batch['payload'],true,512,JSON_THROW_ON_ERROR); $clean=[];
        if ($batch['result']) throw new \InvalidArgumentException('Impor ini sudah selesai.');
        foreach (self::FIELDS as $key=>$_) {
            $value=$mapping[$key]??'';
            if ($value==='') continue;
            if (!is_string($value) || !ctype_digit($value) || !array_key_exists((int)$value,$payload['headers'])) throw new \InvalidArgumentException('Pemetaan kolom tidak valid.');
            $clean[$key]=(int)$value;
        }
        if (!isset($clean['shop_name']) || count(array_unique($clean))!==count($clean)) throw new \InvalidArgumentException('Pilih kolom nama bisnis. Setiap kolom hanya boleh dipakai sekali.');
        $this->db->prepare('UPDATE crm_intake_batches SET mapping=? WHERE token=? AND result IS NULL')->execute([json_encode($clean,JSON_THROW_ON_ERROR),$token]);
    }

    private function normalize(array $row): array
    {
        $r=[];
        foreach (self::FIELDS as $key=>$_) $r[$key]=SalesModel::text($row,$key, in_array($key,['website','gmap_url'],true)?2048:($key==='notes'?4000:($key==='address'?500:($key==='category'?100:200))),$key==='shop_name');
        if (str_starts_with($r['phone'], "'")) $r['phone']=substr($r['phone'],1);
        if ($r['phone']!=='') $r['phone']=GatewayModel::phone($r['phone']);
        foreach (['website','gmap_url'] as $key) if ($r[$key]!=='' && (!filter_var($r[$key],FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($r[$key],PHP_URL_SCHEME)??''),['http','https'],true))) throw new \InvalidArgumentException('Tautan '.$key.' harus berupa URL http/https yang valid.');
        return $r;
    }

    private function keys(array $row): array
    {
        $keys=[]; $map=MapsIdentity::key($row['gmap_url']??null);
        if ($map) $keys[]='map:'.$map;
        try { $phone=GatewayModel::phone($row['phone']?:($row['whatsapp']??'')); $keys[]='phone:'.$phone; } catch (\InvalidArgumentException $e) {}
        $name=mb_strtolower(preg_replace('/\s+/u',' ',trim($row['shop_name']??'')));
        $address=mb_strtolower(preg_replace('/\s+/u',' ',trim($row['address']??'')));
        if ($name!=='') $keys[]='name:'.$name.'|'.$address;
        return $keys;
    }

    public function review(array $batch): array
    {
        $payload=json_decode($batch['payload'],true,512,JSON_THROW_ON_ERROR);
        $mapping=json_decode($batch['mapping']??'null',true);
        if (!is_array($mapping)) throw new \InvalidArgumentException('Cocokkan kolom terlebih dahulu.');
        $seen=[];
        if ($batch['project_id']) {
            $q=$this->db->prepare('SELECT shop_name,phone,whatsapp,address,gmap_url FROM project_leads WHERE project_id=?'); $q->execute([$batch['project_id']]);
            foreach ($q->fetchAll() as $existing) foreach ($this->keys($existing) as $key) $seen[$key]=true;
        }
        $result=[];
        foreach ($payload['rows'] as $index=>$cells) {
            $raw=[]; foreach ($mapping as $key=>$column) $raw[$key]=$cells[$column]??'';
            try {
                $row=$this->normalize($raw); $keys=$this->keys($row); $duplicate=false;
                foreach ($keys as $key) if (isset($seen[$key])) $duplicate=true;
                foreach ($keys as $key) $seen[$key]=true;
                $result[]=['index'=>$index,'row'=>$row,'eligible'=>!$duplicate,'message'=>$duplicate?'Duplikat dalam proyek atau file (kontak, Maps, atau nama dan alamat sama).':'Siap diimpor'];
            } catch (\InvalidArgumentException $e) { $result[]=['index'=>$index,'row'=>$raw,'eligible'=>false,'message'=>$e->getMessage()]; }
        }
        return $result;
    }

    public function commit(string $token, array $selected): array
    {
        if (!$selected || count($selected)>1000) throw new \InvalidArgumentException('Pilih setidaknya satu baris valid.');
        foreach ($selected as $index) if (!is_scalar($index) || !ctype_digit((string)$index)) throw new \InvalidArgumentException('Pilihan baris tidak valid.');
        $selected=array_fill_keys(array_map('intval',$selected),true);
        $this->db->beginTransaction();
        try {
            $batch=$this->batch($token,true);
            if ($batch['result']) { $this->db->commit(); return json_decode($batch['result'],true,512,JSON_THROW_ON_ERROR); }
            $project=(int)$batch['project_id'];
            if ($project) {
                $q=$this->db->prepare('SELECT id FROM projects WHERE id=? AND workspace_id=1 FOR UPDATE'); $q->execute([$project]);
                if (!$q->fetchColumn()) throw new \InvalidArgumentException('Proyek sudah tidak tersedia.');
            }
            $review=$this->review($batch); $valid=[];
            foreach ($review as $item) if (isset($selected[$item['index']]) && $item['eligible']) $valid[]=$item['row'];
            if (!$valid) throw new \InvalidArgumentException('Tidak ada baris valid yang baru. Data mungkin sudah diimpor.');
            if (!$project) {
                $this->db->prepare("INSERT INTO projects (name,keyword,location,total_leads,status) VALUES (?,'Prospek tambahan','',0,'active')")->execute([$batch['project_name']]); $project=(int)$this->db->lastInsertId();
            }
            $q=$this->db->prepare('INSERT INTO project_leads (project_id,shop_name,phone,whatsapp,address,category,website,gmap_url,notes,in_crm,workspace_id) VALUES (?,?,?,?,?,?,?,?,?,0,1)');
            $source=$this->db->prepare('INSERT INTO crm_lead_sources (lead_id,source) VALUES (?,?)');
            foreach ($valid as $row) { $q->execute([$project,$row['shop_name'],$row['phone'],$row['phone'],$row['address'],$row['category'],$row['website'],$row['gmap_url'],$row['notes']]); $source->execute([(int)$this->db->lastInsertId(),$batch['source']]); }
            $this->db->prepare('UPDATE projects SET total_leads=(SELECT COUNT(*) FROM project_leads WHERE project_id=?) WHERE id=?')->execute([$project,$project]);
            $result=['project_id'=>$project,'inserted'=>count($valid),'skipped'=>count($selected)-count($valid)];
            $this->db->prepare('UPDATE crm_intake_batches SET result=? WHERE token=?')->execute([json_encode($result,JSON_THROW_ON_ERROR),$token]);
            $this->db->commit(); return $result;
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
}
