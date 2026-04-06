<?php
/**
 * Customer – Referral Program
 * /public/pages/referrals.php
 */

$appTitle   = 'Referrals – F&B Loyalty';
$showHeader = true;
$pageHeader = 'Refer & Earn';
$headerBack = '/app/profile';
require BASE_PATH . '/public/layout/app_shell.php';

$settings = get_settings(['referral_reward_points','referral_referee_points']);
$refPts    = (int)($settings['referral_reward_points']  ?? 100);
$refBonus  = (int)($settings['referral_referee_points'] ?? 50);
?>

<!-- Hero -->
<div class="points-hero mb-4 text-center">
    <div style="font-size:3rem;">🤝</div>
    <h5 class="fw-bold mt-2 mb-1">Refer & Earn Points</h5>
    <p class="small mb-0" style="color:#a8b2d8;">
        Invite friends and earn <strong class="text-white"><?= $refPts ?> pts</strong> for each signup.
        They get <strong class="text-white"><?= $refBonus ?> pts</strong> too!
    </p>
</div>

<!-- Referral Code Card -->
<div class="card-clean p-3 mb-3">
    <div class="text-center text-muted small mb-2 fw-semibold">Your Referral Code</div>
    <div class="d-flex align-items-center gap-2">
        <div id="ref-code-display" class="flex-grow-1 p-3 text-center fw-bold fs-4 rounded-3"
             style="background:#f0f7ff;letter-spacing:4px;font-family:monospace;color:#0d6efd;">
            ––––––
        </div>
        <button class="btn btn-outline-primary btn-sm" onclick="copyCode()" id="btn-copy">
            <i class="bi bi-copy"></i>
        </button>
    </div>
    <div class="mt-2 text-center">
        <button class="btn btn-sm w-100 text-white mt-1" style="background:#25d366;" onclick="shareWhatsApp()">
            <i class="bi bi-whatsapp me-1"></i>Share on WhatsApp
        </button>
    </div>
</div>

<!-- Stats -->
<div class="row g-2 mb-3">
    <div class="col-6">
        <div class="card-clean p-3 text-center">
            <div class="fs-3 fw-bold text-primary" id="ref-count">–</div>
            <div class="text-muted small">Friends Referred</div>
        </div>
    </div>
    <div class="col-6">
        <div class="card-clean p-3 text-center">
            <div class="fs-3 fw-bold text-success" id="ref-pts-earned">–</div>
            <div class="text-muted small">Points Earned</div>
        </div>
    </div>
</div>

<!-- How it Works -->
<div class="app-section">
    <div class="app-section-title">How It Works</div>
    <div class="card-clean">
        <?php
        $steps = [
            ['1️⃣','Share your referral code or link with a friend.',         '#0d6efd','rgba(13,110,253,.1)'],
            ['2️⃣','Your friend signs up using your code.',                    '#6f42c1','rgba(111,66,193,.1)'],
            ['3️⃣','Both of you earn bonus points instantly!',                 '#198754','rgba(25,135,84,.1)'],
        ];
        foreach ($steps as [$num, $text, $color, $bg]):
        ?>
        <div class="list-item-clean">
            <div class="list-item-icon" style="background:<?= $bg ?>;font-size:1.2rem;"><?= $num ?></div>
            <div class="small"><?= $text ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Referral History -->
<div class="app-section">
    <div class="app-section-title">Referral History</div>
    <div class="card-clean" id="ref-history">
        <div class="text-center text-muted py-3 small">Loading…</div>
    </div>
</div>

<?php require BASE_PATH . '/public/layout/app_footer.php'; ?>
<script>
let myRefCode = '';
let myRefLink = '';

async function loadReferrals() {
    const res = await apiCall('referrals/my');
    if (res.status !== 'success') return;

    myRefCode = res.data.referral_code;
    myRefLink = res.data.referral_link;

    document.getElementById('ref-code-display').textContent  = myRefCode;
    document.getElementById('ref-count').textContent         = res.data.total_referrals;
    document.getElementById('ref-pts-earned').textContent    = parseInt(res.data.total_pts_earned).toLocaleString() + ' pts';

    const history = document.getElementById('ref-history');
    if (res.data.referrals.length) {
        history.innerHTML = res.data.referrals.map(r => `
            <div class="list-item-clean">
                <div class="list-item-icon" style="background:rgba(25,135,84,.1);color:#198754;font-size:1.1rem;">👤</div>
                <div class="flex-grow-1 small fw-semibold">${r.name}</div>
                <div class="text-muted" style="font-size:.75rem;">${new Date(r.created_at).toLocaleDateString('en-MY',{day:'numeric',month:'short',year:'numeric'})}</div>
            </div>`).join('');
    } else {
        history.innerHTML = '<div class="text-center text-muted py-3 small">No referrals yet. Start sharing!</div>';
    }
}

function copyCode() {
    navigator.clipboard.writeText(myRefCode).then(() => showToast('Code copied!', 'success'));
}

function shareWhatsApp() {
    const msg = encodeURIComponent(`🍽️ Join me on F&B Loyalty and enjoy great dining rewards!\n\nUse my referral code *${myRefCode}* to get bonus points when you sign up.\n\n${myRefLink}`);
    window.open(`https://api.whatsapp.com/send?text=${msg}`, '_blank');
}

if (!localStorage.getItem('fnb_token')) { window.location.href = '/app/login'; }
else { loadReferrals(); }
</script>

