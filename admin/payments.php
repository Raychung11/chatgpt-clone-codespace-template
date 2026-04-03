<?php
declare(strict_types=1);

/**
 * admin/payments.php
 * Review, approve, and reject payment orders. Supports receipt preview.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/wallet.php';
require_once __DIR__ . '/../inc/layout.php';

boot_session();
$admin = require_admin('/admin/login.php');
$pdo   = db();

// ── Actions ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action   = $_POST['action']   ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);

    if ($action === 'approve' && $order_id) {
        $result = process_payment_approval($order_id, (int)$admin['id']);
        flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Payment approved and credits added.' : $result['error']);
    }

    if ($action === 'reject' && $order_id) {
        $reason = trim($_POST['reject_reason'] ?? '');
        if (!$reason) {
            flash_error('Please provide a rejection reason.');
        } else {
            $result = process_payment_rejection($order_id, (int)$admin['id'], $reason);
            flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Payment rejected.' : $result['error']);
        }
    }

    redirect(BASE_URL . '/admin/payments.php?status=' . urlencode($_POST['status_filter'] ?? 'pending'));
}

// ── Filters ───────────────────────────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? 'pending';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$focusId      = (int)($_GET['id'] ?? 0); // highlight a specific order

$where  = [];
$params = [];

if (in_array($statusFilter, ['pending','approved','rejected','cancelled'], true)) {
    $where[]  = 'po.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $where[]  = '(u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$wSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cs = $pdo->prepare("SELECT COUNT(*) FROM `payment_orders` po JOIN `users` u ON u.id=po.user_id $wSQL");
$cs->execute($params);
$total = (int)$cs->fetchColumn();
$pager = paginate($total, $page);

$stmt = $pdo->prepare(
    "SELECT po.*, u.name AS user_name, u.email AS user_email,
            pr.file_path AS receipt_path
     FROM `payment_orders` po
     JOIN `users` u ON u.id = po.user_id
     LEFT JOIN `payment_receipts` pr ON pr.payment_order_id = po.id
     $wSQL
     ORDER BY po.created_at DESC
     LIMIT {$pager['per_page']} OFFSET {$pager['offset']}"
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$currency = setting('currency', 'MYR');
$statusBadge = [
    'pending'   => 'badge-warning',
    'approved'  => 'badge-success',
    'rejected'  => 'badge-danger',
    'cancelled' => 'badge-muted',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments — <?= e(setting('site_name','VideoSaaS')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
    <style>
        .receipt-thumb { max-height: 120px; border-radius: 6px; border: 1px solid var(--color-border); cursor: pointer; }
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.8); z-index:1000; align-items:center; justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal-img { max-width: 90vw; max-height: 90vh; border-radius: 8px; }
    </style>
</head>
<body>
<?php render_admin_navbar($admin); ?>
<div class="admin-wrap">
    <?php render_admin_sidebar('payments'); ?>
    <main class="admin-content">
        <?= render_flash() ?>

        <div class="page-header">
            <h1 class="page-title">Payments</h1>
        </div>

        <!-- Filter tabs + search -->
        <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
            <?php foreach (['pending','approved','rejected','cancelled'] as $s): ?>
                <a href="?status=<?= $s ?>"
                   class="btn btn-sm <?= $statusFilter === $s ? 'btn-primary' : 'btn-ghost' ?>">
                    <?= ucfirst($s) ?>
                </a>
            <?php endforeach; ?>
            <form method="GET" style="margin-left:auto;display:flex;gap:8px">
                <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                <input type="text" name="q" value="<?= e($search) ?>" class="form-control btn-sm"
                       placeholder="Search user…" style="max-width:220px">
                <button type="submit" class="btn btn-ghost btn-sm">Search</button>
            </form>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Credits</th>
                            <th>Receipt</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr><td colspan="8" class="text-center text-muted" style="padding:32px">No orders found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($orders as $o): ?>
                                <tr style="<?= $focusId === (int)$o['id'] ? 'background:rgba(108,71,255,.1)' : '' ?>">
                                    <td class="text-muted">#<?= (int)$o['id'] ?></td>
                                    <td>
                                        <div class="fw-bold"><?= e($o['user_name']) ?></div>
                                        <div class="text-muted text-sm"><?= e($o['user_email']) ?></div>
                                    </td>
                                    <td class="fw-bold"><?= e(format_currency((float)$o['amount'], $currency)) ?></td>
                                    <td><?= e(format_credits((float)$o['credits'])) ?></td>
                                    <td>
                                        <?php if ($o['receipt_path']): ?>
                                            <?php $ext = strtolower(pathinfo($o['receipt_path'], PATHINFO_EXTENSION)); ?>
                                            <?php if ($ext === 'pdf'): ?>
                                                <a href="<?= BASE_URL . '/' . e($o['receipt_path']) ?>" target="_blank" class="btn btn-ghost btn-sm">View PDF</a>
                                            <?php else: ?>
                                                <img src="<?= BASE_URL . '/' . e($o['receipt_path']) ?>"
                                                     alt="Receipt" class="receipt-thumb"
                                                     onclick="openModal(this.src)">
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?= $statusBadge[$o['status']] ?>"><?= e($o['status']) ?></span></td>
                                    <td class="text-muted text-sm"><?= e(format_datetime($o['created_at'])) ?></td>
                                    <td>
                                        <?php if ($o['status'] === 'pending'): ?>
                                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                                <!-- Approve -->
                                                <form method="POST" style="display:inline"
                                                      onsubmit="return confirm('Approve payment #<?= (int)$o['id'] ?> and add <?= e(format_credits((float)$o['credits'])) ?> credits?')">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action"        value="approve">
                                                    <input type="hidden" name="order_id"      value="<?= (int)$o['id'] ?>">
                                                    <input type="hidden" name="status_filter" value="<?= e($statusFilter) ?>">
                                                    <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                                </form>

                                                <!-- Reject -->
                                                <form method="POST" style="display:inline"
                                                      onsubmit="return rejectOrder(this)">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action"        value="reject">
                                                    <input type="hidden" name="order_id"      value="<?= (int)$o['id'] ?>">
                                                    <input type="hidden" name="status_filter" value="<?= e($statusFilter) ?>">
                                                    <input type="hidden" name="reject_reason" id="reason_<?= (int)$o['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                                </form>
                                            </div>
                                        <?php elseif ($o['status'] === 'rejected' && $o['reject_reason']): ?>
                                            <span class="text-muted text-sm"><?= e(truncate($o['reject_reason'], 40)) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php render_pagination($pager); ?>
        </div>
    </main>
</div>

<!-- Receipt image modal -->
<div class="modal-overlay" id="imgModal" onclick="closeModal()">
    <img id="modalImg" class="modal-img" src="" alt="Receipt">
</div>

<script>
function openModal(src) {
    document.getElementById('modalImg').src = src;
    document.getElementById('imgModal').classList.add('open');
}
function closeModal() {
    document.getElementById('imgModal').classList.remove('open');
}
function rejectOrder(form) {
    const reason = prompt('Enter rejection reason:');
    if (!reason || !reason.trim()) return false;
    const orderId = form.querySelector('[name="order_id"]').value;
    document.getElementById('reason_' + orderId).value = reason.trim();
    return confirm('Reject this payment with reason: "' + reason.trim() + '"?');
}
</script>
</body>
</html>
