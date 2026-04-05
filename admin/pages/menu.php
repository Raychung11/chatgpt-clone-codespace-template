<?php
/**
 * Admin – Menu Management
 * /admin/pages/menu.php
 *
 * Lists all menu categories and items.
 * Supports inline availability toggle (AJAX) and delete actions.
 */

$pageTitle  = 'Menu Management';
$activePage = 'menu';

$errors  = [];
$success = false;

// --- Handle quick-toggle availability (POST from JS fetch) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_item'])) {
    if (!Auth::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    $itemId = sanitize_int($_POST['item_id'] ?? 0);
    $val    = (int)(bool)$_POST['value'];
    Database::execute('UPDATE menu_items SET is_available = ? WHERE id = ?', [$val, $itemId]);
    admin_log('toggle_menu_item', 'menu', $itemId, 'is_available = ' . $val);
    echo json_encode(['ok' => true, 'value' => $val]);
    exit;
}

// --- Handle delete category ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    if (!Auth::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        flash('error', 'Invalid request.');
    } else {
        $catId = sanitize_int($_POST['cat_id'] ?? 0);
        $count = (int) Database::fetchOne('SELECT COUNT(*) AS c FROM menu_items WHERE category_id = ?', [$catId])['c'];
        if ($count > 0) {
            flash('error', "Cannot delete category – it has {$count} item(s). Delete items first.");
        } else {
            Database::execute('DELETE FROM menu_categories WHERE id = ?', [$catId]);
            admin_log('delete_category', 'menu', $catId);
            flash('success', 'Category deleted.');
        }
    }
    header('Location: /admin/menu');
    exit;
}

// --- Handle delete item ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    if (!Auth::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        flash('error', 'Invalid request.');
    } else {
        $itemId = sanitize_int($_POST['item_id'] ?? 0);
        Database::execute('DELETE FROM menu_items WHERE id = ?', [$itemId]);
        admin_log('delete_item', 'menu', $itemId);
        flash('success', 'Item deleted.');
    }
    header('Location: /admin/menu');
    exit;
}

// --- Filters ---
$filterCat    = sanitize_int($_GET['cat'] ?? 0);
$filterStatus = sanitize_string($_GET['status'] ?? '');
$search       = sanitize_string($_GET['q'] ?? '');

// Pagination for items
$page    = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

// Fetch categories
$categories = Database::fetchAll(
    "SELECT mc.*, ot.name AS outlet_name,
            (SELECT COUNT(*) FROM menu_items mi WHERE mi.category_id = mc.id) AS item_count
     FROM menu_categories mc
     LEFT JOIN outlets ot ON ot.id = mc.outlet_id
     ORDER BY mc.sort_order ASC, mc.name ASC"
);

// Build items query
$where  = '1=1';
$params = [];

if ($filterCat) {
    $where   .= ' AND mi.category_id = ?';
    $params[] = $filterCat;
}
if ($filterStatus === 'available') {
    $where .= ' AND mi.is_available = 1';
} elseif ($filterStatus === 'unavailable') {
    $where .= ' AND mi.is_available = 0';
} elseif ($filterStatus === 'featured') {
    $where .= ' AND mi.is_featured = 1';
}
if ($search) {
    $where   .= ' AND mi.name LIKE ?';
    $params[] = '%' . $search . '%';
}

$totalItems = (int) Database::fetchOne(
    "SELECT COUNT(*) AS c FROM menu_items mi WHERE {$where}",
    $params
)['c'];
$pages = (int) ceil($totalItems / $perPage);

$items = Database::fetchAll(
    "SELECT mi.*, mc.name AS category_name
     FROM menu_items mi
     JOIN menu_categories mc ON mc.id = mi.category_id
     WHERE {$where}
     ORDER BY mc.sort_order ASC, mi.sort_order ASC, mi.name ASC
     LIMIT {$perPage} OFFSET {$offset}",
    array_merge($params, [$perPage, $offset])
);

$csrf = Auth::generateCsrfToken();

require __DIR__ . '/../layout/header.php';
?>

<!-- Stats row -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="text-muted small">Categories</div>
            <div class="fs-4 fw-bold text-primary"><?= count($categories) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="text-muted small">Total Items</div>
            <div class="fs-4 fw-bold"><?= number_format($totalItems ?: (int) Database::fetchOne('SELECT COUNT(*) AS c FROM menu_items')['c']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="text-muted small">Available</div>
            <div class="fs-4 fw-bold text-success"><?= number_format((int) Database::fetchOne('SELECT COUNT(*) AS c FROM menu_items WHERE is_available=1')['c']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="text-muted small">Featured</div>
            <div class="fs-4 fw-bold text-warning"><?= number_format((int) Database::fetchOne('SELECT COUNT(*) AS c FROM menu_items WHERE is_featured=1')['c']) ?></div>
        </div>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3" id="menu-tabs">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-items">
            <i class="bi bi-grid me-1"></i>Menu Items
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-categories">
            <i class="bi bi-tags me-1"></i>Categories
            <span class="badge bg-secondary ms-1"><?= count($categories) ?></span>
        </button>
    </li>
</ul>

<div class="tab-content">

    <!-- ======= ITEMS TAB ======= -->
    <div class="tab-pane fade show active" id="tab-items">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-card-list me-2 text-primary"></i>Menu Items</h6>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <!-- Search & filter -->
                    <form method="GET" class="d-flex gap-2 flex-wrap">
                        <input type="text" name="q" class="form-control form-control-sm" placeholder="Search…"
                               value="<?= htmlspecialchars($search) ?>" style="width:140px;">
                        <select name="cat" class="form-select form-select-sm" style="width:150px;">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $filterCat===$c['id']?'selected':'' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="status" class="form-select form-select-sm" style="width:130px;"
                                onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="available"   <?= $filterStatus==='available'  ?'selected':'' ?>>Available</option>
                            <option value="unavailable" <?= $filterStatus==='unavailable'?'selected':'' ?>>Unavailable</option>
                            <option value="featured"    <?= $filterStatus==='featured'   ?'selected':'' ?>>Featured</option>
                        </select>
                        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
                    </form>
                    <a href="/admin/menu/item/create" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-lg me-1"></i>Add Item
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th style="width:50px;">Img</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th class="text-end">Price</th>
                                <th class="text-center">Available</th>
                                <th class="text-center">Featured</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr id="row-<?= $item['id'] ?>">
                                <td>
                                    <?php if ($item['image_url']): ?>
                                    <img src="<?= htmlspecialchars($item['image_url']) ?>"
                                         class="rounded" width="38" height="38" style="object-fit:cover;">
                                    <?php else: ?>
                                    <div class="rounded bg-light d-flex align-items-center justify-content-center"
                                         style="width:38px;height:38px;font-size:1.2rem;">🍽️</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($item['name']) ?></div>
                                    <?php if ($item['description']): ?>
                                    <div class="text-muted" style="font-size:.73rem;max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                        <?= htmlspecialchars($item['description']) ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-light text-dark"><?= htmlspecialchars($item['category_name']) ?></span></td>
                                <td class="text-end fw-semibold"><?= format_currency((float)$item['price']) ?></td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-flex justify-content-center mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               <?= $item['is_available'] ? 'checked' : '' ?>
                                               onchange="toggleAvailability(<?= $item['id'] ?>, this.checked)"
                                               style="cursor:pointer;">
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if ($item['is_featured']): ?>
                                    <i class="bi bi-star-fill text-warning"></i>
                                    <?php else: ?>
                                    <i class="bi bi-star text-muted"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="/admin/menu/item/edit?id=<?= $item['id'] ?>"
                                       class="btn btn-xs btn-sm btn-outline-primary py-0 px-2 me-1">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-xs btn-sm btn-outline-danger py-0 px-2"
                                            onclick="confirmDelete('item', <?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <?= $search || $filterCat || $filterStatus ? 'No items match your filter.' : 'No menu items yet. <a href="/admin/menu/item/create">Add the first one</a>.' ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top small">
                    <span class="text-muted">Page <?= $page ?> of <?= $pages ?> (<?= number_format($totalItems) ?> items)</span>
                    <nav><ul class="pagination pagination-sm mb-0">
                        <?php for ($i = max(1,$page-2); $i <= min($pages,$page+2); $i++): ?>
                        <li class="page-item <?= $i===$page?'active':'' ?>">
                            <a class="page-link"
                               href="?<?= http_build_query(array_merge($_GET,['page'=>$i,'_tab'=>'items'])) ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul></nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ======= CATEGORIES TAB ======= -->
    <div class="tab-pane fade" id="tab-categories">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-tags me-2 text-warning"></i>Categories</h6>
                <a href="/admin/menu/category/create" class="btn btn-sm btn-warning">
                    <i class="bi bi-plus-lg me-1"></i>Add Category
                </a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Outlet</th>
                            <th class="text-center">Items</th>
                            <th class="text-center">Sort</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($cat['name']) ?></td>
                            <td class="text-muted"><?= $cat['outlet_name'] ? htmlspecialchars($cat['outlet_name']) : '<span class="badge bg-light text-dark">All Outlets</span>' ?></td>
                            <td class="text-center">
                                <a href="?cat=<?= $cat['id'] ?>" class="badge bg-primary text-decoration-none">
                                    <?= $cat['item_count'] ?>
                                </a>
                            </td>
                            <td class="text-center text-muted"><?= $cat['sort_order'] ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= $cat['status']==='active'?'success':'secondary' ?>">
                                    <?= ucfirst($cat['status']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="/admin/menu/category/edit?id=<?= $cat['id'] ?>"
                                   class="btn btn-xs btn-sm btn-outline-primary py-0 px-2 me-1">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button"
                                        class="btn btn-xs btn-sm btn-outline-danger py-0 px-2 <?= $cat['item_count']>0?'disabled':'' ?>"
                                        <?= $cat['item_count']==0 ? "onclick=\"confirmDelete('category', {$cat['id']}, '" . htmlspecialchars(addslashes($cat['name'])) . "')\"" : 'title="Delete items first"' ?>>
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No categories yet. <a href="/admin/menu/category/create">Add the first one</a>.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- /.tab-content -->

<!-- Hidden delete forms -->
<form method="POST" id="delete-category-form" style="display:none;">
    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
    <input type="hidden" name="delete_category" value="1">
    <input type="hidden" name="cat_id" id="delete-cat-id">
</form>
<form method="POST" id="delete-item-form" style="display:none;">
    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
    <input type="hidden" name="delete_item" value="1">
    <input type="hidden" name="item_id" id="delete-item-id">
</form>

<script>
// Availability toggle (AJAX)
async function toggleAvailability(itemId, checked) {
    const fd = new FormData();
    fd.append('toggle_item', '1');
    fd.append('item_id', itemId);
    fd.append('value', checked ? '1' : '0');
    fd.append('_csrf_token', '<?= $csrf ?>');

    const r = await fetch('/admin/menu', { method: 'POST', body: fd });
    if (!r.ok) {
        alert('Toggle failed – please refresh.');
    }
}

// Delete confirmation
function confirmDelete(type, id, name) {
    if (!confirm(`Delete "${name}"? This cannot be undone.`)) return;
    if (type === 'category') {
        document.getElementById('delete-cat-id').value  = id;
        document.getElementById('delete-category-form').submit();
    } else {
        document.getElementById('delete-item-id').value = id;
        document.getElementById('delete-item-form').submit();
    }
}

// Restore active tab after page reload
const tabParam = new URLSearchParams(location.search).get('_tab');
if (tabParam === 'categories') {
    const trigger = document.querySelector('[data-bs-target="#tab-categories"]');
    if (trigger) new bootstrap.Tab(trigger).show();
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
