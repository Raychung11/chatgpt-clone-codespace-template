<?php
declare(strict_types=1);

/**
 * client/referral.php
 * Referral dashboard — referral link, stats, share to WhatsApp.
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

$referralCode = $user['referral_code'];
$referralUrl  = BASE_URL . '/public/register.php?ref=' . urlencode($referralCode);
$rewardCredit = setting('referral_reward_credits', 10.0);

// Stats
$stmt = db()->prepare(
    'SELECT r.*, u.name AS referee_name, u.email AS referee_email, u.created_at AS joined_at
     FROM `referrals` r JOIN `users` u ON u.id = r.referee_id
     WHERE r.referrer_id = ? ORDER BY r.created_at DESC'
);
$stmt->execute([$uid]);
$referrals = $stmt->fetchAll();

$totalRefs    = count($referrals);
$rewardedRefs = count(array_filter($referrals, fn($r) => $r['status'] === 'rewarded'));
$earned       = $rewardedRefs * (float)$rewardCredit;

$waText = urlencode("Hey! I'm using VideoSaaS to create AI marketing videos. Sign up with my link and we both get bonus credits: $referralUrl");
$waUrl  = "https://wa.me/?text=$waText";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral — <?= e(setting('site_name','VideoSaaS')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
</head>
<body>
<?php render_client_navbar($user, 'referral'); ?>

<div class="container main-content">
    <?= render_flash() ?>

    <div class="page-header">
        <div>
            <h1 class="page-title">Referral Program</h1>
            <p class="page-sub">Invite friends and earn <?= e(format_credits((float)$rewardCredit)) ?> free credits per referral</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="kpi-grid mb-4">
        <div class="kpi-card">
            <div class="kpi-label">Total Referrals</div>
            <div class="kpi-value"><?= $totalRefs ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Rewarded</div>
            <div class="kpi-value text-success"><?= $rewardedRefs ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Credits Earned</div>
            <div class="kpi-value text-accent"><?= e(format_credits($earned)) ?></div>
        </div>
    </div>

    <!-- Referral link -->
    <div class="card mb-4">
        <div class="card-header"><span class="card-title">Your Referral Link</span></div>

        <p class="text-muted text-sm mb-3">
            Share this link. When your friend registers AND makes their first purchase,
            you'll receive <strong><?= e(format_credits((float)$rewardCredit)) ?> free credits</strong>.
        </p>

        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <input type="text" id="refUrl" value="<?= e($referralUrl) ?>"
                   class="form-control" readonly
                   style="flex:1;min-width:200px;background:var(--color-surface2)">
            <button onclick="copyRef()" class="btn btn-ghost">Copy Link</button>
            <a href="<?= e($waUrl) ?>" target="_blank" rel="noopener"
               class="btn btn-success">Share on WhatsApp</a>
        </div>

        <div class="mt-3">
            <span class="text-muted text-sm">Your code: </span>
            <strong><?= e($referralCode) ?></strong>
        </div>
    </div>

    <!-- How it works -->
    <div class="card mb-4">
        <div class="card-header"><span class="card-title">How It Works</span></div>
        <ol style="padding-left:20px;line-height:2;color:var(--color-muted);font-size:.95rem">
            <li>Share your unique referral link with friends.</li>
            <li>They register using your link (or enter your code during signup).</li>
            <li>When they make their <strong style="color:var(--color-text)">first credit purchase</strong>, you automatically receive <strong style="color:var(--color-accent)"><?= e(format_credits((float)$rewardCredit)) ?> credits</strong>.</li>
            <li>No limit — invite as many friends as you like!</li>
        </ol>
    </div>

    <!-- Referral list -->
    <?php if (!empty($referrals)): ?>
    <div class="card">
        <div class="card-header"><span class="card-title">People You Referred</span></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Joined</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($referrals as $ref): ?>
                        <tr>
                            <td><?= e($ref['referee_name']) ?></td>
                            <td class="text-muted"><?= e($ref['referee_email']) ?></td>
                            <td class="text-muted text-sm"><?= e(format_datetime($ref['joined_at'])) ?></td>
                            <td>
                                <?php if ($ref['status'] === 'rewarded'): ?>
                                    <span class="badge badge-success">Rewarded</span>
                                <?php else: ?>
                                    <span class="badge badge-muted">Pending purchase</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
function copyRef() {
    const el = document.getElementById('refUrl');
    el.select();
    el.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(el.value).then(() => {
        alert('Referral link copied!');
    }).catch(() => {
        document.execCommand('copy');
        alert('Referral link copied!');
    });
}
</script>
</body>
</html>
