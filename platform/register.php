<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (Auth::check()) { header('Location: /dashboard.php'); exit; }

// Capture referral code from URL and store in a 30-day cookie
$refCode = trim($_GET['ref'] ?? '');
if ($refCode) {
    $exp = date('D, d M Y H:i:s T', strtotime('+30 days'));
    header("Set-Cookie: bizai_ref=" . urlencode(strtoupper($refCode)) . "; Path=/; Expires=$exp; HttpOnly; SameSite=Lax");
}
// Read from cookie if not in URL
if (!$refCode && !empty($_COOKIE['bizai_ref'])) {
    $refCode = strtoupper(trim($_COOKIE['bizai_ref']));
}

// Capture affiliate code from URL — separate from user referral code
$affCode = strtoupper(trim($_GET['aff'] ?? ''));
if ($affCode) {
    $exp = date('D, d M Y H:i:s T', strtotime('+30 days'));
    header("Set-Cookie: bizai_aff=" . urlencode($affCode) . "; Path=/; Expires=$exp; HttpOnly; SameSite=Lax");
}
if (!$affCode && !empty($_COOKIE['bizai_aff'])) {
    $affCode = strtoupper(trim($_COOKIE['bizai_aff']));
}

$pageTitle = 'Create Account - Free Trial';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $company  = trim($_POST['company']  ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';
    $agree    = isset($_POST['agree']);
    $refCode  = strtoupper(trim($_POST['ref_code'] ?? $refCode));

    if (!$name)    $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if (!$agree)   $errors[] = 'You must agree to the Terms of Service.';

    if (empty($errors)) {
        $userId = Auth::register($name, $email, $password, $company, $refCode);
        if ($userId === false) {
            $errors[] = 'An account with this email already exists. Please log in.';
        } else {
            // Clear referral cookie
            header("Set-Cookie: bizai_ref=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT");
            header('Location: /login.php?registered=1');
            exit;
        }
    }
}

require_once 'includes/header.php';
?>

<div class="min-vh-100 d-flex align-items-center py-5">
    <div class="container">
        <div class="row justify-content-center align-items-center g-5">
            <!-- Benefits -->
            <div class="col-lg-5 d-none d-lg-block">
                <h2 class="text-white fw-bold mb-3">Start your <?= TRIAL_DAYS ?>-day free trial</h2>
                <p class="text-muted mb-4">Join thousands of SMEs automating their business with AI. No credit card required.</p>
                <ul class="list-unstyled">
                    <?php $benefits = [
                        ['icon'=>'bi-check-circle-fill','color'=>'text-success','text'=>'Access to all AI Capsules'],
                        ['icon'=>'bi-check-circle-fill','color'=>'text-success','text'=>'14-day free trial, no card needed'],
                        ['icon'=>'bi-check-circle-fill','color'=>'text-success','text'=>'Cancel anytime, no questions asked'],
                        ['icon'=>'bi-check-circle-fill','color'=>'text-success','text'=>'Dedicated onboarding support'],
                        ['icon'=>'bi-check-circle-fill','color'=>'text-success','text'=>'Integrates with your existing tools'],
                    ]; ?>
                    <?php foreach ($benefits as $b): ?>
                    <li class="mb-3 d-flex align-items-center gap-3">
                        <i class="bi <?= $b['icon'] ?> <?= $b['color'] ?> fs-5"></i>
                        <span class="text-muted"><?= $b['text'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <div class="glass-card rounded-4 p-4 mt-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-initials">SK</div>
                        <div>
                            <p class="text-muted small mb-1">"Set up in 10 minutes. Saved 20 hours in week one."</p>
                            <div class="text-white small fw-semibold">Sam K. — Operations Lead</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Registration Form -->
            <div class="col-md-6 col-lg-5">
                <div class="text-center mb-4 d-lg-none">
                    <a href="/" class="navbar-brand fw-bold fs-4 text-white">
                        <i class="bi bi-cpu-fill me-2 text-primary"></i><?= SITE_NAME ?>
                    </a>
                </div>

                <div class="glass-card rounded-4 p-4">
                    <h4 class="text-white fw-bold mb-1">Create your account</h4>
                    <p class="text-muted small mb-4"><?= TRIAL_DAYS ?> days free — then from $49/mo</p>

                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger py-2 small">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if ($refCode): ?>
                    <div class="alert py-2 mb-3 d-flex align-items-center gap-2" style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);border-radius:10px">
                        <i class="bi bi-gift-fill text-success"></i>
                        <span class="text-success small fw-semibold">Referred by a friend — referral bonus applied!</span>
                    </div>
                    <?php endif; ?>

                    <form method="POST" novalidate>
                        <input type="hidden" name="ref_code" value="<?= htmlspecialchars($refCode) ?>">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label text-muted small">Full Name *</label>
                                <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                                       class="form-control bg-dark border-secondary text-white"
                                       placeholder="Jane Smith" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted small">Work Email *</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                       class="form-control bg-dark border-secondary text-white"
                                       placeholder="jane@company.com" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted small">Company Name</label>
                                <input type="text" name="company" value="<?= htmlspecialchars($_POST['company'] ?? '') ?>"
                                       class="form-control bg-dark border-secondary text-white"
                                       placeholder="Your Company Ltd">
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted small">Password * <span class="text-muted">(min 8 chars)</span></label>
                                <div class="input-group">
                                    <input type="password" name="password" id="pass1"
                                           class="form-control bg-dark border-secondary text-white"
                                           placeholder="Create a password" required>
                                    <button type="button" class="btn btn-outline-secondary border-secondary" onclick="togglePass('pass1','eye1')">
                                        <i class="bi bi-eye" id="eye1"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted small">Confirm Password *</label>
                                <div class="input-group">
                                    <input type="password" name="confirm" id="pass2"
                                           class="form-control bg-dark border-secondary text-white"
                                           placeholder="Repeat your password" required>
                                    <button type="button" class="btn btn-outline-secondary border-secondary" onclick="togglePass('pass2','eye2')">
                                        <i class="bi bi-eye" id="eye2"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input type="checkbox" name="agree" class="form-check-input" id="agree" <?= isset($_POST['agree']) ? 'checked' : '' ?>>
                                    <label for="agree" class="form-check-label text-muted small">
                                        I agree to the <a href="#" class="text-primary">Terms of Service</a> and <a href="#" class="text-primary">Privacy Policy</a>
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100 py-2">
                                    <i class="bi bi-rocket-takeoff me-2"></i>Start Free Trial
                                </button>
                            </div>
                        </div>
                    </form>

                    <hr class="border-secondary my-4">
                    <p class="text-center text-muted small mb-0">
                        Already have an account? <a href="/login.php" class="text-primary text-decoration-none fw-semibold">Sign in</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script>
function togglePass(id, iconId) {
    const input = document.getElementById(id);
    const icon  = document.getElementById(iconId);
    if (input.type === "password") { input.type = "text"; icon.className = "bi bi-eye-slash"; }
    else { input.type = "password"; icon.className = "bi bi-eye"; }
}
</script>';
require_once 'includes/footer.php';
?>
