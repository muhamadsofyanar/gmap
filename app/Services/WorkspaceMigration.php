<?php
namespace App\Services;

class WorkspaceMigration
{
    private static array $applied = [];
    public static function apply(\PDO $db): void
    {
        $name = (string)$db->query('SELECT DATABASE()')->fetchColumn();
        if (isset(self::$applied[$name])) return;
        try {
            $db->query('SELECT 1 FROM crm_lead_sources,crm_attachment_chunks,crm_intake_chunks LIMIT 0');
            self::$applied[$name] = true;
            return;
        } catch (\PDOException $e) {
            if ($e->getCode() !== '42S02') throw $e;
        }
        preg_match_all('/CREATE TABLE IF NOT EXISTS `(crm_contact_blocks|crm_attachments|crm_intake_batches|crm_lead_sources|crm_attachment_chunks|crm_intake_chunks)`\s*\(.*?\) ENGINE=InnoDB[^;]*;/s', file_get_contents(ROOT_PATH . '/database.sql'), $matches);
        if (count($matches[0]) !== 6) throw new \RuntimeException('Skema pelengkap CRM tidak lengkap.');
        foreach ($matches[0] as $sql) $db->exec($sql);
        self::$applied[$name] = true;
    }
}
