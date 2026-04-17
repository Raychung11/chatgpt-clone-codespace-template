<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();
$pageTitle = 'Admin Dashboard';

/* ── Safe DB helper: returns 0 on any error ── */
function safeCount(string $sql, array $p = []): int {
    try { return (int)(DB::fetch($sql, $p)['n'] ?? 0); } catch (Throwable $e) { return 0; }
}
function safeRows(string $sql, array $p = []): array {
    try { return DB::fetchAll($sql, $p); } catch (Throwable $e) { return []; }
}

/* ── Stats ── */
$revenue_purchases = safeCount('SELECT COALESCE(SUM(amount),0) as n FROM purchases WHERE status="completed"');
$revenue_subs      = safeCount('SELECT COALESCE(SUM(amount),0) as n FROM subscriptions WHERE status="active"');
$stats = [
    'total_revenue'    => $revenue_purchases + $revenue_subs,
    'total_customers'  => safeCount('SELECT COUNT(*) as n FROM users WHERE role="customer"'),
    'active_subs'      => safeCount('SELECT COUNT(*) as n FROM subscriptions WHERE status="active"'),
    'total_products'   => safeCount('SELECT COUNT(*) as n FROM products WHERE is_active=1'),
    'new_leads'        => safeCount('SELECT COUNT(*) as n FROM leads WHERE status="new"'),
    'new_customers_mo' => safeCount('SELECT COUNT(*) as n FROM users WHERE role="customer" AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'),
    'mrr'              => safeCount('SELECT COALESCE(SUM(amount),0) as n FROM subscriptions WHERE status="active" AND plan="monthly"'),
];

$recentUsers = safeRows('SELECT * FROM users WHERE role="customer" ORDER BY created_at DESC LIMIT 8');
$recentSubs  = safeRows(
    'SELECT s.*, u.name as user_name, u.email as user_email, p.name as product_name
     FROM subscriptions s JOIN users u ON s.user_id=u.id JOIN products p ON s.product_id=p.id
     ORDER BY s.created_at DESC LIMIT 8'
);
$topProducts = safeRows(
    'SELECT p.name, p.price_monthly,
            COUNT(s.id) as sub_count,
            SUM(CASE WHEN s.status="active" THEN 1 ELSE 0 END) as active_count
     FROM products p LEFT JOIN subscriptions s ON p.id=s.product_id
     WHERE p.is_active=1 GROUP BY p.id ORDER BY active_count DESC LIMIT 5'
);
$recentLeads = safeRows('SELECT * FROM leads ORDER BY created_at DESC LIMIT 5');

require_once '../includes/admin-header.php';
?>

<div class="admin-content">
    <!-- Page Title -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white fw-bold mb-0">Dashboard Overview</h4>
            <p class="text-muted small mb-0">Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?></p>
        </div>
        <a href="/admin/products.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i>Add Product
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <?php
        $statCards = [
            ['Total Revenue',   CURRENCY_SYMBOL . number_format($stats['total_revenue'], 0), 'text-success', 'bi-currency-dollar', 'All time'],
            ['Customers',       number_format($stats['total_customers']),                    'text-info',    'bi-people',          '+' . $stats['new_customers_mo'] . ' this month'],
            ['Active Subs',     number_format($stats['active_subs']),                        'text-primary', 'bi-repeat',          'Live now'],
            ['Products',        $stats['total_products'],                                    'text-warning', 'bi-cpu',             'Active listings'],
            ['New Leads',       $stats['new_leads'],                                         'text-danger',  'bi-funnel',          'Need follow-up'],
            ['MRR',             CURRENCY_SYMBOL . number_format($stats['mrr'], 0),           'text-success', 'bi-graph-up',        'Monthly recurring'],
        ];
        foreach ($statCards as [$label, $val, $col, $icon, $sub]):
        ?>
        <div class="col-6 col-xl-2">
            <div class="admin-stat-card rounded-3 p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi <?= $icon ?> <?= $col ?>"></i>
                    <span class="text-muted small"><?= $label ?></span>
                </div>
                <div class="fs-4 fw-bold text-white"><?= $val ?></div>
                <div class="<?= $col ?> small"><?= $sub ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <!-- Left: Recent Subs + Leads -->
        <div class="col-lg-8">
            <div class="admin-card rounded-4 p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Recent Subscriptions</h6>
                    <a href="/admin/subscriptions.php" class="text-primary small">View all</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark table-hover table-sm mb-0">
                        <thead>
                            <tr class="text-muted small">
                                <th>Customer</th><th>Product</th><th>Plan</th><th>Status</th><th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSubs as $sub): ?>
                            <tr>
                                <td>
                                    <div class="text-white small"><?= htmlspecialchars($sub['user_name'] ?? '') ?></div>
                                    <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($sub['user_email'] ?? '') ?></div>
                                </td>
                                <td class="text-muted small"><?= htmlspecialchars($sub['product_name'] ?? '') ?></td>
                                <td><span class="badge bg-secondary"><?= ucfirst($sub['plan'] ?? 'monthly') ?></span></td>
                                <td>
                                    <?php
                                    $sc = ['active'=>'success','trialing'=>'info','trial'=>'info','past_due'=>'warning','canceled'=>'danger','cancelled'=>'danger'];
                                    $cls = $sc[$sub['status'] ?? ''] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $cls ?>"><?= ucfirst($sub['status'] ?? '') ?></span>
                                </td>
                                <td class="text-muted small"><?= date('d M Y', strtotime($sub['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentSubs)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No subscriptions yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="admin-card rounded-4 p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">New Leads</h6>
                    <a href="/admin/leads.php" class="text-primary small">View all</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark table-hover table-sm mb-0">
                        <thead>
                            <tr class="text-muted small"><th>Name</th><th>Email</th><th>Company</th><th>Status</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLeads as $lead): ?>
                            <tr>
                                <td class="text-white small"><?= htmlspecialchars($lead['name'] ?? '') ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($lead['email'] ?? '') ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($lead['company'] ?? '—') ?></td>
                                <td><span class="badge bg-warning text-dark"><?= ucfirst($lead['status'] ?? '') ?></span></td>
                                <td class="text-muted small"><?= date('d M', strtotime($lead['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentLeads)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No leads yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right: Top Products + Recent Signups -->
        <div class="col-lg-4">
            <div class="admin-card rounded-4 p-4 mb-4">
                <h6 class="text-white fw-semibold mb-3">Top Products</h6>
                <?php if (empty($topProducts)): ?>
                <p class="text-muted small text-center py-2">No products yet</p>
                <?php endif; ?>
                <?php foreach ($topProducts as $i => $tp): ?>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="rank-badge"><?= $i + 1 ?></div>
                    <div class="flex-grow-1 min-width-0">
                        <div class="text-white small fw-semibold text-truncate"><?= htmlspecialchars($tp['name']) ?></div>
                        <div class="text-muted" style="font-size:11px"><?= CURRENCY_SYMBOL ?><?= number_format($tp['price_monthly'] ?? 0, 0) ?>/mo</div>
                    </div>
                    <div class="text-end">
                        <div class="text-white small fw-bold"><?= $tp['active_count'] ?? 0 ?></div>
                        <div class="text-muted" style="font-size:11px">active</div>
                    </div>
                </div>
                <?php endforeach; ?>
                <a href="/admin/products.php" class="btn btn-outline-secondary btn-sm w-100 mt-2">Manage Products</a>
            </div>

            <div class="admin-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">Recent Signups</h6>
                <?php if (empty($recentUsers)): ?>
                <p class="text-muted small text-center py-2">No customers yet</p>
                <?php endif; ?>
                <?php foreach ($recentUsers as $u): ?>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="avatar-initials sm"><?= strtoupper(substr($u['name'] ?? 'U', 0, 2)) ?></div>
                    <div class="flex-grow-1 min-width-0">
                        <div class="text-white small fw-semibold text-truncate"><?= htmlspecialchars($u['name'] ?? '') ?></div>
                        <div class="text-muted text-truncate" style="font-size:11px"><?= htmlspecialchars($u['email'] ?? '') ?></div>
                    </div>
                    <div class="text-muted" style="font-size:11px"><?= date('d M', strtotime($u['created_at'])) ?></div>
                </div>
                <?php endforeach; ?>
                <a href="/admin/clients.php" class="btn btn-outline-secondary btn-sm w-100 mt-2">All Customers</a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/admin-footer.php'; ?>
