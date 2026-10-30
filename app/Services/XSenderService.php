<?php
namespace App\Services;

class XSenderService
{
    public static function connectionOptions(): array
    {
        return [
            CURLOPT_CAINFO => CONFIG_PATH . '/certs/cacert.pem',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => false
        ];
    }

    public static function transportFailure(int $errno, string $provider = 'xSender'): array
    {
        $message = match ($errno) {
            60, 77 => 'Sertifikat SSL gateway belum dapat diverifikasi. Periksa berkas CA di config/certs/cacert.pem.',
            5, 6 => 'Alamat gateway tidak dapat ditemukan. Periksa DNS dan koneksi internet server.',
            7 => 'Server tidak dapat terhubung ke gateway. Periksa koneksi internet atau firewall.',
            28 => 'Gateway tidak merespons dalam batas waktu. Periksa riwayat ' . $provider . ' sebelum mengirim ulang.',
            35 => 'Koneksi TLS ke gateway gagal. Periksa konfigurasi SSL server.',
            default => 'Koneksi ke gateway terputus. Periksa riwayat ' . $provider . ' sebelum mengirim ulang.'
        };
        return ['status' => in_array($errno, [5, 6, 7, 35, 60, 77], true) ? 'failed' : 'unknown', 'http_code' => 0, 'diagnostic' => $message . ' (cURL ' . $errno . ')'];
    }

    // Only the fields xSender expects; never forward internal keys such as the provider name.
    private static function account(array $credentials): array
    {
        return ['api_key' => $credentials['api_key'], 'sender' => $credentials['sender']];
    }

    public function send(array $credentials, string $number, string $message): array
    {
        $ch = curl_init('https://xsender.id/id/send-message');
        curl_setopt_array($ch, self::connectionOptions() + [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => json_encode(self::account($credentials) + ['number' => $number, 'message' => $message], JSON_THROW_ON_ERROR)
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);
        if ($errno) return self::transportFailure($errno);
        return self::classify($code, $body);
    }

    /** Ask the gateway whether a number has a WhatsApp account: active, inactive, or unknown. */
    public function checkNumber(array $credentials, string $number): array
    {
        $ch = curl_init('https://xsender.id/id/check-number');
        curl_setopt_array($ch, self::connectionOptions() + [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => json_encode(self::account($credentials) + ['number' => $number], JSON_THROW_ON_ERROR)
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);
        if ($errno) return ['status' => 'unknown', 'http_code' => 0, 'diagnostic' => self::transportFailure($errno)['diagnostic']];
        return self::classifyCheck($code, $body);
    }

    // Only an explicit registration flag or an explicit "not registered" message decides the result.
    public static function classifyCheck(int $code, $body): array
    {
        $data = is_string($body) ? json_decode($body, true) : null;
        if ($code === 401 || $code === 403) return ['status' => 'unknown', 'http_code' => $code, 'diagnostic' => 'API key atau nomor pengirim ditolak gateway.'];
        if ($code === 404 && !is_array($data)) return ['status' => 'unknown', 'http_code' => $code, 'diagnostic' => 'Gateway belum mendukung pengecekan nomor.'];
        if (!is_array($data)) return ['status' => 'unknown', 'http_code' => $code];
        $flag = self::findFlag($data);
        if ($flag !== null) return ['status' => $flag ? 'active' : 'inactive', 'http_code' => $code];
        $message = $data['msg'] ?? $data['message'] ?? '';
        $text = strtolower(is_string($message) ? $message : (json_encode($message) ?: ''));
        if (preg_match('/not (registered|exist|on whatsapp)|tidak terdaftar|belum terdaftar|unregistered|tidak ada di whatsapp/', $text)) return ['status' => 'inactive', 'http_code' => $code];
        // xSender answers an unregistered number with this generic "check your connection" failure,
        // which a real device outage also produces; the caller must confirm the gateway is healthy.
        if (preg_match('/gagal memeriksa nomor|failed to check number/', $text)) return ['status' => 'inactive', 'http_code' => $code, 'needs_gateway_check' => true];
        // Any other refusal (invalid key/sender, device offline) is a gateway problem, not a number result.
        if (in_array($data['status'] ?? null, [false, 'false', 'error', 'failed'], true) || $code >= 400) {
            $reason = is_string($message) && $message !== '' ? mb_substr(strip_tags($message), 0, 160) : 'Gateway menolak permintaan (HTTP ' . $code . ').';
            return ['status' => 'unknown', 'http_code' => $code, 'diagnostic' => 'xSender: ' . $reason];
        }
        return ['status' => 'unknown', 'http_code' => $code];
    }

    private static function findFlag(array $data): ?bool
    {
        foreach (['exists', 'exist', 'isRegistered', 'is_registered', 'registered', 'onWhatsApp', 'on_whatsapp', 'numberExists'] as $key) {
            if (array_key_exists($key, $data)) {
                $value = $data[$key];
                if (is_bool($value)) return $value;
                if (in_array($value, [1, '1', 'true'], true)) return true;
                if (in_array($value, [0, '0', 'false'], true)) return false;
            }
        }
        foreach ($data as $value) {
            if (is_array($value) && ($found = self::findFlag($value)) !== null) return $found;
        }
        return null;
    }

    // The supplied blueprint omits a response schema. Never equate HTTP 200 with delivery.
    public static function classify(int $code, $body): array
    {
        $data = is_string($body) ? json_decode($body, true) : null;
        $status = 'unknown';
        if ($code >= 400 && $code < 500 && $code !== 408) $status = 'failed';
        elseif ($code >= 200 && $code < 300 && is_array($data)) {
            $flags = array_intersect_key($data, ['success' => true, 'status' => true]);
            foreach ($flags as $flag) {
                if (in_array($flag, [false, 'false', 'error', 'failed'], true)) { $status = 'failed'; break; }
                if (in_array($flag, [true, 'true', 'success'], true)) $status = 'accepted';
            }
        }
        return ['status' => $status, 'http_code' => $code];
    }
}
