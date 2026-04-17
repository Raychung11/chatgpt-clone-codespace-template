<?php
declare(strict_types=1);

/**
 * admin/jobs.php
 * View all video generation jobs. Filter by status. Manual refund for failed jobs.
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
$pdo   = db();

// ── Actions ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';
    $jobId  = (int)($_POST['job_id'] ?? 0);

    if ($action === 'refund' && $jobId) {
        $job = $pdo->prepare(
            'SELECT * FROM `video_jobs` WHERE `id` = ? AND `status` = "failed" LIMIT 1'
        );
        $job->execute([$jobId]);
        $job = $job->fetch();

        if (!$job) {
            flash_error('Job not found or not in a failed state.');
        } else {
            $pdo->beginTransaction();
            try {
                $result = wallet_refund(
                    (int)$job['user_id'],
                    (float)$job['credit_cost'],
                    'video_job',
                    $jobId,
                    'Manual admin refund for job #' . $jobId
                );

                if (!$result['ok']) {
                    throw new RuntimeException($result['error']);
                }

                $pdo->prepare(
                    'UPDATE `video_jobs` SET `status`="refunded", `refunded_at`=NOW() WHERE `id`=?'
                )->execute([$jobId]);

                $pdo->commit();
                log_activity('admin', (int)$admin['id'], 'manual_refund', 'Refunded job #' . $jobId);
                flash_success('Job #' . $jobId . ' refunded successfully.');
            } catch (Throwable $e) {
                $pdo->rollBack();
                flash_error('Refund failed: ' . $e->getMessage());
            }
        }
    }

    redirect(BASE_URL . '/admin/jobs.php?status=' . urlencode($_POST['status_filter'] ?? 'all'));
}

// ── Filters ───────────────────────────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? 'all';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));

$where  = [];
$params = [];

$validStatuses = ['queued','processing','completed','failed','refunded'];
if (in_array($statusFilter, $validStatuses, true)) {
    $where[]  = 'vj.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $where[]  = '(u.name LIKE ? OR u.email LIKE ? OR vj.api_task_id LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$wSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cs = $pdo->prepare(
    "SELECT COUNT(*) FROM `video_jobs` vj
     JOIN `users` u ON u.id = vj.user_id $wSQL"
);
$cs->execute($params);
$total = (int)$cs->fetchColumn();
$pager = paginate($total, $page);

$stmt = $pdo->prepare(
    "SELECT vj.*, u.name AS user_name, u.email AS user_email,
            vo.cdn_url, vo.thumbnail
     FROM `video_jobs` vj
     JOIN `users` u ON u.id = vj.user_id
     LEFT JOIN `video_outputs` vo ON vo.job_id = vj.id
     $wSQL
     ORDER BY vj.created_at DESC
     LIMIT {$pager['per_page']} OFFSET {$pager['offset']}"
);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

// Summary counts
$counts = [];
foreach (array_merge(['all'], $validStatuses) as $s) {
    $q = $s === 'all'
        ? $pdo->query('SELECT COUNT(*) FROM `video_jobs`')
        : (function() use ($pdo, $s) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM `video_jobs` WHERE status=?');
            $st->execute([$s]);
            return $st;
        })();
    $counts[$s] = (int)$q->fetchColumn();
}

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
    <title>Video Jobs — <?= e(setting('site_name','Motions')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
    <style>
        .thumb { width:80px;height:45px;object-fit:cover;border-radius:4px;
                 border:1px solid var(--color-border);vertical-align:middle; }
        .prompt-cell { max-width:280px; }
    </style>
</head>
<body>
<?php render_admin_navbar($admin); ?>
<div class="admin-wrap">
    <?php render_admin_sidebar('jobs'); ?>
    <main class="admin-content">
        <?= render_flash() ?>

        <div class="page-header">
            <div>
                <h1 class="page-title">Video Jobs</h1>
                <p class="page-sub"><?= $total ?> job<?= $total !== 1 ? 's' : '' ?> found</p>
            </div>
        </div>

        <!-- Status tabs -->
        <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
            <?php
            $tabLabels = [
                'all'        => 'All',
                'queued'     => 'Queued',
                'processing' => 'Processing',
                'completed'  => 'Completed',
                'failed'     => 'Failed',
                'refunded'   => 'Refunded',
            ];
            foreach ($tabLabels as $val => $label):
            ?>
                <a href="?status=<?= $val ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
                   class="btn btn-sm <?= $statusFilter === $val ? 'btn-primary' : 'btn-ghost' ?>">
                    <?= $label ?> <span class="text-muted">(<?= $counts[$val] ?? 0 ?>)</span>
                </a>
            <?php endforeach; ?>

            <form method="GET" style="margin-left:auto;display:flex;gap:8px">
                <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                <input type="text" name="q" value="<?= e($search) ?>"
                       class="form-control btn-sm" placeholder="Search user / task ID…"
                       style="max-width:240px">
                <button type="submit" class="btn btn-ghost btn-sm">Search</button>
                <?php if ($search): ?>
                    <a href="?status=<?= e($statusFilter) ?>" class="btn btn-ghost btn-sm">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Preview</th>
                            <th>User</th>
                            <th class="prompt-cell">Prompt</th>
                            <th>Quality</th>
                            <th>Credits</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($jobs)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted" style="padding:32px">
                                    No jobs found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($jobs as $job): ?>
                                <tr>
                                    <td class="text-muted text-sm"><?= (int)$job['id'] ?></td>
                                    <td>
                                        <?php if ($job['thumbnail']): ?>
                                            <img src="<?= e($job['thumbnail']) ?>"
                                                 class="thumb" alt="thumb">
                                        <?php elseif ($job['cdn_url']): ?>
                                            <span class="text-muted text-sm">🎥 ready</span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-sm"><?= e($job['user_name']) ?></div>
                                        <div class="text-muted" style="font-size:.75rem"><?= e($job['user_email']) ?></div>
                                    </td>
                                    <td class="prompt-cell">
                                        <span class="text-sm" title="<?= e($job['prompt']) ?>">
                                            <?= e(truncate($job['prompt'], 70)) ?>
                                        </span>
                                        <?php if ($job['api_task_id']): ?>
                                            <div style="font-size:.7rem;color:var(--color-muted);margin-top:2px;font-family:monospace">
                                                <?= e(truncate($job['api_task_id'], 30)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-sm">
                                        <?= e($job['resolution'] ?? '—') ?><br>
                                        <span class="text-muted"><?= (int)$job['duration'] ?>s</span>
                                    </td>
                                    <td class="fw-bold"><?= e(format_credits((float)$job['credit_cost'])) ?></td>
                                    <td>
                                        <span class="badge <?= $statusBadge[$job['status']] ?? 'badge-muted' ?>">
                                            <?= e($job['status']) ?>
                                        </span>
                                        <?php if ($job['error_message']): ?>
                                            <div style="font-size:.72rem;color:var(--color-danger);margin-top:3px;max-width:120px">
                                                <?= e(truncate($job['error_message'], 50)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted text-sm">
                                        <?= e(format_datetime($job['created_at'])) ?>
                                        <?php if ($job['completed_at']): ?>
                                            <br>
                                            <span style="font-size:.72rem">
                                                Done: <?= e(format_datetime($job['completed_at'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display:flex;flex-direction:column;gap:5px">
                                            <?php if ($job['cdn_url']): ?>
                                                <a href="<?= e($job['cdn_url']) ?>"
                                                   target="_blank" rel="noopener"
                                                   class="btn btn-ghost btn-sm">▶ View</a>
                                            <?php endif; ?>

                                            <?php if ($job['status'] === 'failed'): ?>
                                                <form method="POST"
                                                      onsubmit="return confirm('Refund <?= e(format_credits((float)$job['credit_cost'])) ?> credits to <?= e(addslashes($job['user_name'])) ?>?')">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action"        value="refund">
                                                    <input type="hidden" name="job_id"        value="<?= (int)$job['id'] ?>">
                                                    <input type="hidden" name="status_filter" value="<?= e($statusFilter) ?>">
                                                    <button type="submit" class="btn btn-warning btn-sm"
                                                            style="width:100%;background:rgba(246,173,85,.2);color:#fbd38d;border-color:#f6ad55">
                                                        ↩ Refund
                                                    </button>
                                                </form>
                                            <?php elseif ($job['status'] === 'refunded'): ?>
                                                <span class="text-muted text-sm">Refunded</span>
                                            <?php endif; ?>

                                            <a href="<?= BASE_URL ?>/admin/wallets.php?user_id=<?= (int)$job['user_id'] ?>"
                                               class="btn btn-ghost btn-sm">Wallet</a>
                                        </div>
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
