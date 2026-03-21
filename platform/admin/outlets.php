<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);

        $allowedTypes    = ['retail', 'kiosk', 'warehouse', 'online', 'franchise'];
        $allowedStatuses = ['active', 'inactive', 'temporarily_closed'];

        $data = [
            'name'            => htmlspecialchars(trim($_POST['name']            ?? '')),
            'code'            => strtoupper(htmlspecialchars(trim($_POST['code'] ?? ''))),
            'address'         => htmlspecialchars(trim($_POST['address']         ?? '')),
            'city'            => htmlspecialchars(trim($_POST['city']            ?? '')),
            'state'           => htmlspecialchars(trim($_POST['state']           ?? '')),
            'country'         => htmlspecialchars(trim($_POST['country']         ?? 'Malaysia')),
            'phone'           => htmlspecialchars(trim($_POST['phone']           ?? '')),
            'email'           => trim($_POST['email'] ?? ''),
            'manager_name'    => htmlspecialchars(trim($_POST['manager_name']    ?? '')),
            'outlet_type'     => in_array($_POST['outlet_type'] ?? '', $allowedTypes)
                                     ? $_POST['outlet_type'] : 'retail',
            'status'          => in_array($_POST['status'] ?? '', $allowedStatuses)
                                     ? $_POST['status'] : 'active',
            'opening_date'    => $_POST['opening_date'] !== '' ? $_POST['opening_date'] : null,
            'operating_hours' => htmlspecialchars(trim($_POST['operating_hours'] ?? '')),
            'notes'           => htmlspecialchars(trim($_POST['notes']           ?? '')),
        ];

        if ($id > 0) {
            DB::update('outlets', $data, 'id = ?', [$id]);
        } else {
            DB::insert('outlets', $data);
        }
        header('Location: outlets.php?saved=1');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            DB::query('DELETE FROM outlets WHERE id = ?', [$id]);
        }
        header('Location: outlets.php?deleted=1');
        exit;
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$filterType   = $_GET['type']   ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterSearch = trim($_GET['q'] ?? '');

$whereClauses = [];
$params       = [];

if ($filterType !== '') {
    $whereClauses[] = 'outlet_type = ?';
    $params[]       = $filterType;
}
if ($filterStatus !== '') {
    $whereClauses[] = 'status = ?';
    $params[]       = $filterStatus;
}
if ($filterSearch !== '') {
    $whereClauses[] = '(name LIKE ? OR city LIKE ? OR code LIKE ?)';
    $params[]       = "%$filterSearch%";
    $params[]       = "%$filterSearch%";
    $params[]       = "%$filterSearch%";
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// ── KPIs ──────────────────────────────────────────────────────────────────────
$kpiTotal    = (int)(DB::fetch('SELECT COUNT(*) AS n FROM outlets')['n'] ?? 0);
$kpiActive   = (int)(DB::fetch("SELECT COUNT(*) AS n FROM outlets WHERE status = 'active'")['n'] ?? 0);
$kpiInactive = (int)(DB::fetch(
    "SELECT COUNT(*) AS n FROM outlets WHERE status IN ('inactive','temporarily_closed')"
)['n'] ?? 0);
$kpiTypes    = (int)(DB::fetch('SELECT COUNT(DISTINCT outlet_type) AS n FROM outlets')['n'] ?? 0);

// ── Outlet list ───────────────────────────────────────────────────────────────
$outlets = DB::fetchAll(
    "SELECT * FROM outlets $whereSQL ORDER BY name ASC",
    $params
);

// ── Edit pre-fill ─────────────────────────────────────────────────────────────
$editOutlet = null;
if (isset($_GET['edit'])) {
    $editOutlet = DB::fetch('SELECT * FROM outlets WHERE id = ?', [(int)$_GET['edit']]);
}

$pageTitle = 'Outlets';
require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>Outlet saved successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="bi bi-trash me-2"></i>Outlet deleted.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white fw-bold mb-0">
                Outlets
                <span class="text-muted fs-6 fw-normal">(<?= number_format($kpiTotal) ?>)</span>
            </h4>
            <p class="text-muted small mb-0">Manage your retail branches, kiosks and online outlets</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/admin/outlet-reports.php" class="btn btn-outline-info btn-sm">
                <i class="bi bi-bar-chart me-1"></i>Reports
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#outletModal">
                <i class="bi bi-plus-lg me-1"></i>Add Outlet
            </button>
        </div>
    </div>

    <!-- KPI Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-primary bg-opacity-10 text-primary rounded-3 p-3">
                        <i class="bi bi-shop fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Outlets</div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($kpiTotal) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-success bg-opacity-10 text-success rounded-3 p-3">
                        <i class="bi bi-check-circle fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Active</div>
                        <div class="fs-4 fw-bold text-success"><?= number_format($kpiActive) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-warning bg-opacity-10 text-warning rounded-3 p-3">
                        <i class="bi bi-pause-circle fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Inactive / Closed</div>
                        <div class="fs-4 fw-bold text-warning"><?= number_format($kpiInactive) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="kpi-icon bg-info bg-opacity-10 text-info rounded-3 p-3">
                        <i class="bi bi-diagram-2 fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Outlet Types</div>
                        <div class="fs-4 fw-bold text-info"><?= number_format($kpiTypes) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="row g-2 mb-4 align-items-end">
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label text-muted small mb-1">Type</label>
            <select name="type" class="form-select form-select-sm bg-dark border-secondary text-white">
                <option value="">All Types</option>
                <option value="retail"    <?= $filterType === 'retail'    ? 'selected' : '' ?>>Retail</option>
                <option value="kiosk"     <?= $filterType === 'kiosk'     ? 'selected' : '' ?>>Kiosk</option>
                <option value="warehouse" <?= $filterType === 'warehouse' ? 'selected' : '' ?>>Warehouse</option>
                <option value="online"    <?= $filterType === 'online'    ? 'selected' : '' ?>>Online</option>
                <option value="franchise" <?= $filterType === 'franchise' ? 'selected' : '' ?>>Franchise</option>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label text-muted small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm bg-dark border-secondary text-white">
                <option value="">All Statuses</option>
                <option value="active"              <?= $filterStatus === 'active'              ? 'selected' : '' ?>>Active</option>
                <option value="inactive"            <?= $filterStatus === 'inactive'            ? 'selected' : '' ?>>Inactive</option>
                <option value="temporarily_closed"  <?= $filterStatus === 'temporarily_closed'  ? 'selected' : '' ?>>Temporarily Closed</option>
            </select>
        </div>
        <div class="col-12 col-sm-8 col-md-4">
            <label class="form-label text-muted small mb-1">Search</label>
            <input type="text" name="q" value="<?= htmlspecialchars($filterSearch) ?>"
                   class="form-control form-control-sm bg-dark border-secondary text-white"
                   placeholder="Name, city or code…">
        </div>
        <div class="col-12 col-sm-4 col-md-2 d-flex gap-2">
            <button class="btn btn-sm btn-primary w-100">
                <i class="bi bi-search"></i> Filter
            </button>
            <a href="outlets.php" class="btn btn-sm btn-outline-secondary">Clear</a>
        </div>
    </form>

    <!-- Outlets Table -->
    <div class="admin-card rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead class="border-bottom border-secondary">
                    <tr class="text-muted small">
                        <th style="min-width:90px">Code</th>
                        <th style="min-width:180px">Name</th>
                        <th>Type</th>
                        <th>City / State</th>
                        <th>Manager</th>
                        <th>Status</th>
                        <th>Opening Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($outlets as $outlet):

                        // Status badge
                        $statusBadge = match($outlet['status']) {
                            'active'             => 'success',
                            'inactive'           => 'secondary',
                            'temporarily_closed' => 'warning',
                            default              => 'secondary',
                        };
                        $statusLabel = match($outlet['status']) {
                            'active'             => 'Active',
                            'inactive'           => 'Inactive',
                            'temporarily_closed' => 'Temp. Closed',
                            default              => ucfirst($outlet['status']),
                        };

                        // Type badge inline style
                        $typeColor = match($outlet['outlet_type']) {
                            'retail'    => '#0d6efd',   // Bootstrap primary blue
                            'kiosk'     => '#0dcaf0',   // Bootstrap info cyan
                            'warehouse' => '#6c757d',   // Bootstrap secondary
                            'online'    => '#6366f1',   // Project indigo
                            'franchise' => '#9333ea',   // Purple
                            default     => '#6c757d',
                        };
                    ?>
                    <tr>
                        <td>
                            <code class="px-2 py-1 rounded-2 small fw-bold"
                                  style="background:rgba(99,102,241,.15);color:#a5b4fc;letter-spacing:.04em">
                                <?= htmlspecialchars($outlet['code']) ?>
                            </code>
                        </td>
                        <td>
                            <div class="text-white small fw-semibold"><?= htmlspecialchars($outlet['name']) ?></div>
                            <?php if ($outlet['email']): ?>
                            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($outlet['email']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge rounded-pill"
                                  style="background:<?= $typeColor ?>22;color:<?= $typeColor ?>;border:1px solid <?= $typeColor ?>44">
                                <?= ucfirst(htmlspecialchars($outlet['outlet_type'])) ?>
                            </span>
                        </td>
                        <td class="text-muted small">
                            <?= htmlspecialchars($outlet['city']) ?>
                            <?php if ($outlet['state']): ?>
                                <span class="text-secondary">, <?= htmlspecialchars($outlet['state']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small">
                            <?= $outlet['manager_name'] ? htmlspecialchars($outlet['manager_name']) : '<span class="text-secondary">—</span>' ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $statusBadge ?><?= $outlet['status'] === 'temporarily_closed' ? ' text-dark' : '' ?>">
                                <?= $statusLabel ?>
                            </span>
                        </td>
                        <td class="text-muted small">
                            <?= $outlet['opening_date'] ? date('d M Y', strtotime($outlet['opening_date'])) : '—' ?>
                        </td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-1 flex-nowrap">
                                <a href="outlet-reports.php?outlet=<?= (int)$outlet['id'] ?>"
                                   class="btn btn-sm btn-outline-info" title="View reports">
                                    <i class="bi bi-bar-chart-line"></i>
                                </a>
                                <a href="outlets.php?edit=<?= (int)$outlet['id'] ?>"
                                   class="btn btn-sm btn-outline-primary" title="Edit outlet">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('Delete outlet \'<?= htmlspecialchars(addslashes($outlet['name'])) ?>\'? This cannot be undone.')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id"     value="<?= (int)$outlet['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete outlet">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($outlets)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-shop fs-2 d-block mb-2 opacity-50"></i>
                            No outlets found matching your filters.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /container-fluid -->

<!-- Add / Edit Outlet Modal -->
<div class="modal fade" id="outletModal" tabindex="-1"
     aria-labelledby="outletModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content bg-dark border-secondary">
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $editOutlet ? (int)$editOutlet['id'] : 0 ?>">

                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-white" id="outletModalLabel">
                        <i class="bi bi-shop me-2"></i>
                        <?= $editOutlet ? 'Edit Outlet' : 'Add New Outlet' ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white"
                            data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <!-- Name -->
                        <div class="col-md-8">
                            <label class="form-label text-muted small">
                                Outlet Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="name" required
                                   value="<?= $editOutlet ? htmlspecialchars($editOutlet['name']) : '' ?>"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="e.g. Mid Valley Megamall">
                        </div>

                        <!-- Code -->
                        <div class="col-md-4">
                            <label class="form-label text-muted small">
                                Outlet Code <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="code" required maxlength="20"
                                   value="<?= $editOutlet ? htmlspecialchars($editOutlet['code']) : '' ?>"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="e.g. KL-MV-01"
                                   style="font-family:monospace;text-transform:uppercase">
                        </div>

                        <!-- Type -->
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Outlet Type</label>
                            <select name="outlet_type" class="form-select bg-dark border-secondary text-white">
                                <?php
                                $currentType = $editOutlet['outlet_type'] ?? 'retail';
                                $typeOptions = ['retail' => 'Retail', 'kiosk' => 'Kiosk',
                                                'warehouse' => 'Warehouse', 'online' => 'Online',
                                                'franchise' => 'Franchise'];
                                foreach ($typeOptions as $val => $label):
                                ?>
                                <option value="<?= $val ?>" <?= $currentType === $val ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Status -->
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Status</label>
                            <select name="status" class="form-select bg-dark border-secondary text-white">
                                <?php
                                $currentStatus = $editOutlet['status'] ?? 'active';
                                $statusOptions = ['active' => 'Active', 'inactive' => 'Inactive',
                                                  'temporarily_closed' => 'Temporarily Closed'];
                                foreach ($statusOptions as $val => $label):
                                ?>
                                <option value="<?= $val ?>" <?= $currentStatus === $val ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Opening Date -->
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Opening Date</label>
                            <input type="date" name="opening_date"
                                   value="<?= $editOutlet ? htmlspecialchars($editOutlet['opening_date'] ?? '') : '' ?>"
                                   class="form-control bg-dark border-secondary text-white">
                        </div>

                        <!-- Address -->
                        <div class="col-12">
                            <label class="form-label text-muted small">Address</label>
                            <textarea name="address" rows="2"
                                      class="form-control bg-dark border-secondary text-white"
                                      placeholder="Street address, unit number, floor…"><?= $editOutlet ? htmlspecialchars($editOutlet['address'] ?? '') : '' ?></textarea>
                        </div>

                        <!-- City -->
                        <div class="col-md-4">
                            <label class="form-label text-muted small">City</label>
                            <input type="text" name="city"
                                   value="<?= $editOutlet ? htmlspecialchars($editOutlet['city'] ?? '') : '' ?>"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="e.g. Kuala Lumpur">
                        </div>

                        <!-- State -->
                        <div class="col-md-4">
                            <label class="form-label text-muted small">State</label>
                            <input type="text" name="state"
                                   value="<?= $editOutlet ? htmlspecialchars($editOutlet['state'] ?? '') : '' ?>"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="e.g. Selangor">
                        </div>

                        <!-- Country -->
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Country</label>
                            <input type="text" name="country"
                                   value="<?= $editOutlet ? htmlspecialchars($editOutlet['country'] ?? 'Malaysia') : 'Malaysia' ?>"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="Malaysia">
                        </div>

                        <!-- Phone -->
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Phone</label>
                            <input type="text" name="phone"
                                   value="<?= $editOutlet ? htmlspecialchars($editOutlet['phone'] ?? '') : '' ?>"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="+60 3-1234 5678">
                        </div>

                        <!-- Email -->
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Email</label>
                            <input type="email" name="email"
                                   value="<?= $editOutlet ? htmlspecialchars($editOutlet['email'] ?? '') : '' ?>"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="outlet@company.com">
                        </div>

                        <!-- Manager -->
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Manager Name</label>
                            <input type="text" name="manager_name"
                                   value="<?= $editOutlet ? htmlspecialchars($editOutlet['manager_name'] ?? '') : '' ?>"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="e.g. Ahmad Razif">
                        </div>

                        <!-- Operating Hours -->
                        <div class="col-12">
                            <label class="form-label text-muted small">Operating Hours</label>
                            <input type="text" name="operating_hours"
                                   value="<?= $editOutlet ? htmlspecialchars($editOutlet['operating_hours'] ?? '') : '' ?>"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="e.g. Mon–Sun 10:00 AM – 10:00 PM">
                        </div>

                        <!-- Notes -->
                        <div class="col-12">
                            <label class="form-label text-muted small">Notes</label>
                            <textarea name="notes" rows="3"
                                      class="form-control bg-dark border-secondary text-white"
                                      placeholder="Internal notes about this outlet…"><?= $editOutlet ? htmlspecialchars($editOutlet['notes'] ?? '') : '' ?></textarea>
                        </div>

                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>
                        <?= $editOutlet ? 'Save Changes' : 'Add Outlet' ?>
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<?php if ($editOutlet): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('outletModal');
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
