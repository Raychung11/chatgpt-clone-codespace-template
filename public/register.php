<?php
declare(strict_types=1);

/**
 * public/register.php
 * New client registration page.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';

boot_session();

// Already logged in → go to dashboard
if (auth_user()) {
    redirect(BASE_URL . '/client/dashboard.php');
}

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name     = trim($_POST['name']     ?? '');
    $email    = strtolower(trim($_POST['email']    ?? ''));
    $password = $_POST['password']  ?? '';
    $confirm  = $_POST['confirm']   ?? '';
    $ref_code = strtoupper(trim($_POST['ref_code'] ?? ''));

    $old = compact('name', 'email', 'ref_code');

    // Validation
    if ($name === '' || mb_strlen($name) < 2) {
        $errors['name'] = 'Full name must be at least 2 characters.';
    }
    if (!validate_email($email)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if (!validate_password($password)) {
        $errors['password'] = 'Password must be at least 8 characters and contain letters and numbers.';
    }
    if ($password !== $confirm) {
        $errors['confirm'] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $result = register_user($name, $email, $password, $ref_code ?: null);

        if ($result['ok']) {
            flash_success('Account created! Please log in.');
            redirect(BASE_URL . '/public/login.php');
        } else {
            $errors['general'] = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — <?= e(setting('site_name','Motions')) ?></title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-box">

        <div class="auth-logo">
            <div class="logo-text">Video<span>SaaS</span></div>
        </div>

        <div class="auth-card">
            <h1 class="auth-title">Create Account</h1>
            <p class="auth-sub">Start generating AI marketing videos</p>

            <?= render_flash() ?>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert--error"><?= e($errors['general']) ?></div>
            <?php endif; ?>

            <form method="POST" action="" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="name">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control"
                           value="<?= e($old['name'] ?? '') ?>"
                           placeholder="John Doe" required autocomplete="name">
                    <?php if (!empty($errors['name'])): ?>
                        <div class="form-error"><?= e($errors['name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="<?= e($old['email'] ?? '') ?>"
                           placeholder="john@example.com" required autocomplete="email">
                    <?php if (!empty($errors['email'])): ?>
                        <div class="form-error"><?= e($errors['email']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="Min 8 chars with letters & numbers" required autocomplete="new-password">
                    <?php if (!empty($errors['password'])): ?>
                        <div class="form-error"><?= e($errors['password']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm">Confirm Password</label>
                    <input type="password" id="confirm" name="confirm" class="form-control"
                           placeholder="Repeat password" required autocomplete="new-password">
                    <?php if (!empty($errors['confirm'])): ?>
                        <div class="form-error"><?= e($errors['confirm']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="ref_code">Referral Code <span class="text-muted">(optional)</span></label>
                    <input type="text" id="ref_code" name="ref_code" class="form-control"
                           value="<?= e($old['ref_code'] ?? strtoupper(trim($_GET['ref'] ?? ''))) ?>"
                           placeholder="ENTER CODE" maxlength="20" style="text-transform:uppercase">
                    <div class="form-hint">Have a referral code? Enter it to give your inviter a reward.</div>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">Create Account</button>
            </form>

            <hr class="divider">
            <p class="text-center text-sm text-muted">
                Already have an account? <a href="login.php">Sign in</a>
            </p>
        </div>
    </div>
</div>
<script>
// If referral code came from URL, lock the field so it can't be accidentally cleared
(function() {
    const urlRef = new URLSearchParams(location.search).get('ref');
    if (urlRef) {
        const field = document.getElementById('ref_code');
        if (field && !field.value) field.value = urlRef.toUpperCase();
    }
})();
</script>
</body>
</html>
