<?php
require_once __DIR__ . '/inc/bootstrap.php';

if (auth_check()) redirect('/');

$type  = in_array($_GET['type'] ?? '', ['buyer','seller','provider']) ? $_GET['type'] : 'buyer';
$error = '';
$data  = [];

// Capture referral code from URL and store in session
if (!empty($_GET['ref'])) {
    $_SESSION['pg_referral'] = strtoupper(clean($_GET['ref']));
}
$referralCode = $_SESSION['pg_referral'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();

    $type  = in_array($_POST['type'] ?? '', ['buyer','seller','provider']) ? $_POST['type'] : 'buyer';
    $data  = [
        'full_name'     => clean($_POST['full_name']     ?? ''),
        'email'         => clean_email($_POST['email']   ?? ''),
        'phone'         => clean($_POST['phone']         ?? ''),
        'password'      => $_POST['password']            ?? '',
        'password2'     => $_POST['password2']           ?? '',
        'business_name' => clean($_POST['business_name'] ?? ''),
        'referral_code' => strtoupper(clean($_POST['referral_code'] ?? $referralCode)),
    ];
    $agree = !empty($_POST['agree']);

    // Validation
    if (!$data['full_name'])                        $error = __('auth.full_name') . ' ' . __('misc.required');
    elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $error = __('auth.error_invalid');
    elseif ($data['phone'] && !validate_phone($data['phone'])) $error = __('auth.phone') . ' is invalid.';
    elseif (strlen($data['password']) < 8)          $error = __('auth.password') . ' must be at least 8 characters.';
    elseif ($data['password'] !== $data['password2'])$error = __('auth.error_pw_mismatch');
    elseif (!$agree)                                $error = __('auth.error_terms');
    elseif ($type === 'provider' && !$data['business_name']) $error = __('auth.business_name') . ' is required.';
    else {
        $roleMap = ['buyer' => ROLE_BUYER, 'seller' => ROLE_SELLER, 'provider' => ROLE_PROVIDER];
        $result  = auth_register($data, $roleMap[$type]);
        if ($result['success']) {
            unset($_SESSION['pg_referral']);
            $loginResult = auth_login($data['email'], $data['password']);
            flash_set(FLASH_SUCCESS, __('auth.welcome_back') . '! Your account is ready.');
            if ($type === 'seller')        redirect('seller/dashboard.php');
            elseif ($type === 'provider')  redirect('provider/dashboard.php');
            else                           redirect('buyer/dashboard.php');
        } else {
            $error = $result['error'];
        }
    }
}

$titles = ['buyer' => 'Create a Buyer Account', 'seller' => 'List Your Burial Plot', 'provider' => 'Join as a Service Provider'];
$page_title = $titles[$type] ?? 'Create Account';
$body_class = 'auth-page';
include INC_PATH . '/header.php';
?>

<div class="min-vh-100 d-flex align-items-center justify-content-center py-5" style="background: linear-gradient(135deg, var(--pg-navy) 0%, #243060 100%);">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-8 col-lg-6 col-xl-5">

                <div class="text-center mb-4">
                    <a href="<?= pg_url() ?>" class="text-decoration-none">
                        <span class="fs-2 fw-bold text-white">Plot</span><span class="fs-2 fw-bold text-warning">Gold</span>
                        <p class="text-white-50 small mt-1 mb-0">Malaysia</p>
                    </a>
                </div>

                <!-- Account Type Selector -->
                <div class="d-flex gap-2 mb-4">
                    <?php foreach (['buyer' => ['icon' => 'fa-user', 'key' => 'auth.role_buyer'], 'seller' => ['icon' => 'fa-tag', 'key' => 'auth.role_seller'], 'provider' => ['icon' => 'fa-briefcase', 'key' => 'auth.role_provider']] as $t => $cfg): ?>
                        <a href="?type=<?= $t ?>" class="flex-fill btn <?= $type === $t ? 'btn-gold' : 'btn-outline-light' ?> btn-sm text-center py-2">
                            <i class="fas <?= $cfg['icon'] ?> d-block mb-1"></i>
                            <span style="font-size:.8rem"><?= _e($cfg['key']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="pg-card p-4">
                    <h4 class="fw-600 mb-1"><?= h($page_title) ?></h4>
                    <p class="text-muted small mb-4">
                        <?php if ($type === 'seller'): echo _e('auth.seller_subtitle');
                        elseif ($type === 'provider'): echo _e('auth.provider_subtitle');
                        else: echo _e('auth.buyer_subtitle');
                        endif; ?>
                    </p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= h($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="" class="needs-validation" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="type" value="<?= h($type) ?>">
                        <?php if ($referralCode): ?>
                            <input type="hidden" name="referral_code" value="<?= h($referralCode) ?>">
                            <div class="alert alert-success py-2 small mb-3">
                                <i class="fas fa-gift me-2"></i><?= is_lang('zh') ? '您通过推荐链接注册。推荐码：' : 'Referred by a friend. Code: ' ?><strong><?= h($referralCode) ?></strong>
                            </div>
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="full_name" class="form-label"><?= _e('auth.full_name') ?> <span class="text-danger">*</span></label>
                                <input type="text" id="full_name" name="full_name" class="form-control"
                                       value="<?= h($data['full_name'] ?? '') ?>"
                                       placeholder="As per IC" required>
                            </div>

                            <?php if ($type === 'provider'): ?>
                            <div class="col-12">
                                <label for="business_name" class="form-label"><?= _e('auth.business_name') ?> <span class="text-danger">*</span></label>
                                <input type="text" id="business_name" name="business_name" class="form-control"
                                       value="<?= h($data['business_name'] ?? '') ?>"
                                       placeholder="Registered business name" required>
                            </div>
                            <?php endif; ?>

                            <div class="col-md-6">
                                <label for="email" class="form-label"><?= _e('auth.email') ?> <span class="text-danger">*</span></label>
                                <input type="email" id="email" name="email" class="form-control"
                                       value="<?= h($data['email'] ?? '') ?>"
                                       placeholder="you@example.com" required autocomplete="email">
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label"><?= _e('auth.phone') ?></label>
                                <input type="tel" id="phone" name="phone" class="form-control"
                                       value="<?= h($data['phone'] ?? '') ?>"
                                       placeholder="+60 12-345 6789">
                            </div>

                            <div class="col-md-6">
                                <label for="password" class="form-label"><?= _e('auth.password') ?> <span class="text-danger">*</span></label>
                                <input type="password" id="password" name="password" class="form-control"
                                       placeholder="Min. 8 characters" required minlength="8" autocomplete="new-password">
                            </div>
                            <div class="col-md-6">
                                <label for="password2" class="form-label"><?= _e('auth.password_confirm') ?> <span class="text-danger">*</span></label>
                                <input type="password" id="password2" name="password2" class="form-control"
                                       placeholder="Repeat password" required autocomplete="new-password">
                            </div>

                            <?php if (!$referralCode): ?>
                            <div class="col-12">
                                <label for="referral_code" class="form-label small text-muted"><?= is_lang('zh') ? '推荐码（选填）' : 'Referral Code (optional)' ?></label>
                                <input type="text" id="referral_code" name="referral_code" class="form-control form-control-sm"
                                       value="<?= h($data['referral_code'] ?? '') ?>"
                                       placeholder="e.g. PGAB3F91" maxlength="20" style="text-transform:uppercase">
                            </div>
                            <?php endif; ?>

                            <div class="col-12">
                                <div class="form-check">
                                    <input type="checkbox" id="agree" name="agree" class="form-check-input" value="1" required>
                                    <label for="agree" class="form-check-label small text-muted">
                                        <?= _e('auth.agree_terms') ?> <a href="<?= pg_url('terms.php') ?>" target="_blank"><?= _e('footer.terms') ?></a> <?= _e('misc.and') ?>
                                        <a href="<?= pg_url('privacy.php') ?>" target="_blank"><?= _e('footer.privacy') ?></a>
                                    </label>
                                </div>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-gold w-100">
                                    <i class="fas fa-user-plus me-2"></i><?= _e('auth.register_btn') ?>
                                </button>
                            </div>
                        </div>
                    </form>

                    <p class="text-center small text-muted mt-4 mb-0">
                        <?= _e('auth.have_account') ?> <a href="<?= pg_url('login.php') ?>"><?= _e('auth.login_link') ?></a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include INC_PATH . '/footer.php'; ?>
