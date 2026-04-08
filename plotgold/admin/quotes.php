<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_admin();

$statusFilter = clean($_GET['status'] ?? '');
$page         = max(1, clean_int($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];
if ($statusFilter) { $where[] = "q.status = ?"; $params[] = $statusFilter; }

$sql   = "SELECT q.*, u.email AS buyer_email FROM quotations q LEFT JOIN buyers b ON b.id = q.buyer_id LEFT JOIN users u ON u.id = b.user_id WHERE " . implode(' AND ', $where);
$total = (int)(Database::fetchOne("SELECT COUNT(*) c FROM ($sql) x", $params)['c'] ?? 0);
$pp    = ADMIN_PER_PAGE;
$pages = (int)ceil($total / $pp);
$offset= ($page - 1) * $pp;
$quotes= Database::fetchAll("$sql ORDER BY q.created_at DESC LIMIT $pp OFFSET $offset", $params);

// POST: update status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();
    $qid  = clean_int($_POST['quote_id'] ?? 0);
    $nst  = clean($_POST['new_status'] ?? '');
    if ($qid && in_array($nst, ['in_review','quoted','accepted','rejected','expired'])) {
        $old = Database::fetchOne('SELECT status FROM quotations WHERE id = ?', [$qid])['status'] ?? '';
        Database::query('UPDATE quotations SET status = ? WHERE id = ?', [$nst, $qid]);
        Database::query('INSERT INTO quote_status_logs (quotation_id, from_status, to_status, changed_by) VALUES (?,?,?,?)',
            [$qid, $old, $nst, auth_user_id()]);
        flash_set(FLASH_SUCCESS, "Quote status updated to $nst.");
        redirect('admin/quotes.php');
    }
}

$page_title = 'Quote Management';
$body_class = 'admin-layout';
include INC_PATH . '/header.php';
?>
<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="admin-main">
    <?= render_flash() ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-700 text-navy mb-0">Quotes</h4>
        <div class="text-muted small"><?= number_format($total) ?> total</div>
    </div>

    <!-- Status tabs -->
    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php foreach (['','submitted','in_review','quoted','accepted','rejected'] as $s): ?>
            <a href="?status=<?= $s ?>" class="btn btn-sm <?= $statusFilter === $s ? 'btn-navy' : 'btn-outline-secondary' ?>">
                <?= $s ? ucfirst($s) : 'All' ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="pg-card">
        <div class="table-responsive">
            <table class="table admin-table mb-0">
                <thead><tr><th>Code</th><th>Contact</th><th>Mode</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($quotes as $qt): ?>
                <tr>
                    <td class="fw-500 small"><?= h($qt['quote_code']) ?></td>
                    <td>
                        <div class="small fw-500"><?= h($qt['contact_name'] ?? '—') ?></div>
                        <div class="text-muted" style="font-size:.72rem"><?= h($qt['contact_phone'] ?? '') ?></div>
                    </td>
                    <td>
                        <?php if ($qt['mode'] === 'emergency'): ?>
                            <span class="badge bg-danger">Urgent</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Standard</span>
                        <?php endif; ?>
                    </td>
                    <td class="small fw-500"><?= $qt['total'] > 0 ? format_currency($qt['total']) : 'TBD' ?></td>
                    <td><span class="status-pill <?= $qt['status'] === 'submitted' ? 'pending' : $qt['status'] ?>"><?= ucfirst($qt['status']) ?></span></td>
                    <td class="small text-muted"><?= time_ago($qt['created_at']) ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <form method="POST" class="d-flex gap-1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="quote_id" value="<?= (int)$qt['id'] ?>">
                                <select name="new_status" class="form-select form-select-sm" style="width:120px">
                                    <?php foreach (['in_review','quoted','accepted','rejected'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $qt['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-gold">Update</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$quotes): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No quotes found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">
        <?= pagination_links(['rows'=>$quotes,'total'=>$total,'pages'=>$pages,'page'=>$page,'per_page'=>$pp,'has_prev'=>$page>1,'has_next'=>$page<$pages], pg_url('admin/quotes.php') . '?' . http_build_query(array_diff_key($_GET, ['page'=>'']))) ?>
    </div>
</div>
</div>
<?php include INC_PATH . '/footer.php'; ?>
