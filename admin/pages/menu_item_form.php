<?php
/**
 * Admin – Menu Item Form (Create / Edit)
 * /admin/pages/menu_item_form.php
 *
 * Routes:
 *   GET/POST /admin/menu/item/create
 *   GET/POST /admin/menu/item/edit?id=X
 */

$id     = sanitize_int($_GET['id'] ?? 0);
$isEdit = $id > 0;
$item   = null;

if ($isEdit) {
    $item = Database::fetchOne('SELECT * FROM menu_items WHERE id = ?', [$id]);
    if (!$item) {
        flash('error', 'Menu item not found.');
        header('Location: /admin/menu');
        exit;
    }
}

$categories = Database::fetchAll(
    "SELECT mc.id, mc.name, ot.name AS outlet_name
     FROM menu_categories mc
     LEFT JOIN outlets ot ON ot.id = mc.outlet_id
     WHERE mc.status = 'active'
     ORDER BY mc.sort_order ASC, mc.name ASC"
);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $name        = sanitize_string($_POST['name']        ?? '');
        $description = sanitize_string($_POST['description'] ?? '', 500);
        $categoryId  = sanitize_int($_POST['category_id']   ?? 0);
        $price       = (float) preg_replace('/[^0-9.]/', '', $_POST['price'] ?? '0');
        $imageUrl    = sanitize_string($_POST['image_url']   ?? '', 255);
        $sortOrder   = sanitize_int($_POST['sort_order']     ?? 0);
        $isAvailable = isset($_POST['is_available']) ? 1 : 0;
        $isFeatured  = isset($_POST['is_featured'])  ? 1 : 0;

        if (!$name)       $errors[] = 'Item name is required.';
        if (!$categoryId) $errors[] = 'Category is required.';
        if ($price < 0)   $errors[] = 'Price cannot be negative.';

        // Verify category exists
        if ($categoryId && !Database::fetchOne('SELECT id FROM menu_categories WHERE id = ?', [$categoryId])) {
            $errors[] = 'Selected category does not exist.';
        }

        if (empty($errors)) {
            if ($isEdit) {
                Database::execute(
                    'UPDATE menu_items
                     SET name=?, description=?, category_id=?, price=?, image_url=?,
                         sort_order=?, is_available=?, is_featured=?
                     WHERE id=?',
                    [$name, $description ?: null, $categoryId, $price,
                     $imageUrl ?: null, $sortOrder, $isAvailable, $isFeatured, $id]
                );
                admin_log('update_menu_item', 'menu', $id, "Item: {$name}");
                flash('success', 'Menu item updated.');
            } else {
                $newId = Database::insert(
                    'INSERT INTO menu_items
                     (name, description, category_id, price, image_url, sort_order, is_available, is_featured)
                     VALUES (?,?,?,?,?,?,?,?)',
                    [$name, $description ?: null, $categoryId, $price,
                     $imageUrl ?: null, $sortOrder, $isAvailable, $isFeatured]
                );
                admin_log('create_menu_item', 'menu', $newId, "Item: {$name}");
                flash('success', 'Menu item created.');
            }
            header('Location: /admin/menu');
            exit;
        }
    }
}

// Merge values for display
$v = array_merge(
    ['is_available' => 1, 'is_featured' => 0, 'sort_order' => 0, 'price' => '0.00'],
    $item ?: [],
    $_SERVER['REQUEST_METHOD'] === 'POST' ? array_intersect_key($_POST, array_flip([
        'name','description','category_id','price','image_url','sort_order'
    ])) : []
);

$pageTitle  = $isEdit ? 'Edit: ' . $item['name'] : 'New Menu Item';
$activePage = 'menu';
$csrf       = Auth::generateCsrfToken();

require __DIR__ . '/../layout/header.php';
?>

<div class="mb-3">
    <a href="/admin/menu" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Menu
    </a>
</div>

<div class="row g-3">

    <!-- Main Form -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-card-list me-2 text-primary"></i>
                    <?= $isEdit ? 'Edit Menu Item' : 'New Menu Item' ?>
                </h6>
            </div>
            <div class="card-body">

                <?php if ($errors): ?>
                <div class="alert alert-danger py-2 small">
                    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                </div>
                <?php endif; ?>

                <form method="POST" id="item-form">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">

                    <div class="row g-3">
                        <!-- Name -->
                        <div class="col-md-8">
                            <label class="form-label fw-semibold small">Item Name *</label>
                            <input type="text" name="name" class="form-control"
                                   value="<?= htmlspecialchars($v['name'] ?? '') ?>"
                                   placeholder="e.g. Nasi Lemak Special" required autofocus>
                        </div>

                        <!-- Category -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Category *</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">— Select —</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"
                                    <?= (($v['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                    <?= $cat['outlet_name'] ? '(' . htmlspecialchars($cat['outlet_name']) . ')' : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Description -->
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Description
                                <span class="text-muted fw-normal">(optional, max 500 chars)</span>
                            </label>
                            <textarea name="description" class="form-control" rows="3"
                                      maxlength="500"
                                      placeholder="Brief description shown to customers…"><?= htmlspecialchars($v['description'] ?? '') ?></textarea>
                        </div>

                        <!-- Price -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Price (MYR) *</label>
                            <div class="input-group">
                                <span class="input-group-text">RM</span>
                                <input type="number" name="price" class="form-control"
                                       value="<?= number_format((float)($v['price'] ?? 0), 2, '.', '') ?>"
                                       step="0.01" min="0" max="9999.99" required>
                            </div>
                        </div>

                        <!-- Sort Order -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Sort Order</label>
                            <input type="number" name="sort_order" class="form-control"
                                   value="<?= (int)($v['sort_order'] ?? 0) ?>"
                                   min="0" max="255">
                            <div class="form-text">Lower = shown first within category.</div>
                        </div>

                        <!-- Image URL -->
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Image URL
                                <span class="text-muted fw-normal">(optional)</span>
                            </label>
                            <input type="url" name="image_url" class="form-control" id="image-url-input"
                                   value="<?= htmlspecialchars($v['image_url'] ?? '') ?>"
                                   placeholder="https://…/image.jpg"
                                   oninput="previewImage(this.value)">
                            <div class="form-text">Paste an image URL – e.g. from your CDN or a public image host.</div>
                        </div>

                        <!-- Toggles -->
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_available"
                                       id="is-available" role="switch"
                                       <?= ($v['is_available'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold small" for="is-available">
                                    Available for ordering
                                </label>
                            </div>
                            <div class="form-text">Uncheck to hide this item temporarily.</div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_featured"
                                       id="is-featured" role="switch"
                                       <?= ($v['is_featured'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold small" for="is-featured">
                                    Featured item
                                </label>
                            </div>
                            <div class="form-text">Featured items appear prominently in the customer app.</div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Save Changes' : 'Create Item' ?>
                        </button>
                        <a href="/admin/menu" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sidebar: Image Preview + Tips -->
    <div class="col-lg-4">
        <!-- Image preview -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-semibold small"><i class="bi bi-image me-2"></i>Image Preview</h6>
            </div>
            <div class="card-body text-center">
                <div id="image-preview"
                     style="width:100%;aspect-ratio:4/3;background:#f4f6fb;border-radius:10px;
                            display:flex;align-items:center;justify-content:center;overflow:hidden;">
                    <?php if (!empty($v['image_url'])): ?>
                    <img id="preview-img" src="<?= htmlspecialchars($v['image_url']) ?>"
                         style="width:100%;height:100%;object-fit:cover;" onerror="imgError()">
                    <?php else: ?>
                    <span id="preview-placeholder" style="font-size:3rem;color:#ccc;">🍽️</span>
                    <img id="preview-img" src="" style="display:none;width:100%;height:100%;object-fit:cover;" onerror="imgError()">
                    <?php endif; ?>
                </div>
                <div class="small text-muted mt-2">Paste an image URL above to preview</div>
            </div>
        </div>

        <!-- Tips -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-semibold small"><i class="bi bi-lightbulb me-2 text-warning"></i>Tips</h6>
            </div>
            <div class="card-body small text-muted">
                <ul class="ps-3 mb-0">
                    <li class="mb-1">Keep names short and descriptive (max 150 chars).</li>
                    <li class="mb-1">Use sort order to control display sequence within a category.</li>
                    <li class="mb-1">Mark popular items as <strong>Featured</strong> to highlight them.</li>
                    <li>Uncheck <strong>Available</strong> to hide items without deleting them (e.g. seasonal items).</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
let previewTimer;
function previewImage(url) {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(() => {
        const img = document.getElementById('preview-img');
        const placeholder = document.getElementById('preview-placeholder');
        if (!url) {
            img.style.display = 'none';
            if (placeholder) placeholder.style.display = '';
            return;
        }
        img.src = url;
        img.style.display = 'block';
        if (placeholder) placeholder.style.display = 'none';
    }, 500);
}
function imgError() {
    const img = document.getElementById('preview-img');
    const placeholder = document.getElementById('preview-placeholder');
    img.style.display = 'none';
    if (placeholder) placeholder.style.display = '';
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
