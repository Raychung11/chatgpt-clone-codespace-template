<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireModuleAccess('leave_reason');
$user = Auth::user();

/* ── Handle form submit ── */
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_leave'])) {
    $leaveType  = in_array($_POST['leave_type'] ?? '', ['annual','sick','unpaid','parental','bereavement','other']) ? $_POST['leave_type'] : 'annual';
    $startDate  = $_POST['start_date'] ?? '';
    $endDate    = $_POST['end_date']   ?? '';
    $reason     = trim($_POST['reason'] ?? '');

    if ($startDate && $endDate && $reason) {
        $start = new DateTime($startDate);
        $end   = new DateTime($endDate);
        $days  = max(1, $end->diff($start)->days + 1);

        try {
            DB::insert('user_leave_requests', [
                'user_id'    => $user['id'],
                'leave_type' => $leaveType,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'days_count' => $days,
                'reason'     => $reason,
                'status'     => 'pending',
            ]);
            $flash = 'success';
        } catch (Throwable $e) {
            $flash = 'db_error';
        }
    } else {
        $flash = 'validation';
    }
}

/* ── Load history ── */
$history = [];
try {
    $history = DB::fetchAll(
        'SELECT * FROM user_leave_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 20',
        [$user['id']]
    );
} catch (Throwable $e) { /* table may not exist yet */ }

$pageTitle = 'Leave Request';
require_once '../includes/header.php';
?>

<div class="container py-5">

    <!-- Header -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/modules/" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-calendar-check me-2 text-danger"></i>Leave Request</h3>
            <p class="text-muted small mb-0">Submit leave requests and track their status</p>
        </div>
    </div>

    <?php if ($flash === 'success'): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-check-circle-fill"></i>
        <div>Your leave request has been submitted successfully. Your manager will review it shortly.</div>
    </div>
    <?php elseif ($flash === 'validation'): ?>
    <div class="alert alert-warning mb-4">Please fill in all required fields.</div>
    <?php elseif ($flash === 'db_error'): ?>
    <div class="alert alert-danger mb-4">Could not save your request — the leave request table may not be set up yet. Please ask your admin to run the database setup.</div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- New Request -->
        <div class="col-lg-5">
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">New Leave Request</h6>

                <form method="POST" id="leaveForm">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Leave Type</label>
                        <select class="form-select" name="leave_type" id="leaveType">
                            <option value="annual">Annual Leave</option>
                            <option value="sick">Sick Leave</option>
                            <option value="unpaid">Unpaid Leave</option>
                            <option value="parental">Parental Leave</option>
                            <option value="bereavement">Bereavement Leave</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="start_date" id="startDate" required min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">End Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="end_date" id="endDate" required min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="mb-1">
                        <span class="text-muted small">Duration: </span>
                        <span class="text-primary fw-semibold small" id="daysCount">— days</span>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label text-muted small">Reason / Details <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="reason" id="reasonField" rows="3" placeholder="Briefly describe your reason…" required></textarea>
                    </div>

                    <!-- AI Suggest -->
                    <div class="mb-3">
                        <button type="button" class="btn btn-sm btn-outline-primary w-100" id="aiSuggestBtn" onclick="aiSuggest()">
                            <i class="bi bi-magic me-1"></i>AI: Draft Professional Wording
                        </button>
                        <div id="aiSuggestLoading" class="text-center py-2 d-none">
                            <span class="spinner-border spinner-border-sm text-primary me-2"></span>
                            <span class="text-muted small">Drafting…</span>
                        </div>
                    </div>

                    <button type="submit" name="submit_leave" class="btn btn-danger w-100 fw-semibold">
                        <i class="bi bi-send me-2"></i>Submit Request
                    </button>
                </form>
            </div>
        </div>

        <!-- Leave History -->
        <div class="col-lg-7">
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">My Leave History</h6>

                <?php if (empty($history)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-calendar3 fs-1 text-muted d-block mb-3" style="opacity:0.3"></i>
                    <p class="text-muted small">No leave requests yet. Submit your first request on the left.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover table-sm mb-0">
                        <thead>
                            <tr class="text-muted small">
                                <th>Type</th><th>Dates</th><th>Days</th><th>Status</th><th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($history as $req): ?>
                            <?php
                            $badgeClass = match($req['status']) {
                                'approved'  => 'bg-success',
                                'rejected'  => 'bg-danger',
                                'cancelled' => 'bg-secondary',
                                default     => 'bg-warning text-dark',
                            };
                            ?>
                            <tr>
                                <td class="text-white small"><?= ucfirst($req['leave_type']) ?></td>
                                <td class="text-muted small">
                                    <?= date('d M', strtotime($req['start_date'])) ?>
                                    <?= $req['start_date'] !== $req['end_date'] ? ' – ' . date('d M', strtotime($req['end_date'])) : '' ?>
                                </td>
                                <td class="text-muted small"><?= $req['days_count'] ?></td>
                                <td><span class="badge <?= $badgeClass ?>"><?= ucfirst($req['status']) ?></span></td>
                                <td class="text-muted small"><?= date('d M Y', strtotime($req['created_at'])) ?></td>
                            </tr>
                            <?php if ($req['reason']): ?>
                            <tr>
                                <td colspan="5" class="text-muted" style="font-size:12px;padding-top:0;border-top:none">
                                    <i class="bi bi-chat-text me-1"></i><?= htmlspecialchars($req['reason']) ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <!-- Leave Balance Summary -->
                <div class="mt-4 pt-3 border-top border-secondary border-opacity-25">
                    <h6 class="text-white small fw-semibold mb-3">Leave Summary (This Year)</h6>
                    <?php
                    $yearStart = date('Y') . '-01-01';
                    $counts = ['annual'=>0,'sick'=>0,'unpaid'=>0,'parental'=>0,'bereavement'=>0,'other'=>0];
                    foreach ($history as $r) {
                        if ($r['status'] === 'approved' && $r['start_date'] >= $yearStart) {
                            $type = $r['leave_type'] ?? 'other';
                            $counts[$type] = ($counts[$type] ?? 0) + $r['days_count'];
                        }
                    }
                    $total = array_sum($counts);
                    ?>
                    <div class="row g-2">
                        <?php foreach (['annual'=>'Annual','sick'=>'Sick','unpaid'=>'Unpaid'] as $key => $label): ?>
                        <div class="col-4">
                            <div class="text-center p-2 rounded-3" style="background:rgba(255,255,255,0.04)">
                                <div class="fs-5 fw-bold text-white"><?= $counts[$key] ?></div>
                                <div class="text-muted" style="font-size:11px"><?= $label ?> days</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.AI_MODULE_KEY = 'leave_reason';
// Date pickers — update days count and set end min
document.getElementById('startDate').addEventListener('change', function() {
    document.getElementById('endDate').min = this.value;
    calcDays();
});
document.getElementById('endDate').addEventListener('change', calcDays);

function calcDays() {
    const s = document.getElementById('startDate').value;
    const e = document.getElementById('endDate').value;
    if (s && e) {
        const diff = Math.round((new Date(e) - new Date(s)) / 86400000) + 1;
        document.getElementById('daysCount').textContent = diff > 0 ? diff + ' day' + (diff > 1 ? 's' : '') : '—';
    }
}

// AI suggest wording
async function aiSuggest() {
    const brief = document.getElementById('reasonField').value.trim();
    if (!brief) { alert('Please enter a brief reason first, then I\'ll improve the wording.'); return; }

    const leaveType = document.getElementById('leaveType').value;
    const s = document.getElementById('startDate').value;
    const e = document.getElementById('endDate').value;
    const days = (s && e) ? Math.round((new Date(e) - new Date(s)) / 86400000) + 1 : 1;

    document.getElementById('aiSuggestBtn').classList.add('d-none');
    document.getElementById('aiSuggestLoading').classList.remove('d-none');

    const data = new FormData();
    data.append('module', 'leave_reason');
    data.append('leave_type', leaveType);
    data.append('days', days);
    data.append('brief', brief);

    try {
        const res  = await fetch('/api/ai-generate.php', { method: 'POST', body: data });
        const json = await res.json();
        if (json.ok) {
            document.getElementById('reasonField').value = json.text.trim();
            if (typeof refreshMemoryWidget === 'function') refreshMemoryWidget();
        } else {
            alert(json.error || 'AI suggestion failed.');
        }
    } catch(err) {
        alert('Network error. Please try again.');
    }

    document.getElementById('aiSuggestBtn').classList.remove('d-none');
    document.getElementById('aiSuggestLoading').classList.add('d-none');
}
</script>

<?php require_once '../includes/footer.php'; ?>
