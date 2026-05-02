<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();
$pageTitle = 'Employees';

$msg = '';

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id   = (int)($_POST['id'] ?? 0);
        $data = [
            'first_name'       => htmlspecialchars(trim($_POST['first_name'])),
            'last_name'        => htmlspecialchars(trim($_POST['last_name'])),
            'email'            => htmlspecialchars(trim($_POST['email'])),
            'phone'            => htmlspecialchars(trim($_POST['phone'] ?? '')),
            'department'       => $_POST['department'],
            'job_title'        => htmlspecialchars(trim($_POST['job_title'] ?? '')),
            'employment_type'  => $_POST['employment_type'],
            'status'           => $_POST['status'],
            'start_date'       => $_POST['start_date'],
            'end_date'         => $_POST['end_date'] ?: null,
            'salary'           => $_POST['salary'] !== '' ? (float)$_POST['salary'] : null,
            'pay_cycle'        => $_POST['pay_cycle'],
            'manager_id'       => $_POST['manager_id'] ? (int)$_POST['manager_id'] : null,
            'notes'            => htmlspecialchars(trim($_POST['notes'] ?? '')),
        ];

        if ($id > 0) {
            DB::update('employees', $data, 'id=?', [$id]);
            $msg = '<div class="alert alert-success py-2">Employee updated.</div>';
        } else {
            DB::insert('employees', $data);
            $msg = '<div class="alert alert-success py-2">Employee added.</div>';
        }

    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        DB::query('DELETE FROM employees WHERE id=?', [$id]);
        header('Location: /admin/employees.php');
        exit;
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$deptFilter   = trim($_GET['dept']   ?? '');
$statusFilter = trim($_GET['status'] ?? 'active');
$search       = trim($_GET['q']      ?? '');

$employees = DB::fetchAll(
    "SELECT e.*, CONCAT(m.first_name,' ',m.last_name) AS manager_name
     FROM employees e
     LEFT JOIN employees m ON e.manager_id=m.id
     WHERE (? = '' OR e.department=?)
       AND (? = '' OR e.status=?)
       AND (? = '' OR e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ? OR e.job_title LIKE ?)
     ORDER BY e.last_name, e.first_name",
    [$deptFilter,$deptFilter, $statusFilter,$statusFilter, $search,"%$search%","%$search%","%$search%","%$search%"]
);

// Edit mode
$editEmp = null;
if (isset($_GET['edit'])) {
    $editEmp = DB::fetch('SELECT * FROM employees WHERE id=?', [(int)$_GET['edit']]);
}

$allManagers  = DB::fetchAll("SELECT id, first_name, last_name, job_title FROM employees WHERE status='active' ORDER BY last_name");
$departments  = ['engineering','marketing','sales','support','operations','finance','hr','management'];
$empTypes     = ['full_time'=>'Full Time','part_time'=>'Part Time','contractor'=>'Contractor','intern'=>'Intern'];
$payCycles    = ['monthly'=>'Monthly','biweekly'=>'Bi-weekly','weekly'=>'Weekly'];
$statusOpts   = ['active'=>'Active','on_leave'=>'On Leave','terminated'=>'Terminated'];

$deptIcons = [
    'engineering'=>'bi-code-slash','marketing'=>'bi-megaphone','sales'=>'bi-bag',
    'support'=>'bi-headset','operations'=>'bi-gear','finance'=>'bi-currency-dollar',
    'hr'=>'bi-people','management'=>'bi-briefcase'
];

require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white fw-bold mb-0">Employees
            <span class="text-muted fs-6">(<?= count($employees) ?>)</span>
        </h4>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#empModal">
            <i class="bi bi-person-plus me-1"></i>Add Employee
        </button>
    </div>

    <?= $msg ?>

    <!-- Filters -->
    <form method="GET" class="row g-2 mb-4">
        <div class="col-auto">
            <select name="dept" class="form-select form-select-sm bg-dark border-secondary text-white">
                <option value="">All Departments</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= $d ?>" <?= $deptFilter===$d ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <select name="status" class="form-select form-select-sm bg-dark border-secondary text-white">
                <option value="">All Statuses</option>
                <?php foreach ($statusOpts as $val => $label): ?>
                <option value="<?= $val ?>" <?= $statusFilter===$val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <div class="input-group input-group-sm">
                <input type="search" name="q" value="<?= htmlspecialchars($search) ?>"
                       class="form-control bg-dark border-secondary text-white" placeholder="Search...">
                <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
            </div>
        </div>
        <div class="col-auto">
            <a href="/admin/employees.php" class="btn btn-sm btn-outline-secondary">Clear</a>
        </div>
    </form>

    <!-- Table -->
    <div class="admin-card rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
                <thead class="border-bottom border-secondary">
                    <tr class="text-muted small">
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Type</th>
                        <th>Salary</th>
                        <th>Manager</th>
                        <th>Start Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp):
                        $statusColors = ['active'=>'success','on_leave'=>'warning','terminated'=>'danger'];
                        $sc = $statusColors[$emp['status']] ?? 'secondary';
                        $icon = $deptIcons[$emp['department']] ?? 'bi-building';
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-initials sm">
                                    <?= strtoupper(substr($emp['first_name'],0,1).substr($emp['last_name'],0,1)) ?>
                                </div>
                                <div>
                                    <div class="text-white small fw-semibold">
                                        <?= htmlspecialchars($emp['first_name'].' '.$emp['last_name']) ?>
                                    </div>
                                    <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($emp['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <i class="bi <?= $icon ?> text-primary me-1"></i>
                            <span class="text-muted small"><?= ucfirst($emp['department']) ?></span>
                            <?php if ($emp['job_title']): ?>
                            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($emp['job_title']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= $empTypes[$emp['employment_type']] ?? ucfirst($emp['employment_type']) ?></td>
                        <td class="text-white small">
                            <?= $emp['salary'] ? APP_CURRENCY.number_format($emp['salary'],0).'<span class="text-muted">/'.$emp['pay_cycle'][0].'</span>' : '<span class="text-muted">—</span>' ?>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars($emp['manager_name'] ?: '—') ?></td>
                        <td class="text-muted small"><?= date('d M Y', strtotime($emp['start_date'])) ?></td>
                        <td><span class="badge bg-<?= $sc ?>" style="font-size:10px"><?= ucfirst(str_replace('_',' ',$emp['status'])) ?></span></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="?edit=<?= $emp['id'] ?>&dept=<?= urlencode($deptFilter) ?>&status=<?= urlencode($statusFilter) ?>&q=<?= urlencode($search) ?>"
                                   class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="/admin/leave.php?emp=<?= $emp['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Leave history">
                                    <i class="bi bi-calendar3"></i>
                                </a>
                                <form method="POST" onsubmit="return confirm('Delete <?= htmlspecialchars($emp['first_name']) ?>?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($employees)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No employees found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add / Edit Employee Modal -->
<div class="modal fade" id="empModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white"><?= $editEmp ? 'Edit Employee' : 'Add Employee' ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $editEmp['id'] ?? '' ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">First Name *</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($editEmp['first_name'] ?? '') ?>"
                                   class="form-control bg-dark border-secondary text-white" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Last Name *</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($editEmp['last_name'] ?? '') ?>"
                                   class="form-control bg-dark border-secondary text-white" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Email *</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($editEmp['email'] ?? '') ?>"
                                   class="form-control bg-dark border-secondary text-white" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Phone</label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($editEmp['phone'] ?? '') ?>"
                                   class="form-control bg-dark border-secondary text-white">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Department *</label>
                            <select name="department" class="form-select bg-dark border-secondary text-white" required>
                                <?php foreach ($departments as $d): ?>
                                <option value="<?= $d ?>" <?= ($editEmp['department'] ?? '') === $d ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Job Title</label>
                            <input type="text" name="job_title" value="<?= htmlspecialchars($editEmp['job_title'] ?? '') ?>"
                                   class="form-control bg-dark border-secondary text-white">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Employment Type</label>
                            <select name="employment_type" class="form-select bg-dark border-secondary text-white">
                                <?php foreach ($empTypes as $val => $label): ?>
                                <option value="<?= $val ?>" <?= ($editEmp['employment_type'] ?? 'full_time') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Status</label>
                            <select name="status" class="form-select bg-dark border-secondary text-white">
                                <?php foreach ($statusOpts as $val => $label): ?>
                                <option value="<?= $val ?>" <?= ($editEmp['status'] ?? 'active') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Pay Cycle</label>
                            <select name="pay_cycle" class="form-select bg-dark border-secondary text-white">
                                <?php foreach ($payCycles as $val => $label): ?>
                                <option value="<?= $val ?>" <?= ($editEmp['pay_cycle'] ?? 'monthly') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Salary (<?= APP_CURRENCY ?>)</label>
                            <input type="number" name="salary" step="0.01" min="0"
                                   value="<?= $editEmp['salary'] ?? '' ?>"
                                   class="form-control bg-dark border-secondary text-white">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Start Date *</label>
                            <input type="date" name="start_date"
                                   value="<?= $editEmp['start_date'] ?? date('Y-m-d') ?>"
                                   class="form-control bg-dark border-secondary text-white" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">End Date</label>
                            <input type="date" name="end_date" value="<?= $editEmp['end_date'] ?? '' ?>"
                                   class="form-control bg-dark border-secondary text-white">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Manager</label>
                            <select name="manager_id" class="form-select bg-dark border-secondary text-white">
                                <option value="">— No manager —</option>
                                <?php foreach ($allManagers as $mgr):
                                    if ($mgr['id'] === ($editEmp['id'] ?? 0)) continue; ?>
                                <option value="<?= $mgr['id'] ?>" <?= ($editEmp['manager_id'] ?? '') == $mgr['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($mgr['first_name'].' '.$mgr['last_name']) ?>
                                    <?= $mgr['job_title'] ? '— '.htmlspecialchars($mgr['job_title']) : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Notes</label>
                            <textarea name="notes" rows="2" class="form-control bg-dark border-secondary text-white"><?= htmlspecialchars($editEmp['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><?= $editEmp ? 'Update' : 'Add' ?> Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
if ($editEmp) {
    $extraScripts = '<script>new bootstrap.Modal(document.getElementById("empModal")).show();</script>';
}
require_once '../includes/admin-footer.php';
?>
