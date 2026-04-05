<?php
/**
 * Admin – Edit Customer
 * /admin/pages/customers_edit.php
 */

$id = sanitize_int($_GET['id'] ?? 0);
if (!$id) { header('Location: /admin/customers'); exit; }

$customer = Database::fetchOne(
    'SELECT u.*, cp.gender, cp.date_of_birth, cp.avatar_url, cp.total_points,
            cp.lifetime_points, cp.tier, cp.referral_code, cp.address,
            cp.preferred_outlet_id
     FROM users u
     LEFT JOIN customer_profiles cp ON cp.user_id = u.id
     WHERE u.id = ? AND u.role = "customer"',
    [$id]
);
if (!$customer) { flash('error', 'Customer not found.'); header('Location: /admin/customers'); exit; }

$outlets = Database::fetchAll("SELECT id, name FROM outlets WHERE status = 'active' ORDER BY name");
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $name       = sanitize_string($_POST['name']               ?? '');
        $email      = sanitize_string($_POST['email']              ?? '');
        $phone      = sanitize_string($_POST['phone']              ?? '');
        $status     = in_array($_POST['status'], ['active','inactive','banned']) ? $_POST['status'] : 'active';
        $gender     = in_array($_POST['gender'] ?? '', ['male','female','other']) ? $_POST['gender'] : null;
        $dob        = sanitize_string($_POST['date_of_birth']      ?? '');
        $address    = sanitize_string($_POST['address']            ?? '', 255);
        $prefOutlet = sanitize_int($_POST['preferred_outlet_id']   ?? 0) ?: null;
        $newPassword= $_POST['new_password'] ?? '';

        // Validate
        if (!$name)  $errors[] = 'Name is required.';
        if (!$phone) $errors[] = 'Phone is required.';
        if ($email && !validate_email($email)) $errors[] = 'Invalid email address.';
        if ($phone && !validate_phone($phone)) $errors[] = 'Invalid phone format. Use 60xxxxxxxxx.';

        // Check phone uniqueness (exclude current user)
        if ($phone) {
            $existing = Database::fetchOne('SELECT id FROM users WHERE phone = ? AND id != ?', [$phone, $id]);
            if ($existing) $errors[] = 'Phone number already in use by another customer.';
        }

        if (empty($errors)) {
            Database::beginTransaction();
            try {
                // Update user
                if ($newPassword) {
                    Database::execute(
                        'UPDATE users SET name=?, email=?, phone=?, status=?, password_hash=? WHERE id=?',
                        [$name, $email ?: null, $phone, $status, password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]), $id]
                    );
                } else {
                    Database::execute(
                        'UPDATE users SET name=?, email=?, phone=?, status=? WHERE id=?',
                        [$name, $email ?: null, $phone, $status, $id]
                    );
                }

                // Update profile
                Database::execute(
                    'UPDATE customer_profiles SET gender=?, date_of_birth=?, address=?, preferred_outlet_id=? WHERE user_id=?',
                    [$gender, $dob ?: null, $address ?: null, $prefOutlet, $id]
                );

                Database::commit();
                admin_log('update_customer', 'customers', $id, "Updated customer profile");
                flash('success', 'Customer updated successfully.');
                header("Location: /admin/customers/view?id={$id}");
                exit;

            } catch (Exception $e) {
                Database::rollback();
                $errors[] = 'Update failed. Please try again.';
                error_log('[Admin] Customer edit error: ' . $e->getMessage());
            }
        }
    }
}

$v          = $customer;
$pageTitle  = 'Edit Customer: ' . $customer['name'];
$activePage = 'customers';
$csrf       = Auth::generateCsrfToken();

require __DIR__ . '/../layout/header.php';
?>

<div class="mb-3 d-flex gap-2">
    <a href="/admin/customers/view?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Profile
    </a>
</div>

<div class="row g-3">
    <!-- Edit Form -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-pencil me-2 text-primary"></i>Edit Customer Details</h6>
            </div>
            <div class="card-body">

                <?php if ($errors): ?>
                <div class="alert alert-danger py-2 small">
                    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">

                    <h6 class="small fw-bold text-uppercase text-muted mb-3">Account Information</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Full Name *</label>
                            <input type="text" name="name" class="form-control"
                                   value="<?= htmlspecialchars($_POST['name'] ?? $v['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Phone Number *</label>
                            <input type="text" name="phone" class="form-control"
                                   value="<?= htmlspecialchars($_POST['phone'] ?? $v['phone']) ?>"
                                   placeholder="60xxxxxxxxx" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Email</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= htmlspecialchars($_POST['email'] ?? $v['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Account Status</label>
                            <select name="status" class="form-select">
                                <?php foreach (['active'=>'Active','inactive'=>'Inactive','banned'=>'Banned'] as $val=>$lbl): ?>
                                <option value="<?= $val ?>" <?= ($v['status']===$val)?'selected':'' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">New Password <span class="text-muted fw-normal">(leave blank to keep current)</span></label>
                            <input type="password" name="new_password" class="form-control"
                                   placeholder="••••••••" autocomplete="new-password">
                        </div>
                    </div>

                    <hr class="my-3">
                    <h6 class="small fw-bold text-uppercase text-muted mb-3">Personal Information</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">Prefer not to say</option>
                                <option value="male"   <?= ($v['gender']==='male')  ?'selected':'' ?>>Male</option>
                                <option value="female" <?= ($v['gender']==='female')?'selected':'' ?>>Female</option>
                                <option value="other"  <?= ($v['gender']==='other') ?'selected':'' ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control"
                                   value="<?= htmlspecialchars($v['date_of_birth'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Address</label>
                            <input type="text" name="address" class="form-control"
                                   value="<?= htmlspecialchars($v['address'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Preferred Outlet</label>
                            <select name="preferred_outlet_id" class="form-select">
                                <option value="">No preference</option>
                                <?php foreach ($outlets as $o): ?>
                                <option value="<?= $o['id'] ?>"
                                    <?= ($v['preferred_outlet_id'] == $o['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($o['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Save Changes
                        </button>
                        <a href="/admin/customers/view?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sidebar info -->
    <div class="col-lg-4">
        <!-- Loyalty Tier -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-star me-2 text-warning"></i>Loyalty Info</h6>
            </div>
            <div class="card-body small">
                <?php
                $tierIcons  = ['bronze'=>'🥉','silver'=>'🥈','gold'=>'🥇','platinum'=>'💎'];
                $tierColors = ['bronze'=>'warning','silver'=>'secondary','gold'=>'warning','platinum'=>'info'];
                $t = $v['tier'] ?? 'bronze';
                ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Tier</span>
                    <span class="badge bg-<?= $tierColors[$t] ?>"><?= $tierIcons[$t] ?> <?= ucfirst($t) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Current Points</span>
                    <strong><?= number_format((int)$v['total_points']) ?> pts</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Lifetime Points</span>
                    <strong><?= number_format((int)$v['lifetime_points']) ?> pts</strong>
                </div>
                <?php if ($v['referral_code']): ?>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Referral Code</span>
                    <code><?= htmlspecialchars($v['referral_code']) ?></code>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="card border-0 shadow-sm border-danger">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-semibold text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Danger Zone</h6>
            </div>
            <div class="card-body small">
                <p class="text-muted mb-2">Changing status to <strong>Banned</strong> will prevent this customer from logging in.</p>
                <p class="text-muted mb-0">Use the status field above to manage account access.</p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
