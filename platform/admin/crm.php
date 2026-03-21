<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'done') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            DB::update('crm_activities', ['status' => 'done', 'done_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        }
        header('Location: crm.php');
        exit;
    }

    if ($action === 'add_activity') {
        $contactId = (int)($_POST['contact_id'] ?? 0);
        $dealId    = (int)($_POST['deal_id'] ?? 0) ?: null;
        $type      = $_POST['type'] ?? 'note';
        $subject   = trim($_POST['subject'] ?? '');
        $dueAt     = trim($_POST['due_at'] ?? '') ?: null;

        if ($contactId > 0 && $subject !== '') {
            DB::insert('crm_activities', [
                'contact_id' => $contactId,
                'deal_id'    => $dealId,
                'type'       => $type,
                'subject'    => $subject,
                'body'       => '',
                'status'     => 'planned',
                'due_at'     => $dueAt,
                'done_at'    => null,
                'created_by' => Auth::id(),
            ]);
        }
        header('Location: crm.php');
        exit;
    }
}

// ── KPIs ──────────────────────────────────────────────────────────────────────
$totalContacts = DB::fetch('SELECT COUNT(*) AS n FROM crm_contacts')['n'];

$openDeals = DB::fetch(
    "SELECT COUNT(*) AS cnt, COALESCE(SUM(value),0) AS total
     FROM crm_deals
     WHERE stage NOT IN ('closed_won','closed_lost')"
);

$wonThisMonth = DB::fetch(
    "SELECT COALESCE(SUM(value),0) AS total
     FROM crm_deals
     WHERE stage = 'closed_won'
       AND MONTH(updated_at) = MONTH(CURDATE())
       AND YEAR(updated_at)  = YEAR(CURDATE())"
);

$convRate = DB::fetch(
    "SELECT
        COUNT(*) AS total_deals,
        SUM(CASE WHEN stage='closed_won' THEN 1 ELSE 0 END) AS won
     FROM crm_deals"
);
$conversionRate = $convRate['total_deals'] > 0
    ? round(($convRate['won'] / $convRate['total_deals']) * 100, 1)
    : 0;

// ── Pipeline funnel ───────────────────────────────────────────────────────────
$stages         = ['lead', 'qualified', 'proposal', 'negotiation', 'closed_won', 'closed_lost'];
$stageColors    = [
    'lead'         => 'secondary',
    'qualified'    => 'info',
    'proposal'     => 'primary',
    'negotiation'  => 'warning',
    'closed_won'   => 'success',
    'closed_lost'  => 'danger',
];
$pipelineRows = DB::fetchAll(
    "SELECT stage, COUNT(*) AS cnt, COALESCE(SUM(value),0) AS total
     FROM crm_deals
     GROUP BY stage"
);
$pipeline = [];
foreach ($pipelineRows as $row) {
    $pipeline[$row['stage']] = $row;
}
$maxPipelineCount = max(array_column($pipelineRows, 'cnt') ?: [1]);

// ── Recent activities ─────────────────────────────────────────────────────────
$recentActivities = DB::fetchAll(
    "SELECT a.*,
            CONCAT(c.first_name,' ',c.last_name) AS contact_name,
            d.title AS deal_title
     FROM crm_activities a
     LEFT JOIN crm_contacts c ON a.contact_id = c.id
     LEFT JOIN crm_deals    d ON a.deal_id    = d.id
     ORDER BY a.created_at DESC
     LIMIT 10"
);

// ── Upcoming tasks ────────────────────────────────────────────────────────────
$upcomingTasks = DB::fetchAll(
    "SELECT a.*,
            CONCAT(c.first_name,' ',c.last_name) AS contact_name
     FROM crm_activities a
     LEFT JOIN crm_contacts c ON a.contact_id = c.id
     WHERE a.status = 'planned'
       AND a.due_at IS NOT NULL
       AND a.due_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
     ORDER BY a.due_at ASC"
);

// ── Chart: monthly won deals – last 6 months ──────────────────────────────────
$chartRows = DB::fetchAll(
    "SELECT DATE_FORMAT(updated_at,'%Y-%m') AS mo,
            COALESCE(SUM(value),0) AS total
     FROM crm_deals
     WHERE stage = 'closed_won'
       AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY mo
     ORDER BY mo ASC"
);
$chartLabels = [];
$chartData   = [];
// Build last-6-month buckets so missing months show 0
for ($i = 5; $i >= 0; $i--) {
    $key           = date('Y-m', strtotime("-$i months"));
    $chartLabels[] = date('M Y', strtotime("-$i months"));
    $chartData[$key] = 0;
}
foreach ($chartRows as $row) {
    if (isset($chartData[$row['mo']])) {
        $chartData[$row['mo']] = (float)$row['total'];
    }
}

// ── Contacts list for quick-add modal ────────────────────────────────────────
$allContacts = DB::fetchAll(
    "SELECT id, CONCAT(first_name,' ',last_name) AS full_name FROM crm_contacts ORDER BY first_name"
);

$pageTitle = 'CRM Dashboard';
require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <!-- Page title -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white fw-bold mb-0">CRM Dashboard</h4>
            <p class="text-muted small mb-0">Sales pipeline &amp; activity overview</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/admin/crm-contacts.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-people me-1"></i>Contacts
            </a>
            <a href="/admin/crm-deals.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-kanban me-1"></i>Deals
            </a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addActivityModal">
                <i class="bi bi-plus-circle me-1"></i>Log Activity
            </button>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-3">
                <div class="text-muted small mb-1"><i class="bi bi-people me-1"></i>Total Contacts</div>
                <div class="fs-3 fw-bold text-white"><?= number_format($totalContacts) ?></div>
                <div class="text-muted small">All CRM contacts</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-3">
                <div class="text-muted small mb-1"><i class="bi bi-funnel me-1"></i>Open Deals</div>
                <div class="fs-3 fw-bold text-white"><?= CURRENCY_SYMBOL ?><?= number_format($openDeals['total'], 0) ?></div>
                <div class="text-muted small"><?= number_format($openDeals['cnt']) ?> active deals</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-3">
                <div class="text-muted small mb-1"><i class="bi bi-trophy me-1"></i>Won This Month</div>
                <div class="fs-3 fw-bold text-success"><?= CURRENCY_SYMBOL ?><?= number_format($wonThisMonth['total'], 0) ?></div>
                <div class="text-muted small"><?= date('F Y') ?></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="admin-card rounded-4 p-3">
                <div class="text-muted small mb-1"><i class="bi bi-percent me-1"></i>Conversion Rate</div>
                <div class="fs-3 fw-bold text-info"><?= $conversionRate ?>%</div>
                <div class="text-muted small">closed_won / total deals</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">

        <!-- Pipeline Funnel -->
        <div class="col-lg-6">
            <div class="admin-card rounded-4 p-4 h-100">
                <h6 class="text-white fw-semibold mb-3"><i class="bi bi-bar-chart-steps me-2"></i>Pipeline Funnel</h6>
                <?php foreach ($stages as $stage):
                    $info  = $pipeline[$stage] ?? ['cnt' => 0, 'total' => 0];
                    $pct   = $maxPipelineCount > 0 ? round(($info['cnt'] / $maxPipelineCount) * 100) : 0;
                    $color = $stageColors[$stage];
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-white small fw-semibold"><?= ucfirst(str_replace('_', ' ', $stage)) ?></span>
                        <span class="text-muted small">
                            <?= number_format($info['cnt']) ?> deal<?= $info['cnt'] != 1 ? 's' : '' ?>
                            &nbsp;&middot;&nbsp;
                            <?= CURRENCY_SYMBOL ?><?= number_format($info['total'], 0) ?>
                        </span>
                    </div>
                    <div class="progress" style="height:10px;background:#1e1e2e;">
                        <div class="progress-bar bg-<?= $color ?>"
                             role="progressbar"
                             style="width:<?= $pct ?>%"
                             aria-valuenow="<?= $pct ?>"
                             aria-valuemin="0"
                             aria-valuemax="100">
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Won Deals Chart -->
        <div class="col-lg-6">
            <div class="admin-card rounded-4 p-4 h-100">
                <h6 class="text-white fw-semibold mb-3"><i class="bi bi-graph-up me-2"></i>Won Deal Value — Last 6 Months</h6>
                <canvas id="wonChart" height="200"></canvas>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- Recent Activities -->
        <div class="col-lg-7">
            <div class="admin-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3"><i class="bi bi-clock-history me-2"></i>Recent Activities</h6>
                <div class="table-responsive">
                    <table class="table table-dark table-hover table-sm mb-0">
                        <thead>
                            <tr class="text-muted small">
                                <th>Type</th>
                                <th>Subject</th>
                                <th>Contact</th>
                                <th>Deal</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentActivities as $act): ?>
                            <?php
                                $typeIcon = match($act['type']) {
                                    'call'    => 'telephone',
                                    'email'   => 'envelope',
                                    'meeting' => 'camera-video',
                                    'task'    => 'check2-square',
                                    default   => 'sticky',
                                };
                                $statusCls = match($act['status']) {
                                    'done'      => 'success',
                                    'cancelled' => 'danger',
                                    default     => 'warning',
                                };
                            ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-<?= $typeIcon ?>"></i>
                                        <?= ucfirst($act['type']) ?>
                                    </span>
                                </td>
                                <td class="text-white small"><?= htmlspecialchars($act['subject']) ?></td>
                                <td class="text-muted small">
                                    <?php if ($act['contact_name']): ?>
                                    <a href="/admin/crm-contacts.php?edit=<?= $act['contact_id'] ?>" class="text-info text-decoration-none">
                                        <?= htmlspecialchars($act['contact_name']) ?>
                                    </a>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td class="text-muted small">
                                    <?= $act['deal_title'] ? htmlspecialchars($act['deal_title']) : '—' ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $statusCls ?>">
                                        <?= ucfirst($act['status']) ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?= date('d M, H:i', strtotime($act['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentActivities)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">No activities yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Upcoming Tasks -->
        <div class="col-lg-5">
            <div class="admin-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3"><i class="bi bi-calendar-check me-2"></i>Upcoming Tasks (7 days)</h6>
                <?php if (empty($upcomingTasks)): ?>
                <p class="text-muted small mb-0">No upcoming tasks.</p>
                <?php endif; ?>
                <?php foreach ($upcomingTasks as $task): ?>
                <div class="d-flex align-items-start gap-3 py-2 border-bottom border-secondary border-opacity-25">
                    <div class="flex-grow-1">
                        <div class="text-white small fw-semibold"><?= htmlspecialchars($task['subject']) ?></div>
                        <div class="text-muted" style="font-size:11px">
                            <i class="bi bi-person me-1"></i><?= $task['contact_name'] ? htmlspecialchars($task['contact_name']) : '—' ?>
                            &nbsp;&middot;&nbsp;
                            <i class="bi bi-clock me-1"></i><?= date('d M, H:i', strtotime($task['due_at'])) ?>
                        </div>
                    </div>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="done">
                        <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">
                        <button type="submit" class="btn btn-success btn-sm py-0 px-2" title="Mark done">
                            <i class="bi bi-check-lg"></i>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick-Add Activity Modal -->
<div class="modal fade" id="addActivityModal" tabindex="-1" aria-labelledby="addActivityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="addActivityModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>Log Activity
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_activity">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Contact <span class="text-danger">*</span></label>
                        <select name="contact_id" class="form-select bg-dark border-secondary text-white" required>
                            <option value="">— Select contact —</option>
                            <?php foreach ($allContacts as $con): ?>
                            <option value="<?= (int)$con['id'] ?>">
                                <?= htmlspecialchars($con['full_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Type</label>
                        <select name="type" class="form-select bg-dark border-secondary text-white">
                            <option value="call">Call</option>
                            <option value="email">Email</option>
                            <option value="meeting">Meeting</option>
                            <option value="task" selected>Task</option>
                            <option value="note">Note</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Subject <span class="text-danger">*</span></label>
                        <input type="text" name="subject" class="form-control bg-dark border-secondary text-white" required placeholder="Activity subject…">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Due Date / Time</label>
                        <input type="datetime-local" name="due_at" class="form-control bg-dark border-secondary text-white">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Activity
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    const labels = ' . json_encode(array_values($chartLabels)) . ';
    const data   = ' . json_encode(array_values($chartData)) . ';
    const ctx    = document.getElementById("wonChart").getContext("2d");
    new Chart(ctx, {
        type: "bar",
        data: {
            labels,
            datasets: [{
                label: "Won Value (' . CURRENCY_SYMBOL . ')",
                data,
                backgroundColor: "rgba(99,102,241,0.7)",
                borderColor:     "#6366f1",
                borderWidth:     1,
                borderRadius:    4,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { labels: { color: "#aaa" } },
                tooltip: {
                    callbacks: {
                        label: ctx => "' . CURRENCY_SYMBOL . '" + ctx.parsed.y.toLocaleString()
                    }
                }
            },
            scales: {
                x: { ticks: { color: "#aaa" }, grid: { color: "rgba(255,255,255,0.05)" } },
                y: { ticks: { color: "#aaa", callback: v => "' . CURRENCY_SYMBOL . '" + v.toLocaleString() }, grid: { color: "rgba(255,255,255,0.05)" } }
            }
        }
    });
})();
</script>';
require_once '../includes/admin-footer.php';
?>
