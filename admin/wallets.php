<?php
declare(strict_types=1);

/**
 * admin/wallets.php
 * Admin wallet management: view balances, manual top-up / deduct.
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

// ── Manual adjustment ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $user_id = (int)($_POST['user_id'] ?? 0);
    $amount  = (float)($_POST['amount'] ?? 0);
    $note    = trim($_POST['note'] ?? '');
    $action  = $_POST['action'] ?? '';

    if (!$user_id || $amount <= 0) {
        flash_error('Invalid input.');
    } elseif ($note === '') {
        flash_error('A note is required for manual adjustments.');
    } else {
        $adjustAmount = ($action === 'deduct') ? -$amount : $amount;
        $result = wallet_admin_adjust($user_id, $adjustAmount, $note, (int)$admin['id']);

        if ($result['ok']) {
            flash_success('Wallet adjusted successfully.');
        } else {
            flash_error($result['error']);
        }
    }
    redirect(BASE_URL . '/admin/wallets.php' . ($user_id ? '?user_id=' . $user_id : ''));
}

// ── Single user view ──────────────────────────────────────────────────────────
$filterUid = (int)($_GET['user_id'] ?? 0);
$targetUser = null;
$txns = [];

if ($filterUid) {
    $s = $pdo->prepare(
        'SELECT u.id, u.name, u.email, COALESCE(w.balance,0) AS balance
         FROM `users` u LEFT JOIN `wallets` w ON w.user_id = u.id
         WHERE u.id = ? LIMIT 1'
    );
    $s->execute([$filterUid]);
    $targetUser = $s->fetch();

    if ($targetUser) {
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $total = wallet_transaction_count($filterUid);
        $pager = paginate($total, $page);
        $txns  = wallet_transactions($filterUid, $pager['per_page'], $pager['offset']);
    }
}

// ── List all wallets ──────────────────────────────────────────────────────────
$search  = trim($_GET['q'] ?? '');
$page2   = max(1, (int)($_GET['page'] ?? 1));
$where   = [];
$wparams = [];

if ($search !== '') {
    $where[]   = '(u.name LIKE ? OR u.email LIKE ?)';
    $wparams[] = "%$search%";
    $wparams[] = "%$search%";
}
$wSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

if (!$filterUid) {
    $cs = $pdo->prepare("SELECT COUNT(*) FROM `users` u LEFT JOIN `wallets` w ON w.user_id=u.id $wSQL");
    $cs->execute($wparams);
    $wTotal = (int)$cs->fetchColumn();
    $pager2 = paginate($wTotal, $page2);

    $ws = $pdo->prepare(
        "SELECT u.id, u.name, u.email, COALESCE(w.balance,0) AS balance
         FROM `users` u LEFT JOIN `wallets` w ON w.user_id = u.id
         $wSQL ORDER BY w.balance DESC LIMIT {$pager2['per_page']} OFFSET {$pager2['offset']}"
    );
    $ws->execute($wparams);
    $wallets = $ws->fetchAll();
}

$typeLabels = [
    'topup' => 'Top-up', 'deduction' => 'Deduction', 'refund' => 'Refund',
    'referral_reward' => 'Referral', 'admin_adjustment' => 'Admin Adj.',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallets — <?= e(setting('site_name','VideoSaaS')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
</head>
<body>
<?php render_admin_navbar($admin); ?>
<div class="admin-wrap">
    <?php render_admin_sidebar('wallets'); ?>
    <main class="admin-content">
        <?= render_flash() ?>

        <?php if ($targetUser): ?>
            <!-- ── Single user wallet view ── -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">Wallet: <?= e($targetUser['name']) ?></h1>
                    <p class="page-sub"><?= e($targetUser['email']) ?></p>
                </div>
                <a href="<?= BASE_URL ?>/admin/wallets.php" class="btn btn-ghost">← All Wallets</a>
            </div>

            <!-- Balance + Adjust -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
                <div class="card" style="background:linear-gradient(135deg,rgba(108,71,255,.25),rgba(0,212,170,.1))">
                    <div class="kpi-label">Current Balance</div>
                    <div style="font-size:2.5rem;font-weight:900;color:#fff"><?= e(format_credits((float)$targetUser['balance'])) ?></div>
                    <div class="text-muted text-sm">credits</div>
                </div>

                <div class="card">
                    <div class="card-header"><span class="card-title">Manual Adjustment</span></div>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= (int)$targetUser['id'] ?>">
                        <div class="form-group">
                            <label class="form-label">Amount (credits)</label>
                            <input type="number" name="amount" class="form-control"
                                   min="0.01" step="0.01" placeholder="e.g. 50" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Note (required)</label>
                            <input type="text" name="note" class="form-control"
                                   placeholder="Reason for adjustment" required maxlength="200">
                        </div>
                        <div style="display:flex;gap:10px">
                            <button type="submit" name="action" value="credit" class="btn btn-success">+ Add Credits</button>
                            <button type="submit" name="action" value="deduct" class="btn btn-danger"
                                    onclick="return confirm('Deduct credits from this wallet?')">− Deduct</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Transaction history -->
            <div class="card">
                <div class="card-header"><span class="card-title">Transaction History</span></div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>Type</th><th>Amount</th><th>Before</th><th>After</th><th>Note</th><th>By</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($txns)): ?>
                                <tr><td colspan="7" class="text-center text-muted" style="padding:24px">No transactions.</td></tr>
                            <?php else: ?>
                                <?php foreach ($txns as $t): ?>
                                    <?php $amt = (float)$t['amount']; $pos = $amt > 0; ?>
                                    <tr>
                                        <td><span class="badge <?= $pos ? 'badge-success' : 'badge-danger' ?>"><?= e($typeLabels[$t['type']] ?? $t['type']) ?></span></td>
                                        <td style="color:<?= $pos ? 'var(--color-success)' : 'var(--color-danger)' ?>;font-weight:700">
                                            <?= $pos ? '+' : '' ?><?= e(format_credits(abs($amt))) ?>
                                        </td>
                                        <td class="text-muted"><?= e(format_credits((float)$t['balance_before'])) ?></td>
                                        <td><?= e(format_credits((float)$t['balance_after'])) ?></td>
                                        <td class="text-muted text-sm"><?= e(truncate($t['note'] ?? '—', 40)) ?></td>
                                        <td class="text-muted text-sm"><?= e($t['created_by'] ?? 'system') ?></td>
                                        <td class="text-muted text-sm"><?= e(format_datetime($t['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php render_pagination($pager); ?>
            </div>

        <?php else: ?>
            <!-- ── All wallets list ── -->
            <div class="page-header">
                <h1 class="page-title">Wallets</h1>
            </div>

            <form method="GET" style="display:flex;gap:10px;margin-bottom:20px">
                <input type="text" name="q" value="<?= e($search) ?>" class="form-control"
                       placeholder="Search name or email…" style="max-width:320px">
                <button type="submit" class="btn btn-ghost">Search</button>
            </form>

            <div class="card">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>User</th><th>Email</th><th>Balance</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($wallets)): ?>
                                <tr><td colspan="4" class="text-center text-muted" style="padding:24px">No wallets found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($wallets as $w): ?>
                                    <tr>
                                        <td class="fw-bold"><?= e($w['name']) ?></td>
                                        <td class="text-muted"><?= e($w['email']) ?></td>
                                        <td class="fw-bold"><?= e(format_credits((float)$w['balance'])) ?></td>
                                        <td>
                                            <a href="?user_id=<?= (int)$w['id'] ?>" class="btn btn-ghost btn-sm">View / Adjust</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php render_pagination($pager2); ?>
            </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
