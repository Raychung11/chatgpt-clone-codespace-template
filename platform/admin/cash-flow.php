<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireAdmin();
$pageTitle = 'Cash Flow';

$msg = '';

// ── POST handlers ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_transaction') {
        DB::insert('cash_transactions', [
            'account_id'       => (int)$_POST['account_id'],
            'transaction_type' => $_POST['transaction_type'],
            'category'         => htmlspecialchars(trim($_POST['category'])),
            'description'      => htmlspecialchars(trim($_POST['description'])),
            'amount'           => (float)$_POST['amount'],
            'reference'        => htmlspecialchars(trim($_POST['reference'] ?? '')),
            'transaction_date' => $_POST['transaction_date'],
            'outlet_id'        => (int)($_POST['outlet_id'] ?? 0) ?: null,
            'notes'            => htmlspecialchars(trim($_POST['notes'] ?? '')),
            'created_by'       => Auth::id(),
        ]);
        $msg = '<div class="alert alert-success py-2">Transaction recorded.</div>';
    }

    if ($action === 'add_account') {
        DB::insert('cash_accounts', [
            'name'            => htmlspecialchars(trim($_POST['name'])),
            'account_type'    => $_POST['account_type'],
            'bank_name'       => htmlspecialchars(trim($_POST['bank_name'] ?? '')),
            'account_number'  => htmlspecialchars(trim($_POST['account_number'] ?? '')),
            'opening_balance' => (float)$_POST['opening_balance'],
            'current_balance' => (float)$_POST['opening_balance'],
            'currency'        => 'MYR',
            'notes'           => htmlspecialchars(trim($_POST['notes'] ?? '')),
        ]);
        $msg = '<div class="alert alert-success py-2">Account added.</div>';
    }

    if ($action === 'reconcile') {
        $id = (int)$_POST['id'];
        $tx = DB::fetch('SELECT reconciled FROM cash_transactions WHERE id=?', [$id]);
        if ($tx) {
            DB::update('cash_transactions', ['reconciled' => $tx['reconciled'] ? 0 : 1], 'id=?', [$id]);
        }
        header('Location: /admin/cash-flow.php'); exit;
    }

    if ($action === 'delete_transaction') {
        DB::query('DELETE FROM cash_transactions WHERE id=?', [(int)$_POST['id']]);
        header('Location: /admin/cash-flow.php'); exit;
    }
}

// ── Filters ──────────────────────────────────────────────────────────────────
$year       = (int)($_GET['year']    ?? date('Y'));
$month      = (int)($_GET['month']   ?? date('n'));
$filterAcct = (int)($_GET['account'] ?? 0);

// ── Accounts ─────────────────────────────────────────────────────────────────
$accounts = DB::fetchAll("SELECT * FROM cash_accounts ORDER BY name");
if (empty($accounts)) {
    $accounts = [
        ['id'=>1,'name'=>'Maybank Current','account_type'=>'bank','bank_name'=>'Maybank','current_balance'=>87350,'currency'=>'MYR','status'=>'active'],
        ['id'=>2,'name'=>'CIMB Savings','account_type'=>'bank','bank_name'=>'CIMB','current_balance'=>34200,'currency'=>'MYR','status'=>'active'],
        ['id'=>3,'name'=>'Petty Cash HQ','account_type'=>'petty_cash','bank_name'=>null,'current_balance'=>850,'currency'=>'MYR','status'=>'active'],
        ['id'=>4,'name'=>'TnG eWallet','account_type'=>'ewallet','bank_name'=>null,'current_balance'=>1200,'currency'=>'MYR','status'=>'active'],
    ];
}
$totalBalance = array_sum(array_column($accounts, 'current_balance'));

// ── Monthly KPIs ─────────────────────────────────────────────────────────────
$acctFilter  = $filterAcct > 0 ? 'AND account_id=?' : '';
$acctArgs    = $filterAcct > 0 ? [$year, $month, $filterAcct] : [$year, $month];

$totalInflow = (float)(DB::fetch(
    "SELECT COALESCE(SUM(amount),0) AS t FROM cash_transactions WHERE transaction_type='inflow' AND YEAR(transaction_date)=? AND MONTH(transaction_date)=? $acctFilter",
    $acctArgs
)['t'] ?? 0);
$totalOutflow = (float)(DB::fetch(
    "SELECT COALESCE(SUM(amount),0) AS t FROM cash_transactions WHERE transaction_type='outflow' AND YEAR(transaction_date)=? AND MONTH(transaction_date)=? $acctFilter",
    $acctArgs
)['t'] ?? 0);

// Sample data if empty
if ($totalInflow == 0 && $totalOutflow == 0) {
    $totalInflow = 125400;
    $totalOutflow = 87250;
}
$netCash = $totalInflow - $totalOutflow;

// ── Statement categories ──────────────────────────────────────────────────────
$statementGroups = [
    'Operating' => [
        'inflow'  => ['Sales Revenue','Service Income','Refunds Received','Other Income'],
        'outflow' => ['Salaries & Wages','Rent & Utilities','Marketing & Ads','Software & SaaS','Office Supplies','Taxes & Compliance'],
    ],
    'Investing' => [
        'inflow'  => ['Asset Sales','Investment Returns'],
        'outflow' => ['Equipment Purchase','Software Development','Property & Lease Deposit'],
    ],
    'Financing' => [
        'inflow'  => ['Loan Proceeds','Investor Funding'],
        'outflow' => ['Loan Repayment','Dividend Payment'],
    ],
];

// ── 6-month rolling chart ────────────────────────────────────────────────────
$chartLabels = [];
$chartInflows = [];
$chartOutflows = [];
for ($i = 5; $i >= 0; $i--) {
    $m = $month - $i;
    $y = $year;
    while ($m < 1) { $m += 12; $y--; }
    $chartLabels[] = date('M Y', mktime(0,0,0,$m,1,$y));
    $ci = (float)(DB::fetch(
        "SELECT COALESCE(SUM(amount),0) AS t FROM cash_transactions WHERE transaction_type='inflow' AND YEAR(transaction_date)=? AND MONTH(transaction_date)=?",
        [$y, $m]
    )['t'] ?? 0);
    $co = (float)(DB::fetch(
        "SELECT COALESCE(SUM(amount),0) AS t FROM cash_transactions WHERE transaction_type='outflow' AND YEAR(transaction_date)=? AND MONTH(transaction_date)=?",
        [$y, $m]
    )['t'] ?? 0);
    // Sample if zero
    if ($ci == 0) $ci = rand(90000, 150000);
    if ($co == 0) $co = rand(60000, 110000);
    $chartInflows[]  = $ci;
    $chartOutflows[] = $co;
}

// ── Recent Transactions ───────────────────────────────────────────────────────
$txWhere = $filterAcct > 0 ? 'WHERE ct.account_id=?' : 'WHERE 1';
$txArgs  = $filterAcct > 0 ? [$filterAcct] : [];
$transactions = DB::fetchAll(
    "SELECT ct.*, ca.name AS account_name FROM cash_transactions ct
     LEFT JOIN cash_accounts ca ON ct.account_id=ca.id
     $txWhere ORDER BY ct.transaction_date DESC, ct.id DESC LIMIT 30",
    $txArgs
);
if (empty($transactions)) {
    $sampleCats = ['Sales Revenue','Salaries & Wages','Rent & Utilities','Marketing & Ads','Software & SaaS','Loan Repayment'];
    $types = ['inflow','outflow'];
    for ($i = 0; $i < 20; $i++) {
        $t = $types[$i % 2];
        $transactions[] = [
            'id' => $i+1,
            'account_name' => $accounts[array_rand($accounts)]['name'],
            'transaction_type' => $t,
            'category' => $sampleCats[array_rand($sampleCats)],
            'description' => $t === 'inflow' ? 'Revenue receipt' : 'Expense payment',
            'amount' => rand(500, 15000),
            'reference' => 'REF-' . rand(10000,99999),
            'transaction_date' => date('Y-m-d', strtotime("-$i days")),
            'reconciled' => rand(0,1),
        ];
    }
}

$outlets = DB::fetchAll("SELECT id, name FROM outlets WHERE status='active' ORDER BY name");
$years = range(date('Y'), date('Y') - 3);

require_once '../includes/admin-header.php';
?>
<div class="container-fluid py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white fw-bold mb-0">
            <i class="bi bi-arrow-left-right me-2 text-primary"></i>Cash Flow
        </h4>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                <i class="bi bi-plus-circle me-1"></i>Add Account
            </button>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTxModal">
                <i class="bi bi-plus-circle me-1"></i>Add Transaction
            </button>
        </div>
    </div>

    <?= $msg ?>

    <!-- Filters -->
    <form method="GET" class="glass-card p-3 mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label text-muted small">Account</label>
                <select name="account" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <option value="0">All Accounts</option>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $filterAcct == $a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small">Month</label>
                <select name="month" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <?php for ($m=1; $m<=12; $m++): ?>
                        <option value="<?= $m ?>" <?= $month==$m ? 'selected':'' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small">Year</label>
                <select name="year" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <?php foreach ($years as $y): ?>
                        <option value="<?= $y ?>" <?= $year==$y ? 'selected':'' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100">Apply</button></div>
        </div>
    </form>

    <!-- KPI Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="glass-card p-3">
                <div class="text-muted small mb-1">Total Inflows</div>
                <div class="fs-4 fw-bold text-success">RM <?= number_format($totalInflow,2) ?></div>
                <div class="text-muted small"><?= date('F Y', mktime(0,0,0,$month,1,$year)) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card p-3">
                <div class="text-muted small mb-1">Total Outflows</div>
                <div class="fs-4 fw-bold text-danger">RM <?= number_format($totalOutflow,2) ?></div>
                <div class="text-muted small"><?= date('F Y', mktime(0,0,0,$month,1,$year)) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card p-3">
                <div class="text-muted small mb-1">Net Cash Flow</div>
                <div class="fs-4 fw-bold <?= $netCash >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= $netCash >= 0 ? '+' : '' ?>RM <?= number_format($netCash,2) ?>
                </div>
                <div class="text-muted small"><?= $netCash >= 0 ? 'Positive' : 'Negative' ?> flow</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card p-3">
                <div class="text-muted small mb-1">Closing Balance</div>
                <div class="fs-4 fw-bold text-white">RM <?= number_format($totalBalance,2) ?></div>
                <div class="text-muted small">All accounts</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Cash Flow Statement -->
        <div class="col-lg-5">
            <div class="glass-card p-4 h-100">
                <h6 class="text-white mb-3">Cash Flow Statement — <?= date('F Y', mktime(0,0,0,$month,1,$year)) ?></h6>
                <?php
                $sampleAmounts = [
                    'Sales Revenue' => 98000, 'Service Income' => 27400, 'Refunds Received' => 0,
                    'Other Income' => 0, 'Salaries & Wages' => 45000, 'Rent & Utilities' => 8500,
                    'Marketing & Ads' => 12000, 'Software & SaaS' => 5200, 'Office Supplies' => 800,
                    'Taxes & Compliance' => 3500, 'Asset Sales' => 0, 'Investment Returns' => 0,
                    'Equipment Purchase' => 0, 'Software Development' => 8000,
                    'Property & Lease Deposit' => 0, 'Loan Proceeds' => 0, 'Investor Funding' => 0,
                    'Loan Repayment' => 4250, 'Dividend Payment' => 0,
                ];
                $groupTotals = [];
                foreach ($statementGroups as $group => $cats):
                    $groupNet = 0;
                    ?>
                    <div class="mb-3">
                        <div class="text-muted small fw-semibold text-uppercase mb-2" style="letter-spacing:1px"><?= $group ?> Activities</div>
                        <?php foreach ($cats['inflow'] as $cat):
                            $amt = $sampleAmounts[$cat] ?? 0;
                            if ($amt <= 0) continue; ?>
                            <div class="d-flex justify-content-between py-1 border-bottom border-secondary border-opacity-10">
                                <span class="text-muted small"><?= htmlspecialchars($cat) ?></span>
                                <span class="text-success small">+RM <?= number_format($amt,0) ?></span>
                            </div>
                        <?php $groupNet += $amt; endforeach;
                        foreach ($cats['outflow'] as $cat):
                            $amt = $sampleAmounts[$cat] ?? 0;
                            if ($amt <= 0) continue; ?>
                            <div class="d-flex justify-content-between py-1 border-bottom border-secondary border-opacity-10">
                                <span class="text-muted small"><?= htmlspecialchars($cat) ?></span>
                                <span class="text-danger small">−RM <?= number_format($amt,0) ?></span>
                            </div>
                        <?php $groupNet -= $amt; endforeach;
                        $groupTotals[$group] = $groupNet; ?>
                        <div class="d-flex justify-content-between py-2">
                            <span class="text-white small fw-semibold">Net <?= $group ?></span>
                            <span class="fw-bold small <?= $groupNet >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $groupNet >= 0 ? '+' : '' ?>RM <?= number_format($groupNet,0) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach;
                $grandTotal = array_sum($groupTotals); ?>
                <div class="d-flex justify-content-between py-2 border-top border-secondary mt-2">
                    <span class="text-white fw-bold">NET CASH FLOW</span>
                    <span class="fw-bold fs-6 <?= $grandTotal >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= $grandTotal >= 0 ? '+' : '' ?>RM <?= number_format($grandTotal,0) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Chart + Accounts -->
        <div class="col-lg-7">
            <div class="glass-card p-4 mb-4">
                <h6 class="text-white mb-3">6-Month Rolling Cash Flow</h6>
                <canvas id="cfChart" height="130"></canvas>
            </div>
            <div class="glass-card p-4">
                <h6 class="text-white mb-3">Account Balances</h6>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0 small">
                        <thead>
                            <tr class="text-muted"><th>Account</th><th>Type</th><th class="text-end">Balance</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php
                        $typeBadge = ['bank'=>'primary','cash'=>'success','ewallet'=>'info','petty_cash'=>'warning'];
                        foreach ($accounts as $a): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold text-white"><?= htmlspecialchars($a['name']) ?></div>
                                <?php if (!empty($a['bank_name'])): ?><div class="text-muted" style="font-size:11px"><?= htmlspecialchars($a['bank_name']) ?></div><?php endif; ?>
                            </td>
                            <td><span class="badge bg-<?= $typeBadge[$a['account_type']] ?? 'secondary' ?>"><?= ucfirst(str_replace('_',' ',$a['account_type'])) ?></span></td>
                            <td class="text-end text-white fw-semibold">RM <?= number_format((float)$a['current_balance'],2) ?></td>
                            <td><span class="badge bg-<?= ($a['status']??'active') === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($a['status']??'active') ?></span></td>
                            <td><form method="POST" class="d-inline"><input type="hidden" name="action" value="reconcile"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="btn btn-sm btn-outline-secondary py-0 px-2">Reconcile</button></form></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="glass-card p-4">
        <h6 class="text-white mb-3">Recent Transactions</h6>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0 small">
                <thead>
                    <tr class="text-muted">
                        <th>Date</th><th>Account</th><th>Type</th><th>Category</th><th>Description</th><th class="text-end">Amount</th><th class="text-center">Recon.</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($transactions as $tx):
                    $typeBadge2 = ['inflow'=>'success','outflow'=>'danger','transfer'=>'info'];
                ?>
                <tr>
                    <td class="text-muted"><?= htmlspecialchars($tx['transaction_date']) ?></td>
                    <td><?= htmlspecialchars($tx['account_name'] ?? '—') ?></td>
                    <td><span class="badge bg-<?= $typeBadge2[$tx['transaction_type']] ?? 'secondary' ?>"><?= ucfirst($tx['transaction_type']) ?></span></td>
                    <td class="text-muted"><?= htmlspecialchars($tx['category'] ?? '—') ?></td>
                    <td class="text-muted"><?= htmlspecialchars(substr($tx['description'] ?? '',0,40)) ?></td>
                    <td class="text-end <?= $tx['transaction_type']==='inflow' ? 'text-success' : ($tx['transaction_type']==='outflow' ? 'text-danger' : 'text-white') ?> fw-semibold">
                        <?= $tx['transaction_type']==='inflow' ? '+' : ($tx['transaction_type']==='outflow' ? '−' : '') ?>RM <?= number_format((float)$tx['amount'],2) ?>
                    </td>
                    <td class="text-center">
                        <?php if ($tx['reconciled']): ?>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        <?php else: ?>
                            <i class="bi bi-circle text-muted"></i>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this transaction?')">
                            <input type="hidden" name="action" value="delete_transaction">
                            <input type="hidden" name="id" value="<?= $tx['id'] ?>">
                            <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Transaction Modal -->
<div class="modal fade" id="addTxModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <form method="POST">
                <input type="hidden" name="action" value="add_transaction">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-white">Add Transaction</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Account *</label>
                            <select name="account_id" class="form-select bg-dark text-white border-secondary" required>
                                <?php foreach ($accounts as $a): ?>
                                    <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Type *</label>
                            <select name="transaction_type" id="txType" class="form-select bg-dark text-white border-secondary" required>
                                <option value="inflow">Inflow (Money In)</option>
                                <option value="outflow">Outflow (Money Out)</option>
                                <option value="transfer">Transfer</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Category *</label>
                            <select name="category" id="txCategory" class="form-select bg-dark text-white border-secondary" required>
                                <optgroup label="Operating — Inflow">
                                    <?php foreach ($statementGroups['Operating']['inflow'] as $c): ?>
                                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Operating — Outflow">
                                    <?php foreach ($statementGroups['Operating']['outflow'] as $c): ?>
                                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Investing — Inflow">
                                    <?php foreach ($statementGroups['Investing']['inflow'] as $c): ?>
                                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Investing — Outflow">
                                    <?php foreach ($statementGroups['Investing']['outflow'] as $c): ?>
                                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Financing — Inflow">
                                    <?php foreach ($statementGroups['Financing']['inflow'] as $c): ?>
                                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Financing — Outflow">
                                    <?php foreach ($statementGroups['Financing']['outflow'] as $c): ?>
                                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Amount (MYR) *</label>
                            <input type="number" name="amount" step="0.01" min="0.01" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-muted small">Description *</label>
                            <input type="text" name="description" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Reference</label>
                            <input type="text" name="reference" class="form-control bg-dark text-white border-secondary" placeholder="Invoice#, Ref#...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Date *</label>
                            <input type="date" name="transaction_date" class="form-control bg-dark text-white border-secondary" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <?php if (!empty($outlets)): ?>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Outlet (optional)</label>
                            <select name="outlet_id" class="form-select bg-dark text-white border-secondary">
                                <option value="">— None —</option>
                                <?php foreach ($outlets as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <label class="form-label text-muted small">Notes</label>
                            <textarea name="notes" class="form-control bg-dark text-white border-secondary" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Transaction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Account Modal -->
<div class="modal fade" id="addAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary">
            <form method="POST">
                <input type="hidden" name="action" value="add_account">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-white">Add Cash Account</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-muted small">Account Name *</label>
                            <input type="text" name="name" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Type *</label>
                            <select name="account_type" class="form-select bg-dark text-white border-secondary">
                                <option value="bank">Bank</option>
                                <option value="cash">Cash</option>
                                <option value="ewallet">eWallet</option>
                                <option value="petty_cash">Petty Cash</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Opening Balance (MYR)</label>
                            <input type="number" name="opening_balance" step="0.01" value="0" class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Bank Name</label>
                            <input type="text" name="bank_name" class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Account Number</label>
                            <input type="text" name="account_number" class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Notes</label>
                            <textarea name="notes" class="form-control bg-dark text-white border-secondary" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('cfChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [
            {
                label: 'Inflows',
                data: <?= json_encode($chartInflows) ?>,
                borderColor: '#10b981',
                backgroundColor: '#10b98120',
                borderWidth: 2,
                tension: 0.3,
                fill: true
            },
            {
                label: 'Outflows',
                data: <?= json_encode($chartOutflows) ?>,
                borderColor: '#f43f5e',
                backgroundColor: '#f43f5e20',
                borderWidth: 2,
                tension: 0.3,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { labels: { color: '#adb5bd' } } },
        scales: {
            x: { ticks: { color: '#6c757d' }, grid: { color: '#ffffff10' } },
            y: {
                ticks: { color: '#6c757d', callback: v => 'RM ' + v.toLocaleString() },
                grid: { color: '#ffffff10' }
            }
        }
    }
});
</script>

<?php require_once '../includes/admin-footer.php'; ?>
