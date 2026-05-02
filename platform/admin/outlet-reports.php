<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

// ── Period & Outlet Selectors ─────────────────────────────────────────────────
$selectedYear   = (int)($_GET['year']   ?? date('Y'));
$selectedMonth  = (int)($_GET['month']  ?? date('n'));
$selectedOutlet = (int)($_GET['outlet'] ?? 0);   // 0 defaults to first outlet

// Clamp values
if ($selectedYear  < 2020 || $selectedYear > (int)date('Y') + 1) $selectedYear = (int)date('Y');
if ($selectedMonth < 1 || $selectedMonth > 12) $selectedMonth = (int)date('n');

// ── All outlets from DB ───────────────────────────────────────────────────────
$allOutlets = DB::fetchAll("SELECT id, name, code, city, state, outlet_type, status FROM outlets ORDER BY name ASC");

// ── Detect whether outlet_sales has any data ──────────────────────────────────
$salesCount  = (int)(DB::fetch('SELECT COUNT(*) AS n FROM outlet_sales')['n'] ?? 0);
$hasSalesData = $salesCount > 0;

// ── Sample fallback data (5 outlets, realistic MYR) ───────────────────────────
$sampleOutlets = [
    ['id' => 1, 'name' => 'Pavilion KL',     'code' => 'KL-PAV', 'city' => 'Kuala Lumpur', 'state' => 'WP', 'outlet_type' => 'retail',    'status' => 'active'],
    ['id' => 2, 'name' => 'Mid Valley MV',   'code' => 'KL-MV',  'city' => 'Kuala Lumpur', 'state' => 'WP', 'outlet_type' => 'retail',    'status' => 'active'],
    ['id' => 3, 'name' => 'Sunway Pyramid',  'code' => 'SY-PYR', 'city' => 'Subang Jaya',  'state' => 'SGR','outlet_type' => 'franchise',  'status' => 'active'],
    ['id' => 4, 'name' => 'KLCC Online',     'code' => 'KL-ONL', 'city' => 'Kuala Lumpur', 'state' => 'WP', 'outlet_type' => 'online',    'status' => 'active'],
    ['id' => 5, 'name' => 'Penang Branch',   'code' => 'PNG-01', 'city' => 'George Town',  'state' => 'PNG','outlet_type' => 'retail',    'status' => 'active'],
];

// Monthly revenue per sample outlet (Jan–Dec index 0–11)
$sampleRevenue = [
    1 => [48200, 51400, 53100, 49800, 55300, 58900, 61200, 57400, 62100, 65800, 71200, 68500],
    2 => [39100, 41200, 43800, 40500, 44700, 47300, 49600, 46200, 51100, 54300, 58700, 55900],
    3 => [52300, 55100, 57800, 53400, 59200, 63400, 67100, 62800, 68400, 72100, 78300, 74200],
    4 => [28400, 30100, 31700, 29300, 33500, 35800, 37400, 34900, 38200, 41500, 45100, 42800],
    5 => [35700, 37400, 39200, 36800, 41100, 43900, 46200, 43100, 47800, 51200, 55400, 52700],
];
$sampleProducts = [
    1 => 'AI CRM Pro',
    2 => 'Smart Analytics Suite',
    3 => 'AI HR Agent',
    4 => 'Finance AI',
    5 => 'Inventory Agent',
];
$sampleOrdersBase = [1 => 310, 2 => 248, 3 => 385, 4 => 192, 5 => 231];

// ── Resolve which outlet list to use ─────────────────────────────────────────
$displayOutlets = !empty($allOutlets) ? $allOutlets : $sampleOutlets;

// Validate selectedOutlet against display list
$validIds = array_column($displayOutlets, 'id');
if ($selectedOutlet === 0 || !in_array($selectedOutlet, $validIds)) {
    $selectedOutlet = !empty($validIds) ? (int)$validIds[0] : 1;
}

// Find current outlet row
$currentOutletRow = null;
foreach ($displayOutlets as $o) {
    if ((int)$o['id'] === $selectedOutlet) { $currentOutletRow = $o; break; }
}

// ── Helper: get revenue for an outlet + period ────────────────────────────────
function getOutletRevenue(int $oid, int $year, int $month, bool $hasSales, array $sampleRev): float {
    if ($hasSales) {
        $r = DB::fetch(
            "SELECT COALESCE(SUM(amount),0) AS v FROM outlet_sales WHERE outlet_id=? AND YEAR(sale_date)=? AND MONTH(sale_date)=?",
            [$oid, $year, $month]
        );
        return (float)($r['v'] ?? 0);
    }
    return (float)($sampleRev[$oid][$month - 1] ?? 0);
}

function getOutletOrders(int $oid, int $year, int $month, bool $hasSales, array $sampleOrders): int {
    if ($hasSales) {
        $r = DB::fetch(
            "SELECT COUNT(*) AS v FROM outlet_sales WHERE outlet_id=? AND YEAR(sale_date)=? AND MONTH(sale_date)=?",
            [$oid, $year, $month]
        );
        return (int)($r['v'] ?? 0);
    }
    // Scale sample orders slightly by month index
    $base = $sampleOrders[$oid] ?? 200;
    $factor = 0.85 + (($month - 1) / 11) * 0.30;
    return (int)round($base * $factor);
}

// ── KPIs for selected outlet + selected period ────────────────────────────────
$kpiRev    = getOutletRevenue($selectedOutlet, $selectedYear, $selectedMonth, $hasSalesData, $sampleRevenue);
$kpiOrders = getOutletOrders($selectedOutlet, $selectedYear, $selectedMonth, $hasSalesData, $sampleOrdersBase);
$kpiAov    = $kpiOrders > 0 ? $kpiRev / $kpiOrders : 0;

if ($hasSalesData) {
    $topRow     = DB::fetch(
        "SELECT product_name, SUM(amount) AS t FROM outlet_sales WHERE outlet_id=? AND YEAR(sale_date)=? AND MONTH(sale_date)=? GROUP BY product_name ORDER BY t DESC LIMIT 1",
        [$selectedOutlet, $selectedYear, $selectedMonth]
    );
    $kpiTopProduct = $topRow ? htmlspecialchars($topRow['product_name']) : '—';
} else {
    $kpiTopProduct = htmlspecialchars($sampleProducts[$selectedOutlet] ?? 'AI Analytics');
}

// ── Chart.js: grouped bar — monthly revenue across all outlets for $selectedYear ─
$chartMonthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$chartPalette     = ['#6366f1','#22d3ee','#a855f7','#10b981','#f59e0b','#ef4444','#ec4899','#84cc16'];
$chartDatasets    = [];
$ci               = 0;

foreach ($displayOutlets as $o) {
    $oid     = (int)$o['id'];
    $monthly = [];
    for ($m = 1; $m <= 12; $m++) {
        $monthly[] = getOutletRevenue($oid, $selectedYear, $m, $hasSalesData, $sampleRevenue);
    }
    $color = $chartPalette[$ci % count($chartPalette)];
    $chartDatasets[] = [
        'label'           => htmlspecialchars($o['code']),
        'data'            => $monthly,
        'backgroundColor' => $color . 'bb',
        'borderColor'     => $color,
        'borderWidth'     => 1,
        'borderRadius'    => 4,
        'borderSkipped'   => false,
    ];
    $ci++;
}

// ── Per-outlet summary table ──────────────────────────────────────────────────
$summaryRows = [];
$prevMonth     = $selectedMonth === 1 ? 12 : $selectedMonth - 1;
$prevMonthYear = $selectedMonth === 1 ? $selectedYear - 1 : $selectedYear;

foreach ($displayOutlets as $o) {
    $oid     = (int)$o['id'];
    $rev     = getOutletRevenue($oid, $selectedYear, $selectedMonth, $hasSalesData, $sampleRevenue);
    $orders  = getOutletOrders($oid, $selectedYear, $selectedMonth, $hasSalesData, $sampleOrdersBase);
    $prevRev = getOutletRevenue($oid, $prevMonthYear, $prevMonth, $hasSalesData, $sampleRevenue);
    $aov     = $orders > 0 ? $rev / $orders : 0;
    $growth  = $prevRev > 0 ? (($rev - $prevRev) / $prevRev) * 100 : null;

    $summaryRows[] = [
        'id'     => $oid,
        'name'   => $o['name'],
        'code'   => $o['code'],
        'city'   => $o['city'] ?? '',
        'type'   => $o['outlet_type'] ?? 'retail',
        'status' => $o['status'] ?? 'active',
        'rev'    => $rev,
        'orders' => $orders,
        'aov'    => $aov,
        'growth' => $growth,
    ];
}
usort($summaryRows, fn($a, $b) => $b['rev'] <=> $a['rev']);

// ── Recent transactions for selected outlet ───────────────────────────────────
if ($hasSalesData) {
    $recentTx = DB::fetchAll(
        "SELECT * FROM outlet_sales WHERE outlet_id=? AND YEAR(sale_date)=? AND MONTH(sale_date)=? ORDER BY sale_date DESC, id DESC LIMIT 20",
        [$selectedOutlet, $selectedYear, $selectedMonth]
    );
} else {
    // Deterministic synthetic transactions
    $txProducts = ['AI CRM Pro', 'Smart Analytics Suite', 'AI HR Agent', 'Finance AI',
                   'Inventory Agent', 'Marketing Bot', 'Scheduling AI'];
    $txMethods  = ['cash', 'card', 'ewallet', 'bank_transfer'];
    $txAmounts  = [990, 1290, 690, 1490, 890, 1190, 790];
    $recentTx   = [];
    for ($i = 0; $i < 20; $i++) {
        $pIdx       = $i % count($txProducts);
        $recentTx[] = [
            'id'             => 9000 + $i,
            'order_ref'      => 'ORD-' . str_pad(9000 - $i, 5, '0', STR_PAD_LEFT),
            'product_name'   => $txProducts[$pIdx],
            'amount'         => $txAmounts[$pIdx] + ($i % 4) * 100,
            'quantity'       => ($i % 3) + 1,
            'sale_date'      => date('Y-m-d', mktime(0, 0, 0, $selectedMonth, max(1, 28 - $i), $selectedYear)),
            'payment_method' => $txMethods[$i % count($txMethods)],
        ];
    }
}

// ── Month names lookup ────────────────────────────────────────────────────────
$monthNames = [
    1=>'January', 2=>'February', 3=>'March',    4=>'April',
    5=>'May',     6=>'June',     7=>'July',      8=>'August',
    9=>'September',10=>'October',11=>'November',12=>'December',
];
$periodLabel  = ($monthNames[$selectedMonth] ?? '') . ' ' . $selectedYear;
$outletLabel  = $currentOutletRow ? htmlspecialchars($currentOutletRow['name']) : 'Outlet';

$pageTitle = 'Outlet Reports';
require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">

    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white fw-bold mb-0">
                <i class="bi bi-bar-chart-line me-2 text-primary"></i>Outlet Reports
            </h4>
            <p class="text-muted small mb-0 mt-1">
                Performance analytics across all outlets
                <?php if (!$hasSalesData): ?>
                <span class="badge bg-warning text-dark ms-2" style="font-size:10px">
                    <i class="bi bi-info-circle me-1"></i>Sample data — no sales recorded yet
                </span>
                <?php endif; ?>
            </p>
        </div>
        <a href="outlets.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-shop me-1"></i>Manage Outlets
        </a>
    </div>

    <!-- Selector / Filter Bar -->
    <form method="GET" class="admin-card rounded-4 p-3 mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-md-4">
                <label class="form-label text-muted small mb-1">Outlet</label>
                <select name="outlet" class="form-select form-select-sm bg-dark border-secondary text-white">
                    <?php foreach ($displayOutlets as $o): ?>
                    <option value="<?= (int)$o['id'] ?>"
                            <?= (int)$o['id'] === $selectedOutlet ? 'selected' : '' ?>>
                        [<?= htmlspecialchars($o['code']) ?>] <?= htmlspecialchars($o['name']) ?>
                    </option>
                    <?php endforeach; ?>
                    <?php if (empty($displayOutlets)): ?>
                    <option value="0">No outlets found</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label text-muted small mb-1">Month</label>
                <select name="month" class="form-select form-select-sm bg-dark border-secondary text-white">
                    <?php foreach ($monthNames as $num => $name): ?>
                    <option value="<?= $num ?>" <?= $num === $selectedMonth ? 'selected' : '' ?>>
                        <?= $name ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label text-muted small mb-1">Year</label>
                <select name="year" class="form-select form-select-sm bg-dark border-secondary text-white">
                    <?php for ($y = (int)date('Y'); $y >= 2022; $y--): ?>
                    <option value="<?= $y ?>" <?= $y === $selectedYear ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <button class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-search me-1"></i>Apply
                </button>
            </div>
        </div>
    </form>

    <!-- KPI Row — always shown for selected outlet + period -->
    <div class="row g-3 mb-4">
        <!-- Revenue -->
        <div class="col-6 col-xl-3">
            <div class="admin-card rounded-4 p-4 h-100">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="flex-grow-1 min-width-0">
                        <div class="text-muted small mb-1">Revenue</div>
                        <div class="text-white fs-3 fw-bold">
                            <?= APP_CURRENCY ?><?= number_format($kpiRev, 0) ?>
                        </div>
                        <div class="text-muted mt-1" style="font-size:11px">
                            <?= $outletLabel ?> · <?= $periodLabel ?>
                        </div>
                    </div>
                    <span class="d-inline-flex align-items-center justify-content-center rounded-3 flex-shrink-0 ms-2"
                          style="width:44px;height:44px;background:rgba(99,102,241,.15)">
                        <i class="bi bi-cash-coin text-primary fs-5"></i>
                    </span>
                </div>
            </div>
        </div>
        <!-- Orders -->
        <div class="col-6 col-xl-3">
            <div class="admin-card rounded-4 p-4 h-100">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="text-muted small mb-1">Orders</div>
                        <div class="text-info fs-3 fw-bold"><?= number_format($kpiOrders) ?></div>
                        <div class="text-muted mt-1" style="font-size:11px"><?= $periodLabel ?></div>
                    </div>
                    <span class="d-inline-flex align-items-center justify-content-center rounded-3 flex-shrink-0 ms-2"
                          style="width:44px;height:44px;background:rgba(34,211,238,.12)">
                        <i class="bi bi-receipt text-info fs-5"></i>
                    </span>
                </div>
            </div>
        </div>
        <!-- AOV -->
        <div class="col-6 col-xl-3">
            <div class="admin-card rounded-4 p-4 h-100">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="text-muted small mb-1">Avg Order Value</div>
                        <div class="text-warning fs-3 fw-bold">
                            <?= APP_CURRENCY ?><?= number_format($kpiAov, 0) ?>
                        </div>
                        <div class="text-muted mt-1" style="font-size:11px">Per transaction</div>
                    </div>
                    <span class="d-inline-flex align-items-center justify-content-center rounded-3 flex-shrink-0 ms-2"
                          style="width:44px;height:44px;background:rgba(245,158,11,.12)">
                        <i class="bi bi-graph-up-arrow text-warning fs-5"></i>
                    </span>
                </div>
            </div>
        </div>
        <!-- Top Product -->
        <div class="col-6 col-xl-3">
            <div class="admin-card rounded-4 p-4 h-100">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="flex-grow-1 min-width-0">
                        <div class="text-muted small mb-1">Top Product</div>
                        <div class="text-white fw-bold text-truncate" style="font-size:.95rem">
                            <?= $kpiTopProduct ?>
                        </div>
                        <div class="text-muted mt-1" style="font-size:11px">Best seller this month</div>
                    </div>
                    <span class="d-inline-flex align-items-center justify-content-center rounded-3 flex-shrink-0 ms-2"
                          style="width:44px;height:44px;background:rgba(168,85,247,.12)">
                        <i class="bi bi-trophy fs-5" style="color:#a855f7"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart: Grouped Bar — Monthly Revenue by Outlet -->
    <div class="admin-card rounded-4 p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <h6 class="text-white fw-semibold mb-0">Monthly Revenue by Outlet</h6>
                <div class="text-muted small"><?= $selectedYear ?> · all outlets compared side-by-side</div>
            </div>
            <!-- Color legend -->
            <div class="d-flex flex-wrap gap-2">
                <?php
                $li = 0;
                foreach ($displayOutlets as $lo):
                    $lcolor = $chartPalette[$li % count($chartPalette)];
                    $li++;
                ?>
                <span class="d-inline-flex align-items-center gap-1 rounded-pill px-2 py-1"
                      style="background:<?= $lcolor ?>18;border:1px solid <?= $lcolor ?>44;font-size:11px;color:<?= $lcolor ?>">
                    <span style="width:8px;height:8px;border-radius:50%;background:<?= $lcolor ?>;display:inline-block;flex-shrink:0"></span>
                    <?= htmlspecialchars($lo['code']) ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <div style="position:relative;height:320px">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- Two-column: Summary + Recent Transactions -->
    <div class="row g-4">

        <!-- Per-outlet summary table -->
        <div class="col-12 col-xl-7">
            <div class="admin-card rounded-4 overflow-hidden h-100">
                <div class="px-4 pt-4 pb-2 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white fw-semibold mb-0">All Outlets — <?= $periodLabel ?></h6>
                        <p class="text-muted small mb-0">Revenue, orders, AOV and month-over-month growth</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0 align-middle small">
                        <thead class="border-bottom border-secondary">
                            <tr class="text-muted" style="font-size:11px">
                                <th class="px-4">#</th>
                                <th>Outlet</th>
                                <th>Type</th>
                                <th class="text-end">Revenue</th>
                                <th class="text-end">Orders</th>
                                <th class="text-end">AOV</th>
                                <th class="text-center">Growth</th>
                                <th class="text-center pe-4">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summaryRows as $rank => $row):
                                $isSelected = $row['id'] === $selectedOutlet;

                                $statusBadge = match($row['status']) {
                                    'active'             => 'success',
                                    'inactive'           => 'secondary',
                                    'temporarily_closed' => 'warning',
                                    default              => 'secondary',
                                };
                                $statusLabel = match($row['status']) {
                                    'active'             => 'Active',
                                    'inactive'           => 'Inactive',
                                    'temporarily_closed' => 'Closed',
                                    default              => ucfirst($row['status']),
                                };

                                $typeColor = match($row['type']) {
                                    'retail'    => '#0d6efd',
                                    'kiosk'     => '#0dcaf0',
                                    'warehouse' => '#6c757d',
                                    'online'    => '#6366f1',
                                    'franchise' => '#9333ea',
                                    default     => '#6c757d',
                                };
                            ?>
                            <tr class="<?= $isSelected ? 'table-active' : '' ?>">
                                <td class="px-4 text-muted"><?= $rank + 1 ?></td>
                                <td>
                                    <div class="text-white fw-semibold"><?= htmlspecialchars($row['name']) ?></div>
                                    <code class="text-muted" style="font-size:10px"><?= htmlspecialchars($row['code']) ?></code>
                                </td>
                                <td>
                                    <span class="badge rounded-pill"
                                          style="background:<?= $typeColor ?>22;color:<?= $typeColor ?>;border:1px solid <?= $typeColor ?>44;font-size:10px">
                                        <?= ucfirst(htmlspecialchars($row['type'])) ?>
                                    </span>
                                </td>
                                <td class="text-end fw-semibold text-white">
                                    <?= APP_CURRENCY ?><?= number_format($row['rev'], 0) ?>
                                </td>
                                <td class="text-end text-muted"><?= number_format($row['orders']) ?></td>
                                <td class="text-end text-muted">
                                    <?= APP_CURRENCY ?><?= number_format($row['aov'], 0) ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($row['growth'] === null): ?>
                                        <span class="text-muted">—</span>
                                    <?php elseif ($row['growth'] >= 0): ?>
                                        <span class="text-success fw-semibold">
                                            <i class="bi bi-arrow-up-short"></i><?= number_format(abs($row['growth']), 1) ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-danger fw-semibold">
                                            <i class="bi bi-arrow-down-short"></i><?= number_format(abs($row['growth']), 1) ?>%
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center pe-4">
                                    <span class="badge bg-<?= $statusBadge ?><?= $row['status'] === 'temporarily_closed' ? ' text-dark' : '' ?>">
                                        <?= $statusLabel ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($summaryRows)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="bi bi-shop fs-2 d-block mb-2 opacity-25"></i>
                                    No outlets to display. <a href="outlets.php" class="text-primary">Add outlets</a> first.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($summaryRows)): ?>
                        <tfoot class="border-top border-secondary">
                            <tr class="text-muted small">
                                <td colspan="3" class="px-4 fw-semibold text-white">Totals</td>
                                <td class="text-end fw-bold text-white">
                                    <?= APP_CURRENCY ?><?= number_format(array_sum(array_column($summaryRows, 'rev')), 0) ?>
                                </td>
                                <td class="text-end fw-bold text-white">
                                    <?= number_format(array_sum(array_column($summaryRows, 'orders'))) ?>
                                </td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="col-12 col-xl-5">
            <div class="admin-card rounded-4 overflow-hidden h-100">
                <div class="px-4 pt-4 pb-2">
                    <h6 class="text-white fw-semibold mb-0">Recent Transactions</h6>
                    <p class="text-muted small mb-0">
                        Last 20 sales · <?= $outletLabel ?> · <?= $periodLabel ?>
                    </p>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0 align-middle small">
                        <thead class="border-bottom border-secondary">
                            <tr class="text-muted" style="font-size:11px">
                                <th class="px-4">Order Ref</th>
                                <th>Product</th>
                                <th class="text-end">Amt</th>
                                <th class="text-center pe-4">Pay</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTx as $tx):
                                [$pmCls, $pmIcon] = match($tx['payment_method'] ?? '') {
                                    'cash'          => ['secondary', 'bi-cash'],
                                    'card'          => ['info',      'bi-credit-card-2-front'],
                                    'ewallet'       => ['primary',   'bi-phone'],
                                    'bank_transfer' => ['warning',   'bi-bank2'],
                                    default         => ['secondary', 'bi-question'],
                                };
                            ?>
                            <tr>
                                <td class="px-4">
                                    <div>
                                        <code class="text-primary" style="font-size:11px">
                                            <?= htmlspecialchars($tx['order_ref'] ?? '#' . $tx['id']) ?>
                                        </code>
                                    </div>
                                    <div class="text-muted" style="font-size:10px">
                                        <?= htmlspecialchars(date('d M', strtotime($tx['sale_date']))) ?>
                                    </div>
                                </td>
                                <td class="text-white">
                                    <div class="text-truncate" style="max-width:140px" title="<?= htmlspecialchars($tx['product_name'] ?? '') ?>">
                                        <?= htmlspecialchars($tx['product_name'] ?? '—') ?>
                                    </div>
                                    <?php if ((int)($tx['quantity'] ?? 1) > 1): ?>
                                    <div class="text-muted" style="font-size:10px">Qty <?= (int)$tx['quantity'] ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-success fw-semibold" style="white-space:nowrap">
                                    <?= APP_CURRENCY ?><?= number_format((float)$tx['amount'], 0) ?>
                                </td>
                                <td class="text-center pe-4">
                                    <span class="badge bg-<?= $pmCls ?> bg-opacity-20 text-<?= $pmCls ?>"
                                          title="<?= ucfirst(str_replace('_', ' ', $tx['payment_method'] ?? '')) ?>">
                                        <i class="bi <?= $pmIcon ?>"></i>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentTx)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">
                                    <i class="bi bi-receipt fs-2 d-block mb-2 opacity-25"></i>
                                    No transactions for this period.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- /row -->

</div><!-- /container-fluid -->

<?php
// Prepare chart JSON safely
$jsMonthLabels = json_encode($chartMonthLabels, JSON_UNESCAPED_UNICODE);
$jsDatasets    = json_encode($chartDatasets,    JSON_UNESCAPED_UNICODE);
$jsCurrSymbol  = json_encode(APP_CURRENCY);
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';

    var currencySymbol = <?= $jsCurrSymbol ?>;
    var ctx = document.getElementById('revenueChart').getContext('2d');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels:   <?= $jsMonthLabels ?>,
            datasets: <?= $jsDatasets ?>
        },
        options: {
            responsive:          true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1a1a2e',
                    borderColor:     '#374151',
                    borderWidth:     1,
                    titleColor:      '#e5e7eb',
                    bodyColor:       '#9ca3af',
                    padding:         12,
                    callbacks: {
                        label: function (ctx) {
                            var v = ctx.parsed.y;
                            return ' ' + ctx.dataset.label + ': ' + currencySymbol +
                                   v.toLocaleString('en-MY', { minimumFractionDigits: 0 });
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid:  { color: 'rgba(255,255,255,.04)' },
                    ticks: { color: '#6b7280', font: { size: 11 } }
                },
                y: {
                    grid:  { color: 'rgba(255,255,255,.04)' },
                    beginAtZero: true,
                    ticks: {
                        color: '#6b7280',
                        font:  { size: 11 },
                        callback: function (v) {
                            if (v >= 1000000) return currencySymbol + (v / 1000000).toFixed(1) + 'M';
                            if (v >= 1000)    return currencySymbol + (v / 1000).toFixed(0)     + 'k';
                            return currencySymbol + v;
                        }
                    }
                }
            }
        }
    });
})();
</script>

<?php require_once '../includes/admin-footer.php'; ?>
