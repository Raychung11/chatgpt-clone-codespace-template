<?php
require_once __DIR__ . '/inc/bootstrap.php';

$page_title       = __('sell.hero_title') . ' | PlotGold Malaysia';
$meta_description = 'List your resale burial plot, family lot, or columbarium niche on PlotGold Malaysia. Free listing, guided verification, and reach thousands of buyers.';
include INC_PATH . '/header.php';
include INC_PATH . '/nav.php';
?>

<!-- Hero -->
<section class="pg-hero py-5">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-6">
                <div class="badge rounded-pill bg-warning text-dark mb-3 px-3 py-2"><?= _e('sell.free_badge') ?></div>
                <h1 class="text-white"><?= _e('sell.hero_title') ?></h1>
                <p class="lead text-white mb-4" style="opacity:.88"><?= _e('sell.hero_subtitle') ?></p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="<?= pg_url('register.php?type=seller') ?>" class="btn btn-gold btn-lg">
                        <i class="fas fa-tag me-2"></i><?= _e('sell.start_listing') ?>
                    </a>
                    <a href="<?= whatsapp_link(__('sell.ask_first')) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp btn-lg">
                        <i class="fab fa-whatsapp me-2"></i><?= _e('sell.ask_first') ?>
                    </a>
                </div>
            </div>
            <div class="col-lg-6 d-none d-lg-block">
                <div class="row g-3">
                    <?php
                    $benefits = is_lang('zh') ? [
                        ['icon' => 'fa-users', 'title' => '触达买家', 'desc' => '每月数千名买家浏览经验证的房源。'],
                        ['icon' => 'fa-shield-alt', 'title' => '可信平台', 'desc' => '我们的认证标识增强买家信心。'],
                        ['icon' => 'fa-chart-line', 'title' => '价格参考', 'desc' => '我们提供市场价格数据帮您定价。'],
                        ['icon' => 'fa-headset', 'title' => '专属支援', 'desc' => '我们的团队协助文件准备和询价管理。'],
                    ] : [
                        ['icon' => 'fa-users', 'title' => 'Reach Buyers', 'desc' => 'Thousands of buyers browsing verified listings every month.'],
                        ['icon' => 'fa-shield-alt', 'title' => 'Trusted Platform', 'desc' => 'Our verification badge builds buyer confidence.'],
                        ['icon' => 'fa-chart-line', 'title' => 'Price Benchmarks', 'desc' => 'We provide market pricing data to help you price right.'],
                        ['icon' => 'fa-headset', 'title' => 'Dedicated Support', 'desc' => 'Our team helps with document prep and enquiry management.'],
                    ];
                    foreach ($benefits as $b): ?>
                    <div class="col-6">
                        <div class="p-3 rounded-3 text-white" style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15)">
                            <i class="fas <?= $b['icon'] ?> text-warning mb-2"></i>
                            <h6 class="fw-600 small mb-1"><?= $b['title'] ?></h6>
                            <p class="text-white-50 mb-0" style="font-size:.8rem"><?= $b['desc'] ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How Selling Works -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title"><?= _e('sell.how_title') ?></h2>
            <div class="section-divider mx-auto"></div>
        </div>
        <div class="row g-4">
            <?php
            $steps = is_lang('zh') ? [
                ['num' => '1', 'title' => '注册并认证', 'desc' => '创建卖家账号并完成基本身份认证。', 'icon' => 'fa-user-check'],
                ['num' => '2', 'title' => '提交您的房源', 'desc' => '填写墓地详情，上传照片，附上所有权文件。', 'icon' => 'fa-file-upload'],
                ['num' => '3', 'title' => '审核认证', 'desc' => '我们的团队在 3–5 个工作日内审核文件并颁发信任标识。', 'icon' => 'fa-search'],
                ['num' => '4', 'title' => '上架并接收询价', 'desc' => '您的房源上架后，数千名买家可查看。在控制台管理询价。', 'icon' => 'fa-bolt'],
            ] : [
                ['num' => '1', 'title' => 'Register & Verify', 'desc' => 'Create a seller account and complete basic identity verification.', 'icon' => 'fa-user-check'],
                ['num' => '2', 'title' => 'Submit Your Listing', 'desc' => 'Fill in plot details, upload photos, and attach ownership documents.', 'icon' => 'fa-file-upload'],
                ['num' => '3', 'title' => 'Verification Review', 'desc' => 'Our team reviews documents within 3–5 business days and assigns a trust badge.', 'icon' => 'fa-search'],
                ['num' => '4', 'title' => 'Go Live & Get Enquiries', 'desc' => 'Your listing goes live to thousands of buyers. Manage enquiries from your dashboard.', 'icon' => 'fa-bolt'],
            ];
            foreach ($steps as $step): ?>
            <div class="col-md-6 col-lg-3 text-center">
                <div class="step-circle mx-auto mb-3"><?= $step['num'] ?></div>
                <i class="fas <?= $step['icon'] ?> fs-3 text-gold mb-2"></i>
                <h5 class="fw-600"><?= $step['title'] ?></h5>
                <p class="text-muted small"><?= $step['desc'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- What Can You List -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="section-title"><?= _e('sell.types_title') ?></h2>
            <div class="section-divider mx-auto"></div>
        </div>
        <?php
        $types = Database::fetchAll('SELECT label_en, label_zh FROM listing_types WHERE is_active = 1 ORDER BY sort_order');
        ?>
        <div class="row justify-content-center g-3">
            <?php foreach ($types as $t): ?>
            <div class="col-sm-6 col-md-4 col-lg-3">
                <div class="text-center p-3 pg-card">
                    <i class="fas fa-mountain text-gold mb-2 fs-4"></i>
                    <div class="fw-600 small"><?= h($t['label_en']) ?></div>
                    <?php if ($t['label_zh']): ?>
                        <div class="text-muted" style="font-size:.8rem"><?= h($t['label_zh']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Pricing -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <h2 class="section-title"><?= _e('sell.pricing_title') ?></h2>
                <div class="section-divider mx-auto mb-4"></div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="pg-card p-4 h-100">
                            <div class="fs-4 fw-bold text-navy mb-1">Free</div>
                            <div class="small text-muted mb-3">Basic Listing</div>
                            <ul class="list-unstyled text-start small text-muted">
                                <li><i class="fas fa-check text-success me-2"></i>Submit 1 listing</li>
                                <li><i class="fas fa-check text-success me-2"></i>Up to 5 photos</li>
                                <li><i class="fas fa-check text-success me-2"></i>Verification badge</li>
                                <li><i class="fas fa-check text-success me-2"></i>Enquiry management</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="pg-card p-4 h-100 border-gold" style="border-color:var(--pg-gold)!important">
                            <div class="badge bg-warning text-dark mb-2">Popular</div>
                            <div class="fs-4 fw-bold text-navy mb-1">RM 99</div>
                            <div class="small text-muted mb-3">Featured 7 Days</div>
                            <ul class="list-unstyled text-start small text-muted">
                                <li><i class="fas fa-check text-success me-2"></i>Homepage featured spot</li>
                                <li><i class="fas fa-check text-success me-2"></i>Top search results</li>
                                <li><i class="fas fa-check text-success me-2"></i>Featured badge ribbon</li>
                                <li><i class="fas fa-check text-success me-2"></i>Priority support</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="pg-card p-4 h-100">
                            <div class="fs-4 fw-bold text-navy mb-1">RM 299</div>
                            <div class="small text-muted mb-3">Featured 30 Days</div>
                            <ul class="list-unstyled text-start small text-muted">
                                <li><i class="fas fa-check text-success me-2"></i>30-day featured placement</li>
                                <li><i class="fas fa-check text-success me-2"></i>Social media boost</li>
                                <li><i class="fas fa-check text-success me-2"></i>Premium badge</li>
                                <li><i class="fas fa-check text-success me-2"></i>Dedicated support</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <p class="small text-muted mt-3"><?= _e('sell.price_note') ?> <a href="<?= pg_url('contact.php') ?>"><?= _e('footer.contact') ?></a></p>
            </div>
        </div>
    </div>
</section>

<!-- Lead Capture Form -->
<section class="py-5" style="background:var(--pg-gold-pale)">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="pg-card p-4">
                    <h4 class="fw-600 text-center mb-1"><?= _e('sell.form_title') ?></h4>
                    <p class="text-center text-muted small mb-4"><?= _e('sell.form_subtitle') ?></p>

                    <form action="<?= pg_url('api/enquiry.php') ?>" method="POST" id="sellLeadForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="enquiry_type" value="general">
                        <input type="hidden" name="subject" value="Sell My Plot Inquiry">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label"><?= _e('detail.enquiry_name') ?></label>
                                <input type="text" name="contact_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= _e('auth.phone') ?></label>
                                <input type="tel" name="contact_phone" class="form-control" placeholder="+60 12-345 6789">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= _e('auth.email') ?></label>
                                <input type="email" name="contact_email" class="form-control" placeholder="you@example.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= _e('sell.listing_type') ?></label>
                                <select name="listing_type" class="form-select">
                                    <option value="">— <?= _e('misc.all') ?> —</option>
                                    <?php foreach ($types as $t): ?>
                                        <option><?= h(is_lang('zh') && $t['label_zh'] ? $t['label_zh'] : $t['label_en']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?= _e('sell.park_location') ?></label>
                                <input type="text" name="park_name" class="form-control" placeholder="e.g. Nirvana Semenyih, Selangor">
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?= _e('seller.asking_price') ?></label>
                                <input type="number" name="asking_price" class="form-control" placeholder="e.g. 15000">
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?= _e('sell.additional_notes') ?></label>
                                <textarea name="message" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-gold w-100">
                                    <i class="fas fa-paper-plane me-2"></i><?= _e('btn.submit') ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$extra_scripts = <<<'JS'
<script>
document.getElementById('sellLeadForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting…';
    const res = await PlotGold.postForm('/api/enquiry.php', new FormData(this));
    if (res.success) {
        this.innerHTML = '<div class="text-center py-4"><i class="fas fa-check-circle text-success fa-3x mb-3"></i><h5><?= addslashes(__('sell.success_msg')) ?></h5></div>';
    } else {
        PlotGold.toast(res.error || 'Something went wrong.', 'danger');
        btn.disabled = false;
        btn.innerHTML = orig;
    }
});
</script>
JS;
include INC_PATH . '/footer.php';
?>
