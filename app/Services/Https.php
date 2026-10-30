<?php
namespace App\Services;

/** Forces production traffic onto https; .htaccess does the same on Apache, this covers other servers and proxies. */
final class Https
{
    public static function secure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443
            || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public static function local(): bool
    {
        $host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')));
        return in_array($host, ['localhost', '127.0.0.1', '[::1]'], true) || preg_match('/\.(test|local|localhost)$/D', $host) === 1;
    }

    /** Local development stays on http; everything else must be https. */
    public static function required(): bool
    {
        return defined('APP_ENV') && APP_ENV === 'production' && !self::local();
    }

    public static function enforce(): void
    {
        if (PHP_SAPI === 'cli' || !self::required()) return;
        if (self::secure()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
            return;
        }
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if (!preg_match('/^[a-z0-9.-]+(:\d+)?$/iD', $host)) return;
        header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }
}
