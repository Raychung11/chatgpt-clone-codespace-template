<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();
$pageTitle = 'Suppliers';

$msg = '';

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id           = (int)($_POST['id'] ?? 0);
        $name         = htmlspecialchars(trim($_POST['name'] ?? ''));
        $contactName  = htmlspecialchars(trim($_POST['contact_name'] ?? ''));
        $email        = htmlspecialchars(strtolower(trim($_POST['email'] ?? '')));
        $phone        = htmlspecialchars(trim($_POST['phone'] ?? ''));
        $website      = htmlspecialchars(trim($_POST['website'] ?? ''));
        $country      = htmlspecialchars(trim($_POST['country'] ?? ''));
        $category     = htmlspecialchars(trim($_POST['category'] ?? ''));
        $paymentTerms = htmlspecialchars(trim($_POST['payment_terms'] ?? ''));
        $status       = in_array($_POST['status'] ?? '', ['active','inactive','blacklisted']) ? $_POST['status'] : 'active';
        $rating       = max(1, min(5, (int)($_POST['rating'] ?? 3)));
        $notes        = htmlspecialchars(trim($_POST['notes'] ?? ''));

        if ($name === '') {
            $msg = '<div class="alert alert-danger py-2">Supplier name is required.</div>';
        } else {
            $data = [
                'name'          => $name,
                'contact_name'  => $contactName,
                'email'         => $email,
                'phone'         => $phone,
                'website'       => $website,
                'country'       => $country,
                'category'      => $category,
                'payment_terms' => $paymentTerms,
                'status'        => $status,
                'rating'        => $rating,
                'notes'         => $notes,
            ];

            if ($id > 0) {
                DB::update('suppliers', $data, 'id = ?', [$id]);
                $msg = '<div class="alert alert-success py-2">Supplier updated successfully.</div>';
            } else {
                DB::insert('suppliers', $data);
                $msg = '<div class="alert alert-success py-2">Supplier added successfully.</div>';
            }
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            DB::query('DELETE FROM suppliers WHERE id = ?', [$id]);
        }
        header('Location: /admin/suppliers.php');
        exit;
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$statusFilter   = trim($_GET['status']   ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$search         = trim($_GET['q']        ?? '');

$allowedStatuses = ['active', 'inactive', 'blacklisted'];
if (!in_array($statusFilter, $allowedStatuses)) $statusFilter = '';

// ── Fetch suppliers ───────────────────────────────────────────────────────────
$suppliers = DB::fetchAll(
    "SELECT s.*,
            COUNT(po.id)                    AS po_count,
            COALESCE(SUM(CASE WHEN po.status != 'cancelled' THEN po.total ELSE 0 END), 0) AS po_value
     FROM suppliers s
     LEFT JOIN purchase_orders po ON po.supplier_id = s.id
     WHERE (? = '' OR s.status = ?)
       AND (? = '' OR s.category LIKE ?)
       AND (? = '' OR s.name LIKE ? OR s.email LIKE ?)
     GROUP BY s.id
     ORDER BY s.name ASC",
    [
        $statusFilter, $statusFilter,
        $categoryFilter, "%$categoryFilter%",
        $search, "%$search%", "%$search%",
    ]
);

// ── KPIs ──────────────────────────────────────────────────────────────────────
$kpiRow = DB::fetch(
    "SELECT
        COUNT(*)                                                                          AS total,
        SUM(status = 'active')                                                            AS active_count,
        COALESCE((SELECT SUM(total) FROM purchase_orders WHERE status != 'cancelled'), 0) AS total_po_value,
        ROUND(AVG(rating), 1)                                                             AS avg_rating
     FROM suppliers"
);

// ── Edit pre-fill ─────────────────────────────────────────────────────────────
$editSupplier = null;
$autoOpenModal = false;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    if ($editId > 0) {
        $editSupplier = DB::fetch('SELECT * FROM suppliers WHERE id = ?', [$editId]);
        if ($editSupplier) $autoOpenModal = true;
    }
}

require_once '../includes/admin-header.php';

// ── Helper: render star rating ────────────────────────────────────────────────
function renderStars(int $rating, int $max = 5): string {
    $out = '';
    for ($i = 1; $i <= $max; $i++) {
        $out .= $i <= $rating
            ? '<i class="bi bi-star-fill text-warning" style="font-size:13px"></i>'
            : '<i class="bi bi-star text-secondary"    style="font-size:13px"></i>';
    }
    return $out;
}
?>

<div class="container-fluid py-4">

    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white fw-bold mb-0">
            Suppliers
            <span class="text-muted fs-6">(<?= count($suppliers) ?>)</span>
        </h4>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#supplierModal"
                onclick="openAddModal()">
            <i class="bi bi-plus-circle me-1"></i>Add Supplier
        </button>
    </div>

    <?= $msg ?>

    <!-- KPI row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="admin-card rounded-4 p-3 text-center">
                <div class="text-muted small mb-1">Total Suppliers</div>
                <div class="text-white fw-bold fs-4"><?= (int)$kpiRow['total'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="admin-card rounded-4 p-3 text-center">
                <div class="text-muted small mb-1">Active</div>
                <div class="text-success fw-bold fs-4"><?= (int)$kpiRow['active_count'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="admin-card rounded-4 p-3 text-center">
                <div class="text-muted small mb-1">Total PO Value</div>
                <div class="text-primary fw-bold fs-5">
                    <?= CURRENCY_SYMBOL ?><?= number_format((float)$kpiRow['total_po_value'], 2) ?>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="admin-card rounded-4 p-3 text-center">
                <div class="text-muted small mb-1">Avg Rating</div>
                <div class="fw-bold fs-5 d-flex align-items-center justify-content-center gap-1">
                    <span class="text-warning"><?= number_format((float)$kpiRow['avg_rating'], 1) ?></span>
                    <i class="bi bi-star-fill text-warning" style="font-size:14px"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter bar -->
    <form method="GET" class="row g-2 mb-4 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label text-muted small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm bg-dark border-secondary text-white"
                    onchange="this.form.submit()">
                <option value="" <?= $statusFilter==='' ? 'selected' : '' ?>>All Statuses</option>
                <option value="active"      <?= $statusFilter==='active'      ? 'selected' : '' ?>>Active</option>
                <option value="inactive"    <?= $statusFilter==='inactive'    ? 'selected' : '' ?>>Inactive</option>
                <option value="blacklisted" <?= $statusFilter==='blacklisted' ? 'selected' : '' ?>>Blacklisted</option>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label text-muted small mb-1">Category</label>
            <input type="text" name="category" value="<?= htmlspecialchars($categoryFilter) ?>"
                   class="form-control form-control-sm bg-dark border-secondary text-white"
                   placeholder="Filter by category...">
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label text-muted small mb-1">Search</label>
            <input type="search" name="q" value="<?= htmlspecialchars($search) ?>"
                   class="form-control form-control-sm bg-dark border-secondary text-white"
                   placeholder="Name or email...">
        </div>
        <div class="col-12 col-md-2">
            <button class="btn btn-sm btn-outline-secondary w-100">
                <i class="bi bi-search me-1"></i>Filter
            </button>
        </div>
    </form>

    <!-- Suppliers table -->
    <div class="admin-card rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
                <thead class="border-bottom border-secondary">
                    <tr class="text-muted small">
                        <th>Supplier</th>
                        <th>Contact</th>
                        <th>Country</th>
                        <th>Category</th>
                        <th>Payment Terms</th>
                        <th>Rating</th>
                        <th>Status</th>
                        <th class="text-end">POs</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suppliers as $sup):
                        $badgeMap = [
                            'active'      => 'success',
                            'inactive'    => 'secondary',
                            'blacklisted' => 'danger',
                        ];
                        $badgeClass = $badgeMap[$sup['status']] ?? 'secondary';
                    ?>
                    <tr>
                        <td>
                            <div class="text-white small fw-semibold">
                                <?= htmlspecialchars($sup['name']) ?>
                            </div>
                            <div class="text-muted" style="font-size:11px">
                                <?= htmlspecialchars($sup['email']) ?>
                            </div>
                        </td>
                        <td>
                            <div class="text-white small"><?= htmlspecialchars($sup['contact_name']) ?></div>
                            <?php if ($sup['phone']): ?>
                            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($sup['phone']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars($sup['country']) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($sup['category']) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($sup['payment_terms']) ?></td>
                        <td><?= renderStars((int)$sup['rating']) ?></td>
                        <td>
                            <span class="badge bg-<?= $badgeClass ?>" style="font-size:10px">
                                <?= ucfirst(htmlspecialchars($sup['status'])) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <span class="badge bg-secondary"><?= (int)$sup['po_count'] ?></span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <!-- Edit -->
                                <a href="?edit=<?= $sup['id'] ?>&status=<?= urlencode($statusFilter) ?>&category=<?= urlencode($categoryFilter) ?>&q=<?= urlencode($search) ?>"
                                   class="btn btn-sm btn-outline-secondary" title="Edit Supplier">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <!-- View POs -->
                                <a href="/admin/purchase-orders.php?supplier=<?= $sup['id'] ?>"
                                   class="btn btn-sm btn-outline-primary" title="View Purchase Orders">
                                    <i class="bi bi-file-earmark-text"></i>
                                </a>
                                <!-- Delete -->
                                <form method="POST"
                                      onsubmit="return confirm('Delete supplier \'<?= htmlspecialchars(addslashes($sup['name'])) ?>\'? This cannot be undone.')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id"     value="<?= $sup['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" title="Delete">
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
                            <i class="bi bi-building-x fs-2 d-block mb-2"></i>
                            No suppliers found.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add / Edit Supplier Modal -->
<div class="modal fade" id="supplierModal" tabindex="-1" aria-labelledby="supplierModalLabel">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="supplierModalLabel">Add Supplier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id"     id="modal_id" value="0">
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Name -->
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Supplier Name *</label>
                            <input type="text" name="name" id="modal_name"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="Company name" required>
                        </div>
                        <!-- Contact Name -->
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Contact Name</label>
                            <input type="text" name="contact_name" id="modal_contact_name"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="Primary contact">
                        </div>
                        <!-- Email -->
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Email</label>
                            <input type="email" name="email" id="modal_email"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="supplier@example.com">
                        </div>
                        <!-- Phone -->
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Phone</label>
                            <input type="text" name="phone" id="modal_phone"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="+1 555 000 0000">
                        </div>
                        <!-- Website -->
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Website</label>
                            <input type="url" name="website" id="modal_website"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="https://...">
                        </div>
                        <!-- Country -->
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Country</label>
                            <input type="text" name="country" id="modal_country"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="United States">
                        </div>
                        <!-- Category -->
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Category</label>
                            <input type="text" name="category" id="modal_category"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="Software, Hardware, Services…">
                        </div>
                        <!-- Payment Terms -->
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Payment Terms</label>
                            <input type="text" name="payment_terms" id="modal_payment_terms"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="Net 30, Prepaid…">
                        </div>
                        <!-- Status -->
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Status</label>
                            <select name="status" id="modal_status"
                                    class="form-select bg-dark border-secondary text-white">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="blacklisted">Blacklisted</option>
                            </select>
                        </div>
                        <!-- Rating -->
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Rating (1–5)</label>
                            <select name="rating" id="modal_rating"
                                    class="form-select bg-dark border-secondary text-white">
                                <?php for ($r = 1; $r <= 5; $r++): ?>
                                <option value="<?= $r ?>">
                                    <?= str_repeat('★', $r) . str_repeat('☆', 5 - $r) ?> (<?= $r ?>)
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <!-- Notes -->
                        <div class="col-12">
                            <label class="form-label text-muted small">Notes</label>
                            <textarea name="notes" id="modal_notes" rows="3"
                                      class="form-control bg-dark border-secondary text-white"
                                      placeholder="Internal notes about this supplier…"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="modal_submit_btn">Add Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Encode supplier data for JS pre-fill if editing
$editJson = $editSupplier ? json_encode($editSupplier) : 'null';
?>
<script>
const editData = <?= $editJson ?>;

function openAddModal() {
    document.getElementById('supplierModalLabel').textContent = 'Add Supplier';
    document.getElementById('modal_submit_btn').textContent   = 'Add Supplier';
    document.getElementById('modal_id').value           = '0';
    document.getElementById('modal_name').value         = '';
    document.getElementById('modal_contact_name').value = '';
    document.getElementById('modal_email').value        = '';
    document.getElementById('modal_phone').value        = '';
    document.getElementById('modal_website').value      = '';
    document.getElementById('modal_country').value      = '';
    document.getElementById('modal_category').value     = '';
    document.getElementById('modal_payment_terms').value= '';
    document.getElementById('modal_status').value       = 'active';
    document.getElementById('modal_rating').value       = '3';
    document.getElementById('modal_notes').value        = '';
}

function fillEditModal(data) {
    document.getElementById('supplierModalLabel').textContent = 'Edit Supplier';
    document.getElementById('modal_submit_btn').textContent   = 'Save Changes';
    document.getElementById('modal_id').value           = data.id;
    document.getElementById('modal_name').value         = data.name          || '';
    document.getElementById('modal_contact_name').value = data.contact_name  || '';
    document.getElementById('modal_email').value        = data.email         || '';
    document.getElementById('modal_phone').value        = data.phone         || '';
    document.getElementById('modal_website').value      = data.website       || '';
    document.getElementById('modal_country').value      = data.country       || '';
    document.getElementById('modal_category').value     = data.category      || '';
    document.getElementById('modal_payment_terms').value= data.payment_terms || '';
    document.getElementById('modal_status').value       = data.status        || 'active';
    document.getElementById('modal_rating').value       = data.rating        || '3';
    document.getElementById('modal_notes').value        = data.notes         || '';
}

<?php if ($autoOpenModal && $editSupplier): ?>
document.addEventListener('DOMContentLoaded', function () {
    fillEditModal(editData);
    var modal = new bootstrap.Modal(document.getElementById('supplierModal'));
    modal.show();
});
<?php endif; ?>
</script>

<?php require_once '../includes/admin-footer.php'; ?>
