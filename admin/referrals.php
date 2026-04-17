<?php
declare(strict_types=1);

/**
 * admin/referrals.php
 * View all referrals, reward status, and credits issued.
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

// ── Filters ───────────────────────────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? 'all';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));

$where  = [];
$params = [];

if ($statusFilter === 'pending')  { $where[] = 'r.status = "pending"'; }
if ($statusFilter === 'rewarded') { $where[] = 'r.status = "rewarded"'; }

if ($search !== '') {
    $where[]  = '(referrer.name LIKE ? OR referrer.email LIKE ? OR referee.name LIKE ? OR referee.email LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%";
    $params[] = "%$search%"; $params[] = "%$search%";
}

$wSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cs = $pdo->prepare(
    "SELECT COUNT(*) FROM `referrals` r
     JOIN `users` referrer ON referrer.id = r.referrer_id
     JOIN `users` referee  ON referee.id  = r.referee_id
     $wSQL"
);
$cs->execute($params);
$total = (int)$cs->fetchColumn();
$pager = paginate($total, $page);

$stmt = $pdo->prepare(
    "SELECT r.*,
            referrer.name  AS referrer_name,  referrer.email AS referrer_email,
            referee.name   AS referee_name,   referee.email  AS referee_email,
            referee.created_at AS referee_joined,
            rr.credits AS reward_credits
     FROM `referrals` r
     JOIN `users` referrer ON referrer.id = r.referrer_id
     JOIN `users` referee  ON referee.id  = r.referee_id
     LEFT JOIN `referral_rewards` rr ON rr.referral_id = r.id
     $wSQL
     ORDER BY r.created_at DESC
     LIMIT {$pager['per_page']} OFFSET {$pager['offset']}"
);
$stmt->execute($params);
$referrals = $stmt->fetchAll();

// KPIs
$totalRefs    = (int)$pdo->query('SELECT COUNT(*) FROM `referrals`')->fetchColumn();
$rewardedRefs = (int)$pdo->query('SELECT COUNT(*) FROM `referrals` WHERE status="rewarded"')->fetchColumn();
$totalRewards = (float)$pdo->query('SELECT COALESCE(SUM(credits),0) FROM `referral_rewards`')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referrals — <?= e(setting('site_name','Motions')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
</head>
<body>
<?php render_admin_navbar($admin); ?>
<div class="admin-wrap">
    <?php render_admin_sidebar('referrals'); ?>
    <main class="admin-content">
        <?= render_flash() ?>

        <div class="page-header">
            <h1 class="page-title">Referrals</h1>
        </div>

        <!-- KPIs -->
        <div class="kpi-grid mb-4">
            <div class="kpi-card">
                <div class="kpi-label">Total Referrals</div>
                <div class="kpi-value"><?= $totalRefs ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Rewarded</div>
                <div class="kpi-value text-success"><?= $rewardedRefs ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Pending</div>
                <div class="kpi-value text-warning"><?= $totalRefs - $rewardedRefs ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Credits Issued</div>
                <div class="kpi-value text-accent"><?= e(format_credits($totalRewards)) ?></div>
            </div>
        </div>

        <!-- Filter -->
        <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
            <?php foreach (['all'=>'All','pending'=>'Pending','rewarded'=>'Rewarded'] as $v=>$l): ?>
                <a href="?status=<?= $v ?><?= $search ? '&q='.urlencode($search) : '' ?>"
                   class="btn btn-sm <?= $statusFilter===$v ? 'btn-primary' : 'btn-ghost' ?>">
                    <?= $l ?>
                </a>
            <?php endforeach; ?>
            <form method="GET" style="margin-left:auto;display:flex;gap:8px">
                <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                <input type="text" name="q" value="<?= e($search) ?>"
                       class="form-control btn-sm" placeholder="Search referrer / referee…"
                       style="max-width:240px">
                <button type="submit" class="btn btn-ghost btn-sm">Search</button>
            </form>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Referrer</th>
                            <th>Referee</th>
                            <th>Referee Joined</th>
                            <th>Status</th>
                            <th>Credits Issued</th>
                            <th>Rewarded At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($referrals)): ?>
                            <tr><td colspan="6" class="text-center text-muted" style="padding:32px">No referrals found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($referrals as $ref): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-sm"><?= e($ref['referrer_name']) ?></div>
                                        <div class="text-muted" style="font-size:.75rem"><?= e($ref['referrer_email']) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-sm"><?= e($ref['referee_name']) ?></div>
                                        <div class="text-muted" style="font-size:.75rem"><?= e($ref['referee_email']) ?></div>
                                    </td>
                                    <td class="text-muted text-sm"><?= e(format_datetime($ref['referee_joined'])) ?></td>
                                    <td>
                                        <?php if ($ref['status'] === 'rewarded'): ?>
                                            <span class="badge badge-success">Rewarded</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Pending purchase</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ref['reward_credits']): ?>
                                            <span class="fw-bold text-accent">
                                                <?= e(format_credits((float)$ref['reward_credits'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted text-sm">
                                        <?= $ref['rewarded_at'] ? e(format_datetime($ref['rewarded_at'])) : '—' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php render_pagination($pager); ?>
        </div>
    </main>
</div>
</body>
</html>
