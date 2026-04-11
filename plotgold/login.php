<?php
require_once __DIR__ . '/inc/bootstrap.php';

// Already logged in — redirect
if (auth_check()) {
    $roles = auth_roles();
    if (in_array(ROLE_ADMIN, $roles) || in_array(ROLE_SUPER_ADMIN, $roles)) redirect('admin/');
    elseif (in_array(ROLE_SELLER, $roles))   redirect('seller/dashboard.php');
    elseif (in_array(ROLE_PROVIDER, $roles)) redirect('provider/dashboard.php');
    else redirect('buyer/dashboard.php');
}

$error  = '';
$intent = clean($_GET['intent'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();

    $email    = clean_email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if (!$email || !$password) {
        $error = __('auth.error_invalid');
    } else {
        $result = auth_login($email, $password, $remember);
        if ($result['success']) {
            $redirect = clean($_POST['redirect'] ?? '');
            if ($redirect && str_starts_with($redirect, '/')) {
                redirect($redirect);
            }
            $roles = $result['roles'];
            if (in_array(ROLE_ADMIN, $roles) || in_array(ROLE_SUPER_ADMIN, $roles)) redirect('admin/');
            elseif (in_array(ROLE_SELLER, $roles))   redirect('seller/dashboard.php');
            elseif (in_array(ROLE_PROVIDER, $roles)) redirect('provider/dashboard.php');
            else redirect('buyer/dashboard.php');
        } else {
            $error = $result['error'];
        }
    }
}

$page_title       = __('auth.login_title');
$meta_description = 'Log in to your PlotGold Malaysia account.';
$body_class       = 'auth-page';
include INC_PATH . '/header.php';
?>

<div class="min-vh-100 d-flex align-items-center justify-content-center py-5" style="background: linear-gradient(135deg, var(--pg-navy) 0%, #243060 100%);">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">

                <!-- Logo -->
                <div class="text-center mb-4">
                    <a href="<?= pg_url() ?>" class="text-decoration-none">
                        <div class="mb-2">
                            <span class="fs-2 fw-bold text-white">Plot</span><span class="fs-2 fw-bold text-warning">Gold</span>
                        </div>
                        <p class="text-white-50 small mb-0">Malaysia</p>
                    </a>
                </div>

                <div class="pg-card p-4">
                    <h4 class="fw-600 mb-1"><?= _e('auth.welcome_back') ?></h4>
                    <p class="text-muted small mb-4"><?= _e('auth.login_subtitle') ?></p>

                    <?= render_flash() ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-sm"><?= h($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="" class="needs-validation" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="redirect" value="<?= h($_GET['redirect'] ?? '') ?>">

                        <div class="mb-3">
                            <label for="email" class="form-label"><?= _e('auth.email') ?></label>
                            <input type="email" id="email" name="email" class="form-control"
                                   value="<?= h($_POST['email'] ?? '') ?>"
                                   placeholder="you@example.com" required autofocus autocomplete="email">
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="password" class="form-label mb-0"><?= _e('auth.password') ?></label>
                                <a href="<?= pg_url('forgot_password.php') ?>" class="small text-muted"><?= _e('auth.forgot') ?></a>
                            </div>
                            <div class="input-group">
                                <input type="password" id="password" name="password" class="form-control"
                                       placeholder="••••••••" required autocomplete="current-password">
                                <button type="button" class="btn btn-outline-secondary border" onclick="
                                    const p = document.getElementById('password');
                                    p.type = p.type === 'password' ? 'text' : 'password';
                                    this.innerHTML = p.type === 'password' ? '<i class=\'fas fa-eye\'></i>' : '<i class=\'fas fa-eye-slash\'></i>';
                                "><i class="fas fa-eye"></i></button>
                            </div>
                        </div>

                        <div class="mb-4 d-flex align-items-center gap-2">
                            <input type="checkbox" id="remember" name="remember" class="form-check-input mt-0" value="1">
                            <label for="remember" class="form-check-label small text-muted"><?= _e('auth.remember') ?></label>
                        </div>

                        <button type="submit" class="btn btn-gold w-100">
                            <i class="fas fa-sign-in-alt me-2"></i><?= _e('auth.login_btn') ?>
                        </button>
                    </form>

                    <hr class="my-4">

                    <p class="text-center small text-muted mb-2"><?= _e('auth.no_account') ?></p>
                    <div class="row g-2">
                        <div class="col-6">
                            <a href="<?= pg_url('register.php?type=buyer') ?>" class="btn btn-outline-secondary w-100 btn-sm">
                                <i class="fas fa-user me-1"></i><?= _e('auth.register_buyer') ?>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="<?= pg_url('register.php?type=seller') ?>" class="btn btn-outline-gold w-100 btn-sm">
                                <i class="fas fa-tag me-1"></i><?= _e('auth.register_seller') ?>
                            </a>
                        </div>
                    </div>
                </div>

                <p class="text-center text-white-50 small mt-4">
                    <?= _e('auth.urgent_help') ?> <a href="<?= whatsapp_link(__('nav.urgent')) ?>" class="text-warning" target="_blank" rel="noopener">WhatsApp</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php include INC_PATH . '/footer.php'; ?>
