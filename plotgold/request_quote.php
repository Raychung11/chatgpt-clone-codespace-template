<?php
require_once __DIR__ . '/inc/bootstrap.php';

$mode       = clean($_GET['mode']     ?? 'standard');
$providerId = clean_int($_GET['provider'] ?? 0);
$bundle     = clean($_GET['bundle']   ?? '');
$emergency  = $mode === 'urgent';

$provider = null;
if ($providerId) {
    $provider = Database::fetchOne(
        "SELECT p.*, up.full_name FROM providers p LEFT JOIN user_profiles up ON up.user_id = p.user_id WHERE p.id = ? AND p.approval_status = 'approved'",
        [$providerId]
    );
}

$serviceCategories = Database::fetchAll('SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order');

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();

    $contactName  = clean($_POST['contact_name']  ?? '');
    $contactEmail = clean_email($_POST['contact_email'] ?? '');
    $contactPhone = clean($_POST['contact_phone'] ?? '');
    $eventDate    = clean($_POST['event_date']    ?? '');
    $religion     = clean($_POST['religion']      ?? '');
    $location     = clean($_POST['event_location'] ?? '');
    $notes        = clean($_POST['notes']         ?? '');
    $services     = $_POST['services'] ?? [];

    if (!$contactName || !$contactPhone) {
        $error = 'Please provide your name and phone number.';
    } else {
        $quoteCode = generate_quote_code();
        $uuid      = pg_uuid();
        $buyerId   = null;

        if (auth_check()) {
            $buyer = Database::fetchOne('SELECT id FROM buyers WHERE user_id = ?', [auth_user_id()]);
            $buyerId = $buyer['id'] ?? null;
        }

        $quoteId = Database::insert(
            'INSERT INTO quotations (uuid, quote_code, buyer_id, contact_name, contact_email, contact_phone,
             event_date, event_location, religion, notes, status, source, mode)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $uuid, $quoteCode, $buyerId, $contactName, $contactEmail, $contactPhone,
                $eventDate ?: null, $location, $religion, $notes,
                'submitted', 'diy_planner', $emergency ? 'emergency' : 'standard',
            ]
        );

        $total = 0;
        foreach ($services as $svcId => $checked) {
            if (!$checked) continue;
            $svc = Database::fetchOne('SELECT * FROM funeral_services WHERE id = ?', [(int)$svcId]);
            if ($svc) {
                $qty = max(1, (int)($_POST['qty'][$svcId] ?? 1));
                $sub = ($svc['base_price'] ?? 0) * $qty;
                $total += $sub;
                Database::query(
                    'INSERT INTO quotation_items (quotation_id, item_name, item_description, category, unit_price, quantity, subtotal, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$quoteId, $svc['name'], $svc['description'], $svc['category_id'], $svc['base_price'], $qty, $sub, 0]
                );
            }
        }

        Database::query('UPDATE quotations SET total = ? WHERE id = ?', [$total, $quoteId]);
        Database::query('INSERT INTO quote_status_logs (quotation_id, from_status, to_status, note) VALUES (?, ?, ?, ?)',
            [$quoteId, null, 'submitted', 'Quote submitted via web form']);

        if (auth_check()) activity_log(auth_user_id(), 'quote_submitted', 'quotations', $quoteId);

        $success     = true;
        $quoteCodeOut = $quoteCode;
    }
}

$page_title       = $emergency ? __('quote.urgent_title') : __('quote.title');
$meta_description = 'Request an itemised funeral service quote from verified providers across Malaysia.';
include INC_PATH . '/header.php';
include INC_PATH . '/nav.php';
?>

<?php if ($emergency): ?>
<div class="emergency-banner">
    <div class="container">
        <h4><i class="fas fa-hands-helping me-2"></i><?= _e('quote.urgent_banner') ?></h4>
        <p class="mb-0 small opacity-75"><?= is_lang('zh') ? '填写此表格或' : 'Fill this form or' ?> <a href="<?= whatsapp_link('I need urgent funeral assistance.') ?>" class="text-white fw-600" target="_blank" rel="noopener">WhatsApp</a> <?= is_lang('zh') ? '以获得最快回应。' : 'for fastest response.' ?></p>
    </div>
</div>
<?php endif; ?>

<nav class="bg-white border-bottom">
    <div class="container py-2">
        <ol class="breadcrumb pg-breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= pg_url() ?>"><?= _e('nav.home') ?></a></li>
            <li class="breadcrumb-item"><a href="<?= pg_url('diy_funeral_planner.php') ?>"><?= _e('nav.planner') ?></a></li>
            <li class="breadcrumb-item active"><?= _e('quote.title') ?></li>
        </ol>
    </div>
</nav>

<div class="container py-4">
    <?php if ($success): ?>
    <div class="row justify-content-center">
        <div class="col-md-6 text-center py-5">
            <i class="fas fa-check-circle text-success fa-4x mb-4"></i>
            <h3 class="fw-700 text-navy"><?= _e('quote.success_title') ?></h3>
            <p class="text-muted"><?= is_lang('zh') ? '您的报价参考编号为' : 'Your quote reference is' ?> <strong class="text-navy"><?= h($quoteCodeOut ?? '') ?></strong>.</p>
            <p class="text-muted small"><?= _e('quote.success_body') ?></p>
            <?php if ($emergency): ?>
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-clock me-2"></i><?= is_lang('zh') ? '如属紧急情况，请同时' : 'For urgent cases, please also' ?> <a href="<?= whatsapp_link('I submitted an urgent quote request. Reference: ' . ($quoteCodeOut ?? '')) ?>" class="fw-600" target="_blank" rel="noopener">WhatsApp</a> <?= is_lang('zh') ? '我们，以便即时回应。' : 'us so we can respond immediately.' ?>
                </div>
            <?php endif; ?>
            <a href="<?= pg_url() ?>" class="btn btn-outline-gold mt-3"><?= _e('quote.back_home') ?></a>
        </div>
    </div>
    <?php else: ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="pg-card p-4">
                <h4 class="fw-600 mb-1"><?= $emergency ? '<i class="fas fa-hands-helping text-danger me-2"></i>' . _e('quote.urgent_title') : _e('quote.title') ?></h4>
                <p class="text-muted small mb-4"><?= $emergency ? (is_lang('zh') ? '填写此表格，我们将紧急为您匹配合适的服务商。' : 'Complete this form and we\'ll connect you with verified providers urgently.') : _e('quote.subtitle') ?></p>

                <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <?php if ($emergency): ?><input type="hidden" name="mode" value="urgent"><?php endif; ?>
                    <?php if ($providerId): ?><input type="hidden" name="provider_id" value="<?= $providerId ?>"><?php endif; ?>

                    <h6 class="fw-600 mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.08em"><?= _e('quote.section_contact') ?></h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label"><?= _e('quote.contact_name') ?> <span class="text-danger">*</span></label>
                            <input type="text" name="contact_name" class="form-control" required value="<?= auth_check() ? h(auth_user_name()) : '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= _e('quote.contact_phone') ?> <span class="text-danger">*</span></label>
                            <input type="tel" name="contact_phone" class="form-control" required placeholder="+60 12-345 6789">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= _e('quote.contact_email') ?></label>
                            <input type="email" name="contact_email" class="form-control" value="<?= auth_check() ? h(auth_user_email()) : '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= _e('quote.religion') ?></label>
                            <select name="religion" class="form-select">
                                <option value="">— Select if applicable —</option>
                                <?php foreach (Database::fetchAll('SELECT slug, label_en FROM religion_categories WHERE is_active = 1 ORDER BY sort_order') as $r): ?>
                                    <option value="<?= h($r['slug']) ?>"><?= h($r['label_en']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <h6 class="fw-600 mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.08em"><?= _e('quote.section_services') ?></h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label"><?= _e('quote.pref_date') ?> <?= $emergency ? '<span class="text-danger">*</span>' : '' ?></label>
                            <input type="date" name="event_date" class="form-control" min="<?= date('Y-m-d') ?>" <?= $emergency ? 'required' : '' ?>>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= _e('quote.location') ?></label>
                            <input type="text" name="event_location" class="form-control" placeholder="e.g. Cheras, Kuala Lumpur">
                        </div>
                    </div>

                    <?php if ($provider): ?>
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-briefcase me-2"></i><?= _e('quote.quoting_from') ?> <strong><?= h($provider['business_name']) ?></strong>
                    </div>
                    <?php endif; ?>

                    <h6 class="fw-600 mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.08em"><?= _e('quote.section_items') ?></h6>
                    <div class="row g-3 mb-4">
                        <?php foreach ($serviceCategories as $cat):
                            $svcs = Database::fetchAll('SELECT id, name, base_price, is_recommended FROM funeral_services WHERE category_id = ? AND is_active = 1 ORDER BY sort_order LIMIT 3', [$cat['id']]);
                        ?>
                        <div class="col-sm-6">
                            <div class="pg-card p-3">
                                <div class="fw-600 small mb-2"><?= h($cat['label_en']) ?></div>
                                <?php foreach ($svcs as $svc): ?>
                                    <div class="form-check d-flex align-items-center gap-2 mb-1">
                                        <input type="checkbox" name="services[<?= (int)$svc['id'] ?>]" value="1"
                                               class="form-check-input mt-0" id="svc_<?= (int)$svc['id'] ?>"
                                               <?= $bundle && $svc['is_recommended'] ? 'checked' : '' ?>>
                                        <label class="form-check-label small flex-grow-1" for="svc_<?= (int)$svc['id'] ?>">
                                            <?= h($svc['name']) ?>
                                            <?php if ($svc['base_price']): ?>
                                                <span class="text-muted">~RM <?= number_format($svc['base_price'], 0) ?></span>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label"><?= _e('quote.notes') ?></label>
                        <textarea name="notes" class="form-control" rows="4" placeholder="<?= _e('quote.notes') ?>…"></textarea>
                    </div>

                    <button type="submit" class="btn <?= $emergency ? 'btn-danger' : 'btn-gold' ?> w-100 py-3">
                        <i class="fas fa-paper-plane me-2"></i>
                        <?= $emergency ? _e('quote.submit_urgent') : _e('quote.submit') ?>
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="pg-card p-4 mb-3">
                <h6 class="fw-600 mb-3"><?= _e('quote.next_title') ?></h6>
                <div class="d-flex gap-3 mb-3">
                    <div class="step-circle" style="width:32px;height:32px;font-size:.85rem;flex-shrink:0">1</div>
                    <div><p class="small text-muted mb-0"><?= _e('quote.next_1') ?></p></div>
                </div>
                <div class="d-flex gap-3 mb-3">
                    <div class="step-circle" style="width:32px;height:32px;font-size:.85rem;flex-shrink:0">2</div>
                    <div><p class="small text-muted mb-0"><?= _e('quote.next_2') ?></p></div>
                </div>
                <div class="d-flex gap-3">
                    <div class="step-circle" style="width:32px;height:32px;font-size:.85rem;flex-shrink:0">3</div>
                    <div><p class="small text-muted mb-0"><?= _e('quote.next_3') ?></p></div>
                </div>
            </div>

            <?php if ($emergency): ?>
            <div class="pg-card p-4" style="border-left:4px solid var(--pg-danger)">
                <h6 class="fw-600 text-danger mb-2"><i class="fas fa-phone me-2"></i><?= _e('quote.urgent_help_title') ?></h6>
                <p class="small text-muted mb-3"><?= _e('quote.urgent_help_body') ?></p>
                <a href="<?= whatsapp_link('I need urgent funeral assistance.') ?>" target="_blank" rel="noopener" class="btn btn-whatsapp w-100">
                    <i class="fab fa-whatsapp me-2"></i><?= _e('btn.whatsapp') ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include INC_PATH . '/footer.php'; ?>
