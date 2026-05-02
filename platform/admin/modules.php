<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireAdmin();

$adminPage = 'modules';
$pageTitle = 'AI Module Manager';

$action  = $_POST['action'] ?? '';
$message = '';
$error   = '';

if ($action === 'add' || $action === 'edit') {
    $productId = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
    $data = [
        'name'        => trim($_POST['name']        ?? ''),
        'module_key'  => trim($_POST['module_key']  ?? ''),
        'slug'        => trim($_POST['slug']         ?? ''),
        'category'    => trim($_POST['category']     ?? 'General'),
        'description' => trim($_POST['description']  ?? ''),
        'icon'        => trim($_POST['icon']         ?? 'bi-cpu'),
        'color'       => trim($_POST['color']        ?? '#6366f1'),
        'tags'        => trim($_POST['tags']         ?? ''),
        'product_id'  => $productId,
        'is_active'   => isset($_POST['is_active']) ? 1 : 0,
        'sort_order'  => (int)($_POST['sort_order'] ?? 0),
    ];
    if (!$data['name'] || !$data['module_key'] || !$data['slug']) {
        $error = 'Name, Module Key, and Slug are required.';
    } elseif ($action === 'add') {
        DB::insert('ai_modules', $data);
        $message = 'Module "' . htmlspecialchars($data['name']) . '" added.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        DB::update('ai_modules', $data, 'id = ?', [$id]);
        $message = 'Module updated.';
    }
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    DB::execute('DELETE FROM ai_modules WHERE id = ?', [$id]);
    $message = 'Module deleted.';
}

$modules  = DB::fetchAll("SELECT m.*, p.name as product_name FROM ai_modules m LEFT JOIN products p ON m.product_id = p.id ORDER BY m.sort_order, m.category, m.name");
$products = DB::fetchAll("SELECT id, name FROM products WHERE is_active = 1 ORDER BY name");
$total    = count($modules);
$linked   = count(array_filter($modules, fn($m) => $m['product_id']));
$active   = count(array_filter($modules, fn($m) => $m['is_active']));

$categories = ['Communication', 'Sales & Operations', 'HR & People', 'Strategy & Finance', 'Automation & Systems'];

require_once '../includes/admin-header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="admin-stat-card">
            <div class="admin-stat-value"><?= $total ?></div>
            <div class="admin-stat-label">Total Modules</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="admin-stat-card">
            <div class="admin-stat-value text-success"><?= $active ?></div>
            <div class="admin-stat-label">Active</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="admin-stat-card">
            <div class="admin-stat-value text-primary"><?= $linked ?></div>
            <div class="admin-stat-label">Linked to Capsule</div>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="text-white fw-bold mb-1">AI Module Manager</h4>
        <p class="text-muted small mb-0">Register new AI tools here. Linking a module to a Capsule controls which subscribers can access it.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#moduleModal">
        <i class="bi bi-plus-lg me-2"></i>Register Module
    </button>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table admin-table">
            <thead>
                <tr>
                    <th style="width:36px">Ord</th>
                    <th>Module</th>
                    <th>Key</th>
                    <th>Category</th>
                    <th>Linked Capsule</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($modules as $m): ?>
                <tr>
                    <td class="text-muted small"><?= $m['sort_order'] ?: '—' ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:32px;height:32px;border-radius:8px;background:<?= htmlspecialchars($m['color']) ?>22;color:<?= htmlspecialchars($m['color']) ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <i class="bi <?= htmlspecialchars($m['icon']) ?>"></i>
                            </div>
                            <div>
                                <div class="text-white small fw-semibold"><?= htmlspecialchars($m['name']) ?></div>
                                <div class="text-muted" style="font-size:11px">/modules/<?= htmlspecialchars($m['slug']) ?>.php</div>
                            </div>
                        </div>
                    </td>
                    <td><code class="text-primary small"><?= htmlspecialchars($m['module_key']) ?></code></td>
                    <td><span class="badge bg-secondary bg-opacity-25 text-muted" style="font-size:11px"><?= htmlspecialchars($m['category']) ?></span></td>
                    <td class="small">
                        <?php if ($m['product_name']): ?>
                        <span class="text-white"><?= htmlspecialchars($m['product_name']) ?></span>
                        <?php else: ?>
                        <span class="text-muted"><i class="bi bi-dash"></i> Free / Unlinked</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $m['is_active'] ? 'bg-success' : 'bg-secondary' ?> bg-opacity-20 text-<?= $m['is_active'] ? 'success' : 'muted' ?>">
                            <?= $m['is_active'] ? 'Visible' : 'Hidden' ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <a href="/modules/<?= htmlspecialchars($m['slug']) ?>.php" target="_blank"
                           class="btn btn-sm btn-outline-secondary me-1" title="Open tool">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                        <button class="btn btn-sm btn-outline-primary me-1"
                                onclick='openEdit(<?= json_encode($m) ?>)' title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this module?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $m['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add / Edit Modal -->
<div class="modal fade" id="moduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="modalTitle">Register Module</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action"  id="fAction" value="add">
                <input type="hidden" name="id"      id="fId"     value="">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Module Name *</label>
                            <input type="text" class="form-control" name="name" id="fName" required placeholder="e.g. Email Writer">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Module Key *
                                <span class="text-muted" style="font-size:10px">— must match the <code>case</code> in api/ai-generate.php</span>
                            </label>
                            <input type="text" class="form-control font-monospace" name="module_key" id="fKey" required placeholder="e.g. email">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">File Slug *
                                <span class="text-muted" style="font-size:10px">— filename without .php</span>
                            </label>
                            <input type="text" class="form-control" name="slug" id="fSlug" required placeholder="e.g. email-writer">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Category</label>
                            <select class="form-select" name="category" id="fCategory">
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat ?>"><?= $cat ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Bootstrap Icon class</label>
                            <input type="text" class="form-control" name="icon" id="fIcon" value="bi-cpu" placeholder="e.g. bi-envelope-paper">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">Accent Colour</label>
                            <input type="color" class="form-control form-control-color w-100" name="color" id="fColor" value="#6366f1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">Sort Order</label>
                            <input type="number" class="form-control" name="sort_order" id="fOrder" value="0" min="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Description</label>
                            <textarea class="form-control" name="description" id="fDesc" rows="2"
                                      placeholder="Short description shown on the AI Tools page"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Filter Tags
                                <span class="text-muted" style="font-size:10px">comma-separated: writing, sales, finance, hr, marketing, legal, automation</span>
                            </label>
                            <input type="text" class="form-control" name="tags" id="fTags" placeholder="e.g. writing,communication">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Linked Capsule
                                <span class="text-muted" style="font-size:10px">— leave blank for free / unlinked</span>
                            </label>
                            <select class="form-select" name="product_id" id="fProduct">
                                <option value="">— Free / Unlinked —</option>
                                <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="fActive" checked>
                                <label class="form-check-label text-muted small" for="fActive">Show on AI Tools page</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Save Module</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEdit(m) {
    document.getElementById('modalTitle').textContent = 'Edit Module';
    document.getElementById('submitBtn').textContent  = 'Update Module';
    document.getElementById('fAction').value   = 'edit';
    document.getElementById('fId').value       = m.id;
    document.getElementById('fName').value     = m.name;
    document.getElementById('fKey').value      = m.module_key;
    document.getElementById('fSlug').value     = m.slug;
    document.getElementById('fCategory').value = m.category;
    document.getElementById('fIcon').value     = m.icon;
    document.getElementById('fColor').value    = m.color || '#6366f1';
    document.getElementById('fOrder').value    = m.sort_order;
    document.getElementById('fDesc').value     = m.description || '';
    document.getElementById('fTags').value     = m.tags || '';
    document.getElementById('fProduct').value  = m.product_id || '';
    document.getElementById('fActive').checked = m.is_active == 1;
    new bootstrap.Modal(document.getElementById('moduleModal')).show();
}
document.getElementById('moduleModal').addEventListener('hidden.bs.modal', () => {
    document.getElementById('modalTitle').textContent = 'Register Module';
    document.getElementById('submitBtn').textContent  = 'Save Module';
    document.getElementById('fAction').value = 'add';
    document.getElementById('fId').value     = '';
    document.getElementById('moduleModal').querySelector('form').reset();
    document.getElementById('fColor').value = '#6366f1';
});
</script>

<?php require_once '../includes/admin-footer.php'; ?>
