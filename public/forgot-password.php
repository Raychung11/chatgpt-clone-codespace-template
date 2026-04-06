<?php
declare(strict_types=1);

/**
 * public/forgot-password.php
 * Send password reset email.
 *
 * NOTE: In production, replace the placeholder mail() call with
 *       your preferred mailer (PHPMailer / SMTP / SendGrid / etc.).
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/mailer.php';

boot_session();

if (auth_user()) redirect(BASE_URL . '/client/dashboard.php');

$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!validate_email($email)) {
        flash_error('Please enter a valid email address.');
    } else {
        $token = create_password_reset_token($email);

        // Always show success (prevent email enumeration)
        if ($token) {
            $reset_url = BASE_URL . '/public/reset-password.php?token=' . urlencode($token);

            // Fetch user name for personalised email
            $uStmt = db()->prepare('SELECT name FROM `users` WHERE email=? LIMIT 1');
            $uStmt->execute([$email]);
            $uRow = $uStmt->fetch();

            mail_password_reset($email, $uRow['name'] ?? 'User', $reset_url);
        }

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — <?= e(setting('site_name','VideoSaaS')) ?></title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-box">

        <div class="auth-logo">
            <div class="logo-text">Video<span>SaaS</span></div>
        </div>

        <div class="auth-card">
            <h1 class="auth-title">Forgot Password</h1>

            <?= render_flash() ?>

            <?php if ($success): ?>
                <div class="alert alert--success">
                    If that email exists in our system, a reset link has been sent. Please check your inbox.
                </div>
                <p class="text-center mt-3"><a href="login.php">← Back to Sign In</a></p>
            <?php else: ?>
                <p class="auth-sub">Enter your email and we'll send a reset link.</p>

                <form method="POST" action="" novalidate>
                    <?= csrf_field() ?>

                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control"
                               placeholder="john@example.com" required autofocus autocomplete="email">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
                </form>

                <hr class="divider">
                <p class="text-center text-sm text-muted">
                    Remembered it? <a href="login.php">Sign in</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
