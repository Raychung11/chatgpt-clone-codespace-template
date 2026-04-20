<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_admin();

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();
    $action = clean($_POST['_action'] ?? '');

    switch ($action) {

        // ── Add / Edit stock item ─────────────────────────────────────────────
        case 'save_item':
            $id       = clean_int($_POST['item_id'] ?? 0);
            $name     = clean($_POST['name'] ?? '');
            $type     = in_array($_POST['entity_type'] ?? '', ['park_section','gold_vault','custom'])
                        ? $_POST['entity_type'] : 'custom';
            $entityId = clean_int($_POST['entity_id'] ?? 0) ?: null;
            $unit     = clean($_POST['unit'] ?? 'units');
            $minStock = (float)($_POST['min_stock'] ?? 0);
            $notify   = empty($_POST['notify_admin']) ? 0 : 1;
            $notes    = clean($_POST['notes'] ?? '');

            if (!$name) { flash_set(FLASH_ERROR, 'Item name is required.'); break; }

            if ($id) {
                Database::query(
                    'UPDATE stock_items SET name=?, entity_type=?, entity_id=?, unit=?, min_stock=?, notify_admin=?, notes=? WHERE id=?',
                    [$name, $type, $entityId, $unit, $minStock, $notify, $notes, $id]
                );
                flash_set(FLASH_SUCCESS, 'Stock item updated.');
            } else {
                $initStock = (float)($_POST['initial_stock'] ?? 0);
                $newId = Database::insert(
                    'INSERT INTO stock_items (name, entity_type, entity_id, current_stock, unit, min_stock, notify_admin, notes) VALUES (?,?,?,?,?,?,?,?)',
                    [$name, $type, $entityId, $initStock, $unit, $minStock, $notify, $notes]
                );
                // Log initial stock if non-zero
                if ($initStock > 0) {
                    Database::insert(
                        'INSERT INTO stock_transactions (stock_item_id, transaction_type, quantity, stock_before, stock_after, notes, created_by) VALUES (?,?,?,?,?,?,?)',
                        [$newId, 'in', $initStock, 0, $initStock, 'Initial stock entry', auth_user_id()]
                    );
                }
                flash_set(FLASH_SUCCESS, 'Stock item created.');
            }
            break;

        // ── Enable / Disable monitoring ───────────────────────────────────────
        case 'toggle_active':
            $id = clean_int($_POST['item_id'] ?? 0);
            Database::query('UPDATE stock_items SET is_active = 1 - is_active WHERE id = ?', [$id]);
            break;

        // ── Delete (only if no transaction history) ───────────────────────────
        case 'delete_item':
            $id      = clean_int($_POST['item_id'] ?? 0);
            $txCount = (int)(Database::fetchOne(
                'SELECT COUNT(*) c FROM stock_transactions WHERE stock_item_id = ?', [$id]
            )['c'] ?? 0);
            if ($txCount > 0) {
                flash_set(FLASH_ERROR, "Cannot delete — {$txCount} transaction record(s) exist. Deactivate the item instead.");
            } else {
                Database::query('DELETE FROM stock_alerts WHERE stock_item_id = ?', [$id]);
                Database::query('DELETE FROM stock_items WHERE id = ?', [$id]);
                flash_set(FLASH_SUCCESS, 'Stock item removed.');
            }
            break;

        // ── Stock In / Stock Out / Adjustment ────────────────────────────────
        case 'add_transaction':
            $itemId  = clean_int($_POST['item_id'] ?? 0);
            $txType  = in_array($_POST['tx_type'] ?? '', ['in','out','adjustment'])
                       ? $_POST['tx_type'] : 'in';
            $qty     = abs((float)($_POST['quantity'] ?? 0));
            $txNotes = clean($_POST['tx_notes'] ?? '');
            $refType = clean($_POST['ref_type'] ?? '');
            $refId   = clean_int($_POST['ref_id'] ?? 0) ?: null;

            if ($qty <= 0) { flash_set(FLASH_ERROR, 'Quantity must be greater than zero.'); break; }

            $item = Database::fetchOne('SELECT * FROM stock_items WHERE id = ?', [$itemId]);
            if (!$item) { flash_set(FLASH_ERROR, 'Stock item not found.'); break; }

            $before = (float)$item['current_stock'];
            if ($txType === 'in') {
                $after = $before + $qty;
            } elseif ($txType === 'out') {
                if ($qty > $before) {
                    flash_set(FLASH_ERROR, "Cannot remove {$qty} {$item['unit']} — only {$before} in stock.");
                    break;
                }
                $after = $before - $qty;
            } else {
                $after = $qty; // absolute adjustment
            }

            Database::query('UPDATE stock_items SET current_stock = ? WHERE id = ?', [$after, $itemId]);
            Database::insert(
                'INSERT INTO stock_transactions (stock_item_id, transaction_type, quantity, stock_before, stock_after, reference_type, reference_id, notes, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?)',
                [$itemId, $txType, $qty, $before, $after, $refType ?: null, $refId, $txNotes, auth_user_id()]
            );

            // Fire alert when stock drops at or below threshold and not already pending
            if ($item['notify_admin'] && $after <= (float)$item['min_stock'] && $after <= $before) {
                $existing = Database::fetchOne(
                    'SELECT id FROM stock_alerts WHERE stock_item_id = ? AND is_acknowledged = 0', [$itemId]
                );
                if (!$existing) {
                    Database::insert(
                        'INSERT INTO stock_alerts (stock_item_id, stock_at_trigger, min_stock_at_trigger) VALUES (?,?,?)',
                        [$itemId, $after, $item['min_stock']]
                    );
                }
            }

            activity_log(auth_user_id(), 'stock_' . $txType, 'stock_items', $itemId,
                "qty={$qty} before={$before} after={$after}");

            $label = match($txType) {
                'in'  => 'Stock added',
                'out' => 'Stock removed',
                default => 'Stock adjusted',
            };
            flash_set(FLASH_SUCCESS, "{$label} — new level: " . number_format($after, 4) . " {$item['unit']}");
            break;

        // ── Acknowledge one alert ─────────────────────────────────────────────
        case 'acknowledge_alert':
            $alertId = clean_int($_POST['alert_id'] ?? 0);
            Database::query(
                'UPDATE stock_alerts SET is_acknowledged=1, acknowledged_by=?, acknowledged_at=NOW() WHERE id=?',
                [auth_user_id(), $alertId]
            );
            flash_set(FLASH_SUCCESS, 'Alert acknowledged.');
            break;

        // ── Acknowledge all open alerts ───────────────────────────────────────
        case 'acknowledge_all':
            Database::query(
                'UPDATE stock_alerts SET is_acknowledged=1, acknowledged_by=?, acknowledged_at=NOW() WHERE is_acknowledged=0',
                [auth_user_id()]
            );
            flash_set(FLASH_SUCCESS, 'All alerts acknowledged.');
            break;
    }

    redirect('admin/stock_control.php' . (isset($_POST['tab']) ? '?tab=' . clean($_POST['tab']) : ''));
}

// ── Load data (wrapped so page loads even before migration runs) ──────────────
$editId   = clean_int($_GET['edit'] ?? 0);
$activeTab = clean($_GET['tab'] ?? 'overview');

$migrationMissing = false;

try {
    $editItem = $editId ? Database::fetchOne('SELECT * FROM stock_items WHERE id = ?', [$editId]) : null;

    $items = Database::fetchAll('
        SELECT s.*,
            (SELECT COUNT(*) FROM stock_alerts a WHERE a.stock_item_id = s.id AND a.is_acknowledged = 0) AS open_alerts,
            (SELECT COUNT(*) FROM stock_transactions t WHERE t.stock_item_id = s.id) AS tx_count
        FROM stock_items s
        ORDER BY s.is_active DESC, s.name ASC
    ');

    $openAlerts = Database::fetchAll('
        SELECT a.*, s.name AS item_name, s.unit, s.min_stock, s.current_stock
        FROM stock_alerts a
        JOIN stock_items s ON a.stock_item_id = s.id
        WHERE a.is_acknowledged = 0
        ORDER BY a.triggered_at DESC
    ');

    $allAlerts = Database::fetchAll('
        SELECT a.*, s.name AS item_name, s.unit,
               u.full_name AS ack_by_name
        FROM stock_alerts a
        JOIN stock_items s ON a.stock_item_id = s.id
        LEFT JOIN users u ON a.acknowledged_by = u.id
        ORDER BY a.triggered_at DESC
        LIMIT 200
    ');

    $history = Database::fetchAll('
        SELECT t.*, s.name AS item_name, s.unit,
               u.full_name AS by_name
        FROM stock_transactions t
        JOIN stock_items s ON t.stock_item_id = s.id
        LEFT JOIN users u ON t.created_by = u.id
        ORDER BY t.created_at DESC
        LIMIT 150
    ');

    $parkSections = Database::fetchAll('
        SELECT ps.id, ps.name, mp.name AS park_name, ps.available_plots
        FROM park_sections ps
        JOIN memorial_parks mp ON ps.park_id = mp.id
        ORDER BY mp.name, ps.name
    ');
} catch (\Throwable $e) {
    $migrationMissing = true;
    $editItem = null;
    $items = $openAlerts = $allAlerts = $history = $parkSections = [];
}

// Summary stats
$totalItems    = count($items);
$lowStockCount = count(array_filter($items, fn($i) => $i['is_active'] && (float)$i['current_stock'] <= (float)$i['min_stock']));
$unackCount    = count($openAlerts);

// ── Page ──────────────────────────────────────────────────────────────────────
$page_title = 'Stock Control';
include __DIR__ . '/inc/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-700 mb-0">Stock Control</h4>
        <p class="text-muted small mb-0">Monitor inventory levels and receive alerts before running out of stock.</p>
    </div>
    <button class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddItem">
        <i class="fas fa-plus me-1"></i> Add Stock Item
    </button>
</div>

<?php render_flash(); ?>

<?php if ($migrationMissing): ?>
<div class="alert alert-warning">
    <i class="fas fa-database me-2"></i>
    <strong>Migration required.</strong>
    The stock control tables don't exist yet. Run
    <code>mysql -u user -p plotgold &lt; sql/006_stock_control.sql</code>
    on your database, then reload this page.
</div>
<?php endif; ?>

<?php if ($unackCount > 0): ?>
<div class="alert alert-danger d-flex align-items-center gap-3 mb-4" role="alert">
    <i class="fas fa-exclamation-triangle fa-lg flex-shrink-0"></i>
    <div class="flex-fill">
        <strong><?= $unackCount ?> low-stock alert<?= $unackCount > 1 ? 's' : '' ?> require<?= $unackCount === 1 ? 's' : '' ?> attention.</strong>
        <?php foreach ($openAlerts as $oa): ?>
            <span class="ms-2 badge bg-danger"><?= h($oa['item_name']) ?>
                — <?= number_format((float)$oa['stock_at_trigger'], 4) ?> <?= h($oa['unit']) ?> left
            </span>
        <?php endforeach; ?>
    </div>
    <form method="POST" class="flex-shrink-0">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="acknowledge_all">
        <button class="btn btn-sm btn-outline-danger">Acknowledge All</button>
    </form>
</div>
<?php endif; ?>

<!-- Summary cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="pg-card p-3 d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-10"
                 style="width:48px;height:48px;flex-shrink:0;">
                <i class="fas fa-boxes text-primary"></i>
            </div>
            <div>
                <div class="fs-4 fw-700 lh-1"><?= $totalItems ?></div>
                <div class="text-muted small">Tracked Items</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="pg-card p-3 d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center <?= $lowStockCount > 0 ? 'bg-danger bg-opacity-10' : 'bg-success bg-opacity-10' ?>"
                 style="width:48px;height:48px;flex-shrink:0;">
                <i class="fas fa-exclamation-circle <?= $lowStockCount > 0 ? 'text-danger' : 'text-success' ?>"></i>
            </div>
            <div>
                <div class="fs-4 fw-700 lh-1 <?= $lowStockCount > 0 ? 'text-danger' : '' ?>"><?= $lowStockCount ?></div>
                <div class="text-muted small">Below Minimum</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="pg-card p-3 d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center <?= $unackCount > 0 ? 'bg-warning bg-opacity-10' : 'bg-secondary bg-opacity-10' ?>"
                 style="width:48px;height:48px;flex-shrink:0;">
                <i class="fas fa-bell <?= $unackCount > 0 ? 'text-warning' : 'text-secondary' ?>"></i>
            </div>
            <div>
                <div class="fs-4 fw-700 lh-1 <?= $unackCount > 0 ? 'text-warning' : '' ?>"><?= $unackCount ?></div>
                <div class="text-muted small">Open Alerts</div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3" id="stockTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'overview' ? 'active' : '' ?>"
           href="?tab=overview">
            <i class="fas fa-th-list me-1"></i>Stock Items
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'alerts' ? 'active' : '' ?>"
           href="?tab=alerts">
            <i class="fas fa-bell me-1"></i>Alerts
            <?php if ($unackCount > 0): ?>
                <span class="badge bg-danger ms-1" style="font-size:.65rem;"><?= $unackCount ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'history' ? 'active' : '' ?>"
           href="?tab=history">
            <i class="fas fa-history me-1"></i>Transaction Log
        </a>
    </li>
</ul>

<!-- ── TAB: Stock Items ────────────────────────────────────────────────────── -->
<?php if ($activeTab === 'overview'): ?>

<?php if (empty($items)): ?>
    <div class="pg-card p-5 text-center text-muted">
        <i class="fas fa-boxes fa-2x mb-3 d-block opacity-25"></i>
        No stock items yet. Click <strong>Add Stock Item</strong> to begin.
    </div>
<?php else: ?>
<div class="pg-card p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th style="width:28%">Item</th>
                    <th>Type</th>
                    <th class="text-center">Current Stock</th>
                    <th class="text-center">Minimum</th>
                    <th class="text-center">Status</th>
                    <th style="width:22%">Quick Adjustment</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $si):
                $cur  = (float)$si['current_stock'];
                $min  = (float)$si['min_stock'];
                $pct  = $min > 0 ? min(100, round($cur / $min * 100)) : 100;
                if ($cur <= $min)       { $statusClass = 'danger';  $statusLabel = 'Low Stock'; }
                elseif ($cur <= $min * 1.5) { $statusClass = 'warning'; $statusLabel = 'Watch'; }
                else                    { $statusClass = 'success'; $statusLabel = 'OK'; }
                if (!$si['is_active'])  { $statusClass = 'secondary'; $statusLabel = 'Inactive'; }
            ?>
            <tr class="<?= !$si['is_active'] ? 'opacity-50' : '' ?>">
                <td>
                    <div class="fw-600"><?= h($si['name']) ?></div>
                    <?php if ($si['notes']): ?>
                        <div class="text-muted" style="font-size:.75rem;"><?= h(mb_strimwidth($si['notes'], 0, 60, '…')) ?></div>
                    <?php endif; ?>
                    <?php if ($si['open_alerts'] > 0): ?>
                        <span class="badge bg-danger" style="font-size:.6rem;">
                            <i class="fas fa-bell"></i> Alert
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $typeBadge = match($si['entity_type']) {
                        'gold_vault'   => ['bg-warning text-dark', 'fa-coins',    'Gold Vault'],
                        'park_section' => ['bg-primary',           'fa-tree',     'Park Section'],
                        default        => ['bg-secondary',         'fa-box',      'Custom'],
                    };
                    ?>
                    <span class="badge <?= $typeBadge[0] ?>">
                        <i class="fas <?= $typeBadge[1] ?> me-1"></i><?= $typeBadge[2] ?>
                    </span>
                </td>
                <td class="text-center">
                    <div class="fw-700 fs-5 lh-1 <?= $si['is_active'] && $cur <= $min ? 'text-danger' : '' ?>">
                        <?= number_format($cur, $si['unit'] === 'grams' ? 4 : 0) ?>
                    </div>
                    <div class="text-muted" style="font-size:.72rem;"><?= h($si['unit']) ?></div>
                    <?php if ($min > 0 && $si['is_active']): ?>
                        <div class="progress mt-1" style="height:4px;width:60px;margin:0 auto;">
                            <div class="progress-bar bg-<?= $statusClass ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <div class="fw-600"><?= number_format($min, $si['unit'] === 'grams' ? 4 : 0) ?></div>
                    <div class="text-muted" style="font-size:.72rem;"><?= h($si['unit']) ?></div>
                </td>
                <td class="text-center">
                    <span class="badge bg-<?= $statusClass ?>"><?= $statusLabel ?></span>
                </td>
                <td>
                    <?php if ($si['is_active']): ?>
                    <form method="POST" class="d-flex gap-1 align-items-center">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="add_transaction">
                        <input type="hidden" name="item_id" value="<?= $si['id'] ?>">
                        <input type="hidden" name="tab" value="overview">
                        <select name="tx_type" class="form-select form-select-sm" style="width:80px;">
                            <option value="in">+ In</option>
                            <option value="out">− Out</option>
                            <option value="adjustment">= Set</option>
                        </select>
                        <input type="number" name="quantity" min="0.0001" step="0.0001"
                               class="form-control form-control-sm" style="width:80px;"
                               placeholder="Qty" required>
                        <button class="btn btn-sm btn-primary" title="Apply">
                            <i class="fas fa-check"></i>
                        </button>
                    </form>
                    <?php else: ?>
                        <span class="text-muted small">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <div class="d-flex gap-1 justify-content-center">
                        <a href="?tab=overview&edit=<?= $si['id'] ?>"
                           class="btn btn-sm btn-outline-secondary" title="Edit settings">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form method="POST" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="toggle_active">
                            <input type="hidden" name="item_id" value="<?= $si['id'] ?>">
                            <input type="hidden" name="tab" value="overview">
                            <button class="btn btn-sm <?= $si['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                                    title="<?= $si['is_active'] ? 'Pause monitoring' : 'Resume monitoring' ?>">
                                <i class="fas <?= $si['is_active'] ? 'fa-pause' : 'fa-play' ?>"></i>
                            </button>
                        </form>
                        <?php if ($si['tx_count'] == 0): ?>
                        <form method="POST" class="d-inline"
                              onsubmit="return confirm('Delete this stock item?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="delete_item">
                            <input type="hidden" name="item_id" value="<?= $si['id'] ?>">
                            <input type="hidden" name="tab" value="overview">
                            <button class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>

            <?php if ($editId == $si['id'] && $editItem): ?>
            <tr class="table-light">
                <td colspan="7" class="p-3">
                    <strong class="small">Edit: <?= h($si['name']) ?></strong>
                    <form method="POST" class="row g-2 mt-1">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="save_item">
                        <input type="hidden" name="item_id" value="<?= $si['id'] ?>">
                        <input type="hidden" name="tab" value="overview">
                        <div class="col-md-4">
                            <input type="text" name="name" class="form-control form-control-sm"
                                   value="<?= h($editItem['name']) ?>" placeholder="Item name" required>
                        </div>
                        <div class="col-md-2">
                            <select name="entity_type" class="form-select form-select-sm">
                                <?php foreach (['custom'=>'Custom','gold_vault'=>'Gold Vault','park_section'=>'Park Section'] as $v=>$l): ?>
                                    <option value="<?= $v ?>" <?= $editItem['entity_type']===$v?'selected':'' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="text" name="unit" class="form-control form-control-sm"
                                   value="<?= h($editItem['unit']) ?>" placeholder="Unit (grams/pcs…)">
                        </div>
                        <div class="col-md-2">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Min</span>
                                <input type="number" name="min_stock" class="form-control"
                                       value="<?= $editItem['min_stock'] ?>" step="0.0001" min="0">
                            </div>
                        </div>
                        <div class="col-md-2 d-flex gap-2 align-items-center">
                            <div class="form-check form-check-inline mb-0">
                                <input type="checkbox" id="notify_<?= $si['id'] ?>" name="notify_admin"
                                       class="form-check-input" value="1" <?= $editItem['notify_admin']?'checked':'' ?>>
                                <label for="notify_<?= $si['id'] ?>" class="form-check-label small">Alert</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <textarea name="notes" class="form-control form-control-sm" rows="2"
                                      placeholder="Internal notes"><?= h($editItem['notes'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-sm btn-primary">Save Changes</button>
                            <a href="?tab=overview" class="btn btn-sm btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </td>
            </tr>
            <?php endif; ?>

            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ── TAB: Alerts ─────────────────────────────────────────────────────────── -->
<?php elseif ($activeTab === 'alerts'): ?>

<?php if (empty($allAlerts)): ?>
    <div class="pg-card p-5 text-center text-muted">
        <i class="fas fa-check-circle fa-2x mb-3 d-block text-success opacity-50"></i>
        No stock alerts have been triggered yet.
    </div>
<?php else: ?>
<div class="pg-card p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Item</th>
                    <th class="text-center">Stock at Trigger</th>
                    <th class="text-center">Threshold</th>
                    <th>Triggered</th>
                    <th>Status</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($allAlerts as $al): ?>
            <tr class="<?= $al['is_acknowledged'] ? 'opacity-50' : '' ?>">
                <td><strong><?= h($al['item_name']) ?></strong></td>
                <td class="text-center">
                    <span class="<?= !$al['is_acknowledged'] ? 'fw-700 text-danger' : '' ?>">
                        <?= number_format((float)$al['stock_at_trigger'], 4) ?>
                    </span>
                    <span class="text-muted"><?= h($al['unit']) ?></span>
                </td>
                <td class="text-center">
                    <?= number_format((float)$al['min_stock_at_trigger'], 4) ?>
                    <span class="text-muted"><?= h($al['unit']) ?></span>
                </td>
                <td><?= date('d M Y, g:ia', strtotime($al['triggered_at'])) ?></td>
                <td>
                    <?php if ($al['is_acknowledged']): ?>
                        <span class="badge bg-success">Acknowledged</span>
                        <?php if ($al['ack_by_name']): ?>
                            <div class="text-muted" style="font-size:.72rem;">
                                by <?= h($al['ack_by_name']) ?>
                                <?= $al['acknowledged_at'] ? '· ' . date('d M', strtotime($al['acknowledged_at'])) : '' ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge bg-danger"><i class="fas fa-bell me-1"></i>Open</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if (!$al['is_acknowledged']): ?>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="acknowledge_alert">
                        <input type="hidden" name="alert_id" value="<?= $al['id'] ?>">
                        <input type="hidden" name="tab" value="alerts">
                        <button class="btn btn-sm btn-outline-success" title="Acknowledge">
                            <i class="fas fa-check me-1"></i>Acknowledge
                        </button>
                    </form>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ── TAB: History ────────────────────────────────────────────────────────── -->
<?php elseif ($activeTab === 'history'): ?>

<?php if (empty($history)): ?>
    <div class="pg-card p-5 text-center text-muted">
        <i class="fas fa-history fa-2x mb-3 d-block opacity-25"></i>
        No transactions recorded yet.
    </div>
<?php else: ?>
<div class="pg-card p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Item</th>
                    <th class="text-center">Type</th>
                    <th class="text-center">Quantity</th>
                    <th class="text-center">Before</th>
                    <th class="text-center">After</th>
                    <th>Notes</th>
                    <th>By</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($history as $tx):
                $txBadge = match($tx['transaction_type']) {
                    'in'         => ['bg-success', 'fa-arrow-up',    'Stock In'],
                    'out'        => ['bg-danger',  'fa-arrow-down',  'Stock Out'],
                    default      => ['bg-primary', 'fa-sliders-h',   'Adjusted'],
                };
            ?>
            <tr>
                <td><strong><?= h($tx['item_name']) ?></strong></td>
                <td class="text-center">
                    <span class="badge <?= $txBadge[0] ?>">
                        <i class="fas <?= $txBadge[1] ?> me-1"></i><?= $txBadge[2] ?>
                    </span>
                </td>
                <td class="text-center fw-700">
                    <?= ($tx['transaction_type'] === 'in' ? '+' : ($tx['transaction_type'] === 'out' ? '−' : '=')) ?>
                    <?= number_format((float)$tx['quantity'], 4) ?>
                    <span class="fw-400 text-muted"><?= h($tx['unit']) ?></span>
                </td>
                <td class="text-center text-muted"><?= number_format((float)$tx['stock_before'], 4) ?></td>
                <td class="text-center fw-600 <?= (float)$tx['stock_after'] < (float)$tx['stock_before'] ? 'text-danger' : 'text-success' ?>">
                    <?= number_format((float)$tx['stock_after'], 4) ?>
                </td>
                <td class="text-muted"><?= h($tx['notes'] ?? '—') ?></td>
                <td><?= h($tx['by_name'] ?? 'System') ?></td>
                <td><?= date('d M y, g:ia', strtotime($tx['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="p-2 text-muted small text-end border-top">Showing last 150 transactions.</div>
</div>
<?php endif; ?>
<?php endif; ?>


<!-- ── Modal: Add Stock Item ──────────────────────────────────────────────── -->
<div class="modal fade" id="modalAddItem" tabindex="-1" aria-labelledby="modalAddItemLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-700" id="modalAddItemLabel">
                    <i class="fas fa-plus-circle me-2 text-gold"></i>Add New Stock Item
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="save_item">
                <input type="hidden" name="tab" value="overview">
                <div class="modal-body row g-3">

                    <div class="col-12">
                        <label class="form-label">Item Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Gold Vault — PAMP 50g Bars" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Type</label>
                        <select name="entity_type" class="form-select" id="entityTypeSelect">
                            <option value="custom">Custom Item</option>
                            <option value="gold_vault">Gold Vault</option>
                            <option value="park_section">Park Section</option>
                        </select>
                    </div>

                    <div class="col-md-4" id="entityIdRow" style="display:none;">
                        <label class="form-label">Park Section</label>
                        <select name="entity_id" class="form-select">
                            <option value="">— select section —</option>
                            <?php foreach ($parkSections as $ps): ?>
                                <option value="<?= $ps['id'] ?>">
                                    <?= h($ps['park_name']) ?> › <?= h($ps['name']) ?>
                                    (<?= $ps['available_plots'] ?> available)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Unit</label>
                        <input type="text" name="unit" class="form-control" value="units"
                               placeholder="grams / plots / pcs / units" list="unitSuggestions">
                        <datalist id="unitSuggestions">
                            <option value="grams">
                            <option value="plots">
                            <option value="pcs">
                            <option value="units">
                            <option value="kg">
                        </datalist>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Initial Stock</label>
                        <input type="number" name="initial_stock" class="form-control"
                               value="0" min="0" step="0.0001">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">
                            Minimum Stock <span class="text-danger">*</span>
                            <span class="text-muted small">(alert threshold)</span>
                        </label>
                        <input type="number" name="min_stock" class="form-control"
                               value="0" min="0" step="0.0001" required>
                    </div>

                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" id="notifyAdminNew" name="notify_admin"
                                   class="form-check-input" value="1" checked>
                            <label for="notifyAdminNew" class="form-check-label">
                                Alert admin when stock drops to minimum
                            </label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Notes <span class="text-muted small">(internal)</span></label>
                        <textarea name="notes" class="form-control" rows="2"
                                  placeholder="Reorder instructions, supplier details, etc."></textarea>
                    </div>

                    <div class="col-12">
                        <div class="alert alert-info small mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Oversell protection:</strong> When stock reaches zero, the system will block
                            outbound stock movements and display a warning. Set the minimum above zero to get
                            early alerts before you run out.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gold">
                        <i class="fas fa-save me-1"></i>Create Stock Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Show park section selector only when entity_type = park_section
document.getElementById('entityTypeSelect').addEventListener('change', function () {
    document.getElementById('entityIdRow').style.display = this.value === 'park_section' ? '' : 'none';
});
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>
