<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_admin();

$statusFilter = clean($_GET['status'] ?? '');
$typeFilter   = clean($_GET['type']   ?? '');
$page         = max(1, clean_int($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];
if ($statusFilter) { $where[] = 'e.status = ?'; $params[] = $statusFilter; }
if ($typeFilter)   { $where[] = 'e.enquiry_type = ?'; $params[] = $typeFilter; }

$sql   = "SELECT e.*, l.title AS listing_title, l.slug AS listing_slug FROM enquiries e LEFT JOIN listings l ON l.id = e.listing_id WHERE " . implode(' AND ', $where);
$total = (int)(Database::fetchOne("SELECT COUNT(*) c FROM ($sql) x", $params)['c'] ?? 0);
$pp    = ADMIN_PER_PAGE;
$pages = (int)ceil($total / $pp);
$offset= ($page - 1) * $pp;
$enquiries = Database::fetchAll("$sql ORDER BY e.priority = 'urgent' DESC, e.created_at DESC LIMIT $pp OFFSET $offset", $params);

// POST: update status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();
    $eid = clean_int($_POST['enquiry_id'] ?? 0);
    $ns  = clean($_POST['new_status'] ?? '');
    if ($eid && in_array($ns, ['assigned','in_progress','resolved','closed','spam'])) {
        Database::query('UPDATE enquiries SET status = ? WHERE id = ?', [$ns, $eid]);
        flash_set(FLASH_SUCCESS, "Enquiry updated.");
        redirect('admin/enquiries.php');
    }
}

$page_title = 'Enquiries';
$body_class = 'admin-layout';
include INC_PATH . '/header.php';
?>
<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="admin-main">
    <?= render_flash() ?>
    <h4 class="fw-700 text-navy mb-4">Enquiries & Leads</h4>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="?" class="btn btn-sm <?= !$statusFilter ? 'btn-navy' : 'btn-outline-secondary' ?>">All (<?= $total ?>)</a>
        <?php foreach (['new','in_progress','resolved','closed'] as $s): ?>
            <a href="?status=<?= $s ?>" class="btn btn-sm <?= $statusFilter === $s ? 'btn-navy' : 'btn-outline-secondary' ?>"><?= ucfirst($s) ?></a>
        <?php endforeach; ?>
        <a href="?type=urgent" class="btn btn-sm <?= $typeFilter === 'urgent' ? 'btn-danger' : 'btn-outline-danger' ?>">Urgent</a>
    </div>

    <div class="pg-card">
        <div class="table-responsive">
            <table class="table admin-table mb-0">
                <thead><tr><th>Enquiry</th><th>Contact</th><th>Type</th><th>Priority</th><th>Status</th><th>Date</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($enquiries as $e): ?>
                <tr>
                    <td>
                        <div class="small fw-500"><?= h(substr($e['subject'] ?? $e['message'], 0, 45)) ?></div>
                        <?php if ($e['listing_title']): ?><div class="text-muted" style="font-size:.72rem"><?= h(substr($e['listing_title'],0,35)) ?></div><?php endif; ?>
                        <div class="text-muted" style="font-size:.7rem"><?= h($e['enquiry_code']) ?></div>
                    </td>
                    <td>
                        <div class="small fw-500"><?= h($e['contact_name'] ?? 'Anonymous') ?></div>
                        <div class="text-muted" style="font-size:.72rem"><?= h($e['contact_phone'] ?? '') ?></div>
                    </td>
                    <td class="small"><?= ucfirst($e['enquiry_type']) ?></td>
                    <td>
                        <?php if ($e['priority'] === 'urgent'): ?>
                            <span class="badge bg-danger">Urgent</span>
                        <?php elseif ($e['priority'] === 'high'): ?>
                            <span class="badge bg-warning text-dark">High</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Normal</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="status-pill <?= $e['status'] === 'new' ? 'pending' : ($e['status'] === 'resolved' ? 'active' : 'draft') ?>"><?= ucfirst($e['status']) ?></span></td>
                    <td class="small text-muted"><?= time_ago($e['created_at']) ?></td>
                    <td>
                        <form method="POST" class="d-flex gap-1">
                            <?= csrf_field() ?>
                            <input type="hidden" name="enquiry_id" value="<?= (int)$e['id'] ?>">
                            <select name="new_status" class="form-select form-select-sm" style="width:110px">
                                <?php foreach (['assigned','in_progress','resolved','closed','spam'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $e['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-gold">✓</button>
                            <?php if ($e['contact_phone']): ?>
                                <a href="<?= whatsapp_link('Hi ' . ($e['contact_name'] ?? '') . ', this is PlotGold Malaysia regarding your enquiry.', preg_replace('/[^0-9]/', '', $e['contact_phone'])) ?>"
                                   class="btn btn-sm btn-whatsapp" target="_blank" title="WhatsApp">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$enquiries): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No enquiries found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">
        <?= pagination_links(['rows'=>$enquiries,'total'=>$total,'pages'=>$pages,'page'=>$page,'per_page'=>$pp,'has_prev'=>$page>1,'has_next'=>$page<$pages], pg_url('admin/enquiries.php') . '?' . http_build_query(array_diff_key($_GET, ['page'=>'']))) ?>
    </div>
</div>
</div>
<?php include INC_PATH . '/footer.php'; ?>
