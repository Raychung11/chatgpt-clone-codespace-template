<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_admin();

$statusFilter = clean($_GET['status'] ?? '');
$q            = clean($_GET['q']      ?? '');

$where  = ['1=1'];
$params = [];
if ($statusFilter) { $where[] = "p.approval_status = ?"; $params[] = $statusFilter; }
if ($q) { $where[] = "(p.business_name LIKE ? OR u.email LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }

$providers = Database::fetchAll(
    "SELECT p.*, u.email, u.phone, up.full_name
     FROM providers p
     JOIN users u ON u.id = p.user_id
     LEFT JOIN user_profiles up ON up.user_id = p.user_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY p.approval_status = 'pending' DESC, p.created_at DESC",
    $params
);

// POST: approve / reject provider
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();
    $pid    = clean_int($_POST['provider_id'] ?? 0);
    $action = clean($_POST['action'] ?? '');
    if ($pid && in_array($action, ['approve', 'reject', 'suspend'])) {
        $statusMap = ['approve' => 'approved', 'reject' => 'rejected', 'suspend' => 'suspended'];
        $newStatus = $statusMap[$action];
        Database::query(
            "UPDATE providers SET approval_status = ?, approved_at = IF(? = 'approved', NOW(), approved_at), approved_by = ? WHERE id = ?",
            [$newStatus, $newStatus, auth_user_id(), $pid]
        );
        activity_log(auth_user_id(), "provider_$action", 'providers', $pid);
        flash_set(FLASH_SUCCESS, "Provider {$newStatus}.");
        redirect('admin/providers.php');
    }
}

$page_title = 'Manage Providers';
$body_class = 'admin-layout';
include INC_PATH . '/header.php';
?>
<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="admin-main">
    <?= render_flash() ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-700 text-navy mb-0">Service Providers</h4>
    </div>

    <!-- Filters -->
    <form method="GET" class="d-flex gap-2 mb-3 flex-wrap">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Search name or email…" value="<?= h($q) ?>" style="max-width:220px">
        <select name="status" class="form-select form-select-sm" style="max-width:160px" onchange="this.form.submit()">
            <option value="">All Status</option>
            <?php foreach (['pending','approved','rejected','suspended'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
    </form>

    <div class="pg-card">
        <div class="table-responsive">
            <table class="table admin-table mb-0">
                <thead><tr><th>Business</th><th>Type</th><th>Contact</th><th>Status</th><th>Joined</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($providers as $p): ?>
                <tr>
                    <td>
                        <div class="fw-500 small"><?= h($p['business_name']) ?></div>
                        <div class="text-muted" style="font-size:.72rem"><?= h($p['full_name'] ?? '') ?></div>
                    </td>
                    <td class="small"><?= ucwords(str_replace('_', ' ', $p['provider_type'])) ?></td>
                    <td>
                        <div class="small"><?= h($p['email']) ?></div>
                        <div class="text-muted" style="font-size:.72rem"><?= h($p['phone'] ?? '') ?></div>
                    </td>
                    <td><span class="status-pill <?= $p['approval_status'] === 'approved' ? 'active' : ($p['approval_status'] === 'pending' ? 'pending' : 'rejected') ?>"><?= ucfirst($p['approval_status']) ?></span></td>
                    <td class="small text-muted"><?= format_date($p['created_at'], 'd M Y') ?></td>
                    <td>
                        <form method="POST" class="d-flex gap-1">
                            <?= csrf_field() ?>
                            <input type="hidden" name="provider_id" value="<?= (int)$p['id'] ?>">
                            <?php if ($p['approval_status'] === 'pending'): ?>
                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">Approve</button>
                                <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-danger">Reject</button>
                            <?php elseif ($p['approval_status'] === 'approved'): ?>
                                <button type="submit" name="action" value="suspend" class="btn btn-sm btn-outline-warning" onclick="return confirm('Suspend this provider?')">Suspend</button>
                            <?php else: ?>
                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-outline-success">Re-Approve</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$providers): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No providers found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
<?php include INC_PATH . '/footer.php'; ?>
