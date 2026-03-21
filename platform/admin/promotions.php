<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

// ── POST Actions ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id             = (int)($_POST['id'] ?? 0);
        $name           = trim($_POST['name'] ?? '');
        $description    = trim($_POST['description'] ?? '');
        $type           = $_POST['type'] ?? 'percentage';
        $discount_value = (float)($_POST['discount_value'] ?? 0);
        $min_order_value= (float)($_POST['min_order_value'] ?? 0);
        $max_uses       = !empty($_POST['max_uses']) ? (int)$_POST['max_uses'] : null;
        $start_date     = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date       = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $status         = $_POST['status'] ?? 'active';

        $data = [
            'name'            => htmlspecialchars($name),
            'description'     => htmlspecialchars($description),
            'type'            => $type,
            'discount_value'  => $discount_value,
            'min_order_value' => $min_order_value,
            'max_uses'        => $max_uses,
            'start_date'      => $start_date,
            'end_date'        => $end_date,
            'status'          => $status,
        ];

        if ($action === 'edit' && $id > 0) {
            DB::update('promotions', $data, 'id = ?', [$id]);
        } else {
            DB::insert('promotions', $data);
        }
        header('Location: /admin/promotions.php?saved=1');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            DB::query('DELETE FROM promotions WHERE id = ?', [$id]);
        }
        header('Location: /admin/promotions.php?deleted=1');
        exit;
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$filterType   = $_GET['type']   ?? '';
$filterStatus = $_GET['status'] ?? '';

$where  = [];
$params = [];
if ($filterType !== '') {
    $where[]  = 'type = ?';
    $params[] = $filterType;
}
if ($filterStatus !== '') {
    $where[]  = 'status = ?';
    $params[] = $filterStatus;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$promotions = DB::fetchAll("SELECT * FROM promotions $whereSQL ORDER BY created_at DESC", $params);

// KPI data
$activeCount   = DB::fetch("SELECT COUNT(*) as n FROM promotions WHERE status = 'active'")['n'] ?? 0;
$totalUsed     = DB::fetch("SELECT SUM(used_count) as n FROM promotions")['n'] ?? 0;
$avgDiscount   = DB::fetch("SELECT AVG(discount_value) as n FROM promotions WHERE type IN ('percentage','fixed')")['n'] ?? 0;
$revenueImpact = DB::fetch("SELECT SUM(discount_value * used_count) as n FROM promotions WHERE type = 'fixed'")['n'] ?? 0;

$pageTitle = 'Promotions';
require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <!-- Page Title -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white fw-bold mb-0">Promotions</h4>
            <p class="text-muted small mb-0">Manage promotional campaigns and discounts</p>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#promoModal"
                onclick="openPromoModal(null)">
            <i class="bi bi-plus-circle me-1"></i>New Promotion
        </button>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>Promotion saved successfully. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-warning alert-dismissible fade show"><i class="bi bi-trash me-2"></i>Promotion deleted. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-primary bg-opacity-10 text-primary rounded-3 p-3">
                        <i class="bi bi-tag fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Active Promotions</div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($activeCount) ?></div>
                        <div class="text-primary small">Currently running</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-success bg-opacity-10 text-success rounded-3 p-3">
                        <i class="bi bi-gift fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Discounts Given</div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($totalUsed) ?></div>
                        <div class="text-success small">All time uses</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-info bg-opacity-10 text-info rounded-3 p-3">
                        <i class="bi bi-percent fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Avg Discount %</div>
                        <div class="fs-4 fw-bold text-white"><?= round($avgDiscount, 1) ?>%</div>
                        <div class="text-info small">Across all promotions</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-warning bg-opacity-10 text-warning rounded-3 p-3">
                        <i class="bi bi-currency-dollar fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Revenue Impact</div>
                        <div class="fs-4 fw-bold text-white"><?= CURRENCY_SYMBOL ?><?= number_format($revenueImpact, 2) ?></div>
                        <div class="text-warning small">Fixed discounts given</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="admin-card rounded-4 p-3 mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-4 col-md-3">
                <label class="form-label text-muted small mb-1">Type</label>
                <select name="type" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="">All Types</option>
                    <?php foreach (['percentage','fixed','bogo','free_shipping'] as $t): ?>
                    <option value="<?= $t ?>" <?= $filterType === $t ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$t)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-4 col-md-3">
                <label class="form-label text-muted small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="">All Statuses</option>
                    <?php foreach (['active','scheduled','expired','paused'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="/admin/promotions.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
            </div>
        </form>
    </div>

    <!-- Promotions Table -->
    <div class="admin-card rounded-4 p-4">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                    <tr class="text-muted small">
                        <th>Name</th>
                        <th>Type</th>
                        <th>Value</th>
                        <th>Min Order</th>
                        <th>Dates</th>
                        <th class="text-end">Usage</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($promotions)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No promotions found.</td></tr>
                    <?php else: ?>
                    <?php
                    $typeColors = ['percentage'=>'info','fixed'=>'success','bogo'=>'warning','free_shipping'=>'primary'];
                    $statusColors = ['active'=>'success','scheduled'=>'info','expired'=>'secondary','paused'=>'warning'];
                    foreach ($promotions as $p):
                        $typeBadge   = $typeColors[$p['type']] ?? 'secondary';
                        $statusBadge = $statusColors[$p['status']] ?? 'secondary';
                        $usageLimit  = $p['max_uses'] ? number_format($p['max_uses']) : '∞';
                        $valueDisplay = match($p['type']) {
                            'percentage'   => $p['discount_value'] . '%',
                            'fixed'        => CURRENCY_SYMBOL . number_format($p['discount_value'], 2),
                            'bogo'         => 'BOGO',
                            'free_shipping'=> 'Free',
                            default        => $p['discount_value'],
                        };
                    ?>
                    <tr>
                        <td>
                            <div class="text-white fw-semibold small"><?= htmlspecialchars($p['name']) ?></div>
                            <?php if (!empty($p['description'])): ?>
                            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars(mb_substr($p['description'],0,60)) ?><?= mb_strlen($p['description'])>60 ? '…':'' ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-<?= $typeBadge ?> bg-opacity-15 text-<?= $typeBadge ?> border border-<?= $typeBadge ?> border-opacity-25"><?= ucfirst(str_replace('_',' ',$p['type'])) ?></span></td>
                        <td class="text-white fw-semibold small"><?= htmlspecialchars($valueDisplay) ?></td>
                        <td class="text-muted small"><?= $p['min_order_value'] > 0 ? CURRENCY_SYMBOL . number_format($p['min_order_value'], 2) : '—' ?></td>
                        <td class="text-muted small">
                            <?= $p['start_date'] ? date('d M Y', strtotime($p['start_date'])) : '—' ?>
                            <?php if ($p['end_date']): ?><br><span style="font-size:10px">to <?= date('d M Y', strtotime($p['end_date'])) ?></span><?php endif; ?>
                        </td>
                        <td class="text-end">
                            <span class="text-white small fw-semibold"><?= number_format($p['used_count']) ?></span>
                            <span class="text-muted small"> / <?= $usageLimit ?></span>
                            <?php if ($p['max_uses']): ?>
                            <div class="progress mt-1" style="height:3px">
                                <div class="progress-bar bg-primary" style="width:<?= min(100, round(($p['used_count']/$p['max_uses'])*100)) ?>%"></div>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-<?= $statusBadge ?>"><?= ucfirst($p['status']) ?></span></td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                <button class="btn btn-outline-secondary btn-sm py-0 px-2"
                                        onclick="openPromoModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)"
                                        data-bs-toggle="modal" data-bs-target="#promoModal" title="Edit">
                                    <i class="bi bi-pencil" style="font-size:12px"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this promotion?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Delete">
                                        <i class="bi bi-trash" style="font-size:12px"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="promoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="promoModalLabel">New Promotion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="promoForm">
                <input type="hidden" name="action" id="promoAction" value="add">
                <input type="hidden" name="id" id="promoId" value="0">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label text-muted small">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="promoName" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Type <span class="text-danger">*</span></label>
                            <select name="type" id="promoType" class="form-select bg-dark text-white border-secondary" onchange="toggleDiscountValue(this.value)">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount ($)</option>
                                <option value="bogo">Buy One Get One</option>
                                <option value="free_shipping">Free Shipping</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Description</label>
                            <textarea name="description" id="promoDesc" rows="2" class="form-control bg-dark text-white border-secondary"></textarea>
                        </div>
                        <div class="col-md-4" id="discountValueGroup">
                            <label class="form-label text-muted small">Discount Value <span class="text-danger">*</span></label>
                            <input type="number" name="discount_value" id="promoValue" class="form-control bg-dark text-white border-secondary" step="0.01" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Min Order Value</label>
                            <input type="number" name="min_order_value" id="promoMinOrder" class="form-control bg-dark text-white border-secondary" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Max Uses (blank = unlimited)</label>
                            <input type="number" name="max_uses" id="promoMaxUses" class="form-control bg-dark text-white border-secondary" min="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Start Date</label>
                            <input type="date" name="start_date" id="promoStart" class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">End Date</label>
                            <input type="date" name="end_date" id="promoEnd" class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Status</label>
                            <select name="status" id="promoStatus" class="form-select bg-dark text-white border-secondary">
                                <option value="active">Active</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="paused">Paused</option>
                                <option value="expired">Expired</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Promotion</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleDiscountValue(type) {
    const group = document.getElementById('discountValueGroup');
    group.style.display = (type === 'bogo' || type === 'free_shipping') ? 'none' : '';
}

function openPromoModal(record) {
    document.getElementById('promoForm').reset();
    if (!record) {
        document.getElementById('promoModalLabel').textContent = 'New Promotion';
        document.getElementById('promoAction').value = 'add';
        document.getElementById('promoId').value = '0';
        toggleDiscountValue('percentage');
        return;
    }
    document.getElementById('promoModalLabel').textContent = 'Edit Promotion';
    document.getElementById('promoAction').value  = 'edit';
    document.getElementById('promoId').value       = record.id;
    document.getElementById('promoName').value     = record.name;
    document.getElementById('promoDesc').value     = record.description || '';
    document.getElementById('promoType').value     = record.type;
    document.getElementById('promoValue').value    = record.discount_value;
    document.getElementById('promoMinOrder').value = record.min_order_value;
    document.getElementById('promoMaxUses').value  = record.max_uses || '';
    document.getElementById('promoStart').value    = record.start_date || '';
    document.getElementById('promoEnd').value      = record.end_date || '';
    document.getElementById('promoStatus').value   = record.status;
    toggleDiscountValue(record.type);
}
</script>

<?php require_once '../includes/admin-footer.php'; ?>
