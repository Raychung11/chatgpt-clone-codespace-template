<?php
require_once __DIR__ . '/../inc/bootstrap.php';

$ids = array_filter(array_map('intval', explode(',', clean($_GET['ids'] ?? ''))));
$ids = array_slice($ids, 0, 3);

$listings = [];
foreach ($ids as $id) {
    $l = Database::fetchOne(
        "SELECT l.*, lt.label_en AS type_label, mp.name AS park_name, rc.label_en AS religion_label
         FROM listings l
         LEFT JOIN listing_types lt ON lt.id = l.listing_type_id
         LEFT JOIN memorial_parks mp ON mp.id = l.park_id
         LEFT JOIN religion_categories rc ON rc.id = l.religion_id
         WHERE l.id = ? AND l.status = 'active'",
        [$id]
    );
    if ($l) $listings[] = $l;
}

$page_title = 'Compare Listings';
include INC_PATH . '/header.php';
include INC_PATH . '/nav.php';
?>

<nav class="bg-white border-bottom">
    <div class="container py-2">
        <ol class="breadcrumb pg-breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= pg_url() ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= pg_url('browse_listings.php') ?>">Browse</a></li>
            <li class="breadcrumb-item active">Compare</li>
        </ol>
    </div>
</nav>

<div class="container py-4">
    <h1 class="h3 fw-700 text-navy mb-4">Compare Listings</h1>

    <?php if (count($listings) < 2): ?>
    <div class="text-center py-5">
        <i class="fas fa-balance-scale fa-3x text-muted mb-3"></i>
        <h5 class="text-muted">Select at least 2 listings to compare</h5>
        <p class="text-muted small">Browse listings and click the compare icon to add them here.</p>
        <a href="<?= pg_url('browse_listings.php') ?>" class="btn btn-gold mt-2">Browse Listings</a>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered" style="min-width:600px">
            <thead class="table-light">
                <tr>
                    <th style="width:160px">Feature</th>
                    <?php foreach ($listings as $l): ?>
                    <th>
                        <div class="fw-600 small"><?= h(substr($l['title'], 0, 40)) ?></div>
                        <a href="<?= listing_url($l['slug']) ?>" class="small text-muted">View Listing →</a>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php
            $fields = [
                'Asking Price'     => fn($l) => format_currency($l['asking_price']),
                'Type'             => fn($l) => h($l['type_label'] ?? '—'),
                'Religion'         => fn($l) => h($l['religion_label'] ?? '—'),
                'Memorial Park'    => fn($l) => h($l['park_name'] ?? '—'),
                'City'             => fn($l) => h($l['city'] ?? '—'),
                'State'            => fn($l) => h($l['state'] ?? '—'),
                'Block/Row/Lot'    => fn($l) => implode('/', array_filter([h($l['block_no']), h($l['row_no']), h($l['lot_no'])])) ?: '—',
                'Ownership'        => fn($l) => ucfirst(str_replace('_', ' ', $l['ownership_type'] ?? '—')),
                'Transferable'     => fn($l) => $l['is_transferable'] ? '<span class="text-success fw-500">Yes</span>' : '<span class="text-warning fw-500">Check Required</span>',
                'Maintenance'      => fn($l) => ucfirst($l['maintenance_status'] ?? '—'),
                'Transfer Fee'     => fn($l) => format_currency($l['transfer_fee']),
                'Verification'     => fn($l) => listing_badge_html($l['badge_status']),
                'Seller Intent'    => fn($l) => ucwords(str_replace('_', ' ', $l['seller_intent'] ?? '—')),
                'Views'            => fn($l) => number_format($l['view_count']),
            ];
            foreach ($fields as $label => $fn):
            ?>
            <tr>
                <td class="text-muted small fw-500"><?= $label ?></td>
                <?php foreach ($listings as $l): ?>
                <td class="small"><?= $fn($l) ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
            <tr>
                <td></td>
                <?php foreach ($listings as $l): ?>
                <td>
                    <a href="<?= listing_url($l['slug']) ?>" class="btn btn-gold btn-sm w-100">View Details</a>
                </td>
                <?php endforeach; ?>
            </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include INC_PATH . '/footer.php'; ?>
