<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_admin();

// Dashboard stats
$stats = [
    'active_listings'   => (int)(Database::fetchOne("SELECT COUNT(*) c FROM listings WHERE status = 'active'")['c'] ?? 0),
    'pending_listings'  => (int)(Database::fetchOne("SELECT COUNT(*) c FROM listings WHERE status = 'pending_review'")['c'] ?? 0),
    'new_enquiries'     => (int)(Database::fetchOne("SELECT COUNT(*) c FROM enquiries WHERE status = 'new'")['c'] ?? 0),
    'pending_providers' => (int)(Database::fetchOne("SELECT COUNT(*) c FROM providers WHERE approval_status = 'pending'")['c'] ?? 0),
    'total_sellers'     => (int)(Database::fetchOne('SELECT COUNT(*) c FROM sellers')['c'] ?? 0),
    'total_buyers'      => (int)(Database::fetchOne('SELECT COUNT(*) c FROM buyers')['c'] ?? 0),
    'pending_quotes'    => (int)(Database::fetchOne("SELECT COUNT(*) c FROM quotations WHERE status IN ('submitted','in_review')")['c'] ?? 0),
    'ai_leads_new'      => (int)(Database::fetchOne("SELECT COUNT(*) c FROM ai_leads WHERE crm_status = 'new'")['c'] ?? 0),
];

// Recent pending listings
$pendingListings = Database::fetchAll(
    "SELECT l.*, lt.label_en AS type_label, up.full_name AS seller_name
     FROM listings l
     LEFT JOIN listing_types lt ON lt.id = l.listing_type_id
     LEFT JOIN sellers s ON s.id = l.seller_id
     LEFT JOIN user_profiles up ON up.user_id = s.user_id
     WHERE l.status = 'pending_review'
     ORDER BY l.created_at ASC LIMIT 8"
);

// Recent enquiries
$recentEnquiries = Database::fetchAll(
    "SELECT * FROM enquiries WHERE status = 'new' ORDER BY created_at DESC LIMIT 8"
);

// Activity chart data (last 14 days)
$activityData = Database::fetchAll(
    "SELECT DATE(created_at) AS day, COUNT(*) AS count
     FROM listings WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
     GROUP BY DATE(created_at) ORDER BY day ASC"
);

$page_title = 'Admin Dashboard';
$body_class = 'admin-layout';
include INC_PATH . '/header.php';
?>
<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="admin-main">

    <!-- Top bar -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <button class="btn btn-outline-secondary btn-sm d-lg-none me-2" onclick="toggleAdminSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <h4 class="fw-700 text-navy d-inline mb-0">Dashboard</h4>
        </div>
        <div class="d-flex gap-2">
            <span class="small text-muted"><?= date('D, d M Y') ?></span>
            <a href="<?= pg_url() ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-external-link-alt me-1"></i>View Site
            </a>
        </div>
    </div>

    <?= render_flash() ?>

    <!-- Stats Grid -->
    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['icon' => 'fa-list',          'label' => 'Active Listings',    'value' => $stats['active_listings'],  'color' => '#3182CE', 'link' => 'admin/listings.php'],
            ['icon' => 'fa-clock',         'label' => 'Pending Review',     'value' => $stats['pending_listings'], 'color' => '#D69E2E', 'link' => 'admin/listing_review.php'],
            ['icon' => 'fa-envelope',      'label' => 'New Enquiries',      'value' => $stats['new_enquiries'],    'color' => '#E53E3E', 'link' => 'admin/enquiries.php'],
            ['icon' => 'fa-briefcase',     'label' => 'Pending Providers',  'value' => $stats['pending_providers'],'color' => '#805AD5', 'link' => 'admin/providers.php'],
            ['icon' => 'fa-user-tag',      'label' => 'Total Sellers',      'value' => $stats['total_sellers'],    'color' => '#2B6CB0', 'link' => 'admin/users.php'],
            ['icon' => 'fa-user',          'label' => 'Total Buyers',       'value' => $stats['total_buyers'],     'color' => '#276749', 'link' => 'admin/users.php'],
            ['icon' => 'fa-file-invoice',  'label' => 'Pending Quotes',     'value' => $stats['pending_quotes'],   'color' => '#DD6B20', 'link' => 'admin/quotes.php'],
            ['icon' => 'fa-robot',         'label' => 'New AI Leads',       'value' => $stats['ai_leads_new'],     'color' => '#6B46C1', 'link' => 'admin/ai_leads.php'],
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
                    <?php if ($c['value'] > 0 && in_array($c['link'], ['admin/listing_review.php','admin/enquiries.php','admin/providers.php'])): ?>
                        <div class="mt-2 small" style="color:<?= $c['color'] ?>">
                            <i class="fas fa-arrow-right me-1"></i>Action needed
                        </div>
                    <?php endif; ?>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <!-- Pending Listings Table -->
        <div class="col-lg-7">
            <div class="pg-card">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-600 mb-0">
                        <i class="fas fa-clock me-2 text-warning"></i>Pending Review Queue
                        <?php if ($stats['pending_listings']): ?>
                            <span class="badge bg-warning text-dark ms-1"><?= $stats['pending_listings'] ?></span>
                        <?php endif; ?>
                    </h6>
                    <a href="<?= pg_url('admin/listing_review.php') ?>" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table admin-table mb-0">
                        <thead><tr><th>Listing</th><th>Seller</th><th>Type</th><th>Submitted</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($pendingListings as $l): ?>
                        <tr>
                            <td>
                                <div class="fw-500 small"><?= h(substr($l['title'], 0, 40)) ?></div>
                                <div class="text-muted" style="font-size:.72rem"><?= h($l['listing_code']) ?></div>
                            </td>
                            <td class="small"><?= h($l['seller_name'] ?? '—') ?></td>
                            <td class="small"><?= h($l['type_label'] ?? '—') ?></td>
                            <td class="small text-muted"><?= time_ago($l['created_at']) ?></td>
                            <td>
                                <a href="<?= pg_url('admin/listing_review.php?id=' . $l['id']) ?>" class="btn btn-sm btn-gold">Review</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$pendingListings): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No listings pending review.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Enquiries -->
        <div class="col-lg-5">
            <div class="pg-card h-100">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-600 mb-0">
                        <i class="fas fa-envelope me-2 text-danger"></i>New Enquiries
                    </h6>
                    <a href="<?= pg_url('admin/enquiries.php') ?>" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentEnquiries as $e): ?>
                    <a href="<?= pg_url('admin/enquiries.php?id=' . $e['id']) ?>" class="list-group-item list-group-item-action py-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="small fw-500"><?= h($e['contact_name'] ?? 'Anonymous') ?></div>
                                <div class="text-muted" style="font-size:.78rem"><?= h(substr($e['subject'] ?? $e['message'], 0, 50)) ?>…</div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-danger mb-1" style="font-size:.65rem">New</span>
                                <div class="text-muted" style="font-size:.72rem"><?= time_ago($e['created_at']) ?></div>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <?php if (!$recentEnquiries): ?>
                        <div class="p-4 text-center text-muted small">No new enquiries.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-3 mt-2">
        <div class="col-12">
            <div class="pg-card p-3">
                <h6 class="fw-600 mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.08em">Quick Actions</h6>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= pg_url('admin/memorial_parks.php?action=new') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-plus me-1"></i>Add Memorial Park</a>
                    <a href="<?= pg_url('admin/users.php?action=new') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-user-plus me-1"></i>Create User</a>
                    <a href="<?= pg_url('admin/settings.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-cog me-1"></i>Site Settings</a>
                    <a href="<?= pg_url('admin/activity_logs.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-history me-1"></i>Activity Logs</a>
                    <a href="<?= pg_url('admin/quotes.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-invoice me-1"></i>Manage Quotes</a>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<?php include INC_PATH . '/footer.php'; ?>
