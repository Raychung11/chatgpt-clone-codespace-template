<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Save (insert / update) shipment + line items ──
    if ($action === 'save') {
        $shipmentId     = (int)($_POST['shipment_id'] ?? 0);
        $validStatuses  = ['pending','dispatched','in_transit','out_for_delivery','delivered','returned','cancelled'];
        $validDirs      = ['outbound','inbound'];

        $status         = in_array($_POST['status'] ?? '', $validStatuses) ? $_POST['status'] : 'pending';
        $direction      = in_array($_POST['direction'] ?? '', $validDirs) ? $_POST['direction'] : 'outbound';
        $carrier        = htmlspecialchars(trim($_POST['carrier'] ?? ''));
        $trackingNumber = htmlspecialchars(trim($_POST['tracking_number'] ?? ''));
        $originName     = htmlspecialchars(trim($_POST['origin_name'] ?? ''));
        $originAddress  = htmlspecialchars(trim($_POST['origin_address'] ?? ''));
        $destName       = htmlspecialchars(trim($_POST['dest_name'] ?? ''));
        $destAddress    = htmlspecialchars(trim($_POST['dest_address'] ?? ''));
        $destCity       = htmlspecialchars(trim($_POST['dest_city'] ?? ''));
        $destCountry    = htmlspecialchars(trim($_POST['dest_country'] ?? ''));
        $dispatchDate   = ($_POST['dispatch_date'] ?? '') ?: null;
        $estDelivery    = ($_POST['est_delivery'] ?? '') ?: null;
        $actualDelivery = ($_POST['actual_delivery'] ?? '') ?: null;
        $weight         = round((float)str_replace(',', '.', $_POST['weight'] ?? '0'), 3);
        $dimensions     = htmlspecialchars(trim($_POST['dimensions'] ?? ''));
        $shippingCost   = round((float)str_replace(',', '.', $_POST['shipping_cost'] ?? '0'), 2);
        $insuranceValue = round((float)str_replace(',', '.', $_POST['insurance_value'] ?? '0'), 2);
        $notes          = htmlspecialchars(trim($_POST['notes'] ?? ''));

        // Line items
        $rawItems = $_POST['items'] ?? [];
        $itemRows = [];
        foreach ($rawItems as $item) {
            $desc      = htmlspecialchars(trim($item['description'] ?? ''));
            $sku       = htmlspecialchars(trim($item['sku'] ?? ''));
            $qty       = round((float)str_replace(',', '.', $item['quantity'] ?? '0'), 4);
            $unitValue = round((float)str_replace(',', '.', $item['unit_value'] ?? '0'), 4);
            if ($desc === '' && $qty == 0) continue; // skip blank rows
            $itemRows[] = [
                'description' => $desc,
                'sku'         => $sku,
                'quantity'    => $qty,
                'unit_value'  => $unitValue,
                'total_value' => round($qty * $unitValue, 2),
            ];
        }

        $shipmentData = [
            'carrier'         => $carrier,
            'tracking_number' => $trackingNumber,
            'status'          => $status,
            'direction'       => $direction,
            'origin_name'     => $originName,
            'origin_address'  => $originAddress,
            'dest_name'       => $destName,
            'dest_address'    => $destAddress,
            'dest_city'       => $destCity,
            'dest_country'    => $destCountry,
            'dispatch_date'   => $dispatchDate,
            'est_delivery'    => $estDelivery,
            'actual_delivery' => $actualDelivery,
            'weight'          => $weight,
            'dimensions'      => $dimensions,
            'shipping_cost'   => $shippingCost,
            'insurance_value' => $insuranceValue,
            'notes'           => $notes,
        ];

        if ($shipmentId > 0) {
            DB::update('shipments', $shipmentData, 'id = ?', [$shipmentId]);
            DB::query('DELETE FROM shipment_items WHERE shipment_id = ?', [$shipmentId]);
        } else {
            $countRow        = DB::fetch('SELECT COUNT(*) as n FROM shipments');
            $shipmentNumber  = 'SHP-' . date('Ym') . '-' . str_pad((int)$countRow['n'] + 1, 4, '0', STR_PAD_LEFT);
            $shipmentData['shipment_number'] = $shipmentNumber;
            $shipmentData['created_by']      = $_SESSION['user_id'];
            $shipmentId = DB::insert('shipments', $shipmentData);
        }

        foreach ($itemRows as $row) {
            $row['shipment_id'] = $shipmentId;
            DB::insert('shipment_items', $row);
        }

        header('Location: shipments.php?saved=1');
        exit;
    }

    // ── Delete shipment ──
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            DB::query('DELETE FROM shipment_items WHERE shipment_id = ?', [$id]);
            DB::query('DELETE FROM shipments WHERE id = ?', [$id]);
        }
        header('Location: shipments.php?saved=1');
        exit;
    }

    // ── Advance status ──
    if ($action === 'advance_status') {
        $id          = (int)($_POST['id'] ?? 0);
        $nextStatus  = $_POST['next_status'] ?? '';
        $transitions = [
            'pending'          => 'dispatched',
            'dispatched'       => 'in_transit',
            'in_transit'       => 'out_for_delivery',
            'out_for_delivery' => 'delivered',
        ];
        $allowedNext = array_values($transitions);
        if ($id > 0 && in_array($nextStatus, $allowedNext, true)) {
            $current = DB::fetch('SELECT status FROM shipments WHERE id = ?', [$id]);
            if ($current && isset($transitions[$current['status']]) && $transitions[$current['status']] === $nextStatus) {
                $updateData = ['status' => $nextStatus];
                if ($nextStatus === 'delivered') {
                    $updateData['actual_delivery'] = date('Y-m-d');
                }
                DB::update('shipments', $updateData, 'id = ?', [$id]);
            }
        }
        header('Location: shipments.php?saved=1');
        exit;
    }
}

// ── KPIs ──────────────────────────────────────────────────────────────────────
$kpiTotal    = (int)(DB::fetch('SELECT COUNT(*) as n FROM shipments')['n'] ?? 0);
$kpiActive   = (int)(DB::fetch(
    "SELECT COUNT(*) as n FROM shipments WHERE status IN ('pending','dispatched','in_transit','out_for_delivery')"
)['n'] ?? 0);
$kpiDeliveredMonth = (int)(DB::fetch(
    "SELECT COUNT(*) as n FROM shipments WHERE status = 'delivered'
     AND MONTH(actual_delivery) = MONTH(CURDATE()) AND YEAR(actual_delivery) = YEAR(CURDATE())"
)['n'] ?? 0);
$kpiOverdue  = (int)(DB::fetch(
    "SELECT COUNT(*) as n FROM shipments WHERE est_delivery < CURDATE()
     AND status NOT IN ('delivered','cancelled','returned')"
)['n'] ?? 0);

// ── Filters ──────────────────────────────────────────────────────────────────
$filterStatus    = $_GET['status']    ?? '';
$filterDirection = $_GET['direction'] ?? '';
$filterCarrier   = trim($_GET['carrier'] ?? '');
$filterMonth     = (int)($_GET['month'] ?? 0);
$filterYear      = (int)($_GET['year']  ?? 0);

$where  = [];
$params = [];

if ($filterStatus !== '' && in_array($filterStatus, ['pending','dispatched','in_transit','out_for_delivery','delivered','returned','cancelled'])) {
    $where[]  = 's.status = ?';
    $params[] = $filterStatus;
}
if ($filterDirection !== '' && in_array($filterDirection, ['outbound','inbound'])) {
    $where[]  = 's.direction = ?';
    $params[] = $filterDirection;
}
if ($filterCarrier !== '') {
    $where[]  = 's.carrier LIKE ?';
    $params[] = '%' . $filterCarrier . '%';
}
if ($filterMonth > 0 && $filterMonth <= 12) {
    $where[]  = 'MONTH(s.created_at) = ?';
    $params[] = $filterMonth;
}
if ($filterYear > 0) {
    $where[]  = 'YEAR(s.created_at) = ?';
    $params[] = $filterYear;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$shipments = DB::fetchAll(
    "SELECT s.*, COUNT(si.id) as item_count
     FROM shipments s
     LEFT JOIN shipment_items si ON si.shipment_id = s.id
     $whereSql
     GROUP BY s.id
     ORDER BY s.created_at DESC",
    $params
);

// ── Edit pre-fill ─────────────────────────────────────────────────────────────
$editShipment = null;
$editItems    = [];
$editId       = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    $editShipment = DB::fetch('SELECT * FROM shipments WHERE id = ?', [$editId]);
    if ($editShipment) {
        $editItems = DB::fetchAll('SELECT * FROM shipment_items WHERE shipment_id = ?', [$editId]);
    }
}

$pageTitle = 'Shipments';
require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <!-- Page header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-white mb-1"><i class="bi bi-truck me-2 text-primary"></i>Shipments</h4>
            <p class="text-muted small mb-0">Track and manage all inbound and outbound shipments</p>
        </div>
    </div>

    <?php if (isset($_GET['saved'])): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
        <div class="toast align-items-center text-white bg-success border-0 show" role="alert" id="savedToast">
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-check-circle me-2"></i>Changes saved successfully.</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- KPI Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="glass-card p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-primary bg-opacity-15 text-primary rounded-3 p-2 fs-4"><i class="bi bi-truck"></i></div>
                    <div>
                        <div class="text-muted small">Total Shipments</div>
                        <div class="fw-bold fs-4 text-white"><?= $kpiTotal ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="glass-card p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-info bg-opacity-15 text-info rounded-3 p-2 fs-4"><i class="bi bi-arrow-repeat"></i></div>
                    <div>
                        <div class="text-muted small">Active</div>
                        <div class="fw-bold fs-4 text-white"><?= $kpiActive ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="glass-card p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-success bg-opacity-15 text-success rounded-3 p-2 fs-4"><i class="bi bi-check-circle"></i></div>
                    <div>
                        <div class="text-muted small">Delivered This Month</div>
                        <div class="fw-bold fs-4 text-white"><?= $kpiDeliveredMonth ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="glass-card p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-danger bg-opacity-15 text-danger rounded-3 p-2 fs-4"><i class="bi bi-exclamation-circle"></i></div>
                    <div>
                        <div class="text-muted small">Overdue</div>
                        <div class="fw-bold fs-4 text-danger"><?= $kpiOverdue ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter bar -->
    <div class="glass-card p-3 mb-4">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-sm-6 col-md-2">
                <label class="form-label text-muted small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="">All Statuses</option>
                    <?php foreach (['pending','dispatched','in_transit','out_for_delivery','delivered','returned','cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-md-2">
                <label class="form-label text-muted small mb-1">Direction</label>
                <select name="direction" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="">All Directions</option>
                    <option value="outbound" <?= $filterDirection === 'outbound' ? 'selected' : '' ?>>Outbound</option>
                    <option value="inbound"  <?= $filterDirection === 'inbound'  ? 'selected' : '' ?>>Inbound</option>
                </select>
            </div>
            <div class="col-sm-6 col-md-2">
                <label class="form-label text-muted small mb-1">Carrier</label>
                <input type="text" name="carrier" value="<?= htmlspecialchars($filterCarrier) ?>"
                       placeholder="Search carrier…" class="form-control form-control-sm bg-dark text-white border-secondary">
            </div>
            <div class="col-sm-6 col-md-2">
                <label class="form-label text-muted small mb-1">Month</label>
                <select name="month" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="">All Months</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $filterMonth === $m ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-sm-6 col-md-2">
                <label class="form-label text-muted small mb-1">Year</label>
                <select name="year" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="">All Years</option>
                    <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                    <option value="<?= $y ?>" <?= $filterYear === $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-sm-6 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="shipments.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
            </div>
        </form>
        <div class="mt-2 text-end">
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#shipmentModal" id="newShipmentBtn">
                <i class="bi bi-plus-lg me-1"></i>New Shipment
            </button>
        </div>
    </div>

    <!-- Shipments table -->
    <div class="glass-card p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                    <tr class="text-muted small" style="border-bottom:1px solid rgba(255,255,255,0.08)">
                        <th class="px-3 py-3">Shipment #</th>
                        <th class="py-3">Direction</th>
                        <th class="py-3">Carrier / Tracking</th>
                        <th class="py-3">Destination</th>
                        <th class="py-3">Dispatched / Est. Delivery</th>
                        <th class="py-3 text-center">Items</th>
                        <th class="py-3">Status</th>
                        <th class="py-3 text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($shipments)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-truck fs-2 d-block mb-2"></i>No shipments found.
                        </td>
                    </tr>
                <?php else: foreach ($shipments as $shp):
                    $isOverdue = ($shp['est_delivery'] && $shp['est_delivery'] < date('Y-m-d')
                                  && !in_array($shp['status'], ['delivered','cancelled','returned']));
                    $statusColors = [
                        'pending'          => 'secondary',
                        'dispatched'       => 'primary',
                        'in_transit'       => 'info',
                        'out_for_delivery' => 'warning',
                        'delivered'        => 'success',
                        'returned'         => 'danger',
                        'cancelled'        => 'dark',
                    ];
                    $statusColor = $statusColors[$shp['status']] ?? 'secondary';
                    $transitions = [
                        'pending'          => ['next' => 'dispatched',       'label' => 'Dispatch'],
                        'dispatched'       => ['next' => 'in_transit',       'label' => 'Mark In Transit'],
                        'in_transit'       => ['next' => 'out_for_delivery', 'label' => 'Out for Delivery'],
                        'out_for_delivery' => ['next' => 'delivered',        'label' => 'Mark Delivered'],
                    ];
                    $nextTransition = $transitions[$shp['status']] ?? null;
                ?>
                    <tr>
                        <td class="px-3">
                            <span class="text-primary fw-semibold" style="font-family:monospace"><?= htmlspecialchars($shp['shipment_number']) ?></span>
                        </td>
                        <td>
                            <?php if ($shp['direction'] === 'outbound'): ?>
                                <span class="badge bg-primary">Outbound</span>
                            <?php else: ?>
                                <span class="badge bg-info text-dark">Inbound</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold text-white"><?= htmlspecialchars($shp['carrier']) ?></div>
                            <?php if ($shp['tracking_number']): ?>
                            <small class="text-muted" style="font-family:monospace"><?= htmlspecialchars($shp['tracking_number']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="text-white"><?= htmlspecialchars($shp['dest_name']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($shp['dest_city']) ?><?= $shp['dest_city'] && $shp['dest_country'] ? ', ' : '' ?><?= htmlspecialchars($shp['dest_country']) ?></small>
                        </td>
                        <td>
                            <div class="small text-muted"><?= $shp['dispatch_date'] ? date('d M Y', strtotime($shp['dispatch_date'])) : '—' ?></div>
                            <div class="small <?= $isOverdue ? 'text-danger fw-semibold' : 'text-white' ?>">
                                <?= $shp['est_delivery'] ? date('d M Y', strtotime($shp['est_delivery'])) : '—' ?>
                                <?= $isOverdue ? ' <i class="bi bi-exclamation-triangle-fill"></i>' : '' ?>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-secondary rounded-pill"><?= (int)$shp['item_count'] ?></span>
                        </td>
                        <td>
                            <span class="badge bg-<?= $statusColor ?> <?= $statusColor === 'warning' ? 'text-dark' : '' ?>">
                                <?= ucwords(str_replace('_', ' ', $shp['status'])) ?>
                            </span>
                        </td>
                        <td class="text-end pe-3">
                            <div class="d-flex gap-1 justify-content-end flex-wrap">
                                <!-- Edit -->
                                <a href="shipments.php?edit=<?= $shp['id'] ?>" class="btn btn-outline-secondary btn-sm"
                                   title="Edit"><i class="bi bi-pencil"></i></a>

                                <!-- Advance status -->
                                <?php if ($nextTransition): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action"      value="advance_status">
                                    <input type="hidden" name="id"          value="<?= $shp['id'] ?>">
                                    <input type="hidden" name="next_status" value="<?= htmlspecialchars($nextTransition['next']) ?>">
                                    <button type="submit" class="btn btn-outline-primary btn-sm"
                                            title="<?= htmlspecialchars($nextTransition['label']) ?>">
                                        <i class="bi bi-arrow-right-circle me-1"></i><?= htmlspecialchars($nextTransition['label']) ?>
                                    </button>
                                </form>
                                <?php endif; ?>

                                <!-- Delete -->
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this shipment?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id"     value="<?= $shp['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Add / Edit Shipment Modal ─────────────────────────────────────────── -->
<div class="modal fade" id="shipmentModal" tabindex="-1" aria-labelledby="shipmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content bg-dark text-white border border-secondary border-opacity-25">
            <form method="post" id="shipmentForm">
                <input type="hidden" name="action"      value="save">
                <input type="hidden" name="shipment_id" id="fShipmentId" value="0">

                <div class="modal-header border-secondary border-opacity-25">
                    <h5 class="modal-title fw-bold" id="shipmentModalLabel"><i class="bi bi-truck me-2 text-primary"></i>New Shipment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <!-- Status + Direction row -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label text-muted small">Status</label>
                            <select name="status" id="fStatus" class="form-select bg-dark text-white border-secondary">
                                <?php foreach (['pending','dispatched','in_transit','out_for_delivery','delivered','returned','cancelled'] as $s): ?>
                                <option value="<?= $s ?>"><?= ucwords(str_replace('_', ' ', $s)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">Direction</label>
                            <select name="direction" id="fDirection" class="form-select bg-dark text-white border-secondary">
                                <option value="outbound">Outbound</option>
                                <option value="inbound">Inbound</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">Carrier</label>
                            <input type="text" name="carrier" id="fCarrier" class="form-control bg-dark text-white border-secondary" placeholder="e.g. FedEx, DHL">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">Tracking Number</label>
                            <input type="text" name="tracking_number" id="fTrackingNumber" class="form-control bg-dark text-white border-secondary" placeholder="Tracking #">
                        </div>
                    </div>

                    <!-- Origin -->
                    <div class="row g-3 mb-3">
                        <div class="col-12"><h6 class="text-muted text-uppercase small fw-semibold mb-0">Origin</h6><hr class="border-secondary mt-1"></div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Sender Name</label>
                            <input type="text" name="origin_name" id="fOriginName" class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label text-muted small">Sender Address</label>
                            <input type="text" name="origin_address" id="fOriginAddress" class="form-control bg-dark text-white border-secondary">
                        </div>
                    </div>

                    <!-- Destination -->
                    <div class="row g-3 mb-3">
                        <div class="col-12"><h6 class="text-muted text-uppercase small fw-semibold mb-0">Destination</h6><hr class="border-secondary mt-1"></div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Recipient Name</label>
                            <input type="text" name="dest_name" id="fDestName" class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Address</label>
                            <input type="text" name="dest_address" id="fDestAddress" class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-muted small">City</label>
                            <input type="text" name="dest_city" id="fDestCity" class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-muted small">Country</label>
                            <input type="text" name="dest_country" id="fDestCountry" class="form-control bg-dark text-white border-secondary">
                        </div>
                    </div>

                    <!-- Dates -->
                    <div class="row g-3 mb-3">
                        <div class="col-12"><h6 class="text-muted text-uppercase small fw-semibold mb-0">Dates</h6><hr class="border-secondary mt-1"></div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Dispatch Date</label>
                            <input type="date" name="dispatch_date" id="fDispatchDate" class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Est. Delivery</label>
                            <input type="date" name="est_delivery" id="fEstDelivery" class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Actual Delivery</label>
                            <input type="date" name="actual_delivery" id="fActualDelivery" class="form-control bg-dark text-white border-secondary">
                        </div>
                    </div>

                    <!-- Physical + Financial -->
                    <div class="row g-3 mb-3">
                        <div class="col-12"><h6 class="text-muted text-uppercase small fw-semibold mb-0">Physical &amp; Financial</h6><hr class="border-secondary mt-1"></div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">Weight (kg)</label>
                            <input type="number" step="0.001" min="0" name="weight" id="fWeight" class="form-control bg-dark text-white border-secondary" placeholder="0.000">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">Dimensions (L×W×H)</label>
                            <input type="text" name="dimensions" id="fDimensions" class="form-control bg-dark text-white border-secondary" placeholder="e.g. 30×20×15 cm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">Shipping Cost</label>
                            <input type="number" step="0.01" min="0" name="shipping_cost" id="fShippingCost" class="form-control bg-dark text-white border-secondary" placeholder="0.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small">Insurance Value</label>
                            <input type="number" step="0.01" min="0" name="insurance_value" id="fInsuranceValue" class="form-control bg-dark text-white border-secondary" placeholder="0.00">
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-4">
                        <label class="form-label text-muted small">Notes</label>
                        <textarea name="notes" id="fNotes" rows="2" class="form-control bg-dark text-white border-secondary"></textarea>
                    </div>

                    <!-- Line items -->
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="text-muted text-uppercase small fw-semibold mb-0">Line Items</h6>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="addItemRow">
                                <i class="bi bi-plus-lg me-1"></i>Add Row
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-dark table-sm align-middle mb-0" id="itemsTable">
                                <thead>
                                    <tr class="text-muted small">
                                        <th>Description</th>
                                        <th style="width:120px">SKU</th>
                                        <th style="width:90px">Qty</th>
                                        <th style="width:110px">Unit Value</th>
                                        <th style="width:110px">Total</th>
                                        <th style="width:40px"></th>
                                    </tr>
                                </thead>
                                <tbody id="itemsBody">
                                    <!-- rows injected by JS -->
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-end text-muted small fw-semibold pe-2">Grand Total</td>
                                        <td class="text-white fw-bold" id="grandTotal">0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div><!-- /modal-body -->

                <div class="modal-footer border-secondary border-opacity-25">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i>Save Shipment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/admin-footer.php'; ?>

<script>
(function () {
    'use strict';

    // ── Pre-filled data from PHP (edit mode) ──────────────────────────────────
    const editData = <?= $editShipment ? json_encode($editShipment) : 'null' ?>;
    const editItems = <?= !empty($editItems) ? json_encode($editItems) : '[]' ?>;

    // ── Line items management ─────────────────────────────────────────────────
    let rowIndex = 0;

    function makeRow(data) {
        const i   = rowIndex++;
        const tr  = document.createElement('tr');
        tr.dataset.row = i;
        tr.innerHTML = `
            <td><input type="text"   name="items[${i}][description]" value="${escHtml(data.description || '')}"
                       class="form-control form-control-sm bg-dark text-white border-secondary item-desc"></td>
            <td><input type="text"   name="items[${i}][sku]"         value="${escHtml(data.sku || '')}"
                       class="form-control form-control-sm bg-dark text-white border-secondary"></td>
            <td><input type="number" name="items[${i}][quantity]"    value="${parseFloat(data.quantity || 0)}"
                       step="0.0001" min="0" class="form-control form-control-sm bg-dark text-white border-secondary item-qty"></td>
            <td><input type="number" name="items[${i}][unit_value]"  value="${parseFloat(data.unit_value || 0)}"
                       step="0.0001" min="0" class="form-control form-control-sm bg-dark text-white border-secondary item-uv"></td>
            <td class="text-white item-total fw-semibold">${formatNum(parseFloat(data.quantity || 0) * parseFloat(data.unit_value || 0))}</td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-x-lg"></i></button></td>`;

        tr.querySelectorAll('.item-qty, .item-uv').forEach(el => el.addEventListener('input', () => recalcRow(tr)));
        tr.querySelector('.remove-row').addEventListener('click', () => { tr.remove(); reindexRows(); recalcGrand(); });
        return tr;
    }

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function formatNum(n) {
        return isNaN(n) ? '0.00' : n.toFixed(2);
    }

    function recalcRow(tr) {
        const qty = parseFloat(tr.querySelector('.item-qty').value) || 0;
        const uv  = parseFloat(tr.querySelector('.item-uv').value)  || 0;
        tr.querySelector('.item-total').textContent = formatNum(qty * uv);
        recalcGrand();
    }

    function recalcGrand() {
        let total = 0;
        document.querySelectorAll('#itemsBody .item-total').forEach(td => {
            total += parseFloat(td.textContent) || 0;
        });
        document.getElementById('grandTotal').textContent = formatNum(total);
    }

    function reindexRows() {
        document.querySelectorAll('#itemsBody tr').forEach((tr, idx) => {
            tr.dataset.row = idx;
            tr.querySelectorAll('input').forEach(inp => {
                inp.name = inp.name.replace(/items\[\d+\]/, `items[${idx}]`);
            });
        });
        rowIndex = document.querySelectorAll('#itemsBody tr').length;
    }

    document.getElementById('addItemRow').addEventListener('click', () => {
        document.getElementById('itemsBody').appendChild(makeRow({}));
    });

    // ── Modal open/close ──────────────────────────────────────────────────────
    const modal         = document.getElementById('shipmentModal');
    const modalInstance = bootstrap.Modal.getOrCreate(modal);
    const titleEl       = document.getElementById('shipmentModalLabel');

    function fillModal(d, items) {
        rowIndex = 0;
        document.getElementById('fShipmentId').value    = d.id          || '0';
        document.getElementById('fStatus').value        = d.status      || 'pending';
        document.getElementById('fDirection').value     = d.direction   || 'outbound';
        document.getElementById('fCarrier').value       = d.carrier     || '';
        document.getElementById('fTrackingNumber').value= d.tracking_number || '';
        document.getElementById('fOriginName').value    = d.origin_name  || '';
        document.getElementById('fOriginAddress').value = d.origin_address || '';
        document.getElementById('fDestName').value      = d.dest_name    || '';
        document.getElementById('fDestAddress').value   = d.dest_address || '';
        document.getElementById('fDestCity').value      = d.dest_city    || '';
        document.getElementById('fDestCountry').value   = d.dest_country || '';
        document.getElementById('fDispatchDate').value  = d.dispatch_date   || '';
        document.getElementById('fEstDelivery').value   = d.est_delivery    || '';
        document.getElementById('fActualDelivery').value= d.actual_delivery || '';
        document.getElementById('fWeight').value        = d.weight          || '';
        document.getElementById('fDimensions').value    = d.dimensions      || '';
        document.getElementById('fShippingCost').value  = d.shipping_cost   || '';
        document.getElementById('fInsuranceValue').value= d.insurance_value || '';
        document.getElementById('fNotes').value         = d.notes           || '';

        const tbody = document.getElementById('itemsBody');
        tbody.innerHTML = '';
        (items || []).forEach(it => tbody.appendChild(makeRow(it)));
        if (!items || items.length === 0) tbody.appendChild(makeRow({}));
        recalcGrand();
        titleEl.innerHTML = '<i class="bi bi-truck me-2 text-primary"></i>' + (d.id ? 'Edit Shipment' : 'New Shipment');
    }

    // "New Shipment" button resets form
    document.getElementById('newShipmentBtn').addEventListener('click', () => {
        fillModal({}, []);
    });

    // Auto-open for edit mode
    <?php if ($editShipment): ?>
    fillModal(editData, editItems);
    modalInstance.show();
    <?php else: ?>
    // Ensure a fresh empty row when opening modal via New Shipment button
    modal.addEventListener('show.bs.modal', function (e) {
        if (e.relatedTarget && e.relatedTarget.id === 'newShipmentBtn') return; // handled above
    });
    <?php endif; ?>

    // Auto-dismiss saved toast
    const savedToast = document.getElementById('savedToast');
    if (savedToast) {
        setTimeout(() => bootstrap.Toast.getOrCreate(savedToast).hide(), 4000);
    }

})();
</script>
