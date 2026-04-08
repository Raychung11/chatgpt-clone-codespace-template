<?php
defined('PLOTGOLD') or die();
$page_title = 'Page Not Found';
include __DIR__ . '/../header.php';
include __DIR__ . '/../nav.php';
?>
<div class="container py-5 text-center">
    <h1 class="display-1 fw-bold text-muted" style="font-size:6rem">404</h1>
    <h4 class="fw-600 text-navy mb-3">Page Not Found</h4>
    <p class="text-muted mb-4">The page you're looking for doesn't exist or has been moved.</p>
    <a href="<?= pg_url() ?>" class="btn btn-gold me-2">Back to Home</a>
    <a href="<?= pg_url('browse_listings.php') ?>" class="btn btn-outline-gold">Browse Listings</a>
</div>
<?php include __DIR__ . '/../footer.php'; ?>
