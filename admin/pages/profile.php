<?php
/**
 * Admin – My Profile
 * /admin/pages/profile.php
 */

$pageTitle  = 'My Profile';
$activePage = 'profile';

$adminUser = Auth::currentUser();
if (!$adminUser) { header('Location: /admin/login'); exit; }

$errors  = [];
$success = false;

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!Auth::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $name  = sanitize_string($_POST['name']  ?? '');
        $email = sanitize_string($_POST['email'] ?? '');

        if (!$name)                          $errors[] = 'Name is required.';
        if ($email && !validate_email($email)) $errors[] = 'Invalid email address.';

        if (empty($errors)) {
            Database::execute(
                'UPDATE users SET name = ?, email = ? WHERE id = ?',
                [$name, $email ?: null, $adminUser['id']]
            );
            admin_log('update_profile', 'profile', $adminUser['id']);
            flash('success', 'Profile updated.');
            header('Location: /admin/profile');
            exit;
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!Auth::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$current || !$new || !$confirm) {
            $errors[] = 'All password fields are required.';
        } elseif (!password_verify($current, $adminUser['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        } else {
            Database::execute(
                'UPDATE users SET password_hash = ? WHERE id = ?',
                [password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]), $adminUser['id']]
            );
            admin_log('change_password', 'profile', $adminUser['id']);
            flash('success', 'Password changed successfully.');
            header('Location: /admin/profile');
            exit;
        }
    }
}

// Recent activity log for this admin
$recentLogs = Database::fetchAll(
    'SELECT * FROM admin_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 15',
    [$adminUser['id']]
);

$csrf = Auth::generateCsrfToken();
require __DIR__ . '/../layout/header.php';
?>

<?php if ($errors): ?>
<div class="alert alert-danger py-2 small mb-3">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="row g-3">
    <!-- Profile Info -->
    <div class="col-lg-4">
        <!-- Avatar card -->
        <div class="card border-0 shadow-sm mb-3 text-center p-3">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger text-white mx-auto mb-3"
                 style="width:70px;height:70px;font-size:1.8rem;font-weight:700;">
                <?= strtoupper(substr($adminUser['name'], 0, 1)) ?>
            </div>
            <h6 class="fw-bold mb-0"><?= htmlspecialchars($adminUser['name']) ?></h6>
            <div class="text-muted small"><?= htmlspecialchars($adminUser['email'] ?? '') ?></div>
            <div class="mt-2">
                <span class="badge bg-danger"><?= ucfirst($adminUser['role']) ?></span>
            </div>
            <hr class="my-2">
            <div class="small text-muted text-start">
                <div class="mb-1"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($adminUser['phone']) ?></div>
                <div class="mb-1"><i class="bi bi-clock me-1"></i>
                    Joined <?= date('d M Y', strtotime($adminUser['created_at'])) ?>
                </div>
                <div><i class="bi bi-box-arrow-in-right me-1"></i>
                    Last login: <?= $adminUser['last_login_at'] ? date('d M Y H:i', strtotime($adminUser['last_login_at'])) : 'N/A' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Forms -->
    <div class="col-lg-8">
        <!-- Update Profile -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-person me-2 text-primary"></i>Profile Details</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Full Name *</label>
                            <input type="text" name="name" class="form-control"
                                   value="<?= htmlspecialchars($adminUser['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Email Address</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= htmlspecialchars($adminUser['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Phone</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($adminUser['phone']) ?>" disabled>
                            <div class="form-text">Contact superadmin to change phone number.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Role</label>
                            <input type="text" class="form-control" value="<?= ucfirst($adminUser['role']) ?>" disabled>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button name="update_profile" type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-check-lg me-1"></i>Save Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-shield-lock me-2 text-warning"></i>Change Password</h6>
            </div>
            <div class="card-body">
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Current Password *</label>
                            <input type="password" name="current_password" class="form-control"
                                   placeholder="••••••••" autocomplete="current-password">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">New Password *</label>
                            <input type="password" name="new_password" class="form-control" id="new-pw"
                                   placeholder="Min 8 chars" autocomplete="new-password">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Confirm Password *</label>
                            <input type="password" name="confirm_password" class="form-control" id="confirm-pw"
                                   placeholder="Repeat new password" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button name="change_password" type="submit" class="btn btn-warning btn-sm"
                                onclick="return validatePw()">
                            <i class="bi bi-shield-check me-1"></i>Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Recent Activity</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0 small">
                    <thead class="table-light">
                        <tr><th>Action</th><th>Module</th><th>IP</th><th>Time</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentLogs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['action']) ?></td>
                            <td><span class="badge bg-light text-dark"><?= htmlspecialchars($log['module']) ?></span></td>
                            <td class="text-muted"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
                            <td class="text-muted"><?= time_ago($log['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentLogs)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No activity yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function validatePw() {
    const np = document.getElementById('new-pw').value;
    const cp = document.getElementById('confirm-pw').value;
    if (np.length < 8) { alert('Password must be at least 8 characters.'); return false; }
    if (np !== cp)     { alert('Passwords do not match.'); return false; }
    return true;
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
