<?php
namespace App\Services;

/**
 * Response security headers for every request routed through the front controller.
 * Inline <script> blocks must carry nonce="<?= e(csp_nonce()) ?>"; inline event handlers are not allowed
 * (use data-click / data-change / data-confirm / data-autosubmit from assets/js/csp-actions.js instead).
 */
final class SecurityHeaders
{
    private static ?string $nonce = null;

    public static function nonce(): string
    {
        return self::$nonce ??= rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }

    public static function policy(): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-" . self::nonce() . "'",
            // Inline style attributes are used for small layout values; styles cannot execute script.
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: blob:",
            "connect-src 'self'",
            "media-src 'self'",
            "object-src 'none'",
            "frame-src 'none'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "manifest-src 'self'",
            "worker-src 'self'",
        ];
        if (Https::secure()) $directives[] = 'upgrade-insecure-requests';
        return implode('; ', $directives);
    }

    public static function send(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) return;
        header('Content-Security-Policy: ' . self::policy());
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('X-Permitted-Cross-Domain-Policies: none');
        header_remove('X-Powered-By');
        if (Https::secure() && !Https::local()) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
