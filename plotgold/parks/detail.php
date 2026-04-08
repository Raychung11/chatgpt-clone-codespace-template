<?php
require_once __DIR__ . '/../inc/bootstrap.php';

$slug = clean($_GET['slug'] ?? '');
if (!$slug) redirect('browse_listings.php');

$park = Database::fetchOne('SELECT * FROM memorial_parks WHERE slug = ? AND is_active = 1', [$slug]);
if (!$park) { http_response_code(404); include INC_PATH . '/errors/404.php'; exit; }

// Park listings
$page  = max(1, clean_int($_GET['page'] ?? 1));
$perPage = LISTINGS_PER_PAGE;
$total = (int)(Database::fetchOne("SELECT COUNT(*) c FROM listings WHERE park_id = ? AND status = 'active'", [$park['id']])['c'] ?? 0);
$pages = (int)ceil($total / $perPage);
$offset= ($page - 1) * $perPage;

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

$page_title       = $park['meta_title'] ?: $park['name'] . ' | Burial Plot Listings';
$meta_description = $park['meta_description'] ?: 'Browse verified resale burial plot and columbarium niche listings at ' . $park['name'] . '.';
include INC_PATH . '/header.php';
include INC_PATH . '/nav.php';
?>

<!-- Breadcrumb -->
<nav class="bg-white border-bottom">
    <div class="container py-2">
        <ol class="breadcrumb pg-breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= pg_url() ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= pg_url('browse_listings.php') ?>">Browse</a></li>
            <li class="breadcrumb-item active"><?= h($park['name']) ?></li>
        </ol>
    </div>
</nav>

<!-- Park Header -->
<div class="bg-white border-bottom py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="h3 fw-700 text-navy mb-1"><?= h($park['name']) ?></h1>
                <?php if ($park['name_zh']): ?><p class="text-muted mb-1"><?= h($park['name_zh']) ?></p><?php endif; ?>
                <p class="text-muted small"><i class="fas fa-map-marker-alt me-1"></i><?= h($park['city']) ?>, <?= h($park['state']) ?></p>
                <?php if ($park['phone']): ?>
                    <a href="tel:<?= h($park['phone']) ?>" class="text-muted small"><i class="fas fa-phone me-1"></i><?= h($park['phone']) ?></a>
                <?php endif; ?>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="fw-700 fs-4 text-navy"><?= $total ?></div>
                <div class="small text-muted">Active Listings</div>
                <a href="<?= pg_url('sell_plot.php') ?>" class="btn btn-outline-gold btn-sm mt-2">List a Plot Here</a>
            </div>
        </div>
    </div>
</div>

<div class="container py-4">
    <?php if ($park['description']): ?>
    <div class="pg-card p-4 mb-4">
        <p class="text-muted mb-0"><?= h($park['description']) ?></p>
    </div>
    <?php endif; ?>

    <?php if ($listings): ?>
    <h5 class="fw-600 mb-3">Available Listings</h5>
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
        <h5 class="text-muted">No listings at this park yet</h5>
        <p class="text-muted small">Be the first to list a plot here.</p>
        <a href="<?= pg_url('sell_plot.php') ?>" class="btn btn-gold mt-2">List My Plot</a>
    </div>
    <?php endif; ?>
</div>

<!-- LocalBusiness Schema -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Cemetery",
  "name": <?= json_encode($park['name']) ?>,
  "address": {
    "@type": "PostalAddress",
    "addressLocality": <?= json_encode($park['city']) ?>,
    "addressRegion": <?= json_encode($park['state']) ?>,
    "addressCountry": "MY"
  }
  <?php if ($park['phone']): ?>, "telephone": <?= json_encode($park['phone']) ?><?php endif; ?>
}
</script>

<?php include INC_PATH . '/footer.php'; ?>
