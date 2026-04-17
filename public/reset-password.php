<?php
declare(strict_types=1);

/**
 * public/reset-password.php
 * Apply a new password via reset token.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';

boot_session();

if (auth_user()) redirect(BASE_URL . '/client/dashboard.php');

$token = trim($_GET['token'] ?? '');
$user  = $token ? validate_reset_token($token) : null;

$error   = '';
$success = false;

if (!$token || !$user) {
    $error = 'This password reset link is invalid or has expired. Please request a new one.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    csrf_verify();

    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if (!validate_password($password)) {
        $error = 'Password must be at least 8 characters with letters and numbers.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        apply_password_reset((int)$user['id'], $password);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — <?= e(setting('site_name','Motions')) ?></title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-box">

        <div class="auth-logo">
            <div class="logo-text">Video<span>SaaS</span></div>
        </div>

        <div class="auth-card">
            <h1 class="auth-title">Set New Password</h1>

            <?php if ($error && !$user): ?>
                <div class="alert alert--error"><?= e($error) ?></div>
                <p class="text-center mt-3"><a href="forgot-password.php">Request a new link</a></p>

            <?php elseif ($success): ?>
                <div class="alert alert--success">
                    Your password has been updated. You can now sign in.
                </div>
                <a href="login.php" class="btn btn-primary btn-block mt-3">Sign In</a>

            <?php else: ?>
                <?php if ($error): ?>
                    <div class="alert alert--error"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <div class="form-group">
                        <label class="form-label" for="password">New Password</label>
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="Min 8 chars with letters & numbers" required autofocus>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm">Confirm New Password</label>
                        <input type="password" id="confirm" name="confirm" class="form-control"
                               placeholder="Repeat new password" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg">Update Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
