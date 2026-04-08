<?php
/**
 * Listing Card Partial
 * Expects: $listing array
 */
defined('PLOTGOLD') or die('Direct access not permitted.');

$imgSrc    = !empty($listing['primary_image'])
    ? BASE_URL . '/uploads/listings/' . h($listing['primary_image'])
    : null;
$parkName  = $listing['park_name'] ?? $listing['city'] ?? 'Malaysia';
$typeLabel = $listing['type_label'] ?? 'Listing';
$badge     = $listing['badge_status'] ?? 'none';
$urgency   = $listing['urgency_level'] ?? 'standard';
$price     = $listing['asking_price'];
$slug      = $listing['slug'] ?? '#';
?>
<div class="pg-card listing-card h-100">
    <?php if (!empty($listing['is_featured'])): ?>
        <div class="listing-featured-ribbon">Featured</div>
    <?php endif; ?>

    <a href="<?= listing_url($slug) ?>" class="text-decoration-none">
        <?php if ($imgSrc): ?>
            <img src="<?= h($imgSrc) ?>" class="card-img-top" alt="<?= h($listing['title']) ?>" loading="lazy">
        <?php else: ?>
            <div class="pg-card-img-placeholder"><i class="fas fa-mountain"></i></div>
        <?php endif; ?>
    </a>

    <div class="p-3">
        <!-- Type + Badge -->
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="listing-type-tag"><?= h($typeLabel) ?></span>
            <div class="d-flex gap-1 align-items-center">
                <?php if ($badge !== 'none'): ?>
                    <?= listing_badge_html($badge) ?>
                <?php endif; ?>
                <?php if (in_array($urgency, ['urgent', 'immediate'])): ?>
                    <span class="badge-urgent">Urgent</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Title -->
        <a href="<?= listing_url($slug) ?>" class="text-decoration-none">
            <h6 class="fw-600 text-navy mb-1 lh-sm" style="font-size:.9rem"><?= h($listing['title']) ?></h6>
        </a>

        <!-- Location -->
        <p class="listing-location mb-2">
            <i class="fas fa-map-marker-alt me-1"></i>
            <?= h($listing['city'] ?? '') ?><?= $listing['city'] && $listing['state'] ? ', ' : '' ?><?= h($listing['state'] ?? '') ?>
            <?php if (!empty($listing['park_name'])): ?> — <?= h($listing['park_name']) ?><?php endif; ?>
        </p>

        <!-- Price -->
        <div class="d-flex justify-content-between align-items-center mt-2">
            <div>
                <?php if ($price): ?>
                    <div class="listing-price"><?= format_currency($price) ?></div>
                <?php else: ?>
                    <div class="text-muted small fw-500">Price on Enquiry</div>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2">
                <?php if (auth_check()): ?>
                    <button class="btn btn-link p-0 text-muted fav-btn"
                            onclick="toggleFavourite('<?= (int)$listing['id'] ?>', this)"
                            data-listing="<?= (int)$listing['id'] ?>"
                            title="Save to favourites">
                        <i class="far fa-heart"></i>
                    </button>
                <?php endif; ?>
                <button class="btn btn-link p-0 text-muted compare-btn"
                        onclick="Compare.toggle('<?= (int)$listing['id'] ?>', this)"
                        title="Add to compare">
                    <i class="fas fa-balance-scale"></i>
                </button>
            </div>
        </div>
    </div>
</div>
