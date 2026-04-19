<?php
require_once __DIR__ . '/inc/bootstrap.php';

$slug = clean($_GET['slug'] ?? '');
if (!$slug) redirect('index.php');

$cmsPage = Database::fetchOne(
    'SELECT * FROM cms_pages WHERE slug = ? AND is_published = 1',
    [$slug]
);

if (!$cmsPage) {
    http_response_code(404);
    include INC_PATH . '/errors/404.php';
    exit;
}

$page_title       = $cmsPage['meta_title'] ?: $cmsPage['title'] . ' | PlotGold Malaysia';
$meta_description = $cmsPage['meta_description'] ?: '';
include INC_PATH . '/header.php';
include INC_PATH . '/nav.php';
?>

<!-- Breadcrumb -->
<nav class="bg-white border-bottom">
    <div class="container py-2">
        <ol class="breadcrumb pg-breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= pg_url() ?>"><?= _e('nav.home') ?></a></li>
            <li class="breadcrumb-item active"><?= h($cmsPage['title']) ?></li>
        </ol>
    </div>
</nav>

<!-- Page hero -->
<div class="bg-white border-bottom py-4">
    <div class="container">
        <h1 class="h2 fw-700 text-navy mb-0"><?= h($cmsPage['title']) ?></h1>
        <?php if ($cmsPage['published_at']): ?>
            <div class="text-muted small mt-1">
                <i class="fas fa-calendar-alt me-1"></i>
                Last updated <?= format_date($cmsPage['updated_at'], 'd M Y') ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Page content -->
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="pg-card p-4 p-md-5">
                <div class="cms-content" style="line-height:1.8;color:#374151;">
                    <?= $cmsPage['content'] ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Schema.org Article -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebPage",
  "name": <?= json_encode($cmsPage['title']) ?>,
  "url": <?= json_encode(pg_url('page/' . $cmsPage['slug'])) ?>,
  <?php if ($cmsPage['meta_description']): ?>
  "description": <?= json_encode($cmsPage['meta_description']) ?>,
  <?php endif; ?>
  "dateModified": <?= json_encode($cmsPage['updated_at']) ?>
}
</script>

<?php include INC_PATH . '/footer.php'; ?>
