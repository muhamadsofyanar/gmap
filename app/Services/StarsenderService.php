<?php
namespace App\Services;

class StarsenderService
{
    private const BASE_URL = 'https://api.starsender.online/api/';

    public function send(array $credentials, string $number, string $message): array
    {
        [$code, $body, $errno] = $this->post($credentials, 'send', ['messageType' => 'text', 'to' => $number, 'body' => $message]);
        if ($errno) return XSenderService::transportFailure($errno, 'Starsender');
        if ($code === 401 || $code === 403) return ['status' => 'failed', 'http_code' => $code, 'diagnostic' => 'API key device Starsender ditolak. Periksa API key di menu Device Starsender.'];
        return XSenderService::classify($code, $body);
    }

    /** Ask the gateway whether a number has a WhatsApp account: active, inactive, or unknown. */
    public function checkNumber(array $credentials, string $number): array
    {
        [$code, $body, $errno] = $this->post($credentials, 'check-number', ['number' => $number], 20);
        if ($errno) return ['status' => 'unknown', 'http_code' => 0, 'diagnostic' => XSenderService::transportFailure($errno, 'Starsender')['diagnostic']];
        return self::classifyCheck($code, $body);
    }

    // Documented reply: {"success":true,"data":{"status":true|false,"message":"Number (not) registered"}}.
    public static function classifyCheck(int $code, $body): array
    {
        $data = is_string($body) ? json_decode($body, true) : null;
        if ($code === 401 || $code === 403) return ['status' => 'unknown', 'http_code' => $code, 'diagnostic' => 'API key device Starsender ditolak gateway.'];
        if (!is_array($data)) return ['status' => 'unknown', 'http_code' => $code, 'diagnostic' => 'Starsender: respons gateway tidak dapat dibaca (HTTP ' . $code . ').'];
        $flag = is_array($data['data'] ?? null) ? ($data['data']['status'] ?? null) : null;
        if ($code >= 200 && $code < 300 && ($data['success'] ?? null) === true && is_bool($flag)) {
            return ['status' => $flag ? 'active' : 'inactive', 'http_code' => $code];
        }
        // Any other refusal (invalid key, device disconnected) is a gateway problem, not a number result.
        $message = $data['message'] ?? '';
        $reason = is_string($message) && $message !== '' ? mb_substr(strip_tags($message), 0, 160) : 'Gateway menolak permintaan (HTTP ' . $code . ').';
        return ['status' => 'unknown', 'http_code' => $code, 'diagnostic' => 'Starsender: ' . $reason];
    }

    private function post(array $credentials, string $path, array $payload, int $timeout = 30): array
    {
        $ch = curl_init(self::BASE_URL . $path);
        curl_setopt_array($ch, XSenderService::connectionOptions() + [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: ' . $credentials['api_key']],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR)
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);
        return [$code, $body, $errno];
    }
}
