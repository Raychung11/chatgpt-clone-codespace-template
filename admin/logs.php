<?php
declare(strict_types=1);

/**
 * admin/logs.php
 * Activity log viewer — filterable by actor type and action keyword.
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
$actorFilter  = $_GET['actor']  ?? 'all';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));

$where  = [];
$params = [];

if (in_array($actorFilter, ['user','admin','system'], true)) {
    $where[]  = 'actor_type = ?';
    $params[] = $actorFilter;
}
if ($search !== '') {
    $where[]  = '(action LIKE ? OR description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$wSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cs = $pdo->prepare("SELECT COUNT(*) FROM `activity_logs` $wSQL");
$cs->execute($params);
$total = (int)$cs->fetchColumn();
$pager = paginate($total, $page, 50); // 50 per page for logs

$stmt = $pdo->prepare(
    "SELECT al.*,
            CASE al.actor_type
                WHEN 'user'  THEN (SELECT name FROM `users`  WHERE id = al.actor_id)
                WHEN 'admin' THEN (SELECT name FROM `admins` WHERE id = al.actor_id)
                ELSE 'System'
            END AS actor_name
     FROM `activity_logs` al
     $wSQL
     ORDER BY al.created_at DESC
     LIMIT {$pager['per_page']} OFFSET {$pager['offset']}"
);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$actorBadge = [
    'user'   => 'badge-info',
    'admin'  => 'badge-primary',
    'system' => 'badge-muted',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs — <?= e(setting('site_name','VideoSaaS')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
</head>
<body>
<?php render_admin_navbar($admin); ?>
<div class="admin-wrap">
    <?php render_admin_sidebar('logs'); ?>
    <main class="admin-content">

        <div class="page-header">
            <div>
                <h1 class="page-title">Activity Logs</h1>
                <p class="page-sub"><?= number_format($total) ?> entries</p>
            </div>
        </div>

        <!-- Filters -->
        <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
            <?php foreach (['all'=>'All','user'=>'Users','admin'=>'Admins','system'=>'System'] as $v=>$l): ?>
                <a href="?actor=<?= $v ?><?= $search ? '&q='.urlencode($search) : '' ?>"
                   class="btn btn-sm <?= $actorFilter===$v ? 'btn-primary' : 'btn-ghost' ?>">
                    <?= $l ?>
                </a>
            <?php endforeach; ?>
            <form method="GET" style="margin-left:auto;display:flex;gap:8px">
                <input type="hidden" name="actor" value="<?= e($actorFilter) ?>">
                <input type="text" name="q" value="<?= e($search) ?>"
                       class="form-control btn-sm" placeholder="Search action / description…"
                       style="max-width:260px">
                <button type="submit" class="btn btn-ghost btn-sm">Search</button>
                <?php if ($search): ?>
                    <a href="?actor=<?= e($actorFilter) ?>" class="btn btn-ghost btn-sm">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Actor</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="5" class="text-center text-muted" style="padding:32px">No logs found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="text-muted text-sm" style="white-space:nowrap">
                                        <?= e(format_datetime($log['created_at'])) ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $actorBadge[$log['actor_type']] ?? 'badge-muted' ?>">
                                            <?= e($log['actor_type']) ?>
                                        </span>
                                        <?php if ($log['actor_name']): ?>
                                            <div class="text-muted" style="font-size:.75rem;margin-top:2px">
                                                <?= e($log['actor_name']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code style="font-size:.8rem;color:var(--color-accent)">
                                            <?= e($log['action']) ?>
                                        </code>
                                    </td>
                                    <td class="text-muted text-sm">
                                        <?= e($log['description'] ?? '—') ?>
                                    </td>
                                    <td class="text-muted text-sm" style="font-family:monospace">
                                        <?= e($log['ip_address'] ?? '—') ?>
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
