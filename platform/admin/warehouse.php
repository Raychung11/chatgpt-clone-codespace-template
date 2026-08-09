<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_warehouse') {
        $id   = (int)($_POST['id'] ?? 0);
        $data = [
            'name'     => htmlspecialchars(trim($_POST['name']     ?? '')),
            'code'     => htmlspecialchars(trim($_POST['code']     ?? '')),
            'address'  => htmlspecialchars(trim($_POST['address']  ?? '')),
            'city'     => htmlspecialchars(trim($_POST['city']     ?? '')),
            'country'  => htmlspecialchars(trim($_POST['country']  ?? '')),
            'manager'  => htmlspecialchars(trim($_POST['manager']  ?? '')),
            'phone'    => htmlspecialchars(trim($_POST['phone']    ?? '')),
            'capacity' => (int)($_POST['capacity'] ?? 0),
            'status'   => in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active',
            'notes'    => htmlspecialchars(trim($_POST['notes']    ?? '')),
        ];

        if ($id > 0) {
            DB::update('warehouses', $data, 'id = ?', [$id]);
        } else {
            DB::insert('warehouses', $data);
        }
        header('Location: warehouse.php?saved=1');
        exit;
    }

    if ($action === 'delete_warehouse') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            DB::query('DELETE FROM warehouses WHERE id = ?', [$id]);
        }
        header('Location: warehouse.php?saved=1');
        exit;
    }
}

// ── KPIs ──────────────────────────────────────────────────────────────────────
$kpiWarehouses = (int)(DB::fetch("SELECT COUNT(*) as n FROM warehouses WHERE status='active'")['n'] ?? 0);
$kpiSkus       = (int)(DB::fetch("SELECT COUNT(*) as n FROM inventory_items")['n'] ?? 0);
$kpiLowStock   = (int)(DB::fetch("SELECT COUNT(*) as n FROM inventory_items WHERE qty_on_hand <= reorder_level AND status='active'")['n'] ?? 0);
$kpiInvValue   = (float)(DB::fetch("SELECT SUM(qty_on_hand * unit_cost) as v FROM inventory_items")['v'] ?? 0);

// ── Warehouse list ────────────────────────────────────────────────────────────
$warehouses = DB::fetchAll(
    'SELECT w.*, COUNT(i.id) as item_count
     FROM warehouses w
     LEFT JOIN inventory_items i ON i.warehouse_id = w.id
     GROUP BY w.id
     ORDER BY w.name'
);

// ── Low stock alerts ──────────────────────────────────────────────────────────
$lowStockItems = DB::fetchAll(
    "SELECT i.*, w.name as warehouse_name
     FROM inventory_items i
     LEFT JOIN warehouses w ON w.id = i.warehouse_id
     WHERE i.qty_on_hand <= i.reorder_level AND i.status='active'
     ORDER BY (i.qty_on_hand / GREATEST(i.reorder_level,1)) ASC
     LIMIT 10"
);

// ── Recent stock movements ────────────────────────────────────────────────────
$recentMovements = DB::fetchAll(
    'SELECT m.*, i.name as item_name, i.sku
     FROM stock_movements m
     JOIN inventory_items i ON i.id = m.item_id
     ORDER BY m.created_at DESC
     LIMIT 15'
);

// ── Edit pre-fill ─────────────────────────────────────────────────────────────
$editRow = null;
if (!empty($_GET['edit'])) {
    $editId  = (int)$_GET['edit'];
    $editRow = DB::fetch('SELECT * FROM warehouses WHERE id = ?', [$editId]);
}

$pageTitle = 'Warehouse Management';
require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <!-- Page header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-white mb-1"><i class="bi bi-building me-2 text-primary"></i>Warehouse Management</h4>
            <div class="text-muted small">Manage warehouses, stock levels and movements</div>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#warehouseModal">
            <i class="bi bi-plus-lg me-1"></i>Add Warehouse
        </button>
    </div>

    <?php if (!empty($_GET['saved'])): ?>
    <div id="successToast" class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
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
                        <i class="bi bi-building fs-4 text-primary"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Active Warehouses</div>
                        <div class="fs-4 fw-bold text-white"><?= $kpiWarehouses ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="glass-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 p-2 bg-info bg-opacity-10">
                        <i class="bi bi-box-seam fs-4 text-info"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total SKUs</div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($kpiSkus) ?></div>
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
                        <div class="text-muted small">Low Stock Items</div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($kpiLowStock) ?></div>
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
    </div>

    <!-- Main row: Warehouse table + Low stock -->
    <div class="row g-4 mb-4">

        <!-- Warehouses table -->
        <div class="col-lg-8">
            <div class="glass-card p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="fw-semibold text-white mb-0"><i class="bi bi-building me-2"></i>Warehouses</h6>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#warehouseModal">
                        <i class="bi bi-plus-lg me-1"></i>Add Warehouse
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-muted small">
                                <th>Name / Code</th>
                                <th>Location</th>
                                <th>Manager</th>
                                <th>Items</th>
                                <th>Capacity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($warehouses)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No warehouses found. Add one to get started.</td></tr>
                        <?php else: foreach ($warehouses as $wh): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-white"><?= htmlspecialchars($wh['name']) ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($wh['code']) ?></div>
                                </td>
                                <td class="text-muted small">
                                    <?= htmlspecialchars($wh['city']) ?><?= $wh['city'] && $wh['country'] ? ', ' : '' ?><?= htmlspecialchars($wh['country']) ?>
                                </td>
                                <td class="text-muted small"><?= htmlspecialchars($wh['manager']) ?></td>
                                <td class="text-white"><?= number_format($wh['item_count']) ?></td>
                                <td class="text-muted small"><?= $wh['capacity'] ? number_format($wh['capacity']) : '—' ?></td>
                                <td>
                                    <?php if ($wh['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="warehouse.php?edit=<?= $wh['id'] ?>" class="btn btn-sm btn-outline-primary me-1">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this warehouse?')">
                                        <input type="hidden" name="action" value="delete_warehouse">
                                        <input type="hidden" name="id" value="<?= $wh['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Low stock alerts -->
        <div class="col-lg-4">
            <div class="glass-card p-4 h-100">
                <h6 class="fw-semibold text-white mb-3"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Low Stock Alerts</h6>
                <?php if (empty($lowStockItems)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>
                        All items are sufficiently stocked.
                    </div>
                <?php else: foreach ($lowStockItems as $ls):
                    $pct = $ls['reorder_level'] > 0
                        ? min(100, round($ls['qty_on_hand'] / $ls['reorder_level'] * 100))
                        : 100;
                    $barClass = $pct < 25 ? 'bg-danger' : ($pct < 75 ? 'bg-warning' : 'bg-success');
                ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div>
                                <div class="text-white small fw-semibold"><?= htmlspecialchars($ls['name']) ?></div>
                                <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($ls['sku']) ?> · <?= htmlspecialchars($ls['warehouse_name'] ?? '—') ?></div>
                            </div>
                            <div class="text-end">
                                <span class="small fw-semibold text-white"><?= $ls['qty_on_hand'] ?></span>
                                <span class="text-muted small"> / <?= $ls['reorder_level'] ?></span>
                            </div>
                        </div>
                        <div class="progress" style="height:5px;background:#1e1e2e">
                            <div class="progress-bar <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Stock Movements -->
    <div class="glass-card p-4">
        <h6 class="fw-semibold text-white mb-3"><i class="bi bi-arrow-left-right me-2"></i>Recent Stock Movements</h6>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                    <tr class="text-muted small">
                        <th>Date</th>
                        <th>Item / SKU</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Before → After</th>
                        <th>Reference</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recentMovements)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No stock movements recorded yet.</td></tr>
                <?php else: foreach ($recentMovements as $mv):
                    $typeMap = [
                        'inbound'    => ['success', 'Inbound'],
                        'outbound'   => ['danger',  'Outbound'],
                        'adjustment' => ['info',    'Adjustment'],
                        'transfer'   => ['warning', 'Transfer'],
                    ];
                    [$badgeColor, $badgeLabel] = $typeMap[$mv['type']] ?? ['secondary', ucfirst($mv['type'])];
                    $qtySign = $mv['qty'] >= 0 ? '+' : '';
                ?>
                    <tr>
                        <td class="text-muted small"><?= date('d M Y H:i', strtotime($mv['created_at'])) ?></td>
                        <td>
                            <div class="fw-semibold text-white small"><?= htmlspecialchars($mv['item_name']) ?></div>
                            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($mv['sku']) ?></div>
                        </td>
                        <td><span class="badge bg-<?= $badgeColor ?>"><?= $badgeLabel ?></span></td>
                        <td class="fw-semibold text-<?= $mv['qty'] >= 0 ? 'success' : 'danger' ?>"><?= $qtySign . $mv['qty'] ?></td>
                        <td class="text-muted small"><?= (int)$mv['qty_before'] ?> → <?= (int)$mv['qty_after'] ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($mv['reference'] ?? '—') ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($mv['reason'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /container-fluid -->

<!-- Add / Edit Warehouse Modal -->
<div class="modal fade" id="warehouseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background:#111118;border:1px solid rgba(255,255,255,.1)">
            <form method="POST">
                <input type="hidden" name="action" value="save_warehouse">
                <input type="hidden" name="id" id="wh_id" value="">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-white" id="warehouseModalTitle">Add Warehouse</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label text-muted small">Warehouse Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="wh_name" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="wh_code" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Address</label>
                            <input type="text" name="address" id="wh_address" class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">City</label>
                            <input type="text" name="city" id="wh_city" class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Country</label>
                            <input type="text" name="country" id="wh_country" class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Manager</label>
                            <input type="text" name="manager" id="wh_manager" class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Phone</label>
                            <input type="text" name="phone" id="wh_phone" class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Capacity (units)</label>
                            <input type="number" name="capacity" id="wh_capacity" min="0" class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Status</label>
                            <select name="status" id="wh_status" class="form-select bg-dark text-white border-secondary">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Notes</label>
                            <textarea name="notes" id="wh_notes" rows="3" class="form-control bg-dark text-white border-secondary"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Warehouse</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editRow): ?>
<script>
(function () {
    var row = <?= json_encode($editRow) ?>;
    document.getElementById('warehouseModalTitle').textContent = 'Edit Warehouse';
    document.getElementById('wh_id').value       = row.id;
    document.getElementById('wh_name').value     = row.name     || '';
    document.getElementById('wh_code').value     = row.code     || '';
    document.getElementById('wh_address').value  = row.address  || '';
    document.getElementById('wh_city').value     = row.city     || '';
    document.getElementById('wh_country').value  = row.country  || '';
    document.getElementById('wh_manager').value  = row.manager  || '';
    document.getElementById('wh_phone').value    = row.phone    || '';
    document.getElementById('wh_capacity').value = row.capacity || '';
    document.getElementById('wh_status').value   = row.status   || 'active';
    document.getElementById('wh_notes').value    = row.notes    || '';
    var modal = new bootstrap.Modal(document.getElementById('warehouseModal'));
    modal.show();
})();
</script>
<?php endif; ?>

<?php if (!empty($_GET['saved'])): ?>
<script>
setTimeout(function () {
    var el = document.getElementById('successToast');
    if (el) {
        var t = bootstrap.Toast.getOrCreateInstance(el.querySelector('.toast'));
        t.hide();
    }
}, 4000);
</script>
<?php endif; ?>

<?php require_once '../includes/admin-footer.php'; ?>
