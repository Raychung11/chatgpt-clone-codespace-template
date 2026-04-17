<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireAdmin();

$totalRevenue  = (float)DB::fetch("SELECT COALESCE(SUM(amount),0) as n FROM purchases WHERE status='completed'")['n']
               + (float)DB::fetch("SELECT COALESCE(SUM(amount),0) as n FROM subscriptions WHERE status='active'")['n'];
$monthRevenue  = (float)DB::fetch("SELECT COALESCE(SUM(amount),0) as n FROM purchases WHERE status='completed' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")['n'];
$activeSubs    = (int)DB::fetch("SELECT COUNT(*) as n FROM subscriptions WHERE status='active'")['n'];
$mrr           = (float)DB::fetch("SELECT COALESCE(SUM(amount),0) as n FROM subscriptions WHERE status='active' AND plan='monthly'")['n'];

/* Monthly revenue for last 12 months */
$monthly = DB::fetchAll("SELECT DATE_FORMAT(created_at,'%Y-%m') as month, COALESCE(SUM(amount),0) as revenue
    FROM purchases WHERE status='completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month ORDER BY month");

/* Top products */
$topProducts = DB::fetchAll("SELECT p.name, COUNT(pu.id) as sales, COALESCE(SUM(pu.amount),0) as revenue
    FROM purchases pu JOIN products p ON p.id=pu.product_id
    WHERE pu.status='completed'
    GROUP BY p.id ORDER BY revenue DESC LIMIT 10");

/* Recent transactions */
$recent = DB::fetchAll("SELECT pu.*, u.name as customer, u.email, p.name as product
    FROM purchases pu JOIN users u ON u.id=pu.user_id JOIN products p ON p.id=pu.product_id
    WHERE pu.status='completed' ORDER BY pu.created_at DESC LIMIT 20");

$pageTitle = 'Revenue';
require_once '../includes/admin-header.php';
?>
<div class="admin-content px-4 py-4">
<h4 class="fw-bold mb-4">Revenue</h4>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['Total Revenue',CURRENCY_SYMBOL.number_format($totalRevenue,2),'bi-currency-dollar','text-success'],
        ['This Month',CURRENCY_SYMBOL.number_format($monthRevenue,2),'bi-calendar-month','text-primary'],
        ['Active Subs',$activeSubs,'bi-repeat','text-info'],
        ['MRR',CURRENCY_SYMBOL.number_format($mrr,2),'bi-graph-up','text-warning'],
    ] as [$lbl,$val,$icon,$col]): ?>
    <div class="col-6 col-md-3">
        <div class="glass-card p-3">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi <?= $icon ?> <?= $col ?> fs-4"></i>
                <span class="text-muted small"><?= $lbl ?></span>
            </div>
            <div class="fs-4 fw-bold text-white"><?= $val ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="glass-card p-3">
            <h6 class="fw-semibold mb-3">Monthly Revenue (Last 12 Months)</h6>
            <canvas id="revenueChart" height="80"></canvas>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass-card p-3">
            <h6 class="fw-semibold mb-3">Top Products</h6>
            <?php foreach ($topProducts as $tp): ?>
            <div class="mb-2">
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-white text-truncate" style="max-width:160px"><?= htmlspecialchars($tp['name']) ?></span>
                    <span class="text-success fw-semibold"><?= CURRENCY_SYMBOL.number_format($tp['revenue'],0) ?></span>
                </div>
                <?php $maxR = $topProducts[0]['revenue'] ?: 1; ?>
                <div class="progress" style="height:4px"><div class="progress-bar bg-primary" style="width:<?= round($tp['revenue']/$maxR*100) ?>%"></div></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($topProducts)): ?><div class="text-muted small text-center py-3">No sales yet</div><?php endif; ?>
        </div>
    </div>
</div>

<div class="glass-card p-0 overflow-hidden">
<div class="px-3 py-2 border-bottom border-secondary border-opacity-25"><h6 class="fw-semibold mb-0">Recent Transactions</h6></div>
<table class="table table-dark table-hover mb-0">
<thead><tr><th>Customer</th><th>Product</th><th>Amount</th><th>Date</th></tr></thead>
<tbody>
<?php foreach ($recent as $tx): ?>
<tr>
    <td>
        <div class="fw-semibold"><?= htmlspecialchars($tx['customer']) ?></div>
        <div class="text-muted small"><?= htmlspecialchars($tx['email']) ?></div>
    </td>
    <td><?= htmlspecialchars($tx['product']) ?></td>
    <td class="text-success fw-semibold"><?= CURRENCY_SYMBOL.number_format($tx['amount'],2) ?></td>
    <td class="text-muted small"><?= date('d M Y H:i', strtotime($tx['created_at'])) ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($recent)): ?><tr><td colspan="4" class="text-center text-muted py-4">No transactions yet.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels = <?= json_encode(array_column($monthly, 'month')) ?>;
const data   = <?= json_encode(array_map('floatval', array_column($monthly, 'revenue'))) ?>;
new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'Revenue',
            data,
            backgroundColor: 'rgba(99,102,241,0.7)',
            borderColor: '#6366f1',
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color:'#888' }, grid: { color:'rgba(255,255,255,0.05)' } },
            y: { ticks: { color:'#888', callback: v => '$'+v.toLocaleString() }, grid: { color:'rgba(255,255,255,0.05)' } }
        }
    }
});
</script>
<?php require_once '../includes/admin-footer.php'; ?>
