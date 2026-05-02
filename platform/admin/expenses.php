<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();
$pageTitle = 'Expenses';

$msg = '';

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id   = (int)($_POST['id'] ?? 0);
        $data = [
            'category'     => $_POST['category'],
            'description'  => htmlspecialchars(trim($_POST['description'])),
            'amount'       => (float)$_POST['amount'],
            'expense_date' => $_POST['expense_date'],
            'vendor'       => htmlspecialchars(trim($_POST['vendor'] ?? '')),
            'reference'    => htmlspecialchars(trim($_POST['reference'] ?? '')),
            'notes'        => htmlspecialchars(trim($_POST['notes'] ?? '')),
            'created_by'   => Auth::id(),
        ];

        if ($id > 0) {
            DB::update('expenses', $data, 'id=?', [$id]);
            $msg = '<div class="alert alert-success py-2">Expense updated.</div>';
        } else {
            DB::insert('expenses', $data);
            $msg = '<div class="alert alert-success py-2">Expense recorded.</div>';
        }

    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        DB::query('DELETE FROM expenses WHERE id=?', [$id]);
        header('Location: /admin/expenses.php');
        exit;
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$catFilter   = trim($_GET['cat']   ?? '');
$monthFilter = trim($_GET['month'] ?? '');
$yearFilter  = (int)($_GET['year'] ?? date('Y'));

$expenses = DB::fetchAll(
    "SELECT e.*, u.name AS created_by_name FROM expenses e
     LEFT JOIN users u ON e.created_by=u.id
     WHERE (? = '' OR e.category=?)
       AND (? = '' OR MONTH(e.expense_date)=?)
       AND YEAR(e.expense_date)=?
     ORDER BY e.expense_date DESC, e.id DESC",
    [$catFilter, $catFilter, $monthFilter, $monthFilter, $yearFilter]
);

$totalShown = array_sum(array_column($expenses, 'amount'));

$categories = ['software','hosting','marketing','salaries','operations','tax','other'];

// Edit mode
$editExp = null;
if (isset($_GET['edit'])) {
    $editExp = DB::fetch('SELECT * FROM expenses WHERE id=?', [(int)$_GET['edit']]);
}

// Years available
$years = DB::fetchAll('SELECT DISTINCT YEAR(expense_date) AS y FROM expenses ORDER BY y DESC');
if (empty($years)) $years = [['y' => date('Y')]];

require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white fw-bold mb-0">
            Expenses
            <span class="text-muted fs-6">&nbsp;<?= APP_CURRENCY ?><?= number_format($totalShown,2) ?> shown</span>
        </h4>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#expenseModal">
            <i class="bi bi-plus-circle me-1"></i>Add Expense
        </button>
    </div>

    <?= $msg ?>

    <!-- Filters -->
    <form method="GET" class="row g-2 mb-4">
        <div class="col-auto">
            <select name="cat" class="form-select form-select-sm bg-dark border-secondary text-white">
                <option value="">All Categories</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c ?>" <?= $catFilter===$c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <select name="month" class="form-select form-select-sm bg-dark border-secondary text-white">
                <option value="">All Months</option>
                <?php for ($i=1;$i<=12;$i++): ?>
                <option value="<?= $i ?>" <?= $monthFilter==$i ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$i,1)) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-auto">
            <select name="year" class="form-select form-select-sm bg-dark border-secondary text-white">
                <?php foreach ($years as $y): ?>
                <option value="<?= $y['y'] ?>" <?= $y['y']==$yearFilter ? 'selected' : '' ?>><?= $y['y'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-sm btn-primary">Filter</button>
            <a href="/admin/expenses.php" class="btn btn-sm btn-outline-secondary">Clear</a>
        </div>
    </form>

    <!-- Table -->
    <div class="admin-card rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
                <thead class="border-bottom border-secondary">
                    <tr class="text-muted small">
                        <th>Date</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Vendor</th>
                        <th>Ref</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $exp):
                        $catColors = ['software'=>'primary','hosting'=>'info','marketing'=>'warning',
                                      'salaries'=>'success','operations'=>'secondary','tax'=>'danger','other'=>'dark'];
                        $cc = $catColors[$exp['category']] ?? 'secondary';
                    ?>
                    <tr>
                        <td class="text-muted small"><?= date('d M Y', strtotime($exp['expense_date'])) ?></td>
                        <td>
                            <div class="text-white small"><?= htmlspecialchars($exp['description']) ?></div>
                            <?php if ($exp['notes']): ?>
                            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($exp['notes']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-<?= $cc ?>" style="font-size:10px"><?= ucfirst($exp['category']) ?></span></td>
                        <td class="text-muted small"><?= htmlspecialchars($exp['vendor'] ?: '—') ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($exp['reference'] ?: '—') ?></td>
                        <td class="text-white fw-semibold small"><?= APP_CURRENCY ?><?= number_format($exp['amount'],2) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="?edit=<?= $exp['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" onsubmit="return confirm('Delete this expense?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $exp['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($expenses)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No expenses found.</td></tr>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($expenses)): ?>
                <tfoot class="border-top border-secondary">
                    <tr>
                        <td colspan="5" class="text-muted small text-end pe-3 fw-semibold">Total</td>
                        <td class="text-white fw-bold"><?= APP_CURRENCY ?><?= number_format($totalShown,2) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<!-- Add / Edit Expense Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white"><?= $editExp ? 'Edit Expense' : 'Add Expense' ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $editExp['id'] ?? '' ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-muted small">Description *</label>
                            <input type="text" name="description"
                                   value="<?= htmlspecialchars($editExp['description'] ?? '') ?>"
                                   class="form-control bg-dark border-secondary text-white" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Category *</label>
                            <select name="category" class="form-select bg-dark border-secondary text-white" required>
                                <?php foreach ($categories as $c): ?>
                                <option value="<?= $c ?>" <?= ($editExp['category'] ?? '') === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Amount (<?= APP_CURRENCY ?>) *</label>
                            <input type="number" name="amount" step="0.01" min="0.01"
                                   value="<?= $editExp['amount'] ?? '' ?>"
                                   class="form-control bg-dark border-secondary text-white" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Date *</label>
                            <input type="date" name="expense_date"
                                   value="<?= $editExp['expense_date'] ?? date('Y-m-d') ?>"
                                   class="form-control bg-dark border-secondary text-white" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Vendor</label>
                            <input type="text" name="vendor"
                                   value="<?= htmlspecialchars($editExp['vendor'] ?? '') ?>"
                                   class="form-control bg-dark border-secondary text-white" placeholder="e.g. AWS, Google">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Reference / Invoice #</label>
                            <input type="text" name="reference"
                                   value="<?= htmlspecialchars($editExp['reference'] ?? '') ?>"
                                   class="form-control bg-dark border-secondary text-white">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Notes</label>
                            <textarea name="notes" rows="2"
                                      class="form-control bg-dark border-secondary text-white"><?= htmlspecialchars($editExp['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><?= $editExp ? 'Update' : 'Save' ?> Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
if ($editExp) {
    $extraScripts = '<script>new bootstrap.Modal(document.getElementById("expenseModal")).show();</script>';
}
require_once '../includes/admin-footer.php';
?>
