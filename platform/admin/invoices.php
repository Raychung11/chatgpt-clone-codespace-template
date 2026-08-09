<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();
$pageTitle = 'Invoices';

$msg = '';

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $userId    = (int)$_POST['user_id'];
        $issueDate = $_POST['issue_date'];
        $dueDate   = $_POST['due_date'];
        $taxRate   = (float)($_POST['tax_rate'] ?? 0);
        $notes     = htmlspecialchars(trim($_POST['notes'] ?? ''));

        // Line items
        $descs  = $_POST['item_desc']  ?? [];
        $qtys   = $_POST['item_qty']   ?? [];
        $prices = $_POST['item_price'] ?? [];

        $subtotal = 0;
        $items = [];
        foreach ($descs as $i => $desc) {
            $desc  = htmlspecialchars(trim($desc));
            $qty   = (float)($qtys[$i] ?? 1);
            $price = (float)($prices[$i] ?? 0);
            if ($desc === '' || $price <= 0) continue;
            $amount    = $qty * $price;
            $subtotal += $amount;
            $items[]   = compact('desc','qty','price','amount');
        }

        if ($userId && !empty($items)) {
            $taxAmount = $subtotal * ($taxRate / 100);
            $total     = $subtotal + $taxAmount;

            // Generate invoice number: INV-YYYYMM-XXXX
            $count  = (int)(DB::fetch('SELECT COUNT(*)+1 AS n FROM invoices')['n']);
            $invNum = 'INV-' . date('Ym') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

            $invId = DB::insert('invoices', [
                'invoice_number' => $invNum,
                'user_id'        => $userId,
                'status'         => 'draft',
                'issue_date'     => $issueDate,
                'due_date'       => $dueDate,
                'subtotal'       => $subtotal,
                'tax_rate'       => $taxRate,
                'tax_amount'     => $taxAmount,
                'total'          => $total,
                'currency'       => 'USD',
                'notes'          => $notes,
            ]);

            foreach ($items as $item) {
                DB::insert('invoice_items', [
                    'invoice_id'  => $invId,
                    'description' => $item['desc'],
                    'quantity'    => $item['qty'],
                    'unit_price'  => $item['price'],
                    'amount'      => $item['amount'],
                ]);
            }
            $msg = '<div class="alert alert-success py-2">Invoice '.$invNum.' created successfully.</div>';
        } else {
            $msg = '<div class="alert alert-danger py-2">Please select a customer and add at least one line item.</div>';
        }

    } elseif ($action === 'status') {
        $id     = (int)$_POST['id'];
        $status = $_POST['status'];
        $allowed = ['draft','sent','paid','overdue','cancelled'];
        if (in_array($status, $allowed)) {
            $extra = $status === 'paid' ? ['paid_at' => date('Y-m-d H:i:s')] : [];
            DB::update('invoices', array_merge(['status' => $status], $extra), 'id=?', [$id]);
        }
        header('Location: /admin/invoices.php');
        exit;

    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        DB::query('DELETE FROM invoices WHERE id=?', [$id]);
        header('Location: /admin/invoices.php');
        exit;
    }
}

// ── Fetch invoices ────────────────────────────────────────────────────────────
$statusFilter = trim($_GET['status'] ?? '');
$search       = trim($_GET['q'] ?? '');

$invoices = DB::fetchAll(
    "SELECT i.*, u.name AS customer_name, u.email AS customer_email, u.company
     FROM invoices i
     JOIN users u ON i.user_id=u.id
     WHERE (? = '' OR i.status=?)
       AND (? = '' OR u.name LIKE ? OR u.email LIKE ? OR i.invoice_number LIKE ?)
     ORDER BY i.created_at DESC",
    [$statusFilter, $statusFilter, $search, "%$search%", "%$search%", "%$search%"]
);

// Customers list for the create modal
$customers = DB::fetchAll("SELECT id, name, email, company FROM users WHERE role='customer' ORDER BY name");

// Summary counts
$statusCounts = DB::fetchAll("SELECT status, COUNT(*) AS n, COALESCE(SUM(total),0) AS tot FROM invoices GROUP BY status");
$countByStatus = [];
foreach ($statusCounts as $sc) $countByStatus[$sc['status']] = $sc;

require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white fw-bold mb-0">Invoices
            <span class="text-muted fs-6">(<?= count($invoices) ?>)</span>
        </h4>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="bi bi-plus-circle me-1"></i>New Invoice
        </button>
    </div>

    <?= $msg ?>

    <!-- Status filter pills -->
    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php
        $pills = [''=>'All', 'draft'=>'Draft', 'sent'=>'Sent', 'paid'=>'Paid', 'overdue'=>'Overdue', 'cancelled'=>'Cancelled'];
        $pillClasses = ['draft'=>'secondary','sent'=>'primary','paid'=>'success','overdue'=>'danger','cancelled'=>'dark',''=>'light'];
        foreach ($pills as $val => $label):
            $cnt = $val === '' ? count($invoices) : ($countByStatus[$val]['n'] ?? 0);
        ?>
        <a href="?status=<?= $val ?>&q=<?= urlencode($search) ?>"
           class="btn btn-sm <?= $statusFilter===$val ? 'btn-'.$pillClasses[$val] : 'btn-outline-secondary' ?>">
            <?= $label ?> <span class="badge bg-white text-dark ms-1"><?= $cnt ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Search -->
    <form method="GET" class="mb-4">
        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
        <div class="input-group" style="max-width:400px">
            <input type="search" name="q" value="<?= htmlspecialchars($search) ?>"
                   class="form-control bg-dark border-secondary text-white" placeholder="Search invoices...">
            <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
        </div>
    </form>

    <!-- Table -->
    <div class="admin-card rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
                <thead class="border-bottom border-secondary">
                    <tr class="text-muted small">
                        <th>Invoice #</th>
                        <th>Customer</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv):
                        $isOverdue = $inv['status']==='sent' && strtotime($inv['due_date']) < time();
                        $badgeMap = ['draft'=>'secondary','sent'=>'primary','paid'=>'success','overdue'=>'danger','cancelled'=>'dark'];
                        $statusDisplay = $isOverdue ? 'overdue' : $inv['status'];
                        $badgeClass = $badgeMap[$statusDisplay] ?? 'secondary';
                    ?>
                    <tr>
                        <td class="text-white small fw-semibold"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                        <td>
                            <div class="text-white small"><?= htmlspecialchars($inv['customer_name']) ?></div>
                            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($inv['customer_email']) ?></div>
                        </td>
                        <td class="text-muted small"><?= date('d M Y', strtotime($inv['issue_date'])) ?></td>
                        <td class="<?= $isOverdue ? 'text-danger' : 'text-muted' ?> small">
                            <?= date('d M Y', strtotime($inv['due_date'])) ?>
                        </td>
                        <td class="text-white fw-semibold small"><?= APP_CURRENCY ?><?= number_format($inv['total'],2) ?></td>
                        <td><span class="badge bg-<?= $badgeClass ?>" style="font-size:10px"><?= ucfirst($statusDisplay) ?></span></td>
                        <td>
                            <div class="d-flex gap-1">
                                <!-- Status change dropdown -->
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" title="Change status">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                                        <?php foreach (['draft','sent','paid','overdue','cancelled'] as $s): ?>
                                        <li>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="status">
                                                <input type="hidden" name="id" value="<?= $inv['id'] ?>">
                                                <input type="hidden" name="status" value="<?= $s ?>">
                                                <button type="submit" class="dropdown-item <?= $inv['status']===$s ? 'active' : '' ?>">
                                                    <?= ucfirst($s) ?>
                                                </button>
                                            </form>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <!-- Delete -->
                                <form method="POST" onsubmit="return confirm('Delete invoice <?= htmlspecialchars($inv['invoice_number']) ?>?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $inv['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($invoices)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No invoices found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Invoice Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white">New Invoice</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-muted small">Customer *</label>
                            <select name="user_id" class="form-select bg-dark border-secondary text-white" required>
                                <option value="">— Select customer —</option>
                                <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>">
                                    <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['email']) ?>)
                                    <?= $c['company'] ? '— '.htmlspecialchars($c['company']) : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Issue Date *</label>
                            <input type="date" name="issue_date" value="<?= date('Y-m-d') ?>"
                                   class="form-control bg-dark border-secondary text-white" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Due Date *</label>
                            <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>"
                                   class="form-control bg-dark border-secondary text-white" required>
                        </div>

                        <!-- Line items -->
                        <div class="col-12">
                            <label class="form-label text-muted small">Line Items *</label>
                            <div id="lineItems">
                                <div class="row g-2 mb-2 line-item">
                                    <div class="col-6">
                                        <input type="text" name="item_desc[]" placeholder="Description"
                                               class="form-control form-control-sm bg-dark border-secondary text-white">
                                    </div>
                                    <div class="col-2">
                                        <input type="number" name="item_qty[]" value="1" min="0.01" step="0.01" placeholder="Qty"
                                               class="form-control form-control-sm bg-dark border-secondary text-white item-qty">
                                    </div>
                                    <div class="col-3">
                                        <input type="number" name="item_price[]" step="0.01" placeholder="Unit price"
                                               class="form-control form-control-sm bg-dark border-secondary text-white item-price">
                                    </div>
                                    <div class="col-1 d-flex align-items-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-line" title="Remove"><i class="bi bi-x"></i></button>
                                    </div>
                                </div>
                            </div>
                            <button type="button" id="addLine" class="btn btn-sm btn-outline-secondary mt-1">
                                <i class="bi bi-plus me-1"></i>Add Line
                            </button>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-muted small">Tax Rate (%)</label>
                            <input type="number" name="tax_rate" id="taxRate" value="0" min="0" max="100" step="0.01"
                                   class="form-control bg-dark border-secondary text-white">
                        </div>
                        <div class="col-md-8 d-flex align-items-end">
                            <div class="text-muted small">
                                Subtotal: <span id="previewSubtotal" class="text-white">$0.00</span> &nbsp;
                                Tax: <span id="previewTax" class="text-white">$0.00</span> &nbsp;
                                <strong>Total: <span id="previewTotal" class="text-primary">$0.00</span></strong>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Notes</label>
                            <textarea name="notes" rows="2" class="form-control bg-dark border-secondary text-white"
                                      placeholder="Payment terms, bank details, etc."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Line item add/remove
document.getElementById('addLine').addEventListener('click', function(){
    const tmpl = document.querySelector('.line-item').cloneNode(true);
    tmpl.querySelectorAll('input').forEach(i => i.value = i.defaultValue || '');
    document.getElementById('lineItems').appendChild(tmpl);
    tmpl.querySelector('.remove-line').addEventListener('click', () => { tmpl.remove(); recalc(); });
    tmpl.querySelectorAll('.item-qty,.item-price').forEach(i => i.addEventListener('input', recalc));
});
document.querySelectorAll('.remove-line').forEach(b => b.addEventListener('click', function(){
    this.closest('.line-item').remove(); recalc();
}));
document.querySelectorAll('.item-qty,.item-price').forEach(i => i.addEventListener('input', recalc));
document.getElementById('taxRate').addEventListener('input', recalc);

function recalc(){
    let sub = 0;
    document.querySelectorAll('.line-item').forEach(row => {
        const q = parseFloat(row.querySelector('.item-qty').value)||0;
        const p = parseFloat(row.querySelector('.item-price').value)||0;
        sub += q*p;
    });
    const rate = parseFloat(document.getElementById('taxRate').value)||0;
    const tax  = sub * rate/100;
    document.getElementById('previewSubtotal').textContent = '$'+sub.toFixed(2);
    document.getElementById('previewTax').textContent      = '$'+tax.toFixed(2);
    document.getElementById('previewTotal').textContent    = '$'+(sub+tax).toFixed(2);
}
</script>

<?php require_once '../includes/admin-footer.php'; ?>
