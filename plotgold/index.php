<?php
require_once __DIR__ . '/inc/bootstrap.php';

// Featured listings
$featuredListings = Database::fetchAll(
    'SELECT l.*, lt.label_en AS type_label, mp.name AS park_name, mp.city
     FROM listings l
     LEFT JOIN listing_types lt ON lt.id = l.listing_type_id
     LEFT JOIN memorial_parks mp ON mp.id = l.park_id
     WHERE l.status = ? AND l.is_featured = 1
     ORDER BY l.listed_at DESC LIMIT 6',
    [LISTING_ACTIVE]
);

// Recent listings
$recentListings = Database::fetchAll(
    'SELECT l.*, lt.label_en AS type_label, mp.name AS park_name, mp.city,
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

// Stats
$totalListings  = (int)(Database::fetchOne('SELECT COUNT(*) c FROM listings WHERE status = ?', [LISTING_ACTIVE])['c'] ?? 0);
$totalParks     = (int)(Database::fetchOne('SELECT COUNT(*) c FROM memorial_parks WHERE is_active = 1')['c'] ?? 0);
$totalProviders = (int)(Database::fetchOne('SELECT COUNT(*) c FROM providers WHERE approval_status = ?', ['approved'])['c'] ?? 0);

$page_title       = get_setting('site_name', 'PlotGold Malaysia');
$meta_description = get_setting('site_tagline') . ' — Malaysia\'s trusted marketplace for verified resale burial plots, columbarium niches, and dignified funeral planning.';
include INC_PATH . '/header.php';
include INC_PATH . '/nav.php';
?>

<!-- Structured Data: Local Business -->
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
                    Trusted by families across Klang Valley
                </div>
                <h1 class="text-white mb-3">
                    Malaysia's Trusted<br>
                    <span style="color:var(--pg-gold-light)">Burial Plot</span> Marketplace
                </h1>
                <p class="lead text-white mb-4" style="opacity:.88">
                    Buy, sell, and compare verified resale burial plots, family lots, and columbarium niches.
                    Plan with dignity — on your terms.
                </p>

                <!-- Search Bar -->
                <div class="search-bar">
                    <form action="<?= pg_url('browse_listings.php') ?>" method="GET">
                        <div class="row g-0 align-items-center">
                            <div class="col-12 col-md-4 border-end">
                                <select name="type" class="form-select border-0">
                                    <option value="">All Types</option>
                                    <?php
                                    $types = Database::fetchAll('SELECT slug, label_en FROM listing_types WHERE is_active = 1 ORDER BY sort_order');
                                    foreach ($types as $t): ?>
                                        <option value="<?= h($t['slug']) ?>"><?= h($t['label_en']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-4 border-end">
                                <select name="state" class="form-select border-0">
                                    <option value="">Any State</option>
                                    <?php foreach (MY_STATES as $s): ?>
                                        <option value="<?= h($s) ?>"><?= h($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="d-flex align-items-center px-2">
                                    <input type="text" name="q" class="form-control border-0" placeholder="Search park, city…">
                                    <button type="submit" class="btn btn-gold ms-2 px-4">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="mt-3 d-flex flex-wrap gap-2">
                    <a href="<?= pg_url('browse_listings.php?type=burial-plot') ?>" class="badge rounded-pill px-3 py-2 text-white border border-white border-opacity-25" style="background:rgba(255,255,255,.12);font-size:.82rem">Burial Plots</a>
                    <a href="<?= pg_url('columbarium-niches') ?>" class="badge rounded-pill px-3 py-2 text-white border border-white border-opacity-25" style="background:rgba(255,255,255,.12);font-size:.82rem">Columbarium Niches</a>
                    <a href="<?= pg_url('browse_listings.php?state=Selangor') ?>" class="badge rounded-pill px-3 py-2 text-white border border-white border-opacity-25" style="background:rgba(255,255,255,.12);font-size:.82rem">Selangor</a>
                    <a href="<?= pg_url('browse_listings.php?urgency=urgent') ?>" class="badge rounded-pill px-3 py-2 text-danger border" style="background:rgba(255,255,255,.1);font-size:.82rem"><i class="fas fa-bolt me-1"></i>Urgent Sales</a>
                </div>
            </div>

            <div class="col-lg-5 d-none d-lg-block text-center">
                <div class="p-4" style="background:rgba(255,255,255,.06);border-radius:16px;border:1px solid rgba(255,255,255,.1)">
                    <p class="text-white-50 small mb-3 text-uppercase ls-1">Listings available now</p>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="p-3 rounded-3" style="background:rgba(255,255,255,.08)">
                                <div class="fs-2 fw-bold text-warning" data-counter="<?= $totalListings ?>"><?= $totalListings ?></div>
                                <div class="text-white-50 small">Active Listings</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3" style="background:rgba(255,255,255,.08)">
                                <div class="fs-2 fw-bold text-warning" data-counter="<?= $totalParks ?>"><?= $totalParks ?></div>
                                <div class="text-white-50 small">Memorial Parks</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3" style="background:rgba(255,255,255,.08)">
                                <div class="fs-2 fw-bold text-warning" data-counter="<?= $totalProviders ?>"><?= $totalProviders ?></div>
                                <div class="text-white-50 small">Service Providers</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3" style="background:rgba(255,255,255,.08)">
                                <div class="fs-2 fw-bold text-warning">Free</div>
                                <div class="text-white-50 small">To Browse</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── TRUST BADGES ──────────────────────────────────────────────────── -->
<section class="py-5 bg-white border-bottom">
    <div class="container">
        <div class="row g-4 text-center">
            <?php
            $trustItems = [
                ['icon' => 'fa-shield-alt', 'title' => 'Verified Listings', 'desc' => 'Multi-point document and identity verification before listings go live.'],
                ['icon' => 'fa-balance-scale', 'title' => 'Price Benchmarked', 'desc' => 'Every listing is checked against market data for fair pricing.'],
                ['icon' => 'fa-headset', 'title' => 'Compassionate Support', 'desc' => 'Our team understands what you\'re going through. We\'re here.'],
                ['icon' => 'fa-lock', 'title' => 'Private & Secure', 'desc' => 'Your documents and personal information are protected.'],
            ];
            foreach ($trustItems as $item): ?>
                <div class="col-sm-6 col-lg-3">
                    <div class="trust-badge">
                        <div class="trust-icon"><i class="fas <?= $item['icon'] ?>"></i></div>
                        <h5><?= h($item['title']) ?></h5>
                        <p><?= h($item['desc']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── RECENT LISTINGS ───────────────────────────────────────────────── -->
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h2 class="section-title mb-1">Latest Listings</h2>
                <div class="section-divider"></div>
                <p class="text-muted small">Verified resale burial plots and niches across Malaysia</p>
            </div>
            <a href="<?= pg_url('browse_listings.php') ?>" class="btn btn-outline-gold btn-sm d-none d-md-inline">View All Listings</a>
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
                <i class="fas fa-th-large me-2"></i>Browse All Listings
            </a>
        </div>
        <?php else: ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-search fa-2x mb-3"></i>
            <p>Listings are coming soon. <a href="<?= pg_url('sell_plot.php') ?>">Be the first to list.</a></p>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ── HOW IT WORKS ──────────────────────────────────────────────────── -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">How PlotGold Works</h2>
            <div class="section-divider mx-auto"></div>
            <p class="text-muted">Simple, transparent, and dignified process</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-3 text-center">
                <div class="step-circle mb-3">1</div>
                <h5>Browse Verified Listings</h5>
                <p class="text-muted small">Search by location, type, religion, and price. Every listing is verified before it appears.</p>
            </div>
            <div class="col-md-6 col-lg-3 text-center">
                <div class="step-circle mb-3">2</div>
                <h5>Compare & Save</h5>
                <p class="text-muted small">Save listings to compare side-by-side. Get full specs, documents, and pricing details.</p>
            </div>
            <div class="col-md-6 col-lg-3 text-center">
                <div class="step-circle mb-3">3</div>
                <h5>Enquire via WhatsApp</h5>
                <p class="text-muted small">Connect with verified sellers or our team directly via WhatsApp — no phone tag, no pressure.</p>
            </div>
            <div class="col-md-6 col-lg-3 text-center">
                <div class="step-circle mb-3">4</div>
                <h5>Transfer Supported</h5>
                <p class="text-muted small">We guide you through the transfer process with document checklists and park liaisons.</p>
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
                <h2 class="section-title mb-1">Featured Memorial Parks</h2>
                <div class="section-divider"></div>
                <p class="text-muted small">Browse listings by park location</p>
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
                            <span class="listing-type-tag">View Listings</span>
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
                    <h4 class="fw-600">Selling a Burial Plot?</h4>
                    <p class="text-muted">List your verified resale plot or columbarium niche. Reach thousands of serious buyers across Malaysia.</p>
                    <ul class="list-unstyled text-muted small mb-3">
                        <li><i class="fas fa-check text-success me-2"></i>Free listing submission</li>
                        <li><i class="fas fa-check text-success me-2"></i>Guided verification process</li>
                        <li><i class="fas fa-check text-success me-2"></i>Trust badge after verification</li>
                        <li><i class="fas fa-check text-success me-2"></i>Enquiry management dashboard</li>
                    </ul>
                    <a href="<?= pg_url('sell_plot.php') ?>" class="btn btn-gold">List My Plot</a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="bg-white pg-card p-4 h-100">
                    <div class="trust-icon mb-3"><i class="fas fa-clipboard-list"></i></div>
                    <h4 class="fw-600">Planning Ahead?</h4>
                    <p class="text-muted">Use our DIY Funeral Planner to build an itemised funeral quote at your own pace. No pressure, no sales calls.</p>
                    <ul class="list-unstyled text-muted small mb-3">
                        <li><i class="fas fa-check text-success me-2"></i>Choose and price each service</li>
                        <li><i class="fas fa-check text-success me-2"></i>Compare service providers</li>
                        <li><i class="fas fa-check text-success me-2"></i>Save and download your plan</li>
                        <li><i class="fas fa-check text-success me-2"></i>Request quotes from providers</li>
                    </ul>
                    <a href="<?= pg_url('funeral-planner') ?>" class="btn btn-navy">Start Planning</a>
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
                <h4 class="mb-1"><i class="fas fa-hands-helping text-warning me-2"></i>Need immediate assistance?</h4>
                <p class="mb-0 text-white-50 small">Our team and partner providers are available to help during a bereavement. Contact us now.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="<?= whatsapp_link('Hi PlotGold, I need urgent funeral assistance.') ?>" target="_blank" rel="noopener" class="btn btn-whatsapp me-2">
                    <i class="fab fa-whatsapp me-1"></i>WhatsApp Now
                </a>
                <a href="<?= pg_url('urgent-funeral-help') ?>" class="btn btn-outline-light">
                    Urgent Help Page
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
                <h2 class="section-title">Common Questions</h2>
                <div class="section-divider"></div>
                <p class="text-muted">Answers to the most frequently asked questions about burial plot resale and funeral planning.</p>
                <a href="<?= pg_url('faq.php') ?>" class="btn btn-outline-gold">View All FAQs</a>
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
