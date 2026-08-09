<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();
$pageTitle = 'Payroll';

$msg = '';

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id    = (int)($_POST['id'] ?? 0);
        $gross = (float)$_POST['gross_amount'];
        $deductions = (float)($_POST['deductions'] ?? 0);
        $net   = $gross - $deductions;

        $data = [
            'employee_id'      => (int)$_POST['employee_id'],
            'pay_period_start' => $_POST['pay_period_start'],
            'pay_period_end'   => $_POST['pay_period_end'],
            'gross_amount'     => $gross,
            'deductions'       => $deductions,
            'net_amount'       => $net,
            'currency'         => 'USD',
            'status'           => $_POST['status'],
            'payment_date'     => $_POST['payment_date'] ?: null,
            'reference'        => htmlspecialchars(trim($_POST['reference'] ?? '')),
            'notes'            => htmlspecialchars(trim($_POST['notes'] ?? '')),
            'created_by'       => Auth::id(),
        ];

        if ($id > 0) {
            DB::update('payroll', $data, 'id=?', [$id]);
            $msg = '<div class="alert alert-success py-2">Payroll record updated.</div>';
        } else {
            DB::insert('payroll', $data);
            $msg = '<div class="alert alert-success py-2">Payroll record created.</div>';
        }

    } elseif ($action === 'bulk_create') {
        // Generate payroll for all active employees for a given period
        $periodStart = $_POST['period_start'];
        $periodEnd   = $_POST['period_end'];
        $payDate     = $_POST['payment_date'] ?: null;
        $activeEmps  = DB::fetchAll("SELECT * FROM employees WHERE status='active' AND salary IS NOT NULL AND salary > 0");
        $created = 0;
        foreach ($activeEmps as $emp) {
            // Skip if record already exists for this period
            $exists = DB::fetch(
                'SELECT id FROM payroll WHERE employee_id=? AND pay_period_start=? AND pay_period_end=?',
                [$emp['id'], $periodStart, $periodEnd]
            );
            if ($exists) continue;

            $gross = (float)$emp['salary'];
            DB::insert('payroll', [
                'employee_id'      => $emp['id'],
                'pay_period_start' => $periodStart,
                'pay_period_end'   => $periodEnd,
                'gross_amount'     => $gross,
                'deductions'       => 0,
                'net_amount'       => $gross,
                'currency'         => $emp['salary_currency'] ?? 'USD',
                'status'           => 'draft',
                'payment_date'     => $payDate,
                'created_by'       => Auth::id(),
            ]);
            $created++;
        }
        $msg = '<div class="alert alert-success py-2">Generated '.$created.' payroll record'.($created!=1?'s':'').'.</div>';

    } elseif ($action === 'mark_paid') {
        $id = (int)$_POST['id'];
        DB::update('payroll', ['status'=>'paid','payment_date'=>date('Y-m-d')], 'id=?', [$id]);
        header('Location: /admin/payroll.php');
        exit;

    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        DB::query('DELETE FROM payroll WHERE id=?', [$id]);
        header('Location: /admin/payroll.php');
        exit;
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$statusFilter = trim($_GET['status'] ?? '');
$empFilter    = (int)($_GET['emp'] ?? 0);
$yearFilter   = (int)($_GET['year'] ?? date('Y'));
$monthFilter  = (int)($_GET['month'] ?? date('n'));

$records = DB::fetchAll(
    "SELECT pr.*, CONCAT(e.first_name,' ',e.last_name) AS employee_name,
            e.department, e.job_title
     FROM payroll pr
     JOIN employees e ON pr.employee_id=e.id
     WHERE (? = '' OR pr.status=?)
       AND (? = 0  OR pr.employee_id=?)
       AND YEAR(pr.pay_period_start)=?
       AND MONTH(pr.pay_period_start)=?
     ORDER BY pr.pay_period_start DESC, e.last_name",
    [$statusFilter,$statusFilter, $empFilter,$empFilter, $yearFilter, $monthFilter]
);

$totalGross = array_sum(array_column($records, 'gross_amount'));
$totalDeductions = array_sum(array_column($records, 'deductions'));
$totalNet   = array_sum(array_column($records, 'net_amount'));

$employees = DB::fetchAll("SELECT id, first_name, last_name, department, salary FROM employees WHERE status != 'terminated' ORDER BY last_name");

// Edit mode
$editRecord = null;
if (isset($_GET['edit'])) {
    $editRecord = DB::fetch('SELECT * FROM payroll WHERE id=?', [(int)$_GET['edit']]);
}

$statusOpts = ['draft'=>'Draft','processed'=>'Processed','paid'=>'Paid'];
$statusColors = ['draft'=>'secondary','processed'=>'primary','paid'=>'success'];

$years = DB::fetchAll('SELECT DISTINCT YEAR(pay_period_start) AS y FROM payroll ORDER BY y DESC');
if (empty($years)) $years = [['y' => date('Y')]];

require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white fw-bold mb-0">Payroll
            <span class="text-muted fs-6">&nbsp;<?= date('F', mktime(0,0,0,$monthFilter,1)) ?> <?= $yearFilter ?></span>
        </h4>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#bulkModal">
                <i class="bi bi-lightning me-1"></i>Bulk Generate
            </button>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#payrollModal">
                <i class="bi bi-plus-circle me-1"></i>Add Record
            </button>
        </div>
    </div>

    <?= $msg ?>

    <!-- Period + filters -->
    <form method="GET" class="row g-2 mb-4">
        <div class="col-auto">
            <select name="year" class="form-select form-select-sm bg-dark border-secondary text-white">
                <?php foreach ($years as $y): ?>
                <option value="<?= $y['y'] ?>" <?= $y['y']==$yearFilter?'selected':'' ?>><?= $y['y'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <select name="month" class="form-select form-select-sm bg-dark border-secondary text-white">
                <?php for ($i=1;$i<=12;$i++): ?>
                <option value="<?= $i ?>" <?= $i==$monthFilter?'selected':'' ?>><?= date('F', mktime(0,0,0,$i,1)) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-auto">
            <select name="status" class="form-select form-select-sm bg-dark border-secondary text-white">
                <option value="">All Statuses</option>
                <?php foreach ($statusOpts as $val => $label): ?>
                <option value="<?= $val ?>" <?= $statusFilter===$val?'selected':'' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <select name="emp" class="form-select form-select-sm bg-dark border-secondary text-white">
                <option value="0">All Employees</option>
                <?php foreach ($employees as $e): ?>
                <option value="<?= $e['id'] ?>" <?= $empFilter==$e['id']?'selected':'' ?>>
                    <?= htmlspecialchars($e['first_name'].' '.$e['last_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-sm btn-primary">Filter</button>
        </div>
    </form>

    <!-- Summary cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="admin-card rounded-4 p-3 text-center">
                <div class="text-muted small">Gross</div>
                <div class="text-white fw-bold fs-5"><?= APP_CURRENCY ?><?= number_format($totalGross,2) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="admin-card rounded-4 p-3 text-center">
                <div class="text-muted small">Deductions</div>
                <div class="text-danger fw-bold fs-5">−<?= APP_CURRENCY ?><?= number_format($totalDeductions,2) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="admin-card rounded-4 p-3 text-center">
                <div class="text-muted small">Net Payroll</div>
                <div class="text-success fw-bold fs-5"><?= APP_CURRENCY ?><?= number_format($totalNet,2) ?></div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="admin-card rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
                <thead class="border-bottom border-secondary">
                    <tr class="text-muted small">
                        <th>Employee</th>
                        <th>Pay Period</th>
                        <th>Gross</th>
                        <th>Deductions</th>
                        <th>Net</th>
                        <th>Payment Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $pr):
                        $sc = $statusColors[$pr['status']] ?? 'secondary';
                    ?>
                    <tr>
                        <td>
                            <div class="text-white small fw-semibold"><?= htmlspecialchars($pr['employee_name']) ?></div>
                            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($pr['job_title'] ?: ucfirst($pr['department'])) ?></div>
                        </td>
                        <td class="text-muted small">
                            <?= date('d M', strtotime($pr['pay_period_start'])) ?> –
                            <?= date('d M Y', strtotime($pr['pay_period_end'])) ?>
                        </td>
                        <td class="text-white small"><?= APP_CURRENCY ?><?= number_format($pr['gross_amount'],2) ?></td>
                        <td class="text-danger small">
                            <?= $pr['deductions'] > 0 ? '−'.APP_CURRENCY.number_format($pr['deductions'],2) : '<span class="text-muted">—</span>' ?>
                        </td>
                        <td class="text-white fw-semibold small"><?= APP_CURRENCY ?><?= number_format($pr['net_amount'],2) ?></td>
                        <td class="text-muted small">
                            <?= $pr['payment_date'] ? date('d M Y', strtotime($pr['payment_date'])) : '—' ?>
                        </td>
                        <td><span class="badge bg-<?= $sc ?>" style="font-size:10px"><?= ucfirst($pr['status']) ?></span></td>
                        <td>
                            <div class="d-flex gap-1">
                                <?php if ($pr['status'] !== 'paid'): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="mark_paid">
                                    <input type="hidden" name="id" value="<?= $pr['id'] ?>">
                                    <button class="btn btn-sm btn-success" title="Mark Paid">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <a href="?edit=<?= $pr['id'] ?>&year=<?= $yearFilter ?>&month=<?= $monthFilter ?>"
                                   class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" onsubmit="return confirm('Delete this payroll record?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $pr['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            No payroll records for this period.
                            <button class="btn btn-sm btn-outline-primary ms-2" data-bs-toggle="modal" data-bs-target="#bulkModal">
                                Generate from employee salaries
                            </button>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($records)): ?>
                <tfoot class="border-top border-secondary">
                    <tr class="text-muted small fw-semibold">
                        <td colspan="2" class="text-end pe-3">Totals (<?= count($records) ?> records)</td>
                        <td class="text-white"><?= APP_CURRENCY ?><?= number_format($totalGross,2) ?></td>
                        <td class="text-danger">−<?= APP_CURRENCY ?><?= number_format($totalDeductions,2) ?></td>
                        <td class="text-success fw-bold"><?= APP_CURRENCY ?><?= number_format($totalNet,2) ?></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<!-- Bulk Generate Modal -->
<div class="modal fade" id="bulkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white">Bulk Generate Payroll</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="bulk_create">
                <div class="modal-body">
                    <p class="text-muted small">Creates draft payroll records for all active employees with a salary set. Existing records for the same period are skipped.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Period Start *</label>
                            <input type="date" name="period_start"
                                   value="<?= date('Y-m-01') ?>"
                                   class="form-control bg-dark border-secondary text-white" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Period End *</label>
                            <input type="date" name="period_end"
                                   value="<?= date('Y-m-t') ?>"
                                   class="form-control bg-dark border-secondary text-white" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Payment Date</label>
                            <input type="date" name="payment_date"
                                   value="<?= date('Y-m-t') ?>"
                                   class="form-control bg-dark border-secondary text-white">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Generate Drafts</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add / Edit Payroll Modal -->
<div class="modal fade" id="payrollModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white"><?= $editRecord ? 'Edit Payroll Record' : 'Add Payroll Record' ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $editRecord['id'] ?? '' ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-muted small">Employee *</label>
                            <select name="employee_id" class="form-select bg-dark border-secondary text-white" required
                                    id="empSelect">
                                <option value="">— Select —</option>
                                <?php foreach ($employees as $e): ?>
                                <option value="<?= $e['id'] ?>"
                                        data-salary="<?= $e['salary'] ?? 0 ?>"
                                        <?= ($editRecord['employee_id'] ?? '') == $e['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($e['first_name'].' '.$e['last_name']) ?>
                                    <?= $e['salary'] ? ' — '.APP_CURRENCY.number_format($e['salary'],0) : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Period Start *</label>
                            <input type="date" name="pay_period_start"
                                   value="<?= $editRecord['pay_period_start'] ?? date('Y-m-01') ?>"
                                   class="form-control bg-dark border-secondary text-white" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Period End *</label>
                            <input type="date" name="pay_period_end"
                                   value="<?= $editRecord['pay_period_end'] ?? date('Y-m-t') ?>"
                                   class="form-control bg-dark border-secondary text-white" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Gross (<?= APP_CURRENCY ?>) *</label>
                            <input type="number" name="gross_amount" id="grossInput" step="0.01" min="0"
                                   value="<?= $editRecord['gross_amount'] ?? '' ?>"
                                   class="form-control bg-dark border-secondary text-white" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Deductions (<?= APP_CURRENCY ?>)</label>
                            <input type="number" name="deductions" id="deductInput" step="0.01" min="0" value="<?= $editRecord['deductions'] ?? 0 ?>"
                                   class="form-control bg-dark border-secondary text-white">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Net</label>
                            <div class="form-control bg-dark border-secondary text-success fw-semibold" id="netDisplay">
                                <?= APP_CURRENCY ?><?= isset($editRecord) ? number_format($editRecord['net_amount'],2) : '0.00' ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Status</label>
                            <select name="status" class="form-select bg-dark border-secondary text-white">
                                <?php foreach ($statusOpts as $val => $label): ?>
                                <option value="<?= $val ?>" <?= ($editRecord['status'] ?? 'draft') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Payment Date</label>
                            <input type="date" name="payment_date"
                                   value="<?= $editRecord['payment_date'] ?? '' ?>"
                                   class="form-control bg-dark border-secondary text-white">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Reference</label>
                            <input type="text" name="reference" value="<?= htmlspecialchars($editRecord['reference'] ?? '') ?>"
                                   class="form-control bg-dark border-secondary text-white">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Notes</label>
                            <textarea name="notes" rows="2" class="form-control bg-dark border-secondary text-white"><?= htmlspecialchars($editRecord['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><?= $editRecord ? 'Update' : 'Save' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Auto-fill gross from employee salary
document.getElementById('empSelect').addEventListener('change', function(){
    const salary = this.options[this.selectedIndex]?.dataset?.salary;
    if (salary && salary > 0) document.getElementById('grossInput').value = parseFloat(salary).toFixed(2);
    recalcNet();
});
document.getElementById('grossInput').addEventListener('input', recalcNet);
document.getElementById('deductInput').addEventListener('input', recalcNet);
function recalcNet(){
    const g = parseFloat(document.getElementById('grossInput').value)||0;
    const d = parseFloat(document.getElementById('deductInput').value)||0;
    document.getElementById('netDisplay').textContent = '$'+(g-d).toFixed(2);
}
</script>

<?php
if ($editRecord) {
    $extraScripts = '<script>new bootstrap.Modal(document.getElementById("payrollModal")).show();</script>';
}
require_once '../includes/admin-footer.php';
?>
