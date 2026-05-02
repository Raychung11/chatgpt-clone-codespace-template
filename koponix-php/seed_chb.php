<?php
// ============================================================
//  ONE-TIME SEED — Chung Han Bon seller profile
//  Run once via browser: https://yourdomain.com/seed_chb.php
//  DELETE THIS FILE immediately after running.
// ============================================================
require_once __DIR__ . '/functions.php';

$errors = [];
$done   = [];

try {
    // ── 1. Create member account if not exists ───────────────
    $kop_id = 'KKBR-123456';
    $existing = get_member_by_kop_id($kop_id);

    if (!$existing) {
        save_member([
            'name'          => 'Chung Han Bon',
            'koperasi_id'   => $kop_id,
            'email'         => '',
            'password_hash' => hash_password('changeme123'),
        ]);
        ensure_referral_code($kop_id);
        add_credits($kop_id, 10, 'Welcome bonus — account created');
        $done[] = 'Member account created: <b>' . $kop_id . '</b> — default password: <b>changeme123</b> (change it after login)';
    } else {
        $done[] = 'Member <b>' . $kop_id . '</b> already exists — skipped member creation.';
    }

    // ── 2. Create seller listing if not exists ───────────────
    $existing_listings = get_sellers(['koperasi_id' => $kop_id]);

    if (empty($existing_listings)) {
        save_seller([
            'name'          => 'Chung Han Bon',
            'koperasi_id'   => $kop_id,
            'category'      => 'Digital Services',
            'service_title' => 'Website Design',
            'area'          => 'Online / Remote',
            'price_range'   => 'RM 200 – RM 500',
            'availability'  => 'Everyday',
            'experience'    => '3 years',
            'description'   => 'Professional website design for small businesses, freelancers, and community organisations. Services include responsive multi-page websites, landing pages, and basic e-commerce setups. Built on WordPress or plain HTML/CSS depending on your needs. Package includes up to 5 pages, contact form, mobile-friendly layout, and 1 round of revisions. Hosting setup guidance provided. Does not include domain registration or monthly hosting fees.',
            'contact'       => '0133866827',
            'status'        => 'active',
        ]);
        $done[] = 'Seller listing created: <b>Website Design</b> (Digital Services) — status: active.';
    } else {
        $done[] = 'Seller listing for <b>' . $kop_id . '</b> already exists — skipped.';
    }

} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Seed — Koponix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4" style="max-width:640px;margin:auto">
<h4>🌱 Seller Seed — Chung Han Bon</h4>
<?php foreach ($done as $msg): ?>
    <div class="alert alert-success py-2">✅ <?= $msg ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger py-2">❌ <?= htmlspecialchars($err) ?></div>
<?php endforeach; ?>
<div class="alert alert-warning mt-3">
    <strong>⚠️ Delete this file now.</strong><br>
    Remove <code>seed_chb.php</code> from your server immediately — it should not be publicly accessible.
</div>
<a href="marketplace/" class="btn btn-primary">View Marketplace →</a>
<a href="portal/" class="btn btn-outline-secondary ms-2">Login →</a>
</body>
</html>
