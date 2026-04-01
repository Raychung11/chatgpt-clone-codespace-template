<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/points.php';
auth_require(ROLE_MEMBER);

$user       = auth_user();
$page_title = 'My Profile';
$active_nav = 'profile';
$errors     = [];
$success    = false;

// ─── Load existing profile ────────────────────────────────────────────────────
$profile = null;
$verif   = null;
try {
    $stmt = db()->prepare("SELECT * FROM member_profiles WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user['id']]);
    $profile = $stmt->fetch();

    $stmt = db()->prepare("SELECT * FROM senior_verifications WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 1");
    $stmt->execute([$user['id']]);
    $verif = $stmt->fetch();
} catch (PDOException $e) { error_log('[Profile load] ' . $e->getMessage()); }

// ─── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();

    $name   = trim($_POST['name']  ?? '');
    $phone  = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $dob    = $_POST['date_of_birth'] ?? '';
    $gender = in_array($_POST['gender'] ?? '', ['male','female','prefer_not_to_say']) ? $_POST['gender'] : null;
    $addr1  = trim($_POST['address_line1'] ?? '');
    $addr2  = trim($_POST['address_line2'] ?? '');
    $city   = trim($_POST['city']   ?? '');
    $state  = $_POST['state'] ?? '';
    $post   = trim($_POST['postcode'] ?? '');

    // Validate
    if (empty($name))               $errors['name']  = 'Full name is required.';
    if (strlen($phone) < 9)         $errors['phone'] = 'Please enter a valid phone number.';
    if ($dob && !strtotime($dob))   $errors['dob']   = 'Invalid date of birth.';
    if ($dob) {
        $age = (int)date('Y') - (int)date('Y', strtotime($dob));
        if ($age < 1 || $age > 120) $errors['dob'] = 'Please enter a valid date of birth.';
    }

    // Handle avatar upload
    $avatarPath = null;
    if (!empty($_FILES['avatar']['name'])) {
        $file = $_FILES['avatar'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['avatar'] = 'Upload failed. Please try again.';
        } elseif ($file['size'] > MAX_UPLOAD_BYTES) {
            $errors['avatar'] = 'Image must be under 5 MB.';
        } elseif (!in_array(mime_content_type($file['tmp_name']), ALLOWED_IMG_TYPES, true)) {
            $errors['avatar'] = 'Only JPG, PNG or WebP images are allowed.';
        } else {
            $dir = UPLOAD_DIR . 'avatars/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
                $avatarPath = '/assets/img/uploads/avatars/' . $filename;
            } else {
                $errors['avatar'] = 'Failed to save the uploaded image.';
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            // Update user name / avatar
            if ($avatarPath) {
                $pdo->prepare("UPDATE users SET name = ?, phone = ?, avatar = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$name, $phone, $avatarPath, $user['id']]);
                $_SESSION['user_avatar'] = $avatarPath;
            } else {
                $pdo->prepare("UPDATE users SET name = ?, phone = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$name, $phone, $user['id']]);
            }
            $_SESSION['user_name'] = $name;

            $completed = (!empty($dob) && !empty($gender) && !empty($addr1) && !empty($city) && !empty($state)) ? 1 : 0;

            if ($profile) {
                $pdo->prepare("
                    UPDATE member_profiles
                    SET date_of_birth=?, gender=?, address_line1=?, address_line2=?,
                        city=?, state=?, postcode=?, profile_completed=?, updated_at=NOW()
                    WHERE user_id=?
                ")->execute([$dob ?: null, $gender, $addr1, $addr2, $city, $state, $post, $completed, $user['id']]);
            } else {
                $memberNumber = generate_member_number();
                $referralCode = generate_referral_code((int)$user['id']);
                $pdo->prepare("
                    INSERT INTO member_profiles
                        (user_id, member_number, referral_code, date_of_birth, gender,
                         address_line1, address_line2, city, state, postcode, profile_completed)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)
                ")->execute([$user['id'], $memberNumber, $referralCode, $dob ?: null, $gender,
                             $addr1, $addr2, $city, $state, $post, $completed]);
            }

            $pdo->commit();

            // Audit
            db()->prepare("INSERT INTO audit_logs (user_id, action, target_type, target_id, ip_address) VALUES (?, 'member.profile_update', 'user', ?, ?)")
                ->execute([$user['id'], $user['id'], $_SERVER['REMOTE_ADDR'] ?? null]);

            auth_set_flash('success', 'Profile updated successfully!');
            redirect('/member/profile.php');
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            error_log('[Profile save] ' . $e->getMessage());
            $errors['general'] = 'Failed to save. Please try again.';
        }
    }
}

// Reload profile after potential changes
if (empty($errors)) {
    try {
        $stmt = db()->prepare("SELECT * FROM member_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch();
        $stmt = db()->prepare("SELECT u.name, u.email, u.phone, u.avatar FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user['id']]);
        $userRow = $stmt->fetch();
    } catch (PDOException) {}
} else {
    $userRow = ['name'=>$user['name'],'email'=>$user['email'],'phone'=>'','avatar'=>$user['avatar']];
}

$states_my = ['Johor','Kedah','Kelantan','Melaka','Negeri Sembilan','Pahang','Perak','Perlis','Pulau Pinang','Sabah','Sarawak','Selangor','Terengganu','Kuala Lumpur','Labuan','Putrajaya'];

include __DIR__ . '/../inc/member_layout.php';
?>

<div class="grid grid-2" style="align-items:start;gap:var(--space-xl);">

  <!-- ─── Left: Profile Card ────────────────────────────────────────────── -->
  <div>
    <!-- Avatar & Summary -->
    <div class="card" style="padding:var(--space-xl);text-align:center;margin-bottom:var(--space-lg);">
      <?php
      $avatarUrl = $userRow['avatar'] ?? $user['avatar'];
      $initials2 = strtoupper(mb_substr($userRow['name'] ?? $user['name'], 0, 2));
      ?>
      <div style="width:96px;height:96px;border-radius:50%;background:var(--orange-bg);border:3px solid var(--orange-border);display:flex;align-items:center;justify-content:center;margin:0 auto var(--space-md);overflow:hidden;font-size:32px;font-weight:800;color:var(--orange-primary);">
        <?php if ($avatarUrl): ?>
          <img src="<?= e($avatarUrl) ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;">
        <?php else: ?>
          <?= $initials2 ?>
        <?php endif; ?>
      </div>
      <h3 style="margin-bottom:4px;"><?= e($userRow['name'] ?? $user['name']) ?></h3>
      <div style="font-size:14px;color:var(--text-muted);margin-bottom:var(--space-md);"><?= e($userRow['email'] ?? $user['email']) ?></div>

      <!-- Status badge -->
      <?php
      $statusColors = ['active'=>'success','pending'=>'warning','suspended'=>'error','banned'=>'error'];
      $statusColor  = $statusColors[$user['status']] ?? 'muted';
      ?>
      <span class="badge badge--<?= $statusColor ?>" style="font-size:14px;">
        <?= match($user['status']) {
          'active'    => '✅ Active Member',
          'pending'   => '⏳ Pending Verification',
          'suspended' => '⚠ Suspended',
          default     => ucfirst($user['status'])
        } ?>
      </span>

      <?php if ($profile && $profile['member_number']): ?>
        <div style="margin-top:var(--space-md);font-size:13px;color:var(--text-muted);">
          Member No: <strong style="color:var(--text-dark);"><?= e($profile['member_number']) ?></strong>
        </div>
      <?php endif; ?>

      <?php if ($profile && $profile['referral_code']): ?>
        <div style="margin-top:var(--space-sm);font-size:13px;color:var(--text-muted);">
          Referral Code: <strong style="color:var(--orange-primary);"><?= e($profile['referral_code']) ?></strong>
        </div>
      <?php endif; ?>
    </div>

    <!-- Profile Completeness -->
    <?php
    $fields_check = [
        'Date of Birth'   => !empty($profile['date_of_birth']),
        'Gender'          => !empty($profile['gender']),
        'Address'         => !empty($profile['address_line1']),
        'City'            => !empty($profile['city']),
        'State'           => !empty($profile['state']),
    ];
    $done_count = count(array_filter($fields_check));
    $total_count = count($fields_check);
    $pct = (int)($done_count / $total_count * 100);
    ?>
    <div class="card" style="padding:var(--space-lg);">
      <div class="flex-between" style="margin-bottom:var(--space-sm);">
        <span style="font-weight:700;">Profile Completeness</span>
        <span class="badge badge--<?= $pct === 100 ? 'success' : 'orange' ?>"><?= $pct ?>%</span>
      </div>
      <div style="background:var(--border-light);border-radius:var(--radius-pill);height:10px;margin-bottom:var(--space-md);">
        <div style="background:var(--orange-primary);height:10px;border-radius:var(--radius-pill);width:<?= $pct ?>%;transition:width .5s;"></div>
      </div>
      <ul style="display:flex;flex-direction:column;gap:8px;">
        <?php foreach ($fields_check as $label => $done): ?>
          <li style="display:flex;align-items:center;gap:8px;font-size:15px;">
            <span style="color:<?= $done ? 'var(--success)' : 'var(--border-light)' ?>;font-size:18px;"><?= $done ? '✓' : '○' ?></span>
            <span style="color:<?= $done ? 'var(--text-dark)' : 'var(--text-muted)' ?>;"><?= $label ?></span>
          </li>
        <?php endforeach; ?>
      </ul>

      <?php if ($user['status'] === 'pending' && $pct === 100 && !$verif): ?>
        <div style="margin-top:var(--space-lg);padding:var(--space-md);background:var(--orange-bg);border-radius:var(--radius-md);text-align:center;">
          <div style="font-weight:700;margin-bottom:var(--space-sm);">Profile complete!</div>
          <p style="font-size:14px;color:var(--text-muted);margin-bottom:var(--space-md);">Submit your senior verification to activate your membership.</p>
          <a href="/member/verification.php" class="btn btn--primary btn--full">Submit Verification →</a>
        </div>
      <?php elseif ($verif): ?>
        <div style="margin-top:var(--space-lg);padding:var(--space-md);background:<?= $verif['status']==='approved' ? 'var(--success-bg)' : ($verif['status']==='rejected' ? 'var(--error-bg)' : 'var(--warning-bg)') ?>;border-radius:var(--radius-md);">
          <div style="font-weight:700;font-size:15px;">Verification:
            <?= match($verif['status']) { 'approved'=>'✅ Approved','rejected'=>'❌ Rejected',default=>'⏳ Under Review' } ?>
          </div>
          <?php if ($verif['status'] === 'rejected' && $verif['reject_reason']): ?>
            <p style="font-size:13px;margin-top:4px;color:var(--error);"><?= e($verif['reject_reason']) ?></p>
            <a href="/member/verification.php" class="btn btn--primary btn--sm" style="margin-top:var(--space-sm);">Resubmit</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ─── Right: Edit Form ──────────────────────────────────────────────── -->
  <div>
    <?php if (!empty($errors['general'])): ?>
      <div class="alert alert--error"><span class="alert__icon">✕</span><span><?= e($errors['general']) ?></span></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>

      <!-- Section: Personal Info -->
      <div class="card" style="padding:var(--space-xl);margin-bottom:var(--space-lg);">
        <h4 style="margin-bottom:var(--space-lg);padding-bottom:var(--space-md);border-bottom:1px solid var(--border-light);">
          👤 Personal Information
        </h4>

        <div class="form-group">
          <label class="form-label" for="name">Full Name <span class="required">*</span></label>
          <input type="text" id="name" name="name" class="form-control <?= !empty($errors['name'])?'form-control--error':'' ?>"
                 value="<?= e($_POST['name'] ?? $userRow['name'] ?? $user['name']) ?>"
                 placeholder="As per MyKad" required autocomplete="name">
          <?php if (!empty($errors['name'])): ?><div class="form-error"><?= e($errors['name']) ?></div><?php endif; ?>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="phone">Phone Number <span class="required">*</span></label>
            <input type="tel" id="phone" name="phone" class="form-control <?= !empty($errors['phone'])?'form-control--error':'' ?>"
                   value="<?= e($_POST['phone'] ?? $userRow['phone'] ?? '') ?>"
                   placeholder="60123456789" autocomplete="tel">
            <?php if (!empty($errors['phone'])): ?><div class="form-error"><?= e($errors['phone']) ?></div><?php endif; ?>
          </div>

          <div class="form-group">
            <label class="form-label" for="date_of_birth">Date of Birth</label>
            <input type="date" id="date_of_birth" name="date_of_birth"
                   class="form-control <?= !empty($errors['dob'])?'form-control--error':'' ?>"
                   value="<?= e($_POST['date_of_birth'] ?? $profile['date_of_birth'] ?? '') ?>"
                   max="<?= date('Y-m-d', strtotime('-50 years')) ?>">
            <?php if (!empty($errors['dob'])): ?><div class="form-error"><?= e($errors['dob']) ?></div><?php endif; ?>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Gender</label>
          <div style="display:flex;gap:var(--space-lg);">
            <?php foreach (['male'=>'Male','female'=>'Female','prefer_not_to_say'=>'Prefer not to say'] as $val=>$label): ?>
              <label class="form-check" style="flex:1;">
                <input type="radio" name="gender" value="<?= $val ?>"
                  <?= (($_POST['gender'] ?? $profile['gender'] ?? '') === $val) ? 'checked' : '' ?>>
                <span><?= $label ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="avatar">Profile Photo</label>
          <input type="file" id="avatar" name="avatar" class="form-control" accept="image/jpeg,image/png,image/webp">
          <div class="form-hint">JPG, PNG or WebP. Max 5 MB.</div>
          <?php if (!empty($errors['avatar'])): ?><div class="form-error"><?= e($errors['avatar']) ?></div><?php endif; ?>
        </div>
      </div>

      <!-- Section: Address -->
      <div class="card" style="padding:var(--space-xl);margin-bottom:var(--space-lg);">
        <h4 style="margin-bottom:var(--space-lg);padding-bottom:var(--space-md);border-bottom:1px solid var(--border-light);">
          🏠 Address
        </h4>

        <div class="form-group">
          <label class="form-label" for="address_line1">Address Line 1</label>
          <input type="text" id="address_line1" name="address_line1" class="form-control"
                 value="<?= e($_POST['address_line1'] ?? $profile['address_line1'] ?? '') ?>"
                 placeholder="Street, unit or lot number">
        </div>

        <div class="form-group">
          <label class="form-label" for="address_line2">Address Line 2 <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
          <input type="text" id="address_line2" name="address_line2" class="form-control"
                 value="<?= e($_POST['address_line2'] ?? $profile['address_line2'] ?? '') ?>"
                 placeholder="Taman, apartment or block name">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="city">City / Town</label>
            <input type="text" id="city" name="city" class="form-control"
                   value="<?= e($_POST['city'] ?? $profile['city'] ?? '') ?>"
                   placeholder="e.g. Petaling Jaya">
          </div>
          <div class="form-group">
            <label class="form-label" for="postcode">Postcode</label>
            <input type="text" id="postcode" name="postcode" class="form-control"
                   value="<?= e($_POST['postcode'] ?? $profile['postcode'] ?? '') ?>"
                   placeholder="e.g. 47810" maxlength="10">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="state">State</label>
          <select id="state" name="state" class="form-control">
            <option value="">— Select State —</option>
            <?php $sel = $_POST['state'] ?? $profile['state'] ?? ''; foreach ($states_my as $s): ?>
              <option value="<?= e($s) ?>" <?= $sel === $s ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <button type="submit" class="btn btn--primary btn--full btn--lg">
        💾 Save Profile
      </button>
    </form>

    <!-- Change password link -->
    <div style="margin-top:var(--space-lg);text-align:center;">
      <a href="/member/change-password.php" style="font-size:15px;color:var(--text-muted);">🔒 Change Password</a>
    </div>
  </div>

</div>

<?php include __DIR__ . '/../inc/member_layout_end.php'; ?>
