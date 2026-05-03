<?php
// ============================================================
// BizAI Platform - Configuration
// ============================================================

define('SITE_NAME', 'BizAI');
define('SITE_URL', 'https://bizai.my');

// Database (Hostinger MySQL)
define('DB_HOST', 'localhost');
define('DB_NAME', 'ai101_platform');
define('DB_USER', 'your_db_user');       // From Hostinger hPanel
define('DB_PASS', 'your_db_password');   // From Hostinger hPanel
define('DB_CHARSET', 'utf8mb4');

// Anthropic Claude API (get from console.anthropic.com)
define('ANTHROPIC_API_KEY', 'sk-ant-YOUR_KEY_HERE');

// Stripe Keys (get from dashboard.stripe.com)
define('STRIPE_PUBLIC_KEY', 'pk_test_YOUR_KEY_HERE');
define('STRIPE_SECRET_KEY', 'sk_test_YOUR_KEY_HERE');
define('STRIPE_WEBHOOK_SECRET', 'whsec_YOUR_WEBHOOK_SECRET');

// Session settings
define('SESSION_LIFETIME', 86400); // 24 hours

// Admin email
define('ADMIN_EMAIL', 'admin@bizai.my');

// App settings
define('TRIAL_DAYS', 14);

// Currency — Malaysian Ringgit
// IMPORTANT: if these show wrong on your live site, make sure THIS file
// is the version uploaded to Hostinger (public_html/includes/config.php)
if (!defined('CURRENCY'))        define('CURRENCY',        'MYR');
if (!defined('APP_CURRENCY')) define('APP_CURRENCY', 'RM');

// Error reporting — off in production
error_reporting(0);
ini_set('display_errors', 0);
