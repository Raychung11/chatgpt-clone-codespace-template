<?php
/**
 * Admin – Outlets Management
 * /admin/pages/outlets.php
 */

$pageTitle  = 'Outlets';
$activePage = 'outlets';

// Handle status toggle
if (isset($_GET['toggle']) && Auth::validateCsrfToken($_GET['_csrf'] ?? '')) {
    $tid = sanitize_int($_GET['toggle']);
    $outlet = Database::fetchOne('SELECT id, status FROM outlets WHERE id = ?', [$tid]);
    if ($outlet) {
        $new = $outlet['status'] === 'active' ? 'inactive' : 'active';
        Database::execute('UPDATE outlets SET status = ? WHERE id = ?', [$new, $tid]);
        admin_log('toggle_outlet', 'outlets', $tid, "Status: {$new}");
        flash('success', "Outlet status updated.");
    }
    header('Location: /admin/outlets');
    exit;
}

// Handle delete
if (isset($_GET['delete']) && Auth::validateCsrfToken($_GET['_csrf'] ?? '')) {
    $did = sanitize_int($_GET['delete']);
    Database::execute('DELETE FROM outlets WHERE id = ?', [$did]);
    admin_log('delete_outlet', 'outlets', $did);
    flash('success', 'Outlet deleted.');
    header('Location: /admin/outlets');
    exit;
}

$outlets = Database::fetchAll(
    'SELECT o.*, COUNT(r.id) AS total_reservations
     FROM outlets o
     LEFT JOIN reservations r ON r.outlet_id = o.id
     GROUP BY o.id
     ORDER BY o.created_at DESC'
);

require __DIR__ . '/../layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted small"><?= count($outlets) ?> outlets</span>
    <a href="/admin/outlets/create" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Add Outlet
    </a>
</div>

<div class="row g-3">
    <?php foreach ($outlets as $o): ?>
    <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <?php if ($o['image_url']): ?>
            <img src="<?= htmlspecialchars($o['image_url']) ?>" class="card-img-top" style="height:140px;object-fit:cover;">
            <?php else: ?>
            <div class="bg-light d-flex align-items-center justify-content-center" style="height:140px;font-size:2.5rem;">🏪</div>
            <?php endif; ?>
            <div class="card-body pb-2">
                <div class="d-flex justify-content-between align-items-start">
                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($o['name']) ?></h6>
                    <span class="badge bg-<?= $o['status']==='active'?'success':($o['status']==='temporarily_closed'?'warning':'secondary') ?>">
                        <?= ucfirst(str_replace('_', ' ', $o['status'])) ?>
                    </span>
                </div>
                <p class="text-muted small mb-2"><?= htmlspecialchars($o['address']) ?>, <?= htmlspecialchars($o['city']) ?></p>

                <?php if ($o['phone']): ?>
                <div class="small text-muted mb-1"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($o['phone']) ?></div>
                <?php endif; ?>
                <?php if ($o['lat'] && $o['lng']): ?>
                <div class="small text-muted mb-1">
                    <i class="bi bi-geo-alt me-1"></i>
                    <a href="https://maps.google.com/?q=<?= $o['lat'] ?>,<?= $o['lng'] ?>" target="_blank" class="text-decoration-none">
                        View on Maps
                    </a>
                </div>
                <?php endif; ?>

                <div class="small text-muted">
                    <i class="bi bi-calendar-check me-1"></i><?= number_format((int)$o['total_reservations']) ?> reservations
                </div>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0 pb-2 d-flex gap-2">
                <a href="/admin/outlets/edit?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-primary flex-fill">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
                <a href="/admin/outlets?toggle=<?= $o['id'] ?>&_csrf=<?= Auth::generateCsrfToken() ?>"
                   class="btn btn-sm btn-outline-<?= $o['status']==='active'?'warning':'success' ?>"
                   onclick="return confirm('Toggle outlet status?')">
                    <i class="bi bi-toggle-<?= $o['status']==='active'?'on':'off' ?>"></i>
                </a>
                <a href="/admin/outlets?delete=<?= $o['id'] ?>&_csrf=<?= Auth::generateCsrfToken() ?>"
                   class="btn btn-sm btn-outline-danger"
                   onclick="return confirm('Delete this outlet? This cannot be undone.')">
                    <i class="bi bi-trash"></i>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($outlets)): ?>
    <div class="col-12 text-center text-muted py-5">
        <i class="bi bi-shop display-4"></i>
        <p class="mt-2">No outlets yet. <a href="/admin/outlets/create">Add your first outlet.</a></p>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
