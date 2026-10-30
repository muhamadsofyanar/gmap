<?php
namespace App\Services;

use PDO;

class WhatsAppCheckMigration
{
    private static array $applied = [];

    /** Add WhatsApp registration columns to existing workspaces; repeatable and non-destructive. */
    public static function apply(PDO $db): void
    {
        $name = (string)$db->query('SELECT DATABASE()')->fetchColumn();
        if (isset(self::$applied[$name])) return;
        if (!$db->query("SHOW COLUMNS FROM `project_leads` LIKE 'wa_status'")->fetch()) {
            $db->exec("ALTER TABLE `project_leads` ADD COLUMN `wa_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'active, inactive, unknown' AFTER `whatsapp`, ADD COLUMN `wa_checked_at` datetime DEFAULT NULL AFTER `wa_status`");
        }
        self::$applied[$name] = true;
    }
}
