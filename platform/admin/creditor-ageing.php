<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireAdmin();
$pageTitle = 'Creditor Ageing';

$msg = '';

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_invoice') {
        $invDate = $_POST['invoice_date'];
        $terms   = (int)($_POST['payment_terms'] ?? 30);
        $dueDate = $_POST['due_date'] ?: date('Y-m-d', strtotime("$invDate +{$terms} days"));
        DB::insert('supplier_invoices', [
            'supplier_id'    => (int)$_POST['supplier_id'],
            'po_id'          => (int)($_POST['po_id'] ?? 0) ?: null,
            'invoice_number' => htmlspecialchars(trim($_POST['invoice_number'])),
            'invoice_date'   => $invDate,
            'due_date'       => $dueDate,
            'amount'         => (float)$_POST['amount'],
            'paid_amount'    => 0,
            'status'         => 'unpaid',
            'payment_terms'  => $terms,
            'notes'          => htmlspecialchars(trim($_POST['notes'] ?? '')),
        ]);
        $msg = '<div class="alert alert-success py-2">Supplier invoice added.</div>';
    }

    if ($action === 'record_payment') {
        $id      = (int)$_POST['invoice_id'];
        $payAmt  = (float)$_POST['paid_amount'];
        $inv     = DB::fetch('SELECT * FROM supplier_invoices WHERE id=?', [$id]);
        if ($inv) {
            $newPaid = (float)$inv['paid_amount'] + $payAmt;
            $status  = $newPaid >= (float)$inv['amount'] ? 'paid' : 'partial';
            DB::update('supplier_invoices', ['paid_amount'=>$newPaid,'status'=>$status], 'id=?', [$id]);
            $msg = '<div class="alert alert-success py-2">Payment recorded.</div>';
        }
    }

    if ($action === 'delete_invoice') {
        DB::query('DELETE FROM supplier_invoices WHERE id=?', [(int)$_POST['id']]);
        header('Location: /admin/creditor-ageing.php'); exit;
    }
}

// ── CSV Export ────────────────────────────────────────────────────────────────
if (($_GET['action'] ?? '') === 'export') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="creditor-ageing-' . date('Y-m-d') . '.csv"');
    echo "Supplier,Invoice #,Invoice Date,Due Date,Days Overdue,Amount,Paid,Outstanding,Bucket,Status\n";
    $rows = DB::fetchAll(
        "SELECT si.*, s.name AS supplier_name
         FROM supplier_invoices si JOIN suppliers s ON si.supplier_id=s.id
         WHERE si.status IN ('unpaid','partial','overdue')
         ORDER BY DATEDIFF(CURDATE(), si.due_date) DESC"
    );
    foreach ($rows as $r) {
        $days  = (int)(DB::fetch('SELECT DATEDIFF(CURDATE(),?) AS d', [$r['due_date']])['d'] ?? 0);
        $outstanding = (float)$r['amount'] - (float)$r['paid_amount'];
        $bucket = $days <= 0 ? 'Current' : ($days<=30?'1–30 Days':($days<=60?'31–60 Days':($days<=90?'61–90 Days':'90+ Days')));
        echo implode(',', [
            '"' . str_replace('"','""',$r['supplier_name']) . '"',
            '"' . str_replace('"','""',$r['invoice_number']) . '"',
            $r['invoice_date'], $r['due_date'], max(0,$days),
            number_format((float)$r['amount'],2),
            number_format((float)$r['paid_amount'],2),
            number_format($outstanding,2),
            $bucket, $r['status']
        ]) . "\n";
    }
    exit;
}

// ── Filters ──────────────────────────────────────────────────────────────────
$bucketFilter = $_GET['bucket'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$search       = trim($_GET['search'] ?? '');

// ── Fetch supplier invoices ───────────────────────────────────────────────────
$invRows = DB::fetchAll(
    "SELECT si.*, s.name AS supplier_name, s.email AS supplier_email, s.payment_terms AS sup_terms,
            po.po_number,
            DATEDIFF(CURDATE(), si.due_date) AS days_over
     FROM supplier_invoices si
     JOIN suppliers s ON si.supplier_id=s.id
     LEFT JOIN purchase_orders po ON si.po_id=po.id
     WHERE si.status IN ('unpaid','partial','overdue')
     ORDER BY days_over DESC, si.due_date ASC"
);

$useSample = empty($invRows);
if ($useSample) {
    $invRows = [
        ['id'=>1,'supplier_name'=>'CloudTech Solutions','supplier_email'=>'billing@cloudtech.com','invoice_number'=>'CT-2025-0089','invoice_date'=>date('Y-m-d',strtotime('-5 days')),'due_date'=>date('Y-m-d',strtotime('+25 days')),'amount'=>3500.00,'paid_amount'=>0,'status'=>'unpaid','days_over'=>-25,'po_number'=>'PO-202501-0001','sup_terms'=>'Net 30'],
        ['id'=>2,'supplier_name'=>'Office Pro Supplies','supplier_email'=>'ar@officepro.my','invoice_number'=>'OPS-8821','invoice_date'=>date('Y-m-d',strtotime('-40 days')),'due_date'=>date('Y-m-d',strtotime('-10 days')),'amount'=>870.00,'paid_amount'=>400.00,'status'=>'partial','days_over'=>10,'po_number'=>null,'sup_terms'=>'Net 30'],
        ['id'=>3,'supplier_name'=>'Infra Systems Bhd','supplier_email'=>'accounts@infrasys.com.my','invoice_number'=>'IS-2025-441','invoice_date'=>date('Y-m-d',strtotime('-65 days')),'due_date'=>date('Y-m-d',strtotime('-35 days')),'amount'=>12500.00,'paid_amount'=>0,'status'=>'overdue','days_over'=>35,'po_number'=>'PO-202502-0005','sup_terms'=>'Net 30'],
        ['id'=>4,'supplier_name'=>'Cleaning Masters','supplier_email'=>'invoice@cleaningmasters.my','invoice_number'=>'CM-445','invoice_date'=>date('Y-m-d',strtotime('-90 days')),'due_date'=>date('Y-m-d',strtotime('-60 days')),'amount'=>1800.00,'paid_amount'=>0,'status'=>'overdue','days_over'=>60,'po_number'=>null,'sup_terms'=>'Net 30'],
        ['id'=>5,'supplier_name'=>'CloudTech Solutions','supplier_email'=>'billing@cloudtech.com','invoice_number'=>'CT-2025-0055','invoice_date'=>date('Y-m-d',strtotime('-125 days')),'due_date'=>date('Y-m-d',strtotime('-95 days')),'amount'=>4200.00,'paid_amount'=>0,'status'=>'overdue','days_over'=>95,'po_number'=>null,'sup_terms'=>'Net 30'],
        ['id'=>6,'supplier_name'=>'MegaPrint Sdn Bhd','supplier_email'=>'billing@megaprint.com.my','invoice_number'=>'MP-2024-992','invoice_date'=>date('Y-m-d',strtotime('-160 days')),'due_date'=>date('Y-m-d',strtotime('-130 days')),'amount'=>6750.00,'paid_amount'=>1000.00,'status'=>'overdue','days_over'=>130,'po_number'=>'PO-202412-0012','sup_terms'=>'Net 30'],
        ['id'=>7,'supplier_name'=>'Infra Systems Bhd','supplier_email'=>'accounts@infrasys.com.my','invoice_number'=>'IS-2025-380','invoice_date'=>date('Y-m-d',strtotime('-20 days')),'due_date'=>date('Y-m-d',strtotime('+10 days')),'amount'=>8900.00,'paid_amount'=>0,'status'=>'unpaid','days_over'=>-10,'po_number'=>'PO-202503-0002','sup_terms'=>'Net 30'],
        ['id'=>8,'supplier_name'=>'Office Pro Supplies','supplier_email'=>'ar@officepro.my','invoice_number'=>'OPS-8945','invoice_date'=>date('Y-m-d',strtotime('-50 days')),'due_date'=>date('Y-m-d',strtotime('-20 days')),'amount'=>450.00,'paid_amount'=>0,'status'=>'overdue','days_over'=>20,'po_number'=>null,'sup_terms'=>'Net 30'],
    ];
}

// ── Ageing buckets ─────────────────────────────────────────────────────────
$buckets = ['current'=>0,'b1_30'=>0,'b31_60'=>0,'b61_90'=>0,'b90plus'=>0];
foreach ($invRows as $inv) {
    $outstanding = (float)$inv['amount'] - (float)$inv['paid_amount'];
    $d = (int)$inv['days_over'];
    if ($d <= 0)      $buckets['current'] += $outstanding;
    elseif ($d <= 30) $buckets['b1_30']   += $outstanding;
    elseif ($d <= 60) $buckets['b31_60']  += $outstanding;
    elseif ($d <= 90) $buckets['b61_90']  += $outstanding;
    else              $buckets['b90plus'] += $outstanding;
}
$totalPayable = array_sum($buckets);

// ── Apply filters ──────────────────────────────────────────────────────────
$filtered = array_filter($invRows, function($inv) use ($bucketFilter,$statusFilter,$search) {
    $d = (int)$inv['days_over'];
    if ($bucketFilter !== 'all') {
        if ($bucketFilter === 'current' && $d > 0) return false;
        if ($bucketFilter === '1-30'   && !($d>0 && $d<=30)) return false;
        if ($bucketFilter === '31-60'  && !($d>30 && $d<=60)) return false;
        if ($bucketFilter === '61-90'  && !($d>60 && $d<=90)) return false;
        if ($bucketFilter === '90plus' && $d<=90) return false;
    }
    if ($statusFilter !== 'all' && $inv['status'] !== $statusFilter) return false;
    if ($search && stripos($inv['supplier_name'],$search)===false && stripos($inv['supplier_email'],$search)===false) return false;
    return true;
});

// ── Supplier summary ──────────────────────────────────────────────────────
$supplierSummary = [];
foreach ($invRows as $inv) {
    $k = $inv['supplier_email'];
    $outstanding = (float)$inv['amount'] - (float)$inv['paid_amount'];
    if (!isset($supplierSummary[$k])) {
        $supplierSummary[$k] = ['name'=>$inv['supplier_name'],'email'=>$inv['supplier_email'],'total'=>0,'count'=>0,'max_days'=>0,'terms'=>$inv['sup_terms']??'Net 30'];
    }
    $supplierSummary[$k]['total'] += $outstanding;
    $supplierSummary[$k]['count']++;
    $supplierSummary[$k]['max_days'] = max($supplierSummary[$k]['max_days'], (int)$inv['days_over']);
}
usort($supplierSummary, fn($a,$b) => $b['total'] <=> $a['total']);

// ── Data for modals ──────────────────────────────────────────────────────
$suppliers = DB::fetchAll("SELECT id, name FROM suppliers WHERE status='active' ORDER BY name");
if (empty($suppliers)) $suppliers = [['id'=>1,'name'=>'Sample Supplier']];
$purchaseOrders = DB::fetchAll("SELECT id, po_number FROM purchase_orders ORDER BY id DESC LIMIT 50");

require_once '../includes/admin-header.php';

function getCredBucketInfo(int $days): array {
    if ($days <= 0)  return ['Current',   '#10b981', 'rgba(16,185,129,0.1)'];
    if ($days <= 30) return ['1–30 Days',  '#f59e0b', 'rgba(245,158,11,0.1)'];
    if ($days <= 60) return ['31–60 Days', '#fb923c', 'rgba(251,146,60,0.15)'];
    if ($days <= 90) return ['61–90 Days', '#ef4444', 'rgba(220,53,69,0.12)'];
    return           ['90+ Days',  '#991b1b', 'rgba(220,53,69,0.25)'];
}
?>
<div class="container-fluid py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white fw-bold mb-0">
            <i class="bi bi-building-exclamation me-2 text-primary"></i>Creditor Ageing (AP)
        </h4>
        <div class="d-flex gap-2">
            <a href="?action=export" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>Export CSV</a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addInvModal">
                <i class="bi bi-plus-circle me-1"></i>Add Supplier Invoice
            </button>
        </div>
    </div>

    <?= $msg ?>

    <!-- KPI Row -->
    <div class="row g-3 mb-4">
        <div class="col">
            <div class="glass-card p-3 text-center">
                <div class="text-muted small">Total Payable</div>
                <div class="fs-5 fw-bold text-white">RM <?= number_format($totalPayable,2) ?></div>
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
    <?php if ($totalPayable > 0): ?>
    <div class="glass-card p-3 mb-4">
        <div class="text-muted small mb-2">Payables Distribution</div>
        <div class="progress" style="height:22px; border-radius:6px">
            <?php
            $barData = [
                ['Current','#10b981',$buckets['current']],
                ['1–30d','#f59e0b',$buckets['b1_30']],
                ['31–60d','#fb923c',$buckets['b31_60']],
                ['61–90d','#ef4444',$buckets['b61_90']],
                ['90+d','#991b1b',$buckets['b90plus']],
            ];
            foreach ($barData as [$label,$color,$val]):
                $pct = $totalPayable > 0 ? ($val/$totalPayable*100) : 0;
                if ($pct < 0.5) continue;
            ?>
            <div class="progress-bar" style="width:<?= number_format($pct,1) ?>%;background:<?= $color ?>;font-size:11px">
                <?= number_format($pct,0) ?>%
            </div>
            <?php endforeach; ?>
        </div>
        <div class="d-flex gap-3 mt-2">
            <?php foreach ($barData as [$l,$c,$v]): ?>
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
                    <option value="unpaid" <?= $statusFilter==='unpaid'?'selected':'' ?>>Unpaid</option>
                    <option value="partial" <?= $statusFilter==='partial'?'selected':'' ?>>Partial</option>
                    <option value="overdue" <?= $statusFilter==='overdue'?'selected':'' ?>>Overdue</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted small">Search Supplier</label>
                <input type="text" name="search" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= htmlspecialchars($search) ?>" placeholder="Supplier name or email…">
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100">Filter</button></div>
        </div>
    </form>

    <!-- Ageing Table -->
    <div class="glass-card p-4 mb-4">
        <h6 class="text-white mb-3">Ageing Detail <?= $useSample ? '— <span class="badge bg-secondary">Sample Data</span>' : '' ?></h6>
        <div class="table-responsive">
            <table class="table table-dark align-middle mb-0 small">
                <thead>
                    <tr class="text-muted">
                        <th>Supplier</th><th>Invoice #</th><th>PO #</th><th>Invoice Date</th>
                        <th>Due Date</th><th class="text-center">Days Over</th>
                        <th class="text-end">Amount</th><th class="text-end">Paid</th>
                        <th class="text-end">Outstanding</th><th>Bucket</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($filtered as $inv):
                    $days = (int)$inv['days_over'];
                    $outstanding = (float)$inv['amount'] - (float)$inv['paid_amount'];
                    [$bLabel, $bColor, $bBg] = getCredBucketInfo($days);
                    $statusBadge = ['unpaid'=>'secondary','partial'=>'warning','overdue'=>'danger','paid'=>'success','disputed'=>'info'];
                ?>
                <tr style="background:<?= $bBg ?>">
                    <td>
                        <div class="fw-semibold text-white"><?= htmlspecialchars($inv['supplier_name']) ?></div>
                        <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($inv['supplier_email']) ?></div>
                    </td>
                    <td class="font-monospace text-muted"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                    <td class="text-muted"><?= !empty($inv['po_number']) ? htmlspecialchars($inv['po_number']) : '—' ?></td>
                    <td class="text-muted"><?= htmlspecialchars($inv['invoice_date']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($inv['due_date']) ?></td>
                    <td class="text-center">
                        <?php if ($days <= 0): ?>
                            <span class="text-success fw-semibold"><?= abs($days) ?>d left</span>
                        <?php else: ?>
                            <span class="text-danger fw-semibold"><?= $days ?>d</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-muted">RM <?= number_format((float)$inv['amount'],2) ?></td>
                    <td class="text-end text-success">RM <?= number_format((float)$inv['paid_amount'],2) ?></td>
                    <td class="text-end text-white fw-semibold">RM <?= number_format($outstanding,2) ?></td>
                    <td><span class="badge" style="background:<?= $bColor ?>"><?= $bLabel ?></span></td>
                    <td><span class="badge bg-<?= $statusBadge[$inv['status']] ?? 'secondary' ?>"><?= ucfirst($inv['status']) ?></span></td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-success py-0 px-2"
                                data-bs-toggle="modal" data-bs-target="#payModal"
                                data-invid="<?= $inv['id'] ?>"
                                data-invnum="<?= htmlspecialchars($inv['invoice_number']) ?>"
                                data-outstanding="<?= number_format($outstanding,2) ?>">
                                <i class="bi bi-cash"></i>
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this invoice?')">
                                <input type="hidden" name="action" value="delete_invoice">
                                <input type="hidden" name="id" value="<?= $inv['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger py-0 px-2"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="text-muted fw-semibold border-top border-secondary">
                        <td colspan="8" class="text-end">Total Outstanding:</td>
                        <td class="text-end text-white fw-bold">RM <?= number_format($totalPayable,2) ?></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Supplier Summary -->
    <div class="glass-card p-4">
        <h6 class="text-white mb-3">Supplier Outstanding Summary</h6>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0 small">
                <thead>
                    <tr class="text-muted">
                        <th>Supplier</th><th>Payment Terms</th><th class="text-end">Total Outstanding</th>
                        <th class="text-center">Invoices</th><th class="text-center">Oldest (days)</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($supplierSummary as $ss): ?>
                <tr>
                    <td>
                        <div class="fw-semibold text-white"><?= htmlspecialchars($ss['name']) ?></div>
                        <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($ss['email']) ?></div>
                    </td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($ss['terms']) ?></span></td>
                    <td class="text-end text-white fw-semibold">RM <?= number_format($ss['total'],2) ?></td>
                    <td class="text-center text-muted"><?= $ss['count'] ?></td>
                    <td class="text-center <?= $ss['max_days']>60?'text-danger':($ss['max_days']>30?'text-warning':'text-success') ?>">
                        <?= $ss['max_days'] > 0 ? $ss['max_days'].'d overdue' : 'Not due' ?>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary py-0"
                            data-bs-toggle="modal" data-bs-target="#addInvModal"
                            title="Schedule Payment">
                            <i class="bi bi-calendar-check me-1"></i>Schedule
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Supplier Invoice Modal -->
<div class="modal fade" id="addInvModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <form method="POST">
                <input type="hidden" name="action" value="add_invoice">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-white">Add Supplier Invoice</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Supplier *</label>
                            <select name="supplier_id" class="form-select bg-dark text-white border-secondary" required>
                                <option value="">— Select —</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Purchase Order (optional)</label>
                            <select name="po_id" class="form-select bg-dark text-white border-secondary">
                                <option value="">— None —</option>
                                <?php foreach ($purchaseOrders as $po): ?>
                                    <option value="<?= $po['id'] ?>"><?= htmlspecialchars($po['po_number']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Invoice Number *</label>
                            <input type="text" name="invoice_number" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Amount (MYR) *</label>
                            <input type="number" name="amount" step="0.01" min="0.01" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Invoice Date *</label>
                            <input type="date" name="invoice_date" class="form-control bg-dark text-white border-secondary" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Payment Terms</label>
                            <select name="payment_terms" class="form-select bg-dark text-white border-secondary">
                                <option value="7">Net 7</option>
                                <option value="15">Net 15</option>
                                <option value="30" selected>Net 30</option>
                                <option value="45">Net 45</option>
                                <option value="60">Net 60</option>
                                <option value="90">Net 90</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small">Due Date (override)</label>
                            <input type="date" name="due_date" class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Notes</label>
                            <textarea name="notes" class="form-control bg-dark text-white border-secondary" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary">
            <form method="POST">
                <input type="hidden" name="action" value="record_payment">
                <input type="hidden" name="invoice_id" id="payInvId">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-white">Record Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Invoice</label>
                        <input type="text" id="payInvNum" class="form-control bg-dark text-white border-secondary" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Outstanding Amount</label>
                        <input type="text" id="payOutstanding" class="form-control bg-dark text-white border-secondary" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Payment Amount (MYR) *</label>
                        <input type="number" name="paid_amount" step="0.01" min="0.01" class="form-control bg-dark text-white border-secondary" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Payment Date</label>
                        <input type="date" name="payment_date" class="form-control bg-dark text-white border-secondary" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Reference</label>
                        <input type="text" name="reference" class="form-control bg-dark text-white border-secondary" placeholder="Cheque#, Transfer ref…">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('payModal').addEventListener('show.bs.modal', e => {
    const btn = e.relatedTarget;
    document.getElementById('payInvId').value      = btn.dataset.invid;
    document.getElementById('payInvNum').value     = btn.dataset.invnum;
    document.getElementById('payOutstanding').value = 'RM ' + btn.dataset.outstanding;
});
</script>

<?php require_once '../includes/admin-footer.php'; ?>
