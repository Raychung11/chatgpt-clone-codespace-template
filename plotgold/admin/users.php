<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_admin();

$action = clean($_GET['action'] ?? '');
$userId = clean_int($_GET['id'] ?? 0);
$flash  = '';

// ── POST actions ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();
    $act = clean($_POST['action'] ?? '');

    // Change user status
    if ($act === 'update_status' && ($id = clean_int($_POST['user_id'] ?? 0))) {
        $status = clean($_POST['status'] ?? '');
        if (in_array($status, ['active','suspended','pending'])) {
            Database::query('UPDATE users SET status = ? WHERE id = ?', [$status, $id]);
            activity_log(auth_user_id(), 'admin_user_status', 'users', $id, "Status changed to $status");
            flash_set(FLASH_SUCCESS, 'User status updated.');
        }
        redirect('admin/users.php');
    }

    // Unlock account
    if ($act === 'unlock' && ($id = clean_int($_POST['user_id'] ?? 0))) {
        Database::query('UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?', [$id]);
        activity_log(auth_user_id(), 'admin_user_unlock', 'users', $id, 'Account unlocked by admin');
        flash_set(FLASH_SUCCESS, 'Account unlocked.');
        redirect('admin/users.php');
    }

    // Create new user
    if ($act === 'create_user') {
        $fullName  = clean($_POST['full_name']  ?? '');
        $email     = clean_email($_POST['email'] ?? '');
        $phone     = clean($_POST['phone']       ?? '');
        $password  = $_POST['password']          ?? '';
        $roleType  = in_array($_POST['role'] ?? '', ['buyer','seller','provider','admin']) ? clean($_POST['role']) : 'buyer';
        $bizName   = clean($_POST['business_name'] ?? '');

        if (!$fullName || !$email || strlen($password) < 8) {
            flash_set(FLASH_ERROR, 'Name, email, and a password of at least 8 characters are required.');
            redirect('admin/users.php?action=new');
        } else {
            $roleMap = ['buyer' => ROLE_BUYER, 'seller' => ROLE_SELLER, 'provider' => ROLE_PROVIDER, 'admin' => ROLE_ADMIN];
            $result  = auth_register([
                'full_name'     => $fullName,
                'email'         => $email,
                'phone'         => $phone,
                'password'      => $password,
                'business_name' => $bizName,
            ], $roleMap[$roleType]);

            if ($result['success']) {
                activity_log(auth_user_id(), 'admin_user_created', 'users', $result['user_id'], "Admin created user: $email");
                flash_set(FLASH_SUCCESS, "User $email created successfully.");
                redirect('admin/users.php');
            } else {
                flash_set(FLASH_ERROR, $result['error']);
                redirect('admin/users.php?action=new');
            }
        }
    }
}

// ── Filters & Pagination ────────────────────────────────────────────────────
$roleFilter   = clean($_GET['role']   ?? '');
$statusFilter = clean($_GET['status'] ?? '');
$q            = clean($_GET['q']      ?? '');
$page         = max(1, clean_int($_GET['page'] ?? 1));
$perPage      = ADMIN_PER_PAGE ?? 20;

$where  = ['1=1'];
$params = [];

if ($q) {
    $where[]  = '(u.email LIKE ? OR up.full_name LIKE ? OR u.phone LIKE ? OR u.referral_code LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($statusFilter) { $where[] = 'u.status = ?';   $params[] = $statusFilter; }
if ($roleFilter) {
    $where[]  = 'EXISTS (SELECT 1 FROM user_role_map urm2 JOIN roles r2 ON r2.id = urm2.role_id WHERE urm2.user_id = u.id AND r2.name = ?)';
    $params[] = $roleFilter;
}

$baseSQL = "SELECT u.id, u.email, u.phone, u.status, u.referral_code, u.login_attempts,
                   u.locked_until, u.last_login_at, u.created_at,
                   up.full_name,
                   GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ',') AS roles
            FROM users u
            LEFT JOIN user_profiles up ON up.user_id = u.id
            LEFT JOIN user_role_map urm ON urm.user_id = u.id
            LEFT JOIN roles r ON r.id = urm.role_id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY u.id";

$total  = (int)(Database::fetchOne("SELECT COUNT(*) c FROM ($baseSQL) x", $params)['c'] ?? 0);
$pages  = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;
$users  = Database::fetchAll("$baseSQL ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset", $params);

// Referral counts per user on current page
$refCounts = [];
if ($users) {
    $ids = array_column($users, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $rows = Database::fetchAll("SELECT referrer_id, COUNT(*) AS cnt FROM referrals WHERE referrer_id IN ($ph) GROUP BY referrer_id", $ids);
    foreach ($rows as $r) $refCounts[$r['referrer_id']] = (int)$r['cnt'];
}

// Status counts for tabs
$statusCounts = [];
foreach (['active','suspended','pending'] as $s) {
    $statusCounts[$s] = (int)(Database::fetchOne("SELECT COUNT(*) c FROM users WHERE status = ?", [$s])['c'] ?? 0);
}

$statusClass = ['active' => 'success', 'suspended' => 'danger', 'pending' => 'warning', 'deleted' => 'secondary'];
$roleColors  = [
    ROLE_BUYER        => 'primary',
    ROLE_SELLER       => 'info',
    ROLE_PROVIDER     => 'warning',
    ROLE_ADMIN        => 'danger',
    ROLE_SUPER_ADMIN  => 'dark',
    ROLE_SUPPORT      => 'secondary',
];

$page_title = 'Manage Users';
$body_class = 'admin-layout';
include INC_PATH . '/header.php';
?>
<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="admin-main">

    <?= render_flash() ?>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h4 class="fw-700 text-navy mb-0">
            <i class="fas fa-users me-2"></i>Users
            <span class="text-muted fw-400 fs-6 ms-2"><?= number_format($total) ?> total</span>
        </h4>
        <a href="<?= pg_url('admin/users.php?action=new') ?>" class="btn btn-gold btn-sm">
            <i class="fas fa-user-plus me-1"></i>Create User
        </a>
    </div>

    <?php if ($action === 'new'): ?>
    <!-- ── Create User Form ──────────────────────────────────────────────── -->
    <div class="pg-card p-4 mb-4">
        <h6 class="fw-600 mb-3">Create New User</h6>
        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_user">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input type="tel" name="phone" class="form-control" placeholder="+60 12-345 6789">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" minlength="8" required placeholder="Min 8 characters">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="buyer">Buyer</option>
                        <option value="seller">Seller</option>
                        <option value="provider">Provider</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Business Name <span class="text-muted small">(provider only)</span></label>
                    <input type="text" name="business_name" class="form-control">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-gold">Create User</button>
                    <a href="<?= pg_url('admin/users.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- ── Filters ───────────────────────────────────────────────────────── -->
    <form method="GET" action="" class="d-flex flex-wrap gap-2 mb-3" id="userFilterForm">
        <input type="text" name="q" class="form-control form-control-sm" style="max-width:220px"
               placeholder="Name, email, phone, code…" value="<?= h($q) ?>">
        <select name="role" class="form-select form-select-sm" style="max-width:160px" onchange="this.form.submit()">
            <option value="">All Roles</option>
            <?php foreach ([ROLE_BUYER => 'Buyer', ROLE_SELLER => 'Seller', ROLE_PROVIDER => 'Provider', ROLE_ADMIN => 'Admin'] as $val => $lbl): ?>
                <option value="<?= h($val) ?>" <?= $roleFilter === $val ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="form-select form-select-sm" style="max-width:140px" onchange="this.form.submit()">
            <option value="">All Status</option>
            <?php foreach (['active','suspended','pending'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?> (<?= $statusCounts[$s] ?? 0 ?>)</option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-navy">Search</button>
        <?php if ($q || $roleFilter || $statusFilter): ?>
            <a href="<?= pg_url('admin/users.php') ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <!-- ── Users Table ───────────────────────────────────────────────────── -->
    <div class="pg-card">
        <div class="table-responsive">
            <table class="table admin-table mb-0">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role(s)</th>
                        <th>Status</th>
                        <th>Referral</th>
                        <th>Last Login</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u):
                    $roles    = array_filter(explode(',', $u['roles'] ?? ''));
                    $isLocked = $u['locked_until'] && strtotime($u['locked_until']) > time();
                ?>
                <tr>
                    <!-- User info -->
                    <td>
                        <div class="fw-500 small"><?= h($u['full_name'] ?? '—') ?></div>
                        <div class="text-muted" style="font-size:.75rem"><?= h($u['email']) ?></div>
                        <?php if ($u['phone']): ?>
                            <div class="text-muted" style="font-size:.73rem"><?= h($u['phone']) ?></div>
                        <?php endif; ?>
                        <?php if ($isLocked): ?>
                            <span class="badge bg-danger" style="font-size:.6rem"><i class="fas fa-lock me-1"></i>Locked</span>
                        <?php endif; ?>
                    </td>

                    <!-- Roles -->
                    <td>
                        <?php foreach ($roles as $role): ?>
                            <span class="badge bg-<?= $roleColors[$role] ?? 'secondary' ?> me-1" style="font-size:.65rem">
                                <?= h(str_replace('_', ' ', $role)) ?>
                            </span>
                        <?php endforeach; ?>
                    </td>

                    <!-- Status -->
                    <td>
                        <span class="badge bg-<?= $statusClass[$u['status']] ?? 'secondary' ?>">
                            <?= ucfirst($u['status']) ?>
                        </span>
                    </td>

                    <!-- Referral -->
                    <td>
                        <?php if ($u['referral_code']): ?>
                            <div class="fw-500 small" style="letter-spacing:.05em"><?= h($u['referral_code']) ?></div>
                            <div class="text-muted" style="font-size:.72rem">
                                <?= $refCounts[$u['id']] ?? 0 ?> referral<?= ($refCounts[$u['id']] ?? 0) === 1 ? '' : 's' ?>
                            </div>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Last login -->
                    <td class="small text-muted">
                        <?= $u['last_login_at'] ? time_ago($u['last_login_at']) : 'Never' ?>
                    </td>

                    <!-- Joined -->
                    <td class="small text-muted"><?= date('d M Y', strtotime($u['created_at'])) ?></td>

                    <!-- Actions -->
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <!-- Status dropdown -->
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="fas fa-cog"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php foreach (['active' => 'Activate', 'suspended' => 'Suspend', 'pending' => 'Set Pending'] as $s => $lbl): ?>
                                        <?php if ($u['status'] !== $s): ?>
                                        <li>
                                            <form method="POST" action="" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="status" value="<?= $s ?>">
                                                <button type="submit" class="dropdown-item small"
                                                        onclick="return confirm('Set status to <?= $s ?>?')"><?= $lbl ?></button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <?php if ($isLocked): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="unlock">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="dropdown-item small text-success">
                                                <i class="fas fa-unlock me-1"></i>Unlock Account
                                            </button>
                                        </form>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>

                            <!-- View role-specific portal -->
                            <?php if (in_array(ROLE_SELLER, $roles)): ?>
                                <a href="<?= pg_url('admin/listings.php?seller_id=' . $u['id']) ?>" class="btn btn-sm btn-outline-gold" title="View listings">
                                    <i class="fas fa-list"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$users): ?>
                <tr><td colspan="7" class="text-center text-muted py-5">No users found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-3">
        <?= pagination_links([
            'rows'     => $users,
            'total'    => $total,
            'pages'    => $pages,
            'page'     => $page,
            'per_page' => $perPage,
            'has_prev' => $page > 1,
            'has_next' => $page < $pages,
        ], pg_url('admin/users.php') . '?' . http_build_query(array_diff_key($_GET, ['page' => '']))) ?>
    </div>

</div>
</div>
<?php include INC_PATH . '/footer.php'; ?>
