<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_role(ROLE_PROVIDER, '/register.php');

$provider = Database::fetchOne(
    'SELECT p.*, u.full_name, u.email FROM providers p
     JOIN users u ON u.id = p.user_id
     WHERE p.user_id = ?',
    [auth_user_id()]
);
if (!$provider) redirect('/register.php');

$pid = (int)$provider['id'];

// Stats
$stats = [
    'services'       => (int)(Database::fetchOne('SELECT COUNT(*) c FROM provider_services WHERE provider_id = ? AND is_available = 1', [$pid])['c'] ?? 0),
    'quotes_new'     => (int)(Database::fetchOne("SELECT COUNT(*) c FROM quotations WHERE status = 'submitted' AND id IN (SELECT DISTINCT quotation_id FROM quotation_items WHERE provider_id = ?)", [$pid])['c'] ?? 0),
    'quotes_active'  => (int)(Database::fetchOne("SELECT COUNT(*) c FROM quotations WHERE status IN ('submitted','in_review','quoted') AND id IN (SELECT DISTINCT quotation_id FROM quotation_items WHERE provider_id = ?)", [$pid])['c'] ?? 0),
    'quotes_accepted'=> (int)(Database::fetchOne("SELECT COUNT(*) c FROM quotations WHERE status = 'accepted' AND id IN (SELECT DISTINCT quotation_id FROM quotation_items WHERE provider_id = ?)", [$pid])['c'] ?? 0),
];

// Recent quote requests mentioning this provider, or general quotes (no provider assigned)
$recentQuotes = Database::fetchAll(
    "SELECT q.*, GROUP_CONCAT(qi.item_name ORDER BY qi.sort_order SEPARATOR ', ') AS items_summary
     FROM quotations q
     LEFT JOIN quotation_items qi ON qi.quotation_id = q.id
     WHERE q.status IN ('submitted','in_review','quoted')
     GROUP BY q.id
     ORDER BY q.created_at DESC LIMIT 8"
);

// Service areas
$areas = Database::fetchAll(
    'SELECT DISTINCT state FROM provider_service_areas WHERE provider_id = ? ORDER BY state',
    [$pid]
);

// Services offered
$myServices = Database::fetchAll(
    'SELECT ps.*, fs.name AS service_name, sc.label_en AS category_label
     FROM provider_services ps
     JOIN funeral_services fs ON fs.id = ps.service_id
     JOIN service_categories sc ON sc.id = fs.category_id
     WHERE ps.provider_id = ? AND ps.is_available = 1
     ORDER BY sc.sort_order, fs.sort_order
     LIMIT 6',
    [$pid]
);

$page_title = 'Provider Dashboard';
$body_class = 'portal-layout';
include INC_PATH . '/header.php';
include INC_PATH . '/nav.php';
?>

<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="portal-content">
    <?= render_flash() ?>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-700 text-navy mb-0">
                <?= h($provider['business_name']) ?>
            </h4>
            <p class="text-muted small mb-0">
                Provider Portal &mdash;
                <?php if ($provider['approval_status'] === 'approved'): ?>
                    <span class="text-success"><i class="fas fa-check-circle me-1"></i>Approved Provider</span>
                <?php elseif ($provider['approval_status'] === 'pending'): ?>
                    <span class="text-warning"><i class="fas fa-clock me-1"></i>Pending Approval</span>
                <?php else: ?>
                    <span class="text-danger"><i class="fas fa-times-circle me-1"></i><?= ucfirst($provider['approval_status']) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <a href="<?= pg_url('provider/quotes.php') ?>" class="btn btn-gold position-relative">
            <i class="fas fa-file-invoice me-2"></i>Quote Requests
            <?php if ($stats['quotes_new'] > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $stats['quotes_new'] ?></span>
            <?php endif; ?>
        </a>
    </div>

    <!-- Pending approval notice -->
    <?php if ($provider['approval_status'] === 'pending'): ?>
    <div class="alert alert-warning d-flex gap-3 align-items-start mb-4">
        <i class="fas fa-hourglass-half fa-lg mt-1"></i>
        <div>
            <strong>Account Under Review</strong><br>
            <span class="small">Your provider account is being reviewed by our team. You will receive an email once approved. Typical review time: 1–2 business days.</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <?php
        $statCards = [
            ['icon' => 'fa-concierge-bell', 'label' => 'Active Services',    'value' => $stats['services'],        'color' => '#4299E1'],
            ['icon' => 'fa-inbox',          'label' => 'New Quote Requests', 'value' => $stats['quotes_new'],      'color' => 'var(--pg-danger)'],
            ['icon' => 'fa-spinner',        'label' => 'In Progress',        'value' => $stats['quotes_active'],   'color' => 'var(--pg-warning)'],
            ['icon' => 'fa-check-circle',   'label' => 'Accepted Quotes',    'value' => $stats['quotes_accepted'], 'color' => 'var(--pg-verified)'],
        ];
        foreach ($statCards as $c): ?>
        <div class="col-sm-6 col-xl-3">
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

    <div class="row g-4">
        <!-- Recent Quote Requests -->
        <div class="col-lg-8">
            <div class="pg-card">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-600 mb-0"><i class="fas fa-inbox me-2 text-gold"></i>Recent Quote Requests</h6>
                    <a href="<?= pg_url('provider/quotes.php') ?>" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <?php if ($recentQuotes): ?>
                <div class="table-responsive">
                    <table class="table admin-table mb-0">
                        <thead>
                            <tr><th>Reference</th><th>Contact</th><th>Services</th><th>Mode</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentQuotes as $q): ?>
                        <tr>
                            <td>
                                <div class="small fw-500"><?= h($q['quote_code']) ?></div>
                                <div class="text-muted" style="font-size:.73rem"><?= time_ago($q['created_at']) ?></div>
                            </td>
                            <td>
                                <div class="small fw-500"><?= h($q['contact_name'] ?? 'Anonymous') ?></div>
                                <div class="text-muted" style="font-size:.73rem"><?= h($q['contact_phone'] ?? '') ?></div>
                            </td>
                            <td class="small text-muted" style="max-width:180px">
                                <span class="text-truncate d-block"><?= h(substr($q['items_summary'] ?? '—', 0, 60)) ?></span>
                            </td>
                            <td>
                                <?php if ($q['mode'] === 'emergency'): ?>
                                    <span class="badge bg-danger">Emergency</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Standard</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-pill <?= in_array($q['status'], ['accepted']) ? 'active' : (in_array($q['status'], ['in_review','quoted']) ? 'pending' : 'draft') ?>">
                                    <?= ucfirst(str_replace('_', ' ', $q['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?= pg_url('provider/quotes.php?id=' . $q['id']) ?>" class="btn btn-sm btn-outline-gold">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-2x mb-3 opacity-25"></i>
                    <p class="mb-1">No quote requests yet.</p>
                    <p class="small">Complete your profile and add services to start receiving requests.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- My Services Summary -->
            <div class="pg-card mb-3">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-600 mb-0"><i class="fas fa-concierge-bell me-2 text-gold"></i>Active Services</h6>
                    <a href="<?= pg_url('provider/services.php') ?>" class="btn btn-sm btn-outline-secondary">Manage</a>
                </div>
                <div class="p-3">
                    <?php if ($myServices): ?>
                        <?php foreach ($myServices as $s): ?>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div>
                                <div class="small fw-500"><?= h($s['service_name']) ?></div>
                                <div class="text-muted" style="font-size:.73rem"><?= h($s['category_label']) ?></div>
                            </div>
                            <div class="text-end">
                                <?php if ($s['price_type'] === 'quote_required'): ?>
                                    <span class="badge bg-secondary" style="font-size:.68rem">Quote</span>
                                <?php else: ?>
                                    <div class="small fw-600 text-navy"><?= format_currency($s['price']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if ($stats['services'] > 6): ?>
                            <div class="text-center pt-2">
                                <a href="<?= pg_url('provider/services.php') ?>" class="small text-gold">+ <?= $stats['services'] - 6 ?> more services</a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-plus-circle fa-2x mb-2 opacity-25"></i>
                            <p class="small mb-2">No services added yet.</p>
                            <a href="<?= pg_url('provider/services.php') ?>" class="btn btn-sm btn-outline-gold">Add Services</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Service Areas -->
            <div class="pg-card">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-600 mb-0"><i class="fas fa-map-marker-alt me-2 text-gold"></i>Service Areas</h6>
                    <a href="<?= pg_url('provider/areas.php') ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                </div>
                <div class="p-3">
                    <?php if ($areas): ?>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($areas as $a): ?>
                                <span class="badge bg-light text-dark border"><?= h($a['state']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="small text-muted mb-2">No service areas set.</p>
                        <a href="<?= pg_url('provider/areas.php') ?>" class="btn btn-sm btn-outline-gold">Set Service Areas</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>
</div>

<?php include INC_PATH . '/footer.php'; ?>
