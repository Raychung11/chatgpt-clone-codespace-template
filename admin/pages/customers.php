<?php
/**
 * Admin – Customers List
 * /admin/pages/customers.php
 */

$pageTitle  = 'Customers';
$activePage = 'customers';

// Filters
$search  = sanitize_string($_GET['search'] ?? '');
$tier    = sanitize_string($_GET['tier']   ?? '');
$status  = sanitize_string($_GET['status'] ?? '');
$page    = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 20;

// Build query
$where  = '1=1';
$params = [];

if ($search) {
    $where   .= ' AND (u.name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)';
    $s = "%{$search}%";
    $params = array_merge($params, [$s, $s, $s]);
}
if ($tier) {
    $where  .= ' AND cp.tier = ?';
    $params[] = $tier;
}
if ($status) {
    $where  .= ' AND u.status = ?';
    $params[] = $status;
}

$total   = (int) Database::fetchOne(
    "SELECT COUNT(*) AS c FROM users u LEFT JOIN customer_profiles cp ON cp.user_id = u.id WHERE u.role='customer' AND {$where}",
    $params
)['c'];
$pages   = (int) ceil($total / $perPage);
$offset  = ($page - 1) * $perPage;

$customers = Database::fetchAll(
    "SELECT u.id, u.name, u.phone, u.email, u.status, u.created_at, u.last_login_at,
            cp.tier, cp.total_points, cp.referral_code
     FROM users u
     LEFT JOIN customer_profiles cp ON cp.user_id = u.id
     WHERE u.role = 'customer' AND {$where}
     ORDER BY u.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

require __DIR__ . '/../layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <span class="text-muted small"><?= number_format($total) ?> customers found</span>
    </div>
    <a href="/admin/customers/create" class="btn btn-primary btn-sm">
        <i class="bi bi-person-plus me-1"></i>Add Customer
    </a>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Search name, phone, email…" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="tier" class="form-select form-select-sm">
                    <option value="">All Tiers</option>
                    <option value="bronze"   <?= $tier==='bronze'   ? 'selected':'' ?>>Bronze</option>
                    <option value="silver"   <?= $tier==='silver'   ? 'selected':'' ?>>Silver</option>
                    <option value="gold"     <?= $tier==='gold'     ? 'selected':'' ?>>Gold</option>
                    <option value="platinum" <?= $tier==='platinum' ? 'selected':'' ?>>Platinum</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="active"   <?= $status==='active'   ? 'selected':'' ?>>Active</option>
                    <option value="inactive" <?= $status==='inactive' ? 'selected':'' ?>>Inactive</option>
                    <option value="banned"   <?= $status==='banned'   ? 'selected':'' ?>>Banned</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
            </div>
            <?php if ($search || $tier || $status): ?>
            <div class="col-md-2">
                <a href="/admin/customers" class="btn btn-sm btn-outline-secondary w-100">Clear</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Tier</th>
                        <th>Points</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Last Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c): ?>
                    <tr>
                        <td class="text-muted"><?= $c['id'] ?></td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($c['name']) ?></div>
                            <?php if ($c['email']): ?>
                            <div class="text-muted" style="font-size:.78rem;"><?= htmlspecialchars($c['email']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($c['phone']) ?></td>
                        <td>
                            <?php
                            $tierBadges = ['bronze'=>'warning','silver'=>'secondary','gold'=>'warning','platinum'=>'info'];
                            $tierIcons  = ['bronze'=>'🥉','silver'=>'🥈','gold'=>'🥇','platinum'=>'💎'];
                            $t = $c['tier'] ?? 'bronze';
                            ?>
                            <span class="badge bg-<?= $tierBadges[$t] ?? 'secondary' ?>"><?= $tierIcons[$t] ?? '' ?> <?= ucfirst($t) ?></span>
                        </td>
                        <td><strong><?= number_format((int) $c['total_points']) ?></strong> pts</td>
                        <td>
                            <?php $st = $c['status']; ?>
                            <span class="badge bg-<?= $st==='active' ? 'success' : ($st==='banned' ? 'danger' : 'secondary') ?>">
                                <?= ucfirst($st) ?>
                            </span>
                        </td>
                        <td class="text-muted"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                        <td class="text-muted">
                            <?= $c['last_login_at'] ? time_ago($c['last_login_at']) : '—' ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/customers/view?id=<?= $c['id'] ?>" class="btn btn-xs btn-outline-primary btn-sm py-0 px-2" title="View"><i class="bi bi-eye"></i></a>
                                <a href="/admin/customers/edit?id=<?= $c['id'] ?>" class="btn btn-xs btn-outline-secondary btn-sm py-0 px-2" title="Edit"><i class="bi bi-pencil"></i></a>
                                <?php if ($c['status'] === 'active'): ?>
                                <a href="/admin/customers?toggle_status=<?= $c['id'] ?>&_csrf=<?= Auth::generateCsrfToken() ?>"
                                   class="btn btn-xs btn-outline-warning btn-sm py-0 px-2" title="Deactivate"
                                   onclick="return confirm('Deactivate this customer?')"><i class="bi bi-pause"></i></a>
                                <?php else: ?>
                                <a href="/admin/customers?toggle_status=<?= $c['id'] ?>&activate=1&_csrf=<?= Auth::generateCsrfToken() ?>"
                                   class="btn btn-xs btn-outline-success btn-sm py-0 px-2" title="Activate"
                                   onclick="return confirm('Activate this customer?')"><i class="bi bi-play"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($customers)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No customers found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top small">
            <span class="text-muted">Page <?= $page ?> of <?= $pages ?></span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = max(1, $page-2); $i <= min($pages, $page+2); $i++): ?>
                    <li class="page-item <?= $i===$page ? 'active':'' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Handle toggle status action
if (isset($_GET['toggle_status']) && Auth::validateCsrfToken($_GET['_csrf'] ?? '')) {
    $tid = sanitize_int($_GET['toggle_status']);
    $newStatus = isset($_GET['activate']) ? 'active' : 'inactive';
    Database::execute('UPDATE users SET status = ? WHERE id = ? AND role = "customer"', [$newStatus, $tid]);
    admin_log('update_status', 'customers', $tid, "Status changed to {$newStatus}");
    flash('success', "Customer status updated to {$newStatus}.");
    header('Location: /admin/customers');
    exit;
}

require __DIR__ . '/../layout/footer.php';
?>
