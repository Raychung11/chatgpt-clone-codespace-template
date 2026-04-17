<?php
declare(strict_types=1);

/**
 * client/wallet.php
 * Client wallet: balance + full transaction history with pagination.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/wallet.php';
require_once __DIR__ . '/../inc/layout.php';

boot_session();
$user = require_auth('/public/login.php');
$uid  = (int)$user['id'];

$page    = max(1, (int)($_GET['page'] ?? 1));
$total   = wallet_transaction_count($uid);
$pager   = paginate($total, $page);
$txns    = wallet_transactions($uid, $pager['per_page'], $pager['offset']);
$balance = wallet_balance($uid);

$typeLabels = [
    'topup'            => 'Top-up',
    'deduction'        => 'Video Generation',
    'refund'           => 'Refund',
    'referral_reward'  => 'Referral Reward',
    'admin_adjustment' => 'Admin Adjustment',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet — <?= e(setting('site_name','Motions')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
</head>
<body>
<?php render_client_navbar($user, 'wallet'); ?>

<div class="container main-content">
    <?= render_flash() ?>

    <div class="page-header">
        <div>
            <h1 class="page-title">My Wallet</h1>
            <p class="page-sub">Credits balance and transaction history</p>
        </div>
        <a href="<?= BASE_URL ?>/client/buy-credits.php" class="btn btn-accent">+ Buy Credits</a>
    </div>

    <!-- Balance card -->
    <div class="card mb-4" style="background:linear-gradient(135deg,rgba(108,71,255,.3),rgba(0,212,170,.15));border-color:var(--color-primary)">
        <div class="kpi-label">Current Balance</div>
        <div style="font-size:3rem;font-weight:900;color:#fff;line-height:1;margin:8px 0">
            <?= e(format_credits($balance)) ?>
        </div>
        <div class="text-muted text-sm">credits</div>
    </div>

    <!-- Transaction history -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Transaction History</span>
            <span class="text-muted text-sm"> — <?= $total ?> total</span>
        </div>

        <?php if (empty($txns)): ?>
            <p class="text-muted text-center" style="padding:32px 0">No transactions yet. <a href="<?= BASE_URL ?>/client/buy-credits.php">Buy credits to get started.</a></p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Balance After</th>
                            <th>Note</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($txns as $t): ?>
                            <?php
                            $amt   = (float)$t['amount'];
                            $isPos = $amt > 0;
                            $color = $isPos ? 'var(--color-success)' : 'var(--color-danger)';
                            $sign  = $isPos ? '+' : '';
                            ?>
                            <tr>
                                <td>
                                    <span class="badge <?= $isPos ? 'badge-success' : 'badge-danger' ?>">
                                        <?= e($typeLabels[$t['type']] ?? $t['type']) ?>
                                    </span>
                                </td>
                                <td style="color:<?= $color ?>;font-weight:700">
                                    <?= $sign ?><?= e(format_credits(abs($amt))) ?>
                                </td>
                                <td><?= e(format_credits((float)$t['balance_after'])) ?></td>
                                <td class="text-muted"><?= e(truncate($t['note'] ?? '—', 50)) ?></td>
                                <td class="text-muted text-sm"><?= e(format_datetime($t['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php render_pagination($pager); ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
