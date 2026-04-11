<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_role(ROLE_BUYER, '/register.php?type=buyer');

$buyer = Database::fetchOne('SELECT * FROM buyers WHERE user_id = ?', [auth_user_id()]);
if (!$buyer) redirect('/register.php?type=buyer');

$page    = max(1, clean_int($_GET['page'] ?? 1));
$pp      = 10;
$total   = (int)(Database::fetchOne('SELECT COUNT(*) c FROM quotations WHERE buyer_id = ?', [$buyer['id']])['c'] ?? 0);
$pages   = (int)ceil($total / $pp);
$offset  = ($page - 1) * $pp;

$quotes = Database::fetchAll(
    'SELECT * FROM quotations WHERE buyer_id = ? ORDER BY created_at DESC LIMIT ' . $pp . ' OFFSET ' . $offset,
    [$buyer['id']]
);

// Pre-load items for each quote on this page
$quoteIds   = array_column($quotes, 'id');
$itemsByQuote = [];
if ($quoteIds) {
    $placeholders = implode(',', array_fill(0, count($quoteIds), '?'));
    $items = Database::fetchAll(
        "SELECT * FROM quotation_items WHERE quotation_id IN ($placeholders) ORDER BY sort_order, id",
        $quoteIds
    );
    foreach ($items as $item) {
        $itemsByQuote[$item['quotation_id']][] = $item;
    }
}

$statusClasses = [
    'draft'     => 'secondary',
    'submitted' => 'primary',
    'in_review' => 'warning',
    'quoted'    => 'info',
    'accepted'  => 'success',
    'rejected'  => 'danger',
    'expired'   => 'secondary',
];

$statusLabels = [
    'draft'     => is_lang('zh') ? '草稿' : 'Draft',
    'submitted' => is_lang('zh') ? '已提交' : 'Submitted',
    'in_review' => is_lang('zh') ? '审核中' : 'In Review',
    'quoted'    => is_lang('zh') ? '已报价' : 'Quoted',
    'accepted'  => is_lang('zh') ? '已接受' : 'Accepted',
    'rejected'  => is_lang('zh') ? '已拒绝' : 'Rejected',
    'expired'   => is_lang('zh') ? '已过期' : 'Expired',
];

$page_title = __('buyer.my_quotes');
include INC_PATH . '/header.php';
?>
<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="portal-content">
    <?= render_flash() ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-700 text-navy mb-0"><i class="fas fa-file-invoice me-2 text-gold"></i><?= _e('buyer.my_quotes') ?></h4>
            <p class="text-muted small mb-0"><?= is_lang('zh') ? '您提交的所有殡葬服务报价申请' : 'All your funeral service quote requests' ?></p>
        </div>
        <a href="<?= pg_url('request_quote.php') ?>" class="btn btn-gold btn-sm">
            <i class="fas fa-plus me-1"></i><?= is_lang('zh') ? '新报价申请' : 'New Quote Request' ?>
        </a>
    </div>

    <?php if ($quotes): ?>

    <div class="d-flex flex-column gap-3">
        <?php foreach ($quotes as $q):
            $items     = $itemsByQuote[$q['id']] ?? [];
            $itemCount = count($items);
            $statusCls = $statusClasses[$q['status']] ?? 'secondary';
            $statusLbl = $statusLabels[$q['status']]  ?? ucfirst($q['status']);
            $collapseId = 'quote_items_' . $q['id'];
        ?>
        <div class="pg-card">
            <!-- Quote header row -->
            <div class="p-3 d-flex flex-wrap align-items-center gap-3">
                <!-- Reference + mode -->
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="fw-700 text-navy"><?= h($q['quote_code']) ?></span>
                        <?php if ($q['mode'] === 'emergency'): ?>
                            <span class="badge bg-danger" style="font-size:.65rem"><i class="fas fa-bolt me-1"></i><?= is_lang('zh') ? '紧急' : 'Urgent' ?></span>
                        <?php endif; ?>
                        <span class="badge bg-<?= $statusCls ?>"><?= h($statusLbl) ?></span>
                    </div>
                    <div class="text-muted small">
                        <i class="fas fa-calendar-alt me-1"></i><?= date('d M Y, g:ia', strtotime($q['created_at'])) ?>
                        <?php if ($q['event_date']): ?>
                            &nbsp;·&nbsp;<i class="fas fa-star-of-life me-1"></i><?= date('d M Y', strtotime($q['event_date'])) ?>
                        <?php endif; ?>
                        <?php if ($q['event_location']): ?>
                            &nbsp;·&nbsp;<i class="fas fa-map-marker-alt me-1"></i><?= h($q['event_location']) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Total + item count -->
                <div class="text-end">
                    <?php if ($q['total'] > 0): ?>
                        <div class="fw-700 text-navy">RM <?= number_format($q['total'], 2) ?></div>
                        <div class="text-muted small"><?= $itemCount ?> <?= is_lang('zh') ? '项服务' : ($itemCount === 1 ? 'item' : 'items') ?></div>
                    <?php elseif ($itemCount > 0): ?>
                        <div class="text-muted small"><?= $itemCount ?> <?= is_lang('zh') ? '项服务' : ($itemCount === 1 ? 'item' : 'items') ?></div>
                    <?php else: ?>
                        <div class="text-muted small"><?= is_lang('zh') ? '按询价' : 'POQ' ?></div>
                    <?php endif; ?>
                </div>

                <!-- Toggle items -->
                <?php if ($itemCount): ?>
                <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse"
                        data-bs-target="#<?= $collapseId ?>" aria-expanded="false">
                    <i class="fas fa-list me-1"></i><?= is_lang('zh') ? '查看明细' : 'View Items' ?>
                </button>
                <?php endif; ?>
            </div>

            <!-- Optional notes -->
            <?php if ($q['notes']): ?>
            <div class="px-3 pb-2">
                <p class="text-muted small mb-0"><i class="fas fa-sticky-note me-1"></i><?= h($q['notes']) ?></p>
            </div>
            <?php endif; ?>

            <!-- Collapsible items table -->
            <?php if ($itemCount): ?>
            <div class="collapse" id="<?= $collapseId ?>">
                <div class="border-top">
                    <table class="table table-sm mb-0" style="font-size:.85rem">
                        <thead class="table-light">
                            <tr>
                                <th><?= is_lang('zh') ? '服务 / 项目' : 'Service / Item' ?></th>
                                <th><?= is_lang('zh') ? '类别' : 'Category' ?></th>
                                <th class="text-end"><?= is_lang('zh') ? '单价' : 'Unit Price' ?></th>
                                <th class="text-end"><?= is_lang('zh') ? '数量' : 'Qty' ?></th>
                                <th class="text-end"><?= is_lang('zh') ? '小计' : 'Subtotal' ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div class="fw-500"><?= h($item['item_name']) ?></div>
                                <?php if ($item['item_description']): ?>
                                    <div class="text-muted" style="font-size:.78rem"><?= h($item['item_description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?= h($item['category'] ?? '—') ?></td>
                            <td class="text-end"><?= $item['unit_price'] ? 'RM ' . number_format($item['unit_price'], 2) : '—' ?></td>
                            <td class="text-end"><?= (int)$item['quantity'] ?></td>
                            <td class="text-end fw-500"><?= $item['subtotal'] ? 'RM ' . number_format($item['subtotal'], 2) : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <?php if ($q['total'] > 0): ?>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="text-end fw-600"><?= is_lang('zh') ? '合计' : 'Total' ?></td>
                                <td class="text-end fw-700 text-navy">RM <?= number_format($q['total'], 2) ?></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Status-specific action bar -->
            <?php if (in_array($q['status'], ['submitted','in_review'])): ?>
            <div class="px-3 py-2 border-top bg-light rounded-bottom" style="font-size:.8rem">
                <i class="fas fa-clock me-1 text-warning"></i>
                <?= is_lang('zh') ? '您的申请正在处理中。我们将通过 WhatsApp 或电子邮件与您联系。' : 'Your request is being processed. We\'ll contact you via WhatsApp or email.' ?>
            </div>
            <?php elseif ($q['status'] === 'quoted'): ?>
            <div class="px-3 py-2 border-top bg-light rounded-bottom d-flex align-items-center justify-content-between gap-2" style="font-size:.8rem">
                <span class="text-success fw-500"><i class="fas fa-check-circle me-1"></i><?= is_lang('zh') ? '报价已就绪——请查看上方明细。' : 'Quote ready — review the items above.' ?></span>
                <a href="<?= whatsapp_link('My quote reference is ' . $q['quote_code'] . '. I\'d like to proceed.') ?>"
                   class="btn btn-whatsapp btn-sm" target="_blank" rel="noopener">
                    <i class="fab fa-whatsapp me-1"></i><?= is_lang('zh') ? '确认接受' : 'Confirm via WhatsApp' ?>
                </a>
            </div>
            <?php elseif ($q['status'] === 'accepted'): ?>
            <div class="px-3 py-2 border-top rounded-bottom" style="font-size:.8rem;background:var(--pg-verified-pale,#f0fff4)">
                <i class="fas fa-check-double me-1 text-success"></i>
                <?= is_lang('zh') ? '已接受。我们的团队将协调服务安排，感谢您的信任。' : 'Accepted. Our team will coordinate service arrangements. Thank you for choosing us.' ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <div class="mt-4">
        <?= pagination_links([
            'rows'     => $quotes,
            'total'    => $total,
            'pages'    => $pages,
            'page'     => $page,
            'per_page' => $pp,
            'has_prev' => $page > 1,
            'has_next' => $page < $pages,
        ], pg_url('buyer/quotes.php')) ?>
    </div>

    <?php else: ?>
    <div class="text-center py-5">
        <i class="fas fa-file-invoice fa-4x text-muted mb-4"></i>
        <h5 class="text-muted"><?= is_lang('zh') ? '暂无报价申请' : 'No quote requests yet' ?></h5>
        <p class="text-muted small"><?= is_lang('zh') ? '使用规划工具建立您的服务清单，然后申请报价。' : 'Use the planner to build your service list, then request a quote.' ?></p>
        <div class="d-flex justify-content-center gap-2 mt-3">
            <a href="<?= pg_url('diy_funeral_planner.php') ?>" class="btn btn-outline-gold"><?= _e('buyer.open_planner') ?></a>
            <a href="<?= pg_url('request_quote.php') ?>" class="btn btn-gold"><?= is_lang('zh') ? '直接申请报价' : 'Request Quote Directly' ?></a>
        </div>
    </div>
    <?php endif; ?>

</div>
</div>
<?php include INC_PATH . '/footer.php'; ?>
