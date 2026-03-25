<?php
/**
 * Admin – Orders Management
 * /admin/pages/orders.php
 */

$pageTitle  = 'Orders';
$activePage = 'orders';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (Auth::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $oid    = sanitize_int($_POST['order_id'] ?? 0);
        $status = sanitize_string($_POST['status'] ?? '');
        $allowed = ['pending','confirmed','preparing','ready','completed','cancelled'];

        if ($oid && in_array($status, $allowed)) {
            Database::execute('UPDATE orders SET status = ? WHERE id = ?', [$status, $oid]);

            // Award loyalty points on completion
            if ($status === 'completed') {
                $order = Database::fetchOne('SELECT user_id, total, outlet_id, points_earned FROM orders WHERE id = ?', [$oid]);
                if ($order && $order['points_earned'] == 0) {
                    $settings = get_settings(['loyalty_points_per_myr']);
                    $ppm = (int) ($settings['loyalty_points_per_myr'] ?? 1);
                    $pts = (int) floor($order['total'] * $ppm);
                    if ($pts > 0) {
                        add_loyalty_points($order['user_id'], $pts, 'earn', 'order', $oid, 'Order completed', $order['outlet_id']);
                        Database::execute('UPDATE orders SET points_earned = ? WHERE id = ?', [$pts, $oid]);
                    }
                }
            }
            admin_log('update_order', 'orders', $oid, "Status: {$status}");
            flash('success', 'Order status updated.');
        }
    }
    header('Location: /admin/orders'); exit;
}

// Filters
$search  = sanitize_string($_GET['search'] ?? '');
$status  = sanitize_string($_GET['status'] ?? '');
$outlet  = sanitize_int($_GET['outlet']    ?? 0);
$date    = sanitize_string($_GET['date']   ?? '');
$page    = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 25;

$where  = '1=1';
$params = [];

if ($search) {
    $where .= ' AND (o.order_no LIKE ? OR u.name LIKE ?)';
    $s = "%{$search}%";
    $params = array_merge($params, [$s, $s]);
}
if ($status) { $where .= ' AND o.status = ?'; $params[] = $status; }
if ($outlet) { $where .= ' AND o.outlet_id = ?'; $params[] = $outlet; }
if ($date)   { $where .= ' AND DATE(o.created_at) = ?'; $params[] = $date; }

$total  = (int) Database::fetchOne("SELECT COUNT(*) AS c FROM orders o JOIN users u ON u.id=o.user_id WHERE {$where}", $params)['c'];
$pages  = (int) ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$orders = Database::fetchAll(
    "SELECT o.*, u.name AS customer_name, ot.name AS outlet_name
     FROM orders o
     JOIN users u ON u.id = o.user_id
     JOIN outlets ot ON ot.id = o.outlet_id
     WHERE {$where}
     ORDER BY o.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

$outlets = Database::fetchAll("SELECT id, name FROM outlets ORDER BY name");

// Today's revenue
$todayRev = (float) Database::fetchOne(
    "SELECT COALESCE(SUM(total),0) AS r FROM orders WHERE payment_status='paid' AND DATE(created_at)=CURDATE()"
)['r'];

require __DIR__ . '/../layout/header.php';
?>

<!-- Stats -->
<div class="row g-3 mb-3">
    <?php
    $statuses = ['pending'=>['warning','clock'],'confirmed'=>['primary','check'],'preparing'=>['info','fire'],'ready'=>['success','bag'],'completed'=>['success','check-circle'],'cancelled'=>['danger','x-circle']];
    foreach ($statuses as $st => [$color, $icon]):
        $cnt = (int) Database::fetchOne("SELECT COUNT(*) AS c FROM orders WHERE status = ?", [$st])['c'];
    ?>
    <div class="col-6 col-md-2">
        <div class="stat-card text-center">
            <div class="fs-5 fw-bold text-<?= $color ?>"><?= $cnt ?></div>
            <div class="text-muted" style="font-size:.75rem;"><?= ucfirst($st) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Order no, customer…" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <?php foreach (array_keys($statuses) as $st): ?>
                    <option value="<?= $st ?>" <?= $status===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="outlet" class="form-select form-select-sm">
                    <option value="">All Outlets</option>
                    <?php foreach ($outlets as $o): ?>
                    <option value="<?= $o['id'] ?>" <?= $outlet==$o['id']?'selected':'' ?>><?= htmlspecialchars($o['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="date" class="form-control form-control-sm" value="<?= $date ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                <?php if ($search||$status||$outlet||$date): ?>
                <a href="/admin/orders" class="btn btn-sm btn-outline-secondary ms-1">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Orders Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-2 d-flex justify-content-between align-items-center">
        <span class="small text-muted"><?= number_format($total) ?> orders</span>
        <span class="small fw-semibold">Today's Revenue: <span class="text-success"><?= format_currency($todayRev) ?></span></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Order No</th>
                        <th>Customer</th>
                        <th>Outlet</th>
                        <th>Type</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $ord):
                        $stColors = ['pending'=>'warning','confirmed'=>'primary','preparing'=>'info','ready'=>'success','completed'=>'success','cancelled'=>'danger'];
                    ?>
                    <tr>
                        <td><code><?= htmlspecialchars($ord['order_no']) ?></code></td>
                        <td><?= htmlspecialchars($ord['customer_name']) ?></td>
                        <td><?= htmlspecialchars($ord['outlet_name']) ?></td>
                        <td><span class="badge bg-secondary"><?= ucfirst($ord['order_type']) ?></span></td>
                        <td><strong><?= format_currency((float)$ord['total']) ?></strong></td>
                        <td>
                            <span class="badge bg-<?= $ord['payment_status']==='paid'?'success':'warning' ?>">
                                <?= ucfirst($ord['payment_status']) ?>
                            </span>
                        </td>
                        <td><span class="badge bg-<?= $stColors[$ord['status']] ?? 'secondary' ?>"><?= ucfirst($ord['status']) ?></span></td>
                        <td class="text-muted"><?= date('H:i', strtotime($ord['created_at'])) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/orders/view?id=<?= $ord['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2"><i class="bi bi-eye"></i></a>
                                <?php if (!in_array($ord['status'], ['completed','cancelled'])): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="_csrf_token" value="<?= Auth::generateCsrfToken() ?>">
                                    <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                                    <select name="status" class="form-select form-select-sm d-inline" style="width:auto;" onchange="this.form.submit()">
                                        <?php foreach (array_keys($stColors) as $st): ?>
                                        <option value="<?= $st ?>" <?= $ord['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($orders)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No orders found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top small">
            <span class="text-muted">Page <?= $page ?> of <?= $pages ?></span>
            <nav><ul class="pagination pagination-sm mb-0">
                <?php for ($i = max(1,$page-2); $i <= min($pages,$page+2); $i++): ?>
                <li class="page-item <?= $i===$page?'active':'' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
            </ul></nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
