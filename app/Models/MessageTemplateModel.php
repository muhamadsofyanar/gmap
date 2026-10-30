<?php
namespace App\Models;

use App\Core\Database;

class MessageTemplateModel
{
    public function all(): array
    {
        return Database::getInstance()->query('SELECT id, name, body FROM message_templates ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function save(int $id, string $name, string $body): int
    {
        $name = trim($name);
        $body = trim($body);
        if ($name === '' || mb_strlen($name) > 100 || $body === '' || mb_strlen($body) > 4000) {
            throw new \InvalidArgumentException('Nama wajib diisi (maksimal 100 karakter) dan pesan wajib diisi (maksimal 4.000 karakter).');
        }
        self::validateBody($body);
        $db = Database::getInstance();
        if ($id > 0) {
            $find = $db->prepare('SELECT id FROM message_templates WHERE id = ?');
            $find->execute([$id]);
            if (!$find->fetch()) throw new \InvalidArgumentException('Template tidak ditemukan.');
            $stmt = $db->prepare('UPDATE message_templates SET name = ?, body = ? WHERE id = ?');
            $stmt->execute([$name, $body, $id]);
            return $id;
        }
        $stmt = $db->prepare('INSERT INTO message_templates (name, body) VALUES (?, ?)');
        $stmt->execute([$name, $body]);
        return (int)$db->lastInsertId();
    }

    /** A message body must be 1-4000 characters and only use the supported placeholders. */
    public static function validateBody(string $body): string
    {
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 4000) throw new \InvalidArgumentException('Pesan wajib diisi (maksimal 4.000 karakter).');
        preg_match_all('/\{\{.*?\}\}/s', $body, $matches);
        foreach ($matches[0] as $placeholder) {
            if (!in_array($placeholder, ['{{nama_bisnis}}', '{{nama_projek}}'], true)) {
                throw new \InvalidArgumentException('Variabel yang tersedia: {{nama_bisnis}} dan {{nama_projek}}.');
            }
        }
        return $body;
    }

    public static function render(string $body, string $business, string $project): string
    {
        return strtr($body, ['{{nama_bisnis}}' => $business, '{{nama_projek}}' => $project]);
    }
}
