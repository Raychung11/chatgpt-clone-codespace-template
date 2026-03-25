<?php
/**
 * Admin – Reservations Management
 * /admin/pages/reservations.php
 */

$pageTitle  = 'Reservations';
$activePage = 'reservations';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (Auth::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $rid    = sanitize_int($_POST['reservation_id'] ?? 0);
        $status = sanitize_string($_POST['status'] ?? '');
        $allowed = ['pending','confirmed','seated','completed','cancelled','no_show'];

        if ($rid && in_array($status, $allowed)) {
            Database::execute('UPDATE reservations SET status = ?, confirmed_by = ? WHERE id = ?',
                [$status, Auth::currentUserId(), $rid]);

            // Award points for completed reservation
            if ($status === 'completed') {
                $res = Database::fetchOne('SELECT user_id, outlet_id FROM reservations WHERE id = ?', [$rid]);
                if ($res) {
                    $resPts = (int) (get_settings(['reservation_points'])['reservation_points'] ?? 10);
                    add_loyalty_points($res['user_id'], $resPts, 'earn', 'reservation', $rid, 'Reservation completed', $res['outlet_id']);
                    Database::execute('UPDATE reservations SET points_earned = ? WHERE id = ?', [$resPts, $rid]);
                }
            }
            admin_log('update_reservation', 'reservations', $rid, "Status: {$status}");
            flash('success', 'Reservation updated.');
        }
    }
    header('Location: /admin/reservations'); exit;
}

// Filters
$dateFilter   = sanitize_string($_GET['date']   ?? date('Y-m-d'));
$statusFilter = sanitize_string($_GET['status'] ?? '');
$outletFilter = sanitize_int($_GET['outlet']    ?? 0);

$where  = '1=1';
$params = [];

if ($dateFilter) {
    $where   .= ' AND r.reserved_date = ?';
    $params[] = $dateFilter;
}
if ($statusFilter) {
    $where   .= ' AND r.status = ?';
    $params[] = $statusFilter;
}
if ($outletFilter) {
    $where   .= ' AND r.outlet_id = ?';
    $params[] = $outletFilter;
}

$reservations = Database::fetchAll(
    "SELECT r.*, u.name AS customer_name, u.phone AS customer_phone, o.name AS outlet_name
     FROM reservations r
     JOIN users u ON u.id = r.user_id
     JOIN outlets o ON o.id = r.outlet_id
     WHERE {$where}
     ORDER BY r.reserved_time ASC",
    $params
);

$outlets = Database::fetchAll("SELECT id, name FROM outlets WHERE status = 'active' ORDER BY name");

$statusCounts = [];
foreach (['pending','confirmed','seated','completed','cancelled','no_show'] as $s) {
    $statusCounts[$s] = (int) (Database::fetchOne(
        'SELECT COUNT(*) AS c FROM reservations WHERE reserved_date = ?', [$dateFilter ?: date('Y-m-d')]
    )['c'] ?? 0);
}

require __DIR__ . '/../layout/header.php';
?>

<!-- Date Nav + Filters -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Date</label>
                <input type="date" name="date" class="form-control form-control-sm" value="<?= $dateFilter ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <?php foreach (['pending','confirmed','seated','completed','cancelled','no_show'] as $s): ?>
                    <option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Outlet</label>
                <select name="outlet" class="form-select form-select-sm">
                    <option value="">All Outlets</option>
                    <?php foreach ($outlets as $o): ?>
                    <option value="<?= $o['id'] ?>" <?= $outletFilter==$o['id']?'selected':'' ?>><?= htmlspecialchars($o['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Summary badges -->
<div class="d-flex gap-2 flex-wrap mb-3 small">
    <span class="badge bg-warning text-dark py-2 px-3">⏳ Pending: <?= $statusCounts['pending'] ?? 0 ?></span>
    <span class="badge bg-primary py-2 px-3">✅ Confirmed: <?= $statusCounts['confirmed'] ?? 0 ?></span>
    <span class="badge bg-info py-2 px-3">🪑 Seated: <?= $statusCounts['seated'] ?? 0 ?></span>
    <span class="badge bg-success py-2 px-3">🎉 Completed: <?= $statusCounts['completed'] ?? 0 ?></span>
    <span class="badge bg-danger py-2 px-3">❌ Cancelled: <?= $statusCounts['cancelled'] ?? 0 ?></span>
</div>

<!-- Reservations Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Ref No</th>
                        <th>Customer</th>
                        <th>Outlet</th>
                        <th>Time</th>
                        <th>Pax</th>
                        <th>Occasion</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $r):
                        $badge = ['pending'=>'warning','confirmed'=>'primary','seated'=>'info','completed'=>'success','cancelled'=>'danger','no_show'=>'secondary'];
                    ?>
                    <tr>
                        <td><code class="small"><?= htmlspecialchars($r['reservation_no']) ?></code></td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($r['customer_name']) ?></div>
                            <div class="text-muted" style="font-size:.78rem;"><?= htmlspecialchars($r['customer_phone']) ?></div>
                        </td>
                        <td><?= htmlspecialchars($r['outlet_name']) ?></td>
                        <td><strong><?= substr($r['reserved_time'], 0, 5) ?></strong></td>
                        <td><?= $r['party_size'] ?> pax</td>
                        <td class="text-muted"><?= htmlspecialchars($r['occasion'] ?? '—') ?></td>
                        <td><span class="badge bg-<?= $badge[$r['status']] ?? 'secondary' ?>"><?= ucfirst($r['status']) ?></span></td>
                        <td>
                            <!-- Quick status change -->
                            <form method="POST" class="d-flex gap-1">
                                <input type="hidden" name="_csrf_token" value="<?= Auth::generateCsrfToken() ?>">
                                <input type="hidden" name="reservation_id" value="<?= $r['id'] ?>">
                                <select name="status" class="form-select form-select-sm" style="width:auto;">
                                    <?php foreach (['pending','confirmed','seated','completed','cancelled','no_show'] as $st): ?>
                                    <option value="<?= $st ?>" <?= $r['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button name="update_status" class="btn btn-sm btn-outline-primary py-0 px-2">✓</button>
                            </form>
                            <?php if ($r['special_request']): ?>
                            <div class="text-muted mt-1" style="font-size:.75rem;">📝 <?= htmlspecialchars(substr($r['special_request'],0,60)) ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($reservations)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No reservations for this filter.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
