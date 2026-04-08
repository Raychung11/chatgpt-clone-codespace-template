<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_admin();

$statusFilter = clean($_GET['status'] ?? '');
$typeFilter   = clean($_GET['type']   ?? '');
$q            = clean($_GET['q']      ?? '');
$page         = max(1, clean_int($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];
if ($statusFilter) { $where[] = 'l.status = ?';     $params[] = $statusFilter; }
if ($typeFilter)   { $where[] = 'lt.slug = ?';       $params[] = $typeFilter; }
if ($q)            { $where[] = '(l.title LIKE ? OR l.listing_code LIKE ? OR mp.name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }

$sql = "SELECT l.*, lt.label_en AS type_label, mp.name AS park_name, up.full_name AS seller_name
        FROM listings l
        LEFT JOIN listing_types lt ON lt.id = l.listing_type_id
        LEFT JOIN memorial_parks mp ON mp.id = l.park_id
        LEFT JOIN sellers s ON s.id = l.seller_id
        LEFT JOIN user_profiles up ON up.user_id = s.user_id
        WHERE " . implode(' AND ', $where);

$total   = (int)(Database::fetchOne("SELECT COUNT(*) c FROM ($sql) x", $params)['c'] ?? 0);
$perPage = ADMIN_PER_PAGE;
$pages   = (int)ceil($total / $perPage);
$offset  = ($page - 1) * $perPage;
$listings = Database::fetchAll("$sql ORDER BY l.created_at DESC LIMIT $perPage OFFSET $offset", $params);

$statusCounts = [];
foreach (['active','pending_review','draft','sold','rejected','expired'] as $s) {
    $statusCounts[$s] = (int)(Database::fetchOne("SELECT COUNT(*) c FROM listings WHERE status = ?", [$s])['c'] ?? 0);
}

$page_title = 'Manage Listings';
$body_class = 'admin-layout';
include INC_PATH . '/header.php';
?>
<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="admin-main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-700 text-navy mb-0">Listings</h4>
        <div class="small text-muted"><?= number_format($total) ?> total</div>
    </div>

    <!-- Status tabs -->
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="<?= pg_url('admin/listings.php') ?>" class="btn btn-sm <?= !$statusFilter ? 'btn-navy' : 'btn-outline-secondary' ?>">All (<?= array_sum($statusCounts) ?>)</a>
        <?php foreach ($statusCounts as $s => $c): ?>
            <a href="?status=<?= $s ?>" class="btn btn-sm <?= $statusFilter === $s ? 'btn-navy' : 'btn-outline-secondary' ?>">
                <?= ucwords(str_replace('_', ' ', $s)) ?> (<?= $c ?>)
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Search bar -->
    <form method="GET" class="d-flex gap-2 mb-3">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Search title, code, park…" value="<?= h($q) ?>" style="max-width:280px">
        <input type="hidden" name="status" value="<?= h($statusFilter) ?>">
        <button type="submit" class="btn btn-sm btn-outline-secondary">Search</button>
    </form>

    <div class="pg-card">
        <div class="table-responsive">
            <table class="table admin-table mb-0">
                <thead>
                    <tr><th>Code</th><th>Title</th><th>Type</th><th>Status</th><th>Verification</th><th>Price</th><th>Seller</th><th>Date</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($listings as $l): ?>
                <tr>
                    <td class="small text-muted fw-500"><?= h($l['listing_code']) ?></td>
                    <td>
                        <div class="fw-500 small"><?= h(substr($l['title'], 0, 45)) ?></div>
                        <div class="text-muted" style="font-size:.72rem"><?= h($l['city']) ?>, <?= h($l['state']) ?></div>
                    </td>
                    <td class="small"><?= h($l['type_label'] ?? '—') ?></td>
                    <td><span class="status-pill <?= $l['status'] ?>"><?= ucwords(str_replace('_',' ',$l['status'])) ?></span></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:60px">
                                <div class="verification-bar">
                                    <div class="bar-fill <?= $l['verification_score'] >= 90 ? 'verified' : ($l['verification_score'] >= 60 ? 'partial' : 'pending') ?>" style="width:<?= $l['verification_score'] ?>%"></div>
                                </div>
                            </div>
                            <span class="small text-muted"><?= $l['verification_score'] ?>%</span>
                        </div>
                    </td>
                    <td class="small fw-500"><?= $l['asking_price'] ? 'RM ' . number_format($l['asking_price']) : 'POQ' ?></td>
                    <td class="small"><?= h($l['seller_name'] ?? '—') ?></td>
                    <td class="small text-muted"><?= format_date($l['created_at'], 'd M') ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="<?= pg_url('admin/listing_review.php?id=' . $l['id']) ?>" class="btn btn-sm btn-outline-gold" title="Review"><i class="fas fa-search-plus"></i></a>
                            <?php if ($l['status'] === LISTING_ACTIVE): ?>
                                <a href="<?= listing_url($l['slug']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank" title="View"><i class="fas fa-eye"></i></a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$listings): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No listings found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        <?= pagination_links(['rows' => $listings, 'total' => $total, 'pages' => $pages, 'page' => $page, 'per_page' => $perPage, 'has_prev' => $page > 1, 'has_next' => $page < $pages], pg_url('admin/listings.php') . '?' . http_build_query(array_diff_key($_GET, ['page' => '']))) ?>
    </div>
</div>
</div>
<?php include INC_PATH . '/footer.php'; ?>
