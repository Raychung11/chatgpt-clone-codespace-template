<?php
/**
 * Listing Card Partial
 * Expects: $listing array (may contain type_label_en/type_label_zh, park_name_en/park_name_zh
 *          or flat type_label / park_name for backward compat)
 */
defined('PLOTGOLD') or die('Direct access not permitted.');

$imgSrc  = !empty($listing['primary_image'])
    ? BASE_URL . '/uploads/listings/' . h($listing['primary_image'])
    : null;

// Use lang_label() when bi-lingual columns present; fall back to flat column
$typeLabel = isset($listing['type_label_en'])
    ? lang_label($listing, 'type_label')
    : h($listing['type_label'] ?? __('card.pending'));

$parkName  = isset($listing['park_name_en'])
    ? lang_label($listing, 'park_name')
    : h($listing['park_name'] ?? $listing['city'] ?? 'Malaysia');

$badge   = $listing['badge_status'] ?? 'none';
$urgency = $listing['urgency_level'] ?? 'standard';
$price   = $listing['asking_price'];
$slug    = $listing['slug'] ?? '#';
?>
<div class="pg-card listing-card h-100">
    <?php if (!empty($listing['is_featured'])): ?>
        <div class="listing-featured-ribbon"><?= _e('card.featured') ?></div>
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
            <span class="listing-type-tag"><?= $typeLabel ?></span>
            <div class="d-flex gap-1 align-items-center">
                <?php if ($badge !== 'none'): ?>
                    <?= listing_badge_html($badge) ?>
                <?php endif; ?>
                <?php if (in_array($urgency, ['urgent', 'immediate'])): ?>
                    <span class="badge-urgent"><?= _e('card.urgent') ?></span>
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
            <?= h($listing['city'] ?? '') ?><?= ($listing['city'] && $listing['state']) ? ', ' : '' ?><?= h($listing['state'] ?? '') ?>
            <?php if (!empty($listing['park_name']) || !empty($listing['park_name_en'])): ?> — <?= $parkName ?><?php endif; ?>
        </p>

        <!-- Price -->
        <div class="d-flex justify-content-between align-items-center mt-2">
            <div>
                <?php if ($price): ?>
                    <div class="listing-price"><?= format_currency($price) ?></div>
                <?php else: ?>
                    <div class="text-muted small fw-500"><?= _e('detail.enquiry_btn') ?></div>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2">
                <?php if (auth_check()): ?>
                    <button class="btn btn-link p-0 text-muted fav-btn"
                            onclick="toggleFavourite('<?= (int)$listing['id'] ?>', this)"
                            data-listing="<?= (int)$listing['id'] ?>"
                            title="<?= _e('card.save') ?>">
                        <i class="far fa-heart"></i>
                    </button>
                <?php endif; ?>
                <button class="btn btn-link p-0 text-muted compare-btn"
                        onclick="Compare.toggle('<?= (int)$listing['id'] ?>', this)"
                        title="<?= _e('btn.compare') ?>">
                    <i class="fas fa-balance-scale"></i>
                </button>
            </div>
        </div>
    </div>
</div>
