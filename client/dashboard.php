<?php
declare(strict_types=1);

/**
 * client/dashboard.php
 * Main client dashboard — wallet balance, recent jobs, quick actions.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/wallet.php';
require_once __DIR__ . '/../inc/layout.php';

boot_session();
$user = require_auth('/public/login.php');
$uid  = (int)$user['id'];

// ── Data ──────────────────────────────────────────────────────────────────────
$balance = wallet_balance($uid);

// Recent video jobs (last 5)
$stmtJobs = db()->prepare(
    'SELECT `id`,`prompt`,`status`,`credit_cost`,`created_at`
     FROM `video_jobs` WHERE `user_id` = ?
     ORDER BY `created_at` DESC LIMIT 5'
);
$stmtJobs->execute([$uid]);
$recentJobs = $stmtJobs->fetchAll();

// Recent transactions (last 5)
$stmtTxn = db()->prepare(
    'SELECT `type`,`amount`,`balance_after`,`note`,`created_at`
     FROM `wallet_transactions` WHERE `user_id` = ?
     ORDER BY `created_at` DESC LIMIT 5'
);
$stmtTxn->execute([$uid]);
$recentTxns = $stmtTxn->fetchAll();

// Total jobs count
$totalJobs = (int)db()->prepare('SELECT COUNT(*) FROM `video_jobs` WHERE `user_id` = ?')
    ->execute([$uid]) && ($s = db()->prepare('SELECT COUNT(*) FROM `video_jobs` WHERE `user_id` = ?')) && $s->execute([$uid]) ? (int)$s->fetchColumn() : 0;
// Simple direct query
$s = db()->prepare('SELECT COUNT(*) FROM `video_jobs` WHERE `user_id` = ?');
$s->execute([$uid]);
$totalJobs = (int)$s->fetchColumn();

$s2 = db()->prepare('SELECT COUNT(*) FROM `video_jobs` WHERE `user_id` = ? AND `status` = "completed"');
$s2->execute([$uid]);
$completedJobs = (int)$s2->fetchColumn();

$s3 = db()->prepare('SELECT COUNT(*) FROM `referrals` WHERE `referrer_id` = ?');
$s3->execute([$uid]);
$totalReferrals = (int)$s3->fetchColumn();

$statusBadge = [
    'queued'     => 'badge-muted',
    'processing' => 'badge-info',
    'completed'  => 'badge-success',
    'failed'     => 'badge-danger',
    'refunded'   => 'badge-warning',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?= e(setting('site_name','Motions')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
</head>
<body>
<?php render_client_navbar($user, 'dashboard'); ?>

<div class="container main-content">
    <?= render_flash() ?>

    <!-- Welcome -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Welcome, <?= e($user['name']) ?> 👋</h1>
            <p class="page-sub">Here's a snapshot of your account</p>
        </div>
        <a href="<?= BASE_URL ?>/client/generate.php" class="btn btn-primary">
            + Generate Video
        </a>
    </div>

    <!-- KPI row -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Wallet Balance</div>
            <div class="kpi-value text-primary"><?= e(format_credits($balance)) ?></div>
            <div class="kpi-sub">credits available</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Videos Generated</div>
            <div class="kpi-value"><?= $totalJobs ?></div>
            <div class="kpi-sub"><?= $completedJobs ?> completed</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Referrals</div>
            <div class="kpi-value"><?= $totalReferrals ?></div>
            <div class="kpi-sub">users referred</div>
        </div>
        <div class="kpi-card" style="cursor:pointer" onclick="location.href='<?= BASE_URL ?>/client/buy-credits.php'">
            <div class="kpi-label">Buy Credits</div>
            <div class="kpi-value text-accent">+</div>
            <div class="kpi-sub">top up your wallet</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">

        <!-- Recent Video Jobs -->
        <div class="card">
            <div class="card-header d-flex justify-between align-center">
                <span class="card-title">Recent Videos</span>
                <a href="<?= BASE_URL ?>/client/history.php" class="text-sm text-muted">View all →</a>
            </div>
            <?php if (empty($recentJobs)): ?>
                <p class="text-muted text-sm text-center" style="padding:24px 0">
                    No videos yet. <a href="<?= BASE_URL ?>/client/generate.php">Generate your first!</a>
                </p>
            <?php else: ?>
                <?php foreach ($recentJobs as $job): ?>
                    <div style="padding:10px 0;border-bottom:1px solid var(--color-border)">
                        <div class="d-flex justify-between align-center">
                            <span class="text-sm" style="max-width:60%"><?= e(truncate($job['prompt'], 45)) ?></span>
                            <span class="badge <?= $statusBadge[$job['status']] ?? 'badge-muted' ?>">
                                <?= e($job['status']) ?>
                            </span>
                        </div>
                        <div class="text-muted text-sm mt-1">
                            <?= e(format_credits((float)$job['credit_cost'])) ?> credits ·
                            <?= e(format_datetime($job['created_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Recent Transactions -->
        <div class="card">
            <div class="card-header d-flex justify-between align-center">
                <span class="card-title">Recent Transactions</span>
                <a href="<?= BASE_URL ?>/client/wallet.php" class="text-sm text-muted">View all →</a>
            </div>
            <?php if (empty($recentTxns)): ?>
                <p class="text-muted text-sm text-center" style="padding:24px 0">No transactions yet.</p>
            <?php else: ?>
                <?php foreach ($recentTxns as $txn): ?>
                    <?php
                    $amt    = (float)$txn['amount'];
                    $isPos  = $amt > 0;
                    $color  = $isPos ? 'var(--color-success)' : 'var(--color-danger)';
                    $sign   = $isPos ? '+' : '';
                    $typeLabels = [
                        'topup'            => 'Top-up',
                        'deduction'        => 'Video Generation',
                        'refund'           => 'Refund',
                        'referral_reward'  => 'Referral Reward',
                        'admin_adjustment' => 'Admin Adjustment',
                    ];
                    ?>
                    <div style="padding:10px 0;border-bottom:1px solid var(--color-border)">
                        <div class="d-flex justify-between align-center">
                            <span class="text-sm"><?= e($typeLabels[$txn['type']] ?? $txn['type']) ?></span>
                            <span class="fw-bold" style="color:<?= $color ?>">
                                <?= $sign ?><?= e(format_credits(abs($amt))) ?>
                            </span>
                        </div>
                        <div class="text-muted text-sm mt-1">
                            Balance: <?= e(format_credits((float)$txn['balance_after'])) ?> ·
                            <?= e(format_datetime($txn['created_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

    <!-- Quick actions -->
    <div class="card mt-4">
        <div class="card-header"><span class="card-title">Quick Actions</span></div>
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <a href="<?= BASE_URL ?>/client/generate.php"     class="btn btn-primary">Generate Video</a>
            <a href="<?= BASE_URL ?>/client/buy-credits.php"  class="btn btn-accent">Buy Credits</a>
            <a href="<?= BASE_URL ?>/client/referral.php"     class="btn btn-ghost">Share Referral</a>
            <a href="<?= BASE_URL ?>/client/history.php"      class="btn btn-ghost">View History</a>
            <a href="<?= BASE_URL ?>/client/profile.php"      class="btn btn-ghost">Profile Settings</a>
        </div>
    </div>

</div>
</body>
</html>
