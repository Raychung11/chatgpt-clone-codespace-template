<?php
require_once __DIR__ . '/inc/bootstrap.php';

// Featured listings
$featuredListings = Database::fetchAll(
    'SELECT l.*, lt.label_en AS type_label_en, lt.label_zh AS type_label_zh,
            mp.name AS park_name, mp.city
     FROM listings l
     LEFT JOIN listing_types lt ON lt.id = l.listing_type_id
     LEFT JOIN memorial_parks mp ON mp.id = l.park_id
     WHERE l.status = ? AND l.is_featured = 1
     ORDER BY l.listed_at DESC LIMIT 6',
    [LISTING_ACTIVE]
);

// Recent listings
$recentListings = Database::fetchAll(
    'SELECT l.*, lt.label_en AS type_label_en, lt.label_zh AS type_label_zh,
            mp.name AS park_name, mp.city,
            lm.file_path AS primary_image
     FROM listings l
     LEFT JOIN listing_types lt ON lt.id = l.listing_type_id
     LEFT JOIN memorial_parks mp ON mp.id = l.park_id
     LEFT JOIN listing_media lm ON lm.listing_id = l.id AND lm.is_primary = 1
     WHERE l.status = ?
     ORDER BY l.listed_at DESC LIMIT 8',
    [LISTING_ACTIVE]
);

// Memorial parks (featured)
$parks = Database::fetchAll(
    'SELECT * FROM memorial_parks WHERE is_active = 1 ORDER BY is_featured DESC, name ASC LIMIT 4'
);

// Listing types for search bar (both labels)
$listingTypes = Database::fetchAll(
    'SELECT slug, label_en, label_zh FROM listing_types WHERE is_active = 1 ORDER BY sort_order'
);

// Stats
$totalListings  = (int)(Database::fetchOne('SELECT COUNT(*) c FROM listings WHERE status = ?', [LISTING_ACTIVE])['c'] ?? 0);
$totalParks     = (int)(Database::fetchOne('SELECT COUNT(*) c FROM memorial_parks WHERE is_active = 1')['c'] ?? 0);
$totalProviders = (int)(Database::fetchOne('SELECT COUNT(*) c FROM providers WHERE approval_status = ?', ['approved'])['c'] ?? 0);

$page_title       = get_setting('site_name', 'PlotGold Malaysia');
$meta_description = get_setting('site_tagline') . ' — Malaysia\'s trusted marketplace for verified resale burial plots, columbarium niches, and dignified funeral planning.';
include INC_PATH . '/header.php';
include INC_PATH . '/nav.php';
?>

<!-- Structured Data: WebSite -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "name": "PlotGold Malaysia",
  "url": "<?= BASE_URL ?>",
  "description": "<?= h($meta_description) ?>",
  "potentialAction": {
    "@type": "SearchAction",
    "target": "<?= BASE_URL ?>/browse_listings.php?q={search_term_string}",
    "query-input": "required name=search_term_string"
  }
}
</script>

<!-- ── HERO ─────────────────────────────────────────────────────────── -->
<section class="pg-hero">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <div class="d-inline-flex align-items-center gap-2 mb-3 px-3 py-1 rounded-pill" style="background:rgba(255,255,255,.1);font-size:.82rem;color:rgba(255,255,255,.8)">
                    <span class="badge bg-warning text-dark">New</span>
                    <?= is_lang('zh') ? '服务巴生谷地区家庭' : 'Trusted by families across Klang Valley' ?>
                </div>
                <h1 class="text-white mb-3"><?= _e('home.hero_title') ?></h1>
                <p class="lead text-white mb-4" style="opacity:.88"><?= _e('home.hero_subtitle') ?></p>

                <!-- Search Bar -->
                <div class="search-bar">
                    <form action="<?= pg_url('browse_listings.php') ?>" method="GET">
                        <div class="row g-0 align-items-center">
                            <div class="col-12 col-md-4 border-end">
                                <select name="type" class="form-select border-0">
                                    <option value=""><?= _e('misc.all') ?></option>
                                    <?php foreach ($listingTypes as $t): ?>
                                        <option value="<?= h($t['slug']) ?>"><?= lang_label($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-4 border-end">
                                <select name="state" class="form-select border-0">
                                    <option value=""><?= _e('location.all_states') ?></option>
                                    <?php foreach (MY_STATES as $s): ?>
                                        <option value="<?= h($s) ?>"><?= h($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="d-flex align-items-center px-2">
                                    <input type="text" name="q" class="form-control border-0" placeholder="<?= _e('home.hero_placeholder') ?>">
                                    <button type="submit" class="btn btn-gold ms-2 px-4">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="mt-3 d-flex flex-wrap gap-2">
                    <a href="<?= pg_url('browse_listings.php?type=burial-plot') ?>" class="badge rounded-pill px-3 py-2 text-white border border-white border-opacity-25" style="background:rgba(255,255,255,.12);font-size:.82rem">
                        <?= is_lang('zh') ? '土葬墓地' : 'Burial Plots' ?>
                    </a>
                    <a href="<?= pg_url('browse_listings.php?type=columbarium') ?>" class="badge rounded-pill px-3 py-2 text-white border border-white border-opacity-25" style="background:rgba(255,255,255,.12);font-size:.82rem">
                        <?= is_lang('zh') ? '骨灰龛位' : 'Columbarium Niches' ?>
                    </a>
                    <a href="<?= pg_url('browse_listings.php?state=Selangor') ?>" class="badge rounded-pill px-3 py-2 text-white border border-white border-opacity-25" style="background:rgba(255,255,255,.12);font-size:.82rem">
                        <?= is_lang('zh') ? '雪兰莪' : 'Selangor' ?>
                    </a>
                    <a href="<?= pg_url('browse_listings.php?urgency=urgent') ?>" class="badge rounded-pill px-3 py-2 text-danger border" style="background:rgba(255,255,255,.1);font-size:.82rem">
                        <i class="fas fa-bolt me-1"></i><?= _e('urgency.urgent') ?>
                    </a>
                </div>
            </div>

            <div class="col-lg-5 d-none d-lg-block text-center">
                <div class="p-4" style="background:rgba(255,255,255,.06);border-radius:16px;border:1px solid rgba(255,255,255,.1)">
                    <p class="text-white-50 small mb-3 text-uppercase ls-1"><?= is_lang('zh') ? '当前可用房源' : 'Listings available now' ?></p>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="p-3 rounded-3" style="background:rgba(255,255,255,.08)">
                                <div class="fs-2 fw-bold text-warning"><?= $totalListings ?></div>
                                <div class="text-white-50 small"><?= _e('home.stats_listings') ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3" style="background:rgba(255,255,255,.08)">
                                <div class="fs-2 fw-bold text-warning"><?= $totalParks ?></div>
                                <div class="text-white-50 small"><?= _e('home.stats_parks') ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3" style="background:rgba(255,255,255,.08)">
                                <div class="fs-2 fw-bold text-warning"><?= $totalProviders ?></div>
                                <div class="text-white-50 small"><?= _e('home.stats_providers') ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3" style="background:rgba(255,255,255,.08)">
                                <div class="fs-2 fw-bold text-warning"><?= is_lang('zh') ? '免费' : 'Free' ?></div>
                                <div class="text-white-50 small"><?= is_lang('zh') ? '免费浏览' : 'To Browse' ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── RECENT LISTINGS ───────────────────────────────────────────────── -->
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h2 class="section-title mb-1"><?= _e('home.recent_title') ?></h2>
                <div class="section-divider"></div>
            </div>
            <a href="<?= pg_url('browse_listings.php') ?>" class="btn btn-outline-gold btn-sm d-none d-md-inline"><?= _e('btn.view_all') ?></a>
        </div>

        <?php if ($recentListings): ?>
        <div class="row g-3">
            <?php foreach ($recentListings as $listing): ?>
            <div class="col-sm-6 col-lg-3">
                <?php include INC_PATH . '/partials/listing_card.php'; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="<?= pg_url('browse_listings.php') ?>" class="btn btn-outline-gold">
                <i class="fas fa-th-large me-2"></i><?= _e('nav.browse_all') ?>
            </a>
        </div>
        <?php else: ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-search fa-2x mb-3"></i>
            <p><?= is_lang('zh') ? '即将有房源上架。' : 'Listings are coming soon.' ?> <a href="<?= pg_url('sell_plot.php') ?>"><?= _e('nav.sell') ?></a></p>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ── HOW IT WORKS ──────────────────────────────────────────────────── -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title"><?= _e('home.how_title') ?></h2>
            <div class="section-divider mx-auto"></div>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-3 text-center">
                <div class="step-circle mb-3">1</div>
                <h5><?= _e('home.how_s1_title') ?></h5>
                <p class="text-muted small"><?= _e('home.how_s1_body') ?></p>
            </div>
            <div class="col-md-6 col-lg-3 text-center">
                <div class="step-circle mb-3">2</div>
                <h5><?= _e('home.how_s2_title') ?></h5>
                <p class="text-muted small"><?= _e('home.how_s2_body') ?></p>
            </div>
            <div class="col-md-6 col-lg-3 text-center">
                <div class="step-circle mb-3">3</div>
                <h5><?= _e('home.how_s3_title') ?></h5>
                <p class="text-muted small"><?= _e('home.how_s3_body') ?></p>
            </div>
            <div class="col-md-6 col-lg-3 text-center">
                <div class="step-circle mb-3">4</div>
                <h5><?= is_lang('zh') ? '全程协助过户' : 'Transfer Supported' ?></h5>
                <p class="text-muted small"><?= is_lang('zh') ? '我们提供文件清单和园区联络，全程引导您完成过户。' : 'We guide you through the transfer process with document checklists and park liaisons.' ?></p>
            </div>
        </div>
    </div>
</section>

<!-- ── MEMORIAL PARKS ─────────────────────────────────────────────────── -->
<?php if ($parks): ?>
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h2 class="section-title mb-1"><?= _e('home.parks_title') ?></h2>
                <div class="section-divider"></div>
                <p class="text-muted small"><?= _e('home.parks_subtitle') ?></p>
            </div>
        </div>
        <div class="row g-3">
            <?php foreach ($parks as $park): ?>
            <div class="col-sm-6 col-lg-3">
                <a href="<?= park_url($park['slug']) ?>" class="text-decoration-none">
                    <div class="pg-card park-card">
                        <div class="park-image d-flex align-items-center justify-content-center text-muted" style="background:var(--pg-bg)">
                            <i class="fas fa-tree fa-3x" style="opacity:.2"></i>
                        </div>
                        <div class="p-3">
                            <h6 class="fw-600 mb-1 text-navy"><?= h($park['name']) ?></h6>
                            <p class="small text-muted mb-2"><i class="fas fa-map-marker-alt me-1"></i><?= h($park['city']) ?>, <?= h($park['state']) ?></p>
                            <span class="listing-type-tag"><?= _e('btn.view') ?></span>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ── DUAL CTA ───────────────────────────────────────────────────────── -->
<section class="py-5" style="background: var(--pg-gold-pale);">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="bg-white pg-card p-4 h-100">
                    <div class="trust-icon mb-3"><i class="fas fa-tag"></i></div>
                    <h4 class="fw-600"><?= _e('home.sell_cta_title') ?></h4>
                    <p class="text-muted"><?= _e('home.sell_cta_body') ?></p>
                    <ul class="list-unstyled text-muted small mb-3">
                        <?php
                        $sellPoints = is_lang('zh')
                            ? ['免费提交房源', '全程认证指引', '通过认证后获得信任徽章', '询价管理控制台']
                            : ['Free listing submission', 'Guided verification process', 'Trust badge after verification', 'Enquiry management dashboard'];
                        foreach ($sellPoints as $pt): ?>
                        <li><i class="fas fa-check text-success me-2"></i><?= h($pt) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="<?= pg_url('sell_plot.php') ?>" class="btn btn-gold"><?= _e('nav.sell') ?></a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="bg-white pg-card p-4 h-100">
                    <div class="trust-icon mb-3"><i class="fas fa-clipboard-list"></i></div>
                    <h4 class="fw-600"><?= _e('home.plan_cta_title') ?></h4>
                    <p class="text-muted"><?= _e('home.plan_cta_body') ?></p>
                    <ul class="list-unstyled text-muted small mb-3">
                        <?php
                        $planPoints = is_lang('zh')
                            ? ['逐项选择并定价服务', '对比服务商', '保存并下载规划', '向服务商申请报价']
                            : ['Choose and price each service', 'Compare service providers', 'Save and download your plan', 'Request quotes from providers'];
                        foreach ($planPoints as $pt): ?>
                        <li><i class="fas fa-check text-success me-2"></i><?= h($pt) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="<?= pg_url('diy_funeral_planner.php') ?>" class="btn btn-navy"><?= _e('nav.plan') ?></a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── URGENT HELP ────────────────────────────────────────────────────── -->
<section class="py-4" style="background: var(--pg-navy);">
    <div class="container">
        <div class="row align-items-center text-white">
            <div class="col-md-8">
                <h4 class="mb-1"><i class="fas fa-hands-helping text-warning me-2"></i><?= _e('home.urgent_title') ?></h4>
                <p class="mb-0 text-white-50 small"><?= _e('home.urgent_body') ?></p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="<?= whatsapp_link(__('home.urgent_title')) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp me-2">
                    <i class="fab fa-whatsapp me-1"></i><?= _e('home.urgent_btn') ?>
                </a>
                <a href="<?= pg_url('request_quote.php?mode=urgent') ?>" class="btn btn-outline-light">
                    <?= _e('nav.urgent') ?>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ── FAQ PREVIEW ────────────────────────────────────────────────────── -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4 mb-lg-0">
                <h2 class="section-title"><?= _e('home.faq_title') ?></h2>
                <div class="section-divider"></div>
                <p class="text-muted"><?= is_lang('zh') ? '关于墓地转让及丧葬规划的常见问题解答。' : 'Answers to the most frequently asked questions about burial plot resale and funeral planning.' ?></p>
                <a href="<?= pg_url('faq.php') ?>" class="btn btn-outline-gold"><?= _e('btn.view_all') ?></a>
            </div>
            <div class="col-lg-8">
                <div class="accordion" id="homeFaq">
                    <?php
                    $faqs = Database::fetchAll('SELECT question, answer FROM faqs WHERE is_active = 1 ORDER BY sort_order LIMIT 5');
                    foreach ($faqs as $i => $faq):
                        $colId = 'faq' . $i;
                    ?>
                    <div class="accordion-item border-0 border-bottom">
                        <h2 class="accordion-header" id="h<?= $colId ?>">
                            <button class="accordion-button collapsed px-0 py-3 bg-transparent shadow-none fw-500" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#<?= $colId ?>">
                                <?= h($faq['question']) ?>
                            </button>
                        </h2>
                        <div id="<?= $colId ?>" class="accordion-collapse collapse" data-bs-parent="#homeFaq">
                            <div class="accordion-body px-0 pt-0 pb-3 text-muted small">
                                <?= h($faq['answer']) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- FAQ Schema Markup -->
                <script type="application/ld+json">
                {
                  "@context": "https://schema.org",
                  "@type": "FAQPage",
                  "mainEntity": [
                    <?php foreach ($faqs as $i => $faq): ?>
                    {
                      "@type": "Question",
                      "name": <?= json_encode($faq['question']) ?>,
                      "acceptedAnswer": {
                        "@type": "Answer",
                        "text": <?= json_encode($faq['answer']) ?>
                      }
                    }<?= $i < count($faqs) - 1 ? ',' : '' ?>
                    <?php endforeach; ?>
                  ]
                }
                </script>
            </div>
        </div>
    </div>
</section>

<?php include INC_PATH . '/footer.php'; ?>
