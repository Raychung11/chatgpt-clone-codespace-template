<?php
// ============================================================
//  ONE-TIME MIGRATION — Create bookings table
//  Run via browser: https://yourdomain.com/migrate_bookings.php
//  DELETE THIS FILE immediately after running.
// ============================================================
require_once __DIR__ . '/functions.php';

$errors = [];
$done   = [];

try {
    db()->exec("CREATE TABLE IF NOT EXISTS bookings (
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
    $done[] = 'Table <b>bookings</b> created (or already existed).';

    // Add phone column to members (safe if already exists)
    try {
        db()->exec("ALTER TABLE members ADD COLUMN IF NOT EXISTS phone VARCHAR(30) DEFAULT ''");
        $done[] = 'Column <b>members.phone</b> added (or already existed).';
    } catch (Throwable $e) { $errors[] = 'phone column: ' . $e->getMessage(); }
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Migrate — Koponix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4" style="max-width:580px;margin:auto">
<h4>🔧 Bookings Migration</h4>
<?php foreach ($done as $msg): ?>
    <div class="alert alert-success py-2">✅ <?= $msg ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger py-2">❌ <?= htmlspecialchars($err) ?></div>
<?php endforeach; ?>
<div class="alert alert-warning mt-3">
    <strong>⚠️ Delete this file now.</strong><br>
    Remove <code>migrate_bookings.php</code> from your server immediately.
</div>
<a href="marketplace/" class="btn btn-primary">View Marketplace →</a>
</body>
</html>
