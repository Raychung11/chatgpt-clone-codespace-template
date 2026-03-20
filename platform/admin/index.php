<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();
$pageTitle = 'Admin Dashboard';

// Overview stats
$stats = [
    'total_revenue'    => DB::fetch('SELECT COALESCE(SUM(amount),0) as n FROM purchases WHERE status="completed"')['n']
                        + DB::fetch('SELECT COALESCE(SUM(amount),0) as n FROM subscriptions WHERE status="active"')['n'],
    'total_customers'  => DB::fetch('SELECT COUNT(*) as n FROM users WHERE role="customer"')['n'],
    'active_subs'      => DB::fetch('SELECT COUNT(*) as n FROM subscriptions WHERE status="active"')['n'],
    'total_products'   => DB::fetch('SELECT COUNT(*) as n FROM products WHERE is_active=1')['n'],
    'new_leads'        => DB::fetch('SELECT COUNT(*) as n FROM leads WHERE status="new"')['n'],
    'new_customers_mo' => DB::fetch('SELECT COUNT(*) as n FROM users WHERE role="customer" AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')['n'],
];

// Recent signups
$recentUsers = DB::fetchAll('SELECT * FROM users WHERE role="customer" ORDER BY created_at DESC LIMIT 8');
// Recent subscriptions
$recentSubs  = DB::fetchAll(
    'SELECT s.*, u.name as user_name, u.email as user_email, p.name as product_name
     FROM subscriptions s JOIN users u ON s.user_id=u.id JOIN products p ON s.product_id=p.id
     ORDER BY s.created_at DESC LIMIT 8'
);
// Top products
$topProducts = DB::fetchAll(
    'SELECT p.name, p.price_monthly,
            COUNT(s.id) as sub_count,
            SUM(CASE WHEN s.status="active" THEN 1 ELSE 0 END) as active_count
     FROM products p LEFT JOIN subscriptions s ON p.id=s.product_id
     WHERE p.is_active=1 GROUP BY p.id ORDER BY active_count DESC LIMIT 5'
);
// Recent leads
$recentLeads = DB::fetchAll('SELECT * FROM leads ORDER BY created_at DESC LIMIT 5');

require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Title -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white fw-bold mb-0">Dashboard Overview</h4>
            <p class="text-muted small mb-0">Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="/admin/products.php" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i>Add Product
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-2">
            <div class="admin-stat-card rounded-3 p-3">
                <div class="text-muted small mb-1">Total Revenue</div>
                <div class="fs-4 fw-bold text-white"><?= CURRENCY_SYMBOL ?><?= number_format($stats['total_revenue'], 0) ?></div>
                <div class="text-success small"><i class="bi bi-arrow-up-short"></i>All time</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="admin-stat-card rounded-3 p-3">
                <div class="text-muted small mb-1">Customers</div>
                <div class="fs-4 fw-bold text-white"><?= number_format($stats['total_customers']) ?></div>
                <div class="text-success small"><i class="bi bi-arrow-up-short"></i>+<?= $stats['new_customers_mo'] ?> this month</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="admin-stat-card rounded-3 p-3">
                <div class="text-muted small mb-1">Active Subs</div>
                <div class="fs-4 fw-bold text-white"><?= number_format($stats['active_subs']) ?></div>
                <div class="text-info small"><i class="bi bi-circle-fill" style="font-size:8px"></i> Live</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="admin-stat-card rounded-3 p-3">
                <div class="text-muted small mb-1">Products</div>
                <div class="fs-4 fw-bold text-white"><?= $stats['total_products'] ?></div>
                <div class="text-muted small">Active listings</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="admin-stat-card rounded-3 p-3">
                <div class="text-muted small mb-1">New Leads</div>
                <div class="fs-4 fw-bold text-warning"><?= $stats['new_leads'] ?></div>
                <div class="text-warning small">Need follow-up</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="admin-stat-card rounded-3 p-3">
                <div class="text-muted small mb-1">MRR (est.)</div>
                <?php $mrr = DB::fetch('SELECT COALESCE(SUM(amount),0) as n FROM subscriptions WHERE status="active" AND plan="monthly"')['n']; ?>
                <div class="fs-4 fw-bold text-success"><?= CURRENCY_SYMBOL ?><?= number_format($mrr, 0) ?></div>
                <div class="text-success small">Monthly recurring</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Recent Subscriptions -->
        <div class="col-lg-8">
            <div class="admin-card rounded-4 p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Recent Subscriptions</h6>
                    <a href="/admin/clients.php" class="text-primary small">View all</a>
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
                                    <div class="text-white small"><?= htmlspecialchars($sub['user_name']) ?></div>
                                    <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($sub['user_email']) ?></div>
                                </td>
                                <td class="text-muted small"><?= htmlspecialchars($sub['product_name']) ?></td>
                                <td><span class="badge bg-secondary"><?= ucfirst($sub['plan']) ?></span></td>
                                <td>
                                    <?php $cls = match($sub['status']) {'active'=>'success','trialing'=>'info','past_due'=>'warning','canceled'=>'danger',default=>'secondary'}; ?>
                                    <span class="badge bg-<?= $cls ?>"><?= ucfirst($sub['status']) ?></span>
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

            <!-- Recent Leads -->
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
                                <td class="text-white small"><?= htmlspecialchars($lead['name']) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($lead['email']) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($lead['company'] ?: '—') ?></td>
                                <td><span class="badge bg-warning text-dark"><?= ucfirst($lead['status']) ?></span></td>
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

        <!-- Right Panel -->
        <div class="col-lg-4">
            <!-- Top Products -->
            <div class="admin-card rounded-4 p-4 mb-4">
                <h6 class="text-white fw-semibold mb-3">Top Products</h6>
                <?php foreach ($topProducts as $i => $tp): ?>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="rank-badge"><?= $i+1 ?></div>
                    <div class="flex-grow-1">
                        <div class="text-white small fw-semibold"><?= htmlspecialchars($tp['name']) ?></div>
                        <div class="text-muted" style="font-size:11px"><?= CURRENCY_SYMBOL ?><?= number_format($tp['price_monthly'], 0) ?>/mo</div>
                    </div>
                    <div class="text-end">
                        <div class="text-white small fw-bold"><?= $tp['active_count'] ?></div>
                        <div class="text-muted" style="font-size:11px">active</div>
                    </div>
                </div>
                <?php endforeach; ?>
                <a href="/admin/products.php" class="btn btn-outline-secondary btn-sm w-100 mt-2">Manage Products</a>
            </div>

            <!-- Recent Customers -->
            <div class="admin-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">Recent Signups</h6>
                <?php foreach ($recentUsers as $u): ?>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="avatar-initials sm"><?= strtoupper(substr($u['name'],0,2)) ?></div>
                    <div class="flex-grow-1 min-width-0">
                        <div class="text-white small fw-semibold text-truncate"><?= htmlspecialchars($u['name']) ?></div>
                        <div class="text-muted text-truncate" style="font-size:11px"><?= htmlspecialchars($u['email']) ?></div>
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
