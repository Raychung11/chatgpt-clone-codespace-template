<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_role(ROLE_BUYER, '/register.php?type=buyer');

$buyer = Database::fetchOne('SELECT * FROM buyers WHERE user_id = ?', [auth_user_id()]);
if (!$buyer) redirect('/register.php?type=buyer');

$savedCount  = (int)(Database::fetchOne('SELECT COUNT(*) c FROM favourites WHERE buyer_id = ?', [$buyer['id']])['c'] ?? 0);
$enquiryCount= (int)(Database::fetchOne('SELECT COUNT(*) c FROM enquiries WHERE buyer_id = ?', [$buyer['id']])['c'] ?? 0);
$quoteCount  = (int)(Database::fetchOne('SELECT COUNT(*) c FROM quotations WHERE buyer_id = ?', [$buyer['id']])['c'] ?? 0);

$savedListings = Database::fetchAll(
    "SELECT l.*, lt.label_en AS type_label, lm.file_path AS primary_image
     FROM favourites f
     JOIN listings l ON l.id = f.listing_id AND l.status = 'active'
     LEFT JOIN listing_types lt ON lt.id = l.listing_type_id
     LEFT JOIN listing_media lm ON lm.listing_id = l.id AND lm.is_primary = 1
     WHERE f.buyer_id = ?
     ORDER BY f.created_at DESC LIMIT 4",
    [$buyer['id']]
);

$recentEnquiries = Database::fetchAll(
    "SELECT e.*, l.title AS listing_title FROM enquiries e
     LEFT JOIN listings l ON l.id = e.listing_id
     WHERE e.buyer_id = ?
     ORDER BY e.created_at DESC LIMIT 5",
    [$buyer['id']]
);

$recentQuotes = Database::fetchAll(
    "SELECT * FROM quotations WHERE buyer_id = ? ORDER BY created_at DESC LIMIT 3",
    [$buyer['id']]
);

$page_title = 'Buyer Dashboard';
include INC_PATH . '/header.php';
?>
<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="portal-content">
    <?= render_flash() ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-700 text-navy mb-0">Hi, <?= h(auth_user_name()) ?></h4>
            <p class="text-muted small mb-0">Your burial plot journey, all in one place.</p>
        </div>
        <a href="<?= pg_url('browse_listings.php') ?>" class="btn btn-gold btn-sm">
            <i class="fas fa-search me-1"></i>Browse Listings
        </a>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['icon' => 'fa-heart',         'label' => 'Saved Listings',  'value' => $savedCount,   'color' => '#E53E3E', 'link' => 'buyer/saved.php'],
            ['icon' => 'fa-envelope',      'label' => 'Enquiries Sent',  'value' => $enquiryCount, 'color' => '#3182CE', 'link' => 'buyer/enquiries.php'],
            ['icon' => 'fa-file-invoice',  'label' => 'Quote Requests',  'value' => $quoteCount,   'color' => '#D69E2E', 'link' => 'buyer/quotes.php'],
            ['icon' => 'fa-clipboard-list','label' => 'Funeral Plans',   'value' => 0,             'color' => '#38A169', 'link' => 'buyer/planner.php'],
        ];
        foreach ($cards as $c):
        ?>
        <div class="col-sm-6 col-lg-3">
            <a href="<?= pg_url($c['link']) ?>" class="text-decoration-none">
                <div class="admin-stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value"><?= $c['value'] ?></div>
                            <div class="stat-label"><?= $c['label'] ?></div>
                        </div>
                        <div class="stat-icon" style="background:<?= $c['color'] ?>18;color:<?= $c['color'] ?>">
                            <i class="fas <?= $c['icon'] ?>"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <!-- Saved Listings -->
        <div class="col-lg-7">
            <div class="pg-card mb-4">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-600 mb-0"><i class="fas fa-heart me-2 text-danger"></i>Saved Listings</h6>
                    <a href="<?= pg_url('buyer/saved.php') ?>" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <?php if ($savedListings): ?>
                <div class="row g-2 p-3">
                    <?php foreach ($savedListings as $listing): ?>
                    <div class="col-sm-6">
                        <?php include INC_PATH . '/partials/listing_card.php'; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="p-4 text-center text-muted small">
                    <i class="far fa-heart fa-2x mb-2"></i>
                    <p>No saved listings yet. <a href="<?= pg_url('browse_listings.php') ?>">Browse listings</a> and tap the heart icon to save.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right column -->
        <div class="col-lg-5">
            <!-- Recent Enquiries -->
            <div class="pg-card mb-4">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-600 mb-0">Recent Enquiries</h6>
                    <a href="<?= pg_url('buyer/enquiries.php') ?>" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="p-3">
                    <?php foreach ($recentEnquiries as $e): ?>
                    <div class="d-flex gap-2 pb-2 mb-2 border-bottom align-items-start">
                        <div class="nav-avatar flex-shrink-0" style="font-size:.75rem">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div>
                            <div class="small fw-500"><?= h(substr($e['listing_title'] ?? 'General Enquiry', 0, 35)) ?></div>
                            <div class="text-muted" style="font-size:.75rem"><?= time_ago($e['created_at']) ?></div>
                        </div>
                        <span class="status-pill <?= $e['status'] === 'resolved' ? 'active' : ($e['status'] === 'new' ? 'pending' : 'draft') ?> ms-auto"><?= ucfirst($e['status']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!$recentEnquiries): ?>
                        <p class="text-muted small text-center py-2">No enquiries yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Planner CTA -->
            <div class="pg-card p-4" style="border-left:3px solid var(--pg-gold)">
                <div class="trust-icon mb-2" style="width:36px;height:36px;font-size:.9rem"><i class="fas fa-clipboard-list"></i></div>
                <h6 class="fw-600 mb-1">Plan Your Funeral</h6>
                <p class="text-muted small mb-3">Build a personalised plan with itemised services. Request quotes from verified providers.</p>
                <a href="<?= pg_url('diy_funeral_planner.php') ?>" class="btn btn-outline-gold btn-sm">Open Planner</a>
            </div>
        </div>
    </div>
</div>
</div>
<?php include INC_PATH . '/footer.php'; ?>
