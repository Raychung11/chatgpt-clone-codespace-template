<?php
declare(strict_types=1);

/**
 * config/config.php
 * Master configuration file. Values here are overridden by database `settings` table.
 * Keep sensitive values in environment variables or a .env file on production.
 */

// ── Environment ──────────────────────────────────────────────────────────────
define('APP_ENV', getenv('APP_ENV') ?: 'production'); // 'development' | 'production'
define('APP_DEBUG', APP_ENV === 'development');

// ── Paths ─────────────────────────────────────────────────────────────────────
define('BASE_PATH',   dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . '/config');
define('INC_PATH',    BASE_PATH . '/inc');
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('PUBLIC_PATH', BASE_PATH . '/public');

// ── URL (set to your domain) ─────────────────────────────────────────────────
define('BASE_URL', rtrim(getenv('APP_URL') ?: 'http://localhost', '/'));

// ── Database ─────────────────────────────────────────────────────────────────
define('DB_HOST',    getenv('DB_HOST')    ?: '127.0.0.1');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: 'videosaas');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8mb4');

// ── Session ───────────────────────────────────────────────────────────────────
define('SESSION_NAME',     'vs_sess');
define('SESSION_LIFETIME', 60 * 60 * 8); // 8 hours

// ── Security ──────────────────────────────────────────────────────────────────
define('CSRF_TOKEN_NAME', '_csrf_token');
define('PASSWORD_COST',   12);

// ── Upload Limits ─────────────────────────────────────────────────────────────
define('MAX_RECEIPT_SIZE_BYTES', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_RECEIPT_TYPES', ['image/jpeg', 'image/png', 'application/pdf']);

// ── Pagination ────────────────────────────────────────────────────────────────
define('ITEMS_PER_PAGE', 20);

// ── BytePlus API (also configurable via settings table) ───────────────────────
define('BYTEPLUS_API_URL', getenv('BYTEPLUS_API_URL') ?: 'https://api.byteplus.com/visugc/v1');
define('BYTEPLUS_API_KEY', getenv('BYTEPLUS_API_KEY') ?: '');

// ── Error handling ────────────────────────────────────────────────────────────
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
    ini_set('log_errors', '1');
    ini_set('error_log', BASE_PATH . '/logs/php_errors.log');
}

// ── Session bootstrap (called once at entry points) ───────────────────────────
function boot_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}
