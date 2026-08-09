<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireAdmin();

/* ── Actions ── */
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $icon = trim($_POST['icon'] ?? 'bi-grid');
        $desc = trim($_POST['description'] ?? '');
        $slug = preg_replace('/[^a-z0-9-]/', '-', strtolower($name));
        if ($name) {
            DB::insert('categories', [
                'name'        => htmlspecialchars($name),
                'slug'        => $slug,
                'icon'        => htmlspecialchars($icon),
                'description' => htmlspecialchars($desc),
            ]);
            $msg = 'Category added.';
        }
    }

    if ($action === 'edit') {
        $id   = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $icon = trim($_POST['icon'] ?? 'bi-grid');
        $desc = trim($_POST['description'] ?? '');
        if ($id && $name) {
            DB::update('categories', [
                'name'        => htmlspecialchars($name),
                'icon'        => htmlspecialchars($icon),
                'description' => htmlspecialchars($desc),
            ], 'id=?', [$id]);
            $msg = 'Category updated.';
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id) {
            DB::query('DELETE FROM categories WHERE id=?', [$id]);
            $msg = 'Category deleted.';
        }
    }
}

$categories = DB::fetchAll('SELECT c.*, COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON p.category_id=c.id GROUP BY c.id ORDER BY c.name');
$pageTitle = 'Categories';
require_once '../includes/admin-header.php';
?>
<div class="admin-content px-4 py-4">
<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0">Categories</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg me-1"></i>Add Category
    </button>
</div>

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="glass-card p-0 overflow-hidden">
<table class="table table-dark table-hover mb-0">
<thead><tr>
    <th>Icon</th><th>Name</th><th>Description</th><th>Products</th><th>Slug</th><th></th>
</tr></thead>
<tbody>
<?php foreach ($categories as $cat): ?>
<tr>
    <td><i class="bi <?= htmlspecialchars($cat['icon']) ?> fs-5 text-primary"></i></td>
    <td class="fw-semibold"><?= htmlspecialchars($cat['name']) ?></td>
    <td class="text-muted small"><?= htmlspecialchars($cat['description']) ?></td>
    <td><span class="badge bg-primary"><?= $cat['product_count'] ?></span></td>
    <td><code class="text-muted small"><?= htmlspecialchars($cat['slug']) ?></code></td>
    <td class="text-end">
        <button class="btn btn-sm btn-outline-secondary me-1"
            onclick="editCat(<?= $cat['id'] ?>,'<?= htmlspecialchars(addslashes($cat['name'])) ?>','<?= htmlspecialchars(addslashes($cat['icon'])) ?>','<?= htmlspecialchars(addslashes($cat['description'])) ?>')">
            <i class="bi bi-pencil"></i>
        </button>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this category?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (empty($categories)): ?>
<tr><td colspan="6" class="text-center text-muted py-4">No categories yet.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
<div class="modal-dialog"><div class="modal-content bg-dark border border-secondary">
<form method="post">
<input type="hidden" name="action" value="add">
<div class="modal-header border-secondary"><h5 class="modal-title">Add Category</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control bg-dark text-white border-secondary" required></div>
    <div class="mb-3"><label class="form-label">Bootstrap Icon class</label><input type="text" name="icon" class="form-control bg-dark text-white border-secondary" value="bi-grid" placeholder="e.g. bi-cpu"></div>
    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control bg-dark text-white border-secondary" rows="2"></textarea></div>
</div>
<div class="modal-footer border-secondary"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add</button></div>
</form>
</div></div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
<div class="modal-dialog"><div class="modal-content bg-dark border border-secondary">
<form method="post">
<input type="hidden" name="action" value="edit">
<input type="hidden" name="id" id="editId">
<div class="modal-header border-secondary"><h5 class="modal-title">Edit Category</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" name="name" id="editName" class="form-control bg-dark text-white border-secondary" required></div>
    <div class="mb-3"><label class="form-label">Bootstrap Icon class</label><input type="text" name="icon" id="editIcon" class="form-control bg-dark text-white border-secondary"></div>
    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" id="editDesc" class="form-control bg-dark text-white border-secondary" rows="2"></textarea></div>
</div>
<div class="modal-footer border-secondary"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
</form>
</div></div>
</div>

<script>
function editCat(id, name, icon, desc) {
    document.getElementById('editId').value   = id;
    document.getElementById('editName').value = name;
    document.getElementById('editIcon').value = icon;
    document.getElementById('editDesc').value = desc;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php require_once '../includes/admin-footer.php'; ?>
