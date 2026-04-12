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

// ── Timezone ─────────────────────────────────────────────────────────────────
define('APP_TIMEZONE', 'Asia/Kuala_Lumpur');
date_default_timezone_set(APP_TIMEZONE);

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

// ── Cron secret (used when running poll_jobs.php via URL instead of CLI) ─────
// Set a long random string, then hit: /cron/poll_jobs.php?secret=YOUR_SECRET
define('CRON_SECRET', getenv('CRON_SECRET') ?: '');

// ── BytePlus ModelArk API (also configurable via settings table) ─────────────
// API URL: Asia Pacific (Jakarta) endpoint — change region if needed
define('BYTEPLUS_API_URL',     getenv('BYTEPLUS_API_URL')     ?: 'https://ark.ap-southeast.byteplus.com/api/v3');
define('BYTEPLUS_API_KEY',     getenv('BYTEPLUS_API_KEY')     ?: '');
// 5-second video endpoint (Seedance Lite or Pro — ModelArk → Online inference)
define('BYTEPLUS_ENDPOINT_ID',     getenv('BYTEPLUS_ENDPOINT_ID')     ?: '');
// 10-second video endpoint — MUST be a Pro model endpoint (Lite only supports 5s).
// If left empty, the 5s endpoint is used as fallback (video will still be 5s).
define('BYTEPLUS_ENDPOINT_ID_10S', getenv('BYTEPLUS_ENDPOINT_ID_10S') ?: '');
// Text LLM endpoint for prompt enhancement (e.g. doubao-1-5-pro-32k or ep-XXXXX-doubao)
// Create a text model endpoint in ModelArk → Model activation → select a Doubao/chat model
define('LLM_ENDPOINT_ID',          getenv('LLM_ENDPOINT_ID')          ?: '');

// ── BytePlus Vision AI — OmniHuman (AK/SK auth, separate from ModelArk) ──────
// Console: https://console.byteplus.com/ai/overview  (Vision AI → Model Plaza → OmniHuman)
// AK/SK:   https://console.byteplus.com/iam/keymanage
// API URL: BytePlus CV endpoint — Service=cv, Region=ap-singapore-1, Version=2024-06-06
define('VISION_AI_URL', getenv('VISION_AI_URL') ?: 'https://cv.byteplusapi.com');
define('VISION_AI_AK',  getenv('VISION_AI_AK')  ?: '');   // Access Key ID
define('VISION_AI_SK',  getenv('VISION_AI_SK')  ?: '');   // Secret Access Key
// req_key for OmniHuman models:
//   dreamina_omni_human_v1_5  (OmniHuman 1.5)
//   dreamina_omni_human       (OmniHuman 1.0)
define('OMNIHUMAN_REQ_KEY', getenv('OMNIHUMAN_REQ_KEY') ?: 'dreamina_omni_human_v1_5');

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
