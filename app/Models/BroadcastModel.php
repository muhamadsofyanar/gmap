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
        $row['recipients'] = json_decode($row['recipients'], true, 512, JSON_THROW_ON_ERROR);
        return $row;
    }

    public function prepare(string $token, int $projectId, array $template, bool $markContacted = false): array
    {
        $existing = $this->find($token);
        if ($existing) {
            if ((int)$existing['project_id'] !== $projectId || (int)$existing['template_id'] !== (int)$template['id']) throw new \InvalidArgumentException('Broadcast tidak cocok. Muat ulang halaman.');
            return $existing;
        }
        $stmt = Database::getInstance()->prepare('SELECT l.id, l.shop_name, l.phone, l.whatsapp, p.name AS project_name FROM project_leads l JOIN projects p ON p.id = l.project_id WHERE l.project_id = ? AND l.in_crm = 1 ORDER BY l.id');
        $stmt->execute([$projectId]);
        $leads = ContactPolicy::annotate($stmt->fetchAll());
        $recipients = []; $seen = []; $invalid = 0; $duplicates = 0;
        foreach ($leads as $lead) {
            if (!empty($lead['contact_blocked'])) continue;
            try { $number = GatewayModel::phone($lead['whatsapp'] ?: ($lead['phone'] ?? '')); }
            catch (\InvalidArgumentException $e) { $invalid++; continue; }
            if (isset($seen[$number])) { $duplicates++; continue; }
            $seen[$number] = true;
            $recipients[] = [
                'id' => (int)$lead['id'], 'name' => $lead['shop_name'], 'number' => $number,
                'message' => MessageTemplateModel::render($template['body'], $lead['shop_name'], $lead['project_name']),
                'token' => substr(hash('sha256', $token . ':' . $lead['id']), 0, 32)
            ];
        }
        $insert = Database::getInstance()->prepare('INSERT IGNORE INTO whatsapp_broadcasts (token, project_id, template_id, template_name, recipients, total, invalid_count, duplicate_count, mark_contacted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([$token, $projectId, $template['id'], $template['name'], json_encode($recipients, JSON_THROW_ON_ERROR), count($leads), $invalid, $duplicates, (int)$markContacted]);
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
