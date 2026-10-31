<?php
namespace App\Models;

use PDO;
use DomainException;
use InvalidArgumentException;
use App\Services\StarsenderWebhook;

final class InboxRepository
{
    public function __construct(private PDO $db) {}

    public static function install(PDO $db): void
    {
        $sql=file_get_contents(__DIR__.'/../../database-inbox.sql');
        if ($sql===false) throw new \RuntimeException('Skema kotak masuk tidak ditemukan.');
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') {
            $sql=str_replace(['BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY','UNIQUE KEY inbox_device_message'], ['INTEGER PRIMARY KEY AUTOINCREMENT','UNIQUE'], $sql);
            $sql=preg_replace('/,\s*KEY inbox_lead_time \(lead_id,sent_at\)/','',$sql);
            $sql=str_replace(' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin','',$sql);
        }
        $db->exec($sql);
    }

    private function query(string $sql,array $params=[]): \PDOStatement
    {
        $q=$this->db->prepare($sql);$q->execute($params);return $q;
    }

    public function assertActor(array $actor): void
    {
        $row=$this->query('SELECT role,workspace_id,deleted_at,session_version FROM users WHERE id=?',[$actor['id'] ?? 0])->fetch();
        if (!$row || $row['deleted_at']!==null || (int)$row['workspace_id']!==1
            || (int)($actor['workspace_id'] ?? 0)!==1 || !in_array($row['role'],['admin','user'],true)
            || $row['role']!==($actor['role'] ?? '') || (int)$row['session_version']!==(int)($actor['session_version'] ?? 0)) throw new DomainException('Akses ditolak.');
    }

    /** Duplicate or ambiguous phone matches are deliberately left for admin review. */
    private function matches(string $phone): array
    {
        // SQL narrows candidates by digit suffix; PHP verifies the full normalized number.
        $clean=static fn(string $column): string => "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE($column,''),' ',''),'-',''),'(',''),')',''),'.','')";
        $where=$clean('l.phone').' LIKE ? OR '.$clean('l.whatsapp').' LIKE ?';
        $rows=$this->query("SELECT l.id,l.phone,l.whatsapp,l.shop_name,p.name AS project_name FROM project_leads l JOIN projects p ON p.id=l.project_id WHERE l.workspace_id=1 AND p.workspace_id=1 AND ($where)", ['%'.substr($phone,-6),'%'.substr($phone,-6)])->fetchAll();
        return array_values(array_filter($rows,static fn($r)=>StarsenderWebhook::phone($r['whatsapp'])===$phone || StarsenderWebhook::phone($r['phone'])===$phone));
    }

    public function ingest(array $event): bool
    {
        $matches=$event['contact_phone']!==null ? $this->matches($event['contact_phone']) : [];
        $lead=count($matches)===1 ? (int)$matches[0]['id'] : null;
        if ($event['contact_phone']!==null) {
            $resolved=$this->query('SELECT lead_id FROM whatsapp_inbox_contacts WHERE device_id=? AND contact_phone=?',[$event['device_id'],$event['contact_phone']])->fetchColumn();
            if ($resolved && in_array((int)$resolved,array_map('intval',array_column($matches,'id')),true)) $lead=(int)$resolved;
        }
        $prefix=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT OR IGNORE':'INSERT IGNORE';
        $q=$this->query("$prefix INTO whatsapp_inbox (device_id,message_id,contact_phone,direction,push_name,message,file_url,sent_at,lead_id,received_at) VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$event['device_id'],$event['message_id'],$event['contact_phone'],$event['direction'],$event['push_name'],$event['message'],$event['file_url'],$event['sent_at'],$lead,gmdate('Y-m-d H:i:s')]);
        return $q->rowCount()===1;
    }

    private function scope(array $actor): array
    {
        $this->assertActor($actor);
        if ($actor['role']==='admin') return ['(m.lead_id IS NULL OR (l.workspace_id=1 AND p.workspace_id=1))',[]];
        return ['l.workspace_id=1 AND p.workspace_id=1 AND l.assigned_user_id=?',[(int)$actor['id']]];
    }

    public function listing(array $actor,string $filter='all',int $page=1,?int $lead=null): array
    {
        [$where,$params]=$this->scope($actor);
        if ($filter==='unread') $where.=" AND m.direction='incoming' AND m.reviewed_at IS NULL";
        elseif ($filter==='unmatched') $where.=' AND m.lead_id IS NULL';
        if ($lead!==null) {$where.=' AND m.lead_id=?';$params[]=$lead;}
        $offset=(max(1,min(100000,$page))-1)*50;
        return $this->query("SELECT m.*,l.shop_name,l.assigned_user_id,u.name AS assigned_name FROM whatsapp_inbox m LEFT JOIN project_leads l ON l.id=m.lead_id LEFT JOIN projects p ON p.id=l.project_id LEFT JOIN users u ON u.id=l.assigned_user_id WHERE $where ORDER BY m.sent_at DESC,m.id DESC LIMIT 50 OFFSET $offset",$params)->fetchAll();
    }

    public function review(array $actor,int $id): void
    {
        [$where,$params]=$this->scope($actor);$params[]=$id;
        $found=$this->query("SELECT m.id FROM whatsapp_inbox m LEFT JOIN project_leads l ON l.id=m.lead_id LEFT JOIN projects p ON p.id=l.project_id WHERE $where AND m.id=?",$params)->fetchColumn();
        if (!$found) throw new DomainException('Pesan tidak ditemukan atau akses ditolak.');
        // The assignment predicate is repeated in the write so reassignment revokes permission.
        $whereWrite=$actor['role']==='admin' ? '' : ' AND lead_id IN (SELECT l.id FROM project_leads l JOIN projects p ON p.id=l.project_id WHERE l.workspace_id=1 AND p.workspace_id=1 AND l.assigned_user_id='.(int)$actor['id'].')';
        $this->query("UPDATE whatsapp_inbox SET reviewed_at=?,reviewed_by=? WHERE id=?$whereWrite",[gmdate('Y-m-d H:i:s'),$actor['id'],$id]);
    }

    public function linkCandidates(array $actor,int $id): array
    {
        $this->assertActor($actor);
        if ($actor['role']!=='admin') throw new DomainException('Akses admin diperlukan.');
        $phone=$this->query('SELECT contact_phone FROM whatsapp_inbox WHERE id=? AND lead_id IS NULL',[$id])->fetchColumn();
        return $phone ? $this->matches($phone) : [];
    }

    /** Admin creates a CRM prospect from an already authenticated incoming contact. */
    public function createProspect(array $actor,int $id,int $project,string $name,?int $member): int
    {
        $this->assertActor($actor);
        if ($actor['role']!=='admin') throw new DomainException('Akses admin diperlukan.');
        $name=trim($name);
        if ($name==='' || mb_strlen($name)>200) throw new InvalidArgumentException('Isi nama prospek maksimal 200 karakter.');
        $lock=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql' ? ' FOR UPDATE' : '';
        $contact=$this->query('SELECT contact_phone FROM whatsapp_inbox WHERE id=?',[$id])->fetchColumn();
        if (!$contact) throw new InvalidArgumentException('Pilih pesan masuk dengan kontak yang diketahui.');
        $this->db->beginTransaction();
        try {
            // Always acquire the oldest row for this phone before any selected anchor.
            // This serializes creation across anchors and device identities without lock inversion.
            $this->query('SELECT id FROM whatsapp_inbox WHERE contact_phone=? ORDER BY id LIMIT 1'.$lock,[$contact])->fetch();
            $this->assertActor($actor);
            $message=$this->query('SELECT * FROM whatsapp_inbox WHERE id=?'.$lock,[$id])->fetch();
            if (!$message || !$message['contact_phone'] || $message['direction']!=='incoming') throw new InvalidArgumentException('Pilih pesan masuk dengan kontak yang diketahui.');
            if ($message['lead_id']!==null) {
                $existing=$this->query('SELECT l.id FROM project_leads l JOIN projects p ON p.id=l.project_id WHERE l.id=? AND l.workspace_id=1 AND p.workspace_id=1',[$message['lead_id']])->fetchColumn();
                if (!$existing) throw new DomainException('Prospek tidak tersedia.');
                $this->db->commit(); return (int)$existing;
            }
            $p=$this->query('SELECT id FROM projects WHERE id=? AND workspace_id=1'.$lock,[$project])->fetchColumn();
            if (!$p) throw new InvalidArgumentException('Pilih proyek yang tersedia.');
            if ($member!==null) {
                $m=$this->query("SELECT id FROM users WHERE id=? AND workspace_id=1 AND role='user' AND deleted_at IS NULL".$lock,[$member])->fetchColumn();
                if (!$m) throw new InvalidArgumentException('Anggota tidak tersedia.');
            }
            if ($this->matches($message['contact_phone'])) throw new InvalidArgumentException('Nomor ini sudah ada pada prospek. Pilih Hubungkan ke prospek.');
            $now=date('Y-m-d H:i:s');
            $this->query("INSERT INTO project_leads (project_id,workspace_id,assigned_user_id,shop_name,phone,whatsapp,in_crm,crm_added_at,lead_status,notes,deal_value) VALUES (?,1,?,?,?,?,1,?,'new','',0)",
                [$project,$member,$name,$message['contact_phone'],$message['contact_phone'],$now]);
            $lead=(int)$this->db->lastInsertId();
            $this->query('INSERT INTO crm_lead_sources (lead_id,source) VALUES (?,?)',[$lead,'whatsapp']);
            $this->query("INSERT INTO crm_funnel_history (lead_id,from_status,to_status,action_type,notes,actor_user_id) VALUES (?,NULL,'new','moved_to_crm',?,?)",[$lead,'Prospek dibuat dari percakapan WhatsApp.',$actor['id']]);
            $this->persistLink($actor,$message,$lead);
            $this->query('UPDATE projects SET total_leads=(SELECT COUNT(*) FROM project_leads WHERE project_id=?) WHERE id=?',[$project,$project]);
            $this->db->commit();return $lead;
        } catch (\Throwable $e) {if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    private function persistLink(array $actor,array $message,int $lead): void
    {
        $suffix=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'
            ? ' ON CONFLICT(device_id,contact_phone) DO UPDATE SET lead_id=excluded.lead_id,linked_by=excluded.linked_by,linked_at=excluded.linked_at'
            : ' ON DUPLICATE KEY UPDATE lead_id=VALUES(lead_id),linked_by=VALUES(linked_by),linked_at=VALUES(linked_at)';
        $this->query('INSERT INTO whatsapp_inbox_contacts (device_id,contact_phone,lead_id,linked_by,linked_at) VALUES (?,?,?,?,?)'.$suffix,
            [$message['device_id'],$message['contact_phone'],$lead,$actor['id'],gmdate('Y-m-d H:i:s')]);
        $this->query('UPDATE whatsapp_inbox SET lead_id=? WHERE device_id=? AND contact_phone=? AND lead_id IS NULL',[$lead,$message['device_id'],$message['contact_phone']]);
    }

    /** Re-check one stored conversation; only an exact unique contact may be linked. */
    public function reconcile(array $actor,int $id): int
    {
        $this->assertActor($actor);
        if($actor['role']!=='admin')throw new DomainException('Akses admin diperlukan.');
        [$scope,$params]=$this->scope($actor);$params[]=$id;
        $message=$this->query("SELECT m.* FROM whatsapp_inbox m LEFT JOIN project_leads l ON l.id=m.lead_id LEFT JOIN projects p ON p.id=l.project_id WHERE $scope AND m.id=?",$params)->fetch();
        if(!$message || !$message['contact_phone'])throw new InvalidArgumentException('Penerima belum diketahui; tidak dapat dicocokkan.');
        $matches=$this->matches($message['contact_phone']);
        if(count($matches)!==1)throw new InvalidArgumentException($matches?'Nomor cocok dengan beberapa prospek. Pilih proyek dan prospek yang tepat.':'Nomor belum cocok dengan prospek. Periksa nomor pada data proyek atau buat prospek baru.');
        $lead=(int)$matches[0]['id'];
        $this->link($actor,$id,$lead);
        return $lead;
    }

    public function link(array $actor,int $id,int $lead): void
    {
        $this->assertActor($actor);
        if ($actor['role']!=='admin') throw new DomainException('Akses admin diperlukan.');
        [$scope,$params]=$this->scope($actor);$params[]=$id;
        $message=$this->query("SELECT m.* FROM whatsapp_inbox m LEFT JOIN project_leads l ON l.id=m.lead_id LEFT JOIN projects p ON p.id=l.project_id WHERE $scope AND m.id=?",$params)->fetch();
        if (!$message || !$message['contact_phone']) throw new InvalidArgumentException('Kontak penerima belum diketahui.');
        $matches=$this->matches($message['contact_phone']);
        if (!in_array($lead,array_map('intval',array_column($matches,'id')),true)) throw new InvalidArgumentException('Nomor prospek tidak cocok dengan percakapan.');
        $this->db->beginTransaction();
        try {
            $this->assertActor($actor);
            $this->persistLink($actor,$message,$lead);
            $this->query('UPDATE project_leads SET in_crm=1,crm_added_at=COALESCE(crm_added_at,?) WHERE id=? AND workspace_id=1',[date('Y-m-d H:i:s'),$lead]);
            $this->db->commit();
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
}
