<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

Auth::requireLogin();

$userId = Auth::id();
$user   = Auth::user();

// Ensure tables exist + get/create referral code
$myCode = Auth::getReferralCode($userId);
$myLink = SITE_URL . '/register.php?ref=' . $myCode;

// Stats
$totalReferrals   = 0;
$pendingCount     = 0;
$convertedCount   = 0;
$rewardedCount    = 0;
$totalEarned      = 0.00;
$myReferrals      = [];

try {
    $totalReferrals = (int)(DB::fetch('SELECT COUNT(*) as n FROM referrals WHERE referrer_id = ?', [$userId])['n'] ?? 0);
    $pendingCount   = (int)(DB::fetch("SELECT COUNT(*) as n FROM referrals WHERE referrer_id = ? AND status = 'pending'",   [$userId])['n'] ?? 0);
    $convertedCount = (int)(DB::fetch("SELECT COUNT(*) as n FROM referrals WHERE referrer_id = ? AND status IN ('converted','rewarded')", [$userId])['n'] ?? 0);
    $rewardedCount  = (int)(DB::fetch("SELECT COUNT(*) as n FROM referrals WHERE referrer_id = ? AND status = 'rewarded'", [$userId])['n'] ?? 0);
    $totalEarned    = (float)(DB::fetch("SELECT SUM(reward_amount) as s FROM referrals WHERE referrer_id = ? AND status = 'rewarded'", [$userId])['s'] ?? 0);

    $myReferrals = DB::fetchAll(
        "SELECT r.*, u.name as referred_name, u.company as referred_company, u.created_at as user_created
         FROM referrals r
         LEFT JOIN users u ON r.referred_id = u.id
         WHERE r.referrer_id = ?
         ORDER BY r.created_at DESC",
        [$userId]
    );
} catch (Throwable $e) {}

// Was I referred by someone?
$myReferralSource = null;
try {
    $myReferralSource = DB::fetch(
        "SELECT r.created_at, u.name as referrer_name
         FROM referrals r JOIN users u ON r.referrer_id = u.id
         WHERE r.referred_id = ? LIMIT 1",
        [$userId]
    );
} catch (Throwable $e) {}

$pageTitle = 'Referral Programme';
require_once 'includes/header.php';
?>

<div class="container py-5">

    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/dashboard.php" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-gift me-2 text-success"></i>Referral Programme</h3>
            <p class="text-muted small mb-0">Invite businesses to BizAI and earn rewards for every successful referral</p>
        </div>
    </div>

    <div class="row g-4">

        <!-- Left: Link + How It Works -->
        <div class="col-lg-5">

            <!-- Referral Link Card -->
            <div class="glass-card rounded-4 p-4 mb-4" style="border:1px solid rgba(16,185,129,0.25)">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div style="width:44px;height:44px;border-radius:12px;background:rgba(16,185,129,0.15);display:flex;align-items:center;justify-content:center">
                        <i class="bi bi-link-45deg text-success fs-4"></i>
                    </div>
                    <div>
                        <div class="text-white fw-semibold">Your Referral Link</div>
                        <div class="text-muted small">Share this link to earn rewards</div>
                    </div>
                </div>

                <div class="input-group mb-3">
                    <input type="text" class="form-control bg-dark border-secondary text-white" id="refLink"
                           value="<?= htmlspecialchars($myLink) ?>" readonly style="font-size:13px">
                    <button class="btn btn-success" onclick="copyLink()" id="copyBtn">
                        <i class="bi bi-clipboard me-1"></i>Copy
                    </button>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge bg-dark border border-secondary text-muted px-2 py-1" style="font-size:11px">
                        Code: <strong class="text-white"><?= htmlspecialchars($myCode) ?></strong>
                    </span>
                    <a href="https://wa.me/?text=<?= urlencode('Join me on BizAI — the AI business platform for SMEs. Sign up with my link: ' . $myLink) ?>"
                       target="_blank" class="btn btn-sm btn-outline-success py-0">
                        <i class="bi bi-whatsapp me-1"></i>Share on WhatsApp
                    </a>
                </div>
            </div>

            <!-- How It Works -->
            <div class="glass-card rounded-4 p-4 mb-4">
                <h6 class="text-white fw-semibold mb-3"><i class="bi bi-info-circle me-2 text-primary"></i>How It Works</h6>
                <div class="d-flex gap-3 mb-3">
                    <div class="step-num flex-shrink-0">1</div>
                    <div>
                        <div class="text-white small fw-semibold">Share your link</div>
                        <div class="text-muted small">Send your unique referral link to business owners who could benefit from AI automation.</div>
                    </div>
                </div>
                <div class="d-flex gap-3 mb-3">
                    <div class="step-num flex-shrink-0">2</div>
                    <div>
                        <div class="text-white small fw-semibold">They sign up &amp; subscribe</div>
                        <div class="text-muted small">Your referral registers via your link and starts a paid subscription within 30 days.</div>
                    </div>
                </div>
                <div class="d-flex gap-3">
                    <div class="step-num flex-shrink-0">3</div>
                    <div>
                        <div class="text-white small fw-semibold">You earn a reward</div>
                        <div class="text-muted small">You receive a reward credited to your account for every successful conversion.</div>
                    </div>
                </div>
            </div>

            <!-- My Source -->
            <?php if ($myReferralSource): ?>
            <div class="glass-card rounded-4 p-3" style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2)">
                <div class="text-muted small">
                    <i class="bi bi-person-check me-1 text-primary"></i>
                    You were referred by <strong class="text-white"><?= htmlspecialchars($myReferralSource['referrer_name']) ?></strong>
                    on <?= date('d M Y', strtotime($myReferralSource['created_at'])) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right: Stats + Referral List -->
        <div class="col-lg-7">

            <!-- Stats -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="glass-card rounded-4 p-3 text-center">
                        <div class="fs-4 fw-bold text-white"><?= $totalReferrals ?></div>
                        <div class="text-muted small">Total</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="glass-card rounded-4 p-3 text-center">
                        <div class="fs-4 fw-bold text-warning"><?= $pendingCount ?></div>
                        <div class="text-muted small">Pending</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="glass-card rounded-4 p-3 text-center">
                        <div class="fs-4 fw-bold text-success"><?= $convertedCount ?></div>
                        <div class="text-muted small">Converted</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="glass-card rounded-4 p-3 text-center">
                        <div class="fs-4 fw-bold text-primary"><?= APP_CURRENCY ?><?= number_format($totalEarned, 0) ?></div>
                        <div class="text-muted small">Earned</div>
                    </div>
                </div>
            </div>

            <!-- Referral List -->
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">
                    <i class="bi bi-people me-2"></i>My Referrals
                    <?php if ($totalReferrals): ?>
                    <span class="badge bg-secondary ms-1" style="font-size:10px"><?= $totalReferrals ?></span>
                    <?php endif; ?>
                </h6>

                <?php if (empty($myReferrals)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-person-plus fs-1 text-muted d-block mb-3" style="opacity:.3"></i>
                    <p class="text-muted small mb-3">No referrals yet. Share your link to get started!</p>
                    <button onclick="copyLink()" class="btn btn-success btn-sm px-4">
                        <i class="bi bi-clipboard me-1"></i>Copy Referral Link
                    </button>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover table-sm mb-0 align-middle">
                        <thead>
                            <tr class="text-muted" style="font-size:11px;text-transform:uppercase">
                                <th class="fw-normal pb-2">Referred</th>
                                <th class="fw-normal pb-2">Signed Up</th>
                                <th class="fw-normal pb-2">Status</th>
                                <th class="fw-normal pb-2 text-end">Reward</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($myReferrals as $ref):
                            $badge = match($ref['status']) {
                                'converted' => ['bg-success',           'Converted'],
                                'rewarded'  => ['bg-primary',           'Rewarded'],
                                'expired'   => ['bg-secondary',         'Expired'],
                                default     => ['bg-warning text-dark', 'Pending'],
                            };
                        ?>
                        <tr>
                            <td class="py-2">
                                <div class="text-white small">
                                    <?= $ref['referred_name'] ? htmlspecialchars($ref['referred_name']) : '<span class="text-muted">—</span>' ?>
                                </div>
                                <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($ref['referred_email']) ?></div>
                            </td>
                            <td class="py-2 text-muted small"><?= date('d M Y', strtotime($ref['created_at'])) ?></td>
                            <td class="py-2">
                                <span class="badge <?= $badge[0] ?>" style="font-size:10px"><?= $badge[1] ?></span>
                                <?php if ($ref['status'] === 'converted'): ?>
                                <div class="text-muted" style="font-size:10px;margin-top:2px">Reward pending</div>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 text-end">
                                <?php if ($ref['reward_amount'] > 0): ?>
                                <span class="text-success small fw-semibold"><?= APP_CURRENCY ?><?= number_format($ref['reward_amount'], 0) ?></span>
                                <?php else: ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.step-num {
    width: 28px; height: 28px; border-radius: 50%;
    background: rgba(99,102,241,0.2); color: #6366f1;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700;
}
</style>

<script>
function copyLink() {
    const val = document.getElementById('refLink').value;
    navigator.clipboard.writeText(val).then(() => {
        const btn = document.getElementById('copyBtn');
        btn.innerHTML = '<i class="bi bi-check me-1"></i>Copied!';
        btn.classList.replace('btn-success','btn-outline-success');
        setTimeout(() => {
            btn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy';
            btn.classList.replace('btn-outline-success','btn-success');
        }, 2500);
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
