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

// ─── Apify Scraper ────────────────────────────────────────────────────────
// Get your token at: https://console.apify.com/account/integrations
define('APIFY_TOKEN',           getenv('APIFY_TOKEN') ?: '');

// Actor IDs — change these if you prefer a different actor
// Airbnb: https://apify.com/dtrungtin/airbnb-scraper
define('APIFY_AIRBNB_ACTOR',    getenv('APIFY_AIRBNB_ACTOR')  ?: 'dtrungtin/airbnb-scraper');
// Booking.com (optional V2 feature)
define('APIFY_BOOKING_ACTOR',   getenv('APIFY_BOOKING_ACTOR') ?: 'dtrungtin/booking-scraper');

// Max listings to pull per location per run (keep low to save Apify credits)
define('APIFY_MAX_RESULTS',     50);
// Timeout waiting for actor run to finish (seconds)
define('APIFY_TIMEOUT_SEC',     120);
// How long (seconds) before a cached scrape result is considered stale
define('SCRAPER_CACHE_TTL',     4 * 3600); // 4 hours

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
