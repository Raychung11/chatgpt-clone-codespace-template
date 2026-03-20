<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();
$pageTitle = 'Manage Products';

// Handle actions
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'category_id'    => (int)$_POST['category_id'],
            'name'           => htmlspecialchars(trim($_POST['name'])),
            'slug'           => preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', trim($_POST['slug'])))),
            'tagline'        => htmlspecialchars(trim($_POST['tagline'])),
            'description'    => htmlspecialchars(trim($_POST['description'])),
            'price_monthly'  => (float)$_POST['price_monthly'],
            'price_yearly'   => (float)($_POST['price_yearly'] ?? 0),
            'pricing_model'  => $_POST['pricing_model'],
            'badge'          => htmlspecialchars(trim($_POST['badge'] ?? '')),
            'is_featured'    => isset($_POST['is_featured']) ? 1 : 0,
            'is_active'      => isset($_POST['is_active']) ? 1 : 0,
            'demo_url'       => htmlspecialchars(trim($_POST['demo_url'] ?? '')),
            'features'       => json_encode(array_filter(array_map('trim', explode("\n", $_POST['features_list'] ?? '')))),
            'use_cases'      => json_encode(array_filter(array_map('trim', explode("\n", $_POST['use_cases_list'] ?? '')))),
        ];
        if ($id > 0) {
            DB::update('products', $data, 'id=?', [$id]);
            $msg = '<div class="alert alert-success py-2">Product updated successfully.</div>';
        } else {
            DB::insert('products', $data);
            $msg = '<div class="alert alert-success py-2">Product created successfully.</div>';
        }
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $current = DB::fetch('SELECT is_active FROM products WHERE id=?', [$id]);
        if ($current) {
            DB::update('products', ['is_active' => $current['is_active'] ? 0 : 1], 'id=?', [$id]);
        }
        header('Location: /admin/products.php');
        exit;
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        DB::query('DELETE FROM products WHERE id=?', [$id]);
        header('Location: /admin/products.php');
        exit;
    }
}

// Fetch products
$search = trim($_GET['q'] ?? '');
$products = DB::fetchAll(
    'SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id
     WHERE (? = "" OR p.name LIKE ? OR p.tagline LIKE ?)
     ORDER BY p.sort_order, p.created_at DESC',
    [$search, "%$search%", "%$search%"]
);
$categories = DB::fetchAll('SELECT * FROM categories ORDER BY sort_order');

// Edit mode
$editProduct = null;
if (isset($_GET['edit'])) {
    $editProduct = DB::fetch('SELECT * FROM products WHERE id=?', [(int)$_GET['edit']]);
}

require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white fw-bold mb-0">Products <span class="text-muted fs-6">(<?= count($products) ?>)</span></h4>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#productModal">
            <i class="bi bi-plus-circle me-1"></i>Add Product
        </button>
    </div>

    <?= $msg ?>

    <!-- Search -->
    <form method="GET" class="mb-4">
        <div class="input-group" style="max-width:400px">
            <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control bg-dark border-secondary text-white" placeholder="Search products...">
            <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
        </div>
    </form>

    <!-- Products Table -->
    <div class="admin-card rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
                <thead class="border-bottom border-secondary">
                    <tr class="text-muted small">
                        <th style="width:50px">#</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Featured</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td class="text-muted small"><?= $p['id'] ?></td>
                        <td>
                            <div class="text-white small fw-semibold"><?= htmlspecialchars($p['name']) ?></div>
                            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($p['tagline']) ?></div>
                            <?php if ($p['badge']): ?>
                            <span class="badge badge-hot mt-1"><?= htmlspecialchars($p['badge']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars($p['cat_name'] ?? '—') ?></td>
                        <td>
                            <div class="text-white small"><?= CURRENCY_SYMBOL ?><?= number_format($p['price_monthly'], 0) ?>/mo</div>
                            <?php if ($p['price_yearly']): ?>
                            <div class="text-muted" style="font-size:11px"><?= CURRENCY_SYMBOL ?><?= number_format($p['price_yearly'], 0) ?>/yr</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-sm <?= $p['is_active'] ? 'btn-success' : 'btn-secondary' ?>" style="font-size:11px">
                                    <?= $p['is_active'] ? 'Active' : 'Inactive' ?>
                                </button>
                            </form>
                        </td>
                        <td>
                            <?php if ($p['is_featured']): ?>
                            <i class="bi bi-star-fill text-warning"></i>
                            <?php else: ?>
                            <i class="bi bi-star text-muted"></i>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="?edit=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="/product.php?slug=<?= $p['slug'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Preview">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <form method="POST" onsubmit="return confirm('Delete this product?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($products)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No products found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Product Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white"><?= $editProduct ? 'Edit: '.htmlspecialchars($editProduct['name']) : 'Add New Product' ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $editProduct['id'] ?? '' ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label text-muted small">Product Name *</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($editProduct['name'] ?? '') ?>" class="form-control bg-dark border-secondary text-white" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Category</label>
                            <select name="category_id" class="form-select bg-dark border-secondary text-white">
                                <option value="">-- None --</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($editProduct['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label text-muted small">Tagline</label>
                            <input type="text" name="tagline" value="<?= htmlspecialchars($editProduct['tagline'] ?? '') ?>" class="form-control bg-dark border-secondary text-white">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">URL Slug</label>
                            <input type="text" name="slug" value="<?= htmlspecialchars($editProduct['slug'] ?? '') ?>" class="form-control bg-dark border-secondary text-white" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Description</label>
                            <textarea name="description" rows="3" class="form-control bg-dark border-secondary text-white"><?= htmlspecialchars($editProduct['description'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Monthly Price ($)</label>
                            <input type="number" name="price_monthly" step="0.01" value="<?= $editProduct['price_monthly'] ?? '' ?>" class="form-control bg-dark border-secondary text-white">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Yearly Price ($)</label>
                            <input type="number" name="price_yearly" step="0.01" value="<?= $editProduct['price_yearly'] ?? '' ?>" class="form-control bg-dark border-secondary text-white">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Pricing Model</label>
                            <select name="pricing_model" class="form-select bg-dark border-secondary text-white">
                                <?php foreach (['monthly','yearly','onetime','free'] as $pm): ?>
                                <option value="<?= $pm ?>" <?= ($editProduct['pricing_model'] ?? 'monthly') === $pm ? 'selected' : '' ?>><?= ucfirst($pm) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Features (one per line)</label>
                            <textarea name="features_list" rows="5" class="form-control bg-dark border-secondary text-white" placeholder="24/7 support&#10;CRM integration&#10;..."><?= htmlspecialchars(implode("\n", json_decode($editProduct['features'] ?? '[]', true))) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Use Cases (one per line)</label>
                            <textarea name="use_cases_list" rows="5" class="form-control bg-dark border-secondary text-white" placeholder="Retail&#10;E-commerce&#10;..."><?= htmlspecialchars(implode("\n", json_decode($editProduct['use_cases'] ?? '[]', true))) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Demo URL</label>
                            <input type="url" name="demo_url" value="<?= htmlspecialchars($editProduct['demo_url'] ?? '') ?>" class="form-control bg-dark border-secondary text-white">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">Badge Label</label>
                            <input type="text" name="badge" value="<?= htmlspecialchars($editProduct['badge'] ?? '') ?>" class="form-control bg-dark border-secondary text-white" placeholder="New, Hot, Popular">
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-4">
                            <div class="form-check">
                                <input type="checkbox" name="is_featured" class="form-check-input" id="isFeatured" <?= ($editProduct['is_featured'] ?? 0) ? 'checked' : '' ?>>
                                <label for="isFeatured" class="form-check-label text-muted small">Featured</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" id="isActive" <?= ($editProduct['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label for="isActive" class="form-check-label text-muted small">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Auto-open modal if in edit mode
if ($editProduct) {
    $extraScripts = '<script>new bootstrap.Modal(document.getElementById("productModal")).show();</script>';
}
require_once '../includes/admin-footer.php';
?>
