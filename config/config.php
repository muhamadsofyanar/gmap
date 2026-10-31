<?php
// Production secrets are supplied by Coolify, never committed to GitHub.
if (!defined('APP_NAME')) define('APP_NAME', getenv('APP_NAME') ?: 'Gmap Prospek');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.6.0');
if (!defined('APP_ENV')) define('APP_ENV', getenv('APP_ENV') ?: 'production');
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Jakarta');
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
if (!defined('APP_PATH')) define('APP_PATH', ROOT_PATH . '/app');
if (!defined('CONFIG_PATH')) define('CONFIG_PATH', ROOT_PATH . '/config');
if (!defined('PUBLIC_PATH')) define('PUBLIC_PATH', ROOT_PATH . '/public');
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'db');
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: '3306');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'gmap');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'gmap');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');
if (!defined('PYTHON_BIN')) define('PYTHON_BIN', getenv('PYTHON_BIN') ?: '/opt/venv/bin/python');
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 7200);
if (!defined('SESSION_COOKIE_NAME')) define('SESSION_COOKIE_NAME', 'gmap_team_session');
if (!defined('CACHE_EXPIRY_HOURS')) define('CACHE_EXPIRY_HOURS', 24);
$url = rtrim((string)getenv('APP_URL'), '/');
if ($url === '' && APP_ENV === 'development') $url = 'http://localhost';
$parts = parse_url($url);
if (!$parts || empty($parts['host']) || !in_array($parts['scheme'] ?? '', ['http','https'], true)
    || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
    || (APP_ENV === 'production' && ($parts['scheme'] !== 'https' || !empty($parts['path'])))) {
    throw new RuntimeException('APP_URL wajib URL HTTPS domain aplikasi tanpa path pada production.');
}
if (!defined('BASE_URL')) define('BASE_URL', $url);
