<?php
namespace App\Models;

use App\Core\Database;

class BroadcastModel
{
    private static bool $migrated = false;

    public function __construct()
    {
        self::migrate();
    }

    /** Add the mark_contacted column to existing installs; repeatable and non-destructive. */
    private static function migrate(): void
    {
        if (self::$migrated) return;
        $db = Database::getInstance();
        if (!$db->query("SHOW COLUMNS FROM `whatsapp_broadcasts` LIKE 'mark_contacted'")->fetch()) {
            $db->exec("ALTER TABLE `whatsapp_broadcasts` ADD COLUMN `mark_contacted` tinyint(1) NOT NULL DEFAULT '0' AFTER `duplicate_count`");
        }
        self::$migrated = true;
    }

    public function find(string $token): ?array
    {
        $stmt = Database::getInstance()->prepare('SELECT * FROM whatsapp_broadcasts WHERE token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $payload=json_decode($row['recipients'],true,512,JSON_THROW_ON_ERROR);
        // v1.4 stores intent alongside recipients; old array snapshots remain readable.
        $row['intent_hash']=$payload['intent_hash'] ?? null;
        $row['recipients']=$payload['recipients'] ?? $payload;
        return $row;
    }

    public function candidates(int $projectId): array
    {
        $stmt=Database::getInstance()->prepare('SELECT l.id,l.shop_name,l.phone,l.whatsapp,p.name AS project_name FROM project_leads l JOIN projects p ON p.id=l.project_id WHERE l.project_id=? AND l.in_crm=1 AND l.workspace_id=1 AND p.workspace_id=1 ORDER BY l.id');
        $stmt->execute([$projectId]);
        return ContactPolicy::annotate($stmt->fetchAll());
    }

    public function prepare(string $token, int $projectId, array $template, bool $markContacted = false, array $selected = []): array
    {
        if(!$selected || count($selected)>5000) throw new \InvalidArgumentException('Pilih 1 sampai 5.000 penerima broadcast.');
        $ids=[];
        foreach($selected as $id){
            if((!is_int($id) && !is_string($id)) || !filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])) throw new \InvalidArgumentException('Pilihan penerima tidak valid.');
            $ids[(int)$id]=true;
        }
        $canonical=array_keys($ids);sort($canonical,SORT_NUMERIC);
        $intent=hash('sha256',json_encode([$canonical,$template['body'],(int)$template['id'],$markContacted],JSON_THROW_ON_ERROR));
        $leads=array_values(array_filter($this->candidates($projectId),static fn($l)=>isset($ids[(int)$l['id']])));
        if(count($leads)!==count($ids)) throw new \InvalidArgumentException('Penerima berubah atau bukan prospek CRM proyek ini. Muat ulang daftar.');
        $recipients=[];$seen=[];$invalid=0;$duplicates=0;
        foreach($leads as $lead){
            if(!empty($lead['contact_blocked']))continue;
            try{$number=GatewayModel::phone($lead['whatsapp'] ?: ($lead['phone'] ?? ''));}
            catch(\InvalidArgumentException $e){$invalid++;continue;}
            if(isset($seen[$number])){$duplicates++;continue;}$seen[$number]=true;
            $recipients[]=['id'=>(int)$lead['id'],'name'=>$lead['shop_name'],'number'=>$number,
                'message'=>MessageTemplateModel::render($template['body'],$lead['shop_name'],$lead['project_name']),
                'token'=>substr(hash('sha256',$token.':'.$lead['id']),0,32)];
        }
        $existing=$this->find($token);
        if($existing){
            if(($existing['intent_hash']!==null && !hash_equals($existing['intent_hash'],$intent)) || (int)$existing['project_id']!==$projectId || (int)$existing['template_id']!==(int)$template['id'] || $existing['recipients']!==$recipients || (int)$existing['total']!==count($leads) || (bool)$existing['mark_contacted']!==$markContacted)
                throw new \InvalidArgumentException('Isi atau penerima broadcast berubah. Mulai broadcast baru.');
            return $existing;
        }
        $db=Database::getInstance();$prefix=$db->getAttribute(\PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT OR IGNORE':'INSERT IGNORE';
        $insert=$db->prepare($prefix.' INTO whatsapp_broadcasts (token,project_id,template_id,template_name,recipients,total,invalid_count,duplicate_count,mark_contacted) VALUES (?,?,?,?,?,?,?,?,?)');
        $insert->execute([$token,$projectId,$template['id'],$template['name'],json_encode(['intent_hash'=>$intent,'recipients'=>$recipients],JSON_THROW_ON_ERROR),count($leads),$invalid,$duplicates,(int)$markContacted]);
        if(!$insert->rowCount())return $this->prepare($token,$projectId,$template,$markContacted,$selected);
        return $this->find($token);
    }

    public function progress(array $batch): array
    {
        $states = [];
        foreach (array_chunk(array_column($batch['recipients'], 'token'), 500) as $tokens) {
            $stmt = Database::getInstance()->prepare('SELECT request_token, status FROM whatsapp_deliveries WHERE request_token IN (' . implode(',', array_fill(0, count($tokens), '?')) . ')');
            $stmt->execute($tokens);
            foreach ($stmt->fetchAll() as $row) $states[$row['request_token']] = $row['status'];
        }
        $counts = ['accepted' => 0, 'failed' => 0, 'unknown' => 0, 'skipped' => 0];
        $next = null;
        foreach ($batch['recipients'] as $recipient) {
            if (!isset($states[$recipient['token']])) { $next ??= $recipient; continue; }
            $state = $states[$recipient['token']];
            $counts[isset($counts[$state]) ? $state : 'unknown']++;
        }
        return $counts + [
            'token' => $batch['token'], 'project_id' => (int)$batch['project_id'], 'template_id' => (int)$batch['template_id'], 'template_name' => $batch['template_name'],
            'total' => (int)$batch['total'], 'eligible' => count($batch['recipients']),
            'invalid' => (int)$batch['invalid_count'], 'duplicates' => (int)$batch['duplicate_count'],
            'mark_contacted' => (bool)($batch['mark_contacted'] ?? false),
            'blocked' => max(0, (int)$batch['total'] - count($batch['recipients']) - (int)$batch['invalid_count'] - (int)$batch['duplicate_count']),
            'processed' => array_sum($counts), 'remaining' => count($batch['recipients']) - array_sum($counts),
            'next' => $next, 'example' => $batch['recipients'][0]['message'] ?? ''
        ];
    }
}
