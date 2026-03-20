<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();
$pageTitle = 'HR Overview';

// ── Headcount stats ───────────────────────────────────────────────────────────
$headcount   = (int)(DB::fetch("SELECT COUNT(*) AS n FROM employees WHERE status='active'")['n'] ?? 0);
$onLeave     = (int)(DB::fetch("SELECT COUNT(*) AS n FROM employees WHERE status='on_leave'")['n'] ?? 0);
$pendingLeave= (int)(DB::fetch("SELECT COUNT(*) AS n FROM leave_requests WHERE status='pending'")['n'] ?? 0);
$totalPayroll= (float)(DB::fetch(
    "SELECT COALESCE(SUM(net_amount),0) AS t FROM payroll
     WHERE status='paid' AND YEAR(payment_date)=YEAR(CURDATE()) AND MONTH(payment_date)=MONTH(CURDATE())"
)['t'] ?? 0);

// ── Headcount by department ──────────────────────────────────────────────────
$byDept = DB::fetchAll(
    "SELECT department, COUNT(*) AS n FROM employees WHERE status='active'
     GROUP BY department ORDER BY n DESC"
);

// ── Upcoming leave (next 30 days) ─────────────────────────────────────────────
$upcomingLeave = DB::fetchAll(
    "SELECT lr.*, CONCAT(e.first_name,' ',e.last_name) AS employee_name, e.department
     FROM leave_requests lr
     JOIN employees e ON lr.employee_id=e.id
     WHERE lr.status='approved' AND lr.start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY lr.start_date ASC LIMIT 10"
);

// ── Pending leave requests ────────────────────────────────────────────────────
$pendingRequests = DB::fetchAll(
    "SELECT lr.*, CONCAT(e.first_name,' ',e.last_name) AS employee_name, e.department, e.job_title
     FROM leave_requests lr
     JOIN employees e ON lr.employee_id=e.id
     WHERE lr.status='pending'
     ORDER BY lr.created_at ASC LIMIT 8"
);

// ── Recent hires (last 60 days) ───────────────────────────────────────────────
$recentHires = DB::fetchAll(
    "SELECT * FROM employees
     WHERE start_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
     ORDER BY start_date DESC LIMIT 6"
);

// ── Monthly payroll trend (last 6 months) ─────────────────────────────────────
$payrollTrend = DB::fetchAll(
    "SELECT DATE_FORMAT(payment_date,'%b %Y') AS period,
            YEAR(payment_date) AS yr, MONTH(payment_date) AS mo,
            COALESCE(SUM(net_amount),0) AS total
     FROM payroll
     WHERE status='paid' AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY yr, mo ORDER BY yr, mo"
);

$deptIcons = [
    'engineering'=>'bi-code-slash','marketing'=>'bi-megaphone','sales'=>'bi-bag',
    'support'=>'bi-headset','operations'=>'bi-gear','finance'=>'bi-currency-dollar',
    'hr'=>'bi-people','management'=>'bi-briefcase'
];

$extraHead = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>';
require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>HR Overview</h4>
        <div class="d-flex gap-2">
            <a href="/admin/employees.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-person-plus me-1"></i>Employees
            </a>
            <a href="/admin/payroll.php" class="btn btn-sm btn-primary">
                <i class="bi bi-cash-stack me-1"></i>Run Payroll
            </a>
        </div>
    </div>

    <!-- KPI cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="text-muted small mb-1">Active Headcount</div>
                <div class="text-white fs-3 fw-bold"><?= $headcount ?></div>
                <div class="text-muted" style="font-size:11px"><?= $onLeave ?> on leave</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="text-muted small mb-1">Pending Leave</div>
                <div class="text-<?= $pendingLeave > 0 ? 'warning' : 'white' ?> fs-3 fw-bold"><?= $pendingLeave ?></div>
                <div class="text-muted" style="font-size:11px">Awaiting approval</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="text-muted small mb-1">Payroll This Month</div>
                <div class="text-white fs-3 fw-bold"><?= CURRENCY_SYMBOL ?><?= number_format($totalPayroll, 0) ?></div>
                <div class="text-muted" style="font-size:11px">Net paid</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="text-muted small mb-1">Departments</div>
                <div class="text-white fs-3 fw-bold"><?= count($byDept) ?></div>
                <div class="text-muted" style="font-size:11px">Active teams</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <!-- Headcount by dept -->
        <div class="col-lg-4">
            <div class="admin-card rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-white fw-semibold">By Department</div>
                    <a href="/admin/employees.php" class="btn btn-sm btn-outline-secondary">All</a>
                </div>
                <?php if (empty($byDept)): ?>
                <p class="text-muted small">No employees yet.</p>
                <?php else: ?>
                <?php foreach ($byDept as $d):
                    $pct = $headcount > 0 ? ($d['n'] / $headcount) * 100 : 0;
                    $icon = $deptIcons[$d['department']] ?? 'bi-building';
                ?>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi <?= $icon ?> text-primary" style="width:18px"></i>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between small text-white">
                            <span><?= ucfirst($d['department']) ?></span>
                            <span><?= $d['n'] ?></span>
                        </div>
                        <div class="progress mt-1" style="height:4px">
                            <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payroll trend chart -->
        <div class="col-lg-8">
            <div class="admin-card rounded-4 p-4 h-100">
                <div class="text-white fw-semibold mb-3">Payroll — Last 6 Months</div>
                <?php if (empty($payrollTrend)): ?>
                <p class="text-muted small mt-4">No payroll data yet. <a href="/admin/payroll.php" class="text-primary">Add payroll records</a>.</p>
                <?php else: ?>
                <canvas id="payrollChart" height="100"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Pending leave requests -->
        <div class="col-lg-6">
            <div class="admin-card rounded-4 overflow-hidden">
                <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom border-secondary border-opacity-25">
                    <div class="text-white fw-semibold">Pending Leave Requests
                        <?php if ($pendingLeave > 0): ?>
                        <span class="badge bg-warning text-dark ms-2"><?= $pendingLeave ?></span>
                        <?php endif; ?>
                    </div>
                    <a href="/admin/leave.php" class="btn btn-sm btn-outline-secondary">All Requests</a>
                </div>
                <?php if (empty($pendingRequests)): ?>
                <p class="text-muted small p-4">No pending requests.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0">
                        <tbody>
                            <?php foreach ($pendingRequests as $lr):
                                $typeColors = ['annual'=>'success','sick'=>'warning','unpaid'=>'secondary',
                                               'parental'=>'info','bereavement'=>'dark','other'=>'secondary'];
                                $tc = $typeColors[$lr['leave_type']] ?? 'secondary';
                            ?>
                            <tr>
                                <td>
                                    <div class="text-white small fw-semibold"><?= htmlspecialchars($lr['employee_name']) ?></div>
                                    <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($lr['job_title'] ?: $lr['department']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $tc ?>" style="font-size:10px"><?= ucfirst($lr['leave_type']) ?></span>
                                    <div class="text-muted mt-1" style="font-size:11px">
                                        <?= date('d M', strtotime($lr['start_date'])) ?> – <?= date('d M', strtotime($lr['end_date'])) ?>
                                        (<?= $lr['days_count'] ?> day<?= $lr['days_count'] != 1 ? 's' : '' ?>)
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <form method="POST" action="/admin/leave.php">
                                            <input type="hidden" name="action" value="review">
                                            <input type="hidden" name="id" value="<?= $lr['id'] ?>">
                                            <input type="hidden" name="status" value="approved">
                                            <button class="btn btn-sm btn-success" title="Approve"><i class="bi bi-check-lg"></i></button>
                                        </form>
                                        <form method="POST" action="/admin/leave.php">
                                            <input type="hidden" name="action" value="review">
                                            <input type="hidden" name="id" value="<?= $lr['id'] ?>">
                                            <input type="hidden" name="status" value="rejected">
                                            <button class="btn btn-sm btn-outline-danger" title="Reject"><i class="bi bi-x-lg"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming leave + recent hires -->
        <div class="col-lg-6">
            <div class="admin-card rounded-4 overflow-hidden mb-3">
                <div class="px-4 py-3 border-bottom border-secondary border-opacity-25 text-white fw-semibold">
                    Upcoming Leave <span class="text-muted fw-normal fs-6">(next 30 days)</span>
                </div>
                <?php if (empty($upcomingLeave)): ?>
                <p class="text-muted small p-4">No approved leave in the next 30 days.</p>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($upcomingLeave as $ul): ?>
                    <li class="list-group-item bg-transparent border-secondary border-opacity-25 d-flex justify-content-between">
                        <div>
                            <div class="text-white small"><?= htmlspecialchars($ul['employee_name']) ?></div>
                            <div class="text-muted" style="font-size:11px"><?= ucfirst($ul['leave_type']) ?> leave</div>
                        </div>
                        <div class="text-end">
                            <div class="text-muted small"><?= date('d M', strtotime($ul['start_date'])) ?> – <?= date('d M', strtotime($ul['end_date'])) ?></div>
                            <div class="text-muted" style="font-size:11px"><?= $ul['days_count'] ?> day<?= $ul['days_count']!=1?'s':'' ?></div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>

            <div class="admin-card rounded-4 overflow-hidden">
                <div class="px-4 py-3 border-bottom border-secondary border-opacity-25 text-white fw-semibold">
                    Recent Hires <span class="text-muted fw-normal fs-6">(last 60 days)</span>
                </div>
                <?php if (empty($recentHires)): ?>
                <p class="text-muted small p-4">No new hires yet. <a href="/admin/employees.php" class="text-primary">Add employees</a>.</p>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($recentHires as $rh): ?>
                    <li class="list-group-item bg-transparent border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-white small fw-semibold">
                                <?= htmlspecialchars($rh['first_name'].' '.$rh['last_name']) ?>
                            </div>
                            <div class="text-muted" style="font-size:11px">
                                <?= htmlspecialchars($rh['job_title'] ?: ucfirst($rh['department'])) ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-success" style="font-size:10px">New</span>
                            <div class="text-muted mt-1" style="font-size:11px"><?= date('d M Y', strtotime($rh['start_date'])) ?></div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($payrollTrend)): ?>
<script>
new Chart(document.getElementById('payrollChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($payrollTrend, 'period')) ?>,
        datasets: [{
            label: 'Net Payroll',
            data: <?= json_encode(array_map(fn($r) => (float)$r['total'], $payrollTrend)) ?>,
            backgroundColor: 'rgba(99,102,241,0.7)',
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#6b7280' } },
            y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#6b7280',
                 callback: v => '$'+v.toLocaleString() } }
        }
    }
});
</script>
<?php endif; ?>

<?php require_once '../includes/admin-footer.php'; ?>
