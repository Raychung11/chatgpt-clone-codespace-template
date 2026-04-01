<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
auth_require(ROLE_MERCHANT);

$user       = auth_user();
$page_title = 'Change Password';
$active_nav = 'profile';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    rate_limit('change_pw_m_' . $user['id'], 5, 300);

    $current  = $_POST['current_password']  ?? '';
    $new      = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';

    if (!$current || !$new || !$confirm) {
        $error = 'All fields are required.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        try {
            $stmt = db()->prepare("SELECT password_hash FROM users WHERE id=? LIMIT 1");
            $stmt->execute([$user['id']]);
            $hash = $stmt->fetchColumn();

            if (!password_verify($current, $hash)) {
                $error = 'Current password is incorrect.';
            } else {
                db()->prepare("UPDATE users SET password_hash=?,updated_at=NOW() WHERE id=?")
                    ->execute([password_hash($new, PASSWORD_BCRYPT, ['cost'=>12]), $user['id']]);

                db()->prepare("INSERT INTO audit_logs(user_id,action,target_type,target_id,ip_address) VALUES(?,'merchant.change_password','user',?,?)")
                    ->execute([$user['id'], $user['id'], $_SERVER['REMOTE_ADDR'] ?? null]);

                $success = 'Password changed successfully.';
            }
        } catch (PDOException $e) {
            error_log('[Merchant change password] '.$e->getMessage());
            $error = 'Failed to update password. Please try again.';
        }
    }
}

include __DIR__ . '/../inc/merchant_layout.php';
?>

<div style="max-width:480px;">
  <div style="margin-bottom:var(--space-xl);">
    <a href="/merchant/profile.php" style="color:var(--text-muted);font-size:14px;text-decoration:none;">← Back to Profile</a>
  </div>

  <h2 style="margin-bottom:var(--space-xl);">Change Password</h2>

  <?php if ($success): ?>
    <div class="alert alert--success" style="margin-bottom:var(--space-lg);"><span class="alert__icon">✓</span><span><?= e($success) ?></span></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert--error" style="margin-bottom:var(--space-lg);"><span class="alert__icon">✕</span><span><?= e($error) ?></span></div>
  <?php endif; ?>

  <div class="card" style="padding:var(--space-xl);">
    <form method="POST">
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label" for="current_password">Current Password</label>
        <input type="password" id="current_password" name="current_password" class="form-control" required autocomplete="current-password">
      </div>

      <div class="form-group">
        <label class="form-label" for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password" class="form-control" required autocomplete="new-password" minlength="8">
        <div class="form-hint">At least 8 characters.</div>
      </div>

      <div class="form-group">
        <label class="form-label" for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required autocomplete="new-password">
      </div>

      <button type="submit" class="btn btn--primary btn--full btn--lg">Update Password</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../inc/merchant_layout_end.php'; ?>
