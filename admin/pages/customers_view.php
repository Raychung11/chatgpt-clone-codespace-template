<?php
/**
 * Admin – Customer Profile View
 * /admin/pages/customers_view.php
 */

$id = sanitize_int($_GET['id'] ?? 0);
if (!$id) { header('Location: /admin/customers'); exit; }

$customer = Database::fetchOne(
    'SELECT u.*, cp.gender, cp.date_of_birth, cp.avatar_url, cp.total_points, cp.lifetime_points,
            cp.tier, cp.referral_code, cp.address, cp.notes
     FROM users u
     LEFT JOIN customer_profiles cp ON cp.user_id = u.id
     WHERE u.id = ? AND u.role = "customer"',
    [$id]
);
if (!$customer) { header('Location: /admin/customers'); exit; }

// Notes update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notes'])) {
    if (!Auth::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        flash('error', 'Invalid request.');
    } else {
        $notes = sanitize_string($_POST['notes'] ?? '', 1000);
        Database::execute('UPDATE customer_profiles SET notes = ? WHERE user_id = ?', [$notes, $id]);
        admin_log('update_notes', 'customers', $id);
        flash('success', 'Notes updated.');
        header("Location: /admin/customers/view?id={$id}");
        exit;
    }
}

// Points adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_points'])) {
    if (!Auth::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        flash('error', 'Invalid request.');
    } else {
        $pts  = sanitize_int($_POST['points'] ?? 0);
        $type = in_array($_POST['adj_type'], ['earn','adjust','expire']) ? $_POST['adj_type'] : 'adjust';
        $desc = sanitize_string($_POST['reason'] ?? '', 150);
        if ($pts !== 0) {
            add_loyalty_points($id, $pts, $type, 'admin', null, $desc ?: 'Admin adjustment');
            admin_log('adjust_points', 'customers', $id, "Points: {$pts}");
            flash('success', 'Points adjusted.');
        }
        header("Location: /admin/customers/view?id={$id}");
        exit;
    }
}

// Loyalty history
$loyaltyHistory = Database::fetchAll(
    'SELECT * FROM loyalty_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20',
    [$id]
);

// Recent orders
$orders = Database::fetchAll(
    'SELECT o.*, ot.name AS outlet_name FROM orders o
     LEFT JOIN outlets ot ON ot.id = o.outlet_id
     WHERE o.user_id = ? ORDER BY o.created_at DESC LIMIT 10',
    [$id]
);

// Reservations
$reservations = Database::fetchAll(
    'SELECT r.*, ot.name AS outlet_name FROM reservations r
     LEFT JOIN outlets ot ON ot.id = r.outlet_id
     WHERE r.user_id = ? ORDER BY r.created_at DESC LIMIT 10',
    [$id]
);

$pageTitle  = 'Customer: ' . $customer['name'];
$activePage = 'customers';
$csrf       = Auth::generateCsrfToken();

require __DIR__ . '/../layout/header.php';
?>

<div class="mb-3">
    <a href="/admin/customers" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Customers
    </a>
</div>

<div class="row g-3">
    <!-- Profile Card -->
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="mb-2">
                <div class="rounded-circle bg-primary d-inline-flex align-items-center justify-content-center text-white"
                     style="width:70px;height:70px;font-size:1.8rem;">
                    <?= strtoupper(substr($customer['name'], 0, 1)) ?>
                </div>
            </div>
            <h6 class="fw-bold mb-0"><?= htmlspecialchars($customer['name']) ?></h6>
            <div class="text-muted small"><?= htmlspecialchars($customer['phone']) ?></div>
            <?php if ($customer['email']): ?>
            <div class="text-muted small"><?= htmlspecialchars($customer['email']) ?></div>
            <?php endif; ?>

            <hr class="my-2">

            <?php
            $tierIcons = ['bronze'=>'🥉','silver'=>'🥈','gold'=>'🥇','platinum'=>'💎'];
            $t = $customer['tier'] ?? 'bronze';
            ?>
            <div class="badge bg-warning text-dark mb-2"><?= ($tierIcons[$t] ?? '') . ' ' . ucfirst($t) ?></div>

            <div class="row text-center g-0">
                <div class="col-6 border-end py-2">
                    <div class="fs-5 fw-bold text-primary"><?= number_format((int)$customer['total_points']) ?></div>
                    <div class="text-muted" style="font-size:.72rem;">Current Points</div>
                </div>
                <div class="col-6 py-2">
                    <div class="fs-5 fw-bold text-success"><?= number_format((int)$customer['lifetime_points']) ?></div>
                    <div class="text-muted" style="font-size:.72rem;">Lifetime Points</div>
                </div>
            </div>

            <hr class="my-2">
            <div class="text-muted small text-start">
                <div><i class="bi bi-calendar me-1"></i>Joined <?= date('d M Y', strtotime($customer['created_at'])) ?></div>
                <?php if ($customer['date_of_birth']): ?>
                <div><i class="bi bi-cake me-1"></i>DOB: <?= date('d M Y', strtotime($customer['date_of_birth'])) ?></div>
                <?php endif; ?>
                <?php if ($customer['referral_code']): ?>
                <div><i class="bi bi-share me-1"></i>Ref: <code><?= $customer['referral_code'] ?></code></div>
                <?php endif; ?>
                <div>
                    <i class="bi bi-circle-fill me-1 text-<?= $customer['status']==='active' ? 'success' : 'danger' ?>" style="font-size:.6rem;"></i>
                    <?= ucfirst($customer['status']) ?>
                </div>
            </div>

            <div class="mt-3">
                <a href="/admin/customers/edit?id=<?= $id ?>" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-pencil me-1"></i>Edit Profile
                </a>
            </div>
        </div>

        <!-- Adjust Points -->
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white border-0 py-2 small fw-semibold">
                <i class="bi bi-stars me-1 text-warning"></i>Adjust Points
            </div>
            <div class="card-body py-2">
                <form method="POST">
                    <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                    <div class="mb-2">
                        <input type="number" name="points" class="form-control form-control-sm"
                               placeholder="e.g. 100 or -50" required>
                    </div>
                    <div class="mb-2">
                        <select name="adj_type" class="form-select form-select-sm">
                            <option value="earn">Earn (+)</option>
                            <option value="adjust">Adjust</option>
                            <option value="expire">Expire (-)</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason (optional)">
                    </div>
                    <button name="adjust_points" class="btn btn-sm btn-warning w-100">Apply</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div class="col-lg-9">
        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3" id="custTabs">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabPoints">Points History</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabOrders">Orders</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabReservations">Reservations</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabNotes">CRM Notes</button></li>
        </ul>

        <div class="tab-content">
            <!-- Points History -->
            <div class="tab-pane active" id="tabPoints">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover mb-0 small">
                            <thead class="table-light"><tr><th>Date</th><th>Type</th><th>Points</th><th>Balance</th><th>Description</th></tr></thead>
                            <tbody>
                                <?php foreach ($loyaltyHistory as $lt): ?>
                                <tr>
                                    <td><?= date('d M Y H:i', strtotime($lt['created_at'])) ?></td>
                                    <td><span class="badge bg-<?= $lt['points']>0?'success':'danger' ?>"><?= ucfirst($lt['type']) ?></span></td>
                                    <td class="<?= $lt['points']>0?'text-success fw-bold':'text-danger fw-bold' ?>">
                                        <?= ($lt['points']>0?'+':'') . number_format($lt['points']) ?>
                                    </td>
                                    <td><?= number_format($lt['balance_after']) ?></td>
                                    <td class="text-muted"><?= htmlspecialchars($lt['description'] ?? '—') ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($loyaltyHistory)): ?><tr><td colspan="5" class="text-center text-muted py-3">No transactions yet.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Orders -->
            <div class="tab-pane" id="tabOrders">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover mb-0 small">
                            <thead class="table-light"><tr><th>Order No</th><th>Outlet</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
                            <tbody>
                                <?php foreach ($orders as $ord): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($ord['order_no']) ?></code></td>
                                    <td><?= htmlspecialchars($ord['outlet_name'] ?? '—') ?></td>
                                    <td><?= format_currency((float)$ord['total']) ?></td>
                                    <td><span class="badge bg-<?= $ord['status']==='completed'?'success':($ord['status']==='cancelled'?'danger':'warning') ?>"><?= ucfirst($ord['status']) ?></span></td>
                                    <td><?= date('d M Y', strtotime($ord['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($orders)): ?><tr><td colspan="5" class="text-center text-muted py-3">No orders yet.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Reservations -->
            <div class="tab-pane" id="tabReservations">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover mb-0 small">
                            <thead class="table-light"><tr><th>Ref No</th><th>Outlet</th><th>Date & Time</th><th>Pax</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($reservations as $res): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($res['reservation_no']) ?></code></td>
                                    <td><?= htmlspecialchars($res['outlet_name'] ?? '—') ?></td>
                                    <td><?= date('d M Y', strtotime($res['reserved_date'])) ?> <?= $res['reserved_time'] ?></td>
                                    <td><?= $res['party_size'] ?></td>
                                    <td><span class="badge bg-<?= $res['status']==='confirmed'?'primary':($res['status']==='completed'?'success':($res['status']==='cancelled'?'danger':'warning')) ?>"><?= ucfirst($res['status']) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($reservations)): ?><tr><td colspan="5" class="text-center text-muted py-3">No reservations yet.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- CRM Notes -->
            <div class="tab-pane" id="tabNotes">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Internal Notes (not visible to customer)</label>
                                <textarea name="notes" class="form-control" rows="6"><?= htmlspecialchars($customer['notes'] ?? '') ?></textarea>
                            </div>
                            <button name="save_notes" class="btn btn-sm btn-primary">Save Notes</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
