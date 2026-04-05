<?php
/**
 * Application Configuration
 * /config/app.php
 *
 * Copy this file and adjust values per environment.
 * NEVER commit real secrets to version control.
 */

defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));

return [

    // -------------------------------------------------
    // Application
    // -------------------------------------------------
    'app_name'        => getenv('APP_NAME')    ?: 'F&B Loyalty Platform',
    'app_url'         => getenv('APP_URL')     ?: 'http://localhost',
    'app_env'         => getenv('APP_ENV')     ?: 'production',  // local | production
    'app_debug'       => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'app_timezone'    => 'Asia/Kuala_Lumpur',
    'app_locale'      => 'en',

    // -------------------------------------------------
    // Session
    // -------------------------------------------------
    'session_name'    => 'fnb_sess',
    'session_lifetime'=> 7200,   // seconds (2 hours)
    'admin_session'   => 'fnb_admin',

    // -------------------------------------------------
    // Security
    // -------------------------------------------------
    'csrf_token_name' => '_csrf_token',
    'password_cost'   => 12,

    // -------------------------------------------------
    // OTP
    // -------------------------------------------------
    'otp_length'      => 6,
    'otp_expiry_min'  => 10,     // minutes

    // -------------------------------------------------
    // Loyalty Points
    // -------------------------------------------------
    'points_per_myr'  => 1,      // 1 point per MYR 1 spent
    'points_currency' => 'MYR',

    // -------------------------------------------------
    // WhatsApp / AiServe
    // -------------------------------------------------
    'whatsapp_api_url'    => getenv('WHATSAPP_API_URL') ?: '',
    'whatsapp_api_key'    => getenv('WHATSAPP_API_KEY') ?: '',
    'whatsapp_sender'     => getenv('WHATSAPP_SENDER')  ?: '',

    // -------------------------------------------------
    // Payment
    // -------------------------------------------------
    'stripe_public_key'   => getenv('STRIPE_PUBLIC_KEY') ?: '',
    'stripe_secret_key'   => getenv('STRIPE_SECRET_KEY') ?: '',

    // -------------------------------------------------
    // Google Maps
    // -------------------------------------------------
    'google_maps_key'     => getenv('GOOGLE_MAPS_KEY') ?: '',

    // -------------------------------------------------
    // File Upload
    // -------------------------------------------------
    'upload_dir'          => BASE_PATH . '/public/uploads/',
    'upload_url'          => '/public/uploads/',
    'allowed_image_types' => ['image/jpeg', 'image/png', 'image/webp'],
    'max_upload_size'     => 5 * 1024 * 1024,  // 5MB

];
