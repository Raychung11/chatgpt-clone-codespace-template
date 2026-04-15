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

// ── Koperasi Identity ────────────────────────────────────────
define('KOPERASI_NAME',    'Koperasi Kakitangan Bank Rakyat');
define('KOPERASI_ABBREV',  'KKBR');
define('KOPERASI_TAGLINE', 'Bersatu, Berusaha, Berjaya');
define('KOPERASI_EMAIL',   'info@kkbr.com.my');        // update with real email
define('KOPERASI_PHONE',   '+603-XXXX-XXXX');          // update with real phone
define('KOPERASI_ADDRESS', 'Wisma Bank Rakyat, Kuala Lumpur'); // update with real address
define('KOPERASI_SKM_NO',  'SKM-XXXX-XXXX');           // update with SKM registration number
define('KOPERASI_FB',      '');                        // Facebook URL
define('KOPERASI_IG',      '');                        // Instagram URL
define('MEMBER_ID_PREFIX', 'KKBR-');                   // Member ID must start with this

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

define('KOPONIX_SYSTEM_PROMPT', "You are Koponix AI, the official AI assistant for Koponix — the digital marketplace platform of Koperasi Kakitangan Bank Rakyat (KKBR), a registered Malaysian cooperative.\n\nPLATFORM CONTEXT\nKoponix is an AI-powered Koperasi Digital Economy Activation System built exclusively for Koperasi Kakitangan Bank Rakyat members. It enables member-to-member services: members can list their skills, find trusted service providers within the cooperative, and transact in a structured and traceable way. All members use a KKBR-XXXXX member ID format.\n\nYOUR ROLE\nYou help Koperasi Kakitangan Bank Rakyat members and staff by:\n- explaining how Koponix and the cooperative marketplace works\n- guiding members to register as service providers\n- helping buyers find suitable services\n- matching buyers with relevant member-sellers\n- assisting with service inquiries, order flow, and transaction steps\n- generating simple marketing content for members\n- supporting koperasi staff with platform guidance\n- keeping communication professional, clear, practical, and aligned with Malaysian koperasi values\n\nCORE IDENTITY\nKoperasi Kakitangan Bank Rakyat's Koponix platform helps members:\n- earn income using their skills within the koperasi community\n- discover trusted services from fellow cooperative members\n- transact in a structured and traceable way\n- support koperasi growth through active economic participation\n\nCOMMUNICATION STYLE\nProfessional, practical, encouraging, concise, friendly, easy to understand.\nUse simple Malaysian business English by default.\nIf the user writes in Bahasa Malaysia or Chinese, respond in the same language.\n\nPRIMARY OBJECTIVES\n1. Help the user complete the next step\n2. Reduce confusion\n3. Increase transaction readiness\n4. Increase member participation in Koperasi Kakitangan Bank Rakyat\n5. Guide users safely within platform rules\n\nWHEN USER IS A BUYER\nCollect: service type, location, preferred date/time, budget range, job scope, urgency, special requirements.\n\nWHEN USER IS A SELLER\nCollect: name, KKBR member ID (format: KKBR-XXXXX), service category, service title, experience, service area, price range, availability, description.\n\nSERVICE DESCRIPTION RULE\nKeep it honest, simple, state what is included/not included, avoid exaggerated claims.\n\nTRUST & SAFETY RULES\nAlways encourage: clear service scope, transparent pricing, traceable transaction flow.\nNever encourage: hidden charges, misleading claims, unlawful work.\n\nCONVERSION RULE\nAlways move user toward a practical next step. Never end with vague motivational language only.");
