<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
auth_require(ROLE_MEMBER);

$user       = auth_user();
$page_title = 'Change Password';
$active_nav = 'profile';
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();

    if (!rate_limit('change_pw', 5, 300)) {
        $errors['general'] = 'Too many attempts. Please wait a few minutes.';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        // Load current hash
        $stmt = db()->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user['id']]);
        $row  = $stmt->fetch();

        if (!password_verify($current, $row['password_hash'] ?? '')) {
            $errors['current'] = 'Current password is incorrect.';
        }
        if (strlen($new) < 8) {
            $errors['new'] = 'New password must be at least 8 characters.';
        }
        if ($new !== $confirm) {
            $errors['confirm'] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost'=>12]);
            db()->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$hash, $user['id']]);

            db()->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'member.change_password', ?)")
                ->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? null]);

            auth_set_flash('success', 'Password changed successfully.');
            redirect('/member/profile.php');
        }
    }
}

include __DIR__ . '/../inc/member_layout.php';
?>

<div style="max-width:480px;margin:0 auto;">
  <div class="card" style="padding:var(--space-xl);">
    <h3 style="margin-bottom:var(--space-xl);">🔒 Change Password</h3>

    <?php if (!empty($errors['general'])): ?>
      <div class="alert alert--error"><span class="alert__icon">✕</span><span><?= e($errors['general']) ?></span></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label" for="current_password">Current Password</label>
        <div style="position:relative;">
          <input type="password" id="current_password" name="current_password"
                 class="form-control <?= !empty($errors['current'])?'form-control--error':'' ?>"
                 placeholder="Your current password" autocomplete="current-password" required>
          <button type="button" data-toggle-password="current_password"
            style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:18px;">👁</button>
        </div>
        <?php if (!empty($errors['current'])): ?><div class="form-error"><?= e($errors['current']) ?></div><?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="new_password">New Password</label>
        <div style="position:relative;">
          <input type="password" id="new_password" name="new_password"
                 class="form-control <?= !empty($errors['new'])?'form-control--error':'' ?>"
                 placeholder="Minimum 8 characters" autocomplete="new-password" required>
          <button type="button" data-toggle-password="new_password"
            style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:18px;">👁</button>
        </div>
        <?php if (!empty($errors['new'])): ?><div class="form-error"><?= e($errors['new']) ?></div><?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password"
               class="form-control <?= !empty($errors['confirm'])?'form-control--error':'' ?>"
               placeholder="Re-enter new password" autocomplete="new-password" required>
        <?php if (!empty($errors['confirm'])): ?><div class="form-error"><?= e($errors['confirm']) ?></div><?php endif; ?>
      </div>

      <button type="submit" class="btn btn--primary btn--full btn--lg">Update Password</button>
    </form>

    <div style="margin-top:var(--space-lg);text-align:center;">
      <a href="/member/profile.php" style="font-size:15px;color:var(--text-muted);">← Back to Profile</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../inc/member_layout_end.php'; ?>
