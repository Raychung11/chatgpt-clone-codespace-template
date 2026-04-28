<?php
// ============================================================
// AI101 Platform - Configuration
// Edit these values before deploying to Hostinger
// ============================================================

define('SITE_NAME', 'AI101 Platform');
define('SITE_URL', 'https://yourdomain.com'); // Change to your domain

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
define('ADMIN_EMAIL', 'admin@yourdomain.com');

// App settings
define('TRIAL_DAYS', 14);
define('CURRENCY', 'USD');
define('CURRENCY_SYMBOL', '$');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
