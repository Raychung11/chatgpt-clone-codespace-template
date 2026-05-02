<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

// ── POST handlers ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id   = (int)($_POST['id'] ?? 0);
        $data = [
            'name'          => htmlspecialchars(trim($_POST['name']          ?? '')),
            'contact_name'  => htmlspecialchars(trim($_POST['contact_name']  ?? '')),
            'email'         => trim($_POST['email'] ?? ''),
            'phone'         => htmlspecialchars(trim($_POST['phone']         ?? '')),
            'website'       => htmlspecialchars(trim($_POST['website']       ?? '')),
            'country'       => htmlspecialchars(trim($_POST['country']       ?? '')),
            'category'      => htmlspecialchars(trim($_POST['category']      ?? '')),
            'payment_terms' => htmlspecialchars(trim($_POST['payment_terms'] ?? '')),
            'status'        => in_array($_POST['status'] ?? '', ['active', 'inactive', 'blacklisted'])
                                    ? $_POST['status'] : 'active',
            'rating'        => max(1, min(5, (int)($_POST['rating'] ?? 1))),
            'notes'         => htmlspecialchars(trim($_POST['notes'] ?? '')),
        ];

        if ($id > 0) {
            DB::update('suppliers', $data, 'id = ?', [$id]);
        } else {
            DB::insert('suppliers', $data);
        }
        header('Location: suppliers.php?saved=1');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            DB::query('DELETE FROM suppliers WHERE id = ?', [$id]);
        }
        header('Location: suppliers.php?deleted=1');
        exit;
    }
}

// ── Filters ──────────────────────────────────────────────────────────────────
$filterStatus   = $_GET['status']   ?? '';
$filterCategory = trim($_GET['category'] ?? '');
$filterSearch   = trim($_GET['q']   ?? '');

$whereClauses = [];
$params       = [];

if ($filterStatus !== '') {
    $whereClauses[] = 's.status = ?';
    $params[]       = $filterStatus;
}
if ($filterCategory !== '') {
    $whereClauses[] = 's.category LIKE ?';
    $params[]       = "%$filterCategory%";
}
if ($filterSearch !== '') {
    $whereClauses[] = '(s.name LIKE ? OR s.email LIKE ?)';
    $params[]       = "%$filterSearch%";
    $params[]       = "%$filterSearch%";
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// ── KPIs ─────────────────────────────────────────────────────────────────────
$kpiTotal  = (int)DB::fetch('SELECT COUNT(*) AS n FROM suppliers')['n'];
$kpiActive = (int)DB::fetch("SELECT COUNT(*) AS n FROM suppliers WHERE status = 'active'")['n'];
$kpiPoVal  = (float)DB::fetch(
    "SELECT COALESCE(SUM(total), 0) AS v FROM purchase_orders WHERE status != ?",
    ['cancelled']
)['v'];
$kpiRating = DB::fetch('SELECT ROUND(AVG(rating), 1) AS v FROM suppliers')['v'];

// ── Supplier list with PO count ───────────────────────────────────────────────
$suppliers = DB::fetchAll(
    "SELECT s.*,
            (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id = s.id) AS po_count
     FROM suppliers s
     $whereSQL
     ORDER BY s.name ASC",
    $params
);

// ── Edit pre-fill ─────────────────────────────────────────────────────────────
$editSupplier = null;
if (isset($_GET['edit'])) {
    $editSupplier = DB::fetch('SELECT * FROM suppliers WHERE id = ?', [(int)$_GET['edit']]);
}

$pageTitle = 'Supplier Management';
require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Supplier saved successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        Supplier deleted.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white fw-bold mb-0">
            Suppliers
            <span class="text-muted fs-6">(<?= number_format($kpiTotal) ?>)</span>
        </h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#supplierModal">
            <i class="bi bi-plus-lg me-1"></i> Add Supplier
        </button>
    </div>

    <!-- KPI Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="admin-card rounded-4 p-3 text-center">
                <div class="text-muted small mb-1">Total Suppliers</div>
                <div class="text-white fs-4 fw-bold"><?= number_format($kpiTotal) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="admin-card rounded-4 p-3 text-center">
                <div class="text-muted small mb-1">Active</div>
                <div class="text-success fs-4 fw-bold"><?= number_format($kpiActive) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="admin-card rounded-4 p-3 text-center">
                <div class="text-muted small mb-1">Total PO Value</div>
                <div class="text-warning fs-4 fw-bold">
                    <?= APP_CURRENCY ?><?= number_format($kpiPoVal, 2) ?>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="admin-card rounded-4 p-3 text-center">
                <div class="text-muted small mb-1">Avg Supplier Rating</div>
                <div class="text-info fs-4 fw-bold">
                    <?= $kpiRating !== null ? htmlspecialchars($kpiRating) . ' ★' : '—' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="row g-2 mb-4 align-items-end">
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label text-muted small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm bg-dark border-secondary text-white">
                <option value="">All Statuses</option>
                <option value="active"      <?= $filterStatus === 'active'      ? 'selected' : '' ?>>Active</option>
                <option value="inactive"    <?= $filterStatus === 'inactive'    ? 'selected' : '' ?>>Inactive</option>
                <option value="blacklisted" <?= $filterStatus === 'blacklisted' ? 'selected' : '' ?>>Blacklisted</option>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <label class="form-label text-muted small mb-1">Category</label>
            <input type="text" name="category" value="<?= htmlspecialchars($filterCategory) ?>"
                   class="form-control form-control-sm bg-dark border-secondary text-white"
                   placeholder="e.g. SaaS, Hardware…">
        </div>
        <div class="col-12 col-sm-8 col-md-4">
            <label class="form-label text-muted small mb-1">Search</label>
            <input type="text" name="q" value="<?= htmlspecialchars($filterSearch) ?>"
                   class="form-control form-control-sm bg-dark border-secondary text-white"
                   placeholder="Name or email…">
        </div>
        <div class="col-12 col-sm-4 col-md-2 d-flex gap-2">
            <button class="btn btn-sm btn-primary w-100">
                <i class="bi bi-search"></i> Filter
            </button>
            <a href="suppliers.php" class="btn btn-sm btn-outline-secondary">Clear</a>
        </div>
    </form>

    <!-- Supplier Table -->
    <div class="admin-card rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead class="border-bottom border-secondary">
                    <tr class="text-muted small">
                        <th>Name / Email</th>
                        <th>Contact</th>
                        <th>Country</th>
                        <th>Category</th>
                        <th>Payment Terms</th>
                        <th>Rating</th>
                        <th>Status</th>
                        <th class="text-center">POs</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suppliers as $sup): ?>
                    <tr>
                        <td>
                            <div class="text-white small fw-semibold">
                                <?= htmlspecialchars($sup['name']) ?>
                            </div>
                            <div class="text-muted" style="font-size:11px">
                                <?= htmlspecialchars($sup['email']) ?>
                            </div>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars($sup['contact_name']) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($sup['country']) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($sup['category']) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($sup['payment_terms']) ?></td>
                        <td style="white-space:nowrap">
                            <?php
                            $rating = max(0, min(5, (int)$sup['rating']));
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $rating
                                    ? '<span class="text-warning" title="' . $rating . '/5">★</span>'
                                    : '<span class="text-secondary">★</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            $badgeClass = match($sup['status']) {
                                'active'      => 'success',
                                'inactive'    => 'secondary',
                                'blacklisted' => 'danger',
                                default       => 'secondary',
                            };
                            ?>
                            <span class="badge bg-<?= $badgeClass ?>">
                                <?= ucfirst(htmlspecialchars($sup['status'])) ?>
                            </span>
                        </td>
                        <td class="text-center text-muted small"><?= (int)$sup['po_count'] ?></td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-1 flex-nowrap">
                                <a href="suppliers.php?edit=<?= (int)$sup['id'] ?>"
                                   class="btn btn-sm btn-outline-primary" title="Edit supplier">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="purchase-orders.php?supplier=<?= (int)$sup['id'] ?>"
                                   class="btn btn-sm btn-outline-info" title="View purchase orders">
                                    <i class="bi bi-file-earmark-text"></i>
                                </a>
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('Delete supplier \'<?= htmlspecialchars(addslashes($sup['name'])) ?>\'? This cannot be undone.')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id"     value="<?= (int)$sup['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete supplier">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($suppliers)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-5">
                            <i class="bi bi-building fs-3 d-block mb-2 opacity-50"></i>
                            No suppliers found matching your filters.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /container-fluid -->

<!-- Add / Edit Supplier Modal -->
<div class="modal fade" id="supplierModal" tabindex="-1"
     aria-labelledby="supplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-dark border-secondary">
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $editSupplier ? (int)$editSupplier['id'] : 0 ?>">

                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-white" id="supplierModalLabel">
                        <i class="bi bi-building me-2"></i>
                        <?= $editSupplier ? 'Edit Supplier' : 'Add New Supplier' ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white"
                            data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label text-muted small">
                                Company Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="name" required
                                   value="<?= $editSupplier ? htmlspecialchars($editSupplier['name']) : '' ?>"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="Acme Corp">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-muted small">Contact Name</label>
                            <input type="text" name="contact_name"
                                   value="<?= $editSupplier ? htmlspecialchars($editSupplier['contact_name']) : '' ?>"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="John Smith">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-muted small">Email</label>
                            <input type="email" name="email"
                                   value="<?= $editSupplier ? htmlspecialchars($editSupplier['email']) : '' ?>"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="contact@supplier.com">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-muted small">Phone</label>
                            <input type="text" name="phone"
                                   value="<?= $editSupplier ? htmlspecialchars($editSupplier['phone']) : '' ?>"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="+1 555 000 0000">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-muted small">Website</label>
                            <input type="text" name="website"
                                   value="<?= $editSupplier ? htmlspecialchars($editSupplier['website']) : '' ?>"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="https://supplier.com">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-muted small">Country</label>
                            <input type="text" name="country"
                                   value="<?= $editSupplier ? htmlspecialchars($editSupplier['country']) : '' ?>"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="United States">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-muted small">Category</label>
                            <input type="text" name="category"
                                   value="<?= $editSupplier ? htmlspecialchars($editSupplier['category']) : '' ?>"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="e.g. SaaS, Hardware, Services">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-muted small">Payment Terms</label>
                            <input type="text" name="payment_terms"
                                   value="<?= $editSupplier ? htmlspecialchars($editSupplier['payment_terms']) : '' ?>"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="e.g. Net 30, Prepaid, COD">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-muted small">Status</label>
                            <select name="status" class="form-select bg-dark border-secondary text-white">
                                <?php
                                $currentStatus = $editSupplier['status'] ?? 'active';
                                foreach (['active', 'inactive', 'blacklisted'] as $s):
                                ?>
                                <option value="<?= $s ?>" <?= $currentStatus === $s ? 'selected' : '' ?>>
                                    <?= ucfirst($s) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-muted small">Rating</label>
                            <select name="rating" class="form-select bg-dark border-secondary text-white">
                                <?php
                                $currentRating = (int)($editSupplier['rating'] ?? 3);
                                for ($i = 1; $i <= 5; $i++):
                                ?>
                                <option value="<?= $i ?>" <?= $currentRating === $i ? 'selected' : '' ?>>
                                    <?= str_repeat('★', $i) . str_repeat('☆', 5 - $i) ?> &nbsp;(<?= $i ?> star<?= $i > 1 ? 's' : '' ?>)
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label text-muted small">Notes</label>
                            <textarea name="notes" rows="3"
                                      class="form-control bg-dark border-secondary text-white"
                                      placeholder="Internal notes about this supplier…"><?= $editSupplier ? htmlspecialchars($editSupplier['notes']) : '' ?></textarea>
                        </div>

                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>
                        <?= $editSupplier ? 'Save Changes' : 'Add Supplier' ?>
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<?php if ($editSupplier): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('supplierModal');
    var modal   = new bootstrap.Modal(modalEl, { backdrop: 'static' });
    modal.show();
    // When modal is closed without saving, return to clean URL
    modalEl.addEventListener('hidden.bs.modal', function () {
        var url = new URL(window.location.href);
        url.searchParams.delete('edit');
        window.history.replaceState({}, '', url.toString());
    });
});
</script>
<?php endif; ?>

<?php require_once '../includes/admin-footer.php'; ?>
