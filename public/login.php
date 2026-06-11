<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';

if (auth_check()) {
    $role = $_SESSION['user_role'] ?? '';
    redirect(match($role) {
        ROLE_ADMIN, ROLE_SUPERADMIN => '/admin/dashboard.php',
        ROLE_MERCHANT               => '/merchant/dashboard.php',
        ROLE_COMMUNITY              => '/community/dashboard.php',
        default                     => '/member/dashboard.php',
    });
}

$page_title = 'Login — SilverDeals MY';
$meta_desc  = 'Login to your SilverDeals MY account to access exclusive senior deals, rewards, and your digital membership card.';

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();

    if (!rate_limit('login', 5, 300)) {
        $errors['general'] = 'Too many login attempts. Please wait a few minutes and try again.';
    } else {
        $email    = trim(strtolower($_POST['email']    ?? ''));
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if (empty($email))    $errors['email']    = 'Please enter your email address.';
        if (empty($password)) $errors['password'] = 'Please enter your password.';

        if (empty($errors)) {
            try {
                $stmt = db()->prepare("
                    SELECT id, name, email, phone, password_hash, role, status, avatar
                    FROM users
                    WHERE email = ? AND deleted_at IS NULL
                    LIMIT 1
                ");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($password, $user['password_hash'])) {
                    $errors['general'] = 'Incorrect email or password. Please try again.';
                } elseif ($user['status'] === 'banned') {
                    $errors['general'] = 'This account has been suspended. Please contact support.';
                } elseif ($user['status'] === 'suspended') {
                    $errors['general'] = 'Your account is temporarily suspended. Please contact support.';
                } else {
                    // Log in
                    auth_login([
                        'id'     => $user['id'],
                        'name'   => $user['name'],
                        'email'  => $user['email'],
                        'role'   => $user['role'],
                        'avatar' => $user['avatar'] ?? '',
                        'status' => $user['status'],
                    ]);

                    // Update last login
                    db()->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")
                        ->execute([$user['id']]);

                    // Audit log
                    db()->prepare("
                        INSERT INTO audit_logs (user_id, action, ip_address, user_agent)
                        VALUES (?, 'member.login', ?, ?)
                    ")->execute([
                        $user['id'],
                        $_SERVER['REMOTE_ADDR'] ?? null,
                        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 499),
                    ]);

                    $redirect = $_GET['redirect'] ?? '';
                    if ($redirect && str_starts_with($redirect, '/')) {
                        redirect($redirect);
                    }

                    $role = $user['role'];
                    redirect(match($role) {
                        ROLE_ADMIN, ROLE_SUPERADMIN => '/admin/dashboard.php',
                        ROLE_MERCHANT               => '/merchant/dashboard.php',
                        ROLE_COMMUNITY              => '/community/dashboard.php',
                        default                     => '/member/dashboard.php',
                    });
                }
            } catch (PDOException $e) {
                error_log('[SilverDeals Login] ' . $e->getMessage());
                $errors['general'] = 'A server error occurred. Please try again.';
            }
        }
    }
}

include __DIR__ . '/../inc/public_header.php';
?>

<div class="auth-page">
  <div class="auth-card">
    <div class="auth-card__logo">
      <div class="auth-card__logo-text">🟠 SilverDeals MY</div>
      <div style="font-size:13px;color:var(--text-muted);margin-top:4px;">Senior Membership &amp; Rewards</div>
    </div>

    <h2 class="auth-card__title">Welcome Back</h2>
    <p class="auth-card__subtitle">Login to access your deals, rewards, and membership card.</p>

    <?php if (!empty($errors['general'])): ?>
      <div class="alert alert--error"><span class="alert__icon">✕</span><span><?= e($errors['general']) ?></span></div>
    <?php endif; ?>

    <form method="POST" action="/login.php<?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" novalidate>
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label" for="email">Email Address</label>
        <input
          type="email" id="email" name="email"
          class="form-control <?= !empty($errors['email']) ? 'form-control--error' : '' ?>"
          value="<?= e($email) ?>"
          placeholder="yourname@email.com"
          autocomplete="email" autofocus required>
        <?php if (!empty($errors['email'])): ?><div class="form-error"><?= e($errors['email']) ?></div><?php endif; ?>
      </div>

      <div class="form-group">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-sm);">
          <label class="form-label" for="password" style="margin-bottom:0;">Password</label>
          <a href="/forgot-password.php" style="font-size:14px;color:var(--orange-primary);">Forgot password?</a>
        </div>
        <div style="position:relative;">
          <input
            type="password" id="password" name="password"
            class="form-control <?= !empty($errors['password']) ? 'form-control--error' : '' ?>"
            placeholder="Your password"
            autocomplete="current-password" required>
          <button type="button" data-toggle-password="password"
            style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:18px;">👁</button>
        </div>
        <?php if (!empty($errors['password'])): ?><div class="form-error"><?= e($errors['password']) ?></div><?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-check">
          <input type="checkbox" name="remember">
          <span>Keep me logged in on this device</span>
        </label>
      </div>

      <button type="submit" class="btn btn--primary btn--full btn--lg">
        Login to My Account
      </button>
    </form>

    <div class="auth-divider">or</div>

    <p class="text-center" style="font-size:16px;">
      New to SilverDeals MY?
      <a href="/register.php" style="font-weight:600;">Create a free account</a>
    </p>

    <div style="margin-top:var(--space-xl);background:var(--orange-bg);border-radius:var(--radius-md);padding:var(--space-md);text-align:center;">
      <p style="font-size:15px;color:var(--text-muted);margin:0;">
        Are you a merchant or community partner?<br>
        <a href="/merchant/login.php" style="font-weight:600;">Merchant Login</a> &nbsp;|&nbsp;
        <a href="/community/login.php" style="font-weight:600;">Community Login</a>
      </p>
    </div>

    <p class="text-center text-muted" style="font-size:14px;margin-top:var(--space-lg);">
      Need help? <a href="<?= whatsapp_url('Hello, I need help logging in to SilverDeals MY.') ?>" target="_blank">Chat with us on WhatsApp</a>
    </p>
  </div>
</div>

<?php include __DIR__ . '/../inc/public_footer.php'; ?>
