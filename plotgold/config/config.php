<?php
/**
 * PlotGold Malaysia — Main Configuration
 * Copy this file and adjust for each environment.
 * NEVER commit real credentials to version control.
 */

defined('PLOTGOLD') or die('Direct access not permitted.');

// ── Environment ────────────────────────────────────────────────────────────
define('APP_ENV',     getenv('APP_ENV')     ?: 'production');  // development | production
define('APP_DEBUG',   APP_ENV === 'development');
define('APP_VERSION', '1.0.0');

// ── Base Paths ──────────────────────────────────────────────────────────────
define('ROOT_PATH',    dirname(__DIR__));
define('CONFIG_PATH',  ROOT_PATH . '/config');
define('INC_PATH',     ROOT_PATH . '/inc');
define('UPLOAD_PATH',  ROOT_PATH . '/uploads');
define('LOG_PATH',     ROOT_PATH . '/logs');
define('ASSET_PATH',   ROOT_PATH . '/assets');

// ── Base URL ────────────────────────────────────────────────────────────────
define('BASE_URL', rtrim(getenv('APP_URL') ?: 'http://localhost/plotgold', '/'));

// ── Database ────────────────────────────────────────────────────────────────
define('DB_HOST',    getenv('DB_HOST')    ?: '127.0.0.1');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: 'plotgold');
define('DB_USER',    getenv('DB_USER')    ?: 'plotgold_user');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8mb4');

// ── Session ─────────────────────────────────────────────────────────────────
define('SESSION_NAME',     'PGSS');
define('SESSION_LIFETIME', 7200);           // 2 hours
define('REMEMBER_LIFETIME', 30 * 86400);   // 30 days

// ── Security ─────────────────────────────────────────────────────────────────
define('CSRF_TOKEN_LENGTH', 32);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 30);
define('BCRYPT_COST', 12);

// ── File Uploads ─────────────────────────────────────────────────────────────
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);  // 5 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('ALLOWED_DOC_TYPES',   ['image/jpeg', 'image/png', 'application/pdf']);
define('MAX_LISTING_IMAGES',  10);

// ── Pagination ────────────────────────────────────────────────────────────────
define('LISTINGS_PER_PAGE', 12);
define('ADMIN_PER_PAGE',    20);

// ── WhatsApp ──────────────────────────────────────────────────────────────────
define('WHATSAPP_NUMBER', getenv('WHATSAPP_NUMBER') ?: '601112345678');

// ── Error Handling ────────────────────────────────────────────────────────────
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LOG_PATH . '/php_errors.log');
}

// ── Timezone ──────────────────────────────────────────────────────────────────
date_default_timezone_set('Asia/Kuala_Lumpur');
