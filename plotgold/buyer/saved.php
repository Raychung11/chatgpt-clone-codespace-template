<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_role(ROLE_BUYER, '/register.php?type=buyer');

$buyer = Database::fetchOne('SELECT * FROM buyers WHERE user_id = ?', [auth_user_id()]);
if (!$buyer) redirect('/register.php?type=buyer');

$listings = Database::fetchAll(
    "SELECT l.*, lt.label_en AS type_label, mp.name AS park_name, lm.file_path AS primary_image
     FROM favourites f
     JOIN listings l ON l.id = f.listing_id AND l.status = 'active'
     LEFT JOIN listing_types lt ON lt.id = l.listing_type_id
     LEFT JOIN memorial_parks mp ON mp.id = l.park_id
     LEFT JOIN listing_media lm ON lm.listing_id = l.id AND lm.is_primary = 1
     WHERE f.buyer_id = ?
     ORDER BY f.created_at DESC",
    [$buyer['id']]
);

$page_title = 'Saved Listings';
include INC_PATH . '/header.php';
?>
<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="portal-content">
    <?= render_flash() ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-700 text-navy mb-0"><i class="fas fa-heart me-2 text-danger"></i>Saved Listings</h4>
        <a href="<?= pg_url('browse_listings.php') ?>" class="btn btn-outline-gold btn-sm">Browse More</a>
    </div>

    <?php if ($listings): ?>
    <div class="row g-3">
        <?php foreach ($listings as $listing): ?>
        <div class="col-sm-6 col-lg-4">
            <?php include INC_PATH . '/partials/listing_card.php'; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-center py-5">
        <i class="far fa-heart fa-4x text-muted mb-4"></i>
        <h5 class="text-muted">No saved listings yet</h5>
        <p class="text-muted small">Browse listings and click the heart icon to save them here for easy access.</p>
        <a href="<?= pg_url('browse_listings.php') ?>" class="btn btn-gold mt-2">Browse Listings</a>
    </div>
    <?php endif; ?>
</div>
</div>
<?php include INC_PATH . '/footer.php'; ?>
