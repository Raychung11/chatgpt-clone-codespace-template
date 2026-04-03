<?php
declare(strict_types=1);

/**
 * admin/packages.php
 * Manage credit packages (CRUD).
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

// ── Handle form actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['name']        ?? '');
        $description = trim($_POST['description'] ?? '');
        $credits     = (float)($_POST['credits']  ?? 0);
        $price       = (float)($_POST['price']    ?? 0);
        $is_popular  = isset($_POST['is_popular']) ? 1 : 0;
        $is_active   = isset($_POST['is_active'])  ? 1 : 0;
        $sort_order  = (int)($_POST['sort_order'] ?? 0);

        if (!$name || $credits <= 0 || $price <= 0) {
            flash_error('Name, credits, and price are required and must be positive.');
        } else {
            if ($id) {
                $pdo->prepare(
                    'UPDATE `credit_packages` SET `name`=?,`description`=?,`credits`=?,`price`=?,
                     `is_popular`=?,`is_active`=?,`sort_order`=? WHERE `id`=?'
                )->execute([$name, $description, $credits, $price, $is_popular, $is_active, $sort_order, $id]);
                flash_success('Package updated.');
                log_activity('admin', (int)$admin['id'], 'update_package', 'Updated package #' . $id);
            } else {
                $pdo->prepare(
                    'INSERT INTO `credit_packages` (`name`,`description`,`credits`,`price`,`is_popular`,`is_active`,`sort_order`)
                     VALUES (?,?,?,?,?,?,?)'
                )->execute([$name, $description, $credits, $price, $is_popular, $is_active, $sort_order]);
                flash_success('Package created.');
                log_activity('admin', (int)$admin['id'], 'create_package', 'Created package: ' . $name);
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare('DELETE FROM `credit_packages` WHERE `id` = ?')->execute([$id]);
            flash_success('Package deleted.');
        }
    }

    redirect(BASE_URL . '/admin/packages.php');
}

// Load all packages
$packages = $pdo->query('SELECT * FROM `credit_packages` ORDER BY `sort_order`, `id`')->fetchAll();

// Edit mode
$editing = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    foreach ($packages as $pkg) {
        if ((int)$pkg['id'] === $eid) { $editing = $pkg; break; }
    }
}

$currency = setting('currency', 'MYR');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Packages — <?= e(setting('site_name','VideoSaaS')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
</head>
<body>
<?php render_admin_navbar($admin); ?>
<div class="admin-wrap">
    <?php render_admin_sidebar('packages'); ?>
    <main class="admin-content">
        <?= render_flash() ?>

        <div class="page-header">
            <h1 class="page-title">Credit Packages</h1>
            <button onclick="document.getElementById('addForm').style.display='block';this.style.display='none'"
                    class="btn btn-primary" id="showAddBtn">+ Add Package</button>
        </div>

        <!-- Add form -->
        <div id="addForm" style="display:<?= $editing ? 'none' : 'none' ?>">
            <div class="card mb-4">
                <div class="card-header"><span class="card-title"><?= $editing ? 'Edit Package' : 'New Package' ?></span></div>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                        <div class="form-group">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control"
                                   value="<?= e($editing['name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Credits</label>
                            <input type="number" name="credits" class="form-control"
                                   value="<?= e($editing['credits'] ?? '') ?>" min="1" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Price (<?= e($currency) ?>)</label>
                            <input type="number" name="price" class="form-control"
                                   value="<?= e($editing['price'] ?? '') ?>" min="0.01" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sort Order</label>
                            <input type="number" name="sort_order" class="form-control"
                                   value="<?= (int)($editing['sort_order'] ?? 0) ?>" min="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control"
                               value="<?= e($editing['description'] ?? '') ?>" maxlength="200">
                    </div>
                    <div style="display:flex;gap:24px;margin-bottom:16px">
                        <label style="cursor:pointer">
                            <input type="checkbox" name="is_popular" value="1"
                                   <?= ($editing['is_popular'] ?? 0) ? 'checked' : '' ?>>
                            Mark as Popular
                        </label>
                        <label style="cursor:pointer">
                            <input type="checkbox" name="is_active" value="1"
                                   <?= ($editing['is_active'] ?? 1) ? 'checked' : '' ?>>
                            Active (visible to clients)
                        </label>
                    </div>
                    <div style="display:flex;gap:10px">
                        <button type="submit" class="btn btn-primary">Save Package</button>
                        <a href="<?= BASE_URL ?>/admin/packages.php" class="btn btn-ghost">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($editing): ?>
        <script>document.getElementById('addForm').style.display='block';document.getElementById('showAddBtn').style.display='none';</script>
        <?php endif; ?>

        <!-- Package list -->
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Credits</th>
                            <th>Price</th>
                            <th>Popular</th>
                            <th>Status</th>
                            <th>Order</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($packages)): ?>
                            <tr><td colspan="7" class="text-center text-muted" style="padding:24px">No packages yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($packages as $pkg): ?>
                                <tr>
                                    <td class="fw-bold"><?= e($pkg['name']) ?><br><span class="text-muted text-sm"><?= e(truncate($pkg['description'] ?? '', 40)) ?></span></td>
                                    <td><?= e(format_credits((float)$pkg['credits'])) ?></td>
                                    <td><?= e(format_currency((float)$pkg['price'], $currency)) ?></td>
                                    <td><?= $pkg['is_popular'] ? '<span class="badge badge-primary">Yes</span>' : '—' ?></td>
                                    <td><?= $pkg['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-muted">Inactive</span>' ?></td>
                                    <td><?= (int)$pkg['sort_order'] ?></td>
                                    <td>
                                        <div style="display:flex;gap:6px">
                                            <a href="?edit=<?= (int)$pkg['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
                                            <form method="POST" style="display:inline"
                                                  onsubmit="return confirm('Delete this package?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$pkg['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
</body>
</html>
