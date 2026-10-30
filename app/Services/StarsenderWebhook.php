<?php
namespace App\Services;

use InvalidArgumentException;

/** Strict adapter for the documented Starsender Premium event, not a delivery receipt. */
final class StarsenderWebhook
{
    public static function phone(mixed $value): ?string
    {
        if (!is_string($value) || !preg_match('/^\+?[0-9 ().-]{8,30}$/D', $value)) return null;
        $phone = preg_replace('/\D/', '', $value);
        if (str_starts_with($phone, '0')) $phone = '62'.substr($phone, 1);
        return preg_match('/^[1-9][0-9]{7,14}$/D', $phone) ? $phone : null;
    }

    public static function normalize(array $event, string $device): ?array
    {
        $id = $event['device_id'] ?? null;
        if ((!is_int($id) && !is_string($id)) || (string)$id !== $device) throw new InvalidArgumentException('Perangkat tidak sesuai.');
        foreach (['is_group', 'is_me'] as $key) {
            if (isset($event[$key]) && !is_bool($event[$key])) throw new InvalidArgumentException('Penanda pesan tidak valid.');
        }
        if (($event['is_group'] ?? false) || ($event['chat_type'] ?? '') === 'group') return null;
        if (($event['chat_type'] ?? '') !== 'personal' || !array_key_exists('is_me', $event) || !is_bool($event['is_me'])) throw new InvalidArgumentException('Jenis pesan tidak valid.');
        $messageId = $event['message_id'] ?? null;
        if (!is_string($messageId) || !preg_match('/^[A-Za-z0-9_.:@-]{1,191}$/D', $messageId)) throw new InvalidArgumentException('ID pesan tidak valid.');
        $message = $event['message'] ?? '';
        $name = $event['push_name'] ?? '';
        if (!is_string($message) || mb_strlen($message)>32000 || !is_string($name) || mb_strlen($name)>200) throw new InvalidArgumentException('Isi pesan tidak valid.');
        $timestamp = $event['timestamp'] ?? null;
        if ((!is_int($timestamp) && !(is_string($timestamp) && preg_match('/^(?:[0-9]{9,10}|[0-9]{12,13})$/D', $timestamp))) || (int)$timestamp<946684800) throw new InvalidArgumentException('Waktu pesan tidak valid.');
        $timestamp = (int)$timestamp;
        if ($timestamp>9999999999) $timestamp=intdiv($timestamp,1000);
        if ($timestamp<946684800 || $timestamp>time()+86400) throw new InvalidArgumentException('Waktu pesan di masa depan.');
        $file=$event['file'] ?? null;
        if ($file!==null && $file!=='') {
            if (!is_string($file) || strlen($file)>4096 || !filter_var($file,FILTER_VALIDATE_URL)
                || parse_url($file,PHP_URL_SCHEME)!=='https' || parse_url($file,PHP_URL_USER)!==null
                || parse_url($file,PHP_URL_PASS)!==null) throw new InvalidArgumentException('Media tidak valid.');
        } else $file=null;
        // The provider documents `from` but no destination for is_me=true. Never infer it.
        $contact=$event['is_me'] ? null : self::phone($event['from'] ?? null);
        if (!$event['is_me'] && $contact===null) throw new InvalidArgumentException('Kontak tidak valid.');
        return ['device_id'=>$device,'message_id'=>$messageId,'contact_phone'=>$contact,
            'direction'=>$event['is_me']?'outgoing':'incoming','push_name'=>$name,
            'message'=>$message,'file_url'=>$file,'sent_at'=>gmdate('Y-m-d H:i:s',$timestamp)];
    }
}
