<?php
require_once __DIR__ . '/inc/bootstrap.php';

$page_title       = __('how.title');
$meta_description = 'Learn how PlotGold Malaysia works for buyers, sellers, and funeral service providers. Simple steps for transparent burial plot transactions.';
include INC_PATH . '/header.php';
include INC_PATH . '/nav.php';
?>

<!-- Breadcrumb -->
<nav class="bg-white border-bottom">
    <div class="container py-2">
        <ol class="breadcrumb pg-breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= pg_url() ?>"><?= _e('nav.home') ?></a></li>
            <li class="breadcrumb-item active"><?= _e('how.title') ?></li>
        </ol>
    </div>
</nav>

<!-- Hero -->
<section style="background:linear-gradient(135deg,#0D1B2A 0%,#1a2f4a 100%);color:#fff;padding:5rem 0 4rem;">
    <div class="container text-center">
        <div class="mb-3">
            <span style="background:rgba(200,160,60,.15);border:1px solid rgba(200,160,60,.4);color:var(--pg-gold);padding:.35rem 1rem;border-radius:2rem;font-size:.85rem;letter-spacing:.05em;">
                <?= _e('how.title') ?>
            </span>
        </div>
        <h1 class="display-5 fw-700 mb-3"><?= _e('how.title') ?></h1>
        <p class="lead mb-4" style="color:rgba(255,255,255,.75);max-width:580px;margin:0 auto 1.5rem;">
            <?= _e('how.subtitle') ?>
        </p>
        <!-- Tab nav anchors -->
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <a href="#buyers"    class="btn btn-outline-light btn-sm"><i class="fas fa-user me-1"></i><?= _e('how.for_buyers') ?></a>
            <a href="#sellers"   class="btn btn-outline-light btn-sm"><i class="fas fa-tag me-1"></i><?= _e('how.for_sellers') ?></a>
            <a href="#providers" class="btn btn-outline-light btn-sm"><i class="fas fa-briefcase me-1"></i><?= _e('how.for_providers') ?></a>
        </div>
    </div>
</section>

<!-- ── FOR BUYERS ───────────────────────────────────────────────────────── -->
<section id="buyers" class="py-5 bg-white">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge mb-2" style="background:var(--pg-gold-pale);color:var(--pg-gold);border:1px solid rgba(200,160,60,.3);font-size:.85rem;padding:.45em 1em;">
                <i class="fas fa-user me-1"></i><?= _e('how.for_buyers') ?>
            </span>
            <h2 class="h3 fw-700 text-navy"><?= is_lang('zh') ? '如何购买墓地' : 'How to Buy a Burial Plot' ?></h2>
            <p class="text-muted"><?= is_lang('zh') ? '从搜索到完成过户，全程四步走' : 'From search to completed transfer — four simple steps' ?></p>
        </div>

        <?php
        $buyerSteps = [
            ['num'=>'01','icon'=>'fa-search',          'title'=>__('how.buyer_s1_title'),'body'=>__('how.buyer_s1_body'),'btn_label'=>__('nav.browse'),       'btn_url'=>'browse_listings.php'],
            ['num'=>'02','icon'=>'fa-balance-scale',   'title'=>__('how.buyer_s2_title'),'body'=>__('how.buyer_s2_body'),'btn_label'=>is_lang('zh')?'比较房源':'Compare Listings','btn_url'=>'buyer/compare.php'],
            ['num'=>'03','icon'=>'fa-envelope',        'title'=>__('how.buyer_s3_title'),'body'=>__('how.buyer_s3_body'),'btn_label'=>null,                   'btn_url'=>null],
            ['num'=>'04','icon'=>'fa-file-signature',  'title'=>__('how.buyer_s4_title'),'body'=>__('how.buyer_s4_body'),'btn_label'=>null,                   'btn_url'=>null],
        ];
        ?>
        <div class="row g-4">
            <?php foreach ($buyerSteps as $i => $step): ?>
            <div class="col-md-6 col-lg-3">
                <div class="pg-card p-4 h-100 position-relative">
                    <!-- Step number -->
                    <div class="fw-700 mb-3" style="font-size:2.5rem;line-height:1;color:var(--pg-gold-pale);font-family:'Playfair Display',serif;">
                        <?= $step['num'] ?>
                    </div>
                    <div class="mb-3 d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;border-radius:50%;background:var(--pg-gold-pale);">
                        <i class="fas <?= $step['icon'] ?>" style="color:var(--pg-gold);"></i>
                    </div>
                    <h5 class="fw-700 text-navy mb-2"><?= h($step['title']) ?></h5>
                    <p class="text-muted small mb-3"><?= h($step['body']) ?></p>
                    <?php if ($step['btn_url']): ?>
                        <a href="<?= pg_url($step['btn_url']) ?>" class="btn btn-outline-gold btn-sm">
                            <?= h($step['btn_label']) ?> <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($i < count($buyerSteps) - 1): ?>
                        <div class="d-none d-lg-block position-absolute" style="right:-1.5rem;top:50%;transform:translateY(-50%);z-index:1;color:var(--pg-gold);font-size:1.25rem;">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4 text-center">
            <a href="<?= pg_url('browse_listings.php') ?>" class="btn btn-gold btn-lg me-2">
                <i class="fas fa-search me-2"></i><?= _e('nav.browse') ?>
            </a>
            <a href="<?= pg_url('register.php?type=buyer') ?>" class="btn btn-outline-gold btn-lg">
                <?= _e('nav.register') ?>
            </a>
        </div>
    </div>
</section>

<!-- Divider -->
<div style="background:#F8F9FA;height:4px;border-top:1px solid #e9ecef;border-bottom:1px solid #e9ecef;"></div>

<!-- ── FOR SELLERS ──────────────────────────────────────────────────────── -->
<section id="sellers" class="py-5" style="background:#F8F9FA;">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge mb-2" style="background:#e8f5e9;color:#2e7d32;border:1px solid #c8e6c9;font-size:.85rem;padding:.45em 1em;">
                <i class="fas fa-tag me-1"></i><?= _e('how.for_sellers') ?>
            </span>
            <h2 class="h3 fw-700 text-navy"><?= is_lang('zh') ? '如何出售您的墓地' : 'How to Sell Your Burial Plot' ?></h2>
            <p class="text-muted"><?= is_lang('zh') ? '从刊登到完成交易，轻松四步' : 'From listing to closed deal — four straightforward steps' ?></p>
        </div>

        <?php
        $sellerSteps = [
            ['num'=>'01','icon'=>'fa-plus-circle',   'title'=>__('how.seller_s1_title'),'body'=>__('how.seller_s1_body'),'btn_label'=>is_lang('zh')?'立即刊登':'List Now','btn_url'=>'register.php?type=seller'],
            ['num'=>'02','icon'=>'fa-shield-alt',    'title'=>__('how.seller_s2_title'),'body'=>__('how.seller_s2_body'),'btn_label'=>null,'btn_url'=>null],
            ['num'=>'03','icon'=>'fa-envelope-open', 'title'=>__('how.seller_s3_title'),'body'=>__('how.seller_s3_body'),'btn_label'=>null,'btn_url'=>null],
            ['num'=>'04','icon'=>'fa-handshake',     'title'=>__('how.seller_s4_title'),'body'=>__('how.seller_s4_body'),'btn_label'=>null,'btn_url'=>null],
        ];
        ?>
        <div class="row g-4">
            <?php foreach ($sellerSteps as $i => $step): ?>
            <div class="col-md-6 col-lg-3">
                <div class="pg-card p-4 h-100 position-relative bg-white">
                    <div class="fw-700 mb-3" style="font-size:2.5rem;line-height:1;color:#e8f5e9;font-family:'Playfair Display',serif;">
                        <?= $step['num'] ?>
                    </div>
                    <div class="mb-3 d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;border-radius:50%;background:#e8f5e9;">
                        <i class="fas <?= $step['icon'] ?>" style="color:#2e7d32;"></i>
                    </div>
                    <h5 class="fw-700 text-navy mb-2"><?= h($step['title']) ?></h5>
                    <p class="text-muted small mb-3"><?= h($step['body']) ?></p>
                    <?php if ($step['btn_url']): ?>
                        <a href="<?= pg_url($step['btn_url']) ?>" class="btn btn-sm btn-outline-secondary">
                            <?= h($step['btn_label']) ?> <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($i < count($sellerSteps) - 1): ?>
                        <div class="d-none d-lg-block position-absolute" style="right:-1.5rem;top:50%;transform:translateY(-50%);z-index:1;color:#2e7d32;font-size:1.25rem;">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Seller highlights -->
        <div class="row g-3 mt-3">
            <?php
            $highlights = is_lang('zh')
                ? [['fa-check','免费刊登，无隐藏费用'],['fa-users','触达数千名活跃买家'],['fa-star','认证房源询价量提升 3 倍']]
                : [['fa-check','Free listing, no hidden costs'],['fa-users','Reach thousands of active buyers'],['fa-star','Verified listings get 3× more enquiries']];
            foreach ($highlights as $h): ?>
            <div class="col-md-4 text-center">
                <div class="pg-card p-3 bg-white">
                    <i class="fas <?= $h[0] ?> mb-2" style="color:#2e7d32;font-size:1.2rem;"></i>
                    <div class="small fw-600 text-navy"><?= htmlspecialchars($h[1], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4 text-center">
            <a href="<?= pg_url('sell_plot.php') ?>" class="btn btn-gold btn-lg me-2">
                <i class="fas fa-plus-circle me-2"></i><?= _e('about.cta_sell') ?>
            </a>
            <a href="<?= pg_url('register.php?type=seller') ?>" class="btn btn-outline-secondary btn-lg">
                <?= _e('nav.register') ?>
            </a>
        </div>
    </div>
</section>

<!-- Divider -->
<div style="height:4px;border-top:1px solid #e9ecef;border-bottom:1px solid #e9ecef;"></div>

<!-- ── FOR PROVIDERS ─────────────────────────────────────────────────────── -->
<section id="providers" class="py-5 bg-white">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge mb-2" style="background:#e3f2fd;color:#1565c0;border:1px solid #bbdefb;font-size:.85rem;padding:.45em 1em;">
                <i class="fas fa-briefcase me-1"></i><?= _e('how.for_providers') ?>
            </span>
            <h2 class="h3 fw-700 text-navy"><?= is_lang('zh') ? '作为服务商如何加入' : 'How to Join as a Service Provider' ?></h2>
            <p class="text-muted"><?= is_lang('zh') ? '触达更多有需求的家庭，发展您的殡葬业务' : 'Reach more families in need and grow your funeral services business' ?></p>
        </div>

        <?php
        $providerSteps = [
            ['num'=>'01','icon'=>'fa-user-plus',     'title'=>__('how.provider_s1_title'),'body'=>__('how.provider_s1_body'),'btn_label'=>is_lang('zh')?'注册为服务商':'Register as Provider','btn_url'=>'register.php?type=provider'],
            ['num'=>'02','icon'=>'fa-inbox',         'title'=>__('how.provider_s2_title'),'body'=>__('how.provider_s2_body'),'btn_label'=>null,'btn_url'=>null],
            ['num'=>'03','icon'=>'fa-file-invoice',  'title'=>__('how.provider_s3_title'),'body'=>__('how.provider_s3_body'),'btn_label'=>null,'btn_url'=>null],
            ['num'=>'04','icon'=>'fa-chart-line',    'title'=>__('how.provider_s4_title'),'body'=>__('how.provider_s4_body'),'btn_label'=>null,'btn_url'=>null],
        ];
        ?>
        <div class="row g-4">
            <?php foreach ($providerSteps as $i => $step): ?>
            <div class="col-md-6 col-lg-3">
                <div class="pg-card p-4 h-100 position-relative">
                    <div class="fw-700 mb-3" style="font-size:2.5rem;line-height:1;color:#e3f2fd;font-family:'Playfair Display',serif;">
                        <?= $step['num'] ?>
                    </div>
                    <div class="mb-3 d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;border-radius:50%;background:#e3f2fd;">
                        <i class="fas <?= $step['icon'] ?>" style="color:#1565c0;"></i>
                    </div>
                    <h5 class="fw-700 text-navy mb-2"><?= h($step['title']) ?></h5>
                    <p class="text-muted small mb-3"><?= h($step['body']) ?></p>
                    <?php if ($step['btn_url']): ?>
                        <a href="<?= pg_url($step['btn_url']) ?>" class="btn btn-sm" style="background:#1565c0;color:#fff;">
                            <?= h($step['btn_label']) ?> <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($i < count($providerSteps) - 1): ?>
                        <div class="d-none d-lg-block position-absolute" style="right:-1.5rem;top:50%;transform:translateY(-50%);z-index:1;color:#1565c0;font-size:1.25rem;">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4 text-center">
            <a href="<?= pg_url('register.php?type=provider') ?>" class="btn btn-lg me-2" style="background:#1565c0;color:#fff;">
                <i class="fas fa-user-plus me-2"></i><?= _e('how.for_providers') ?>
            </a>
            <a href="<?= pg_url('providers.php') ?>" class="btn btn-outline-secondary btn-lg">
                <?= _e('footer.providers') ?>
            </a>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="py-5" style="background:#F8F9FA;">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="h3 fw-700 text-navy"><?= _e('how.faq_title') ?></h2>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion" id="howFaq">
                    <?php
                    $faqs = [
                        ['q'=>__('how.faq1_q'),'a'=>__('how.faq1_a')],
                        ['q'=>__('how.faq2_q'),'a'=>__('how.faq2_a')],
                        ['q'=>__('how.faq3_q'),'a'=>__('how.faq3_a')],
                        ['q'=>__('how.faq4_q'),'a'=>__('how.faq4_a')],
                        ['q'=>__('how.faq5_q'),'a'=>__('how.faq5_a')],
                    ];
                    foreach ($faqs as $i => $faq): ?>
                    <div class="accordion-item border-0 mb-2 pg-card">
                        <h3 class="accordion-header">
                            <button class="accordion-button <?= $i > 0 ? 'collapsed' : '' ?> fw-600 bg-transparent text-navy"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#faqItem<?= $i ?>"
                                    aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>">
                                <?= h($faq['q']) ?>
                            </button>
                        </h3>
                        <div id="faqItem<?= $i ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>" data-bs-parent="#howFaq">
                            <div class="accordion-body text-muted">
                                <?= h($faq['a']) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="text-center mt-4">
                    <p class="text-muted small">
                        <?= is_lang('zh') ? '还有其他问题？' : 'Have more questions?' ?>
                        <a href="<?= pg_url('contact.php') ?>" class="text-decoration-none fw-600" style="color:var(--pg-gold);">
                            <?= _e('footer.contact') ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Final CTA -->
<section style="background:linear-gradient(135deg,#C8A03C 0%,#a07828 100%);color:#fff;padding:4rem 0;">
    <div class="container text-center">
        <h2 class="h3 fw-700 mb-3"><?= _e('how.cta_title') ?></h2>
        <p class="mb-4" style="opacity:.9;max-width:500px;margin:0 auto 1.5rem;"><?= _e('how.cta_body') ?></p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="<?= pg_url('browse_listings.php') ?>" class="btn btn-light btn-lg fw-600">
                <i class="fas fa-search me-2"></i><?= _e('about.cta_browse') ?>
            </a>
            <a href="<?= pg_url('sell_plot.php') ?>" class="btn btn-lg fw-600" style="background:rgba(255,255,255,.15);color:#fff;border:2px solid rgba(255,255,255,.5);">
                <i class="fas fa-plus-circle me-2"></i><?= _e('about.cta_sell') ?>
            </a>
            <a href="<?= pg_url('register.php?type=provider') ?>" class="btn btn-lg fw-600" style="background:rgba(255,255,255,.15);color:#fff;border:2px solid rgba(255,255,255,.5);">
                <i class="fas fa-briefcase me-2"></i><?= _e('how.for_providers') ?>
            </a>
        </div>
    </div>
</section>

<?php include INC_PATH . '/footer.php'; ?>
