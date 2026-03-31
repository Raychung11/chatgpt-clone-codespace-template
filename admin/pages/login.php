<?php
/**
 * Admin Login Page
 * /admin/pages/login.php  (served via admin/index.php router)
 *
 * BASE_PATH and bootstrap already loaded by admin/index.php.
 */

// Already logged in → go straight to dashboard
if (Auth::check() && Auth::isAdmin()) {
    header('Location: /admin/dashboard');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email    = sanitize_string($_POST['email']    ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Email and password are required.';
        } else {
            $user = Database::fetchOne(
                'SELECT * FROM users WHERE email = ? AND role IN ("admin","superadmin","staff") AND status = "active"',
                [$email]
            );

            if ($user && password_verify($password, $user['password_hash'])) {
                Auth::login($user);
                Database::execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
                admin_log('login', 'auth', null, 'Admin login');
                header('Location: /admin/dashboard');
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}

$csrf = Auth::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login – F&B Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #1a1a2e; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { background: #fff; border-radius: 12px; padding: 2.5rem; width: 100%; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,0.4); }
        .brand-title { font-weight: 700; color: #e94560; font-size: 1.4rem; }
        .btn-login { background: #e94560; border: none; font-weight: 600; letter-spacing: 0.5px; }
        .btn-login:hover { background: #c73652; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="text-center mb-4">
        <div class="brand-title">🍽️ F&B Admin</div>
        <p class="text-muted mt-1 mb-0 small">Merchant Management Platform</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/admin/login">
        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">

        <div class="mb-3">
            <label class="form-label fw-semibold">Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="admin@example.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold">Password</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn btn-login text-white w-100 py-2">Sign In</button>
    </form>

    <p class="text-center text-muted small mt-3 mb-0">
        Contact your system administrator for access.
    </p>
</div>
</body>
</html>
