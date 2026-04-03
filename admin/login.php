<?php
declare(strict_types=1);

/**
 * admin/login.php
 * Admin login page.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';

boot_session();

if (auth_admin()) redirect(BASE_URL . '/admin/index.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = strtolower(trim($_POST['email']    ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = db()->prepare(
            'SELECT `id`,`name`,`email`,`password_hash`,`role`,`is_active`
             FROM `admins` WHERE `email` = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            $error = 'Invalid credentials.';
            log_activity('admin', null, 'admin_login_failed', 'Failed admin login: ' . $email);
        } elseif (!$admin['is_active']) {
            $error = 'This admin account is inactive.';
        } else {
            auth_login_admin($admin);
            redirect(BASE_URL . '/admin/index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — <?= e(setting('site_name','VideoSaaS')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-box">

        <div class="auth-logo">
            <div class="logo-text">Video<span>SaaS</span>
                <span style="font-size:.8rem;color:var(--color-primary);font-weight:400"> Admin</span>
            </div>
        </div>

        <div class="auth-card">
            <h1 class="auth-title">Admin Access</h1>
            <p class="auth-sub">Restricted area — authorised personnel only</p>

            <?= render_flash() ?>

            <?php if ($error): ?>
                <div class="alert alert--error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="email">Admin Email</label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="admin@example.com" required autofocus autocomplete="username">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="Your password" required autocomplete="current-password">
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">Sign In to Admin</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
