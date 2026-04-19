<?php
require_once __DIR__ . '/../inc/bootstrap.php';

$slug = clean($_GET['slug'] ?? '');
if (!$slug) redirect('browse_listings.php');

$park = Database::fetchOne('SELECT * FROM memorial_parks WHERE slug = ? AND is_active = 1', [$slug]);
if (!$park) { http_response_code(404); include INC_PATH . '/errors/404.php'; exit; }

// Park listings
$page    = max(1, clean_int($_GET['page'] ?? 1));
$perPage = LISTINGS_PER_PAGE;
$total   = (int)(Database::fetchOne("SELECT COUNT(*) c FROM listings WHERE park_id = ? AND status = 'active'", [$park['id']])['c'] ?? 0);
$pages   = (int)ceil($total / $perPage);
$offset  = ($page - 1) * $perPage;

$listings = Database::fetchAll(
    "SELECT l.*, lt.label_en AS type_label, lm.file_path AS primary_image
     FROM listings l
     LEFT JOIN listing_types lt ON lt.id = l.listing_type_id
     LEFT JOIN listing_media lm ON lm.listing_id = l.id AND lm.is_primary = 1
     WHERE l.park_id = ? AND l.status = 'active'
     ORDER BY l.is_featured DESC, l.listed_at DESC
     LIMIT $perPage OFFSET $offset",
    [$park['id']]
);

$sections = Database::fetchAll('SELECT * FROM park_sections WHERE park_id = ? AND is_active = 1', [$park['id']]);

$hasBanner = !empty($park['banner_path']);
$hasLogo   = !empty($park['logo_path']);

$page_title       = $park['meta_title'] ?: $park['name'] . ' | PlotGold Malaysia';
$meta_description = $park['meta_description'] ?: 'Browse verified resale burial plot and columbarium niche listings at ' . $park['name'] . '.';
include INC_PATH . '/header.php';
include INC_PATH . '/nav.php';
?>

<!-- Breadcrumb -->
<nav class="bg-white border-bottom">
    <div class="container py-2">
        <ol class="breadcrumb pg-breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= pg_url() ?>"><?= _e('nav.home') ?></a></li>
            <li class="breadcrumb-item"><a href="<?= pg_url('browse_listings.php') ?>"><?= _e('nav.browse') ?></a></li>
            <li class="breadcrumb-item active"><?= h($park['name']) ?></li>
        </ol>
    </div>
</nav>

<!-- ── PARK HERO (banner) ─────────────────────────────────────────────────── -->
<?php if ($hasBanner): ?>
<div class="position-relative" style="height:320px;overflow:hidden;">
    <img src="<?= pg_url('uploads/parks/' . h($park['banner_path'])) ?>"
         alt="<?= h($park['name']) ?>"
         style="width:100%;height:100%;object-fit:cover;">
    <!-- Dark gradient overlay so text is readable -->
    <div class="position-absolute inset-0 w-100 h-100"
         style="background:linear-gradient(to bottom, rgba(0,0,0,.15) 0%, rgba(0,0,0,.55) 100%);top:0;left:0;"></div>
    <!-- Park name overlay -->
    <div class="position-absolute w-100" style="bottom:0;left:0;padding:2rem 0 1.5rem;">
        <div class="container">
            <div class="d-flex align-items-end gap-3">
                <?php if ($hasLogo): ?>
                <img src="<?= pg_url('uploads/parks/' . h($park['logo_path'])) ?>"
                     alt=""
                     style="width:72px;height:72px;object-fit:contain;background:#fff;border-radius:12px;padding:6px;border:2px solid rgba(255,255,255,.3);flex-shrink:0;">
                <?php endif; ?>
                <div>
                    <h1 class="fw-700 mb-0" style="color:#fff;text-shadow:0 1px 4px rgba(0,0,0,.4);">
                        <?= h($park['name']) ?>
                    </h1>
                    <?php if ($park['name_zh']): ?>
                        <div style="color:rgba(255,255,255,.8);font-size:.95rem;"><?= h($park['name_zh']) ?></div>
                    <?php endif; ?>
                    <div style="color:rgba(255,255,255,.75);font-size:.9rem;" class="mt-1">
                        <i class="fas fa-map-marker-alt me-1"></i><?= h($park['city']) ?>, <?= h($park['state']) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── PARK HEADER (info bar) ────────────────────────────────────────────── -->
<div class="bg-white border-bottom py-4">
    <div class="container">
        <div class="row align-items-center g-3">
            <div class="col-md-8">
                <?php if (!$hasBanner): ?>
                <!-- Only show heading here if there's no banner (banner already shows it) -->
                <div class="d-flex align-items-center gap-3 mb-2">
                    <?php if ($hasLogo): ?>
                    <img src="<?= pg_url('uploads/parks/' . h($park['logo_path'])) ?>"
                         alt=""
                         style="width:60px;height:60px;object-fit:contain;background:#f8f9fa;border-radius:10px;padding:6px;border:1px solid #e9ecef;flex-shrink:0;">
                    <?php endif; ?>
                    <div>
                        <h1 class="h3 fw-700 text-navy mb-0"><?= h($park['name']) ?></h1>
                        <?php if ($park['name_zh']): ?>
                            <div class="text-muted small"><?= h($park['name_zh']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Contact / location chips -->
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge bg-light text-muted border">
                        <i class="fas fa-map-marker-alt me-1" style="color:var(--pg-gold);"></i><?= h($park['city']) ?>, <?= h($park['state']) ?>
                    </span>
                    <?php if ($park['phone']): ?>
                    <a href="tel:<?= h($park['phone']) ?>" class="badge bg-light text-muted border text-decoration-none">
                        <i class="fas fa-phone me-1"></i><?= h($park['phone']) ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($park['email']): ?>
                    <a href="mailto:<?= h($park['email']) ?>" class="badge bg-light text-muted border text-decoration-none">
                        <i class="fas fa-envelope me-1"></i><?= h($park['email']) ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($park['supported_religions']): ?>
                        <?php foreach (array_map('trim', explode(',', $park['supported_religions'])) as $rel): ?>
                        <span class="badge" style="background:var(--pg-gold-pale);color:var(--pg-gold);border:1px solid rgba(200,160,60,.3);">
                            <?= h(ucfirst($rel)) ?>
                        </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-4 text-md-end">
                <div class="fw-700 fs-3 text-navy"><?= $total ?></div>
                <div class="small text-muted mb-2"><?= _e('parks.active_listings', ['count' => '']) ?></div>
                <a href="<?= pg_url('sell_plot.php') ?>" class="btn btn-outline-gold btn-sm">
                    <i class="fas fa-plus me-1"></i>List a Plot Here
                </a>
                <?php if ($park['phone']): ?>
                <a href="<?= whatsapp_link('Hi, I found ' . $park['name'] . ' on PlotGold Malaysia. I\'d like to enquire about listings.') ?>"
                   target="_blank" rel="noopener"
                   class="btn btn-whatsapp btn-sm ms-1">
                    <i class="fab fa-whatsapp me-1"></i>WhatsApp
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── MAIN CONTENT ───────────────────────────────────────────────────────── -->
<div class="container py-4">

    <?php if ($park['description']): ?>
    <div class="pg-card p-4 mb-4">
        <h5 class="fw-700 text-navy mb-2">
            <i class="fas fa-info-circle me-2" style="color:var(--pg-gold);"></i>
            <?= is_lang('zh') ? '关于此园区' : 'About This Park' ?>
        </h5>
        <p class="text-muted mb-0"><?= nl2br(h($park['description'])) ?></p>
    </div>
    <?php endif; ?>

    <?php if ($sections): ?>
    <!-- Sections overview -->
    <div class="mb-4">
        <h5 class="fw-700 text-navy mb-3">
            <i class="fas fa-map me-2" style="color:var(--pg-gold);"></i>
            <?= is_lang('zh') ? '园区分区' : 'Park Sections' ?>
        </h5>
        <div class="row g-2">
            <?php foreach ($sections as $sec): ?>
            <div class="col-sm-6 col-md-4 col-lg-3">
                <div class="pg-card p-3 h-100">
                    <div class="fw-600 small text-navy mb-1"><?= h($sec['name']) ?></div>
                    <?php if ($sec['section_type']): ?>
                        <div class="text-muted" style="font-size:.75rem;"><?= h(ucwords(str_replace('_',' ',$sec['section_type']))) ?></div>
                    <?php endif; ?>
                    <?php if ($sec['available_plots']): ?>
                        <div class="mt-1 small" style="color:var(--pg-gold);">
                            <?= (int)$sec['available_plots'] ?> <?= is_lang('zh') ? '个可用' : 'available' ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Listings -->
    <?php if ($listings): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-700 text-navy mb-0">
            <i class="fas fa-list-alt me-2" style="color:var(--pg-gold);"></i>
            <?= is_lang('zh') ? '在售房源' : 'Available Listings' ?>
            <span class="badge bg-light text-muted border ms-2" style="font-size:.75rem;"><?= $total ?></span>
        </h5>
    </div>
    <div class="row g-3">
        <?php foreach ($listings as $listing): ?>
        <div class="col-sm-6 col-lg-3">
            <?php include INC_PATH . '/partials/listing_card.php'; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="mt-4">
        <?= pagination_links(['rows'=>$listings,'total'=>$total,'pages'=>$pages,'page'=>$page,'per_page'=>$perPage,'has_prev'=>$page>1,'has_next'=>$page<$pages], park_url($slug)) ?>
    </div>
    <?php else: ?>
    <div class="text-center py-5">
        <i class="fas fa-mountain fa-3x text-muted mb-3"></i>
        <h5 class="text-muted"><?= is_lang('zh') ? '此园区暂无在售房源' : 'No listings at this park yet' ?></h5>
        <p class="text-muted small"><?= is_lang('zh') ? '成为第一个在此刊登墓地的用户。' : 'Be the first to list a plot here.' ?></p>
        <a href="<?= pg_url('sell_plot.php') ?>" class="btn btn-gold mt-2">
            <i class="fas fa-plus me-1"></i><?= _e('about.cta_sell') ?>
        </a>
    </div>
    <?php endif; ?>

    <!-- Get a quote CTA -->
    <div class="mt-5 pg-card p-4 d-flex flex-column flex-md-row align-items-center justify-content-between gap-3"
         style="border-left:4px solid var(--pg-gold);">
        <div>
            <h5 class="fw-700 text-navy mb-1"><?= is_lang('zh') ? '需要更多帮助？' : 'Need More Help?' ?></h5>
            <p class="text-muted small mb-0">
                <?= is_lang('zh') ? '申请报价，我们将为您匹配合适的服务商和房源。' : 'Request a quote and we\'ll match you with the right listing and service providers.' ?>
            </p>
        </div>
        <div class="d-flex gap-2 flex-shrink-0">
            <a href="<?= pg_url('request_quote.php') ?>" class="btn btn-gold">
                <i class="fas fa-file-invoice me-1"></i><?= _e('btn.get_quote') ?>
            </a>
            <a href="<?= whatsapp_link('Hi PlotGold! I need help with ' . $park['name'] . '.') ?>"
               class="btn btn-whatsapp" target="_blank" rel="noopener">
                <i class="fab fa-whatsapp me-1"></i><?= _e('btn.whatsapp') ?>
            </a>
        </div>
    </div>
</div>

<!-- Schema.org -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Cemetery",
  "name": <?= json_encode($park['name']) ?>,
  <?php if ($hasBanner): ?>
  "image": <?= json_encode(pg_url('uploads/parks/' . $park['banner_path'])) ?>,
  <?php endif; ?>
  "address": {
    "@type": "PostalAddress",
    "streetAddress": <?= json_encode($park['address_line1'] ?? '') ?>,
    "addressLocality": <?= json_encode($park['city']) ?>,
    "addressRegion": <?= json_encode($park['state']) ?>,
    "addressCountry": "MY"
  }
  <?php if ($park['phone']): ?>, "telephone": <?= json_encode($park['phone']) ?><?php endif; ?>
  <?php if ($park['email']): ?>, "email": <?= json_encode($park['email']) ?><?php endif; ?>
}
</script>

<?php include INC_PATH . '/footer.php'; ?>
