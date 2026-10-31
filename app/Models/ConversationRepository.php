<?php
namespace App\Models;
use PDO;

/** Scoped read model shared by Inbox and CRM; unidentified outbound never guesses a contact. */
final class ConversationRepository
{
    public function __construct(private PDO $db) {}
    private function query(string $sql,array $params=[]): \PDOStatement {
        $q=$this->db->prepare($sql);$q->execute($params);return $q;
    }
    private function scope(array $actor,string $prefix='l'): array {
        (new InboxRepository($this->db))->assertActor($actor);
        $where="$prefix.workspace_id=1 AND p.workspace_id=1";$params=[];
        if($actor['role']!=='admin'){$where.=" AND $prefix.assigned_user_id=?";$params[]=(int)$actor['id'];}
        return [$where,$params];
    }
    private function normalizedPhone(string $column): string {
        $clean="COALESCE($column,'')";
        foreach(['+',' ','.','-','(',')'] as $char)$clean="REPLACE($clean,'$char','')";
        $prefix=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?"'62'||SUBSTR($clean,2)":"CONCAT('62',SUBSTR($clean,2))";
        return "CASE WHEN SUBSTR($clean,1,1)='0' THEN $prefix ELSE $clean END";
    }
    /** Legacy delivery times are local Asia/Jakarta, webhook times are UTC. */
    private function stream(array $actor): array {
        [$leadScope,$leadParams]=$this->scope($actor);
        $webScope=$actor['role']==='admin'?"(m.lead_id IS NULL OR ($leadScope))":$leadScope;
        $webParams=$actor['role']==='admin'?[]:$leadParams;
        $time=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?"datetime(d.created_at,'-7 hours')":"DATE_SUB(d.created_at,INTERVAL 7 HOUR)";
        $phones='(d.recipient=('.$this->normalizedPhone('l.phone').') OR d.recipient=('.$this->normalizedPhone('l.whatsapp').'))';
        $sql="SELECT m.id,'webhook' AS source,m.lead_id,m.device_id,m.contact_phone,m.push_name,m.message,m.direction,m.file_url,m.sent_at AS occurred_at,m.reviewed_at,NULL AS delivery_status,
            CASE WHEN m.lead_id IS NULL THEN m.device_id ELSE '' END AS group_device,
            CASE WHEN m.lead_id IS NULL THEN COALESCE(m.contact_phone,'') ELSE '' END AS group_phone,
            CASE WHEN m.contact_phone IS NULL THEN m.id ELSE 0 END AS orphan
            FROM whatsapp_inbox m LEFT JOIN project_leads l ON l.id=m.lead_id LEFT JOIN projects p ON p.id=l.project_id WHERE $webScope
            UNION ALL
            SELECT d.id,'crm',d.lead_id,NULL,d.recipient,'',d.message,'outgoing',NULL,$time,NULL,d.status,'','',0
            FROM whatsapp_deliveries d JOIN project_leads l ON l.id=d.lead_id JOIN projects p ON p.id=l.project_id WHERE $leadScope AND $phones";
        return [$sql,array_merge($webParams,$leadParams)];
    }
    public function groups(array $actor,string $filter='all',int $page=1,string $search=''): array {
        [$stream,$params]=$this->stream($actor);
        if (mb_strlen($search)>100)throw new \InvalidArgumentException('Pencarian maksimal 100 karakter.');
        $partition='lead_id,group_device,group_phone,orphan';
        $where=match($filter){'unknown'=>'r.orphan>0','unmatched'=>'r.lead_id IS NULL AND r.orphan=0','unread'=>'r.unread_count>0 AND r.orphan=0','needs_reply'=>"r.last_incoming IS NOT NULL AND (r.last_reply IS NULL OR r.last_incoming>r.last_reply) AND r.orphan=0",default=>'r.orphan=0'};
        if($search!==''){$where.=' AND (r.contact_phone LIKE ? OR r.push_name LIKE ? OR l.shop_name LIKE ? OR p.name LIKE ?)';array_push($params,...array_fill(0,4,'%'.$search.'%'));}
        $offset=(max(1,min(100000,$page))-1)*50;
        return $this->query("WITH events AS ($stream), ranked AS (
            SELECT events.*,ROW_NUMBER() OVER(PARTITION BY $partition ORDER BY occurred_at DESC,source DESC,id DESC) AS rn,
            COUNT(*) OVER(PARTITION BY $partition) AS message_count,
            SUM(CASE WHEN source='webhook' AND direction='incoming' AND reviewed_at IS NULL THEN 1 ELSE 0 END) OVER(PARTITION BY $partition) AS unread_count,
            MAX(CASE WHEN direction='incoming' THEN occurred_at ELSE NULL END) OVER(PARTITION BY $partition) AS last_incoming,
            MAX(CASE WHEN direction='outgoing' AND (source='webhook' OR delivery_status='accepted') THEN occurred_at ELSE NULL END) OVER(PARTITION BY $partition) AS last_reply FROM events)
            SELECT r.*,l.shop_name,p.name AS project_name,u.name AS assigned_name FROM ranked r
            LEFT JOIN project_leads l ON l.id=r.lead_id LEFT JOIN projects p ON p.id=l.project_id LEFT JOIN users u ON u.id=l.assigned_user_id
            WHERE r.rn=1 AND $where ORDER BY r.occurred_at DESC,r.source DESC,r.id DESC LIMIT 50 OFFSET $offset",$params)->fetchAll();
    }
    public function thread(array $actor,?int $anchor,?int $lead,int $page=1,int $limit=50): ?array {
        [$leadScope,$leadParams]=$this->scope($actor);
        $context=null;
        if($lead!==null){
            $r=$this->query("SELECT l.*,p.name AS project_name,u.name AS assigned_name FROM project_leads l JOIN projects p ON p.id=l.project_id LEFT JOIN users u ON u.id=l.assigned_user_id WHERE $leadScope AND l.id=?",array_merge($leadParams,[$lead]))->fetch();
            if(!$r)return null;
            $incomingAnchor=$this->query("SELECT id FROM whatsapp_inbox WHERE lead_id=? AND direction='incoming' AND contact_phone IS NOT NULL ORDER BY id LIMIT 1",[$lead])->fetchColumn();
            $context=['lead'=>$r,'lead_id'=>(int)$r['id'],'phone'=>\App\Services\StarsenderWebhook::phone($r['whatsapp']) ?? \App\Services\StarsenderWebhook::phone($r['phone']),'name'=>\App\Services\ContactLabel::name($r['shop_name'],null,$r['whatsapp']?:$r['phone']),'anchor'=>$incomingAnchor?(int)$incomingAnchor:null,'device'=>null];
        }elseif($anchor!==null){
            $webScope=$actor['role']==='admin'?"(m.lead_id IS NULL OR ($leadScope))":$leadScope;
            $r=$this->query("SELECT m.* FROM whatsapp_inbox m LEFT JOIN project_leads l ON l.id=m.lead_id LEFT JOIN projects p ON p.id=l.project_id WHERE $webScope AND m.id=?",array_merge($leadParams,[$anchor]))->fetch();
            if(!$r)return null;
            if($r['lead_id']!==null)return $this->thread($actor,null,(int)$r['lead_id'],$page,$limit);
            $context=['lead'=>null,'lead_id'=>null,'phone'=>$r['contact_phone'],'name'=>\App\Services\ContactLabel::name(null,$r['push_name'],$r['contact_phone']),'anchor'=>(int)$r['id'],'device'=>$r['device_id']];
        }else return null;
        [$stream,$params]=$this->stream($actor);
        if($context['lead_id']!==null){$where='lead_id=?';$params[]=$context['lead_id'];}
        elseif($context['phone']!==null){$where="source='webhook' AND lead_id IS NULL AND device_id=? AND contact_phone=?";array_push($params,$context['device'],$context['phone']);}
        else{$where="source='webhook' AND lead_id IS NULL AND contact_phone IS NULL AND id=?";$params[]=$context['anchor'];}
        $limit=max(1,min(50,$limit));$offset=(max(1,min(100000,$page))-1)*$limit;$take=$limit+1;
        $events=$this->query("SELECT * FROM ($stream) AS events WHERE $where ORDER BY occurred_at DESC,source DESC,id DESC LIMIT $take OFFSET $offset",$params)->fetchAll();
        $hasOlder=count($events)>$limit;$events=array_reverse(array_slice($events,0,$limit));
        return ['context'=>$context,'events'=>$events,'hasOlder'=>$hasOlder];
    }
    public function options(array $actor): array {
        (new InboxRepository($this->db))->assertActor($actor);
        if($actor['role']!=='admin')return ['projects'=>[],'members'=>[]];
        return ['projects'=>$this->query('SELECT id,name FROM projects WHERE workspace_id=1 ORDER BY name')->fetchAll(),
            'members'=>$this->query("SELECT id,name FROM users WHERE workspace_id=1 AND role='user' AND deleted_at IS NULL ORDER BY name")->fetchAll()];
    }
    /** Based on recorded timestamps only; not a WhatsApp read/delivery receipt. */
    public function needsReplyCount(array $actor): int
    {
        [$stream,$params]=$this->stream($actor);
        return (int)$this->query("WITH events AS ($stream) SELECT COUNT(*) FROM (
            SELECT lead_id,group_device,group_phone,orphan,
            MAX(CASE WHEN direction='incoming' THEN occurred_at ELSE NULL END) AS last_incoming,
            MAX(CASE WHEN direction='outgoing' AND (source='webhook' OR delivery_status='accepted') THEN occurred_at ELSE NULL END) AS last_reply
            FROM events WHERE orphan=0 GROUP BY lead_id,group_device,group_phone,orphan
            HAVING last_incoming IS NOT NULL AND (last_reply IS NULL OR last_incoming>last_reply)) AS waiting",$params)->fetchColumn();
    }
    public function reminderCount(array $actor): int {
        [$scope,$params]=$this->scope($actor);$params[]=date('Y-m-d',strtotime('tomorrow')).' 00:00:00';
        return (int)$this->query("SELECT COUNT(*) FROM crm_tasks t JOIN project_leads l ON l.id=t.lead_id JOIN projects p ON p.id=l.project_id WHERE $scope AND l.in_crm=1 AND t.completed_at IS NULL AND t.due_at<?",$params)->fetchColumn();
    }
}
