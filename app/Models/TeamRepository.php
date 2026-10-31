<?php
namespace App\Models;

use PDO;
use DomainException;
use InvalidArgumentException;

/** Single-workspace team surface. Never call unscoped legacy repositories as a member. */
final class TeamRepository
{
    public function __construct(private PDO $db, private array $actor)
    {
        if ((int)($actor['id'] ?? 0)<1 || (int)($actor['workspace_id'] ?? 0)!==1
            || !in_array($actor['role'] ?? '', ['admin','user'],true)) {
            throw new DomainException('Akses ditolak.');
        }
    }

    public function isAdmin(): bool { return $this->actor['role']==='admin'; }

    private function admin(): void
    {
        if (!$this->isAdmin()) throw new DomainException('Akses admin diperlukan.');
    }

    private function scope(): array
    {
        $where='l.workspace_id=? AND p.workspace_id=l.workspace_id';
        $params=[(int)$this->actor['workspace_id']];
        if (!$this->isAdmin()) { $where.=' AND l.assigned_user_id=?'; $params[]=(int)$this->actor['id']; }
        return [$where,$params];
    }

    private function query(string $sql, array $params=[]): \PDOStatement
    {
        $q=$this->db->prepare($sql); $q->execute($params); return $q;
    }

    private function locking(): string
    {
        return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql' ? ' FOR UPDATE' : '';
    }

    private function transaction(callable $action): mixed
    {
        $this->db->beginTransaction();
        try {
            $current=$this->query('SELECT role,workspace_id,deleted_at,session_version FROM users WHERE id=?'.$this->locking(),[$this->actor['id']])->fetch();
            if (!$current || $current['deleted_at']!==null || $current['role']!==$this->actor['role'] || (int)$current['session_version']!==(int)($this->actor['session_version'] ?? 0) || (int)$current['workspace_id']!==(int)$this->actor['workspace_id']) throw new DomainException('Sesi akun tidak lagi berlaku.');
            $result=$action(); $this->db->commit(); return $result;
        }
        catch (\Throwable $e) { if($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function listing(string $search='',int $page=1,int $limit=50): array
    {
        [$where,$params]=$this->scope();
        $search=trim($search);
        if (strlen($search)>400) throw new InvalidArgumentException('Pencarian terlalu panjang.');
        if ($search!=='') {
            $where.=' AND (l.shop_name LIKE ? OR l.phone LIKE ? OR p.name LIKE ?)';
            array_push($params,'%'.$search.'%','%'.$search.'%','%'.$search.'%');
        }
        $limit=max(1,min(100,$limit)); $offset=(max(1,min(100000,$page))-1)*$limit;
        // Do not select projects.total_leads: it would reveal team-wide counts to members.
        return $this->query("SELECT l.*,p.name AS project_name,u.name AS assigned_name FROM project_leads l
            JOIN projects p ON p.id=l.project_id LEFT JOIN users u ON u.id=l.assigned_user_id
            WHERE $where ORDER BY l.id DESC LIMIT $limit OFFSET $offset",$params)->fetchAll();
    }

    public function lead(int $id,bool $lock=false): ?array
    {
        [$where,$params]=$this->scope(); $params[]=$id;
        $row=$this->query("SELECT l.*,p.name AS project_name FROM project_leads l JOIN projects p ON p.id=l.project_id
            WHERE $where AND l.id=?".($lock?$this->locking():''),$params)->fetch();
        return $row ?: null;
    }

    private function requireLead(int $id,bool $lock=false): array
    {
        return $this->lead($id,$lock) ?? throw new DomainException('Prospek tidak ditemukan atau akses ditolak.');
    }

    public function summary(): array
    {
        [$where,$params]=$this->scope();
        return $this->query("SELECT COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN l.lead_status='new' THEN 1 ELSE 0 END),0) AS new_total,
            COALESCE(SUM(CASE WHEN l.lead_status='closing' THEN 1 ELSE 0 END),0) AS closing_total,
            COALESCE(SUM(CASE WHEN l.lead_status='closing' THEN l.deal_value ELSE 0 END),0) AS deal_total
            FROM project_leads l JOIN projects p ON p.id=l.project_id WHERE $where",$params)->fetch();
    }

    public function exportRows(): array
    {
        [$where,$params]=$this->scope();
        return $this->query("SELECT l.id,l.shop_name,p.name AS project_name,l.phone,l.address,l.website,l.lead_status,l.notes,l.deal_value
            FROM project_leads l JOIN projects p ON p.id=l.project_id WHERE $where ORDER BY l.id",$params)->fetchAll();
    }

    private function audit(string $action,array $details,?int $lead=null): void
    {
        $this->query('INSERT INTO team_audit (workspace_id,actor_user_id,lead_id,action,details) VALUES (?,?,?,?,?)',
            [$this->actor['workspace_id'],$this->actor['id'],$lead,$action,json_encode($details,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
    }

    private function historyEntry(array $lead,string $action,string $note,?string $status=null,?float $value=null): void
    {
        $this->query('INSERT INTO crm_funnel_history (lead_id,from_status,to_status,action_type,notes,deal_value,actor_user_id) VALUES (?,?,?,?,?,?,?)',
            [$lead['id'],$lead['lead_status'],$status ?? $lead['lead_status'],$action,$note,$value ?? $lead['deal_value'],$this->actor['id']]);
    }

    public function history(int $id): array
    {
        $this->requireLead($id);
        [$where,$params]=$this->scope(); $params[]=$id;
        return $this->query("SELECT h.*,u.name AS actor_name FROM crm_funnel_history h
            JOIN project_leads l ON l.id=h.lead_id JOIN projects p ON p.id=l.project_id
            LEFT JOIN users u ON u.id=h.actor_user_id WHERE $where AND l.id=? ORDER BY h.id DESC LIMIT 100",$params)->fetchAll();
    }

    public function updateLead(int $id,array $data): void
    {
        $status=$data['status'] ?? '';
        $notes=$data['notes'] ?? '';
        $amount=$data['deal_value'] ?? '0';
        if (!is_string($status) || !isset(CrmModel::STAGES[$status])) throw new InvalidArgumentException('Status tidak valid.');
        if (!is_string($notes) || strlen($notes)>20000) throw new InvalidArgumentException('Catatan maksimal 20000 byte.');
        if (!is_string($amount) || !preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D',$amount)) throw new InvalidArgumentException('Nilai deal tidak valid.');
        $this->transaction(function() use($id,$status,$notes,$amount):void {
            $lead=$this->requireLead($id,true);
            $this->query('UPDATE project_leads SET lead_status=?,notes=?,deal_value=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$status,trim($notes),$amount,$id]);
            $this->historyEntry($lead,'team_update',trim($notes),$status,(float)$amount);
        });
    }

    public function tasks(int $id): array
    {
        $this->requireLead($id);
        [$where,$params]=$this->scope(); $params[]=$id;
        return $this->query("SELECT t.* FROM crm_tasks t JOIN project_leads l ON l.id=t.lead_id
            JOIN projects p ON p.id=l.project_id WHERE $where AND l.id=? ORDER BY t.completed_at,t.due_at,t.id LIMIT 200",$params)->fetchAll();
    }

    public function addTask(int $id,string $title,string $due): void
    {
        $title=trim($title);
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$due);
        if ($title==='' || strlen($title)>240 || !$date || $date->format('Y-m-d\TH:i')!==$due) throw new InvalidArgumentException('Judul atau waktu aktivitas tidak valid.');
        $this->transaction(function() use($id,$title,$date):void {
            $lead=$this->requireLead($id,true);
            $this->query('INSERT INTO crm_tasks (lead_id,title,due_at,request_token) VALUES (?,?,?,?)',[$id,$title,$date->format('Y-m-d H:i:s'),bin2hex(random_bytes(16))]);
            $this->historyEntry($lead,'task_scheduled',$title.' - '.$date->format('Y-m-d H:i'));
        });
    }

    public function completeTask(int $id,int $task): void
    {
        $this->transaction(function() use($id,$task):void {
            $lead=$this->requireLead($id,true);
            if (!$this->query('SELECT id FROM crm_tasks WHERE id=? AND lead_id=?',[$task,$id])->fetchColumn()) throw new DomainException('Aktivitas tidak ditemukan.');
            $q=$this->query('UPDATE crm_tasks SET completed_at=CURRENT_TIMESTAMP WHERE id=? AND lead_id=? AND completed_at IS NULL',[$task,$id]);
            if ($q->rowCount()) {
                $this->query('UPDATE project_leads SET last_follow_up=CURRENT_TIMESTAMP,follow_up_count=follow_up_count+1 WHERE id=?',[$id]);
                $this->historyEntry($lead,'task_completed','Aktivitas #'.$task.' selesai.');
            }
        });
    }

    public function attachments(int $id): array
    {
        $this->requireLead($id);
        [$where,$params]=$this->scope(); $params[]=$id;
        return $this->query("SELECT a.id,a.name,a.mime,a.size,a.created_at FROM crm_attachments a
            JOIN project_leads l ON l.id=a.lead_id JOIN projects p ON p.id=l.project_id
            WHERE $where AND l.id=? ORDER BY a.id DESC",$params)->fetchAll();
    }

    public function attachment(int $id): ?array
    {
        [$where,$params]=$this->scope(); $params[]=$id;
        $row=$this->query("SELECT a.* FROM crm_attachments a JOIN project_leads l ON l.id=a.lead_id
            JOIN projects p ON p.id=l.project_id WHERE $where AND a.id=?",$params)->fetch();
        if (!$row) return null;
        // Legacy uploads keep bytes in chunks. Ownership remains checked in the chunks query too.
        if ($row['content']==='' || $row['content']===null) {
            $row['content']=implode('', $this->query("SELECT c.content FROM crm_attachment_chunks c
                JOIN crm_attachments a ON a.id=c.attachment_id JOIN project_leads l ON l.id=a.lead_id
                JOIN projects p ON p.id=l.project_id WHERE $where AND a.id=? ORDER BY c.chunk_no",$params)->fetchAll(PDO::FETCH_COLUMN));
        }
        return $row;
    }

    public function attach(int $id,string $path,string $name): void
    {
        $size=is_file($path)?filesize($path):0;
        if ($size<1 || $size>5*1024*1024) throw new InvalidArgumentException('Lampiran maksimal 5 MB.');
        $name=basename(str_replace('\\','/',$name));
        $name=preg_replace('/[\x00-\x1f\x7f]/','',$name);
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $allowed=['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'];
        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if ($name==='' || strlen($name)>200 || ($allowed[$ext] ?? '')!==$mime) throw new InvalidArgumentException('Gunakan PDF, JPG, atau PNG yang valid.');
        if ($mime!=='application/pdf' && !@getimagesize($path)) throw new InvalidArgumentException('Gambar tidak valid.');
        $content=file_get_contents($path);
        $this->transaction(function() use($id,$name,$mime,$size,$content):void {
            $lead=$this->requireLead($id,true);
            $this->query("INSERT INTO crm_attachments (lead_id,name,mime,size,content) VALUES (?,?,?,?,'')",[$id,$name,$mime,$size]);
            $attachment=(int)$this->db->lastInsertId();
            \App\Services\PrivateStorage::write($this->db,'attachment',$attachment,$content);
            $this->historyEntry($lead,'attachment_added','Lampiran: '.$name);
        });
    }

    public function deleteAttachment(int $id): int
    {
        return $this->transaction(function() use($id):int {
            $row=$this->attachment($id);
            if (!$row) throw new DomainException('Lampiran tidak ditemukan.');
            $lead=$this->requireLead((int)$row['lead_id'],true);
            $this->query('DELETE FROM crm_attachment_chunks WHERE attachment_id=?',[$id]);
            $this->query('DELETE FROM crm_attachments WHERE id=?',[$id]);
            $this->historyEntry($lead,'attachment_removed','Lampiran: '.$row['name']);
            return (int)$row['lead_id'];
        });
    }

    public function members(): array
    {
        $this->admin();
        return $this->query("SELECT id,username,email,name,role,deleted_at FROM users WHERE workspace_id=? AND role='user' ORDER BY deleted_at,name",[$this->actor['workspace_id']])->fetchAll();
    }

    public function assign(array $ids,?int $member): int
    {
        $this->admin();
        $ids=array_values(array_unique(array_map('intval',$ids)));
        if (!$ids || count($ids)>200 || min($ids)<1) throw new InvalidArgumentException('Pilih 1 sampai 200 prospek.');
        sort($ids);
        return $this->transaction(function() use($ids,$member):int {
            if ($member!==null && !$this->query("SELECT id FROM users WHERE id=? AND workspace_id=? AND role='user' AND deleted_at IS NULL".$this->locking(),[$member,$this->actor['workspace_id']])->fetchColumn()) throw new DomainException('Anggota tidak tersedia.');
            $leads=[];
            foreach($ids as $id) $leads[]=$this->requireLead($id,true);
            foreach($leads as $lead) {
                $crm=$member!==null ? ',in_crm=1,crm_added_at=COALESCE(crm_added_at,CURRENT_TIMESTAMP)' : '';
                $this->query('UPDATE project_leads SET assigned_user_id=?,updated_at=CURRENT_TIMESTAMP'.$crm.' WHERE id=?',[$member,$lead['id']]);
                $this->audit('lead_assigned',['from'=>$lead['assigned_user_id'],'to'=>$member],(int)$lead['id']);
                $this->historyEntry($lead,'assignment_changed','Penanggung jawab: '.($member===null?'belum ditugaskan':'akun #'.$member));
            }
            return count($leads);
        });
    }

    public static function validatePassword(string $password): void
    {
        if (strlen($password)<12 || strlen($password)>72 || str_contains($password,"\0")) throw new InvalidArgumentException('Password harus 12–72 byte.');
    }

    public function createMember(string $username,string $email,string $name,string $password): int
    {
        $this->admin();
        if(\App\Services\PlatformContext::enabled())throw new InvalidArgumentException('Gunakan Undang anggota pada menu Akun & tim agar akun terverifikasi.');
        self::validatePassword($password);
        $username=trim($username);$email=trim($email);$name=trim($name);
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/D',$username) || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>100 || $name==='' || strlen($name)>100) throw new InvalidArgumentException('Nama, username, atau email tidak valid.');
        return $this->transaction(function() use($username,$email,$name,$password):int {
            if ($this->query('SELECT id FROM users WHERE username IN (?,?) OR email IN (?,?)',[$username,$email,$username,$email])->fetchColumn()) throw new InvalidArgumentException('Username atau email sudah digunakan.');
            $this->query("INSERT INTO users (username,email,name,password,role,workspace_id) VALUES (?,?,?,?,'user',?)",[$username,$email,$name,password_hash_secure($password),$this->actor['workspace_id']]);
            $id=(int)$this->db->lastInsertId();$this->audit('member_created',['user_id'=>$id]);return $id;
        });
    }

    private function member(int $id): array
    {
        return $this->query("SELECT * FROM users WHERE id=? AND workspace_id=? AND role='user' AND deleted_at IS NULL".$this->locking(),[$id,$this->actor['workspace_id']])->fetch() ?: throw new DomainException('Anggota tidak ditemukan.');
    }

    public function disableMember(int $id): void
    {
        $this->admin();
        $this->transaction(function() use($id):void {
            $this->member($id);
            $this->query('UPDATE users SET deleted_at=CURRENT_TIMESTAMP,session_version=session_version+1 WHERE id=?',[$id]);
            $this->query('UPDATE project_leads SET assigned_user_id=NULL WHERE workspace_id=? AND assigned_user_id=?',[$this->actor['workspace_id'],$id]);
            $this->audit('member_disabled',['user_id'=>$id,'assignments_released'=>true]);
        });
    }

    public function resetMemberPassword(int $id,string $password): void
    {
        $this->admin();self::validatePassword($password);
        $this->transaction(function() use($id,$password):void {
            $this->member($id);
            $this->query('UPDATE users SET password=?,session_version=session_version+1 WHERE id=?',[password_hash_secure($password),$id]);
            $this->audit('member_password_reset',['user_id'=>$id]);
        });
    }
}
