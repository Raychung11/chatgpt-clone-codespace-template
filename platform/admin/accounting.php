<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();
$pageTitle = 'Accounting';

// Period filter
$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? 0); // 0 = full year

$periodSql  = $month > 0 ? 'YEAR(s.created_at)=? AND MONTH(s.created_at)=?' : 'YEAR(s.created_at)=?';
$periodArgs = $month > 0 ? [$year, $month] : [$year];

// ── Revenue from subscriptions ───────────────────────────────────────────────
$subRevenue = (float)(DB::fetch(
    "SELECT COALESCE(SUM(amount),0) AS t FROM subscriptions s
     WHERE status IN ('active','cancelled') AND $periodSql",
    $periodArgs
)['t'] ?? 0);

// Revenue from one-time purchases
$purchaseRevenue = (float)(DB::fetch(
    "SELECT COALESCE(SUM(p.amount),0) AS t FROM purchases p
     WHERE p.status='completed'
       AND " . ($month > 0 ? 'YEAR(p.created_at)=? AND MONTH(p.created_at)=?' : 'YEAR(p.created_at)=?'),
    $periodArgs
)['t'] ?? 0);

$grossRevenue = $subRevenue + $purchaseRevenue;

// ── Expenses ─────────────────────────────────────────────────────────────────
$expPeriodSql  = $month > 0 ? 'YEAR(expense_date)=? AND MONTH(expense_date)=?' : 'YEAR(expense_date)=?';
$totalExpenses = (float)(DB::fetch(
    "SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE $expPeriodSql",
    $periodArgs
)['t'] ?? 0);

$netProfit = $grossRevenue - $totalExpenses;

// ── Invoice stats ─────────────────────────────────────────────────────────────
$invPeriodSql = $month > 0 ? 'YEAR(issue_date)=? AND MONTH(issue_date)=?' : 'YEAR(issue_date)=?';
$invoiceStats = DB::fetch(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN status='paid' THEN total ELSE 0 END),0)       AS paid,
        COALESCE(SUM(CASE WHEN status='overdue' THEN total ELSE 0 END),0)    AS overdue,
        COALESCE(SUM(CASE WHEN status='sent' THEN total ELSE 0 END),0)       AS outstanding
     FROM invoices WHERE $invPeriodSql",
    $periodArgs
);

// ── Monthly breakdown for chart (always full year) ───────────────────────────
$monthlyRev = DB::fetchAll(
    "SELECT MONTH(created_at) AS m, COALESCE(SUM(amount),0) AS rev
     FROM subscriptions WHERE YEAR(created_at)=? AND status IN ('active','cancelled')
     GROUP BY MONTH(created_at)",
    [$year]
);
$monthlyExp = DB::fetchAll(
    "SELECT MONTH(expense_date) AS m, COALESCE(SUM(amount),0) AS exp
     FROM expenses WHERE YEAR(expense_date)=?
     GROUP BY MONTH(expense_date)",
    [$year]
);

// Build 12-month arrays
$revByMonth = array_fill(1, 12, 0);
$expByMonth = array_fill(1, 12, 0);
foreach ($monthlyRev as $r) $revByMonth[(int)$r['m']] = (float)$r['rev'];
foreach ($monthlyExp as $e) $expByMonth[(int)$e['m']] = (float)$e['exp'];

// ── Expenses by category ─────────────────────────────────────────────────────
$expByCategory = DB::fetchAll(
    "SELECT category, COALESCE(SUM(amount),0) AS total FROM expenses
     WHERE $expPeriodSql GROUP BY category ORDER BY total DESC",
    $periodArgs
);

// ── Recent transactions ───────────────────────────────────────────────────────
$recentTx = DB::fetchAll(
    "SELECT 'subscription' AS type, u.name AS customer, p.name AS product,
            s.amount, s.created_at AS tx_date, s.status
     FROM subscriptions s
     JOIN users u ON s.user_id=u.id
     JOIN products p ON s.product_id=p.id
     WHERE $periodSql
     UNION ALL
     SELECT 'purchase' AS type, u.name AS customer, p.name AS product,
            pu.amount, pu.created_at AS tx_date, pu.status
     FROM purchases pu
     JOIN users u ON pu.user_id=u.id
     JOIN products p ON pu.product_id=p.id
     WHERE " . str_replace('s.created_at', 'pu.created_at', $periodSql) . "
     ORDER BY tx_date DESC LIMIT 15",
    array_merge($periodArgs, $periodArgs)
);

// Available years for filter
$years = DB::fetchAll('SELECT DISTINCT YEAR(created_at) AS y FROM subscriptions ORDER BY y DESC');
if (empty($years)) $years = [['y' => date('Y')]];

$extraHead = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>';
require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <!-- Page header + period filter -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <h4 class="text-white fw-bold mb-0">
            <i class="bi bi-calculator me-2 text-primary"></i>Accounting
            <?php if ($month > 0): ?>
            <span class="text-muted fs-6 fw-normal">— <?= date('F', mktime(0,0,0,$month,1)) ?> <?= $year ?></span>
            <?php else: ?>
            <span class="text-muted fs-6 fw-normal">— <?= $year ?></span>
            <?php endif; ?>
        </h4>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <select name="year" class="form-select form-select-sm bg-dark border-secondary text-white" style="width:100px">
                <?php foreach ($years as $y): ?>
                <option value="<?= $y['y'] ?>" <?= $y['y'] == $year ? 'selected' : '' ?>><?= $y['y'] ?></option>
                <?php endforeach; ?>
            </select>
            <select name="month" class="form-select form-select-sm bg-dark border-secondary text-white" style="width:140px">
                <option value="0" <?= $month==0 ? 'selected' : '' ?>>Full Year</option>
                <?php for ($i=1;$i<=12;$i++): ?>
                <option value="<?= $i ?>" <?= $month==$i ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$i,1)) ?></option>
                <?php endfor; ?>
            </select>
            <button class="btn btn-sm btn-primary">Filter</button>
        </form>
    </div>

    <!-- KPI cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="text-muted small mb-1">Gross Revenue</div>
                <div class="text-white fs-4 fw-bold"><?= APP_CURRENCY ?><?= number_format($grossRevenue, 2) ?></div>
                <div class="text-muted" style="font-size:11px">Subs: <?= APP_CURRENCY ?><?= number_format($subRevenue,2) ?> &middot; One-off: <?= APP_CURRENCY ?><?= number_format($purchaseRevenue,2) ?></div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="text-muted small mb-1">Total Expenses</div>
                <div class="text-white fs-4 fw-bold"><?= APP_CURRENCY ?><?= number_format($totalExpenses, 2) ?></div>
                <div class="text-muted" style="font-size:11px"><?= count($expByCategory) ?> categor<?= count($expByCategory)==1?'y':'ies' ?></div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="text-muted small mb-1">Net Profit</div>
                <div class="fs-4 fw-bold <?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= $netProfit < 0 ? '-' : '' ?><?= APP_CURRENCY ?><?= number_format(abs($netProfit), 2) ?>
                </div>
                <div class="text-muted" style="font-size:11px">
                    Margin: <?= $grossRevenue > 0 ? number_format(($netProfit/$grossRevenue)*100,1).'%' : '—' ?>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="admin-card rounded-4 p-4">
                <div class="text-muted small mb-1">Outstanding Invoices</div>
                <div class="text-warning fs-4 fw-bold"><?= APP_CURRENCY ?><?= number_format((float)$invoiceStats['outstanding'], 2) ?></div>
                <div class="text-muted" style="font-size:11px">Overdue: <?= APP_CURRENCY ?><?= number_format((float)$invoiceStats['overdue'], 2) ?></div>
            </div>
        </div>
    </div>

    <!-- Revenue vs Expenses chart + breakdown -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="admin-card rounded-4 p-4 h-100">
                <div class="text-white fw-semibold mb-3">Revenue vs Expenses — <?= $year ?></div>
                <canvas id="plChart" height="120"></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="admin-card rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-white fw-semibold">Expenses by Category</div>
                    <a href="/admin/expenses.php" class="btn btn-sm btn-outline-secondary">Manage</a>
                </div>
                <?php if (empty($expByCategory)): ?>
                <p class="text-muted small">No expenses recorded yet.</p>
                <?php else: ?>
                <?php foreach ($expByCategory as $cat):
                    $pct = $totalExpenses > 0 ? ($cat['total']/$totalExpenses)*100 : 0;
                    $icons = ['software'=>'bi-laptop','hosting'=>'bi-server','marketing'=>'bi-megaphone',
                              'salaries'=>'bi-people','operations'=>'bi-gear','tax'=>'bi-receipt','other'=>'bi-three-dots'];
                    $icon = $icons[$cat['category']] ?? 'bi-circle';
                ?>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi <?= $icon ?> text-primary" style="width:18px"></i>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between small text-white">
                            <span><?= ucfirst($cat['category']) ?></span>
                            <span><?= APP_CURRENCY ?><?= number_format($cat['total'],2) ?></span>
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
    </div>

    <!-- Recent transactions -->
    <div class="admin-card rounded-4 overflow-hidden">
        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom border-secondary border-opacity-25">
            <div class="text-white fw-semibold">Recent Transactions</div>
            <a href="/admin/invoices.php" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-receipt me-1"></i>All Invoices
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
                <thead class="border-bottom border-secondary">
                    <tr class="text-muted small">
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentTx as $tx): ?>
                    <tr>
                        <td class="text-muted small"><?= date('d M Y', strtotime($tx['tx_date'])) ?></td>
                        <td class="text-white small"><?= htmlspecialchars($tx['customer']) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($tx['product']) ?></td>
                        <td>
                            <span class="badge <?= $tx['type']==='subscription' ? 'bg-primary' : 'bg-info text-dark' ?>" style="font-size:10px">
                                <?= ucfirst($tx['type']) ?>
                            </span>
                        </td>
                        <td class="text-white fw-semibold small"><?= APP_CURRENCY ?><?= number_format($tx['amount'],2) ?></td>
                        <td>
                            <?php
                            $sc = ['active'=>'success','completed'=>'success','cancelled'=>'secondary','pending'=>'warning','failed'=>'danger'];
                            $sc2 = $sc[$tx['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $sc2 ?>" style="font-size:10px"><?= ucfirst($tx['status']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentTx)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No transactions for this period.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function(){
    const labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const rev = <?= json_encode(array_values($revByMonth)) ?>;
    const exp = <?= json_encode(array_values($expByMonth)) ?>;
    const profit = rev.map((r,i) => +(r - exp[i]).toFixed(2));

    new Chart(document.getElementById('plChart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'Revenue', data: rev,    backgroundColor: 'rgba(99,102,241,0.7)', borderRadius: 4 },
                { label: 'Expenses', data: exp,   backgroundColor: 'rgba(239,68,68,0.6)',  borderRadius: 4 },
                { label: 'Net Profit', data: profit, type: 'line', borderColor: '#10b981',
                  backgroundColor: 'rgba(16,185,129,0.1)', pointBackgroundColor: '#10b981',
                  tension: 0.3, borderWidth: 2, fill: true }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { labels: { color: '#9ca3af', font: { size: 11 } } } },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#6b7280' } },
                y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#6b7280',
                     callback: v => '$'+v.toLocaleString() } }
            }
        }
    });
})();
</script>

<?php require_once '../includes/admin-footer.php'; ?>
