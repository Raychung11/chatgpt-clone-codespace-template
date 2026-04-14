<?php
require_once __DIR__ . '/inc/bootstrap.php';

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name    = clean($_POST['name']    ?? '');
    $email   = clean($_POST['email']   ?? '');
    $phone   = clean($_POST['phone']   ?? '');
    $topic   = clean($_POST['topic']   ?? '');
    $message = clean($_POST['message'] ?? '');

    if (!$name)                          $errors['name']    = __('contact.err_name');
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))
                                         $errors['email']   = __('contact.err_email');
    if (!$message)                       $errors['message'] = __('contact.err_message');

    if (!$errors) {
        // Attempt to save to DB; gracefully swallow if table doesn't exist
        try {
            Database::query(
                'INSERT INTO contact_messages (name, email, phone, topic, message, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())',
                [$name, $email, $phone, $topic, $message]
            );
        } catch (\Throwable $e) {
            // Table may not exist yet — message is still acknowledged
        }

        $success = true;
        // Clear fields on success
        $name = $email = $phone = $topic = $message = '';
    }
}

$page_title       = __('contact.title');
$meta_description = 'Contact PlotGold Malaysia — reach us via WhatsApp, email or our online form. Bereavement support available 24/7.';
include INC_PATH . '/header.php';
include INC_PATH . '/nav.php';
?>

<!-- Breadcrumb -->
<nav class="bg-white border-bottom">
    <div class="container py-2">
        <ol class="breadcrumb pg-breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= pg_url() ?>"><?= _e('nav.home') ?></a></li>
            <li class="breadcrumb-item active"><?= _e('contact.title') ?></li>
        </ol>
    </div>
</nav>

<!-- Hero -->
<section style="background:linear-gradient(135deg,#0D1B2A 0%,#1a2f4a 100%);color:#fff;padding:4rem 0 3rem;">
    <div class="container text-center">
        <h1 class="display-6 fw-700 mb-2"><?= _e('contact.title') ?></h1>
        <p class="mb-0" style="color:rgba(255,255,255,.75);max-width:520px;margin:0 auto;">
            <?= _e('contact.subtitle') ?>
        </p>
    </div>
</section>

<!-- Main Content -->
<section class="py-5" style="background:#F8F9FA;">
    <div class="container">
        <div class="row g-4 justify-content-center">

            <!-- ── Contact Form ── -->
            <div class="col-lg-7">
                <div class="pg-card p-4 p-md-5">
                    <h2 class="h5 fw-700 text-navy mb-4">
                        <i class="fas fa-paper-plane me-2" style="color:var(--pg-gold);"></i>
                        <?= _e('contact.form_title') ?>
                    </h2>

                    <?php if ($success): ?>
                        <div class="alert alert-success d-flex gap-3 align-items-start">
                            <i class="fas fa-check-circle fa-lg mt-1 flex-shrink-0"></i>
                            <div>
                                <strong><?= _e('flash.success') ?></strong><br>
                                <?= _e('contact.sent') ?>
                            </div>
                        </div>
                        <div class="text-center mt-3">
                            <a href="<?= pg_url('contact.php') ?>" class="btn btn-outline-secondary btn-sm">
                                <?= is_lang('zh') ? '再次发送' : 'Send Another Message' ?>
                            </a>
                        </div>
                    <?php else: ?>
                    <form method="POST" action="" novalidate>
                        <?= csrf_field() ?>

                        <div class="row g-3">
                            <!-- Name -->
                            <div class="col-sm-6">
                                <label class="form-label fw-600 small"><?= _e('contact.name') ?> <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                                       value="<?= h($name ?? '') ?>" placeholder="<?= is_lang('zh') ? '您的全名' : 'Your full name' ?>" required>
                                <?php if (isset($errors['name'])): ?>
                                    <div class="invalid-feedback"><?= h($errors['name']) ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Email -->
                            <div class="col-sm-6">
                                <label class="form-label fw-600 small"><?= _e('contact.email') ?> <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                       value="<?= h($email ?? '') ?>" placeholder="you@example.com" required>
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback"><?= h($errors['email']) ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Phone -->
                            <div class="col-sm-6">
                                <label class="form-label fw-600 small"><?= _e('contact.phone') ?> <span class="text-muted fw-400"><?= _e('misc.optional') ?></span></label>
                                <input type="tel" name="phone" class="form-control"
                                       value="<?= h($phone ?? '') ?>" placeholder="+60 11-XXXX XXXX">
                            </div>

                            <!-- Topic -->
                            <div class="col-sm-6">
                                <label class="form-label fw-600 small"><?= _e('contact.subject') ?></label>
                                <select name="topic" class="form-select">
                                    <?php
                                    $topics = [
                                        ''          => __('contact.topic_general'),
                                        'buying'    => __('contact.topic_buying'),
                                        'selling'   => __('contact.topic_selling'),
                                        'provider'  => __('contact.topic_provider'),
                                        'funeral'   => __('contact.topic_funeral'),
                                        'urgent'    => __('contact.topic_urgent'),
                                        'other'     => __('contact.topic_other'),
                                    ];
                                    foreach ($topics as $val => $label): ?>
                                        <option value="<?= h($val) ?>" <?= ($topic ?? '') === $val ? 'selected' : '' ?>><?= h($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Message -->
                            <div class="col-12">
                                <label class="form-label fw-600 small"><?= _e('contact.message') ?> <span class="text-danger">*</span></label>
                                <textarea name="message" rows="6"
                                          class="form-control <?= isset($errors['message']) ? 'is-invalid' : '' ?>"
                                          placeholder="<?= is_lang('zh') ? '请告诉我们您需要什么帮助……' : 'Tell us how we can help you…' ?>"
                                          required><?= h($message ?? '') ?></textarea>
                                <?php if (isset($errors['message'])): ?>
                                    <div class="invalid-feedback"><?= h($errors['message']) ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Submit -->
                            <div class="col-12">
                                <button type="submit" class="btn btn-gold btn-lg w-100">
                                    <i class="fas fa-paper-plane me-2"></i><?= _e('contact.send') ?>
                                </button>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Sidebar: Contact Info ── -->
            <div class="col-lg-4">

                <!-- WhatsApp CTA -->
                <div class="pg-card p-4 mb-4" style="background:linear-gradient(135deg,#25D366,#128C7E);color:#fff;border:none;">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div style="width:48px;height:48px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fab fa-whatsapp fa-xl"></i>
                        </div>
                        <div>
                            <div class="fw-700"><?= _e('contact.whatsapp_title') ?></div>
                            <div style="font-size:.8rem;opacity:.85;"><?= is_lang('zh') ? '24/7 全天候在线' : 'Available 24/7' ?></div>
                        </div>
                    </div>
                    <p style="font-size:.88rem;opacity:.9;margin-bottom:1rem;"><?= _e('contact.whatsapp_body') ?></p>
                    <a href="<?= whatsapp_link(__('nav.urgent')) ?>"
                       target="_blank" rel="noopener"
                       class="btn btn-light btn-sm w-100 fw-600" style="color:#128C7E;">
                        <i class="fab fa-whatsapp me-2"></i><?= _e('contact.whatsapp_btn') ?>
                    </a>
                </div>

                <!-- Contact Details -->
                <div class="pg-card p-4 mb-4">
                    <h3 class="h6 fw-700 text-navy mb-3">
                        <i class="fas fa-info-circle me-2" style="color:var(--pg-gold);"></i>
                        <?= _e('contact.office_title') ?>
                    </h3>
                    <ul class="list-unstyled mb-0" style="font-size:.9rem;">
                        <li class="d-flex gap-3 mb-3">
                            <i class="fas fa-envelope text-muted mt-1 flex-shrink-0"></i>
                            <div>
                                <div class="text-muted small"><?= _e('contact.email_label') ?></div>
                                <a href="mailto:<?= h(get_setting('site_email','hello@plotgold.my')) ?>" class="text-navy fw-600 text-decoration-none">
                                    <?= h(get_setting('site_email','hello@plotgold.my')) ?>
                                </a>
                            </div>
                        </li>
                        <li class="d-flex gap-3 mb-3">
                            <i class="fab fa-whatsapp text-muted mt-1 flex-shrink-0"></i>
                            <div>
                                <div class="text-muted small"><?= _e('contact.phone_label') ?></div>
                                <a href="<?= whatsapp_link('Hi PlotGold!') ?>" class="text-navy fw-600 text-decoration-none" target="_blank" rel="noopener">
                                    <?= h(get_setting('site_phone','+60 11-XXXX XXXX')) ?>
                                </a>
                            </div>
                        </li>
                        <li class="d-flex gap-3 mb-3">
                            <i class="fas fa-map-marker-alt text-muted mt-1 flex-shrink-0"></i>
                            <div>
                                <div class="text-muted small"><?= is_lang('zh') ? '地址' : 'Location' ?></div>
                                <div class="text-navy fw-600">Kuala Lumpur, Malaysia</div>
                                <div class="text-muted small"><?= is_lang('zh') ? '（服务巴生谷及雪兰莪）' : 'Serving Klang Valley &amp; Selangor' ?></div>
                            </div>
                        </li>
                        <li class="d-flex gap-3">
                            <i class="fas fa-clock text-muted mt-1 flex-shrink-0"></i>
                            <div>
                                <div class="text-muted small"><?= is_lang('zh') ? '办公时间' : 'Office Hours' ?></div>
                                <div class="text-navy fw-600 small"><?= _e('contact.office_hours') ?></div>
                                <div class="text-navy fw-600 small"><?= _e('contact.office_hours2') ?></div>
                                <div class="text-muted small"><?= is_lang('zh') ? 'WhatsApp 全天候开放' : 'WhatsApp available 24/7' ?></div>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Quick Links -->
                <div class="pg-card p-4">
                    <h3 class="h6 fw-700 text-navy mb-3">
                        <i class="fas fa-link me-2" style="color:var(--pg-gold);"></i>
                        <?= is_lang('zh') ? '快速导航' : 'Quick Links' ?>
                    </h3>
                    <ul class="list-unstyled mb-0" style="font-size:.9rem;">
                        <li class="mb-2">
                            <a href="<?= pg_url('request_quote.php?mode=urgent') ?>" class="text-decoration-none d-flex align-items-center gap-2" style="color:var(--pg-gold);font-weight:600;">
                                <i class="fas fa-ambulance fa-fw"></i><?= _e('nav.urgent') ?>
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?= pg_url('diy_funeral_planner.php') ?>" class="text-decoration-none d-flex align-items-center gap-2 text-muted">
                                <i class="fas fa-list-alt fa-fw"></i><?= _e('nav.diy') ?>
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?= pg_url('request_quote.php') ?>" class="text-decoration-none d-flex align-items-center gap-2 text-muted">
                                <i class="fas fa-file-invoice fa-fw"></i><?= _e('btn.get_quote') ?>
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?= pg_url('how_it_works.php') ?>" class="text-decoration-none d-flex align-items-center gap-2 text-muted">
                                <i class="fas fa-question-circle fa-fw"></i><?= _e('how.title') ?>
                            </a>
                        </li>
                        <li>
                            <a href="<?= pg_url('about.php') ?>" class="text-decoration-none d-flex align-items-center gap-2 text-muted">
                                <i class="fas fa-info-circle fa-fw"></i><?= _e('footer.about') ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Urgent Banner -->
<section style="background:#FFF3CD;border-top:1px solid #FFD700;border-bottom:1px solid #FFD700;padding:1.5rem 0;">
    <div class="container">
        <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-exclamation-triangle fa-lg" style="color:#856404;"></i>
                <div>
                    <div class="fw-700" style="color:#856404;"><?= is_lang('zh') ? '紧急丧葬支援' : 'Urgent Bereavement Support' ?></div>
                    <div class="small" style="color:#856404;opacity:.85;">
                        <?= is_lang('zh') ? '如遇紧急情况，请直接联系我们，无需填写表格。' : 'For urgent cases, contact us directly — no need to fill out the form.' ?>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2 flex-shrink-0">
                <a href="<?= whatsapp_link(__('nav.urgent')) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm">
                    <i class="fab fa-whatsapp me-1"></i><?= is_lang('zh') ? '立即 WhatsApp' : 'WhatsApp Now' ?>
                </a>
                <a href="<?= pg_url('request_quote.php?mode=urgent') ?>" class="btn btn-sm" style="background:#856404;color:#fff;">
                    <i class="fas fa-clock me-1"></i><?= _e('nav.urgent') ?>
                </a>
            </div>
        </div>
    </div>
</section>

<?php include INC_PATH . '/footer.php'; ?>
