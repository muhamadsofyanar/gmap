<?php
namespace App\Models;

use App\Core\Database;

class ContactPolicy
{
    public static function key(array $lead): string
    {
        try { return 'phone:' . GatewayModel::phone(($lead['whatsapp'] ?? '') ?: ($lead['phone'] ?? '')); }
        catch (\InvalidArgumentException $e) { return 'lead:' . (int)$lead['id']; }
    }

    public static function blocked(array $lead): bool
    {
        return self::preference($lead) !== null;
    }

    public static function preference(array $lead): ?array
    {
        $q = Database::getInstance()->prepare('SELECT contact_key,reason FROM crm_contact_blocks WHERE contact_key IN (?,?) LIMIT 1');
        $q->execute([self::key($lead), 'lead:' . (int)$lead['id']]);
        return $q->fetch() ?: null;
    }

    public static function annotate(array $leads): array
    {
        $keys = array_fill_keys(Database::getInstance()->query('SELECT contact_key FROM crm_contact_blocks')->fetchAll(\PDO::FETCH_COLUMN), true);
        foreach ($leads as &$lead) $lead['contact_blocked'] = isset($keys[self::key($lead)]) || isset($keys['lead:' . (int)$lead['id']]);
        return $leads;
    }

    public function save(int $id, bool $blocked, string $reason): void
    {
        if (mb_strlen($reason) > 300) throw new \InvalidArgumentException('Alasan maksimal 300 karakter.');
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $q = $db->prepare('SELECT * FROM project_leads WHERE id=? AND in_crm=1 FOR UPDATE'); $q->execute([$id]);
            $lead = $q->fetch();
            if (!$lead) throw new \InvalidArgumentException('Prospek tidak ditemukan di CRM.');
            $key = self::key($lead);
            if ($blocked) {
                $db->prepare('INSERT INTO crm_contact_blocks (contact_key,reason) VALUES (?,?) ON DUPLICATE KEY UPDATE reason=VALUES(reason)')->execute([$key, trim($reason)]);
            } else {
                $db->prepare('DELETE FROM crm_contact_blocks WHERE contact_key IN (?,?)')->execute([$key, 'lead:' . $id]);
            }
            (new CrmModel())->addHistory($id, $lead['lead_status'], $lead['lead_status'], 'contact_preference', $blocked ? 'Jangan dihubungi. ' . trim($reason) : 'Kontak dapat dihubungi kembali.');
            $db->commit();
        } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }
}
