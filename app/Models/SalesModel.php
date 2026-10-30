<?php
namespace App\Models;

use App\Core\Database;
use App\Services\MapsIdentity;
use PDO;

class SalesModel
{
    private PDO $db;
    public function __construct() { $this->db = Database::getInstance(); }

    public static function text(array $data, string $key, int $max, bool $required = false): string
    {
        $value = $data[$key] ?? '';
        if (!is_string($value) || mb_strlen(trim($value)) > $max || ($required && trim($value) === '')) throw new \InvalidArgumentException('Isi ' . $key . ' dengan benar (maksimal ' . $max . ' karakter).');
        return trim($value);
    }

    public static function date(string $value, bool $time = false): string
    {
        $format = $time ? 'Y-m-d\TH:i' : 'Y-m-d';
        $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
        if (!$date || $date->format($format) !== $value || (int)$date->format('Y') < 2000 || (int)$date->format('Y') > 2100) throw new \InvalidArgumentException('Tanggal tidak valid.');
        return $date->format($time ? 'Y-m-d H:i:s' : 'Y-m-d');
    }

    private function lockLead(int $id): array
    {
        $q = $this->db->prepare('SELECT * FROM project_leads WHERE id=? AND in_crm=1 FOR UPDATE');
        $q->execute([$id]);
        $lead = $q->fetch();
        if (!$lead) throw new \InvalidArgumentException('Prospek tidak ditemukan di CRM.');
        return $lead;
    }

    private function transaction(callable $action): mixed
    {
        $this->db->beginTransaction();
        try { $result = $action(); $this->db->commit(); return $result; }
        catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function profile(int $id): array
    {
        $q = $this->db->prepare('SELECT * FROM crm_profiles WHERE lead_id=?'); $q->execute([$id]);
        return $q->fetch() ?: ['pic_name'=>'','pic_role'=>'','pic_phone'=>'','pic_email'=>'','tags'=>'[]'];
    }

    public function saveProfile(int $id, array $data): void
    {
        $name = self::text($data, 'pic_name', 100); $role = self::text($data, 'pic_role', 100);
        $phone = self::text($data, 'pic_phone', 30); $email = self::text($data, 'pic_email', 254);
        if ($phone !== '') $phone = GatewayModel::phone($phone);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Email PIC tidak valid.');
        $tags = array_values(array_unique(array_filter(array_map('trim', explode(',', self::text($data, 'tags', 600))))));
        if (count($tags) > 10 || array_filter($tags, fn($tag) => mb_strlen($tag) > 50)) throw new \InvalidArgumentException('Maksimal 10 label, masing-masing 50 karakter.');
        $this->transaction(function () use ($id,$name,$role,$phone,$email,$tags) {
            $lead = $this->lockLead($id);
            $q = $this->db->prepare('INSERT INTO crm_profiles (lead_id,pic_name,pic_role,pic_phone,pic_email,tags) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE pic_name=VALUES(pic_name),pic_role=VALUES(pic_role),pic_phone=VALUES(pic_phone),pic_email=VALUES(pic_email),tags=VALUES(tags)');
            $q->execute([$id,$name,$role,$phone,$email,json_encode($tags,JSON_THROW_ON_ERROR)]);
            (new CrmModel())->addHistory($id,$lead['lead_status'],$lead['lead_status'],'profile_updated','Kontak PIC dan label diperbarui.');
        });
    }

    private static function token(array $data): string
    {
        $token = $data['request_token'] ?? '';
        if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/D',$token)) throw new \InvalidArgumentException('Muat ulang halaman sebelum menyimpan.');
        return $token;
    }

    public function addTask(int $id, array $data): void
    {
        $title = self::text($data,'title',200,true); $due = self::date(self::text($data,'due_at',16,true),true); $token = self::token($data);
        $this->transaction(function () use ($id,$title,$due,$token) {
            $lead = $this->lockLead($id);
            $q = $this->db->prepare('SELECT lead_id,title,due_at FROM crm_tasks WHERE request_token=?'); $q->execute([$token]);
            if ($existing=$q->fetch()) {
                if ((int)$existing['lead_id']!==$id || $existing['title']!==$title || $existing['due_at']!==$due) throw new \InvalidArgumentException('Permintaan jadwal berubah. Muat ulang halaman.');
                return;
            }
            $q=$this->db->prepare('INSERT INTO crm_tasks (lead_id,title,due_at,request_token) VALUES (?,?,?,?)'); $q->execute([$id,$title,$due,$token]);
            (new CrmModel())->addHistory($id,$lead['lead_status'],$lead['lead_status'],'task_scheduled','Jadwal: '.$title.' - '.$due);
        });
    }

    public function completeTask(int $id, int $task): void
    {
        $this->transaction(function () use ($id,$task) {
            $lead=$this->lockLead($id);
            $find=$this->db->prepare('SELECT id FROM crm_tasks WHERE id=? AND lead_id=?'); $find->execute([$task,$id]);
            if (!$find->fetchColumn()) throw new \InvalidArgumentException('Aktivitas tidak ditemukan pada prospek ini.');
            $q=$this->db->prepare('UPDATE crm_tasks SET completed_at=NOW() WHERE id=? AND lead_id=? AND completed_at IS NULL'); $q->execute([$task,$id]);
            if ($q->rowCount()) {
                $this->db->prepare('UPDATE project_leads SET last_follow_up=NOW(),follow_up_count=follow_up_count+1 WHERE id=?')->execute([$id]);
                (new CrmModel())->addHistory($id,$lead['lead_status'],$lead['lead_status'],'task_completed','Aktivitas terjadwal ditandai selesai.');
            }
        });
    }

    public function addOrder(int $id, array $data): void
    {
        $note=self::text($data,'description',200,true); $date=self::date(self::text($data,'ordered_at',10,true));
        $amount=self::text($data,'amount',20,true);
        if (!preg_match('/^\d{1,13}(?:\.\d{1,2})?$/D',$amount) || (float)$amount<=0 || (float)$amount>9999999999999.99) throw new \InvalidArgumentException('Nilai pembelian harus lebih dari nol dan maksimal 13 digit.');
        $next=self::text($data,'repeat_at',16); $next=$next!=='' ? self::date($next,true) : null;
        if ($next && substr($next,0,10)<$date) throw new \InvalidArgumentException('Jadwal repeat order tidak boleh sebelum tanggal pembelian.');
        $token=self::token($data);
        $this->transaction(function () use ($id,$note,$date,$amount,$next,$token) {
            $lead=$this->lockLead($id);
            $q=$this->db->prepare('SELECT * FROM crm_orders WHERE request_token=?'); $q->execute([$token]);
            if ($old=$q->fetch()) {
                if ((int)$old['lead_id']!==$id || $old['description']!==$note || $old['ordered_at']!==$date || (float)$old['amount']!==(float)$amount || $old['repeat_at']!==$next) throw new \InvalidArgumentException('Permintaan pembelian berubah. Muat ulang halaman.');
                return;
            }
            $q=$this->db->prepare('INSERT INTO crm_orders (lead_id,description,ordered_at,amount,repeat_at,request_token) VALUES (?,?,?,?,?,?)'); $q->execute([$id,$note,$date,$amount,$next,$token]);
            if ($next) {
                $q=$this->db->prepare("INSERT INTO crm_tasks (lead_id,title,due_at,kind,request_token) VALUES (?,?,?,'repeat_order',?)"); $q->execute([$id,'Tawarkan repeat order: '.$note,$next,$token]);
            }
            $q=$this->db->prepare("UPDATE project_leads SET lead_status='closing',updated_at=NOW() WHERE id=?"); $q->execute([$id]);
            (new CrmModel())->addHistory($id,$lead['lead_status'],'closing','order_recorded','Pembelian: '.$note.' - Rp '.$amount,(float)$amount);
        });
    }

    public function tasks(?int $lead = null, string $filter = 'all'): array
    {
        $where=['l.in_crm=1','t.completed_at IS NULL']; $params=[];
        if ($lead!==null) { $where[]='t.lead_id=?'; $params[]=$lead; }
        if ($filter==='today') $where[]='DATE(t.due_at)=CURDATE()';
        if ($filter==='overdue') $where[]='t.due_at<NOW()';
        if ($filter==='upcoming') $where[]='DATE(t.due_at)>CURDATE()';
        $q=$this->db->prepare('SELECT t.*,l.shop_name,p.name AS project_name FROM crm_tasks t JOIN project_leads l ON l.id=t.lead_id JOIN projects p ON p.id=l.project_id WHERE '.implode(' AND ',$where).' ORDER BY t.due_at,t.id LIMIT 200'); $q->execute($params); return $q->fetchAll();
    }

    public function reminderCounts(): array
    {
        return $this->db->query('SELECT SUM(DATE(t.due_at)=CURDATE()) AS today,SUM(t.due_at<NOW()) AS overdue,SUM(t.due_at<CURDATE()+INTERVAL 1 DAY) AS total,SUM(t.kind=\'repeat_order\') AS repeat_count,SUM(DATE(t.due_at)>CURDATE()) AS upcoming,COUNT(*) AS open_count FROM crm_tasks t JOIN project_leads l ON l.id=t.lead_id WHERE l.in_crm=1 AND t.completed_at IS NULL')->fetch();
    }

    public function reminders(string $filter, int $page): array
    {
        $where = match ($filter) {
            'today' => 'DATE(t.due_at)=CURDATE()', 'overdue' => 't.due_at<NOW()',
            'upcoming' => 'DATE(t.due_at)>CURDATE()', 'repeat' => "t.kind='repeat_order'", default => '1=1'
        };
        $q=$this->db->prepare('SELECT t.*,l.shop_name,p.name AS project_name FROM crm_tasks t JOIN project_leads l ON l.id=t.lead_id JOIN projects p ON p.id=l.project_id WHERE l.in_crm=1 AND t.completed_at IS NULL AND '.$where.' ORDER BY t.due_at,t.id LIMIT 51 OFFSET ?');
        $q->bindValue(1,($page-1)*50,\PDO::PARAM_INT); $q->execute(); return $q->fetchAll();
    }

    public function orders(int $id): array
    {
        $q=$this->db->prepare('SELECT * FROM crm_orders WHERE lead_id=? ORDER BY ordered_at DESC,id DESC'); $q->execute([$id]); return $q->fetchAll();
    }

    public function stages(int $project): array
    {
        $stages=CrmModel::STAGES;
        $q=$this->db->prepare('SELECT code,label FROM crm_pipeline WHERE project_id=?'); $q->execute([$project]);
        foreach ($q->fetchAll() as $row) if (isset($stages[$row['code']])) $stages[$row['code']]['label']=$row['label'];
        return $stages;
    }

    public function saveStages(int $project, array $data): void
    {
        $labels=[];
        foreach (CrmModel::STAGES as $code=>$stage) $labels[$code]=self::text($data,$code,60,true);
        if (count(array_unique(array_map('mb_strtolower',$labels)))!==count($labels)) throw new \InvalidArgumentException('Nama setiap tahapan harus berbeda.');
        $this->transaction(function () use ($project,$labels) {
            $q=$this->db->prepare('SELECT id FROM projects WHERE id=? FOR UPDATE'); $q->execute([$project]);
            if (!$q->fetchColumn()) throw new \InvalidArgumentException('Proyek tidak ditemukan.');
            $q=$this->db->prepare('INSERT INTO crm_pipeline (project_id,code,label) VALUES (?,?,?) ON DUPLICATE KEY UPDATE label=VALUES(label)');
            foreach ($labels as $code=>$label) $q->execute([$project,$code,$label]);
        });
    }

    public function directory(string $tag='', bool $customers=false): array
    {
        $q=$this->db->query("SELECT l.id,l.shop_name,l.lead_status,p.name AS project_name,s.*, (SELECT COUNT(*) FROM crm_orders o WHERE o.lead_id=l.id) AS order_count FROM project_leads l JOIN projects p ON p.id=l.project_id LEFT JOIN crm_profiles s ON s.lead_id=l.id WHERE l.in_crm=1 ORDER BY l.updated_at DESC,l.id DESC");
        return array_values(array_filter($q->fetchAll(), static fn($r)=> (!$customers || $r['lead_status']==='closing' || (int)$r['order_count']>0) && ($tag==='' || in_array($tag,json_decode($r['tags']??'[]',true)?:[],true))));
    }

    /** Stable Maps IDs identify matches; shared phone numbers are explicitly only possible matches. */
    public function duplicates(array $leads): array
    {
        if (!$leads) return [];
        $all=$this->db->query('SELECT l.id,l.project_id,l.shop_name,l.phone,l.whatsapp,l.gmap_url,l.in_crm,l.lead_status,l.last_follow_up,p.name AS project_name,(SELECT MAX(created_at) FROM crm_funnel_history h WHERE h.lead_id=l.id) AS last_activity FROM project_leads l JOIN projects p ON p.id=l.project_id')->fetchAll();
        $index=[];
        foreach ($all as $row) {
            $map=MapsIdentity::key($row['gmap_url']); $phone=format_whatsapp($row['phone']?:($row['whatsapp']??''));
            if ($map) $index['map:'.$map][]=$row;
            if (preg_match('/^[1-9][0-9]{7,14}$/D',$phone)) $index['phone:'.$phone][]=$row;
        }
        $result=[];
        foreach ($leads as $lead) {
            $map=MapsIdentity::key($lead['gmap_url']??null); $phone=format_whatsapp($lead['phone']?:($lead['whatsapp']??''));
            $matches=[];
            foreach (['map:'.($map??'')=>'ID Google Maps sama','phone:'.$phone=>'Kemungkinan sama: nomor kontak sama'] as $key=>$reason) foreach ($index[$key]??[] as $row) {
                if ((int)$row['project_id']===(int)$lead['project_id'] || isset($matches[$row['id']])) continue;
                $row['reason']=$reason; $matches[$row['id']]=$row;
            }
            $result[$lead['id']]=array_slice(array_values($matches),0,10);
        }
        return $result;
    }

    public function reports(): array
    {
        $rows=$this->db->query("SELECT p.id,p.name,p.keyword,p.location,COUNT(l.id) AS prospects,SUM(l.in_crm=1) AS crm_total,
            SUM(l.in_crm=1 AND (l.lead_status IN ('contacted','interested','negotiation','closing') OR EXISTS(SELECT 1 FROM crm_funnel_history h WHERE h.lead_id=l.id AND h.to_status IN ('contacted','interested','negotiation','closing')))) AS contacted,
            SUM(l.in_crm=1 AND l.lead_status IN ('interested','negotiation')) AS interested,
            SUM(l.in_crm=1 AND l.lead_status='closing') AS closing,
            SUM(CASE WHEN l.in_crm=1 AND l.lead_status='closing' THEN l.deal_value ELSE 0 END) AS deal_value,
            (SELECT COUNT(*) FROM crm_orders o JOIN project_leads ol ON ol.id=o.lead_id WHERE ol.project_id=p.id) AS orders,
            (SELECT COALESCE(SUM(o.amount),0) FROM crm_orders o JOIN project_leads ol ON ol.id=o.lead_id WHERE ol.project_id=p.id) AS revenue
            FROM projects p LEFT JOIN project_leads l ON l.project_id=p.id GROUP BY p.id,p.name,p.keyword,p.location ORDER BY p.id DESC")->fetchAll();
        return $rows;
    }
}
