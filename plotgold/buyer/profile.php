<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_role(ROLE_BUYER, '/login.php');

$uid   = auth_user_id();
$error = '';

$avatarUploadDir = UPLOAD_PATH . '/avatars';

// ── Load data ──────────────────────────────────────────────────────────────────
$user    = Database::fetchOne('SELECT * FROM users WHERE id = ?', [$uid]);
$profile = Database::fetchOne('SELECT * FROM user_profiles WHERE user_id = ?', [$uid]);
$buyer   = Database::fetchOne('SELECT * FROM buyers WHERE user_id = ?', [$uid]);
$seller  = Database::fetchOne('SELECT * FROM sellers WHERE user_id = ?', [$uid]);
$provider= Database::fetchOne('SELECT * FROM providers WHERE user_id = ?', [$uid]);

if (!$profile) redirect('buyer/dashboard.php');

// ── POST handlers ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();
    $postAction = clean($_POST['_action'] ?? 'save_profile');

    // ── Change password ────────────────────────────────────────────────────────
    if ($postAction === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            Database::query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $uid]);
            activity_log($uid, 'password_changed', 'users', $uid);
            flash_set(FLASH_SUCCESS, 'Password updated successfully.');
            redirect('buyer/profile.php#security');
        }
    }

    // ── Save buyer preferences ─────────────────────────────────────────────────
    elseif ($postAction === 'save_preferences' && $buyer) {
        $buyerType    = clean($_POST['buyer_type']          ?? 'individual');
        $intent       = clean($_POST['purchase_intent']     ?? 'browsing');
        $budgetMin    = clean_float($_POST['budget_min']    ?? '');
        $budgetMax    = clean_float($_POST['budget_max']    ?? '');
        $prefLoc      = clean($_POST['preferred_location']  ?? '');
        $relPref      = clean($_POST['religion_preference'] ?? '');

        Database::query(
            'UPDATE buyers SET buyer_type=?,purchase_intent=?,budget_min=?,budget_max=?,preferred_location=?,religion_preference=? WHERE user_id=?',
            [$buyerType, $intent, $budgetMin ?: null, $budgetMax ?: null, $prefLoc, $relPref, $uid]
        );
        activity_log($uid, 'buyer_preferences_updated', 'buyers', $buyer['id']);
        flash_set(FLASH_SUCCESS, 'Preferences saved.');
        redirect('buyer/profile.php#preferences');
    }

    // ── Save profile (default) ─────────────────────────────────────────────────
    else {
        $fullName  = clean($_POST['full_name']          ?? '');
        $phone     = clean($_POST['phone']              ?? '');
        $gender    = clean($_POST['gender']             ?? '');
        $dob       = clean($_POST['date_of_birth']      ?? '');
        $lang      = clean($_POST['preferred_language'] ?? 'en');
        $addr1     = clean($_POST['address_line1']      ?? '');
        $addr2     = clean($_POST['address_line2']      ?? '');
        $city      = clean($_POST['city']               ?? '');
        $state     = clean($_POST['state']              ?? '');
        $postcode  = clean($_POST['postcode']           ?? '');

        if (!$fullName) {
            $error = 'Full name is required.';
        } else {
            // Avatar upload
            $avatarPath = $profile['avatar_path'] ?? null;

            if (!empty($_POST['remove_avatar'])) {
                if ($avatarPath && file_exists($avatarUploadDir . '/' . $avatarPath)) {
                    @unlink($avatarUploadDir . '/' . $avatarPath);
                }
                $avatarPath = null;
            } elseif (!empty($_FILES['avatar']['name'])) {
                $up = upload_file($_FILES['avatar'], $avatarUploadDir, ALLOWED_IMAGE_TYPES, 5 * 1024 * 1024);
                if (!$up['success']) {
                    $error = 'Avatar: ' . $up['error'];
                } else {
                    if ($avatarPath && file_exists($avatarUploadDir . '/' . $avatarPath)) {
                        @unlink($avatarUploadDir . '/' . $avatarPath);
                    }
                    $avatarPath = $up['filename'];
                }
            }

            if (!$error) {
                Database::query(
                    'UPDATE user_profiles
                     SET full_name=?,gender=?,date_of_birth=?,preferred_language=?,
                         avatar_path=?,address_line1=?,address_line2=?,city=?,state=?,postcode=?
                     WHERE user_id=?',
                    [$fullName, $gender ?: null, $dob ?: null, $lang,
                     $avatarPath, $addr1, $addr2, $city, $state, $postcode, $uid]
                );
                Database::query('UPDATE users SET phone=? WHERE id=?', [$phone, $uid]);
                activity_log($uid, 'profile_updated', 'user_profiles', $uid);
                flash_set(FLASH_SUCCESS, 'Profile updated.');
                redirect('buyer/profile.php');
            }
        }
    }

    // ── Apply as Seller ───────────────────────────────────────────────────────
    if ($postAction === 'apply_seller') {
        if ($seller) {
            flash_set(FLASH_INFO, 'You already have a seller account.');
        } else {
            $sellerType = clean($_POST['seller_type'] ?? 'individual');
            $companyName = clean($_POST['company_name'] ?? '');
            Database::query(
                'INSERT INTO sellers (user_id, seller_type, company_name, verification_status) VALUES (?,?,?,?)',
                [$uid, $sellerType, $companyName ?: null, 'unverified']
            );
            $role = Database::fetchOne('SELECT id FROM roles WHERE name = ?', [ROLE_SELLER]);
            if ($role) {
                Database::query(
                    'INSERT IGNORE INTO user_role_map (user_id, role_id) VALUES (?,?)',
                    [$uid, $role['id']]
                );
            }
            activity_log($uid, 'seller_role_added', 'sellers', $uid, 'Buyer upgraded to seller');
            flash_set(FLASH_SUCCESS, 'Seller account activated! You can now list plots.');
            redirect('seller/dashboard.php');
        }
        redirect('buyer/profile.php#upgrade');
    }

    // ── Apply as Provider ─────────────────────────────────────────────────────
    if ($postAction === 'apply_provider') {
        if ($provider) {
            flash_set(FLASH_INFO, 'You already have a provider application.');
        } else {
            $businessName = clean($_POST['business_name'] ?? '');
            $providerType = clean($_POST['provider_type'] ?? 'funeral_home');
            $description  = clean($_POST['description']   ?? '');
            $website      = clean($_POST['website']       ?? '');
            $whatsapp     = clean($_POST['whatsapp']      ?? '');
            if (!$businessName) {
                $error = 'Business name is required to apply as a provider.';
            } else {
                Database::query(
                    'INSERT INTO providers (user_id, business_name, provider_type, description, website, whatsapp, approval_status) VALUES (?,?,?,?,?,?,?)',
                    [$uid, $businessName, $providerType, $description ?: null, $website ?: null, $whatsapp ?: null, 'pending']
                );
                $role = Database::fetchOne('SELECT id FROM roles WHERE name = ?', [ROLE_PROVIDER]);
                if ($role) {
                    Database::query(
                        'INSERT IGNORE INTO user_role_map (user_id, role_id) VALUES (?,?)',
                        [$uid, $role['id']]
                    );
                }
                activity_log($uid, 'provider_application', 'providers', $uid, 'Buyer applied as provider: ' . $businessName);
                flash_set(FLASH_SUCCESS, 'Provider application submitted! Our team will review within 1–2 business days.');
                redirect('buyer/profile.php#upgrade');
            }
        }
    }

    // Reload after potential error
    $user    = Database::fetchOne('SELECT * FROM users WHERE id = ?', [$uid]);
    $profile = Database::fetchOne('SELECT * FROM user_profiles WHERE user_id = ?', [$uid]);
    $buyer   = Database::fetchOne('SELECT * FROM buyers WHERE user_id = ?', [$uid]);
    $seller  = Database::fetchOne('SELECT * FROM sellers WHERE user_id = ?', [$uid]);
    $provider= Database::fetchOne('SELECT * FROM providers WHERE user_id = ?', [$uid]);
}

// Referral link
$referralCode = $user['referral_code'] ?? null;
$referralLink = $referralCode ? pg_url('register.php?ref=' . $referralCode) : null;

$page_title = 'My Profile';
include INC_PATH . '/header.php';
?>
<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="portal-content">
    <?= render_flash() ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-700 text-navy mb-0">My Profile</h4>
            <p class="text-muted small mb-0">Manage your personal information and account settings.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger mb-4"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- ── LEFT COLUMN ── -->
        <div class="col-lg-4">

            <!-- Avatar card -->
            <div class="pg-card p-4 text-center mb-4">
                <div class="position-relative d-inline-block mb-3">
                    <?php if (!empty($profile['avatar_path'])): ?>
                        <img id="avatarImg"
                             src="<?= pg_url('uploads/avatars/' . h($profile['avatar_path'])) ?>"
                             alt="Avatar"
                             style="width:100px;height:100px;object-fit:cover;border-radius:50%;border:3px solid var(--pg-gold);">
                    <?php else: ?>
                        <div id="avatarImg" class="d-flex align-items-center justify-content-center rounded-circle fw-700"
                             style="width:100px;height:100px;background:var(--pg-gold);font-size:2.2rem;color:#fff;border:3px solid var(--pg-gold);">
                            <?= strtoupper(substr($profile['full_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <h6 class="fw-700 text-navy mb-0"><?= h($profile['full_name']) ?></h6>
                <div class="text-muted small mb-3"><?= h($user['email']) ?></div>
                <div class="badge" style="background:var(--pg-gold-pale);color:var(--pg-gold);border:1px solid rgba(184,134,11,.2);">
                    <i class="fas fa-user me-1"></i>Buyer
                </div>
                <div class="text-muted small mt-2">Member since <?= format_date($user['created_at'], 'M Y') ?></div>

                <!-- Avatar upload form -->
                <form method="POST" enctype="multipart/form-data" class="mt-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="save_profile">
                    <?php foreach (['full_name','phone','gender','date_of_birth','preferred_language','address_line1','address_line2','city','state','postcode'] as $f): ?>
                        <input type="hidden" name="<?= $f ?>" value="<?= h($profile[$f] ?? $user[$f] ?? '') ?>">
                    <?php endforeach; ?>

                    <label class="btn btn-outline-gold btn-sm w-100 mb-2" for="avatarInput">
                        <i class="fas fa-camera me-1"></i>Change Photo
                    </label>
                    <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/webp"
                           class="d-none" onchange="previewAvatar(this)">

                    <?php if (!empty($profile['avatar_path'])): ?>
                    <div class="form-check mb-2">
                        <input type="checkbox" name="remove_avatar" value="1" id="removeAvatar" class="form-check-input">
                        <label for="removeAvatar" class="form-check-label small text-danger">Remove photo</label>
                    </div>
                    <?php endif; ?>

                    <button type="submit" id="avatarSaveBtn" class="btn btn-gold btn-sm w-100" style="display:none;">
                        <i class="fas fa-save me-1"></i>Save Photo
                    </button>
                </form>
            </div>

            <!-- Account info -->
            <div class="pg-card p-4 mb-4">
                <h6 class="fw-600 text-navy mb-3"><i class="fas fa-info-circle me-2" style="color:var(--pg-gold);"></i>Account Info</h6>
                <table class="table table-sm mb-0" style="font-size:.82rem;">
                    <tr>
                        <td class="text-muted border-0 py-1">Email</td>
                        <td class="border-0 py-1 fw-500"><?= h($user['email']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted border-0 py-1">Status</td>
                        <td class="border-0 py-1">
                            <span class="badge <?= $user['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                <?= ucfirst($user['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted border-0 py-1">Joined</td>
                        <td class="border-0 py-1"><?= format_date($user['created_at'], 'd M Y') ?></td>
                    </tr>
                    <?php if ($user['last_login_at']): ?>
                    <tr>
                        <td class="text-muted border-0 py-1">Last login</td>
                        <td class="border-0 py-1"><?= time_ago($user['last_login_at']) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <!-- Referral -->
            <?php if ($referralCode): ?>
            <div class="pg-card p-4" style="border-left:4px solid var(--pg-gold);">
                <h6 class="fw-600 text-navy mb-2"><i class="fas fa-gift me-2" style="color:var(--pg-gold);"></i>Your Referral Code</h6>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <code class="fs-5 fw-700 text-navy" style="letter-spacing:.1em;"><?= h($referralCode) ?></code>
                    <button type="button" class="btn btn-sm btn-outline-gold" onclick="copyReferral()" title="Copy code">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                <div class="input-group input-group-sm mb-2">
                    <input type="text" class="form-control" id="referralLinkInput" value="<?= h($referralLink) ?>" readonly style="font-size:.72rem;">
                    <button class="btn btn-outline-secondary" onclick="copyReferralLink()" title="Copy link">
                        <i class="fas fa-link"></i>
                    </button>
                </div>
                <p class="text-muted small mb-0">Share your link to earn rewards when friends sign up.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── RIGHT COLUMN ── -->
        <div class="col-lg-8">

            <!-- Personal info form -->
            <div class="pg-card p-4 mb-4" id="personal">
                <h6 class="fw-600 text-navy mb-4">
                    <i class="fas fa-user me-2" style="color:var(--pg-gold);"></i>Personal Information
                </h6>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="save_profile">

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-600 small">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" required
                                   value="<?= h($profile['full_name']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-600 small">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">— Select —</option>
                                <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Prefer not to say'] as $val => $lbl): ?>
                                    <option value="<?= $val ?>" <?= ($profile['gender'] ?? '') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-600 small">Phone Number</label>
                            <input type="text" name="phone" class="form-control"
                                   placeholder="e.g. 012-345 6789"
                                   value="<?= h($user['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-600 small">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control"
                                   value="<?= h($profile['date_of_birth'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-600 small">Preferred Language</label>
                            <select name="preferred_language" class="form-select">
                                <option value="en" <?= ($profile['preferred_language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                                <option value="zh" <?= ($profile['preferred_language'] ?? '') === 'zh' ? 'selected' : '' ?>>中文 (Chinese)</option>
                                <option value="ms" <?= ($profile['preferred_language'] ?? '') === 'ms' ? 'selected' : '' ?>>Bahasa Malaysia</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-600 small">Email Address</label>
                            <input type="email" class="form-control bg-light" value="<?= h($user['email']) ?>" disabled>
                            <div class="form-text">Contact support to change your email.</div>
                        </div>

                        <div class="col-12">
                            <hr class="my-1">
                            <label class="form-label fw-600 small mt-2">Address Line 1</label>
                            <input type="text" name="address_line1" class="form-control"
                                   placeholder="Street address, unit number"
                                   value="<?= h($profile['address_line1'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-600 small">Address Line 2</label>
                            <input type="text" name="address_line2" class="form-control"
                                   placeholder="Area, suburb"
                                   value="<?= h($profile['address_line2'] ?? '') ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-600 small">City</label>
                            <input type="text" name="city" class="form-control"
                                   value="<?= h($profile['city'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-600 small">State</label>
                            <select name="state" class="form-select">
                                <option value="">— Select —</option>
                                <?php foreach (MY_STATES as $s): ?>
                                    <option value="<?= h($s) ?>" <?= ($profile['state'] ?? '') === $s ? 'selected' : '' ?>><?= h($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-600 small">Postcode</label>
                            <input type="text" name="postcode" class="form-control" maxlength="10"
                                   value="<?= h($profile['postcode'] ?? '') ?>">
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-gold">
                                <i class="fas fa-save me-1"></i>Save Profile
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Buyer preferences -->
            <?php if ($buyer): ?>
            <div class="pg-card p-4 mb-4" id="preferences">
                <h6 class="fw-600 text-navy mb-4">
                    <i class="fas fa-sliders-h me-2" style="color:var(--pg-gold);"></i>Buying Preferences
                </h6>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="save_preferences">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-600 small">Buyer Type</label>
                            <select name="buyer_type" class="form-select">
                                <?php foreach (['individual' => 'Individual', 'family' => 'Family', 'corporate' => 'Corporate'] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= ($buyer['buyer_type'] ?? 'individual') === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-600 small">Purchase Intent</label>
                            <select name="purchase_intent" class="form-select">
                                <?php foreach ([
                                    'immediate'    => 'Need immediately',
                                    'within_6mo'   => 'Within 6 months',
                                    'within_year'  => 'Within a year',
                                    'planning'     => 'Pre-planning',
                                    'browsing'     => 'Just browsing',
                                ] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= ($buyer['purchase_intent'] ?? 'browsing') === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-600 small">Budget Min (RM)</label>
                            <div class="input-group">
                                <span class="input-group-text">RM</span>
                                <input type="number" name="budget_min" class="form-control" min="0" step="1000"
                                       placeholder="e.g. 5000"
                                       value="<?= $buyer['budget_min'] ? (int)$buyer['budget_min'] : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-600 small">Budget Max (RM)</label>
                            <div class="input-group">
                                <span class="input-group-text">RM</span>
                                <input type="number" name="budget_max" class="form-control" min="0" step="1000"
                                       placeholder="e.g. 50000"
                                       value="<?= $buyer['budget_max'] ? (int)$buyer['budget_max'] : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-600 small">Preferred Location</label>
                            <input type="text" name="preferred_location" class="form-control"
                                   placeholder="e.g. Klang Valley, Selangor"
                                   value="<?= h($buyer['preferred_location'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-600 small">Religion Preference</label>
                            <select name="religion_preference" class="form-select">
                                <option value="">No preference</option>
                                <?php foreach (['Buddhist' => 'Buddhist', 'Taoist' => 'Taoist', 'Christian' => 'Christian', 'Catholic' => 'Catholic', 'Hindu' => 'Hindu', 'Non-religious' => 'Non-religious', 'Multi-faith' => 'Multi-faith'] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= ($buyer['religion_preference'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-gold">
                                <i class="fas fa-save me-1"></i>Save Preferences
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Security / password -->
            <div class="pg-card p-4" id="security">
                <h6 class="fw-600 text-navy mb-4">
                    <i class="fas fa-lock me-2" style="color:var(--pg-gold);"></i>Change Password
                </h6>
                <form method="POST" autocomplete="off">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="change_password">
                    <div class="row g-3">
                        <div class="col-md-10">
                            <label class="form-label fw-600 small">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                        </div>
                        <div class="col-md-10">
                            <label class="form-label fw-600 small">New Password</label>
                            <input type="password" name="new_password" id="newPwd" class="form-control" required minlength="8" autocomplete="new-password">
                            <div class="form-text">Minimum 8 characters.</div>
                        </div>
                        <div class="col-md-10">
                            <label class="form-label fw-600 small">Confirm New Password</label>
                            <input type="password" name="confirm_password" id="confirmPwd" class="form-control" required autocomplete="new-password">
                            <div id="pwdMismatch" class="text-danger small mt-1" style="display:none;">Passwords do not match.</div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-outline-gold">
                                <i class="fas fa-key me-1"></i>Update Password
                            </button>
                        </div>
                    </div>
                </form>
            </div>

        </div><!-- /col-lg-8 -->
    </div><!-- /row -->

    <!-- ── UPGRADE ACCOUNT ─────────────────────────────────────────────────── -->
    <div class="mt-4" id="upgrade">
        <h5 class="fw-700 text-navy mb-1"><i class="fas fa-layer-group me-2" style="color:var(--pg-gold);"></i>Upgrade Your Account</h5>
        <p class="text-muted small mb-4">Your account can hold multiple roles. Add seller or provider access without creating a new account.</p>

        <div class="row g-4">

            <!-- ── Seller card ── -->
            <div class="col-md-6">
                <div class="pg-card p-4 h-100" style="border-top:4px solid <?= $seller ? '#28a745' : 'var(--pg-gold)' ?>;">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width:48px;height:48px;background:<?= $seller ? 'rgba(40,167,69,.12)' : 'var(--pg-gold-pale)' ?>;flex-shrink:0;">
                            <i class="fas fa-tag fs-5" style="color:<?= $seller ? '#28a745' : 'var(--pg-gold)' ?>;"></i>
                        </div>
                        <div>
                            <h6 class="fw-700 mb-0">Sell a Burial Plot</h6>
                            <div class="text-muted small">List your plot or columbarium niche</div>
                        </div>
                        <?php if ($seller): ?>
                            <span class="badge bg-success ms-auto">Active</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($seller): ?>
                        <p class="text-muted small mb-3">You already have a seller account. Your listings are managed from the Seller Portal.</p>
                        <div class="d-flex gap-2">
                            <a href="<?= pg_url('seller/dashboard.php') ?>" class="btn btn-success btn-sm">
                                <i class="fas fa-th-large me-1"></i>Seller Dashboard
                            </a>
                            <a href="<?= pg_url('seller/new_listing.php') ?>" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-plus me-1"></i>New Listing
                            </a>
                        </div>
                    <?php else: ?>
                        <ul class="list-unstyled text-muted small mb-3">
                            <li><i class="fas fa-check text-success me-2"></i>Free to list your burial plot</li>
                            <li><i class="fas fa-check text-success me-2"></i>Reach verified buyers</li>
                            <li><i class="fas fa-check text-success me-2"></i>Guided document verification</li>
                            <li><i class="fas fa-check text-success me-2"></i>Enquiry management dashboard</li>
                        </ul>
                        <button class="btn btn-gold btn-sm" type="button"
                                data-bs-toggle="collapse" data-bs-target="#sellerForm">
                            <i class="fas fa-tag me-1"></i>Become a Seller
                        </button>
                        <div class="collapse mt-3" id="sellerForm">
                            <form method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_action" value="apply_seller">
                                <div class="mb-3">
                                    <label class="form-label fw-600 small">Seller Type</label>
                                    <select name="seller_type" class="form-select form-select-sm">
                                        <option value="individual">Individual (personal plot)</option>
                                        <option value="agent">Agent / Reseller</option>
                                        <option value="developer">Developer</option>
                                        <option value="estate">Estate / Legal Representative</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-600 small">Company Name <span class="text-muted fw-400">(optional)</span></label>
                                    <input type="text" name="company_name" class="form-control form-control-sm"
                                           placeholder="Leave blank if individual">
                                </div>
                                <button type="submit" class="btn btn-gold btn-sm w-100">
                                    <i class="fas fa-check me-1"></i>Activate Seller Account
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Provider card ── -->
            <div class="col-md-6">
                <?php
                $providerStatus = $provider['approval_status'] ?? null;
                $borderColor = match($providerStatus) {
                    'approved'  => '#28a745',
                    'pending'   => '#ffc107',
                    'rejected'  => '#dc3545',
                    default     => 'var(--pg-navy)',
                };
                ?>
                <div class="pg-card p-4 h-100" style="border-top:4px solid <?= $borderColor ?>;">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width:48px;height:48px;background:rgba(26,39,68,.08);flex-shrink:0;">
                            <i class="fas fa-briefcase fs-5 text-navy"></i>
                        </div>
                        <div>
                            <h6 class="fw-700 mb-0">Join as Service Provider</h6>
                            <div class="text-muted small">Funeral homes, florists, transport &amp; more</div>
                        </div>
                        <?php if ($provider): ?>
                            <span class="badge ms-auto
                                <?= $providerStatus === 'approved' ? 'bg-success' : ($providerStatus === 'pending' ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                <?= ucfirst($providerStatus) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($provider && $providerStatus === 'approved'): ?>
                        <p class="text-muted small mb-3">Your provider account is active. Manage your services and quotes from the Provider Portal.</p>
                        <a href="<?= pg_url('provider/dashboard.php') ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-th-large me-1"></i>Provider Dashboard
                        </a>

                    <?php elseif ($provider && $providerStatus === 'pending'): ?>
                        <div class="alert alert-warning py-2 small mb-3">
                            <i class="fas fa-clock me-1"></i>
                            Your application for <strong><?= h($provider['business_name']) ?></strong> is under review.
                            Our team will respond within 1–2 business days.
                        </div>
                        <a href="<?= pg_url('provider/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-eye me-1"></i>View Application
                        </a>

                    <?php elseif ($provider && $providerStatus === 'rejected'): ?>
                        <div class="alert alert-danger py-2 small mb-3">
                            <i class="fas fa-times-circle me-1"></i>
                            Your previous application was not approved. Please contact support for details.
                        </div>

                    <?php else: ?>
                        <ul class="list-unstyled text-muted small mb-3">
                            <li><i class="fas fa-check text-success me-2"></i>Get discovered by families in need</li>
                            <li><i class="fas fa-check text-success me-2"></i>Receive quote requests directly</li>
                            <li><i class="fas fa-check text-success me-2"></i>Build trust with verified profile</li>
                            <li><i class="fas fa-check text-success me-2"></i>Admin approval within 1–2 days</li>
                        </ul>
                        <?php if ($error && str_contains($error, 'Business name')): ?>
                            <div class="alert alert-danger small py-2"><?= h($error) ?></div>
                        <?php endif; ?>
                        <button class="btn btn-outline-navy btn-sm" type="button"
                                data-bs-toggle="collapse" data-bs-target="#providerForm"
                                <?= ($error && str_contains($error, 'Business name')) ? '' : '' ?>>
                            <i class="fas fa-briefcase me-1"></i>Apply as Provider
                        </button>
                        <div class="collapse mt-3 <?= ($error && str_contains($error, 'Business name')) ? 'show' : '' ?>" id="providerForm">
                            <form method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_action" value="apply_provider">
                                <div class="mb-3">
                                    <label class="form-label fw-600 small">Business Name <span class="text-danger">*</span></label>
                                    <input type="text" name="business_name" class="form-control form-control-sm"
                                           required placeholder="Registered business or trading name">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-600 small">Service Type</label>
                                    <select name="provider_type" class="form-select form-select-sm">
                                        <option value="funeral_home">Funeral Home</option>
                                        <option value="transport">Hearse / Transport</option>
                                        <option value="florist">Florist</option>
                                        <option value="memorial_park">Memorial Park</option>
                                        <option value="catering">Catering</option>
                                        <option value="clergy">Clergy / Religious</option>
                                        <option value="admin_support">Admin / Documentation</option>
                                        <option value="multipurpose">Multi-service</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-600 small">WhatsApp Number</label>
                                    <input type="text" name="whatsapp" class="form-control form-control-sm"
                                           placeholder="e.g. 60123456789">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-600 small">Website <span class="text-muted fw-400">(optional)</span></label>
                                    <input type="url" name="website" class="form-control form-control-sm"
                                           placeholder="https://yourbusiness.com">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-600 small">Brief Description</label>
                                    <textarea name="description" class="form-control form-control-sm" rows="2"
                                              placeholder="Describe the services you offer…"></textarea>
                                </div>
                                <button type="submit" class="btn btn-navy btn-sm w-100">
                                    <i class="fas fa-paper-plane me-1"></i>Submit Application
                                </button>
                                <div class="text-muted text-center mt-2" style="font-size:.72rem;">
                                    Applications are reviewed by our team within 1–2 business days.
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /row -->
    </div><!-- /#upgrade -->

</div><!-- /portal-content -->
</div>

<?php
$extra_scripts = <<<'JS'
<script>
// Live avatar preview
function previewAvatar(input) {
    if (!input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const el = document.getElementById('avatarImg');
        if (el.tagName === 'IMG') {
            el.src = e.target.result;
        } else {
            const img = document.createElement('img');
            img.id = 'avatarImg';
            img.src = e.target.result;
            img.style = 'width:100px;height:100px;object-fit:cover;border-radius:50%;border:3px solid var(--pg-gold);';
            el.replaceWith(img);
        }
    };
    reader.readAsDataURL(input.files[0]);
    document.getElementById('avatarSaveBtn').style.display = '';
}

// Password match check
const newPwd     = document.getElementById('newPwd');
const confirmPwd = document.getElementById('confirmPwd');
const mismatch   = document.getElementById('pwdMismatch');
if (confirmPwd) {
    confirmPwd.addEventListener('input', () => {
        mismatch.style.display = confirmPwd.value && confirmPwd.value !== newPwd.value ? '' : 'none';
    });
}

// Copy referral code
function copyReferral() {
    const code = document.querySelector('code.fs-5');
    if (!code) return;
    navigator.clipboard.writeText(code.textContent.trim()).then(() => alert('Referral code copied!'));
}
function copyReferralLink() {
    const inp = document.getElementById('referralLinkInput');
    if (!inp) return;
    navigator.clipboard.writeText(inp.value).then(() => alert('Referral link copied!'));
}
</script>
JS;
include INC_PATH . '/footer.php';
?>
