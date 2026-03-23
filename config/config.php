<?php
/**
 * STRate AI — Configuration
 * Copy this file to config/config.local.php for local overrides (never commit secrets).
 */

// ─── Database (MySQL / MariaDB on Hostinger) ──────────────────────────────
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'strate_ai');
define('DB_USER', getenv('DB_USER') ?: 'strate_user');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ─── OpenAI ───────────────────────────────────────────────────────────────
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
define('OPENAI_MODEL',   'gpt-4o-mini');

// ─── App ─────────────────────────────────────────────────────────────────
define('APP_NAME',    'STRate AI');
define('APP_VERSION', '1.0.0');
define('APP_ENV',     getenv('APP_ENV') ?: 'production'); // 'development' | 'production'
define('APP_TIMEZONE', 'Asia/Kuala_Lumpur');

// ─── Pricing Engine Defaults ──────────────────────────────────────────────
define('PRICING_DAYS_AHEAD',   7);   // how many days forward to generate recs
define('MARKET_DATA_DAYS_BACK', 30); // days of historical data to keep

// ─── WhatsApp (AiServe) ───────────────────────────────────────────────────
define('AISENSY_API_KEY',  getenv('AISENSY_API_KEY')  ?: '');
define('AISENSY_TEMPLATE', 'strate_daily_update');

// ─── Load local overrides if present ─────────────────────────────────────
$local_config = __DIR__ . '/config.local.php';
if (file_exists($local_config)) {
    require_once $local_config;
}

// ─── Timezone ─────────────────────────────────────────────────────────────
date_default_timezone_set(APP_TIMEZONE);
