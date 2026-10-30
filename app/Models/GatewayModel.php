<?php
namespace App\Models;

use App\Core\Database;
use App\Services\GatewaySecret;

class GatewayModel
{
    public const PROVIDERS = ['xsender' => 'xSender', 'starsender' => 'Starsender'];

    private static bool $migrated = false;

    public function __construct()
    {
        self::migrate();
    }

    /** Add the provider column to existing installs; repeatable and non-destructive. */
    private static function migrate(): void
    {
        if (self::$migrated) return;
        $db = Database::getInstance();
        if (!$db->query("SHOW COLUMNS FROM `whatsapp_gateway` LIKE 'provider'")->fetch()) {
            $db->exec("ALTER TABLE `whatsapp_gateway` ADD COLUMN `provider` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'xsender' AFTER `id`");
        }
        self::$migrated = true;
    }

    public static function label(string $provider): string
    {
        return self::PROVIDERS[$provider] ?? self::PROVIDERS['xsender'];
    }

    /** @return \App\Services\XSenderService|\App\Services\StarsenderService */
    public static function service(string $provider): object
    {
        return $provider === 'starsender' ? new \App\Services\StarsenderService() : new \App\Services\XSenderService();
    }

    /** Label of the saved provider, for user-facing messages. */
    public function providerLabel(): string
    {
        return self::label($this->settings()['provider']);
    }

    public function settings(): array
    {
        $row = Database::getInstance()->query('SELECT provider, sender, enabled, api_key_encrypted FROM whatsapp_gateway WHERE id = 1')->fetch();
        $provider = isset(self::PROVIDERS[$row['provider'] ?? '']) ? $row['provider'] : 'xsender';
        return ['provider' => $provider, 'sender' => $row['sender'] ?? '', 'enabled' => !empty($row['enabled']), 'has_key' => !empty($row['api_key_encrypted'])];
    }

    /** Only use on the authenticated settings editor, never CRM or public status responses. */
    public function editableSettings(): array
    {
        $settings = $this->settings();
        $encrypted = Database::getInstance()->query('SELECT api_key_encrypted FROM whatsapp_gateway WHERE id = 1')->fetchColumn();
        $settings['api_key'] = $encrypted ? (new GatewaySecret())->decrypt($encrypted) : '';
        return $settings;
    }

    public function credentials(): array
    {
        $row = Database::getInstance()->query('SELECT * FROM whatsapp_gateway WHERE id = 1')->fetch();
        $provider = isset(self::PROVIDERS[$row['provider'] ?? '']) ? $row['provider'] : 'xsender';
        // Starsender identifies the sending device by its API key, so only xSender needs a sender number.
        if (!$row || !$row['enabled'] || !$row['api_key_encrypted'] || ($provider === 'xsender' && !$row['sender'])) {
            throw new \InvalidArgumentException('Lengkapi dan aktifkan WhatsApp Gateway di menu Pengaturan terlebih dahulu.');
        }
        return ['provider' => $provider, 'api_key' => (new GatewaySecret())->decrypt($row['api_key_encrypted']), 'sender' => $row['sender']];
    }

    public static function phone(string $number): string
    {
        if (preg_match('/[^0-9+\s().-]/', $number)) throw new \InvalidArgumentException('Nomor WhatsApp tidak valid.');
        $number = format_whatsapp($number);
        if (!preg_match('/^[1-9][0-9]{7,14}$/', $number)) throw new \InvalidArgumentException('Nomor WhatsApp tidak valid. Gunakan format 08 atau kode negara seperti 62812.');
        return $number;
    }

    public function save(string $provider, string $apiKey, string $sender, bool $enabled): void
    {
        if (!isset(self::PROVIDERS[$provider])) throw new \InvalidArgumentException('Pilih penyedia WhatsApp Gateway yang valid.');
        if ($provider !== 'xsender') {
            // The field is hidden for Starsender: keep a valid xSender number for switching back, drop anything else.
            try { $sender = trim($sender) === '' ? '' : self::phone($sender); } catch (\InvalidArgumentException $e) { $sender = ''; }
        } else {
            $sender = trim($sender) === '' && !$enabled ? '' : self::phone($sender);
        }
        $apiKey = trim($apiKey);
        if (strlen($apiKey) > 2048 || preg_match('/[\x00-\x1f\x7f]/', $apiKey)) throw new \InvalidArgumentException('API key tidak valid.');
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $row = $db->query('SELECT api_key_encrypted FROM whatsapp_gateway WHERE id = 1 FOR UPDATE')->fetch();
            $encrypted = $apiKey !== '' ? (new GatewaySecret())->encrypt($apiKey) : ($row['api_key_encrypted'] ?? '');
            if ($enabled && $encrypted === '') throw new \InvalidArgumentException('Isi API key sebelum mengaktifkan gateway.');
            $stmt = $db->prepare('INSERT INTO whatsapp_gateway (id, provider, sender, api_key_encrypted, enabled) VALUES (1, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE provider = VALUES(provider), sender = VALUES(sender), api_key_encrypted = VALUES(api_key_encrypted), enabled = VALUES(enabled)');
            $stmt->execute([$provider, $sender, $encrypted, (int)$enabled]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function reserve(string $token, int $leadId, int $templateId, string $number, string $message): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('INSERT IGNORE INTO whatsapp_deliveries (request_token, lead_id, template_id, recipient, message, status) VALUES (?, ?, ?, ?, ?, \'pending\')');
        $stmt->execute([$token, $leadId, $templateId, $number, $message]);
        $created = $stmt->rowCount() === 1;
        $find = $db->prepare('SELECT * FROM whatsapp_deliveries WHERE request_token = ?');
        $find->execute([$token]);
        $row = $find->fetch();
        if ((int)$row['lead_id'] !== $leadId || (int)$row['template_id'] !== $templateId) throw new \InvalidArgumentException('Permintaan pengiriman tidak cocok. Muat ulang halaman.');
        return ['created' => $created, 'delivery' => $row];
    }

    public function finish(int $id, string $status, int $httpCode): void
    {
        $stmt = Database::getInstance()->prepare('UPDATE whatsapp_deliveries SET status = ?, http_code = ?, updated_at = NOW() WHERE id = ? AND status = \'pending\'');
        $stmt->execute([$status, $httpCode, $id]);
    }

    public function history(int $leadId): array
    {
        $stmt = Database::getInstance()->prepare('SELECT id, recipient, message, status, created_at FROM whatsapp_deliveries WHERE lead_id = ? ORDER BY id DESC LIMIT 50');
        $stmt->execute([$leadId]);
        return $stmt->fetchAll();
    }
}
