<?php
/**
 * Admin – Rewards Management
 * /admin/pages/rewards.php
 */

$pageTitle  = 'Rewards';
$activePage = 'rewards';

// Toggle status
if (isset($_GET['toggle']) && Auth::validateCsrfToken($_GET['_csrf'] ?? '')) {
    $tid = sanitize_int($_GET['toggle']);
    $r = Database::fetchOne('SELECT id, status FROM rewards WHERE id = ?', [$tid]);
    if ($r) {
        $new = $r['status'] === 'active' ? 'inactive' : 'active';
        Database::execute('UPDATE rewards SET status = ? WHERE id = ?', [$new, $tid]);
        flash('success', 'Reward status updated.');
    }
    header('Location: /admin/rewards'); exit;
}

$rewards = Database::fetchAll(
    'SELECT r.*, o.name AS outlet_name,
            COUNT(rr.id) AS total_redeemed
     FROM rewards r
     LEFT JOIN outlets o ON o.id = r.outlet_id
     LEFT JOIN reward_redemptions rr ON rr.reward_id = r.id
     GROUP BY r.id
     ORDER BY r.created_at DESC'
);

require __DIR__ . '/../layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted small"><?= count($rewards) ?> rewards</span>
    <a href="/admin/rewards/create" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Add Reward
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Reward</th>
                        <th>Type</th>
                        <th>Points Required</th>
                        <th>Stock</th>
                        <th>Redeemed</th>
                        <th>Valid Until</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rewards as $rw): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($rw['name']) ?></div>
                            <?php if ($rw['outlet_name']): ?>
                            <div class="text-muted" style="font-size:.78rem;"><i class="bi bi-shop me-1"></i><?= htmlspecialchars($rw['outlet_name']) ?></div>
                            <?php else: ?>
                            <div class="text-muted" style="font-size:.78rem;">All Outlets</div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-info"><?= ucfirst($rw['reward_type']) ?></span></td>
                        <td><strong><?= number_format($rw['points_required']) ?></strong> pts</td>
                        <td>
                            <?php if ($rw['stock'] === null): ?>
                            <span class="text-muted">Unlimited</span>
                            <?php else: ?>
                            <?= number_format($rw['stock']) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= number_format((int)$rw['total_redeemed']) ?></td>
                        <td>
                            <?php if ($rw['valid_until']): ?>
                            <?= date('d M Y', strtotime($rw['valid_until'])) ?>
                            <?php if (strtotime($rw['valid_until']) < time()): ?>
                            <span class="badge bg-danger ms-1">Expired</span>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="text-muted">No Expiry</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $rw['status']==='active'?'success':($rw['status']==='out_of_stock'?'warning':'secondary') ?>">
                                <?= ucfirst(str_replace('_',' ',$rw['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/rewards/edit?id=<?= $rw['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-2"><i class="bi bi-pencil"></i></a>
                                <a href="/admin/rewards?toggle=<?= $rw['id'] ?>&_csrf=<?= Auth::generateCsrfToken() ?>"
                                   class="btn btn-sm btn-outline-<?= $rw['status']==='active'?'warning':'success' ?> py-0 px-2"
                                   onclick="return confirm('Toggle reward status?')">
                                    <i class="bi bi-toggle-<?= $rw['status']==='active'?'on':'off' ?>"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rewards)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No rewards yet. <a href="/admin/rewards/create">Create one.</a></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
