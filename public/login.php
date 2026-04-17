<?php
declare(strict_types=1);

/**
 * public/login.php
 * Client login page.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';

boot_session();

if (auth_user()) {
    redirect(BASE_URL . '/client/dashboard.php');
}

$error = '';
$old_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = strtolower(trim($_POST['email']    ?? ''));
    $password = $_POST['password'] ?? '';
    $old_email = $email;

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = db()->prepare(
            'SELECT `id`,`name`,`email`,`password_hash`,`is_active`
             FROM `users` WHERE `email` = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Generic message to avoid user enumeration
            $error = 'Invalid email or password.';
            log_activity('user', null, 'login_failed', 'Failed login attempt for: ' . $email);
        } elseif (!$user['is_active']) {
            $error = 'Your account has been suspended. Please contact support.';
        } else {
            auth_login_user($user);
            $redirect = $_GET['redirect'] ?? (BASE_URL . '/client/dashboard.php');
            redirect($redirect);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — <?= e(setting('site_name','Motions')) ?></title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-box">

        <div class="auth-logo">
            <div class="logo-text">Video<span>SaaS</span></div>
        </div>

        <div class="auth-card">
            <h1 class="auth-title">Welcome Back</h1>
            <p class="auth-sub">Sign in to your account</p>

            <?= render_flash() ?>

            <?php if ($error): ?>
                <div class="alert alert--error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="<?= e($old_email) ?>"
                           placeholder="john@example.com" required autofocus autocomplete="email">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">
                        Password
                        <a href="forgot-password.php" style="float:right;font-size:.8rem;text-transform:none;letter-spacing:0">
                            Forgot password?
                        </a>
                    </label>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="Your password" required autocomplete="current-password">
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">Sign In</button>
            </form>

            <hr class="divider">
            <p class="text-center text-sm text-muted">
                Don't have an account? <a href="register.php">Create one free</a>
            </p>
        </div>
    </div>
</div>
</body>
</html>
