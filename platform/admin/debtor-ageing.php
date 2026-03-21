<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireAdmin();
$pageTitle = 'Debtor Ageing';

$msg = '';

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_note') {
        DB::insert('debtor_notes', [
            'invoice_id'     => (int)$_POST['invoice_id'],
            'note'           => htmlspecialchars(trim($_POST['note'])),
            'follow_up_date' => $_POST['follow_up_date'] ?: null,
            'created_by'     => Auth::id(),
        ]);
        $msg = '<div class="alert alert-success py-2">Note saved.</div>';
    }
    if ($action === 'send_reminder') {
        $msg = '<div class="alert alert-success py-2">Reminder email queued for ' . htmlspecialchars($_POST['customer_name'] ?? '') . '.</div>';
    }
}

// ── CSV Export ────────────────────────────────────────────────────────────────
if (($_GET['action'] ?? '') === 'export') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="debtor-ageing-' . date('Y-m-d') . '.csv"');
    echo "Customer,Email,Invoice #,Issue Date,Due Date,Days Overdue,Amount,Bucket,Status\n";
    $rows = DB::fetchAll(
        "SELECT u.name, u.email, i.id AS inv_id, i.issue_date, i.due_date,
                DATEDIFF(CURDATE(), i.due_date) AS days_over,
                i.amount, i.status
         FROM invoices i
         JOIN users u ON i.client_id=u.id
         WHERE i.status IN ('sent','overdue','partial')
         ORDER BY days_over DESC"
    );
    foreach ($rows as $r) {
        $bucket = $r['days_over'] <= 0 ? 'Current'
            : ($r['days_over'] <= 30 ? '1–30 Days'
            : ($r['days_over'] <= 60 ? '31–60 Days'
            : ($r['days_over'] <= 90 ? '61–90 Days' : '90+ Days')));
        echo implode(',', [
            '"' . str_replace('"','""',$r['name']) . '"',
            '"' . str_replace('"','""',$r['email']) . '"',
            'INV-' . str_pad($r['inv_id'],5,'0',STR_PAD_LEFT),
            $r['issue_date'], $r['due_date'],
            max(0,$r['days_over']),
            number_format($r['amount'],2),
            $bucket, $r['status']
        ]) . "\n";
    }
    exit;
}

// ── Filters ──────────────────────────────────────────────────────────────────
$bucketFilter = $_GET['bucket'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$search       = trim($_GET['search'] ?? '');

// ── Fetch unpaid invoices ─────────────────────────────────────────────────────
$invoices = DB::fetchAll(
    "SELECT i.*, u.name AS customer_name, u.email AS customer_email,
            DATEDIFF(CURDATE(), i.due_date) AS days_over
     FROM invoices i
     JOIN users u ON i.client_id=u.id
     WHERE i.status IN ('sent','overdue','partial')
     ORDER BY days_over DESC, i.due_date ASC"
);

// Sample data if empty
$useSample = empty($invoices);
if ($useSample) {
    $invoices = [
        ['id'=>1,'customer_name'=>'TechNova Sdn Bhd','customer_email'=>'ap@technova.my','invoice_number'=>'INV-00101','issue_date'=>date('Y-m-d',strtotime('-5 days')),'due_date'=>date('Y-m-d',strtotime('+25 days')),'amount'=>4500.00,'status'=>'sent','days_over'=>-25],
        ['id'=>2,'customer_name'=>'Kafe Meranti Jaya','customer_email'=>'owner@kafemerantijaya.my','invoice_number'=>'INV-00098','issue_date'=>date('Y-m-d',strtotime('-45 days')),'due_date'=>date('Y-m-d',strtotime('-15 days')),'amount'=>1200.00,'status'=>'sent','days_over'=>15],
        ['id'=>3,'customer_name'=>'Global Retail Bhd','customer_email'=>'finance@globalretail.com','invoice_number'=>'INV-00091','issue_date'=>date('Y-m-d',strtotime('-65 days')),'due_date'=>date('Y-m-d',strtotime('-35 days')),'amount'=>8750.00,'status'=>'overdue','days_over'=>35],
        ['id'=>4,'customer_name'=>'Hijau Organics','customer_email'=>'billing@hijauorganics.com','invoice_number'=>'INV-00085','issue_date'=>date('Y-m-d',strtotime('-90 days')),'due_date'=>date('Y-m-d',strtotime('-60 days')),'amount'=>3200.00,'status'=>'overdue','days_over'=>60],
        ['id'=>5,'customer_name'=>'SwiftLogix Sdn Bhd','customer_email'=>'accounts@swiftlogix.com','invoice_number'=>'INV-00079','issue_date'=>date('Y-m-d',strtotime('-120 days')),'due_date'=>date('Y-m-d',strtotime('-90 days')),'amount'=>15000.00,'status'=>'overdue','days_over'=>90],
        ['id'=>6,'customer_name'=>'Nusantara Group','customer_email'=>'cfo@nusantara.com','invoice_number'=>'INV-00072','issue_date'=>date('Y-m-d',strtotime('-150 days')),'due_date'=>date('Y-m-d',strtotime('-120 days')),'amount'=>22500.00,'status'=>'overdue','days_over'=>120],
        ['id'=>7,'customer_name'=>'TechNova Sdn Bhd','customer_email'=>'ap@technova.my','invoice_number'=>'INV-00065','issue_date'=>date('Y-m-d',strtotime('-25 days')),'due_date'=>date('Y-m-d',strtotime('+5 days')),'amount'=>6800.00,'status'=>'sent','days_over'=>-5],
        ['id'=>8,'customer_name'=>'Prime Solutions','customer_email'=>'finance@primesolutions.my','invoice_number'=>'INV-00060','issue_date'=>date('Y-m-d',strtotime('-55 days')),'due_date'=>date('Y-m-d',strtotime('-25 days')),'amount'=>2900.00,'status'=>'partial','days_over'=>25],
        ['id'=>9,'customer_name'=>'BrightStar Academy','customer_email'=>'admin@brightstaracademy.edu.my','invoice_number'=>'INV-00054','issue_date'=>date('Y-m-d',strtotime('-100 days')),'due_date'=>date('Y-m-d',strtotime('-70 days')),'amount'=>5400.00,'status'=>'overdue','days_over'=>70],
        ['id'=>10,'customer_name'=>'Kafe Meranti Jaya','customer_email'=>'owner@kafemerantijaya.my','invoice_number'=>'INV-00048','issue_date'=>date('Y-m-d',strtotime('-170 days')),'due_date'=>date('Y-m-d',strtotime('-140 days')),'amount'=>980.00,'status'=>'overdue','days_over'=>140],
    ];
}

// ── Ageing buckets ────────────────────────────────────────────────────────────
$buckets = ['current'=>0,'b1_30'=>0,'b31_60'=>0,'b61_90'=>0,'b90plus'=>0];
foreach ($invoices as $inv) {
    $d = (int)$inv['days_over'];
    if ($d <= 0)      $buckets['current'] += $inv['amount'];
    elseif ($d <= 30) $buckets['b1_30']   += $inv['amount'];
    elseif ($d <= 60) $buckets['b31_60']  += $inv['amount'];
    elseif ($d <= 90) $buckets['b61_90']  += $inv['amount'];
    else              $buckets['b90plus'] += $inv['amount'];
}
$totalOutstanding = array_sum($buckets);

// ── Apply filters ─────────────────────────────────────────────────────────────
$filtered = array_filter($invoices, function($inv) use ($bucketFilter, $statusFilter, $search) {
    $d = (int)$inv['days_over'];
    if ($bucketFilter !== 'all') {
        if ($bucketFilter === 'current' && $d > 0) return false;
        if ($bucketFilter === '1-30'   && !($d > 0 && $d <= 30)) return false;
        if ($bucketFilter === '31-60'  && !($d > 30 && $d <= 60)) return false;
        if ($bucketFilter === '61-90'  && !($d > 60 && $d <= 90)) return false;
        if ($bucketFilter === '90plus' && $d <= 90) return false;
    }
    if ($statusFilter !== 'all' && $inv['status'] !== $statusFilter) return false;
    if ($search && stripos($inv['customer_name'], $search) === false && stripos($inv['customer_email'], $search) === false) return false;
    return true;
});

// ── Customer summary ──────────────────────────────────────────────────────────
$customerSummary = [];
foreach ($invoices as $inv) {
    $k = $inv['customer_email'];
    if (!isset($customerSummary[$k])) {
        $customerSummary[$k] = ['name'=>$inv['customer_name'],'email'=>$inv['customer_email'],'total'=>0,'count'=>0,'max_days'=>0];
    }
    $customerSummary[$k]['total'] += $inv['amount'];
    $customerSummary[$k]['count']++;
    $customerSummary[$k]['max_days'] = max($customerSummary[$k]['max_days'], (int)$inv['days_over']);
}
usort($customerSummary, fn($a,$b) => $b['total'] <=> $a['total']);

// ── Notes per invoice ─────────────────────────────────────────────────────────
$notes = DB::fetchAll("SELECT * FROM debtor_notes ORDER BY created_at DESC");
$notesByInv = [];
foreach ($notes as $n) $notesByInv[$n['invoice_id']][] = $n;

require_once '../includes/admin-header.php';

function getBucketInfo(int $days): array {
    if ($days <= 0)      return ['Current',   'success',  'rgba(16,185,129,0.1)'];
    if ($days <= 30)     return ['1–30 Days',  'warning',  'rgba(245,158,11,0.1)'];
    if ($days <= 60)     return ['31–60 Days', 'orange',   'rgba(251,146,60,0.15)'];
    if ($days <= 90)     return ['61–90 Days', 'danger',   'rgba(220,53,69,0.12)'];
    return              ['90+ Days',  'danger',   'rgba(220,53,69,0.25)'];
}
?>
<div class="container-fluid py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white fw-bold mb-0">
            <i class="bi bi-person-exclamation me-2 text-primary"></i>Debtor Ageing (AR)
        </h4>
        <a href="?action=export" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>

    <?= $msg ?>

    <!-- KPI Row -->
    <div class="row g-3 mb-4">
        <div class="col">
            <div class="glass-card p-3 text-center">
                <div class="text-muted small">Total Outstanding</div>
                <div class="fs-5 fw-bold text-white">RM <?= number_format($totalOutstanding,2) ?></div>
            </div>
        </div>
        <div class="col">
            <div class="glass-card p-3 text-center">
                <div class="text-muted small">Current</div>
                <div class="fs-5 fw-bold text-success">RM <?= number_format($buckets['current'],2) ?></div>
            </div>
        </div>
        <div class="col">
            <div class="glass-card p-3 text-center">
                <div class="text-muted small">1–30 Days</div>
                <div class="fs-5 fw-bold text-warning">RM <?= number_format($buckets['b1_30'],2) ?></div>
            </div>
        </div>
        <div class="col">
            <div class="glass-card p-3 text-center">
                <div class="text-muted small">31–60 Days</div>
                <div class="fs-5 fw-bold" style="color:#fb923c">RM <?= number_format($buckets['b31_60'],2) ?></div>
            </div>
        </div>
        <div class="col">
            <div class="glass-card p-3 text-center">
                <div class="text-muted small">61–90 Days</div>
                <div class="fs-5 fw-bold text-danger">RM <?= number_format($buckets['b61_90'],2) ?></div>
            </div>
        </div>
        <div class="col" style="border-left:3px solid #dc3545">
            <div class="glass-card p-3 text-center" style="background:rgba(220,53,69,0.08)">
                <div class="text-muted small">90+ Days</div>
                <div class="fs-5 fw-bold text-danger">RM <?= number_format($buckets['b90plus'],2) ?></div>
            </div>
        </div>
    </div>

    <!-- Stacked Bar -->
    <?php if ($totalOutstanding > 0): ?>
    <div class="glass-card p-3 mb-4">
        <div class="text-muted small mb-2">Ageing Distribution</div>
        <div class="progress" style="height:22px; border-radius:6px">
            <?php
            $barData = [
                ['current', '#10b981', $buckets['current']],
                ['1-30',    '#f59e0b', $buckets['b1_30']],
                ['31-60',   '#fb923c', $buckets['b31_60']],
                ['61-90',   '#ef4444', $buckets['b61_90']],
                ['90+',     '#991b1b', $buckets['b90plus']],
            ];
            foreach ($barData as [$label, $color, $val]):
                $pct = $totalOutstanding > 0 ? ($val / $totalOutstanding * 100) : 0;
                if ($pct < 0.5) continue;
            ?>
            <div class="progress-bar" style="width:<?= number_format($pct,1) ?>%; background:<?= $color ?>; font-size:11px">
                <?= number_format($pct,0) ?>%
            </div>
            <?php endforeach; ?>
        </div>
        <div class="d-flex gap-3 mt-2">
            <?php foreach ([['Current','#10b981'],['1–30d','#f59e0b'],['31–60d','#fb923c'],['61–90d','#ef4444'],['90+d','#991b1b']] as [$l,$c]): ?>
            <span class="small text-muted"><span class="d-inline-block rounded-1 me-1" style="width:12px;height:12px;background:<?= $c ?>"></span><?= $l ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="GET" class="glass-card p-3 mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label text-muted small">Bucket</label>
                <select name="bucket" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="all" <?= $bucketFilter==='all'?'selected':'' ?>>All Buckets</option>
                    <option value="current" <?= $bucketFilter==='current'?'selected':'' ?>>Current</option>
                    <option value="1-30" <?= $bucketFilter==='1-30'?'selected':'' ?>>1–30 Days</option>
                    <option value="31-60" <?= $bucketFilter==='31-60'?'selected':'' ?>>31–60 Days</option>
                    <option value="61-90" <?= $bucketFilter==='61-90'?'selected':'' ?>>61–90 Days</option>
                    <option value="90plus" <?= $bucketFilter==='90plus'?'selected':'' ?>>90+ Days</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small">Status</label>
                <select name="status" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="all" <?= $statusFilter==='all'?'selected':'' ?>>All</option>
                    <option value="sent" <?= $statusFilter==='sent'?'selected':'' ?>>Sent</option>
                    <option value="overdue" <?= $statusFilter==='overdue'?'selected':'' ?>>Overdue</option>
                    <option value="partial" <?= $statusFilter==='partial'?'selected':'' ?>>Partial</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted small">Search Customer</label>
                <input type="text" name="search" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= htmlspecialchars($search) ?>" placeholder="Name or email…">
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100">Filter</button></div>
        </div>
    </form>

    <!-- Ageing Table -->
    <div class="glass-card p-4 mb-4">
        <h6 class="text-white mb-3">Ageing Detail — <?= $useSample ? '<span class="badge bg-secondary">Sample Data</span>' : count($filtered) . ' records' ?></h6>
        <div class="table-responsive">
            <table class="table table-dark align-middle mb-0 small">
                <thead>
                    <tr class="text-muted">
                        <th>Customer</th><th>Invoice #</th><th>Issue Date</th><th>Due Date</th>
                        <th class="text-center">Days Over</th><th class="text-end">Amount</th>
                        <th>Bucket</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($filtered as $inv):
                    $days = (int)$inv['days_over'];
                    [$bLabel, $bBadge, $bBg] = getBucketInfo($days);
                    $statusBadge = ['sent'=>'secondary','overdue'=>'danger','partial'=>'warning'];
                    $invNotes = $notesByInv[$inv['id']] ?? [];
                ?>
                <tr style="background:<?= $bBg ?>">
                    <td>
                        <div class="fw-semibold text-white"><?= htmlspecialchars($inv['customer_name']) ?></div>
                        <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($inv['customer_email']) ?></div>
                    </td>
                    <td class="font-monospace text-muted">INV-<?= str_pad($inv['id'],5,'0',STR_PAD_LEFT) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($inv['issue_date'] ?? '') ?></td>
                    <td class="text-muted"><?= htmlspecialchars($inv['due_date'] ?? '') ?></td>
                    <td class="text-center">
                        <?php if ($days <= 0): ?>
                            <span class="text-success fw-semibold"><?= abs($days) ?>d left</span>
                        <?php else: ?>
                            <span class="text-danger fw-semibold"><?= $days ?>d</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-white fw-semibold">RM <?= number_format((float)$inv['amount'],2) ?></td>
                    <td>
                        <span class="badge" style="background:<?= str_replace(['success','warning','danger','orange'],['#10b981','#f59e0b','#ef4444','#fb923c'],$bBadge==='orange'?'#fb923c':($bBadge==='success'?'#10b981':($bBadge==='warning'?'#f59e0b':'#ef4444'))) ?>">
                            <?= $bLabel ?>
                        </span>
                    </td>
                    <td><span class="badge bg-<?= $statusBadge[$inv['status']] ?? 'secondary' ?>"><?= ucfirst($inv['status']) ?></span></td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-primary py-0 px-2"
                                data-bs-toggle="modal" data-bs-target="#noteModal"
                                data-invid="<?= $inv['id'] ?>"
                                data-invnum="INV-<?= str_pad($inv['id'],5,'0',STR_PAD_LEFT) ?>"
                                data-customer="<?= htmlspecialchars($inv['customer_name']) ?>">
                                <i class="bi bi-chat-left-text"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-warning py-0 px-2"
                                data-bs-toggle="modal" data-bs-target="#reminderModal"
                                data-customer="<?= htmlspecialchars($inv['customer_name']) ?>"
                                data-email="<?= htmlspecialchars($inv['customer_email']) ?>"
                                data-amount="RM <?= number_format((float)$inv['amount'],2) ?>">
                                <i class="bi bi-envelope"></i>
                            </button>
                        </div>
                        <?php foreach ($invNotes as $n): ?>
                            <div class="text-muted mt-1" style="font-size:10px">
                                <i class="bi bi-chat-left-fill me-1"></i><?= htmlspecialchars(substr($n['note'],0,60)) ?>
                            </div>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Customer Summary -->
    <div class="glass-card p-4">
        <h6 class="text-white mb-3">Customer Outstanding Summary</h6>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0 small">
                <thead>
                    <tr class="text-muted"><th>Customer</th><th>Email</th><th class="text-end">Total Outstanding</th><th class="text-center">Invoices</th><th class="text-center">Oldest (days)</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($customerSummary as $cs): ?>
                <tr>
                    <td class="fw-semibold text-white"><?= htmlspecialchars($cs['name']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($cs['email']) ?></td>
                    <td class="text-end text-white fw-semibold">RM <?= number_format($cs['total'],2) ?></td>
                    <td class="text-center text-muted"><?= $cs['count'] ?></td>
                    <td class="text-center <?= $cs['max_days'] > 60 ? 'text-danger' : ($cs['max_days'] > 30 ? 'text-warning' : 'text-success') ?>">
                        <?= $cs['max_days'] > 0 ? $cs['max_days'] . 'd' : 'Current' ?>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-warning py-0"
                            data-bs-toggle="modal" data-bs-target="#reminderModal"
                            data-customer="<?= htmlspecialchars($cs['name']) ?>"
                            data-email="<?= htmlspecialchars($cs['email']) ?>"
                            data-amount="RM <?= number_format($cs['total'],2) ?>">
                            <i class="bi bi-envelope me-1"></i>Send Reminder
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Note Modal -->
<div class="modal fade" id="noteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary">
            <form method="POST">
                <input type="hidden" name="action" value="add_note">
                <input type="hidden" name="invoice_id" id="noteInvId">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-white">Add Follow-up Note — <span id="noteInvNum"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Customer</label>
                        <input type="text" id="noteCustomer" class="form-control bg-dark text-white border-secondary" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Note *</label>
                        <textarea name="note" class="form-control bg-dark text-white border-secondary" rows="3" required placeholder="Called customer, promised payment by..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Follow-up Date</label>
                        <input type="date" name="follow_up_date" class="form-control bg-dark text-white border-secondary">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Note</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reminder Modal -->
<div class="modal fade" id="reminderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary">
            <form method="POST">
                <input type="hidden" name="action" value="send_reminder">
                <input type="hidden" name="customer_name" id="reminderName">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-white">Send Payment Reminder</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small">
                        <i class="bi bi-info-circle me-1"></i>
                        A payment reminder will be queued to the customer's email.
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">To</label>
                        <input type="email" id="reminderEmail" class="form-control bg-dark text-white border-secondary" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Outstanding Amount</label>
                        <input type="text" id="reminderAmount" class="form-control bg-dark text-white border-secondary" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Custom Message (optional)</label>
                        <textarea name="custom_message" class="form-control bg-dark text-white border-secondary" rows="3" placeholder="Please settle the outstanding amount at your earliest..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Send Reminder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('noteModal').addEventListener('show.bs.modal', e => {
    const btn = e.relatedTarget;
    document.getElementById('noteInvId').value    = btn.dataset.invid;
    document.getElementById('noteInvNum').textContent  = btn.dataset.invnum;
    document.getElementById('noteCustomer').value = btn.dataset.customer;
});
document.getElementById('reminderModal').addEventListener('show.bs.modal', e => {
    const btn = e.relatedTarget;
    document.getElementById('reminderName').value   = btn.dataset.customer;
    document.getElementById('reminderEmail').value  = btn.dataset.email;
    document.getElementById('reminderAmount').value = btn.dataset.amount;
});
</script>

<?php require_once '../includes/admin-footer.php'; ?>
