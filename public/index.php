<?php
/**
 * Front Controller Entry Point
 * Gmap Prospek MVC Architecture
 */

// Error reporting configuration
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Define root directory
define('ROOT_PATH', dirname(__DIR__));

// 1. Load Configurations
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

// 2. Load Core Helpers
require_once APP_PATH . '/Core/Helpers.php';

// 3. Simple PSR-4 Class Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = APP_PATH . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// 4. Security headers, https redirect, then the session (cookies: HttpOnly, SameSite=Lax, Secure on https)
\App\Services\SecurityHeaders::send();
header('Cache-Control: private, no-store');
\App\Services\Https::enforce();
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_COOKIE_NAME);
    $sessionPath = ROOT_PATH . '/storage/sessions';
    if (!is_dir($sessionPath)) mkdir($sessionPath, 0700, true);
    session_save_path($sessionPath);
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => \App\Services\Https::secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// 5. Initialize and Dispatch Application Router
try {
    new \App\Core\App();
} catch (\Throwable $e) {
    if (defined('APP_ENV') && APP_ENV === 'development') {
        echo "<div style='font-family:sans-serif;padding:20px;background:#fee2e2;color:#991b1b;border-radius:12px;margin:20px;'>";
        echo "<h3 style='margin-top:0;'>Terjadi Kesalahan Sistem (Development Mode):</h3>";
        echo "<p><strong>Pesan:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . " (Baris: " . $e->getLine() . ")</p>";
        echo "<pre style='background:#fff;padding:12px;border-radius:8px;overflow:auto;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        echo "</div>";
    } else {
        error_log('Application error: ' . $e->getMessage());
        http_response_code(500);
        echo "<h1>Terjadi kesalahan pada sistem. Silakan hubungi administrator.</h1>";
    }
}
