<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();
$pageTitle = 'Inventory';

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Save item (insert or update) ──────────────────────────────────────────
    if ($action === 'save') {
        $id   = (int)($_POST['id'] ?? 0);
        $data = [
            'sku'          => htmlspecialchars(trim($_POST['sku']          ?? '')),
            'name'         => htmlspecialchars(trim($_POST['name']         ?? '')),
            'category'     => htmlspecialchars(trim($_POST['category']     ?? '')),
            'description'  => htmlspecialchars(trim($_POST['description']  ?? '')),
            'warehouse_id' => (int)($_POST['warehouse_id'] ?? 0) ?: null,
            'bin_location' => htmlspecialchars(trim($_POST['bin_location'] ?? '')),
            'qty_on_hand'  => (int)($_POST['qty_on_hand']  ?? 0),
            'reorder_level'=> (int)($_POST['reorder_level'] ?? 0),
            'reorder_qty'  => (int)($_POST['reorder_qty']  ?? 0),
            'unit_cost'    => (float)($_POST['unit_cost']  ?? 0),
            'unit_price'   => (float)($_POST['unit_price'] ?? 0),
            'supplier_id'  => (int)($_POST['supplier_id']  ?? 0) ?: null,
            'status'       => in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active',
            'notes'        => htmlspecialchars(trim($_POST['notes'] ?? '')),
        ];
        if ($id > 0) {
            DB::update('inventory_items', $data, 'id = ?', [$id]);
        } else {
            DB::insert('inventory_items', $data);
        }
        header('Location: inventory.php?saved=1');
        exit;
    }

    // ── Delete item ───────────────────────────────────────────────────────────
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            DB::query('DELETE FROM inventory_items WHERE id = ?', [$id]);
        }
        header('Location: inventory.php?saved=1');
        exit;
    }

    // ── Adjust stock ──────────────────────────────────────────────────────────
    if ($action === 'adjust_stock') {
        $id         = (int)($_POST['id'] ?? 0);
        $adjustment = (int)($_POST['adjustment'] ?? 0);
        $reference  = htmlspecialchars(trim($_POST['reference'] ?? ''));
        $reason     = htmlspecialchars(trim($_POST['reason']    ?? ''));

        if ($id > 0) {
            $item = DB::fetch('SELECT qty_on_hand FROM inventory_items WHERE id = ?', [$id]);
            if ($item) {
                $currentQty = (int)$item['qty_on_hand'];
                $newQty     = $currentQty + $adjustment;

                DB::insert('stock_movements', [
                    'item_id'    => $id,
                    'type'       => 'adjustment',
                    'qty'        => $adjustment,
                    'qty_before' => $currentQty,
                    'qty_after'  => $newQty,
                    'reference'  => $reference,
                    'reason'     => $reason,
                    'created_by' => $_SESSION['user_id'],
                ]);

                DB::update('inventory_items', ['qty_on_hand' => $newQty], 'id = ?', [$id]);
            }
        }
        header('Location: inventory.php?saved=1');
        exit;
    }
}

// ── KPIs ──────────────────────────────────────────────────────────────────────
$kpiTotalItems  = (int)(DB::fetch("SELECT COUNT(*) as n FROM inventory_items WHERE status='active'")['n'] ?? 0);
$kpiInvValue    = (float)(DB::fetch("SELECT COALESCE(SUM(qty_on_hand * unit_cost),0) as v FROM inventory_items WHERE status='active'")['v'] ?? 0);
$kpiLowStock    = (int)(DB::fetch("SELECT COUNT(*) as n FROM inventory_items WHERE qty_on_hand <= reorder_level AND status='active'")['n'] ?? 0);
$kpiOutOfStock  = (int)(DB::fetch("SELECT COUNT(*) as n FROM inventory_items WHERE qty_on_hand = 0 AND status='active'")['n'] ?? 0);

// ── Dropdown data ─────────────────────────────────────────────────────────────
$warehouseOptions = DB::fetchAll('SELECT id, name FROM warehouses ORDER BY name');
$supplierOptions  = DB::fetchAll('SELECT id, company_name FROM suppliers ORDER BY company_name');

// ── Build item list with optional filters ─────────────────────────────────────
$where  = ['1=1'];
$params = [];

if (!empty($_GET['warehouse_id'])) {
    $where[]  = 'i.warehouse_id = ?';
    $params[] = (int)$_GET['warehouse_id'];
}
if (!empty($_GET['status'])) {
    $where[]  = 'i.status = ?';
    $params[] = $_GET['status'];
}
if (!empty($_GET['search'])) {
    $where[]  = '(i.name LIKE ? OR i.sku LIKE ?)';
    $s = '%' . $_GET['search'] . '%';
    $params[] = $s;
    $params[] = $s;
}

$whereStr = implode(' AND ', $where);

$items = DB::fetchAll(
    "SELECT i.*, w.name as warehouse_name, s.company_name as supplier_name
     FROM inventory_items i
     LEFT JOIN warehouses w ON w.id = i.warehouse_id
     LEFT JOIN suppliers  s ON s.id = i.supplier_id
     WHERE $whereStr
     ORDER BY i.name",
    $params
);

// ── Edit / adjust pre-fill ────────────────────────────────────────────────────
$editRow   = null;
$adjustRow = null;

if (!empty($_GET['edit'])) {
    $editRow = DB::fetch('SELECT * FROM inventory_items WHERE id = ?', [(int)$_GET['edit']]);
}
if (!empty($_GET['adjust'])) {
    $adjustRow = DB::fetch('SELECT id, name, qty_on_hand FROM inventory_items WHERE id = ?', [(int)$_GET['adjust']]);
}

require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <!-- Page header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-white mb-1"><i class="bi bi-box-seam me-2 text-primary"></i>Inventory</h4>
            <div class="text-muted small">Track stock levels, costs and warehouse locations</div>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#itemModal">
            <i class="bi bi-plus-lg me-1"></i>Add Item
        </button>
    </div>

    <?php if (!empty($_GET['saved'])): ?>
    <div id="successToastWrap" class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
        <div class="toast align-items-center text-bg-success border-0 show" role="alert">
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-check-circle me-2"></i>Changes saved successfully.</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="glass-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 p-2 bg-primary bg-opacity-10">
                        <i class="bi bi-box-seam fs-4 text-primary"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total SKUs</div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($kpiTotalItems) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="glass-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 p-2 bg-success bg-opacity-10">
                        <i class="bi bi-currency-dollar fs-4 text-success"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Inventory Value</div>
                        <div class="fs-4 fw-bold text-white"><?= APP_CURRENCY . number_format($kpiInvValue, 2) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="glass-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 p-2 bg-warning bg-opacity-10">
                        <i class="bi bi-exclamation-triangle fs-4 text-warning"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Low Stock</div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($kpiLowStock) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="glass-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 p-2 bg-danger bg-opacity-10">
                        <i class="bi bi-slash-circle fs-4 text-danger"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Out of Stock</div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($kpiOutOfStock) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter bar -->
    <div class="glass-card p-3 mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-6 col-md-3">
                <label class="form-label text-muted small mb-1">Warehouse</label>
                <select name="warehouse_id" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="">All Warehouses</option>
                    <?php foreach ($warehouseOptions as $wOpt): ?>
                        <option value="<?= (int)$wOpt['id'] ?>" <?= (isset($_GET['warehouse_id']) && (int)$_GET['warehouse_id'] === (int)$wOpt['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($wOpt['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-md-2">
                <label class="form-label text-muted small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="">All Statuses</option>
                    <option value="active"   <?= (($_GET['status'] ?? '') === 'active')   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= (($_GET['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-sm-8 col-md-4">
                <label class="form-label text-muted small mb-1">Search</label>
                <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="Name or SKU…">
            </div>
            <div class="col-sm-4 col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary w-100"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="inventory.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>

    <!-- Items table -->
    <div class="glass-card p-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h6 class="fw-semibold text-white mb-0">
                <i class="bi bi-list-ul me-2"></i>Items
                <span class="badge bg-secondary ms-2"><?= count($items) ?></span>
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                    <tr class="text-muted small">
                        <th>SKU</th>
                        <th>Name / Category</th>
                        <th>Warehouse / Bin</th>
                        <th>Stock</th>
                        <th>Reorder Level</th>
                        <th>Unit Cost</th>
                        <th>Unit Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No items found. Add your first inventory item.</td></tr>
                <?php else: foreach ($items as $item):
                    $qty      = (int)$item['qty_on_hand'];
                    $rl       = (int)$item['reorder_level'];
                    $qtyClass = ($qty <= $rl) ? 'bg-danger' : ($qty <= $rl * 2 ? 'bg-warning text-dark' : 'bg-success');
                ?>
                    <tr>
                        <td class="text-muted small fw-semibold"><?= htmlspecialchars($item['sku']) ?></td>
                        <td>
                            <div class="fw-semibold text-white small"><?= htmlspecialchars($item['name']) ?></div>
                            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($item['category']) ?></div>
                        </td>
                        <td class="text-muted small">
                            <?= htmlspecialchars($item['warehouse_name'] ?? '—') ?>
                            <?php if ($item['bin_location']): ?>
                                <span class="text-muted"> · <?= htmlspecialchars($item['bin_location']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= $qtyClass ?>"><?= $qty ?></span></td>
                        <td class="text-muted small"><?= $rl ?></td>
                        <td class="text-muted small"><?= APP_CURRENCY . number_format((float)$item['unit_cost'], 2) ?></td>
                        <td class="text-muted small"><?= APP_CURRENCY . number_format((float)$item['unit_price'], 2) ?></td>
                        <td>
                            <?php if ($item['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="inventory.php?edit=<?= (int)$item['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="inventory.php?adjust=<?= (int)$item['id'] ?>" class="btn btn-sm btn-outline-info" title="Adjust Stock">
                                    <i class="bi bi-sliders"></i>
                                </a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this item?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /container-fluid -->

<!-- ── Add / Edit Item Modal ──────────────────────────────────────────────── -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content" style="background:#111118;border:1px solid rgba(255,255,255,.1)">
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="item_id" value="">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-white" id="itemModalTitle">Add Inventory Item</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label text-muted small">SKU <span class="text-danger">*</span></label>
                            <input type="text" name="sku" id="item_sku" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label text-muted small">Item Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="item_name" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Category</label>
                            <input type="text" name="category" id="item_category" class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Description</label>
                            <textarea name="description" id="item_description" rows="2" class="form-control bg-dark text-white border-secondary"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Warehouse</label>
                            <select name="warehouse_id" id="item_warehouse_id" class="form-select bg-dark text-white border-secondary">
                                <option value="">— Select Warehouse —</option>
                                <?php foreach ($warehouseOptions as $wOpt): ?>
                                    <option value="<?= (int)$wOpt['id'] ?>"><?= htmlspecialchars($wOpt['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Bin / Location</label>
                            <input type="text" name="bin_location" id="item_bin_location" class="form-control bg-dark text-white border-secondary" placeholder="e.g. A-12-3">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Qty on Hand</label>
                            <input type="number" name="qty_on_hand" id="item_qty_on_hand" min="0" class="form-control bg-dark text-white border-secondary" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Reorder Level</label>
                            <input type="number" name="reorder_level" id="item_reorder_level" min="0" class="form-control bg-dark text-white border-secondary" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Reorder Qty</label>
                            <input type="number" name="reorder_qty" id="item_reorder_qty" min="0" class="form-control bg-dark text-white border-secondary" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">Unit Cost</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark text-muted border-secondary"><?= APP_CURRENCY ?></span>
                                <input type="number" step="0.01" min="0" name="unit_cost" id="item_unit_cost" class="form-control bg-dark text-white border-secondary" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">Unit Price</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark text-muted border-secondary"><?= APP_CURRENCY ?></span>
                                <input type="number" step="0.01" min="0" name="unit_price" id="item_unit_price" class="form-control bg-dark text-white border-secondary" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Supplier</label>
                            <select name="supplier_id" id="item_supplier_id" class="form-select bg-dark text-white border-secondary">
                                <option value="">— Select Supplier —</option>
                                <?php foreach ($supplierOptions as $sOpt): ?>
                                    <option value="<?= (int)$sOpt['id'] ?>"><?= htmlspecialchars($sOpt['company_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-muted small">Status</label>
                            <select name="status" id="item_status" class="form-select bg-dark text-white border-secondary">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Notes</label>
                            <textarea name="notes" id="item_notes" rows="2" class="form-control bg-dark text-white border-secondary"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Adjust Stock Modal ─────────────────────────────────────────────────── -->
<div class="modal fade" id="adjustModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="background:#111118;border:1px solid rgba(255,255,255,.1)">
            <form method="POST">
                <input type="hidden" name="action" value="adjust_stock">
                <input type="hidden" name="id" id="adj_id" value="">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-white">Adjust Stock</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Item</label>
                        <input type="text" id="adj_item_name" class="form-control bg-dark text-white border-secondary" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Current Qty on Hand</label>
                        <input type="text" id="adj_current_qty" class="form-control bg-dark text-white border-secondary" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Adjustment <span class="text-muted">(positive = add, negative = remove)</span></label>
                        <input type="number" name="adjustment" id="adj_adjustment" class="form-control bg-dark text-white border-secondary" required placeholder="e.g. 10 or -5">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Reference</label>
                        <input type="text" name="reference" class="form-control bg-dark text-white border-secondary" placeholder="e.g. PO-2026-001">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Reason</label>
                        <input type="text" name="reason" class="form-control bg-dark text-white border-secondary" placeholder="e.g. Cycle count correction">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Apply Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editRow): ?>
<script>
(function () {
    var row = <?= json_encode($editRow) ?>;
    document.getElementById('itemModalTitle').textContent = 'Edit Inventory Item';
    document.getElementById('item_id').value           = row.id;
    document.getElementById('item_sku').value          = row.sku          || '';
    document.getElementById('item_name').value         = row.name         || '';
    document.getElementById('item_category').value     = row.category     || '';
    document.getElementById('item_description').value  = row.description  || '';
    document.getElementById('item_bin_location').value = row.bin_location || '';
    document.getElementById('item_qty_on_hand').value  = row.qty_on_hand  || 0;
    document.getElementById('item_reorder_level').value= row.reorder_level|| 0;
    document.getElementById('item_reorder_qty').value  = row.reorder_qty  || 0;
    document.getElementById('item_unit_cost').value    = row.unit_cost    || '0.00';
    document.getElementById('item_unit_price').value   = row.unit_price   || '0.00';
    document.getElementById('item_notes').value        = row.notes        || '';
    document.getElementById('item_status').value       = row.status       || 'active';

    var whSelect = document.getElementById('item_warehouse_id');
    if (row.warehouse_id) whSelect.value = row.warehouse_id;

    var supSelect = document.getElementById('item_supplier_id');
    if (row.supplier_id) supSelect.value = row.supplier_id;

    new bootstrap.Modal(document.getElementById('itemModal')).show();
})();
</script>
<?php endif; ?>

<?php if ($adjustRow): ?>
<script>
(function () {
    document.getElementById('adj_id').value           = <?= (int)$adjustRow['id'] ?>;
    document.getElementById('adj_item_name').value    = <?= json_encode($adjustRow['name']) ?>;
    document.getElementById('adj_current_qty').value  = <?= (int)$adjustRow['qty_on_hand'] ?>;
    new bootstrap.Modal(document.getElementById('adjustModal')).show();
})();
</script>
<?php endif; ?>

<?php if (!empty($_GET['saved'])): ?>
<script>
setTimeout(function () {
    var wrap = document.getElementById('successToastWrap');
    if (wrap) {
        var t = bootstrap.Toast.getOrCreateInstance(wrap.querySelector('.toast'));
        t.hide();
    }
}, 4000);
</script>
<?php endif; ?>

<?php require_once '../includes/admin-footer.php'; ?>
