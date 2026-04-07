<?php
declare(strict_types=1);

/**
 * admin/index.php
 * Admin dashboard — KPIs and recent activity.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/wallet.php';
require_once __DIR__ . '/../inc/layout.php';

boot_session();
$admin = require_admin('/admin/login.php');

$pdo = db();

// ── KPIs ──────────────────────────────────────────────────────────────────────
function kpi(PDO $pdo, string $sql, array $params = []): int|float
{
    $s = $pdo->prepare($sql);
    $s->execute($params);
    $val = $s->fetchColumn();
    return is_numeric($val) ? $val + 0 : 0;
}

try {
$totalUsers      = kpi($pdo, 'SELECT COUNT(*) FROM `users`');
$activeUsers     = kpi($pdo, 'SELECT COUNT(*) FROM `users` WHERE `is_active` = 1');
$pendingPayments = kpi($pdo, 'SELECT COUNT(*) FROM `payment_orders` WHERE `status` = "pending"');
$totalRevenue    = kpi($pdo, 'SELECT COALESCE(SUM(`amount`),0) FROM `payment_orders` WHERE `status` = "approved"');
$totalCreditsOut = kpi($pdo, 'SELECT COALESCE(SUM(`credits`),0) FROM `payment_orders` WHERE `status` = "approved"');
$totalJobs       = kpi($pdo, 'SELECT COUNT(*) FROM `video_jobs`');
$jobsProcessing  = kpi($pdo, 'SELECT COUNT(*) FROM `video_jobs` WHERE `status` IN ("queued","processing")');
$totalReferrals  = kpi($pdo, 'SELECT COUNT(*) FROM `referrals`');
$totalTokens     = kpi($pdo, 'SELECT COALESCE(SUM(`tokens_used`),0) FROM `video_jobs` WHERE `status` IN ("completed","failed")');
$totalApiCost    = kpi($pdo, 'SELECT COALESCE(SUM(`api_cost_usd`),0) FROM `video_jobs` WHERE `status` IN ("completed","failed")');

// New users this month
$newUsers30 = kpi($pdo, 'SELECT COUNT(*) FROM `users` WHERE `created_at` >= DATE_SUB(NOW(), INTERVAL 30 DAY)');

// Revenue this month
$revenue30 = kpi($pdo,
    'SELECT COALESCE(SUM(`amount`),0) FROM `payment_orders`
     WHERE `status` = "approved" AND `approved_at` >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
);

// ── Recent pending payments ───────────────────────────────────────────────────
$pendingList = $pdo->query(
    'SELECT po.*, u.name AS user_name, u.email AS user_email
     FROM `payment_orders` po JOIN `users` u ON u.id = po.user_id
     WHERE po.status = "pending"
     ORDER BY po.created_at DESC LIMIT 8'
)->fetchAll();

// ── Recent video jobs ─────────────────────────────────────────────────────────
$recentJobs = $pdo->query(
    'SELECT vj.id, vj.status, vj.credit_cost, vj.created_at, u.name AS user_name
     FROM `video_jobs` vj JOIN `users` u ON u.id = vj.user_id
     ORDER BY vj.created_at DESC LIMIT 8'
)->fetchAll();

} catch (\Throwable $e) {
    // Show DB error to admin so it can be diagnosed
    http_response_code(500);
    echo '<pre style="background:#1a1a2e;color:#ff6b6b;padding:24px;font-family:monospace;margin:0">';
    echo '<strong>Dashboard DB Error</strong>' . "\n\n";
    echo htmlspecialchars($e->getMessage()) . "\n\n";
    echo 'Likely cause: missing database tables. Re-import sql/schema.sql via phpMyAdmin.' . "\n";
    echo '</pre>';
    exit;
}

$currency = setting('currency', 'MYR');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — <?= e(setting('site_name','VideoSaaS')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
</head>
<body>
<?php render_admin_navbar($admin); ?>
<div class="admin-wrap">
    <?php render_admin_sidebar('dashboard'); ?>

    <main class="admin-content">
        <?= render_flash() ?>

        <div class="page-header">
            <div>
                <h1 class="page-title">Dashboard</h1>
                <p class="page-sub">Platform overview</p>
            </div>
            <?php if ($pendingPayments > 0): ?>
                <a href="<?= BASE_URL ?>/admin/payments.php?status=pending" class="btn btn-accent">
                    🔔 <?= $pendingPayments ?> Pending Payment<?= $pendingPayments > 1 ? 's' : '' ?>
                </a>
            <?php endif; ?>
        </div>

        <!-- KPI Grid -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">Total Users</div>
                <div class="kpi-value"><?= $totalUsers ?></div>
                <div class="kpi-sub">+<?= $newUsers30 ?> this month</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Total Revenue</div>
                <div class="kpi-value text-accent"><?= e(format_currency((float)$totalRevenue, $currency)) ?></div>
                <div class="kpi-sub"><?= e(format_currency((float)$revenue30, $currency)) ?> this month</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Credits Sold</div>
                <div class="kpi-value"><?= e(format_credits((float)$totalCreditsOut)) ?></div>
                <div class="kpi-sub">total issued</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Video Jobs</div>
                <div class="kpi-value"><?= $totalJobs ?></div>
                <div class="kpi-sub"><?= $jobsProcessing ?> in queue</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Pending Payments</div>
                <div class="kpi-value <?= $pendingPayments > 0 ? 'text-danger' : '' ?>"><?= $pendingPayments ?></div>
                <div class="kpi-sub">awaiting approval</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Referrals</div>
                <div class="kpi-value"><?= $totalReferrals ?></div>
                <div class="kpi-sub">total tracked</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">API Tokens Used</div>
                <div class="kpi-value"><?= number_format((float)$totalTokens) ?></div>
                <div class="kpi-sub">est. cost: $<?= number_format((float)$totalApiCost, 4) ?></div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

            <!-- Pending payments -->
            <div class="card">
                <div class="card-header d-flex justify-between align-center">
                    <span class="card-title">Pending Payments</span>
                    <a href="<?= BASE_URL ?>/admin/payments.php" class="text-sm text-muted">View all →</a>
                </div>
                <?php if (empty($pendingList)): ?>
                    <p class="text-muted text-sm text-center" style="padding:24px 0">No pending payments.</p>
                <?php else: ?>
                    <?php foreach ($pendingList as $p): ?>
                        <div style="padding:8px 0;border-bottom:1px solid var(--color-border)">
                            <div class="d-flex justify-between align-center">
                                <div>
                                    <span class="fw-bold text-sm"><?= e($p['user_name']) ?></span>
                                    <span class="text-muted text-sm"> · <?= e(format_currency((float)$p['amount'], $currency)) ?></span>
                                </div>
                                <a href="<?= BASE_URL ?>/admin/payments.php?id=<?= (int)$p['id'] ?>" class="btn btn-accent btn-sm">Review</a>
                            </div>
                            <div class="text-muted text-sm"><?= e(format_credits((float)$p['credits'])) ?> credits · <?= e(format_datetime($p['created_at'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Recent video jobs -->
            <div class="card">
                <div class="card-header d-flex justify-between align-center">
                    <span class="card-title">Recent Video Jobs</span>
                    <a href="<?= BASE_URL ?>/admin/jobs.php" class="text-sm text-muted">View all →</a>
                </div>
                <?php if (empty($recentJobs)): ?>
                    <p class="text-muted text-sm text-center" style="padding:24px 0">No jobs yet.</p>
                <?php else: ?>
                    <?php
                    $statusBadge = [
                        'queued'     => 'badge-muted',
                        'processing' => 'badge-info',
                        'completed'  => 'badge-success',
                        'failed'     => 'badge-danger',
                        'refunded'   => 'badge-warning',
                    ];
                    foreach ($recentJobs as $j): ?>
                        <div style="padding:8px 0;border-bottom:1px solid var(--color-border)">
                            <div class="d-flex justify-between align-center">
                                <span class="text-sm"><?= e($j['user_name']) ?></span>
                                <span class="badge <?= $statusBadge[$j['status']] ?? 'badge-muted' ?>"><?= e($j['status']) ?></span>
                            </div>
                            <div class="text-muted text-sm"><?= e(format_credits((float)$j['credit_cost'])) ?> credits · <?= e(format_datetime($j['created_at'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>
</body>
</html>
