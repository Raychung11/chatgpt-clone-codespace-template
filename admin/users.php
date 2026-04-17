<?php
declare(strict_types=1);

/**
 * admin/users.php
 * Manage clients: list, search, toggle status.
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
    $action  = $_POST['action']  ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($action === 'toggle_status' && $user_id) {
        $pdo->prepare('UPDATE `users` SET `is_active` = 1 - `is_active` WHERE `id` = ?')
            ->execute([$user_id]);
        log_activity('admin', (int)$admin['id'], 'toggle_user_status', 'Toggled user #' . $user_id);
        flash_success('User status updated.');
    }
    redirect(BASE_URL . '/admin/users.php');
}

// ── Search & filter ───────────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));

$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = '(u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status === 'active')   { $where[] = 'u.is_active = 1'; }
if ($status === 'inactive') { $where[] = 'u.is_active = 0'; }

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total  = (int)$pdo->prepare("SELECT COUNT(*) FROM `users` u $whereSQL")
    ->execute($params) ? (function() use ($pdo, $whereSQL, $params) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM `users` u $whereSQL");
        $s->execute($params);
        return (int)$s->fetchColumn();
    })() : 0;

// Direct count
$cs = $pdo->prepare("SELECT COUNT(*) FROM `users` u $whereSQL");
$cs->execute($params);
$total = (int)$cs->fetchColumn();

$pager  = paginate($total, $page);

$stmt = $pdo->prepare(
    "SELECT u.id, u.name, u.email, u.is_active, u.created_at, u.last_login_at,
            COALESCE(w.balance, 0) AS balance
     FROM `users` u LEFT JOIN `wallets` w ON w.user_id = u.id
     $whereSQL
     ORDER BY u.created_at DESC
     LIMIT {$pager['per_page']} OFFSET {$pager['offset']}"
);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users — <?= e(setting('site_name','Motions')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
</head>
<body>
<?php render_admin_navbar($admin); ?>
<div class="admin-wrap">
    <?php render_admin_sidebar('users'); ?>
    <main class="admin-content">
        <?= render_flash() ?>

        <div class="page-header">
            <div>
                <h1 class="page-title">Users</h1>
                <p class="page-sub"><?= $total ?> total clients</p>
            </div>
        </div>

        <!-- Search bar -->
        <form method="GET" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
            <input type="text" name="q" value="<?= e($search) ?>" class="form-control"
                   placeholder="Search name or email…" style="max-width:320px">
            <select name="status" class="form-control" style="max-width:160px">
                <option value="">All Status</option>
                <option value="active"   <?= $status==='active'   ? 'selected':'' ?>>Active</option>
                <option value="inactive" <?= $status==='inactive' ? 'selected':'' ?>>Inactive</option>
            </select>
            <button type="submit" class="btn btn-ghost">Filter</button>
            <?php if ($search || $status): ?>
                <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-ghost">Clear</a>
            <?php endif; ?>
        </form>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="8" class="text-center text-muted" style="padding:32px">No users found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td class="text-muted">#<?= (int)$u['id'] ?></td>
                                    <td class="fw-bold"><?= e($u['name']) ?></td>
                                    <td class="text-muted"><?= e($u['email']) ?></td>
                                    <td><?= e(format_credits((float)$u['balance'])) ?></td>
                                    <td>
                                        <?php if ($u['is_active']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted text-sm"><?= e(format_datetime($u['last_login_at'])) ?></td>
                                    <td class="text-muted text-sm"><?= e(format_datetime($u['created_at'])) ?></td>
                                    <td>
                                        <div style="display:flex;gap:6px;align-items:center">
                                            <a href="<?= BASE_URL ?>/admin/wallets.php?user_id=<?= (int)$u['id'] ?>"
                                               class="btn btn-ghost btn-sm">Wallet</a>
                                            <form method="POST" style="display:inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action"  value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-danger' : 'btn-success' ?>"
                                                        onclick="return confirm('Toggle status for <?= e(addslashes($u['name'])) ?>?')">
                                                    <?= $u['is_active'] ? 'Disable' : 'Enable' ?>
                                                </button>
                                            </form>
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
