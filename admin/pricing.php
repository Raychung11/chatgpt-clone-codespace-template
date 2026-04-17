<?php
declare(strict_types=1);

/**
 * admin/pricing.php
 * Manage video generation pricing rules (resolution × duration × credit cost).
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

// ── Form actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['name']        ?? '');
        $description = trim($_POST['description'] ?? '');
        $resolution  = trim($_POST['resolution']  ?? '');
        $duration    = (int)($_POST['duration']   ?? 0);
        $credit_cost = (float)($_POST['credit_cost'] ?? 0);
        $is_active   = isset($_POST['is_active']) ? 1 : 0;

        if (!$name || $credit_cost <= 0 || $duration <= 0) {
            flash_error('Name, duration, and credit cost are required and must be positive.');
        } else {
            if ($id) {
                $pdo->prepare(
                    'UPDATE `generation_pricing_rules`
                     SET `name`=?,`description`=?,`resolution`=?,`duration`=?,
                         `credit_cost`=?,`is_active`=?
                     WHERE `id`=?'
                )->execute([$name, $description, $resolution, $duration, $credit_cost, $is_active, $id]);
                log_activity('admin', (int)$admin['id'], 'update_pricing_rule', "Updated rule #$id");
                flash_success('Pricing rule updated.');
            } else {
                $pdo->prepare(
                    'INSERT INTO `generation_pricing_rules`
                     (`name`,`description`,`resolution`,`duration`,`credit_cost`,`is_active`)
                     VALUES (?,?,?,?,?,?)'
                )->execute([$name, $description, $resolution, $duration, $credit_cost, $is_active]);
                log_activity('admin', (int)$admin['id'], 'create_pricing_rule', "Created rule: $name");
                flash_success('Pricing rule created.');
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            // Prevent deletion if any jobs use this rule
            $inUse = $pdo->prepare('SELECT COUNT(*) FROM `video_jobs` WHERE `pricing_rule_id`=?');
            $inUse->execute([$id]);
            if ((int)$inUse->fetchColumn() > 0) {
                flash_error('Cannot delete: this rule has associated video jobs. Deactivate it instead.');
            } else {
                $pdo->prepare('DELETE FROM `generation_pricing_rules` WHERE `id`=?')->execute([$id]);
                flash_success('Pricing rule deleted.');
            }
        }
    }

    redirect(BASE_URL . '/admin/pricing.php');
}

$rules = $pdo->query(
    'SELECT r.*,
            (SELECT COUNT(*) FROM `video_jobs` vj WHERE vj.pricing_rule_id = r.id) AS job_count
     FROM `generation_pricing_rules` r
     ORDER BY r.credit_cost'
)->fetchAll();

$editing = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    foreach ($rules as $r) {
        if ((int)$r['id'] === $eid) { $editing = $r; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing Rules — <?= e(setting('site_name','Motions')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
</head>
<body>
<?php render_admin_navbar($admin); ?>
<div class="admin-wrap">
    <?php render_admin_sidebar('pricing'); ?>
    <main class="admin-content">
        <?= render_flash() ?>

        <div class="page-header">
            <div>
                <h1 class="page-title">Generation Pricing</h1>
                <p class="page-sub">Credit cost per video quality tier</p>
            </div>
            <button onclick="toggleForm()" class="btn btn-primary" id="showFormBtn">
                + Add Rule
            </button>
        </div>

        <!-- Add / Edit form -->
        <div id="ruleForm" style="display:none">
            <div class="card mb-4">
                <div class="card-header">
                    <span class="card-title"><?= $editing ? 'Edit Rule' : 'New Pricing Rule' ?></span>
                </div>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id"     value="<?= (int)($editing['id'] ?? 0) ?>">

                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:16px">
                        <div class="form-group">
                            <label class="form-label">Rule Name</label>
                            <input type="text" name="name" class="form-control"
                                   value="<?= e($editing['name'] ?? '') ?>"
                                   placeholder="e.g. HD 10s" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Resolution</label>
                            <select name="resolution" class="form-control">
                                <?php foreach (['720p','1080p','4K','480p'] as $res): ?>
                                    <option value="<?= $res ?>"
                                        <?= ($editing['resolution'] ?? '720p') === $res ? 'selected' : '' ?>>
                                        <?= $res ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Duration (seconds)</label>
                            <input type="number" name="duration" class="form-control"
                                   value="<?= (int)($editing['duration'] ?? 5) ?>"
                                   min="1" max="60" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Credit Cost</label>
                            <input type="number" name="credit_cost" class="form-control"
                                   value="<?= e($editing['credit_cost'] ?? '') ?>"
                                   min="0.01" step="0.01" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description <span class="text-muted">(optional)</span></label>
                        <input type="text" name="description" class="form-control"
                               value="<?= e($editing['description'] ?? '') ?>"
                               placeholder="Short description shown to clients" maxlength="200">
                    </div>

                    <div style="margin-bottom:16px">
                        <label style="cursor:pointer">
                            <input type="checkbox" name="is_active" value="1"
                                   <?= ($editing['is_active'] ?? 1) ? 'checked' : '' ?>>
                            Active (visible on generate page)
                        </label>
                    </div>

                    <div style="display:flex;gap:10px">
                        <button type="submit" class="btn btn-primary">Save Rule</button>
                        <a href="<?= BASE_URL ?>/admin/pricing.php" class="btn btn-ghost">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($editing): ?>
            <script>document.getElementById('ruleForm').style.display='block';document.getElementById('showFormBtn').style.display='none';</script>
        <?php endif; ?>

        <!-- Rules table -->
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Resolution</th>
                            <th>Duration</th>
                            <th>Credit Cost</th>
                            <th>Jobs Used</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rules)): ?>
                            <tr><td colspan="7" class="text-center text-muted" style="padding:24px">No pricing rules yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rules as $r): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= e($r['name']) ?></div>
                                        <?php if ($r['description']): ?>
                                            <div class="text-muted text-sm"><?= e($r['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($r['resolution'] ?? '—') ?></td>
                                    <td><?= (int)$r['duration'] ?>s</td>
                                    <td class="fw-bold text-accent">
                                        <?= e(format_credits((float)$r['credit_cost'])) ?>
                                    </td>
                                    <td><?= (int)$r['job_count'] ?></td>
                                    <td>
                                        <?php if ($r['is_active']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-muted">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:6px">
                                            <a href="?edit=<?= (int)$r['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
                                            <?php if ((int)$r['job_count'] === 0): ?>
                                                <form method="POST" style="display:inline"
                                                      onsubmit="return confirm('Delete this pricing rule?')">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id"     value="<?= (int)$r['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted text-sm" title="In use — cannot delete">🔒</span>
                                            <?php endif; ?>
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
<script>
function toggleForm() {
    const f = document.getElementById('ruleForm');
    const b = document.getElementById('showFormBtn');
    if (f.style.display === 'none') {
        f.style.display = 'block';
        b.textContent   = '✕ Cancel';
    } else {
        f.style.display = 'none';
        b.textContent   = '+ Add Rule';
    }
}
</script>
</body>
</html>
