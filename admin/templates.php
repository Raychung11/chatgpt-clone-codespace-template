<?php
declare(strict_types=1);

/**
 * admin/templates.php
 * Manage prompt templates. Each template can contain {{placeholders}}
 * that clients fill in before generating a video.
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

// ── Actions ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id        = (int)($_POST['id'] ?? 0);
        $name      = trim($_POST['name']     ?? '');
        $category  = trim($_POST['category'] ?? '');
        $template  = trim($_POST['template'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (!$name || !$template) {
            flash_error('Name and template body are required.');
        } else {
            if ($id) {
                $pdo->prepare(
                    'UPDATE `prompt_templates`
                     SET `name`=?,`category`=?,`template`=?,`is_active`=?
                     WHERE `id`=?'
                )->execute([$name, $category, $template, $is_active, $id]);
                flash_success('Template updated.');
                log_activity('admin', (int)$admin['id'], 'update_template', "Updated template #$id");
            } else {
                $pdo->prepare(
                    'INSERT INTO `prompt_templates` (`name`,`category`,`template`,`is_active`)
                     VALUES (?,?,?,?)'
                )->execute([$name, $category, $template, $is_active]);
                flash_success('Template created.');
                log_activity('admin', (int)$admin['id'], 'create_template', "Created template: $name");
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare('DELETE FROM `prompt_templates` WHERE `id`=?')->execute([$id]);
            flash_success('Template deleted.');
        }
    }

    redirect(BASE_URL . '/admin/templates.php');
}

$templates = $pdo->query(
    'SELECT * FROM `prompt_templates` ORDER BY `category`, `name`'
)->fetchAll();

$editing = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    foreach ($templates as $t) {
        if ((int)$t['id'] === $eid) { $editing = $t; break; }
    }
}

// Distinct categories for filter
$categories = array_unique(array_filter(array_column($templates, 'category')));
sort($categories);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prompt Templates — <?= e(setting('site_name','VideoSaaS')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
</head>
<body>
<?php render_admin_navbar($admin); ?>
<div class="admin-wrap">
    <?php render_admin_sidebar('templates'); ?>
    <main class="admin-content">
        <?= render_flash() ?>

        <div class="page-header">
            <div>
                <h1 class="page-title">Prompt Templates</h1>
                <p class="page-sub">Reusable starting points for clients on the generate page</p>
            </div>
            <button onclick="toggleForm()" class="btn btn-primary" id="showFormBtn">+ Add Template</button>
        </div>

        <!-- Form -->
        <div id="tplForm" style="display:none">
            <div class="card mb-4">
                <div class="card-header">
                    <span class="card-title"><?= $editing ? 'Edit Template' : 'New Template' ?></span>
                </div>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id"     value="<?= (int)($editing['id'] ?? 0) ?>">

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                        <div class="form-group">
                            <label class="form-label">Template Name</label>
                            <input type="text" name="name" class="form-control"
                                   value="<?= e($editing['name'] ?? '') ?>"
                                   placeholder="e.g. Product Launch" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" class="form-control"
                                   value="<?= e($editing['category'] ?? '') ?>"
                                   placeholder="e.g. product, brand, event, promo"
                                   list="cat-list">
                            <datalist id="cat-list">
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= e($c) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Template Body</label>
                        <textarea name="template" class="form-control" rows="7" required
                                  placeholder="Write the prompt template here. Use {{placeholder}} for fields clients fill in.
Example: A cinematic product video for {{product_name}}. Show the product against {{background_description}}. Target audience: {{target_audience}}. Tone: {{tone}}."><?= e($editing['template'] ?? '') ?></textarea>
                        <div class="form-hint">
                            Use <code>{{variable_name}}</code> for client-editable fields.
                            Example: <code>{{product_name}}</code>, <code>{{target_audience}}</code>.
                        </div>
                    </div>

                    <div style="margin-bottom:16px">
                        <label style="cursor:pointer">
                            <input type="checkbox" name="is_active" value="1"
                                   <?= ($editing['is_active'] ?? 1) ? 'checked' : '' ?>>
                            Active (visible to clients)
                        </label>
                    </div>

                    <div style="display:flex;gap:10px">
                        <button type="submit" class="btn btn-primary">Save Template</button>
                        <a href="<?= BASE_URL ?>/admin/templates.php" class="btn btn-ghost">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($editing): ?>
            <script>document.getElementById('tplForm').style.display='block';document.getElementById('showFormBtn').style.display='none';</script>
        <?php endif; ?>

        <!-- Seed helpers -->
        <?php if (empty($templates)): ?>
            <div class="alert alert--info">
                No templates yet. Add your first template above, or
                <form method="POST" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action"    value="save">
                    <input type="hidden" name="id"        value="0">
                    <input type="hidden" name="name"      value="Product Launch">
                    <input type="hidden" name="category"  value="product">
                    <input type="hidden" name="is_active" value="1">
                    <input type="hidden" name="template"  value="A vibrant product launch video for {{product_name}}. Show the product against {{background_description}} with {{visual_style}} cinematography. Key selling points: {{key_benefits}}. Target audience: {{target_audience}}. Tone: {{tone}}.">
                    <button type="submit" class="btn btn-ghost btn-sm">seed a sample</button>
                </form>.
            </div>
        <?php else: ?>
            <div class="card">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Template Preview</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templates as $t): ?>
                                <tr>
                                    <td class="fw-bold"><?= e($t['name']) ?></td>
                                    <td>
                                        <?php if ($t['category']): ?>
                                            <span class="badge badge-muted"><?= e($t['category']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="max-width:320px">
                                        <span class="text-muted text-sm">
                                            <?= e(truncate($t['template'], 90)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($t['is_active']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-muted">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:6px">
                                            <a href="?edit=<?= (int)$t['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
                                            <form method="POST" style="display:inline"
                                                  onsubmit="return confirm('Delete this template?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id"     value="<?= (int)$t['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>
<script>
function toggleForm() {
    const f = document.getElementById('tplForm');
    const b = document.getElementById('showFormBtn');
    if (f.style.display === 'none') {
        f.style.display = 'block'; b.textContent = '✕ Cancel';
    } else {
        f.style.display = 'none';  b.textContent = '+ Add Template';
    }
}
</script>
</body>
</html>
