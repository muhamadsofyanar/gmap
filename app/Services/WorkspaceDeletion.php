<?php
namespace App\Services;

use PDO;
use App\Models\ProspectModel;

class WorkspaceDeletion
{
    public static function intake(PDO $db, int $project): void {
        $q=$db->prepare('SELECT token,project_id,result FROM crm_intake_batches WHERE project_id=? OR (project_id=0 AND result IS NOT NULL) FOR UPDATE');$q->execute([$project]);
        foreach ($q->fetchAll() as $batch) {
            $result=json_decode($batch['result'] ?? '',true);
            if ((int)$batch['project_id']===$project || (int)($result['project_id'] ?? 0)===$project) {
                $db->prepare('DELETE FROM crm_intake_batches WHERE token=?')->execute([$batch['token']]);
            }
        }
    }

    public static function leads(PDO $db, array $ids): int {
        if (!$db->inTransaction()) throw new \LogicException('Penghapusan prospek memerlukan transaksi.');
        $removed=0; $projects=[];
        foreach (array_chunk($ids,100) as $batch) {
            if (!$batch) continue;
            $marks=implode(',',array_fill(0,count($batch),'?'));
            $q=$db->prepare("SELECT l.*,p.keyword,p.location FROM project_leads l JOIN projects p ON p.id=l.project_id WHERE l.id IN ($marks) ORDER BY l.id FOR UPDATE"); $q->execute($batch);
            $leads=$q->fetchAll();
            $q=$db->prepare("SELECT status FROM whatsapp_deliveries WHERE lead_id IN ($marks) FOR UPDATE"); $q->execute($batch);
            if (in_array('sending',$q->fetchAll(PDO::FETCH_COLUMN),true)) throw new \DomainException('Pesan prospek sedang dikirim. Coba hapus setelah selesai.');
            foreach ($leads as $lead) {
                $projects[(int)$lead['project_id']]=true;
                $db->prepare('DELETE FROM crm_contact_blocks WHERE contact_key=?')->execute(['lead:'.$lead['id']]);
                // Phone-level opt-outs are workspace preferences shared by other contacts.
                $db->prepare("DELETE FROM prospect_cache WHERE search_key=? AND shop_name=? AND COALESCE(address,'')=COALESCE(?,'')")
                    ->execute([ProspectModel::makeSearchKey($lead['keyword'],$lead['location']),$lead['shop_name'],$lead['address']]);
            }
            $db->prepare("DELETE FROM whatsapp_deliveries WHERE lead_id IN ($marks)")->execute($batch);
            // FK cascades remove CRM profiles, tasks, orders, history, attachments and binary chunks.
            $q=$db->prepare("DELETE FROM project_leads WHERE id IN ($marks)");$q->execute($batch);$removed+=$q->rowCount();
        }
        foreach (array_keys($projects) as $project) {
            $db->prepare('UPDATE projects SET total_leads=(SELECT COUNT(*) FROM project_leads WHERE project_id=?) WHERE id=?')->execute([$project,$project]);
            $q=$db->prepare('SELECT token,recipients FROM whatsapp_broadcasts WHERE project_id=? FOR UPDATE');$q->execute([$project]);
            foreach ($q->fetchAll() as $broadcast) {
                $recipients=json_decode($broadcast['recipients'],true,512,JSON_THROW_ON_ERROR);
                $kept=array_values(array_filter($recipients,static fn($recipient)=>!in_array((int)$recipient['id'],array_map('intval',$ids),true)));
                if (count($kept)===count($recipients)) continue;
                if (!$kept) $db->prepare('DELETE FROM whatsapp_broadcasts WHERE token=?')->execute([$broadcast['token']]);
                else $db->prepare('UPDATE whatsapp_broadcasts SET recipients=?,total=GREATEST(0,total-?) WHERE token=?')->execute([json_encode($kept,JSON_THROW_ON_ERROR),count($recipients)-count($kept),$broadcast['token']]);
            }
            // Import snapshots may contain the removed contacts, so discard the temporary source payload.
            self::intake($db,$project);
        }
        return $removed;
    }
}
