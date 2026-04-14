<?php
require_once __DIR__ . '/inc/bootstrap.php';

$page_title       = __('about.title');
$meta_description = 'Learn about PlotGold Malaysia — our mission, values, and commitment to transparent, dignified burial plot transactions across Malaysia.';
include INC_PATH . '/header.php';
include INC_PATH . '/nav.php';
?>

<!-- Breadcrumb -->
<nav class="bg-white border-bottom">
    <div class="container py-2">
        <ol class="breadcrumb pg-breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= pg_url() ?>"><?= _e('nav.home') ?></a></li>
            <li class="breadcrumb-item active"><?= _e('about.title') ?></li>
        </ol>
    </div>
</nav>

<!-- Hero -->
<section style="background: linear-gradient(135deg, #0D1B2A 0%, #1a2f4a 100%); color:#fff; padding:5rem 0 4rem;">
    <div class="container text-center">
        <div class="mb-3">
            <span style="background:rgba(200,160,60,.15);border:1px solid rgba(200,160,60,.4);color:var(--pg-gold);padding:.35rem 1rem;border-radius:2rem;font-size:.85rem;letter-spacing:.05em;">
                <?= _e('footer.about') ?>
            </span>
        </div>
        <h1 class="display-5 fw-700 mb-3"><?= _e('about.title') ?></h1>
        <p class="lead mb-0" style="color:rgba(255,255,255,.75);max-width:600px;margin:0 auto;">
            <?= _e('about.subtitle') ?>
        </p>
    </div>
</section>

<!-- Mission & Story -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <div class="d-inline-flex align-items-center gap-2 mb-3" style="color:var(--pg-gold);">
                    <i class="fas fa-seedling"></i>
                    <span class="fw-600 text-uppercase" style="font-size:.8rem;letter-spacing:.08em;"><?= _e('about.mission_title') ?></span>
                </div>
                <h2 class="h3 fw-700 text-navy mb-3"><?= _e('about.mission_title') ?></h2>
                <p class="text-muted lh-lg"><?= _e('about.mission_body') ?></p>
                <div class="d-flex gap-3 mt-4">
                    <a href="<?= pg_url('browse_listings.php') ?>" class="btn btn-gold"><?= _e('about.cta_browse') ?></a>
                    <a href="<?= pg_url('how_it_works.php') ?>" class="btn btn-outline-secondary"><?= _e('home.how_title') ?></a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="pg-card p-4" style="border-left:4px solid var(--pg-gold);">
                    <div class="d-inline-flex align-items-center gap-2 mb-3" style="color:var(--pg-gold);">
                        <i class="fas fa-book-open"></i>
                        <span class="fw-600 text-uppercase" style="font-size:.8rem;letter-spacing:.08em;"><?= _e('about.story_title') ?></span>
                    </div>
                    <h3 class="h5 fw-700 text-navy mb-3"><?= _e('about.story_title') ?></h3>
                    <p class="text-muted lh-lg mb-0"><?= _e('about.story_body') ?></p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Values -->
<section class="py-5" style="background:#F8F9FA;">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="h3 fw-700 text-navy"><?= is_lang('zh') ? '我们的核心价值' : 'Our Core Values' ?></h2>
            <p class="text-muted"><?= is_lang('zh') ? '指引 PlotGold 每一个决策的原则' : 'The principles that guide every decision we make at PlotGold' ?></p>
        </div>
        <div class="row g-4">
            <?php
            $values = [
                ['icon' => 'fa-eye',          'color' => '#C8A03C', 'title' => __('about.value1_title'), 'body' => __('about.value1_body')],
                ['icon' => 'fa-shield-alt',   'color' => '#2563EB', 'title' => __('about.value2_title'), 'body' => __('about.value2_body')],
                ['icon' => 'fa-heart',        'color' => '#E11D48', 'title' => __('about.value3_title'), 'body' => __('about.value3_body')],
                ['icon' => 'fa-users',        'color' => '#059669', 'title' => __('about.value4_title'), 'body' => __('about.value4_body')],
            ];
            foreach ($values as $v): ?>
            <div class="col-sm-6 col-lg-3">
                <div class="pg-card p-4 h-100 text-center">
                    <div class="mb-3 mx-auto d-flex align-items-center justify-content-center"
                         style="width:56px;height:56px;border-radius:50%;background:<?= $v['color'] ?>1a;">
                        <i class="fas <?= $v['icon'] ?> fa-lg" style="color:<?= $v['color'] ?>;"></i>
                    </div>
                    <h5 class="fw-700 text-navy mb-2"><?= h($v['title']) ?></h5>
                    <p class="text-muted small mb-0"><?= h($v['body']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Who We Serve -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-5">
                <h2 class="h3 fw-700 text-navy mb-3"><?= _e('about.serve_title') ?></h2>
                <p class="text-muted lh-lg mb-4"><?= _e('about.serve_body') ?></p>
                <div class="d-flex flex-wrap gap-2">
                    <?php
                    $communities = is_lang('zh')
                        ? ['华人', '穆斯林', '基督徒', '印度教', '佛教', '多元宗教']
                        : ['Chinese', 'Muslim', 'Christian', 'Hindu', 'Buddhist', 'Multi-faith'];
                    foreach ($communities as $c): ?>
                        <span class="badge" style="background:var(--pg-gold-pale);color:var(--pg-gold);border:1px solid rgba(200,160,60,.3);font-size:.85rem;padding:.45em .9em;">
                            <?= h($c) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4 d-flex flex-wrap gap-2">
                    <?php
                    $states = ['Kuala Lumpur', 'Selangor', 'Putrajaya', 'Klang Valley'];
                    foreach ($states as $s): ?>
                        <span class="badge bg-light text-muted border" style="font-size:.85rem;padding:.45em .9em;">
                            <i class="fas fa-map-marker-alt me-1" style="color:var(--pg-gold);"></i><?= h($s) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="row g-3">
                    <?php
                    $plotTypes = is_lang('zh')
                        ? [
                            ['icon' => 'fa-mountain',   'label' => '地面墓地',   'desc' => '独立墓穴及家庭墓地'],
                            ['icon' => 'fa-building',   'label' => '骨灰龛',     'desc' => '室内及户外骨灰龛位'],
                            ['icon' => 'fa-users',      'label' => '家庭墓地',   'desc' => '多穴家庭墓地区'],
                            ['icon' => 'fa-leaf',       'label' => '生态墓地',   'desc' => '草坪及生态葬选项'],
                          ]
                        : [
                            ['icon' => 'fa-mountain',   'label' => 'Burial Plots',      'desc' => 'Individual and garden lots'],
                            ['icon' => 'fa-building',   'label' => 'Columbarium',       'desc' => 'Indoor and outdoor niches'],
                            ['icon' => 'fa-users',      'label' => 'Family Lots',        'desc' => 'Multi-plot family sections'],
                            ['icon' => 'fa-leaf',       'label' => 'Green Burial',       'desc' => 'Lawn and eco options'],
                          ];
                    foreach ($plotTypes as $pt): ?>
                    <div class="col-6">
                        <div class="pg-card p-3 h-100 d-flex gap-3 align-items-start">
                            <div class="d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:44px;height:44px;border-radius:10px;background:var(--pg-gold-pale);">
                                <i class="fas <?= $pt['icon'] ?>" style="color:var(--pg-gold);"></i>
                            </div>
                            <div>
                                <div class="fw-600 text-navy small"><?= h($pt['label']) ?></div>
                                <div class="text-muted" style="font-size:.78rem;"><?= h($pt['desc']) ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Banner -->
<section style="background:linear-gradient(135deg,#C8A03C 0%,#a07828 100%);color:#fff;padding:3.5rem 0;">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="h4 fw-700 mb-0"><?= _e('about.stats_title') ?></h2>
        </div>
        <div class="row g-4 text-center">
            <?php
            $stats = [
                ['icon' => 'fa-list-alt',  'label' => __('about.stat_listings'),  'value' => '500+'],
                ['icon' => 'fa-tree',      'label' => __('about.stat_parks'),     'value' => '30+'],
                ['icon' => 'fa-briefcase','label' => __('about.stat_providers'),  'value' => '50+'],
                ['icon' => 'fa-map',       'label' => __('about.stat_states'),    'value' => '5'],
            ];
            foreach ($stats as $st): ?>
            <div class="col-6 col-md-3">
                <div class="mb-2">
                    <i class="fas <?= $st['icon'] ?> fa-2x" style="opacity:.8;"></i>
                </div>
                <div class="display-6 fw-700"><?= h($st['value']) ?></div>
                <div class="small" style="opacity:.85;"><?= h($st['label']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Why Choose PlotGold -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="h3 fw-700 text-navy"><?= is_lang('zh') ? '为什么选择 PlotGold？' : 'Why Choose PlotGold?' ?></h2>
        </div>
        <div class="row g-4">
            <?php
            $reasons = is_lang('zh')
                ? [
                    ['icon' => 'fa-check-circle', 'title' => '认证房源',       'body' => '每个房源均经我们团队审核，核实产权文件及园区详情。'],
                    ['icon' => 'fa-tag',          'title' => '价格透明',       'body' => '价格公开显示，买卖双方均可自信谈判。'],
                    ['icon' => 'fa-lock',         'title' => '安全交易',       'body' => '受保护的消息系统及有据可查的过户流程保障双方利益。'],
                    ['icon' => 'fa-phone-alt',    'title' => '24/7 支持',      'body' => '哀伤支持专线全天候开放，处理紧急家庭需求。'],
                    ['icon' => 'fa-language',     'title' => '双语服务',       'body' => '平台支持中英双语，服务多元社群。'],
                    ['icon' => 'fa-star',         'title' => '认证服务商',     'body' => '经审核的殡葬服务商，提供有竞争力的报价。'],
                  ]
                : [
                    ['icon' => 'fa-check-circle', 'title' => 'Verified Listings',    'body' => 'Every listing is reviewed by our team to verify title documents and park details.'],
                    ['icon' => 'fa-tag',          'title' => 'Transparent Pricing',   'body' => 'Prices are displayed openly so both buyers and sellers can negotiate with confidence.'],
                    ['icon' => 'fa-lock',         'title' => 'Secure Transactions',   'body' => 'Protected messaging and a documented transfer process protect all parties.'],
                    ['icon' => 'fa-phone-alt',    'title' => '24/7 Support',          'body' => 'Our bereavement support line is always available for urgent family needs.'],
                    ['icon' => 'fa-language',     'title' => 'Bilingual Platform',    'body' => 'Full support in English and Mandarin to serve diverse communities.'],
                    ['icon' => 'fa-star',         'title' => 'Vetted Providers',      'body' => 'Verified funeral service providers offering competitive, itemised quotes.'],
                  ];
            foreach ($reasons as $r): ?>
            <div class="col-md-6 col-lg-4">
                <div class="d-flex gap-3">
                    <div class="flex-shrink-0 mt-1">
                        <i class="fas <?= $r['icon'] ?> fa-lg" style="color:var(--pg-gold);"></i>
                    </div>
                    <div>
                        <h6 class="fw-700 text-navy mb-1"><?= h($r['title']) ?></h6>
                        <p class="text-muted small mb-0"><?= h($r['body']) ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="py-5" style="background:#F8F9FA;">
    <div class="container">
        <div class="pg-card p-5 text-center" style="border-top:4px solid var(--pg-gold);">
            <h2 class="h3 fw-700 text-navy mb-3"><?= _e('about.cta_title') ?></h2>
            <p class="text-muted mb-4"><?= _e('about.cta_body') ?></p>
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <a href="<?= pg_url('browse_listings.php') ?>" class="btn btn-gold btn-lg">
                    <i class="fas fa-search me-2"></i><?= _e('about.cta_browse') ?>
                </a>
                <a href="<?= pg_url('sell_plot.php') ?>" class="btn btn-outline-gold btn-lg">
                    <i class="fas fa-plus-circle me-2"></i><?= _e('about.cta_sell') ?>
                </a>
                <a href="<?= pg_url('contact.php') ?>" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-envelope me-2"></i><?= _e('footer.contact') ?>
                </a>
            </div>
        </div>
    </div>
</section>

<?php include INC_PATH . '/footer.php'; ?>
