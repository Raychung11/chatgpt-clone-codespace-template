<?php
// ============================================================
//  KOPONIX – Database Setup
//  Run ONCE via browser: https://yourdomain.com/setup.php
//  Then DELETE this file from the server.
// ============================================================
require_once __DIR__ . '/functions.php';

// One-run protection: refuse to run if .setup_done exists
$setup_flag = __DIR__ . '/.setup_done';
if (file_exists($setup_flag)) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><title>Setup</title>'
        . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>'
        . '<body class="p-4"><div class="container" style="max-width:500px">'
        . '<div class="alert alert-danger"><strong>Setup already completed.</strong><br>'
        . 'Delete <code>setup.php</code> from your server. If you need to re-run setup, '
        . 'delete <code>.setup_done</code> first.</div>'
        . '<a href="index.php" class="btn btn-primary">Go to Koponix</a>'
        . '</div></body></html>');
}

$errors = [];
$done   = [];

try {
    $pdo = db();

    // Members table
    $pdo->exec("CREATE TABLE IF NOT EXISTS members (
        id            VARCHAR(36)  PRIMARY KEY,
        name          VARCHAR(255) NOT NULL,
        koperasi_id   VARCHAR(100) NOT NULL UNIQUE,
        email         VARCHAR(255) DEFAULT '',
        password_hash VARCHAR(64)  NOT NULL,
        role          VARCHAR(50)  DEFAULT 'member',
        joined_date   DATE         NOT NULL,
        avatar        VARCHAR(255) DEFAULT '',
        bio           TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done[] = 'Table <b>members</b> created.';

    // Sellers table
    $pdo->exec("CREATE TABLE IF NOT EXISTS sellers (
        id              VARCHAR(36)  PRIMARY KEY,
        name            VARCHAR(255) NOT NULL,
        koperasi_id     VARCHAR(100) NOT NULL,
        category        VARCHAR(100) NOT NULL,
        service_title   VARCHAR(255) NOT NULL,
        area            VARCHAR(100) NOT NULL,
        price_range     VARCHAR(100) NOT NULL,
        availability    VARCHAR(255) DEFAULT '',
        description     TEXT,
        contact         VARCHAR(255) DEFAULT 'WhatsApp available upon request',
        experience      VARCHAR(50)  DEFAULT '',
        registered_date DATE         NOT NULL,
        status          VARCHAR(20)  DEFAULT 'active',
        image           VARCHAR(255) DEFAULT '',
        gallery1        VARCHAR(255) DEFAULT '',
        gallery2        VARCHAR(255) DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done[] = 'Table <b>sellers</b> created.';

    // Add new columns to existing tables (safe to run multiple times)
    foreach ([
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS avatar        VARCHAR(255) DEFAULT ''",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS bio           TEXT",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS status        VARCHAR(20)  DEFAULT 'active'",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS referral_code VARCHAR(20)  DEFAULT ''",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS referred_by   VARCHAR(20)  DEFAULT ''",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS credits       INT          DEFAULT 0",
        "ALTER TABLE sellers ADD COLUMN IF NOT EXISTS image         VARCHAR(255) DEFAULT ''",
        "ALTER TABLE sellers ADD COLUMN IF NOT EXISTS gallery1      VARCHAR(255) DEFAULT ''",
        "ALTER TABLE sellers ADD COLUMN IF NOT EXISTS gallery2      VARCHAR(255) DEFAULT ''",
        "ALTER TABLE sellers ADD COLUMN IF NOT EXISTS admin_note    VARCHAR(500) DEFAULT ''",
        "ALTER TABLE requests ADD COLUMN IF NOT EXISTS credits_used INT          DEFAULT 0",
    ] as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $e) { /* already exists */ }
    }
    $done[] = 'Columns updated (credits, referral_code, referred_by, credits_used).';

    // Credit transactions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS credit_transactions (
        id            VARCHAR(8)   PRIMARY KEY,
        member_kop_id VARCHAR(100) NOT NULL,
        amount        INT          NOT NULL,
        type          VARCHAR(10)  NOT NULL DEFAULT 'earn',
        description   VARCHAR(255) DEFAULT '',
        created_at    DATETIME     NOT NULL,
        INDEX idx_cred_member (member_kop_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done[] = 'Table <b>credit_transactions</b> created.';

    // Create uploads directories
    $dirs = [
        __DIR__ . '/uploads',
        __DIR__ . '/uploads/sellers',
        __DIR__ . '/uploads/avatars',
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            $done[] = "Created directory: <code>{$dir}</code>";
        }
    }
    // Security: block PHP in uploads
    $htaccess = __DIR__ . '/uploads/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "<FilesMatch \"\.(php|php5|phtml|cgi|pl|py)$\">\n    Order Allow,Deny\n    Deny from all\n</FilesMatch>");
        $done[] = 'Created <code>uploads/.htaccess</code> (blocks PHP execution).';
    }

    // Requests table
    $pdo->exec("CREATE TABLE IF NOT EXISTS requests (
        id                  VARCHAR(36)  PRIMARY KEY,
        buyer_name          VARCHAR(255) NOT NULL,
        buyer_contact       VARCHAR(255) NOT NULL,
        member_kop_id       VARCHAR(100) DEFAULT '',
        category            VARCHAR(100) NOT NULL,
        location            VARCHAR(100) NOT NULL,
        service_description TEXT         NOT NULL,
        preferred_date      DATE,
        urgency             VARCHAR(100) DEFAULT '',
        budget              VARCHAR(100) DEFAULT '',
        special_notes       TEXT,
        submitted_date      DATE         NOT NULL,
        status              VARCHAR(20)  DEFAULT 'open'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done[] = 'Table <b>requests</b> created.';

    // Conversations table
    $pdo->exec("CREATE TABLE IF NOT EXISTS conversations (
        id             VARCHAR(8)   PRIMARY KEY,
        buyer_kop_id   VARCHAR(100) NOT NULL,
        buyer_name     VARCHAR(255) NOT NULL,
        seller_kop_id  VARCHAR(100) NOT NULL,
        seller_name    VARCHAR(255) NOT NULL,
        subject        VARCHAR(255) NOT NULL,
        seller_id      VARCHAR(36)  DEFAULT '',
        created_date   DATE         NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done[] = 'Table <b>conversations</b> created.';

    // Messages table
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id            VARCHAR(8)   PRIMARY KEY,
        conv_id       VARCHAR(8)   NOT NULL,
        sender_kop_id VARCHAR(100) NOT NULL,
        sender_name   VARCHAR(255) NOT NULL,
        text          TEXT         NOT NULL,
        timestamp     DATETIME     NOT NULL,
        is_ai         TINYINT(1)   DEFAULT 0,
        FOREIGN KEY (conv_id) REFERENCES conversations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done[] = 'Table <b>messages</b> created.';

    // Bookings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
        id            VARCHAR(20)  PRIMARY KEY,
        seller_id     VARCHAR(36)  NOT NULL,
        seller_kop_id VARCHAR(100) NOT NULL,
        seller_name   VARCHAR(255) NOT NULL,
        service_title VARCHAR(255) NOT NULL,
        category      VARCHAR(100) NOT NULL,
        buyer_kop_id  VARCHAR(100) DEFAULT '',
        buyer_name    VARCHAR(255) NOT NULL,
        buyer_contact VARCHAR(255) NOT NULL,
        booking_date  DATE,
        booking_time  VARCHAR(50)  DEFAULT '',
        notes         TEXT,
        status        VARCHAR(20)  DEFAULT 'pending',
        created_at    DATETIME     NOT NULL,
        INDEX idx_book_seller (seller_kop_id),
        INDEX idx_book_buyer  (buyer_kop_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done[] = 'Table <b>bookings</b> created.';

    // ── Seed demo sellers ────────────────────────────────────
    $count = (int) $pdo->query('SELECT COUNT(*) FROM sellers')->fetchColumn();
    if ($count === 0) {
        $demo = [
            ['demo-001','Ahmad Rizal bin Hassan','KKBR-00001','Home Services','Professional Home Cleaning','Kuala Lumpur','RM 80 – RM 120 per session','Weekends, Weekday evenings','Reliable home cleaning service covering living areas, bedrooms, kitchen, and bathrooms. Bring own equipment. Min 2-hour booking. Does not include deep cleaning of drains or heavy furniture moving.','WhatsApp available upon request','3 years','2026-01-10'],
            ['demo-002','Siti Norzahra binti Azmi','KKBR-00002','Education & Tutoring','Math & Science Tutor (Form 1–5)','Petaling Jaya','RM 50 – RM 80 per hour','Mon–Fri evenings, Sat mornings','SPM-focused tutoring for Mathematics and Science subjects. Home visit or online via Google Meet. Small group sessions available at reduced rate. 5 years teaching experience.','WhatsApp available upon request','5 years','2026-01-15'],
            ['demo-003','David Lim Wei Keat','KKBR-00003','Creative & Design','Logo & Branding Design','Online / Remote','RM 200 – RM 500 per project','Mon–Fri, 9am–6pm','Professional logo design and basic brand identity packages. Includes up to 3 concept drafts and 2 revision rounds. Delivers editable files (AI, PNG, PDF). Social media kit available as add-on.','WhatsApp available upon request','4 years','2026-01-20'],
            ['demo-004','Nurul Izzati Mohamad','KKBR-00004','Language & Translation','English ↔ Bahasa Malaysia Translation','Online / Remote','RM 0.12 – RM 0.18 per word','Flexible, 24–48 hour turnaround','Accurate translation for documents, reports, marketing materials, and websites. Specialises in business and legal texts. Certified translator.','WhatsApp available upon request','6 years','2026-01-22'],
            ['demo-005','Hafiz Rahman','KKBR-00005','Technical & Repair','Electrical & Plumbing Repair','Shah Alam','RM 80 – RM 250 per job','Mon–Sat, 8am–6pm','Minor electrical wiring, switch/socket replacement, water pipe leaks, tap installation and basic plumbing. Covers Klang Valley area. Parts cost not included. Emergency call-out at additional charge.','WhatsApp available upon request','8 years','2026-02-01'],
            ['demo-006','Aileen Tan Mei Ling','KKBR-00006','Events & Assistance','Event Helper & Emcee (Bilingual)','Klang','RM 150 – RM 350 per day','Weekends, Public Holidays','Event coordination assistant and bilingual emcee (English/Mandarin) for weddings, corporate dinners, and community events. Experienced in crowd management and MC script preparation.','WhatsApp available upon request','3 years','2026-02-05'],
            ['demo-007','Mohd Faizal Nordin','KKBR-00007','Delivery & Logistics','Same-Day Item Delivery (Klang Valley)','Subang Jaya','RM 25 – RM 60 per trip','Daily, 8am–9pm','Point-to-point delivery within Klang Valley using MPV. Suitable for documents, food items, small parcels, and market goods. Max load 30kg. Price varies by distance.','WhatsApp available upon request','2 years','2026-02-10'],
            ['demo-008','Rashidah Omar','KKBR-00008','Digital Services','Social Media Management','Online / Remote','RM 300 – RM 800 per month','Mon–Fri','Monthly social media management for Facebook and Instagram. Includes content planning, 12 posts/month, basic graphic design, and monthly performance report. Ad budget not included.','WhatsApp available upon request','4 years','2026-02-12'],
        ];
        $stmt = $pdo->prepare('INSERT INTO sellers (id,name,koperasi_id,category,service_title,area,price_range,availability,description,contact,experience,registered_date,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($demo as $row) {
            $stmt->execute([...$row, 'active']);
        }
        $done[] = 'Seeded <b>8 demo sellers</b>.';
    } else {
        $done[] = "Sellers table already has {$count} rows — skipped seeding.";
    }

    // ── Seed admin member ────────────────────────────────────
    $adminExists = $pdo->prepare('SELECT id FROM members WHERE koperasi_id=?');
    $adminExists->execute(['ADMIN-001']);
    if (!$adminExists->fetch()) {
        $adminId = gen_uuid();
        $pdo->prepare('INSERT INTO members (id,name,koperasi_id,email,password_hash,role,joined_date) VALUES (?,?,?,?,?,?,?)')
            ->execute([$adminId, 'Administrator', 'ADMIN-001', 'admin@koponix.my', hash_password('admin123'), 'admin', date('Y-m-d')]);
        $done[] = 'Created admin account: <b>ADMIN-001</b> / password: <b>admin123</b> — change it!';
    }

} catch (PDOException $e) {
    $errors[] = 'Database error: ' . $e->getMessage();
}

// Mark setup as done (only if no errors)
if (empty($errors)) {
    file_put_contents($setup_flag, date('Y-m-d H:i:s'));
    $done[] = 'Setup flag created: <code>.setup_done</code>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Koponix Setup</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container" style="max-width:640px">
<h2>🤝 Koponix — Database Setup</h2>
<?php foreach ($done as $msg): ?>
    <div class="alert alert-success">✅ <?= $msg ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $msg): ?>
    <div class="alert alert-danger">❌ <?= e($msg) ?></div>
<?php endforeach; ?>
<?php if (empty($errors)): ?>
<div class="alert alert-warning mt-3">
    <strong>⚠️ Important:</strong> Delete <code>setup.php</code> from your server now for security.
</div>
<a href="index.php" class="btn btn-primary">Go to Koponix →</a>
<?php endif; ?>
</div>
</body>
</html>
