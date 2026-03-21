<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

// ── Helper: generate next PO number ──────────────────────────────────────────
function generatePoNumber(): string {
    $ym    = date('Ym');
    $count = (int)DB::fetch(
        "SELECT COUNT(*) AS n FROM purchase_orders WHERE po_number LIKE ?",
        ["PO-$ym-%"]
    )['n'];
    return 'PO-' . $ym . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

// ── Helper: recalculate PO totals from its items ──────────────────────────────
function recalcPoTotals(int $poId): void {
    $subtotal = (float)DB::fetch(
        'SELECT COALESCE(SUM(total), 0) AS s FROM purchase_order_items WHERE po_id = ?',
        [$poId]
    )['s'];

    $po  = DB::fetch('SELECT tax FROM purchase_orders WHERE id = ?', [$poId]);
    $tax = $po ? (float)$po['tax'] : 0.00;

    DB::update('purchase_orders', [
        'subtotal' => $subtotal,
        'total'    => $subtotal + $tax,
    ], 'id = ?', [$poId]);
}

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Save (insert / update) PO + line items ──
    if ($action === 'save') {
        $poId          = (int)($_POST['po_id'] ?? 0);
        $supplierId    = (int)($_POST['supplier_id'] ?? 0);
        $status        = in_array($_POST['status'] ?? '', ['draft','sent','confirmed','received','cancelled'])
                            ? $_POST['status'] : 'draft';
        $orderDate     = $_POST['order_date']    ?: null;
        $expectedDate  = $_POST['expected_date'] ?: null;
        $receivedDate  = ($_POST['received_date'] ?? '') ?: null;
        $currency      = strtoupper(substr(preg_replace('/[^A-Z]/i', '', $_POST['currency'] ?? 'USD'), 0, 3)) ?: 'USD';
        $taxAmount     = round((float)str_replace(',', '.', $_POST['tax'] ?? '0'), 2);
        $notes         = htmlspecialchars(trim($_POST['notes'] ?? ''));

        // Line items
        $rawItems = $_POST['items'] ?? [];
        $itemRows = [];
        foreach ($rawItems as $item) {
            $desc  = htmlspecialchars(trim($item['description'] ?? ''));
            $qty   = round((float)str_replace(',', '.', $item['quantity']   ?? '0'), 4);
            $price = round((float)str_replace(',', '.', $item['unit_price'] ?? '0'), 4);
            if ($desc === '' && $qty == 0) continue; // skip blank rows
            $itemRows[] = [
                'description' => $desc,
                'quantity'    => $qty,
                'unit_price'  => $price,
                'total'       => round($qty * $price, 2),
            ];
        }

        $subtotal = array_sum(array_column($itemRows, 'total'));
        $total    = round($subtotal + $taxAmount, 2);

        if ($poId > 0) {
            // Update existing PO
            DB::update('purchase_orders', [
                'supplier_id'   => $supplierId,
                'status'        => $status,
                'order_date'    => $orderDate,
                'expected_date' => $expectedDate,
                'received_date' => $receivedDate,
                'currency'      => $currency,
                'tax'           => $taxAmount,
                'subtotal'      => $subtotal,
                'total'         => $total,
                'notes'         => $notes,
            ], 'id = ?', [$poId]);

            // Replace all items
            DB::query('DELETE FROM purchase_order_items WHERE po_id = ?', [$poId]);
        } else {
            // Insert new PO
            $poNumber = generatePoNumber();
            $poId     = DB::insert('purchase_orders', [
                'po_number'     => $poNumber,
                'supplier_id'   => $supplierId,
                'status'        => $status,
                'order_date'    => $orderDate,
                'expected_date' => $expectedDate,
                'received_date' => $receivedDate,
                'currency'      => $currency,
                'tax'           => $taxAmount,
                'subtotal'      => $subtotal,
                'total'         => $total,
                'notes'         => $notes,
                'created_by'    => Auth::id(),
            ]);
        }

        // Insert (re-)insert line items
        foreach ($itemRows as $row) {
            DB::insert('purchase_order_items', array_merge(['po_id' => $poId], $row));
        }

        header('Location: purchase-orders.php?saved=1');
        exit;
    }

    // ── Delete PO (cascade deletes items via FK) ──
    if ($action === 'delete') {
        $poId = (int)($_POST['po_id'] ?? 0);
        if ($poId > 0) {
            DB::query('DELETE FROM purchase_order_items WHERE po_id = ?', [$poId]);
            DB::query('DELETE FROM purchase_orders WHERE id = ?', [$poId]);
        }
        header('Location: purchase-orders.php?deleted=1');
        exit;
    }

    // ── Advance status ──
    if ($action === 'advance_status') {
        $poId       = (int)($_POST['po_id'] ?? 0);
        $nextStatus = $_POST['next_status'] ?? '';
        $allowed    = ['sent', 'confirmed', 'received'];
        if ($poId > 0 && in_array($nextStatus, $allowed)) {
            $updateData = ['status' => $nextStatus];
            if ($nextStatus === 'received') {
                $updateData['received_date'] = date('Y-m-d');
            }
            DB::update('purchase_orders', $updateData, 'id = ?', [$poId]);
        }
        header('Location: purchase-orders.php?advanced=1');
        exit;
    }
}

// ── Filters ──────────────────────────────────────────────────────────────────
$filterStatus   = $_GET['status']   ?? '';
$filterSupplier = (int)($_GET['supplier'] ?? 0);
$filterYear     = (int)($_GET['year']  ?? 0);
$filterMonth    = (int)($_GET['month'] ?? 0);

$whereClauses = [];
$params       = [];

if ($filterStatus !== '') {
    $whereClauses[] = 'po.status = ?';
    $params[]       = $filterStatus;
}
if ($filterSupplier > 0) {
    $whereClauses[] = 'po.supplier_id = ?';
    $params[]       = $filterSupplier;
}
if ($filterYear > 0) {
    $whereClauses[] = 'YEAR(po.order_date) = ?';
    $params[]       = $filterYear;
}
if ($filterMonth > 0) {
    $whereClauses[] = 'MONTH(po.order_date) = ?';
    $params[]       = $filterMonth;
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// ── KPIs ──────────────────────────────────────────────────────────────────────
$kpiTotal   = (int)DB::fetch('SELECT COUNT(*) AS n FROM purchase_orders')['n'];
$kpiDraft   = (int)DB::fetch("SELECT COUNT(*) AS n FROM purchase_orders WHERE status = 'draft'")['n'];
$kpiPending = (float)DB::fetch(
    "SELECT COALESCE(SUM(total), 0) AS v FROM purchase_orders WHERE status IN ('sent','confirmed')"
)['v'];
$thisYear   = date('Y');
$thisMonth  = date('n');
$kpiReceived = (float)DB::fetch(
    "SELECT COALESCE(SUM(total), 0) AS v FROM purchase_orders
     WHERE status = 'received'
       AND YEAR(received_date) = ?
       AND MONTH(received_date) = ?",
    [$thisYear, $thisMonth]
)['v'];

// ── Purchase order list ───────────────────────────────────────────────────────
$orders = DB::fetchAll(
    "SELECT po.*,
            s.name AS supplier_name,
            (SELECT COUNT(*) FROM purchase_order_items poi WHERE poi.po_id = po.id) AS item_count
     FROM purchase_orders po
     LEFT JOIN suppliers s ON s.id = po.supplier_id
     $whereSQL
     ORDER BY po.order_date DESC, po.id DESC",
    $params
);

// ── Supplier dropdown for filter + modal ─────────────────────────────────────
$allSuppliers = DB::fetchAll(
    "SELECT id, name FROM suppliers WHERE status != 'blacklisted' ORDER BY name ASC"
);

// ── Edit / View PO pre-fill ───────────────────────────────────────────────────
$editPo    = null;
$editItems = [];
if (isset($_GET['edit'])) {
    $editPo = DB::fetch(
        "SELECT po.*, s.name AS supplier_name
         FROM purchase_orders po
         LEFT JOIN suppliers s ON s.id = po.supplier_id
         WHERE po.id = ?",
        [(int)$_GET['edit']]
    );
    if ($editPo) {
        $editItems = DB::fetchAll(
            'SELECT * FROM purchase_order_items WHERE po_id = ? ORDER BY id ASC',
            [(int)$editPo['id']]
        );
    }
}

// Year options for filter
$yearOptions = DB::fetchAll(
    "SELECT DISTINCT YEAR(order_date) AS y FROM purchase_orders WHERE order_date IS NOT NULL ORDER BY y DESC"
);

$pageTitle = 'Purchase Orders';
require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        Purchase order saved successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-warning alert-dismissible fade show">
        Purchase order deleted.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['advanced'])): ?>
    <div class="alert alert-info alert-dismissible fade show">
        Purchase order status updated.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white fw-bold mb-0">
            Purchase Orders
            <span class="text-muted fs-6">(<?= number_format($kpiTotal) ?>)</span>
        </h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#poModal">
            <i class="bi bi-plus-lg me-1"></i> New PO
        </button>
    </div>

    <!-- KPI Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="admin-card rounded-4 p-3 text-center">
                <div class="text-muted small mb-1">Total POs</div>
                <div class="text-white fs-4 fw-bold"><?= number_format($kpiTotal) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="admin-card rounded-4 p-3 text-center">
                <div class="text-muted small mb-1">Draft</div>
                <div class="text-secondary fs-4 fw-bold"><?= number_format($kpiDraft) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="admin-card rounded-4 p-3 text-center">
                <div class="text-muted small mb-1">Pending Value</div>
                <div class="text-warning fs-4 fw-bold">
                    <?= CURRENCY_SYMBOL ?><?= number_format($kpiPending, 2) ?>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="admin-card rounded-4 p-3 text-center">
                <div class="text-muted small mb-1">Received This Month</div>
                <div class="text-success fs-4 fw-bold">
                    <?= CURRENCY_SYMBOL ?><?= number_format($kpiReceived, 2) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="row g-2 mb-4 align-items-end">
        <div class="col-12 col-sm-6 col-md-2">
            <label class="form-label text-muted small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm bg-dark border-secondary text-white">
                <option value="">All Statuses</option>
                <?php foreach (['draft','sent','confirmed','received','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>>
                    <?= ucfirst($s) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label text-muted small mb-1">Supplier</label>
            <select name="supplier" class="form-select form-select-sm bg-dark border-secondary text-white">
                <option value="0">All Suppliers</option>
                <?php foreach ($allSuppliers as $sup): ?>
                <option value="<?= (int)$sup['id'] ?>" <?= $filterSupplier === (int)$sup['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($sup['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label text-muted small mb-1">Year</label>
            <select name="year" class="form-select form-select-sm bg-dark border-secondary text-white">
                <option value="0">All Years</option>
                <?php foreach ($yearOptions as $yr): ?>
                <option value="<?= (int)$yr['y'] ?>" <?= $filterYear === (int)$yr['y'] ? 'selected' : '' ?>>
                    <?= (int)$yr['y'] ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label text-muted small mb-1">Month</label>
            <select name="month" class="form-select form-select-sm bg-dark border-secondary text-white">
                <option value="0">All Months</option>
                <?php
                $months = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',
                           7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'];
                foreach ($months as $num => $name):
                ?>
                <option value="<?= $num ?>" <?= $filterMonth === $num ? 'selected' : '' ?>>
                    <?= $name ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3 d-flex gap-2 align-items-end">
            <button class="btn btn-sm btn-primary">
                <i class="bi bi-search"></i> Filter
            </button>
            <a href="purchase-orders.php" class="btn btn-sm btn-outline-secondary">Clear</a>
        </div>
    </form>

    <!-- Purchase Orders Table -->
    <div class="admin-card rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead class="border-bottom border-secondary">
                    <tr class="text-muted small">
                        <th>PO Number</th>
                        <th>Supplier</th>
                        <th>Order Date</th>
                        <th>Expected</th>
                        <th class="text-center">Items</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-end">Tax</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $po): ?>
                    <?php
                    $statusBadge = match($po['status']) {
                        'draft'     => 'secondary',
                        'sent'      => 'primary',
                        'confirmed' => 'info',
                        'received'  => 'success',
                        'cancelled' => 'danger',
                        default     => 'secondary',
                    };
                    $nextStatus = match($po['status']) {
                        'draft'     => 'sent',
                        'sent'      => 'confirmed',
                        'confirmed' => 'received',
                        default     => null,
                    };
                    $nextLabel = match($po['status']) {
                        'draft'     => 'Send',
                        'sent'      => 'Confirm',
                        'confirmed' => 'Received',
                        default     => null,
                    };
                    $nextIcon = match($po['status']) {
                        'draft'     => 'bi-send',
                        'sent'      => 'bi-check-circle',
                        'confirmed' => 'bi-box-seam',
                        default     => null,
                    };
                    ?>
                    <tr>
                        <td>
                            <span class="text-white small fw-semibold font-monospace">
                                <?= htmlspecialchars($po['po_number']) ?>
                            </span>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars($po['supplier_name'] ?? '—') ?></td>
                        <td class="text-muted small">
                            <?= $po['order_date'] ? date('d M Y', strtotime($po['order_date'])) : '—' ?>
                        </td>
                        <td class="text-muted small">
                            <?php if ($po['expected_date']): ?>
                                <?php
                                $exp   = strtotime($po['expected_date']);
                                $isLate = $po['status'] !== 'received' && $exp < time();
                                ?>
                                <span class="<?= $isLate ? 'text-danger' : '' ?>">
                                    <?= date('d M Y', $exp) ?>
                                    <?= $isLate ? '<i class="bi bi-exclamation-triangle-fill ms-1" title="Overdue"></i>' : '' ?>
                                </span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="text-center text-muted small"><?= (int)$po['item_count'] ?></td>
                        <td class="text-end text-muted small">
                            <?= CURRENCY_SYMBOL ?><?= number_format((float)$po['subtotal'], 2) ?>
                        </td>
                        <td class="text-end text-muted small">
                            <?= CURRENCY_SYMBOL ?><?= number_format((float)$po['tax'], 2) ?>
                        </td>
                        <td class="text-end text-white small fw-semibold">
                            <?= CURRENCY_SYMBOL ?><?= number_format((float)$po['total'], 2) ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $statusBadge ?>">
                                <?= ucfirst(htmlspecialchars($po['status'])) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-1 flex-nowrap">
                                <!-- Status advance -->
                                <?php if ($nextStatus): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action"      value="advance_status">
                                    <input type="hidden" name="po_id"       value="<?= (int)$po['id'] ?>">
                                    <input type="hidden" name="next_status" value="<?= $nextStatus ?>">
                                    <button type="submit"
                                            class="btn btn-sm btn-outline-warning"
                                            title="Mark as <?= htmlspecialchars($nextLabel) ?>">
                                        <i class="bi <?= $nextIcon ?>"></i>
                                        <span class="d-none d-xl-inline ms-1"><?= htmlspecialchars($nextLabel) ?></span>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <!-- Edit -->
                                <a href="purchase-orders.php?edit=<?= (int)$po['id'] ?>"
                                   class="btn btn-sm btn-outline-primary" title="Edit PO">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <!-- Delete -->
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('Delete PO <?= htmlspecialchars(addslashes($po['po_number'])) ?>? This cannot be undone.')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="po_id"  value="<?= (int)$po['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete PO">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-5">
                            <i class="bi bi-file-earmark-text fs-3 d-block mb-2 opacity-50"></i>
                            No purchase orders found matching your filters.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /container-fluid -->

<!-- Add / Edit PO Modal -->
<div class="modal fade" id="poModal" tabindex="-1"
     aria-labelledby="poModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content bg-dark border-secondary">
            <form method="POST" id="poForm">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="po_id"  value="<?= $editPo ? (int)$editPo['id'] : 0 ?>">

                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-white" id="poModalLabel">
                        <i class="bi bi-file-earmark-plus me-2"></i>
                        <?php if ($editPo): ?>
                            Edit PO — <span class="font-monospace"><?= htmlspecialchars($editPo['po_number']) ?></span>
                        <?php else: ?>
                            New Purchase Order
                        <?php endif; ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white"
                            data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">

                    <!-- PO Header Fields -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label text-muted small">
                                Supplier <span class="text-danger">*</span>
                            </label>
                            <select name="supplier_id" required
                                    class="form-select bg-dark border-secondary text-white">
                                <option value="">— Select Supplier —</option>
                                <?php foreach ($allSuppliers as $sup): ?>
                                <option value="<?= (int)$sup['id'] ?>"
                                    <?= ($editPo && (int)$editPo['supplier_id'] === (int)$sup['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sup['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label text-muted small">Status</label>
                            <select name="status" class="form-select bg-dark border-secondary text-white">
                                <?php
                                $currentPoStatus = $editPo['status'] ?? 'draft';
                                foreach (['draft','sent','confirmed','received','cancelled'] as $s):
                                ?>
                                <option value="<?= $s ?>" <?= $currentPoStatus === $s ? 'selected' : '' ?>>
                                    <?= ucfirst($s) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label text-muted small">Currency</label>
                            <input type="text" name="currency" maxlength="3"
                                   value="<?= $editPo ? htmlspecialchars($editPo['currency']) : 'USD' ?>"
                                   class="form-control bg-dark border-secondary text-white text-uppercase"
                                   placeholder="USD">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label text-muted small">Order Date</label>
                            <input type="date" name="order_date"
                                   value="<?= $editPo ? htmlspecialchars($editPo['order_date'] ?? '') : date('Y-m-d') ?>"
                                   class="form-control bg-dark border-secondary text-white">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label text-muted small">Expected Date</label>
                            <input type="date" name="expected_date"
                                   value="<?= $editPo ? htmlspecialchars($editPo['expected_date'] ?? '') : '' ?>"
                                   class="form-control bg-dark border-secondary text-white">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label text-muted small">Received Date</label>
                            <input type="date" name="received_date"
                                   value="<?= $editPo ? htmlspecialchars($editPo['received_date'] ?? '') : '' ?>"
                                   class="form-control bg-dark border-secondary text-white">
                        </div>

                        <div class="col-12">
                            <label class="form-label text-muted small">Notes</label>
                            <textarea name="notes" rows="2"
                                      class="form-control bg-dark border-secondary text-white"
                                      placeholder="Internal notes…"><?= $editPo ? htmlspecialchars($editPo['notes']) : '' ?></textarea>
                        </div>
                    </div>

                    <hr class="border-secondary">

                    <!-- Line Items -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="text-white mb-0">
                            <i class="bi bi-list-ul me-1"></i> Line Items
                        </h6>
                        <button type="button" class="btn btn-sm btn-outline-success" id="addLineItem">
                            <i class="bi bi-plus-lg me-1"></i> Add Row
                        </button>
                    </div>

                    <div class="table-responsive mb-3">
                        <table class="table table-dark table-sm mb-0" id="lineItemsTable">
                            <thead class="border-bottom border-secondary">
                                <tr class="text-muted small">
                                    <th>Description</th>
                                    <th style="width:110px">Qty</th>
                                    <th style="width:140px">Unit Price</th>
                                    <th style="width:140px" class="text-end">Line Total</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="lineItemsBody">
                                <?php if (!empty($editItems)): ?>
                                    <?php foreach ($editItems as $idx => $item): ?>
                                    <tr class="line-item-row">
                                        <td>
                                            <input type="text"
                                                   name="items[<?= $idx ?>][description]"
                                                   value="<?= htmlspecialchars($item['description']) ?>"
                                                   class="form-control form-control-sm bg-dark border-secondary text-white item-desc"
                                                   placeholder="Item description" required>
                                        </td>
                                        <td>
                                            <input type="number" step="0.0001" min="0"
                                                   name="items[<?= $idx ?>][quantity]"
                                                   value="<?= htmlspecialchars($item['quantity']) ?>"
                                                   class="form-control form-control-sm bg-dark border-secondary text-white item-qty">
                                        </td>
                                        <td>
                                            <input type="number" step="0.0001" min="0"
                                                   name="items[<?= $idx ?>][unit_price]"
                                                   value="<?= htmlspecialchars($item['unit_price']) ?>"
                                                   class="form-control form-control-sm bg-dark border-secondary text-white item-price">
                                        </td>
                                        <td class="text-end">
                                            <input type="number" step="0.01" readonly
                                                   name="items[<?= $idx ?>][total]"
                                                   value="<?= htmlspecialchars($item['total']) ?>"
                                                   class="form-control form-control-sm bg-dark border-secondary text-white text-end item-line-total">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-row">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <!-- Blank starter row for new PO -->
                                    <tr class="line-item-row">
                                        <td>
                                            <input type="text" name="items[0][description]"
                                                   class="form-control form-control-sm bg-dark border-secondary text-white item-desc"
                                                   placeholder="Item description">
                                        </td>
                                        <td>
                                            <input type="number" step="0.0001" min="0" name="items[0][quantity]"
                                                   value="1"
                                                   class="form-control form-control-sm bg-dark border-secondary text-white item-qty">
                                        </td>
                                        <td>
                                            <input type="number" step="0.0001" min="0" name="items[0][unit_price]"
                                                   value="0"
                                                   class="form-control form-control-sm bg-dark border-secondary text-white item-price">
                                        </td>
                                        <td class="text-end">
                                            <input type="number" step="0.01" readonly name="items[0][total]"
                                                   value="0.00"
                                                   class="form-control form-control-sm bg-dark border-secondary text-white text-end item-line-total">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-row">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Totals Summary -->
                    <div class="row justify-content-end">
                        <div class="col-md-4">
                            <div class="admin-card rounded-3 p-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Subtotal</span>
                                    <span class="text-white small" id="summarySubtotal">
                                        <?= CURRENCY_SYMBOL ?><span id="subtotalVal">
                                            <?= $editPo ? number_format((float)$editPo['subtotal'], 2) : '0.00' ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="text-muted small mb-0" for="taxInput">Tax</label>
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="text-muted small"><?= CURRENCY_SYMBOL ?></span>
                                        <input type="number" step="0.01" min="0" name="tax" id="taxInput"
                                               value="<?= $editPo ? htmlspecialchars($editPo['tax']) : '0.00' ?>"
                                               class="form-control form-control-sm bg-dark border-secondary text-white text-end"
                                               style="width:100px">
                                    </div>
                                </div>
                                <hr class="border-secondary my-2">
                                <div class="d-flex justify-content-between">
                                    <span class="text-white fw-semibold small">Total</span>
                                    <span class="text-white fw-semibold" id="totalVal">
                                        <?= CURRENCY_SYMBOL ?><span id="grandTotalVal">
                                            <?= $editPo ? number_format((float)$editPo['total'], 2) : '0.00' ?>
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /modal-body -->

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>
                        <?= $editPo ? 'Save Changes' : 'Create PO' ?>
                    </button>
                </div>

            </form>
        </div>
    </div>
</div><!-- /poModal -->

<!-- ── JavaScript: Line Items + Auto-calc ─────────────────────────────────── -->
<script>
(function () {
    'use strict';

    var tbody      = document.getElementById('lineItemsBody');
    var taxInput   = document.getElementById('taxInput');
    var subtotalEl = document.getElementById('subtotalVal');
    var grandEl    = document.getElementById('grandTotalVal');

    // ── Recalculate totals ──────────────────────────────────────────────
    function calcTotals() {
        var subtotal = 0;
        tbody.querySelectorAll('.line-item-row').forEach(function (row) {
            var qty    = parseFloat(row.querySelector('.item-qty').value)   || 0;
            var price  = parseFloat(row.querySelector('.item-price').value) || 0;
            var line   = Math.round(qty * price * 100) / 100;
            row.querySelector('.item-line-total').value = line.toFixed(2);
            subtotal += line;
        });
        subtotal = Math.round(subtotal * 100) / 100;
        var tax   = Math.round((parseFloat(taxInput.value) || 0) * 100) / 100;
        var total = Math.round((subtotal + tax) * 100) / 100;
        subtotalEl.textContent = subtotal.toFixed(2);
        grandEl.textContent    = total.toFixed(2);
    }

    // ── Delegate qty / price changes ───────────────────────────────────
    tbody.addEventListener('input', function (e) {
        if (e.target.classList.contains('item-qty') ||
            e.target.classList.contains('item-price')) {
            calcTotals();
        }
    });

    taxInput.addEventListener('input', calcTotals);

    // ── Remove row ─────────────────────────────────────────────────────
    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.remove-row');
        if (!btn) return;
        // Keep at least one row
        if (tbody.querySelectorAll('.line-item-row').length > 1) {
            btn.closest('tr').remove();
            reIndex();
            calcTotals();
        } else {
            // Clear the row instead of deleting it
            var row = btn.closest('tr');
            row.querySelector('.item-desc').value        = '';
            row.querySelector('.item-qty').value         = '1';
            row.querySelector('.item-price').value       = '0';
            row.querySelector('.item-line-total').value  = '0.00';
            calcTotals();
        }
    });

    // ── Add row ────────────────────────────────────────────────────────
    document.getElementById('addLineItem').addEventListener('click', function () {
        var idx = tbody.querySelectorAll('.line-item-row').length;
        var tr  = document.createElement('tr');
        tr.className = 'line-item-row';
        tr.innerHTML =
            '<td><input type="text" name="items[' + idx + '][description]" ' +
                'class="form-control form-control-sm bg-dark border-secondary text-white item-desc" ' +
                'placeholder="Item description"></td>' +
            '<td><input type="number" step="0.0001" min="0" name="items[' + idx + '][quantity]" ' +
                'value="1" class="form-control form-control-sm bg-dark border-secondary text-white item-qty"></td>' +
            '<td><input type="number" step="0.0001" min="0" name="items[' + idx + '][unit_price]" ' +
                'value="0" class="form-control form-control-sm bg-dark border-secondary text-white item-price"></td>' +
            '<td class="text-end"><input type="number" step="0.01" readonly ' +
                'name="items[' + idx + '][total]" value="0.00" ' +
                'class="form-control form-control-sm bg-dark border-secondary text-white text-end item-line-total"></td>' +
            '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row">' +
                '<i class="bi bi-x-lg"></i></button></td>';
        tbody.appendChild(tr);
    });

    // ── Re-index names after row removal ──────────────────────────────
    function reIndex() {
        tbody.querySelectorAll('.line-item-row').forEach(function (row, i) {
            row.querySelector('.item-desc').name        = 'items[' + i + '][description]';
            row.querySelector('.item-qty').name         = 'items[' + i + '][quantity]';
            row.querySelector('.item-price').name       = 'items[' + i + '][unit_price]';
            row.querySelector('.item-line-total').name  = 'items[' + i + '][total]';
        });
    }

    // ── Initial calculation on page load ──────────────────────────────
    calcTotals();
})();
</script>

<?php if ($editPo): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('poModal');
    var modal   = new bootstrap.Modal(modalEl, { backdrop: 'static' });
    modal.show();
    modalEl.addEventListener('hidden.bs.modal', function () {
        var url = new URL(window.location.href);
        url.searchParams.delete('edit');
        window.history.replaceState({}, '', url.toString());
    });
});
</script>
<?php endif; ?>

<?php require_once '../includes/admin-footer.php'; ?>
