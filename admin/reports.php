<?php
declare(strict_types=1);

/**
 * admin/reports.php
 * Revenue, usage, referral, and margin reports with CSV export.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/layout.php';

boot_session();
$admin = require_admin('/admin/login.php');
$pdo   = db();

// ── CSV export ────────────────────────────────────────────────────────────────
$export = $_GET['export'] ?? '';
if ($export === 'revenue') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="revenue_report_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Month','Approved Orders','Revenue','Credits Sold']);
    $rows = $pdo->query(
        'SELECT DATE_FORMAT(approved_at,"%Y-%m") AS month,
                COUNT(*) AS orders,
                SUM(amount) AS revenue,
                SUM(credits) AS credits
         FROM `payment_orders` WHERE status="approved"
         GROUP BY month ORDER BY month DESC LIMIT 24'
    )->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, [$r['month'], $r['orders'], $r['revenue'], $r['credits']]);
    }
    fclose($out);
    exit;
}

if ($export === 'jobs') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="jobs_report_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Job ID','User','Status','Resolution','Duration','Credit Cost','Created','Completed']);
    $rows = $pdo->query(
        'SELECT vj.id, u.email, vj.status, vj.resolution, vj.duration,
                vj.credit_cost, vj.created_at, vj.completed_at
         FROM `video_jobs` vj JOIN `users` u ON u.id=vj.user_id
         ORDER BY vj.created_at DESC LIMIT 10000'
    )->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, array_values($r));
    }
    fclose($out);
    exit;
}

$currency = setting('currency', 'MYR');

// ── Revenue by month (last 12) ────────────────────────────────────────────────
$revenueByMonth = $pdo->query(
    'SELECT DATE_FORMAT(approved_at,"%Y-%m") AS month,
            COUNT(*) AS orders,
            SUM(amount) AS revenue,
            SUM(credits) AS credits
     FROM `payment_orders` WHERE status="approved"
     GROUP BY month ORDER BY month DESC LIMIT 12'
)->fetchAll();

// ── Job stats by status ───────────────────────────────────────────────────────
$jobStats = $pdo->query(
    'SELECT status, COUNT(*) AS cnt, COALESCE(SUM(credit_cost),0) AS total_cost
     FROM `video_jobs` GROUP BY status ORDER BY cnt DESC'
)->fetchAll();

// ── Top users by credits spent ────────────────────────────────────────────────
$topUsers = $pdo->query(
    'SELECT u.name, u.email,
            COUNT(vj.id)             AS jobs,
            COALESCE(SUM(vj.credit_cost),0) AS credits_used
     FROM `users` u
     LEFT JOIN `video_jobs` vj ON vj.user_id = u.id AND vj.status = "completed"
     GROUP BY u.id ORDER BY credits_used DESC LIMIT 10'
)->fetchAll();

// ── Referral summary ──────────────────────────────────────────────────────────
$refStats = [
    'total'    => (int)$pdo->query('SELECT COUNT(*) FROM `referrals`')->fetchColumn(),
    'rewarded' => (int)$pdo->query('SELECT COUNT(*) FROM `referrals` WHERE status="rewarded"')->fetchColumn(),
    'credits'  => (float)$pdo->query('SELECT COALESCE(SUM(credits),0) FROM `referral_rewards`')->fetchColumn(),
];

// ── Overall KPIs ──────────────────────────────────────────────────────────────
$kpi = [
    'revenue'       => (float)$pdo->query('SELECT COALESCE(SUM(amount),0) FROM `payment_orders` WHERE status="approved"')->fetchColumn(),
    'credits_sold'  => (float)$pdo->query('SELECT COALESCE(SUM(credits),0) FROM `payment_orders` WHERE status="approved"')->fetchColumn(),
    'credits_used'  => (float)$pdo->query('SELECT COALESCE(SUM(credit_cost),0) FROM `video_jobs` WHERE status IN ("completed","failed","refunded")')->fetchColumn(),
    'total_jobs'    => (int)$pdo->query('SELECT COUNT(*) FROM `video_jobs`')->fetchColumn(),
    'completed'     => (int)$pdo->query('SELECT COUNT(*) FROM `video_jobs` WHERE status="completed"')->fetchColumn(),
    'failed'        => (int)$pdo->query('SELECT COUNT(*) FROM `video_jobs` WHERE status IN ("failed","refunded")'  )->fetchColumn(),
];
$kpi['completion_rate'] = $kpi['total_jobs'] > 0
    ? round($kpi['completed'] / $kpi['total_jobs'] * 100, 1) : 0;
$kpi['credits_balance'] = $kpi['credits_sold'] - $kpi['credits_used']; // unused credits = liability
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports — <?= e(setting('site_name','Motions')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
</head>
<body>
<?php render_admin_navbar($admin); ?>
<div class="admin-wrap">
    <?php render_admin_sidebar('reports'); ?>
    <main class="admin-content">

        <div class="page-header">
            <h1 class="page-title">Reports</h1>
            <div style="display:flex;gap:8px">
                <a href="?export=revenue" class="btn btn-ghost btn-sm">↓ Revenue CSV</a>
                <a href="?export=jobs"    class="btn btn-ghost btn-sm">↓ Jobs CSV</a>
            </div>
        </div>

        <!-- Overall KPIs -->
        <div class="kpi-grid mb-4">
            <div class="kpi-card">
                <div class="kpi-label">Total Revenue</div>
                <div class="kpi-value text-accent"><?= e(format_currency($kpi['revenue'], $currency)) ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Credits Sold</div>
                <div class="kpi-value"><?= e(format_credits($kpi['credits_sold'])) ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Credits Used</div>
                <div class="kpi-value"><?= e(format_credits($kpi['credits_used'])) ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Unused Credits</div>
                <div class="kpi-value text-warning"><?= e(format_credits($kpi['credits_balance'])) ?></div>
                <div class="kpi-sub">platform liability</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Completion Rate</div>
                <div class="kpi-value <?= $kpi['completion_rate'] >= 90 ? 'text-success' : 'text-warning' ?>">
                    <?= $kpi['completion_rate'] ?>%
                </div>
                <div class="kpi-sub"><?= $kpi['completed'] ?> / <?= $kpi['total_jobs'] ?> jobs</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Referral Credits Issued</div>
                <div class="kpi-value"><?= e(format_credits($refStats['credits'])) ?></div>
                <div class="kpi-sub"><?= $refStats['rewarded'] ?> rewarded</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

            <!-- Revenue by month -->
            <div class="card">
                <div class="card-header"><span class="card-title">Revenue by Month</span></div>
                <?php if (empty($revenueByMonth)): ?>
                    <p class="text-muted text-sm text-center" style="padding:24px">No revenue data yet.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr><th>Month</th><th>Orders</th><th>Revenue</th><th>Credits</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($revenueByMonth as $r): ?>
                                    <tr>
                                        <td class="fw-bold"><?= e($r['month']) ?></td>
                                        <td><?= (int)$r['orders'] ?></td>
                                        <td class="text-accent fw-bold"><?= e(format_currency((float)$r['revenue'], $currency)) ?></td>
                                        <td><?= e(format_credits((float)$r['credits'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Job stats by status -->
            <div class="card">
                <div class="card-header"><span class="card-title">Job Stats by Status</span></div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>Status</th><th>Count</th><th>Credits</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            $sb = ['queued'=>'badge-muted','processing'=>'badge-info',
                                   'completed'=>'badge-success','failed'=>'badge-danger','refunded'=>'badge-warning'];
                            foreach ($jobStats as $js): ?>
                                <tr>
                                    <td><span class="badge <?= $sb[$js['status']] ?? 'badge-muted' ?>"><?= e($js['status']) ?></span></td>
                                    <td class="fw-bold"><?= (int)$js['cnt'] ?></td>
                                    <td><?= e(format_credits((float)$js['total_cost'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top users -->
            <div class="card">
                <div class="card-header"><span class="card-title">Top 10 Users by Credits Used</span></div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>User</th><th>Jobs</th><th>Credits Used</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($topUsers)): ?>
                                <tr><td colspan="3" class="text-center text-muted" style="padding:20px">No data yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($topUsers as $u): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-sm"><?= e($u['name']) ?></div>
                                            <div class="text-muted" style="font-size:.75rem"><?= e($u['email']) ?></div>
                                        </td>
                                        <td><?= (int)$u['jobs'] ?></td>
                                        <td class="fw-bold text-accent"><?= e(format_credits((float)$u['credits_used'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Referral breakdown -->
            <div class="card">
                <div class="card-header"><span class="card-title">Referral Summary</span></div>
                <div class="kpi-grid" style="grid-template-columns:1fr 1fr;gap:12px">
                    <div class="kpi-card">
                        <div class="kpi-label">Total Referrals</div>
                        <div class="kpi-value"><?= $refStats['total'] ?></div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Rewarded</div>
                        <div class="kpi-value text-success"><?= $refStats['rewarded'] ?></div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Pending</div>
                        <div class="kpi-value text-warning"><?= $refStats['total'] - $refStats['rewarded'] ?></div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Credits Issued</div>
                        <div class="kpi-value text-accent"><?= e(format_credits($refStats['credits'])) ?></div>
                    </div>
                </div>
                <div style="margin-top:16px">
                    <a href="<?= BASE_URL ?>/admin/referrals.php" class="btn btn-ghost btn-sm">
                        View All Referrals →
                    </a>
                </div>
            </div>

        </div>
    </main>
</div>
</body>
</html>
