<?php
/**
 * Admin Dashboard
 * /admin/pages/dashboard.php
 */

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

// --- Stats ---
$totalCustomers    = (int) Database::fetchOne('SELECT COUNT(*) AS c FROM users WHERE role = "customer"')['c'];
$activeToday       = (int) Database::fetchOne('SELECT COUNT(*) AS c FROM users WHERE role = "customer" AND DATE(last_login_at) = CURDATE()')['c'];
$totalReservations = (int) Database::fetchOne('SELECT COUNT(*) AS c FROM reservations WHERE reserved_date = CURDATE()')['c'];
$pendingOrders     = (int) Database::fetchOne('SELECT COUNT(*) AS c FROM orders WHERE status = "pending"')['c'];
$totalRevenue      = (float) (Database::fetchOne("SELECT COALESCE(SUM(total),0) AS r FROM orders WHERE payment_status='paid' AND DATE(created_at) = CURDATE()")['r'] ?? 0);
$totalPoints       = (int) (Database::fetchOne('SELECT COALESCE(SUM(points),0) AS p FROM loyalty_transactions WHERE type="earn" AND DATE(created_at) = CURDATE()')['p'] ?? 0);

// --- Recent Reservations ---
$recentReservations = Database::fetchAll(
    'SELECT r.*, u.name AS customer_name, o.name AS outlet_name
     FROM reservations r
     JOIN users u ON u.id = r.user_id
     JOIN outlets o ON o.id = r.outlet_id
     ORDER BY r.created_at DESC LIMIT 8'
);

// --- Tier Distribution ---
$tiers = Database::fetchAll(
    'SELECT tier, COUNT(*) AS cnt FROM customer_profiles GROUP BY tier'
);
$tierMap = array_column($tiers, 'cnt', 'tier');

require __DIR__ . '/../layout/header.php';
?>

<!-- Stat Cards Row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small">Total Customers</div>
                    <div class="fs-4 fw-bold mt-1"><?= number_format($totalCustomers) ?></div>
                </div>
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-people-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small">Active Today</div>
                    <div class="fs-4 fw-bold mt-1"><?= number_format($activeToday) ?></div>
                </div>
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-person-check-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small">Today's Reservations</div>
                    <div class="fs-4 fw-bold mt-1"><?= number_format($totalReservations) ?></div>
                </div>
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-calendar-check-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small">Pending Orders</div>
                    <div class="fs-4 fw-bold mt-1"><?= number_format($pendingOrders) ?></div>
                </div>
                <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-receipt"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small">Today's Revenue</div>
                    <div class="fs-4 fw-bold mt-1"><?= format_currency($totalRevenue) ?></div>
                </div>
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-cash-stack"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small">Points Earned Today</div>
                    <div class="fs-4 fw-bold mt-1"><?= number_format($totalPoints) ?></div>
                </div>
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-star-fill"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Content Row -->
<div class="row g-3">
    <!-- Recent Reservations -->
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar-check me-2 text-primary"></i>Today's Reservations</h6>
                <a href="/admin/reservations" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Ref No</th>
                                <th>Customer</th>
                                <th>Outlet</th>
                                <th>Time</th>
                                <th>Pax</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentReservations as $res): ?>
                            <tr>
                                <td><code class="small"><?= htmlspecialchars($res['reservation_no']) ?></code></td>
                                <td><?= htmlspecialchars($res['customer_name']) ?></td>
                                <td><?= htmlspecialchars($res['outlet_name']) ?></td>
                                <td><?= htmlspecialchars($res['reserved_time']) ?></td>
                                <td><?= $res['party_size'] ?></td>
                                <td>
                                    <?php
                                    $badges = [
                                        'pending'   => 'warning',
                                        'confirmed' => 'primary',
                                        'seated'    => 'info',
                                        'completed' => 'success',
                                        'cancelled' => 'danger',
                                        'no_show'   => 'secondary',
                                    ];
                                    $badge = $badges[$res['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $badge ?>"><?= ucfirst($res['status']) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentReservations)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">No reservations yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Tier Distribution -->
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-bar-chart-fill me-2 text-warning"></i>Customer Tiers</h6>
            </div>
            <div class="card-body">
                <?php
                $tierColors = ['bronze'=>'#cd7f32','silver'=>'#a8a9ad','gold'=>'#ffd700','platinum'=>'#b5c4d4'];
                $tierIcons  = ['bronze'=>'🥉','silver'=>'🥈','gold'=>'🥇','platinum'=>'💎'];
                $tierList   = ['bronze','silver','gold','platinum'];
                $tierTotal  = array_sum($tierMap) ?: 1;
                foreach ($tierList as $t):
                    $cnt = $tierMap[$t] ?? 0;
                    $pct = round($cnt / $tierTotal * 100);
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?= $tierIcons[$t] ?> <?= ucfirst($t) ?></span>
                        <span class="fw-semibold"><?= number_format($cnt) ?> (<?= $pct ?>%)</span>
                    </div>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $tierColors[$t] ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>

                <hr>
                <div class="d-flex justify-content-between align-items-center small">
                    <span class="text-muted">Total Members</span>
                    <strong><?= number_format($totalCustomers) ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
