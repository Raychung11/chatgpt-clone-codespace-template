<?php
// ============================================================
//  KOPONIX – Configuration
//  Edit the values below before uploading to Hostinger
// ============================================================

// ── Database (from Hostinger cPanel → MySQL Databases) ─────
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');      // e.g. u123456789_koponix
define('DB_USER', 'your_db_user');      // e.g. u123456789_admin
define('DB_PASS', 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

// ── Anthropic API ───────────────────────────────────────────
define('ANTHROPIC_API_KEY', 'sk-ant-your-key-here');
define('CLAUDE_MODEL', 'claude-sonnet-4-6');
define('CLAUDE_MODEL_OPUS', 'claude-opus-4-6');

// ── Site ────────────────────────────────────────────────────
define('SITE_NAME', 'Koponix');
define('SITE_URL', 'https://yourdomain.com');   // no trailing slash
define('ADMIN_PASSWORD_HASH', hash('sha256', 'admin123')); // change 'admin123'

// ── Session ─────────────────────────────────────────────────
define('SESSION_NAME', 'koponix_session');

// ── Constants ───────────────────────────────────────────────
define('CATEGORIES', json_encode([
    'Home Services',
    'Education & Tutoring',
    'Creative & Design',
    'Language & Translation',
    'Technical & Repair',
    'Events & Assistance',
    'Delivery & Logistics',
    'Digital Services',
    'Other',
]));

define('CATEGORY_ICONS', json_encode([
    'Home Services'        => '🏠',
    'Education & Tutoring' => '📚',
    'Creative & Design'    => '🎨',
    'Language & Translation' => '🌐',
    'Technical & Repair'   => '🔧',
    'Events & Assistance'  => '🎉',
    'Delivery & Logistics' => '🚚',
    'Digital Services'     => '💻',
    'Other'                => '⭐',
]));

define('CATEGORY_COLORS', json_encode([
    'Home Services'        => '#1abc9c',
    'Education & Tutoring' => '#3498db',
    'Creative & Design'    => '#9b59b6',
    'Language & Translation' => '#e67e22',
    'Technical & Repair'   => '#e74c3c',
    'Events & Assistance'  => '#f39c12',
    'Delivery & Logistics' => '#2ecc71',
    'Digital Services'     => '#1a5276',
    'Other'                => '#607d8b',
]));

define('LOCATIONS', json_encode([
    'Kuala Lumpur', 'Petaling Jaya', 'Shah Alam', 'Subang Jaya', 'Klang',
    'Ampang', 'Cheras', 'Puchong', 'Seremban', 'Johor Bahru',
    'Penang', 'Ipoh', 'Kota Kinabalu', 'Kuching', 'Online / Remote',
]));

define('EXPERIENCE_OPTIONS', json_encode([
    'Less than 1 year', '1 year', '2 years', '3 years',
    '4 years', '5 years', '6 years', '7 years', '8 years',
    '9 years', '10 years', 'More than 10 years',
]));

define('KOPONIX_SYSTEM_PROMPT', "You are Koponix AI, the official AI assistant for Koponix, an AI-powered Koperasi Digital Economy Activation System in Malaysia.\n\nYOUR ROLE\nYou help koperasi members and administrators activate the member-to-member economy by:\n- explaining how Koponix works\n- guiding members to register as service providers\n- helping buyers find suitable services\n- matching buyers with relevant member-sellers\n- assisting with service inquiries, order flow, and transaction steps\n- generating simple marketing content for members\n- supporting koperasi staff with platform guidance\n- keeping communication professional, clear, practical, and aligned with Malaysian koperasi context\n\nCORE IDENTITY\nKoponix is a koperasi-centric digital economy activation system that helps members:\n- earn income using their skills\n- discover trusted services within the koperasi ecosystem\n- transact in a structured and traceable way\n- support koperasi growth through member economic participation\n\nCOMMUNICATION STYLE\nProfessional, practical, encouraging, concise, friendly, easy to understand.\nUse simple Malaysian business English by default.\nIf the user writes in Bahasa Malaysia or Chinese, respond in the same language.\n\nPRIMARY OBJECTIVES\n1. Help the user complete the next step\n2. Reduce confusion\n3. Increase transaction readiness\n4. Increase member participation\n5. Guide users safely within platform rules\n\nWHEN USER IS A BUYER\nCollect: service type, location, preferred date/time, budget range, job scope, urgency, special requirements.\n\nWHEN USER IS A SELLER\nCollect: name, koperasi/member identity, service category, service title, experience, service area, price range, availability, description.\n\nSERVICE DESCRIPTION RULE\nKeep it honest, simple, state what is included/not included, avoid exaggerated claims.\n\nTRUST & SAFETY RULES\nAlways encourage: clear service scope, transparent pricing, traceable transaction flow.\nNever encourage: hidden charges, misleading claims, unlawful work.\n\nCONVERSION RULE\nAlways move user toward a practical next step. Never end with vague motivational language only.");
