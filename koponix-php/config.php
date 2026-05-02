<?php
// ============================================================
//  KOPONIX – Configuration
//  Sensitive values are loaded from .env (same directory).
//  Copy .env.example to .env and fill in real values.
// ============================================================

// ── Load .env file ───────────────────────────────────────────
(function () {
    $env_file = __DIR__ . '/.env';
    if (!file_exists($env_file)) return;
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key); $val = trim($val, " \t\n\r\0\x0B\"'");
        if ($key !== '') $_ENV[$key] = $val;
    }
})();

function env(string $key, string $default = ''): string {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// ── Database ─────────────────────────────────────────────────
define('DB_HOST',    env('DB_HOST',    'localhost'));
define('DB_NAME',    env('DB_NAME',    'your_db_name'));
define('DB_USER',    env('DB_USER',    'your_db_user'));
define('DB_PASS',    env('DB_PASS',    'your_db_password'));
define('DB_CHARSET', 'utf8mb4');

// ── Anthropic API ────────────────────────────────────────────
define('ANTHROPIC_API_KEY',  env('ANTHROPIC_API_KEY',  'sk-ant-your-key-here'));
define('CLAUDE_MODEL',       'claude-sonnet-4-6');
define('CLAUDE_MODEL_OPUS',  'claude-opus-4-6');

// ── Site ─────────────────────────────────────────────────────
define('SITE_NAME',  'Koponix');
define('SITE_URL',   rtrim(env('SITE_URL', 'https://yourdomain.com'), '/'));
define('SITE_EMAIL', env('SITE_EMAIL', 'noreply@yourdomain.com'));

// ── Section URLs (no trailing slash) ─────────────────────────
define('ADMIN_URL',  SITE_URL . '/admin');
define('PORTAL_URL', SITE_URL . '/portal');
define('MARKET_URL', SITE_URL . '/marketplace');

// ── Admin ─────────────────────────────────────────────────────
// Admin authenticates via the members table (ADMIN-001, role=admin).
// This hash is only used as a final fallback if the members table is unreachable.
define('ADMIN_FALLBACK_HASH', env('ADMIN_FALLBACK_HASH', ''));

// ── Security ─────────────────────────────────────────────────
define('ADMIN_MAX_ATTEMPTS', 5);    // lock after N failed admin logins
define('ADMIN_LOCKOUT_SEC',  900);  // 15 minutes lockout

// ── Notifications ─────────────────────────────────────────────
// Set to '1' to enable email notifications (requires PHP mail() working on host)
define('NOTIFY_EMAIL',    env('NOTIFY_EMAIL',    '1'));

// ── Koperasi Identity ─────────────────────────────────────────
define('KOPERASI_NAME',    env('KOPERASI_NAME',    'Koperasi Kakitangan Bank Rakyat'));
define('KOPERASI_ABBREV',  env('KOPERASI_ABBREV',  'KKBR'));
define('KOPERASI_TAGLINE', env('KOPERASI_TAGLINE', 'Bersatu, Berusaha, Berjaya'));
define('KOPERASI_EMAIL',   env('KOPERASI_EMAIL',   'info@kkbr.com.my'));
define('KOPERASI_PHONE',   env('KOPERASI_PHONE',   '+603-XXXX-XXXX'));
define('KOPERASI_ADDRESS', env('KOPERASI_ADDRESS', 'Wisma Bank Rakyat, Kuala Lumpur'));
define('KOPERASI_SKM_NO',  env('KOPERASI_SKM_NO',  'SKM-XXXX-XXXX'));
define('KOPERASI_FB',      env('KOPERASI_FB',      ''));
define('KOPERASI_IG',      env('KOPERASI_IG',      ''));
define('MEMBER_ID_PREFIX', env('MEMBER_ID_PREFIX', 'KKBR-'));

// ── Session ───────────────────────────────────────────────────
define('SESSION_NAME', 'koponix_session');

// ── Categories & Lookup Data ──────────────────────────────────
define('CATEGORIES', json_encode([
    'Home Services', 'Education & Tutoring', 'Creative & Design',
    'Language & Translation', 'Technical & Repair', 'Events & Assistance',
    'Delivery & Logistics', 'Digital Services', 'Other',
]));
define('CATEGORY_ICONS', json_encode([
    'Home Services'          => '🏠',
    'Education & Tutoring'   => '📚',
    'Creative & Design'      => '🎨',
    'Language & Translation' => '🌐',
    'Technical & Repair'     => '🔧',
    'Events & Assistance'    => '🎉',
    'Delivery & Logistics'   => '🚚',
    'Digital Services'       => '💻',
    'Other'                  => '⭐',
]));
define('CATEGORY_COLORS', json_encode([
    'Home Services'          => '#1abc9c',
    'Education & Tutoring'   => '#3498db',
    'Creative & Design'      => '#9b59b6',
    'Language & Translation' => '#e67e22',
    'Technical & Repair'     => '#e74c3c',
    'Events & Assistance'    => '#f39c12',
    'Delivery & Logistics'   => '#2ecc71',
    'Digital Services'       => '#1a5276',
    'Other'                  => '#607d8b',
]));
define('LOCATIONS', json_encode([
    'Kuala Lumpur', 'Petaling Jaya', 'Shah Alam', 'Subang Jaya', 'Klang',
    'Ampang', 'Cheras', 'Puchong', 'Seremban', 'Johor Bahru',
    'Penang', 'Ipoh', 'Kota Kinabalu', 'Kuching', 'Online / Remote',
]));
define('EXPERIENCE_OPTIONS', json_encode([
    'Less than 1 year', '1 year', '2 years', '3 years', '4 years',
    '5 years', '6 years', '7 years', '8 years', '9 years',
    '10 years', 'More than 10 years',
]));
define('KOPONIX_SYSTEM_PROMPT', "You are Koponix AI, the official AI assistant for Koponix — the digital marketplace platform of Koperasi Kakitangan Bank Rakyat (KKBR), a registered Malaysian cooperative.\n\nPLATFORM CONTEXT\nKoponix is an AI-powered Koperasi Digital Economy Activation System built exclusively for Koperasi Kakitangan Bank Rakyat members. It enables member-to-member services: members can list their skills, find trusted service providers within the cooperative, and transact in a structured and traceable way. All members use a KKBR-XXXXX member ID format.\n\nYOUR ROLE\nYou help Koperasi Kakitangan Bank Rakyat members and staff by:\n- explaining how Koponix and the cooperative marketplace works\n- guiding members to register as service providers\n- helping buyers find suitable services\n- matching buyers with relevant member-sellers\n- assisting with service inquiries, order flow, and transaction steps\n- generating simple marketing content for members\n- supporting koperasi staff with platform guidance\n- keeping communication professional, clear, practical, and aligned with Malaysian koperasi values\n\nCORE IDENTITY\nKoperasi Kakitangan Bank Rakyat's Koponix platform helps members:\n- earn income using their skills within the koperasi community\n- discover trusted services from fellow cooperative members\n- transact in a structured and traceable way\n- support koperasi growth through active economic participation\n\nCOMMUNICATION STYLE\nProfessional, practical, encouraging, concise, friendly, easy to understand.\nUse simple Malaysian business English by default.\nIf the user writes in Bahasa Malaysia or Chinese, respond in the same language.\n\nPRIMARY OBJECTIVES\n1. Help the user complete the next step\n2. Reduce confusion\n3. Increase transaction readiness\n4. Increase member participation in Koperasi Kakitangan Bank Rakyat\n5. Guide users safely within platform rules\n\nWHEN USER IS A BUYER\nCollect: service type, location, preferred date/time, budget range, job scope, urgency, special requirements.\n\nWHEN USER IS A SELLER\nCollect: name, KKBR member ID (format: KKBR-XXXXX), service category, service title, experience, service area, price range, availability, description.\n\nSERVICE DESCRIPTION RULE\nKeep it honest, simple, state what is included/not included, avoid exaggerated claims.\n\nTRUST & SAFETY RULES\nAlways encourage: clear service scope, transparent pricing, traceable transaction flow.\nNever encourage: hidden charges, misleading claims, unlawful work.\n\nCONVERSION RULE\nAlways move user toward a practical next step. Never end with vague motivational language only.");
