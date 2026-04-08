<?php
require_once __DIR__ . '/inc/bootstrap.php';

if (auth_check()) redirect('/');

$sent  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();
    $email = clean_email($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $user = Database::fetchOne('SELECT id FROM users WHERE email = ? AND status != ?', [$email, 'deleted']);
        if ($user) {
            // Delete existing tokens
            Database::query('DELETE FROM password_resets WHERE user_id = ?', [$user['id']]);

            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            Database::query(
                'INSERT INTO password_resets (user_id, token, expires_at, ip_address) VALUES (?, ?, ?, ?)',
                [$user['id'], $token, $expires, ip_address()]
            );
            // In production, send email with reset link:
            // $resetLink = pg_url("reset_password.php?token=$token");
            // send_password_reset_email($email, $resetLink);
        }
        // Always show success to prevent email enumeration
        $sent = true;
        activity_log(null, 'password_reset_requested', 'users', null, "Reset requested for: $email");
    }
}

$page_title = 'Forgot Password';
$body_class = 'auth-page';
include INC_PATH . '/header.php';
?>

<div class="min-vh-100 d-flex align-items-center justify-content-center py-5" style="background: linear-gradient(135deg, var(--pg-navy) 0%, #243060 100%);">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">

                <div class="text-center mb-4">
                    <a href="<?= pg_url() ?>">
                        <span class="fs-2 fw-bold text-white">Plot</span><span class="fs-2 fw-bold text-warning">Gold</span>
                    </a>
                </div>

                <div class="pg-card p-4">
                    <?php if ($sent): ?>
                        <div class="text-center py-3">
                            <div class="trust-icon mx-auto mb-3"><i class="fas fa-envelope-open-text"></i></div>
                            <h4 class="fw-600">Check your email</h4>
                            <p class="text-muted small">If an account with that email exists, we've sent a password reset link. Check your inbox (and spam folder).</p>
                            <a href="<?= pg_url('login.php') ?>" class="btn btn-outline-gold mt-2">Back to Login</a>
                        </div>
                    <?php else: ?>
                        <h4 class="fw-600 mb-1">Reset password</h4>
                        <p class="text-muted small mb-4">Enter your email and we'll send you a reset link.</p>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= h($error) ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" id="email" name="email" class="form-control"
                                       value="<?= h($_POST['email'] ?? '') ?>"
                                       placeholder="you@example.com" required autofocus>
                            </div>
                            <button type="submit" class="btn btn-gold w-100">Send Reset Link</button>
                        </form>

                        <p class="text-center small text-muted mt-4 mb-0">
                            <a href="<?= pg_url('login.php') ?>">Back to Login</a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include INC_PATH . '/footer.php'; ?>
