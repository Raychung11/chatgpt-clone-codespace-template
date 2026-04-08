<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_role(ROLE_SELLER, '/register.php?type=seller');

$seller = Database::fetchOne('SELECT * FROM sellers WHERE user_id = ?', [auth_user_id()]);
if (!$seller) redirect('/register.php?type=seller');

$stats = [
    'total'    => (int)(Database::fetchOne('SELECT COUNT(*) c FROM listings WHERE seller_id = ?', [$seller['id']])['c'] ?? 0),
    'active'   => (int)(Database::fetchOne("SELECT COUNT(*) c FROM listings WHERE seller_id = ? AND status = 'active'", [$seller['id']])['c'] ?? 0),
    'pending'  => (int)(Database::fetchOne("SELECT COUNT(*) c FROM listings WHERE seller_id = ? AND status = 'pending_review'", [$seller['id']])['c'] ?? 0),
    'enquiries'=> (int)(Database::fetchOne(
        "SELECT COUNT(*) c FROM enquiries e
         JOIN listings l ON l.id = e.listing_id AND l.seller_id = ?
         WHERE e.status = 'new'", [$seller['id']])['c'] ?? 0),
];

$recentListings = Database::fetchAll(
    "SELECT l.*, lt.label_en AS type_label
     FROM listings l
     LEFT JOIN listing_types lt ON lt.id = l.listing_type_id
     WHERE l.seller_id = ?
     ORDER BY l.updated_at DESC LIMIT 8",
    [$seller['id']]
);

$recentEnquiries = Database::fetchAll(
    "SELECT e.*, l.title AS listing_title, l.slug AS listing_slug
     FROM enquiries e
     LEFT JOIN listings l ON l.id = e.listing_id
     WHERE l.seller_id = ?
     ORDER BY e.created_at DESC LIMIT 5",
    [$seller['id']]
);

$page_title = 'Seller Dashboard';
include INC_PATH . '/header.php';
?>
<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="portal-content">
    <?= render_flash() ?>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-700 text-navy mb-0">Welcome back, <?= h(auth_user_name()) ?></h4>
            <p class="text-muted small mb-0">Manage your listings and enquiries</p>
        </div>
        <a href="<?= pg_url('seller/new_listing.php') ?>" class="btn btn-gold">
            <i class="fas fa-plus me-2"></i>New Listing
        </a>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <?php
        $statCards = [
            ['icon' => 'fa-list',         'label' => 'Total Listings',   'value' => $stats['total'],    'color' => '#4299E1'],
            ['icon' => 'fa-check-circle', 'label' => 'Active',           'value' => $stats['active'],   'color' => 'var(--pg-verified)'],
            ['icon' => 'fa-clock',        'label' => 'Pending Review',   'value' => $stats['pending'],  'color' => 'var(--pg-warning)'],
            ['icon' => 'fa-envelope',     'label' => 'New Enquiries',    'value' => $stats['enquiries'],'color' => 'var(--pg-danger)'],
        ];
        foreach ($statCards as $c): ?>
        <div class="col-sm-6 col-lg-3">
            <div class="admin-stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?= $c['value'] ?></div>
                        <div class="stat-label"><?= $c['label'] ?></div>
                    </div>
                    <div class="stat-icon" style="background:<?= $c['color'] ?>22;color:<?= $c['color'] ?>">
                        <i class="fas <?= $c['icon'] ?>"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Verification status -->
    <?php if ($seller['verification_status'] !== 'verified'): ?>
    <div class="alert alert-warning d-flex align-items-center gap-3 mb-4">
        <i class="fas fa-exclamation-triangle fs-4"></i>
        <div>
            <strong>Seller account not fully verified.</strong>
            Your listings will display a "Pending" badge until your seller identity is verified.
            <a href="<?= pg_url('seller/profile.php') ?>" class="fw-500 ms-1">Complete verification →</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Listings table -->
        <div class="col-lg-8">
            <div class="pg-card">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-600 mb-0">My Listings</h6>
                    <a href="<?= pg_url('seller/my_listings.php') ?>" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table admin-table mb-0">
                        <thead><tr><th>Listing</th><th>Status</th><th>Price</th><th>Views</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($recentListings as $l): ?>
                        <tr>
                            <td>
                                <div class="fw-500 small"><?= h(substr($l['title'], 0, 45)) ?></div>
                                <div class="text-muted" style="font-size:.75rem"><?= h($l['listing_code']) ?> &middot; <?= h($l['type_label'] ?? '—') ?></div>
                            </td>
                            <td><span class="status-pill <?= $l['status'] ?>"><?= ucwords(str_replace('_', ' ', $l['status'])) ?></span></td>
                            <td class="small fw-500"><?= $l['asking_price'] ? format_currency($l['asking_price']) : 'POQ' ?></td>
                            <td class="small text-muted"><?= number_format($l['view_count']) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <?php if ($l['status'] === LISTING_ACTIVE): ?>
                                        <a href="<?= listing_url($l['slug']) ?>" class="btn btn-sm btn-outline-secondary" title="View" target="_blank"><i class="fas fa-eye"></i></a>
                                    <?php endif; ?>
                                    <a href="<?= pg_url('seller/edit_listing.php?id=' . $l['id']) ?>" class="btn btn-sm btn-outline-gold" title="Edit"><i class="fas fa-edit"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$recentListings): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No listings yet. <a href="<?= pg_url('seller/new_listing.php') ?>">Create your first listing</a>.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Enquiries -->
        <div class="col-lg-4">
            <div class="pg-card h-100">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-600 mb-0">Recent Enquiries</h6>
                    <a href="<?= pg_url('seller/enquiries.php') ?>" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="p-3">
                    <?php foreach ($recentEnquiries as $e): ?>
                    <div class="d-flex gap-3 pb-3 mb-3 border-bottom">
                        <div class="nav-avatar flex-shrink-0"><i class="fas fa-user"></i></div>
                        <div>
                            <div class="small fw-500"><?= h($e['contact_name'] ?? 'Anonymous') ?></div>
                            <div class="text-muted" style="font-size:.78rem"><?= h(substr($e['listing_title'] ?? 'General', 0, 30)) ?></div>
                            <div class="text-muted" style="font-size:.75rem"><?= time_ago($e['created_at']) ?></div>
                        </div>
                        <?php if ($e['status'] === 'new'): ?>
                            <span class="badge bg-danger ms-auto align-self-start" style="font-size:.65rem">New</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!$recentEnquiries): ?>
                        <p class="text-muted small text-center py-3">No enquiries yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<?php include INC_PATH . '/footer.php'; ?>
