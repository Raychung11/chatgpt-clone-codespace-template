<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';

// Already logged in as admin
if (auth_check() && in_array($_SESSION['user_role'] ?? '', [ROLE_ADMIN, ROLE_SUPERADMIN], true)) {
    redirect('/admin/dashboard.php');
}

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();

    if (!rate_limit('admin_login', 5, 300)) {
        $errors['general'] = 'Too many failed attempts. Please wait 5 minutes.';
    } else {
        $email    = trim(strtolower($_POST['email']    ?? ''));
        $password = $_POST['password'] ?? '';

        if (empty($email))    $errors['email']    = 'Email is required.';
        if (empty($password)) $errors['password'] = 'Password is required.';

        if (empty($errors)) {
            try {
                $stmt = db()->prepare("
                    SELECT id, name, email, password_hash, role, status
                    FROM users
                    WHERE email = ? AND role IN ('admin','superadmin') AND deleted_at IS NULL
                    LIMIT 1
                ");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($password, $user['password_hash'])) {
                    $errors['general'] = 'Invalid credentials.';
                } elseif ($user['status'] !== 'active') {
                    $errors['general'] = 'This admin account is inactive.';
                } else {
                    auth_login([
                        'id'     => $user['id'],
                        'name'   => $user['name'],
                        'email'  => $user['email'],
                        'role'   => $user['role'],
                        'avatar' => '',
                        'status' => $user['status'],
                    ]);

                    db()->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")
                        ->execute([$user['id']]);

                    db()->prepare("
                        INSERT INTO audit_logs (user_id, action, ip_address, user_agent)
                        VALUES (?, 'admin.login', ?, ?)
                    ")->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 499)]);

                    redirect('/admin/dashboard.php');
                }
            } catch (PDOException $e) {
                error_log('[Admin Login] ' . $e->getMessage());
                $errors['general'] = 'Server error. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en-MY">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — SilverDeals MY</title>
  <meta name="robots" content="noindex,nofollow">
  <link rel="stylesheet" href="/assets/css/theme.css">
</head>
<body>
<div class="auth-page" style="background:var(--text-dark);">
  <div class="auth-card">
    <div class="auth-card__logo">
      <div class="auth-card__logo-text">🟠 SilverDeals MY</div>
      <div style="font-size:13px;color:var(--text-muted);margin-top:4px;font-weight:600;text-transform:uppercase;letter-spacing:.1em;">Admin Portal</div>
    </div>

    <h2 class="auth-card__title" style="font-size:22px;">Admin Login</h2>
    <p class="auth-card__subtitle" style="font-size:15px;">Restricted access — authorised personnel only.</p>

    <?php if (!empty($errors['general'])): ?>
      <div class="alert alert--error"><span class="alert__icon">✕</span><span><?= e($errors['general']) ?></span></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label" for="email">Admin Email</label>
        <input
          type="email" id="email" name="email"
          class="form-control <?= !empty($errors['email']) ? 'form-control--error' : '' ?>"
          value="<?= e($email) ?>"
          placeholder="admin@silverdeals.my"
          autocomplete="username" autofocus required>
        <?php if (!empty($errors['email'])): ?><div class="form-error"><?= e($errors['email']) ?></div><?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div style="position:relative;">
          <input
            type="password" id="password" name="password"
            class="form-control <?= !empty($errors['password']) ? 'form-control--error' : '' ?>"
            placeholder="Admin password"
            autocomplete="current-password" required>
          <button type="button" data-toggle-password="password"
            style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:18px;">👁</button>
        </div>
        <?php if (!empty($errors['password'])): ?><div class="form-error"><?= e($errors['password']) ?></div><?php endif; ?>
      </div>

      <button type="submit" class="btn btn--primary btn--full btn--lg">Login to Admin</button>
    </form>

    <p class="text-center" style="margin-top:var(--space-xl);font-size:14px;color:var(--text-muted);">
      <a href="/index.php">← Back to public site</a>
    </p>
  </div>
</div>
<script src="/assets/js/app.js"></script>
</body>
</html>
