<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_role(ROLE_SELLER, '/register.php?type=seller');

$seller = Database::fetchOne('SELECT * FROM sellers WHERE user_id = ?', [auth_user_id()]);
if (!$seller) redirect('/register.php?type=seller');

$statusFilter = clean($_GET['status'] ?? '');
$page         = max(1, clean_int($_GET['page'] ?? 1));

$where  = ['l.seller_id = ?'];
$params = [$seller['id']];
if ($statusFilter) { $where[] = 'l.status = ?'; $params[] = $statusFilter; }

$sql   = "SELECT l.*, lt.label_en AS type_label FROM listings l LEFT JOIN listing_types lt ON lt.id = l.listing_type_id WHERE " . implode(' AND ', $where);
$total = (int)(Database::fetchOne("SELECT COUNT(*) c FROM ($sql) x", $params)['c'] ?? 0);
$pp    = 10;
$pages = (int)ceil($total / $pp);
$offset= ($page - 1) * $pp;
$listings = Database::fetchAll("$sql ORDER BY l.updated_at DESC LIMIT $pp OFFSET $offset", $params);

$page_title = __('seller.my_listings');
include INC_PATH . '/header.php';
?>
<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="portal-content">
    <?= render_flash() ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-700 text-navy mb-0"><?= _e('seller.my_listings') ?></h4>
        <a href="<?= pg_url('seller/new_listing.php') ?>" class="btn btn-gold btn-sm"><i class="fas fa-plus me-1"></i><?= _e('seller.new_listing') ?></a>
    </div>

    <!-- Status filter tabs -->
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="<?= pg_url('seller/my_listings.php') ?>" class="btn btn-sm <?= !$statusFilter ? 'btn-navy' : 'btn-outline-secondary' ?>"><?= _e('misc.all') ?> (<?= $total ?>)</a>
        <?php foreach (['active','pending_review','draft','sold'] as $s): ?>
            <a href="?status=<?= $s ?>" class="btn btn-sm <?= $statusFilter === $s ? 'btn-navy' : 'btn-outline-secondary' ?>"><?= _e('status.' . $s) ?></a>
        <?php endforeach; ?>
    </div>

    <div class="pg-card">
        <div class="table-responsive">
            <table class="table admin-table mb-0">
                <thead><tr><th><?= _e('seller.listing_col') ?></th><th><?= _e('seller.status_col') ?></th><th><?= _e('seller.verification_col') ?></th><th><?= _e('seller.price_col') ?></th><th><?= _e('seller.views_col') ?></th><th><?= _e('seller.enquiries_col') ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($listings as $l): ?>
                <tr>
                    <td>
                        <div class="fw-500 small"><?= h(substr($l['title'], 0, 50)) ?></div>
                        <div class="text-muted" style="font-size:.72rem"><?= h($l['listing_code']) ?> &middot; <?= h($l['type_label'] ?? '—') ?></div>
                    </td>
                    <td><span class="status-pill <?= $l['status'] ?>"><?= ucwords(str_replace('_',' ',$l['status'])) ?></span></td>
                    <td>
                        <?= listing_badge_html($l['badge_status']) ?>
                        <div class="small text-muted"><?= $l['verification_score'] ?>%</div>
                    </td>
                    <td class="small fw-500"><?= $l['asking_price'] ? format_currency($l['asking_price']) : 'POQ' ?></td>
                    <td class="small text-muted"><?= number_format($l['view_count']) ?></td>
                    <td class="small text-muted"><?= number_format($l['inquiry_count']) ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <?php if ($l['status'] === LISTING_ACTIVE): ?>
                                <a href="<?= listing_url($l['slug']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="fas fa-eye"></i></a>
                            <?php endif; ?>
                            <a href="<?= pg_url('seller/edit_listing.php?id=' . $l['id']) ?>" class="btn btn-sm btn-outline-gold"><i class="fas fa-edit"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$listings): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4"><?= _e('seller.no_listings_found') ?> <a href="<?= pg_url('seller/new_listing.php') ?>"><?= _e('seller.create_first') ?></a>.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">
        <?= pagination_links(['rows'=>$listings,'total'=>$total,'pages'=>$pages,'page'=>$page,'per_page'=>$pp,'has_prev'=>$page>1,'has_next'=>$page<$pages], pg_url('seller/my_listings.php') . '?' . http_build_query(array_diff_key($_GET, ['page'=>'']))) ?>
    </div>
</div>
</div>
<?php include INC_PATH . '/footer.php'; ?>
