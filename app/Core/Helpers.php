<?php
/**
 * Global Helper Functions
 * Gmap Prospek
 */

if (!function_exists('e')) {
    /**
     * Escape HTML output to prevent Cross-Site Scripting (XSS) (PRD Section 6)
     * 
     * @param mixed $value
     * @return string
     */
    function e($value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('base_url')) {
    /**
     * Generate base URL for routes and assets
     * 
     * @param string $path
     * @return string
     */
    function base_url(string $path = ''): string
    {
        $base = defined('BASE_URL') ? BASE_URL : '';
        $path = ltrim($path, '/');
        return $path === '' ? $base : $base . '/' . $path;
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Generate or retrieve CSRF token from session
     * 
     * @return string
     */
    function csrf_token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate hidden input containing CSRF token
     * 
     * @return string
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('verify_csrf_token')) {
    /**
     * Verify submitted CSRF token
     * 
     * @param string|null $token
     * @return bool
     */
    function verify_csrf_token(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($token) || empty($_SESSION['_csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['_csrf_token'], $token);
    }
}

if (!function_exists('format_whatsapp')) {
    /**
     * Normalize Indonesian phone number into international WhatsApp format (e.g. 628123456789)
     * 
     * @param string|null $phone
     * @return string
     */
    function format_whatsapp(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        // Remove all non-numeric characters except +
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        // Convert leading 0 to 62
        if (str_starts_with($cleaned, '0')) {
            $cleaned = '62' . substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '8')) {
            $cleaned = '62' . $cleaned;
        }

        return $cleaned;
    }
}

if (!function_exists('format_phone_display')) {
    /**
     * Format phone number nicely for UI display
     * 
     * @param string|null $phone
     * @return string
     */
    function format_phone_display(?string $phone): string
    {
        if (empty($phone)) {
            return '-';
        }
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleaned) >= 10) {
            if (str_starts_with($cleaned, '62')) {
                $cleaned = '0' . substr($cleaned, 2);
            }
            // Format 0812-3456-7890
            return preg_replace('/(\d{4})(\d{4})(\d+)/', '$1-$2-$3', $cleaned);
        }
        return $phone;
    }
}

if (!function_exists('set_flash')) {
    /**
     * Set a flash message
     * 
     * @param string $type success|error|warning|info
     * @param string $message
     */
    function set_flash(string $type, string $message): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash_messages'][] = [
            'type' => $type,
            'message' => $message
        ];
    }
}

if (!function_exists('get_flashes')) {
    /**
     * Retrieve and clear all flash messages
     * 
     * @return array
     */
    function get_flashes(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $messages = $_SESSION['flash_messages'] ?? [];
        unset($_SESSION['flash_messages']);
        return $messages;
    }
}

if (!function_exists('is_authenticated')) {
    /**
     * Check if user is authenticated admin
     * 
     * @return bool
     */
    function is_authenticated(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
            return false;
        }

        // Check session timeout (PRD Section 2)
        $timeout = defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 7200;
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
            // Session expired
            session_unset();
            session_destroy();
            return false;
        }

        $_SESSION['last_activity'] = time(); // Refresh active time
        return in_array($_SESSION['role'], ['admin', 'user'], true);
    }
}

if (!function_exists('current_user')) {
    /**
     * Get current logged-in user information
     * 
     * @return array|null
     */
    function current_user(): ?array
    {
        if (!is_authenticated()) {
            return null;
        }
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? '',
            'name' => $_SESSION['user_name'] ?? 'Admin',
            'email' => $_SESSION['user_email'] ?? '',
            'role' => $_SESSION['role'] ?? 'admin'
        ];
    }
}

if (!function_exists('password_hash_secure')) {
    /** Argon2id when PHP provides it, bcrypt (cost 12) otherwise. password_verify() accepts both. */
    function password_hash_secure(string $password): string
    {
        return defined('PASSWORD_ARGON2ID') ? password_hash($password, PASSWORD_ARGON2ID) : password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /** True when a stored hash should be upgraded to the current algorithm after a successful login. */
    function password_needs_upgrade(string $hash): bool
    {
        return defined('PASSWORD_ARGON2ID') ? password_needs_rehash($hash, PASSWORD_ARGON2ID) : password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}

if (!function_exists('csp_nonce')) {
    /** Per-request nonce that inline <script> blocks need under the Content-Security-Policy. */
    function csp_nonce(): string
    {
        return \App\Services\SecurityHeaders::nonce();
    }
}

