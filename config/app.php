<?php
declare(strict_types=1);

// ─── SilverDeals MY — App Configuration ─────────────────────────────────────
// Operated by SLV Lifestyle Sdn Bhd | Powered by SLV Group Sdn Bhd

define('APP_NAME',    'SilverDeals MY');
define('APP_TAGLINE', 'Senior Membership & Rewards Ecosystem');
define('APP_VERSION', '1.0.0');
define('APP_ENV',     getenv('APP_ENV') ?: 'production'); // 'development' | 'production'

// Base URL — set to your Hostinger domain (no trailing slash)
define('BASE_URL', getenv('APP_BASE_URL') ?: 'https://silverdeals.my');

// Session name (security: avoid default 'PHPSESSID')
define('SESSION_NAME', 'SDMY_SESS');

// CSRF token lifetime in seconds
define('CSRF_LIFETIME', 3600);

// Points config
define('POINTS_WELCOME_BONUS',         100);
define('POINTS_REFERRAL_BONUS',        200);
define('POINTS_VERIFICATION_BONUS',    50);
define('POINTS_FIRST_REDEMPTION_BONUS', 25);

// Membership tiers
define('PLAN_FREE',    'free');
define('PLAN_SILVER',  'silver');
define('PLAN_GOLD',    'gold');

// Roles
define('ROLE_MEMBER',    'member');
define('ROLE_MERCHANT',  'merchant');
define('ROLE_COMMUNITY', 'community');
define('ROLE_ADMIN',     'admin');
define('ROLE_SUPERADMIN','superadmin');

// WhatsApp CTA number (E.164, no +)
define('WHATSAPP_NUMBER', '60123456789');

// Upload paths (relative to project root)
define('UPLOAD_DIR',        __DIR__ . '/../assets/img/uploads/');
define('MAX_UPLOAD_BYTES',  5 * 1024 * 1024); // 5 MB
define('ALLOWED_IMG_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// Error display — NEVER true in production
if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Timezone — Malaysia Standard Time
date_default_timezone_set('Asia/Kuala_Lumpur');
