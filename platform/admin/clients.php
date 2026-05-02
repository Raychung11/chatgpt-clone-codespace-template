<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireAdmin();
$pageTitle = 'Customer Management';

$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where  = $search ? 'WHERE (u.email LIKE ? OR u.name LIKE ? OR u.company LIKE ?)' : 'WHERE 1=1';
$params = $search ? ["%$search%", "%$search%", "%$search%"] : [];

$total = DB::fetch("SELECT COUNT(*) as n FROM users u $where AND u.role='customer'", $params)['n'];
$customers = DB::fetchAll(
    "SELECT u.*,
        (SELECT COUNT(*) FROM subscriptions WHERE user_id=u.id AND status='active') as active_subs,
        (SELECT COUNT(*) FROM purchases WHERE user_id=u.id AND status='completed') as purchases
     FROM users u $where AND u.role='customer'
     ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);
$totalPages = ceil($total / $perPage);

// View single customer
$viewCustomer = null;
if (isset($_GET['view'])) {
    $viewCustomer = DB::fetch('SELECT * FROM users WHERE id=? AND role="customer"', [(int)$_GET['view']]);
    if ($viewCustomer) {
        $viewCustomer['subscriptions'] = DB::fetchAll(
            'SELECT s.*, p.name as product_name FROM subscriptions s JOIN products p ON s.product_id=p.id WHERE s.user_id=?',
            [$viewCustomer['id']]
        );
        $viewCustomer['purchases'] = DB::fetchAll(
            'SELECT pu.*, p.name as product_name FROM purchases pu JOIN products p ON pu.product_id=p.id WHERE pu.user_id=?',
            [$viewCustomer['id']]
        );
    }
}

require_once '../includes/admin-header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white fw-bold mb-0">Customers <span class="text-muted fs-6">(<?= number_format($total) ?>)</span></h4>
    </div>

    <form method="GET" class="mb-4">
        <div class="input-group" style="max-width:400px">
            <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control bg-dark border-secondary text-white" placeholder="Search by name, email or company...">
            <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
        </div>
    </form>

    <div class="row g-4">
        <!-- Customer List -->
        <div class="col-lg-<?= $viewCustomer ? '7' : '12' ?>">
            <div class="admin-card rounded-4 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0">
                        <thead class="border-bottom border-secondary">
                            <tr class="text-muted small">
                                <th>Customer</th>
                                <th>Company</th>
                                <th>Active Subs</th>
                                <th>Purchases</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $c): ?>
                            <tr <?= $viewCustomer && $viewCustomer['id'] == $c['id'] ? 'class="table-active"' : '' ?>>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-initials sm"><?= strtoupper(substr($c['name'],0,2)) ?></div>
                                        <div>
                                            <div class="text-white small fw-semibold"><?= htmlspecialchars($c['name']) ?></div>
                                            <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($c['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-muted small"><?= htmlspecialchars($c['company'] ?: '—') ?></td>
                                <td>
                                    <span class="badge <?= $c['active_subs'] > 0 ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $c['active_subs'] ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?= $c['purchases'] ?></td>
                                <td class="text-muted small"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                                <td>
                                    <a href="?view=<?= $c['id'] ?><?= $search ? "&q=$search" : '' ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($customers)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No customers found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($totalPages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination pagination-sm justify-content-center">
                    <?php for ($i=1;$i<=$totalPages;$i++): ?>
                    <li class="page-item <?= $i===$page?'active':'' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>

        <!-- Customer Detail Panel -->
        <?php if ($viewCustomer): ?>
        <div class="col-lg-5">
            <div class="admin-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">
                    <i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($viewCustomer['name']) ?>
                </h6>
                <div class="mb-3">
                    <div class="text-muted small mb-1">Email</div>
                    <div class="text-white small"><?= htmlspecialchars($viewCustomer['email']) ?></div>
                </div>
                <?php if ($viewCustomer['company']): ?>
                <div class="mb-3">
                    <div class="text-muted small mb-1">Company</div>
                    <div class="text-white small"><?= htmlspecialchars($viewCustomer['company']) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($viewCustomer['phone']): ?>
                <div class="mb-3">
                    <div class="text-muted small mb-1">Phone</div>
                    <div class="text-white small"><?= htmlspecialchars($viewCustomer['phone']) ?></div>
                </div>
                <?php endif; ?>
                <div class="mb-4">
                    <div class="text-muted small mb-1">Member Since</div>
                    <div class="text-white small"><?= date('d M Y', strtotime($viewCustomer['created_at'])) ?></div>
                </div>

                <!-- Subscriptions -->
                <?php if ($viewCustomer['subscriptions']): ?>
                <h6 class="text-white fw-semibold mb-2">Subscriptions</h6>
                <?php foreach ($viewCustomer['subscriptions'] as $sub): ?>
                <div class="d-flex justify-content-between align-items-center mb-2 p-2 rounded-3 bg-secondary bg-opacity-10">
                    <div class="text-white small"><?= htmlspecialchars($sub['product_name']) ?></div>
                    <?php $cls = match($sub['status']) {'active'=>'success','trialing'=>'info','canceled'=>'danger',default=>'secondary'}; ?>
                    <span class="badge bg-<?= $cls ?>"><?= ucfirst($sub['status']) ?></span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <!-- Purchases -->
                <?php if ($viewCustomer['purchases']): ?>
                <h6 class="text-white fw-semibold mb-2 mt-3">Purchases</h6>
                <?php foreach ($viewCustomer['purchases'] as $pur): ?>
                <div class="d-flex justify-content-between align-items-center mb-2 p-2 rounded-3 bg-secondary bg-opacity-10">
                    <div class="text-white small"><?= htmlspecialchars($pur['product_name']) ?></div>
                    <span class="text-muted small"><?= APP_CURRENCY ?><?= number_format($pur['amount'], 2) ?></span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/admin-footer.php'; ?>
