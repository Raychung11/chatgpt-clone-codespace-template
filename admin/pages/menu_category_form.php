<?php
/**
 * Admin – Menu Category Form (Create / Edit)
 * /admin/pages/menu_category_form.php
 *
 * Routes:
 *   GET  /admin/menu/category/create
 *   GET  /admin/menu/category/edit?id=X
 *   POST same URLs
 */

$id       = sanitize_int($_GET['id'] ?? 0);
$isEdit   = $id > 0;
$category = null;

if ($isEdit) {
    $category = Database::fetchOne('SELECT * FROM menu_categories WHERE id = ?', [$id]);
    if (!$category) {
        flash('error', 'Category not found.');
        header('Location: /admin/menu');
        exit;
    }
}

$outlets = Database::fetchAll("SELECT id, name FROM outlets WHERE status='active' ORDER BY name");
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $name      = sanitize_string($_POST['name']       ?? '');
        $outletId  = sanitize_int($_POST['outlet_id']     ?? 0) ?: null;
        $sortOrder = sanitize_int($_POST['sort_order']    ?? 0);
        $status    = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

        if (!$name) $errors[] = 'Category name is required.';

        if (empty($errors)) {
            if ($isEdit) {
                Database::execute(
                    'UPDATE menu_categories SET name=?, outlet_id=?, sort_order=?, status=? WHERE id=?',
                    [$name, $outletId, $sortOrder, $status, $id]
                );
                admin_log('update_category', 'menu', $id, "Category: {$name}");
                flash('success', 'Category updated.');
            } else {
                $newId = Database::insert(
                    'INSERT INTO menu_categories (name, outlet_id, sort_order, status) VALUES (?,?,?,?)',
                    [$name, $outletId, $sortOrder, $status]
                );
                admin_log('create_category', 'menu', $newId, "Category: {$name}");
                flash('success', 'Category created.');
            }
            header('Location: /admin/menu?_tab=categories');
            exit;
        }
    }
}

// For display – merge POST values on validation error
$v = $isEdit ? $category : [];
$v = array_merge($v, $isEdit ? [] : ['status'=>'active','sort_order'=>0]);

$pageTitle  = $isEdit ? 'Edit Category: ' . $category['name'] : 'New Category';
$activePage = 'menu';
$csrf       = Auth::generateCsrfToken();

require __DIR__ . '/../layout/header.php';
?>

<div class="mb-3">
    <a href="/admin/menu?_tab=categories" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Menu
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-tags me-2 text-warning"></i>
                    <?= $isEdit ? 'Edit Category' : 'New Category' ?>
                </h6>
            </div>
            <div class="card-body">

                <?php if ($errors): ?>
                <div class="alert alert-danger py-2 small">
                    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Category Name *</label>
                        <input type="text" name="name" class="form-control"
                               value="<?= htmlspecialchars($_POST['name'] ?? $v['name'] ?? '') ?>"
                               placeholder="e.g. Beverages, Main Course…" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Outlet
                            <span class="text-muted fw-normal">(leave blank = all outlets)</span>
                        </label>
                        <select name="outlet_id" class="form-select">
                            <option value="">All Outlets</option>
                            <?php foreach ($outlets as $o): ?>
                            <option value="<?= $o['id'] ?>"
                                <?= (($_POST['outlet_id'] ?? $v['outlet_id'] ?? '') == $o['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($o['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Sort Order</label>
                            <input type="number" name="sort_order" class="form-control" min="0" max="255"
                                   value="<?= (int)($_POST['sort_order'] ?? $v['sort_order'] ?? 0) ?>">
                            <div class="form-text">Lower numbers appear first.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Status</label>
                            <select name="status" class="form-select">
                                <option value="active"   <?= (($_POST['status'] ?? $v['status'] ?? 'active')==='active')  ?'selected':'' ?>>Active</option>
                                <option value="inactive" <?= (($_POST['status'] ?? $v['status'] ?? '')==='inactive')?'selected':'' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Save Changes' : 'Create Category' ?>
                        </button>
                        <a href="/admin/menu?_tab=categories" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
