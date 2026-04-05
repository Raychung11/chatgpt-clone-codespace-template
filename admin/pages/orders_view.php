<?php
/**
 * Admin – Order Detail View
 * /admin/pages/orders_view.php
 */

$id = sanitize_int($_GET['id'] ?? 0);
if (!$id) { header('Location: /admin/orders'); exit; }

$order = Database::fetchOne(
    'SELECT o.*, u.name AS customer_name, u.phone AS customer_phone, u.email AS customer_email,
            ot.name AS outlet_name, ot.address AS outlet_address, ot.phone AS outlet_phone
     FROM orders o
     JOIN users u  ON u.id  = o.user_id
     JOIN outlets ot ON ot.id = o.outlet_id
     WHERE o.id = ?',
    [$id]
);
if (!$order) { flash('error', 'Order not found.'); header('Location: /admin/orders'); exit; }

$items = Database::fetchAll('SELECT * FROM order_items WHERE order_id = ?', [$id]);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!Auth::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        flash('error', 'Invalid request.');
    } else {
        $newStatus = sanitize_string($_POST['status'] ?? '');
        $allowed   = ['pending','confirmed','preparing','ready','completed','cancelled'];

        if (in_array($newStatus, $allowed)) {
            Database::execute('UPDATE orders SET status = ? WHERE id = ?', [$newStatus, $id]);

            // Award loyalty points on completion
            if ($newStatus === 'completed' && $order['points_earned'] == 0) {
                $settings = get_settings(['loyalty_points_per_myr']);
                $ppm = (int)($settings['loyalty_points_per_myr'] ?? 1);
                $pts = (int) floor($order['total'] * $ppm);
                if ($pts > 0) {
                    add_loyalty_points($order['user_id'], $pts, 'earn', 'order', $id, 'Order completed', $order['outlet_id']);
                    Database::execute('UPDATE orders SET points_earned = ? WHERE id = ?', [$pts, $id]);
                    $order['points_earned'] = $pts;
                }
            }

            // Handle payment status update
            if (isset($_POST['payment_status'])) {
                $payStatus = in_array($_POST['payment_status'], ['unpaid','paid','refunded'])
                    ? $_POST['payment_status'] : $order['payment_status'];
                Database::execute('UPDATE orders SET payment_status = ? WHERE id = ?', [$payStatus, $id]);
            }

            admin_log('update_order', 'orders', $id, "Status → {$newStatus}");
            flash('success', 'Order updated.');
            header("Location: /admin/orders/view?id={$id}");
            exit;
        }
    }
}

// Refresh after any update
$order = Database::fetchOne(
    'SELECT o.*, u.name AS customer_name, u.phone AS customer_phone, u.email AS customer_email,
            ot.name AS outlet_name, ot.address AS outlet_address
     FROM orders o
     JOIN users u  ON u.id  = o.user_id
     JOIN outlets ot ON ot.id = o.outlet_id
     WHERE o.id = ?',
    [$id]
);

$pageTitle  = 'Order #' . $order['order_no'];
$activePage = 'orders';
$csrf       = Auth::generateCsrfToken();

$statusColors = [
    'pending'   => 'warning',
    'confirmed' => 'primary',
    'preparing' => 'info',
    'ready'     => 'success',
    'completed' => 'success',
    'cancelled' => 'danger',
];

require __DIR__ . '/../layout/header.php';
?>

<div class="mb-3 d-flex justify-content-between align-items-center">
    <a href="/admin/orders" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Orders
    </a>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-<?= $statusColors[$order['status']] ?? 'secondary' ?> px-3 py-2">
            <?= ucfirst($order['status']) ?>
        </span>
        <span class="badge bg-<?= $order['payment_status']==='paid'?'success':'warning' ?> px-3 py-2">
            <?= ucfirst($order['payment_status']) ?>
        </span>
    </div>
</div>

<div class="row g-3">
    <!-- Left: Order Items + Summary -->
    <div class="col-lg-8">

        <!-- Items -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-bag me-2 text-primary"></i>Order Items</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0 small align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Item</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($item['name']) ?></div>
                                <?php if ($item['notes']): ?>
                                <div class="text-muted" style="font-size:.75rem;">📝 <?= htmlspecialchars($item['notes']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= $item['qty'] ?></td>
                            <td class="text-end"><?= format_currency((float)$item['price']) ?></td>
                            <td class="text-end fw-semibold"><?= format_currency((float)$item['subtotal']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Totals -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row justify-content-end">
                    <div class="col-md-6">
                        <table class="table table-sm mb-0 small">
                            <tr>
                                <td class="text-muted border-0">Subtotal</td>
                                <td class="text-end border-0"><?= format_currency((float)$order['subtotal']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted border-0">Tax (SST)</td>
                                <td class="text-end border-0"><?= format_currency((float)$order['tax']) ?></td>
                            </tr>
                            <?php if ($order['discount'] > 0): ?>
                            <tr>
                                <td class="text-success border-0">Discount (Points)</td>
                                <td class="text-end text-success border-0">-<?= format_currency((float)$order['discount']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="fw-bold">
                                <td class="border-top">Total</td>
                                <td class="text-end border-top fs-6"><?= format_currency((float)$order['total']) ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php if ($order['points_earned'] > 0 || $order['points_used'] > 0): ?>
                <div class="d-flex gap-3 mt-3 pt-3 border-top small">
                    <?php if ($order['points_earned'] > 0): ?>
                    <span class="text-success"><i class="bi bi-star-fill me-1"></i>+<?= number_format($order['points_earned']) ?> pts earned</span>
                    <?php endif; ?>
                    <?php if ($order['points_used'] > 0): ?>
                    <span class="text-warning"><i class="bi bi-star me-1"></i><?= number_format($order['points_used']) ?> pts redeemed</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notes -->
        <?php if ($order['notes']): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-chat-left-text me-2"></i>Customer Notes</h6>
            </div>
            <div class="card-body small text-muted"><?= nl2br(htmlspecialchars($order['notes'])) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right: Info + Actions -->
    <div class="col-lg-4">

        <!-- Order Info -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2"></i>Order Info</h6>
            </div>
            <div class="card-body small">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Order No</span>
                    <code><?= htmlspecialchars($order['order_no']) ?></code>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Type</span>
                    <span class="badge bg-secondary"><?= ucfirst(str_replace('_',' ',$order['order_type'])) ?></span>
                </div>
                <?php if ($order['table_no']): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Table</span>
                    <strong><?= htmlspecialchars($order['table_no']) ?></strong>
                </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Outlet</span>
                    <span class="text-end"><?= htmlspecialchars($order['outlet_name']) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Placed At</span>
                    <span><?= date('d M Y H:i', strtotime($order['created_at'])) ?></span>
                </div>
                <?php if ($order['payment_method']): ?>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Payment</span>
                    <span><?= ucfirst($order['payment_method']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Customer Info -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-person me-2"></i>Customer</h6>
            </div>
            <div class="card-body small">
                <div class="fw-semibold mb-1"><?= htmlspecialchars($order['customer_name']) ?></div>
                <div class="text-muted mb-1"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($order['customer_phone']) ?></div>
                <?php if ($order['customer_email']): ?>
                <div class="text-muted mb-2"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($order['customer_email']) ?></div>
                <?php endif; ?>
                <a href="/admin/customers/view?id=<?= $order['user_id'] ?>" class="btn btn-xs btn-outline-primary btn-sm py-0 px-2">
                    <i class="bi bi-eye me-1"></i>View Profile
                </a>
            </div>
        </div>

        <!-- Update Status -->
        <?php if (!in_array($order['status'], ['completed','cancelled'])): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-arrow-repeat me-2 text-warning"></i>Update Order</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Order Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <?php foreach (array_keys($statusColors) as $st): ?>
                            <option value="<?= $st ?>" <?= $order['status']===$st?'selected':'' ?>>
                                <?= ucfirst($st) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Payment Status</label>
                        <select name="payment_status" class="form-select form-select-sm">
                            <?php foreach (['unpaid','paid','refunded'] as $ps): ?>
                            <option value="<?= $ps ?>" <?= $order['payment_status']===$ps?'selected':'' ?>>
                                <?= ucfirst($ps) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button name="update_status" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-check-lg me-1"></i>Update Order
                    </button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted small py-4">
                <i class="bi bi-lock display-6 d-block mb-2"></i>
                Order is <strong><?= $order['status'] ?></strong> and cannot be modified.
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
