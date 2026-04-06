<?php
/**
 * Customer – Rewards Page
 * /public/pages/rewards.php
 */

$appTitle   = 'Rewards – F&B Loyalty';
$showHeader = true;
$pageHeader = 'My Rewards';
require BASE_PATH . '/public/layout/app_shell.php';
?>

<!-- Tab -->
<ul class="nav nav-pills mb-3" id="rewardTabs">
    <li class="nav-item"><button class="nav-link active small" onclick="showTab('available')">Available</button></li>
    <li class="nav-item"><button class="nav-link small" onclick="showTab('redeemed')">My Vouchers</button></li>
</ul>

<!-- Points Balance -->
<div class="d-flex align-items-center gap-2 mb-3 p-3 card-clean">
    <i class="bi bi-star-fill text-warning fs-5"></i>
    <div>
        <div class="small text-muted">Your Points</div>
        <div class="fw-bold" id="my-points-display">Loading…</div>
    </div>
</div>

<!-- Available Rewards -->
<div id="tab-available">
    <div class="row g-3" id="rewards-grid">
        <?php for($i=0;$i<3;$i++): ?>
        <div class="col-6"><div class="skeleton" style="height:200px;border-radius:14px;"></div></div>
        <?php endfor; ?>
    </div>
</div>

<!-- Redeemed / My Vouchers -->
<div id="tab-redeemed" style="display:none;">
    <div id="vouchers-list">
        <div class="text-center text-muted py-4 small">Loading…</div>
    </div>
</div>

<!-- Redeem Modal -->
<div class="modal fade" id="redeemModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold">Confirm Redemption</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="redeem-modal-body"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-sm text-white" style="background:#e94560;" id="btn-confirm-redeem">Confirm Redeem</button>
            </div>
        </div>
    </div>
</div>

<?php require BASE_PATH . '/public/layout/app_footer.php'; ?>

<script>
// NOTE: this script is AFTER app_footer.php so apiCall() is already defined
let selectedRewardId = null;
let redeemModal      = null;

function showTab(tab) {
    document.getElementById('tab-available').style.display = tab === 'available' ? '' : 'none';
    document.getElementById('tab-redeemed').style.display  = tab === 'redeemed'  ? '' : 'none';
    document.querySelectorAll('#rewardTabs .nav-link')
        .forEach((b, i) => b.classList.toggle('active',
            (tab === 'available' && i === 0) || (tab === 'redeemed' && i === 1)));
    if (tab === 'redeemed') loadVouchers();
}

async function loadRewards() {
    const grid = document.getElementById('rewards-grid');
    try {
        const [balRes, rwRes] = await Promise.all([
            apiCall('loyalty/balance'),
            apiCall('rewards/list'),
        ]);

        const myPoints = balRes.status === 'success' ? balRes.data.total_points : 0;
        if (balRes.status === 'success') {
            document.getElementById('my-points-display').textContent =
                myPoints.toLocaleString() + ' pts';
        }

        if (rwRes.status !== 'success' || !rwRes.data.rewards.length) {
            grid.innerHTML = '<div class="col-12 text-center text-muted py-5">No rewards available yet.</div>';
            return;
        }

        grid.innerHTML = rwRes.data.rewards.map(r => {
            const canRedeem = myPoints >= r.points_required;
            const onclick   = canRedeem
                ? `openRedeem(${r.id},'${r.name.replace(/'/g,"\\'")}',${r.points_required})`
                : `showToast('Not enough points','error')`;
            return `
            <div class="col-6">
                <div class="reward-card ${canRedeem ? '' : 'opacity-75'}" onclick="${onclick}">
                    <div style="height:100px;background:linear-gradient(135deg,#1a1a2e,#16213e);
                                display:flex;align-items:center;justify-content:center;font-size:2.5rem;">🎁</div>
                    <div class="reward-card-body">
                        <div class="small fw-semibold mb-1" style="font-size:.82rem;">${r.name}</div>
                        <span class="reward-pts">${parseInt(r.points_required).toLocaleString()} pts</span>
                        ${r.valid_until
                            ? `<div class="text-muted" style="font-size:.7rem;margin-top:4px;">Expires ${r.valid_until}</div>`
                            : ''}
                        ${!canRedeem
                            ? `<div class="text-danger mt-1" style="font-size:.7rem;">Need ${(r.points_required - myPoints).toLocaleString()} more pts</div>`
                            : ''}
                    </div>
                </div>
            </div>`;
        }).join('');

    } catch (e) {
        console.error('loadRewards error:', e);
        grid.innerHTML = '<div class="col-12 text-center text-muted py-5">Could not load rewards.</div>';
    }
}

async function loadVouchers() {
    const res  = await apiCall('rewards/my');
    const list = document.getElementById('vouchers-list');
    if (res.status !== 'success' || !res.data.redemptions.length) {
        list.innerHTML = '<div class="text-center text-muted py-5">No vouchers yet.</div>';
        return;
    }
    list.innerHTML = res.data.redemptions.map(v => `
        <div class="card-clean mb-2 p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-semibold small">${v.reward_name}</div>
                    <div class="text-muted" style="font-size:.75rem;">
                        ${new Date(v.redeemed_at).toLocaleDateString('en-MY')}
                    </div>
                </div>
                <span class="badge bg-${v.status === 'pending' ? 'success' : v.status === 'used' ? 'secondary' : 'danger'}">
                    ${v.status}
                </span>
            </div>
            ${v.voucher_code
                ? `<div class="mt-2 p-2 text-center rounded" style="background:#f8f9ff;">
                       <code class="fs-6 fw-bold">${v.voucher_code}</code>
                   </div>`
                : ''}
        </div>`).join('');
}

function openRedeem(id, name, points) {
    if (!redeemModal) redeemModal = new bootstrap.Modal(document.getElementById('redeemModal'));
    selectedRewardId = id;
    document.getElementById('redeem-modal-body').innerHTML = `
        <div class="text-center py-2">
            <div style="font-size:3rem;">🎁</div>
            <div class="fw-bold mt-2">${name}</div>
            <div class="text-muted small mt-1">
                This will deduct <strong>${points.toLocaleString()} pts</strong> from your balance.
            </div>
        </div>`;
    redeemModal.show();
}

document.getElementById('btn-confirm-redeem').onclick = async () => {
    if (!selectedRewardId) return;
    const btn = document.getElementById('btn-confirm-redeem');
    btn.disabled    = true;
    btn.textContent = 'Processing…';

    const res = await apiCall('rewards/redeem', 'POST', { reward_id: selectedRewardId });
    redeemModal.hide();

    if (res.status === 'success') {
        showToast('Redeemed! Voucher: ' + res.data.voucher_code, 'success');
        loadRewards();
    } else {
        showToast(res.message || 'Redemption failed.', 'error');
    }
    btn.disabled    = false;
    btn.textContent = 'Confirm Redeem';
};

// Init – apiCall is guaranteed available since app_footer.php is above
if (!localStorage.getItem('fnb_token')) {
    window.location.href = '/app/login';
} else {
    loadRewards();
}
</script>
