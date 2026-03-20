<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();
$pageTitle = 'Leave Requests';

$msg = '';

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $empId    = (int)$_POST['employee_id'];
        $start    = $_POST['start_date'];
        $end      = $_POST['end_date'];
        $days     = max(1, (float)$_POST['days_count']);
        $type     = $_POST['leave_type'];
        $reason   = htmlspecialchars(trim($_POST['reason'] ?? ''));

        if ($empId && $start && $end) {
            DB::insert('leave_requests', [
                'employee_id' => $empId,
                'leave_type'  => $type,
                'start_date'  => $start,
                'end_date'    => $end,
                'days_count'  => $days,
                'reason'      => $reason,
                'status'      => 'pending',
            ]);
            $msg = '<div class="alert alert-success py-2">Leave request submitted.</div>';
        }

    } elseif ($action === 'review') {
        $id     = (int)$_POST['id'];
        $status = $_POST['status'];
        $note   = htmlspecialchars(trim($_POST['review_note'] ?? ''));
        $allowed = ['approved','rejected','cancelled'];
        if (in_array($status, $allowed)) {
            DB::update('leave_requests', [
                'status'      => $status,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => date('Y-m-d H:i:s'),
                'review_note' => $note,
            ], 'id=?', [$id]);

            // Sync employee status
            $lr = DB::fetch('SELECT employee_id FROM leave_requests WHERE id=?', [$id]);
            if ($lr) {
                if ($status === 'approved') {
                    // check if leave is current
                    $lr2 = DB::fetch('SELECT * FROM leave_requests WHERE id=?', [$id]);
                    if ($lr2 && $lr2['start_date'] <= date('Y-m-d') && $lr2['end_date'] >= date('Y-m-d')) {
                        DB::update('employees', ['status'=>'on_leave'], 'id=?', [$lr['employee_id']]);
                    }
                } elseif ($status === 'rejected' || $status === 'cancelled') {
                    DB::update('employees', ['status'=>'active'], 'id=?', [$lr['employee_id']]);
                }
            }
        }
        header('Location: /admin/leave.php');
        exit;

    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        DB::query('DELETE FROM leave_requests WHERE id=?', [$id]);
        header('Location: /admin/leave.php');
        exit;
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$statusFilter = trim($_GET['status'] ?? '');
$empFilter    = (int)($_GET['emp'] ?? 0);
$typeFilter   = trim($_GET['type'] ?? '');

$requests = DB::fetchAll(
    "SELECT lr.*, CONCAT(e.first_name,' ',e.last_name) AS employee_name,
            e.department, e.job_title,
            u.name AS reviewer_name
     FROM leave_requests lr
     JOIN employees e ON lr.employee_id=e.id
     LEFT JOIN users u ON lr.reviewed_by=u.id
     WHERE (? = '' OR lr.status=?)
       AND (? = 0  OR lr.employee_id=?)
       AND (? = '' OR lr.leave_type=?)
     ORDER BY FIELD(lr.status,'pending','approved','rejected','cancelled'), lr.start_date DESC",
    [$statusFilter,$statusFilter, $empFilter,$empFilter, $typeFilter,$typeFilter]
);

$employees  = DB::fetchAll("SELECT id, first_name, last_name, department FROM employees WHERE status != 'terminated' ORDER BY last_name");
$leaveTypes = ['annual','sick','unpaid','parental','bereavement','other'];

$typeColors = ['annual'=>'success','sick'=>'warning','unpaid'=>'secondary',
               'parental'=>'info','bereavement'=>'dark','other'=>'secondary'];
$statusColors = ['pending'=>'warning','approved'=>'success','rejected'=>'danger','cancelled'=>'secondary'];

require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white fw-bold mb-0">Leave Requests
            <span class="text-muted fs-6">(<?= count($requests) ?>)</span>
        </h4>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#leaveModal">
            <i class="bi bi-plus-circle me-1"></i>New Request
        </button>
    </div>

    <?= $msg ?>

    <!-- Filters -->
    <form method="GET" class="row g-2 mb-4">
        <div class="col-auto">
            <select name="status" class="form-select form-select-sm bg-dark border-secondary text-white">
                <option value="">All Statuses</option>
                <?php foreach (['pending','approved','rejected','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <select name="type" class="form-select form-select-sm bg-dark border-secondary text-white">
                <option value="">All Types</option>
                <?php foreach ($leaveTypes as $t): ?>
                <option value="<?= $t ?>" <?= $typeFilter===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
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
            <a href="/admin/leave.php" class="btn btn-sm btn-outline-secondary">Clear</a>
        </div>
    </form>

    <!-- Table -->
    <div class="admin-card rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
                <thead class="border-bottom border-secondary">
                    <tr class="text-muted small">
                        <th>Employee</th>
                        <th>Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Reviewed By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $lr):
                        $tc = $typeColors[$lr['leave_type']] ?? 'secondary';
                        $sc = $statusColors[$lr['status']] ?? 'secondary';
                    ?>
                    <tr>
                        <td>
                            <div class="text-white small fw-semibold"><?= htmlspecialchars($lr['employee_name']) ?></div>
                            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($lr['job_title'] ?: ucfirst($lr['department'])) ?></div>
                        </td>
                        <td><span class="badge bg-<?= $tc ?>" style="font-size:10px"><?= ucfirst($lr['leave_type']) ?></span></td>
                        <td class="text-muted small">
                            <?= date('d M Y', strtotime($lr['start_date'])) ?>
                            <?php if ($lr['start_date'] !== $lr['end_date']): ?>
                            <br>→ <?= date('d M Y', strtotime($lr['end_date'])) ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-white small text-center"><?= $lr['days_count'] ?></td>
                        <td class="text-muted small" style="max-width:180px">
                            <?= $lr['reason'] ? htmlspecialchars(mb_strimwidth($lr['reason'], 0, 60, '…')) : '—' ?>
                        </td>
                        <td><span class="badge bg-<?= $sc ?>" style="font-size:10px"><?= ucfirst($lr['status']) ?></span></td>
                        <td class="text-muted small"><?= htmlspecialchars($lr['reviewer_name'] ?: '—') ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <?php if ($lr['status'] === 'pending'): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="review">
                                    <input type="hidden" name="id" value="<?= $lr['id'] ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <button class="btn btn-sm btn-success" title="Approve"><i class="bi bi-check-lg"></i></button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="action" value="review">
                                    <input type="hidden" name="id" value="<?= $lr['id'] ?>">
                                    <input type="hidden" name="status" value="rejected">
                                    <button class="btn btn-sm btn-outline-danger" title="Reject"><i class="bi bi-x-lg"></i></button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" onsubmit="return confirm('Delete this request?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $lr['id'] ?>">
                                    <button class="btn btn-sm btn-outline-secondary" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($requests)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No leave requests found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- New Leave Request Modal -->
<div class="modal fade" id="leaveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white">New Leave Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-muted small">Employee *</label>
                            <select name="employee_id" class="form-select bg-dark border-secondary text-white" required>
                                <option value="">— Select employee —</option>
                                <?php foreach ($employees as $e): ?>
                                <option value="<?= $e['id'] ?>" <?= $empFilter==$e['id']?'selected':'' ?>>
                                    <?= htmlspecialchars($e['first_name'].' '.$e['last_name']) ?> (<?= ucfirst($e['department']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Leave Type *</label>
                            <select name="leave_type" class="form-select bg-dark border-secondary text-white" required>
                                <?php foreach ($leaveTypes as $t): ?>
                                <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Days *</label>
                            <input type="number" name="days_count" value="1" min="0.5" step="0.5"
                                   class="form-control bg-dark border-secondary text-white" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Start Date *</label>
                            <input type="date" name="start_date" value="<?= date('Y-m-d') ?>"
                                   class="form-control bg-dark border-secondary text-white" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">End Date *</label>
                            <input type="date" name="end_date" value="<?= date('Y-m-d') ?>"
                                   class="form-control bg-dark border-secondary text-white" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Reason</label>
                            <textarea name="reason" rows="2" class="form-control bg-dark border-secondary text-white"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/admin-footer.php'; ?>
