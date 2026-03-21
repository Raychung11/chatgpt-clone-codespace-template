<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

// ── POST Actions ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $code        = strtoupper(trim($_POST['code'] ?? ''));
        $type        = $_POST['type'] ?? 'percentage';
        $value       = (float)($_POST['value'] ?? 0);
        $min_order   = (float)($_POST['min_order'] ?? 0);
        $usage_limit = (int)($_POST['usage_limit'] ?? 1);
        $expires_at  = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $status      = $_POST['status'] ?? 'active';

        $data = [
            'code'        => htmlspecialchars($code),
            'type'        => $type,
            'value'       => $value,
            'min_order'   => $min_order,
            'usage_limit' => $usage_limit,
            'expires_at'  => $expires_at,
            'customer_id' => $customer_id,
            'status'      => $status,
        ];

        if ($id > 0) {
            DB::update('vouchers', $data, 'id = ?', [$id]);
        } else {
            DB::insert('vouchers', $data);
        }
        header('Location: /admin/vouchers.php?saved=1');
        exit;
    }

    if ($action === 'bulk_generate') {
        $type        = $_POST['bulk_type'] ?? 'percentage';
        $value       = (float)($_POST['bulk_value'] ?? 10);
        $usage_limit = (int)($_POST['bulk_usage_limit'] ?? 1);
        $expires_at  = !empty($_POST['bulk_expires_at']) ? $_POST['bulk_expires_at'] : null;
        $generated   = 0;

        for ($i = 0; $i < 10; $i++) {
            $code = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
            // Skip if code already exists
            $exists = DB::fetch('SELECT id FROM vouchers WHERE code = ?', [$code]);
            if ($exists) { $i--; continue; }
            DB::insert('vouchers', [
                'code'        => $code,
                'type'        => $type,
                'value'       => $value,
                'min_order'   => 0,
                'usage_limit' => $usage_limit,
                'expires_at'  => $expires_at,
                'customer_id' => null,
                'status'      => 'active',
            ]);
            $generated++;
        }
        header('Location: /admin/vouchers.php?generated=' . $generated);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            DB::query('DELETE FROM vouchers WHERE id = ?', [$id]);
        }
        header('Location: /admin/vouchers.php?deleted=1');
        exit;
    }
}

// ── Filters ──────────────────────────────────────────────────────────────────
$filterType   = $_GET['type']   ?? '';
$filterStatus = $_GET['status'] ?? '';

$where  = [];
$params = [];
if ($filterType !== '') {
    $where[]  = 'v.type = ?';
    $params[] = $filterType;
}
if ($filterStatus !== '') {
    $where[]  = 'v.status = ?';
    $params[] = $filterStatus;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$vouchers = DB::fetchAll(
    "SELECT v.*, u.name as customer_name FROM vouchers v
     LEFT JOIN users u ON v.customer_id = u.id
     $whereSQL ORDER BY v.created_at DESC",
    $params
);

// ── KPIs ─────────────────────────────────────────────────────────────────────
$kpiTotal       = DB::fetch("SELECT COUNT(*) as n FROM vouchers")['n'] ?? 0;
$kpiActive      = DB::fetch("SELECT COUNT(*) as n FROM vouchers WHERE status = 'active'")['n'] ?? 0;
$kpiRedemptions = DB::fetch("SELECT SUM(used_count) as n FROM vouchers")['n'] ?? 0;
$kpiSavings     = DB::fetch("SELECT SUM(used_count * value) as n FROM vouchers WHERE type = 'fixed'")['n'] ?? 0;

$customers = DB::fetchAll("SELECT id, name, email FROM users WHERE role = 'customer' ORDER BY name ASC LIMIT 200");

$pageTitle = 'Vouchers';
require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white fw-bold mb-0">Vouchers</h4>
            <p class="text-muted small mb-0">Manage coupon codes and discount vouchers</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#bulkGenerateModal">
                <i class="bi bi-lightning me-1"></i>Bulk Generate
            </button>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#voucherModal"
                    onclick="openVoucherModal(null)">
                <i class="bi bi-plus-circle me-1"></i>New Voucher
            </button>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>Voucher saved. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-warning alert-dismissible fade show"><i class="bi bi-trash me-2"></i>Voucher deleted. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (isset($_GET['generated'])): ?>
    <div class="alert alert-info alert-dismissible fade show"><i class="bi bi-lightning me-2"></i><?= (int)$_GET['generated'] ?> voucher codes generated successfully. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-primary bg-opacity-10 text-primary rounded-3 p-3">
                        <i class="bi bi-ticket-perforated fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Vouchers</div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($kpiTotal) ?></div>
                        <div class="text-primary small">All codes</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-success bg-opacity-10 text-success rounded-3 p-3">
                        <i class="bi bi-check2-circle fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Active Vouchers</div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($kpiActive) ?></div>
                        <div class="text-success small">Ready to use</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-info bg-opacity-10 text-info rounded-3 p-3">
                        <i class="bi bi-arrow-repeat fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Redemptions</div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($kpiRedemptions) ?></div>
                        <div class="text-info small">All time uses</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-warning bg-opacity-10 text-warning rounded-3 p-3">
                        <i class="bi bi-piggy-bank fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Savings Given</div>
                        <div class="fs-4 fw-bold text-white"><?= CURRENCY_SYMBOL ?><?= number_format($kpiSavings, 2) ?></div>
                        <div class="text-warning small">Fixed discounts</div>
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
                    <?php foreach (['percentage','fixed','free_shipping'] as $t): ?>
                    <option value="<?= $t ?>" <?= $filterType === $t ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$t)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-4 col-md-3">
                <label class="form-label text-muted small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="">All Statuses</option>
                    <?php foreach (['active','expired','disabled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="/admin/vouchers.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
            </div>
        </form>
    </div>

    <!-- Vouchers Table -->
    <div class="admin-card rounded-4 p-4">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                    <tr class="text-muted small">
                        <th>Code</th>
                        <th>Type</th>
                        <th class="text-end">Value</th>
                        <th class="text-end">Used / Limit</th>
                        <th>Expires</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vouchers)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No vouchers found.</td></tr>
                    <?php else: ?>
                    <?php
                    $typeColors   = ['percentage'=>'info','fixed'=>'success','free_shipping'=>'primary'];
                    $statusColors = ['active'=>'success','expired'=>'secondary','disabled'=>'danger'];
                    foreach ($vouchers as $v):
                        $typeCol   = $typeColors[$v['type']] ?? 'secondary';
                        $statusCol = $statusColors[$v['status']] ?? 'secondary';
                        $valueDisp = $v['type'] === 'percentage' ? $v['value'] . '%'
                                   : ($v['type'] === 'fixed' ? CURRENCY_SYMBOL . number_format($v['value'],2)
                                   : 'Free Shipping');
                        $usagePct = $v['usage_limit'] > 0 ? min(100, round(($v['used_count']/$v['usage_limit'])*100)) : 0;
                        $isExpired = $v['expires_at'] && strtotime($v['expires_at']) < time();
                    ?>
                    <tr>
                        <td>
                            <code class="text-primary fw-bold fs-6"><?= htmlspecialchars($v['code']) ?></code>
                        </td>
                        <td><span class="badge bg-<?= $typeCol ?> bg-opacity-15 text-<?= $typeCol ?> border border-<?= $typeCol ?> border-opacity-25"><?= ucfirst(str_replace('_',' ',$v['type'])) ?></span></td>
                        <td class="text-white fw-semibold small text-end"><?= htmlspecialchars($valueDisp) ?></td>
                        <td class="text-end">
                            <span class="text-white small fw-semibold"><?= number_format($v['used_count']) ?></span>
                            <span class="text-muted small"> / <?= number_format($v['usage_limit']) ?></span>
                            <div class="progress mt-1" style="height:3px">
                                <div class="progress-bar bg-<?= $usagePct >= 90 ? 'danger' : 'primary' ?>" style="width:<?= $usagePct ?>%"></div>
                            </div>
                        </td>
                        <td class="small <?= $isExpired ? 'text-danger' : 'text-muted' ?>">
                            <?= $v['expires_at'] ? date('d M Y', strtotime($v['expires_at'])) : '—' ?>
                        </td>
                        <td class="text-muted small"><?= $v['customer_name'] ? htmlspecialchars($v['customer_name']) : '<span class="text-muted">All customers</span>' ?></td>
                        <td><span class="badge bg-<?= $statusCol ?>"><?= ucfirst($v['status']) ?></span></td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                <button class="btn btn-outline-secondary btn-sm py-0 px-2"
                                        onclick="openVoucherModal(<?= htmlspecialchars(json_encode($v), ENT_QUOTES) ?>)"
                                        data-bs-toggle="modal" data-bs-target="#voucherModal" title="Edit">
                                    <i class="bi bi-pencil" style="font-size:12px"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this voucher?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $v['id'] ?>">
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

<!-- Add/Edit Voucher Modal -->
<div class="modal fade" id="voucherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="voucherModalLabel">New Voucher</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="voucherForm">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="vFieldId" value="0">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Voucher Code <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" name="code" id="vFieldCode" class="form-control bg-dark text-white border-secondary text-uppercase" required placeholder="e.g. SAVE20NOW">
                                <button type="button" class="btn btn-outline-secondary" onclick="generateCode()">
                                    <i class="bi bi-shuffle"></i> Auto
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Type <span class="text-danger">*</span></label>
                            <select name="type" id="vFieldType" class="form-select bg-dark text-white border-secondary">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount</option>
                                <option value="free_shipping">Free Shipping</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Value</label>
                            <input type="number" name="value" id="vFieldValue" step="0.01" min="0"
                                   class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Min Order Value</label>
                            <input type="number" name="min_order" id="vFieldMinOrder" step="0.01" min="0" value="0"
                                   class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Usage Limit</label>
                            <input type="number" name="usage_limit" id="vFieldUsageLimit" min="1" value="1"
                                   class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Expires At</label>
                            <input type="date" name="expires_at" id="vFieldExpiresAt"
                                   class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Status</label>
                            <select name="status" id="vFieldStatus" class="form-select bg-dark text-white border-secondary">
                                <option value="active">Active</option>
                                <option value="disabled">Disabled</option>
                                <option value="expired">Expired</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Restrict to Customer <span class="text-muted">(optional — leave blank for all)</span></label>
                            <select name="customer_id" id="vFieldCustomerId" class="form-select bg-dark text-white border-secondary">
                                <option value="">All customers</option>
                                <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['email']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Voucher</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Generate Modal -->
<div class="modal fade" id="bulkGenerateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white"><i class="bi bi-lightning me-2 text-warning"></i>Bulk Generate Vouchers</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="bulk_generate">
                <div class="modal-body">
                    <p class="text-muted small">This will generate <strong class="text-white">10 random 8-character codes</strong> with the settings below.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Discount Type</label>
                            <select name="bulk_type" class="form-select bg-dark text-white border-secondary">
                                <option value="percentage">Percentage</option>
                                <option value="fixed">Fixed Amount</option>
                                <option value="free_shipping">Free Shipping</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Value</label>
                            <input type="number" name="bulk_value" value="10" step="0.01" min="0"
                                   class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Usage Limit per Code</label>
                            <input type="number" name="bulk_usage_limit" value="1" min="1"
                                   class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Expires At</label>
                            <input type="date" name="bulk_expires_at"
                                   class="form-control bg-dark text-white border-secondary">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark"><i class="bi bi-lightning me-1"></i>Generate 10 Codes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function generateCode() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let code = '';
    for (let i = 0; i < 8; i++) code += chars.charAt(Math.floor(Math.random() * chars.length));
    document.getElementById('vFieldCode').value = code;
}

function openVoucherModal(record) {
    document.getElementById('voucherModalLabel').textContent = record ? 'Edit Voucher' : 'New Voucher';
    document.getElementById('voucherForm').reset();
    document.getElementById('vFieldId').value = '0';
    if (!record) return;
    document.getElementById('vFieldId').value          = record.id;
    document.getElementById('vFieldCode').value        = record.code;
    document.getElementById('vFieldType').value        = record.type;
    document.getElementById('vFieldValue').value       = record.value;
    document.getElementById('vFieldMinOrder').value    = record.min_order;
    document.getElementById('vFieldUsageLimit').value  = record.usage_limit;
    document.getElementById('vFieldExpiresAt').value   = record.expires_at || '';
    document.getElementById('vFieldStatus').value      = record.status;
    document.getElementById('vFieldCustomerId').value  = record.customer_id || '';
}
</script>

<?php require_once '../includes/admin-footer.php'; ?>
