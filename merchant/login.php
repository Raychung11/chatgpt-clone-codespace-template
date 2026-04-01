<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';

if (auth_check() && $_SESSION['user_role'] === ROLE_MERCHANT) redirect('/merchant/dashboard.php');
if (auth_check() && in_array($_SESSION['user_role'], [ROLE_ADMIN, ROLE_SUPERADMIN])) redirect('/admin/dashboard.php');

$page_title = 'Merchant Login — SilverDeals MY';
$errors = []; $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    if (!rate_limit('merchant_login', 5, 300)) {
        $errors['general'] = 'Too many attempts. Please wait a few minutes.';
    } else {
        $email    = trim(strtolower($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        if (!$email)    $errors['email']    = 'Email is required.';
        if (!$password) $errors['password'] = 'Password is required.';

        if (empty($errors)) {
            try {
                $stmt = db()->prepare("SELECT id,name,email,password_hash,role,status FROM users WHERE email=? AND role='merchant' AND deleted_at IS NULL LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if (!$user || !password_verify($password, $user['password_hash'])) {
                    $errors['general'] = 'Invalid email or password.';
                } elseif ($user['status'] === 'banned') {
                    $errors['general'] = 'This account has been suspended. Contact support.';
                } else {
                    auth_login(['id'=>$user['id'],'name'=>$user['name'],'email'=>$user['email'],'role'=>$user['role'],'avatar'=>'','status'=>$user['status']]);
                    db()->prepare("UPDATE users SET last_login_at=NOW() WHERE id=?")->execute([$user['id']]);
                    db()->prepare("INSERT INTO audit_logs(user_id,action,ip_address) VALUES(?,'merchant.login',?)")->execute([$user['id'],$_SERVER['REMOTE_ADDR']??null]);
                    redirect('/merchant/dashboard.php');
                }
            } catch (PDOException $e) { error_log('[Merchant Login] '.$e->getMessage()); $errors['general']='Server error.'; }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en-MY">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?></title>
  <meta name="robots" content="noindex,nofollow">
  <link rel="stylesheet" href="/assets/css/theme.css">
</head>
<body>
<div class="auth-page" style="background:var(--orange-bg);">
  <div class="auth-card">
    <div class="auth-card__logo">
      <div class="auth-card__logo-text">🟠 SilverDeals MY</div>
      <div style="font-size:13px;color:var(--text-muted);margin-top:4px;font-weight:600;text-transform:uppercase;letter-spacing:.1em;">Merchant Portal</div>
    </div>
    <h2 class="auth-card__title">Merchant Login</h2>
    <p class="auth-card__subtitle">Access your business dashboard and manage your deals.</p>

    <?php if (!empty($errors['general'])): ?>
      <div class="alert alert--error"><span class="alert__icon">✕</span><span><?= e($errors['general']) ?></span></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label" for="email">Business Email</label>
        <input type="email" id="email" name="email" class="form-control <?= !empty($errors['email'])?'form-control--error':'' ?>"
               value="<?= e($email) ?>" placeholder="merchant@yourbusiness.com" autocomplete="email" autofocus required>
        <?php if (!empty($errors['email'])): ?><div class="form-error"><?= e($errors['email']) ?></div><?php endif; ?>
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div style="position:relative;">
          <input type="password" id="password" name="password" class="form-control" placeholder="Your password" autocomplete="current-password" required>
          <button type="button" data-toggle-password="password" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:18px;">👁</button>
        </div>
      </div>
      <button type="submit" class="btn btn--primary btn--full btn--lg">Login to Merchant Portal</button>
    </form>

    <div class="auth-divider">or</div>
    <p class="text-center" style="font-size:16px;">Not a merchant yet? <a href="/public/join-merchant.php" style="font-weight:600;">Partner with us →</a></p>
    <p class="text-center" style="margin-top:var(--space-md);font-size:14px;color:var(--text-muted);">
      <a href="/public/login.php">Member login</a> · <a href="/admin/login.php">Admin login</a>
    </p>
  </div>
</div>
<script src="/assets/js/app.js"></script>
</body>
</html>
