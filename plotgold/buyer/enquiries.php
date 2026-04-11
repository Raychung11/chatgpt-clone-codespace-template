<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_role(ROLE_BUYER, '/register.php?type=buyer');

$buyer = Database::fetchOne('SELECT * FROM buyers WHERE user_id = ?', [auth_user_id()]);
if (!$buyer) redirect('/register.php?type=buyer');

$page   = max(1, clean_int($_GET['page'] ?? 1));
$pp     = 12;
$total  = (int)(Database::fetchOne('SELECT COUNT(*) c FROM enquiries WHERE buyer_id = ?', [$buyer['id']])['c'] ?? 0);
$pages  = (int)ceil($total / $pp);
$offset = ($page - 1) * $pp;

$enquiries = Database::fetchAll(
    "SELECT e.*, l.title AS listing_title, l.slug AS listing_slug, mp.name AS park_name
     FROM enquiries e
     LEFT JOIN listings l ON l.id = e.listing_id
     LEFT JOIN memorial_parks mp ON mp.id = l.park_id
     WHERE e.buyer_id = ?
     ORDER BY e.created_at DESC
     LIMIT $pp OFFSET $offset",
    [$buyer['id']]
);

$statusClass = [
    'new'         => 'primary',
    'assigned'    => 'info',
    'in_progress' => 'warning',
    'resolved'    => 'success',
    'closed'      => 'secondary',
    'spam'        => 'danger',
];
$statusLabel = [
    'new'         => is_lang('zh') ? '新询价' : 'New',
    'assigned'    => is_lang('zh') ? '已分配' : 'Assigned',
    'in_progress' => is_lang('zh') ? '处理中' : 'In Progress',
    'resolved'    => is_lang('zh') ? '已解决' : 'Resolved',
    'closed'      => is_lang('zh') ? '已关闭' : 'Closed',
    'spam'        => is_lang('zh') ? '垃圾信息' : 'Spam',
];

$page_title = __('buyer.my_enquiries');
include INC_PATH . '/header.php';
?>
<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="portal-content">
    <?= render_flash() ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-700 text-navy mb-0"><i class="fas fa-envelope me-2" style="color:var(--pg-gold)"></i><?= _e('buyer.my_enquiries') ?></h4>
            <p class="text-muted small mb-0"><?= is_lang('zh') ? '您发送给卖家和服务商的所有询价记录' : 'All enquiries you have sent to sellers and providers' ?></p>
        </div>
        <a href="<?= pg_url('browse_listings.php') ?>" class="btn btn-gold btn-sm">
            <i class="fas fa-search me-1"></i><?= _e('buyer.browse_cta') ?>
        </a>
    </div>

    <?php if ($enquiries): ?>
    <div class="pg-card">
        <div class="table-responsive">
            <table class="table admin-table mb-0">
                <thead>
                    <tr>
                        <th><?= is_lang('zh') ? '编号' : 'Reference' ?></th>
                        <th><?= is_lang('zh') ? '房源 / 主题' : 'Listing / Subject' ?></th>
                        <th><?= is_lang('zh') ? '类型' : 'Type' ?></th>
                        <th><?= is_lang('zh') ? '状态' : 'Status' ?></th>
                        <th><?= is_lang('zh') ? '日期' : 'Date' ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($enquiries as $e): ?>
                <tr>
                    <td>
                        <div class="fw-500 small"><?= h($e['enquiry_code']) ?></div>
                        <?php if ($e['priority'] === 'urgent'): ?>
                            <span class="badge bg-danger" style="font-size:.6rem"><?= is_lang('zh') ? '紧急' : 'Urgent' ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($e['listing_title']): ?>
                            <div class="fw-500 small"><?= h(substr($e['listing_title'], 0, 45)) ?></div>
                            <?php if ($e['park_name']): ?>
                                <div class="text-muted" style="font-size:.75rem"><?= h($e['park_name']) ?></div>
                            <?php endif; ?>
                        <?php elseif ($e['subject']): ?>
                            <div class="fw-500 small"><?= h(substr($e['subject'], 0, 45)) ?></div>
                        <?php else: ?>
                            <div class="text-muted small"><?= is_lang('zh') ? '一般询价' : 'General Enquiry' ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-light text-muted border" style="font-size:.7rem">
                            <?= h(ucwords(str_replace('_', ' ', $e['enquiry_type']))) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-<?= $statusClass[$e['status']] ?? 'secondary' ?>">
                            <?= h($statusLabel[$e['status']] ?? ucfirst($e['status'])) ?>
                        </span>
                    </td>
                    <td class="small text-muted"><?= time_ago($e['created_at']) ?></td>
                    <td>
                        <?php if ($e['listing_slug'] && $e['listing_id']): ?>
                            <a href="<?= listing_url($e['listing_slug']) ?>"
                               class="btn btn-sm btn-outline-secondary" target="_blank"
                               title="<?= is_lang('zh') ? '查看房源' : 'View Listing' ?>">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        <?= pagination_links([
            'rows'     => $enquiries,
            'total'    => $total,
            'pages'    => $pages,
            'page'     => $page,
            'per_page' => $pp,
            'has_prev' => $page > 1,
            'has_next' => $page < $pages,
        ], pg_url('buyer/enquiries.php')) ?>
    </div>

    <?php else: ?>
    <div class="text-center py-5">
        <i class="fas fa-envelope-open fa-4x text-muted mb-4"></i>
        <h5 class="text-muted"><?= is_lang('zh') ? '暂无询价记录' : 'No enquiries yet' ?></h5>
        <p class="text-muted small"><?= is_lang('zh') ? '浏览房源并向卖家发送询价，即可在此查看记录。' : 'Browse listings and send an enquiry to a seller to see your history here.' ?></p>
        <a href="<?= pg_url('browse_listings.php') ?>" class="btn btn-gold mt-2"><?= _e('buyer.browse_cta') ?></a>
    </div>
    <?php endif; ?>

</div>
</div>
<?php include INC_PATH . '/footer.php'; ?>
