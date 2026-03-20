<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (Auth::check()) { header('Location: /dashboard.php'); exit; }

$pageTitle = 'Login';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } elseif (Auth::login($email, $password)) {
        $redirect = $_GET['redirect'] ?? (Auth::isAdmin() ? '/admin/' : '/dashboard.php');
        header("Location: $redirect");
        exit;
    } else {
        $error = 'Invalid email or password. Please try again.';
    }
}

require_once 'includes/header.php';
?>

<div class="min-vh-100 d-flex align-items-center py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="text-center mb-4">
                    <a href="/" class="navbar-brand fw-bold fs-4 text-white">
                        <i class="bi bi-cpu-fill me-2 text-primary"></i><?= SITE_NAME ?>
                    </a>
                    <h2 class="text-white fw-bold mt-3 mb-1">Welcome back</h2>
                    <p class="text-muted">Sign in to your account</p>
                </div>

                <div class="glass-card rounded-4 p-4">
                    <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <?php if (isset($_GET['registered'])): ?>
                    <div class="alert alert-success py-2 small"><i class="bi bi-check-circle me-2"></i>Account created! Please log in.</div>
                    <?php endif; ?>

                    <form method="POST" novalidate>
                        <div class="mb-3">
                            <label class="form-label text-muted small">Email Address</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   class="form-control bg-dark border-secondary text-white"
                                   placeholder="you@company.com" required autofocus>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <label class="form-label text-muted small">Password</label>
                                <a href="/forgot-password.php" class="text-primary small text-decoration-none">Forgot password?</a>
                            </div>
                            <div class="input-group">
                                <input type="password" name="password" id="passwordInput"
                                       class="form-control bg-dark border-secondary text-white"
                                       placeholder="Your password" required>
                                <button type="button" class="btn btn-outline-secondary border-secondary" onclick="togglePassword()">
                                    <i class="bi bi-eye" id="eyeIcon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <div class="form-check">
                                <input type="checkbox" name="remember" class="form-check-input" id="remember">
                                <label for="remember" class="form-check-label text-muted small">Keep me signed in</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                        </button>
                    </form>

                    <hr class="border-secondary my-4">
                    <p class="text-center text-muted small mb-0">
                        Don't have an account? <a href="/register.php" class="text-primary text-decoration-none fw-semibold">Start free trial</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script>
function togglePassword() {
    const input = document.getElementById("passwordInput");
    const icon  = document.getElementById("eyeIcon");
    if (input.type === "password") { input.type = "text"; icon.className = "bi bi-eye-slash"; }
    else { input.type = "password"; icon.className = "bi bi-eye"; }
}
</script>';
require_once 'includes/footer.php';
?>
