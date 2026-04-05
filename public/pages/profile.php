<?php
/**
 * Customer – Profile Page
 * /public/pages/profile.php
 */

$appTitle   = 'Profile – F&B Loyalty';
$showHeader = true;
$pageHeader = 'My Profile';
require BASE_PATH . '/public/layout/app_shell.php';
?>

<!-- Profile Header -->
<div class="points-hero mb-3 text-center">
    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white mb-2"
         style="width:64px;height:64px;font-size:1.8rem;" id="avatar-circle">👤</div>
    <div class="fw-bold fs-5" id="prof-name">–</div>
    <div class="text-muted small" id="prof-phone">–</div>
    <div class="mt-2" id="prof-tier-badge">
        <span class="tier-badge tier-bronze">🥉 Bronze</span>
    </div>

    <div class="row text-center g-0 mt-3">
        <div class="col-4 border-end py-2" style="border-color:rgba(255,255,255,.1) !important;">
            <div class="fw-bold" id="prof-points">–</div>
            <div class="pts-label">Points</div>
        </div>
        <div class="col-4 border-end py-2" style="border-color:rgba(255,255,255,.1) !important;">
            <div class="fw-bold" id="prof-lifetime">–</div>
            <div class="pts-label">Lifetime</div>
        </div>
        <div class="col-4 py-2">
            <div class="fw-bold" id="prof-referrals">–</div>
            <div class="pts-label">Referrals</div>
        </div>
    </div>
</div>

<!-- Quick Links -->
<div class="app-section">
    <div class="card-clean">
        <?php
        $links = [
            ['icon'=>'bell',         'label'=>'Notifications',     'href'=>'/app/notifications', 'color'=>'#e94560', 'bg'=>'rgba(233,69,96,.1)'],
            ['icon'=>'share',        'label'=>'Referral Program',  'href'=>'/app/referrals',     'color'=>'#198754', 'bg'=>'rgba(25,135,84,.1)'],
            ['icon'=>'receipt',      'label'=>'My Orders',         'href'=>'/app/orders',        'color'=>'#0d6efd', 'bg'=>'rgba(13,110,253,.1)'],
            ['icon'=>'calendar-check','label'=>'My Reservations',  'href'=>'/app/reservations',  'color'=>'#6f42c1', 'bg'=>'rgba(111,66,193,.1)'],
            ['icon'=>'star',         'label'=>'Loyalty History',   'href'=>'#loyalty',           'color'=>'#ffc107', 'bg'=>'rgba(255,193,7,.1)'],
            ['icon'=>'pencil-square','label'=>'Edit Profile',      'href'=>'#edit',              'color'=>'#20c997', 'bg'=>'rgba(32,201,151,.1)'],
            ['icon'=>'shield-lock',  'label'=>'Change Password',   'href'=>'#password',          'color'=>'#6f42c1', 'bg'=>'rgba(111,66,193,.1)'],
            ['icon'=>'box-arrow-right','label'=>'Sign Out',        'href'=>'/app/logout',        'color'=>'#dc3545', 'bg'=>'rgba(220,53,69,.1)'],
        ];
        foreach ($links as $l):
        ?>
        <a href="<?= $l['href'] ?>" class="list-item-clean text-decoration-none text-dark" id="link-<?= str_replace([' ','#'],['_',''],$l['label']) ?>">
            <div class="list-item-icon" style="background:<?= $l['bg'] ?>; color:<?= $l['color'] ?>;">
                <i class="bi bi-<?= $l['icon'] ?>"></i>
            </div>
            <div class="flex-grow-1 small fw-semibold"><?= $l['label'] ?></div>
            <i class="bi bi-chevron-right text-muted" style="font-size:.8rem;"></i>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Edit Profile Drawer (offcanvas) -->
<div class="offcanvas offcanvas-bottom" tabindex="-1" id="editCanvas" style="height:85vh;border-radius:24px 24px 0 0;">
    <div class="offcanvas-header border-0">
        <h6 class="offcanvas-title fw-bold">Edit Profile</h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div class="mb-3">
            <label class="form-label fw-semibold small">Full Name</label>
            <input type="text" id="edit-name" class="form-control form-control-app">
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold small">Email</label>
            <input type="email" id="edit-email" class="form-control form-control-app" placeholder="Optional">
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold small">Date of Birth</label>
            <input type="date" id="edit-dob" class="form-control form-control-app">
        </div>
        <div class="mb-4">
            <label class="form-label fw-semibold small">Gender</label>
            <select id="edit-gender" class="form-select form-control-app">
                <option value="">Prefer not to say</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
            </select>
        </div>
        <button class="btn-brand" id="btn-save-profile">Save Changes</button>
    </div>
</div>

<!-- Change Password Offcanvas -->
<div class="offcanvas offcanvas-bottom" tabindex="-1" id="passwordCanvas" style="height:auto;max-height:85vh;border-radius:24px 24px 0 0;">
    <div class="offcanvas-header border-0">
        <h6 class="offcanvas-title fw-bold">Change Password</h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <p class="text-muted small mb-3">
            Set a password to sign in without OTP.<br>
            If you've never set a password, leave "Current password" blank.
        </p>
        <div class="mb-3" id="current-pw-row">
            <label class="form-label fw-semibold small">Current Password</label>
            <input type="password" id="pw-current" class="form-control form-control-app"
                   placeholder="Leave blank if setting for the first time" autocomplete="current-password">
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold small">New Password *</label>
            <input type="password" id="pw-new" class="form-control form-control-app"
                   placeholder="Min. 8 characters" autocomplete="new-password">
        </div>
        <div class="mb-4">
            <label class="form-label fw-semibold small">Confirm New Password *</label>
            <input type="password" id="pw-confirm" class="form-control form-control-app"
                   placeholder="Repeat new password" autocomplete="new-password">
        </div>
        <button class="btn-brand" id="btn-save-password">Save Password</button>
    </div>
</div>

<!-- Loyalty History Offcanvas -->
<div class="offcanvas offcanvas-bottom" tabindex="-1" id="loyaltyCanvas" style="height:85vh;border-radius:24px 24px 0 0;">
    <div class="offcanvas-header border-0">
        <h6 class="offcanvas-title fw-bold">Points History</h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body" id="loyalty-history-body">
        <div class="text-center text-muted py-4 small">Loading…</div>
    </div>
</div>

<script>
const editCanvas     = new bootstrap.Offcanvas(document.getElementById('editCanvas'));
const passwordCanvas = new bootstrap.Offcanvas(document.getElementById('passwordCanvas'));
const loyaltyCanvas  = new bootstrap.Offcanvas(document.getElementById('loyaltyCanvas'));

const tierIcons  = { bronze:'🥉', silver:'🥈', gold:'🥇', platinum:'💎' };
const tierColors = { bronze:'bronze', silver:'silver', gold:'gold', platinum:'platinum' };

async function loadProfile() {
    const [profRes, refRes] = await Promise.all([
        apiCall('customers/profile'),
        apiCall('referrals/my'),
    ]);

    if (profRes.status !== 'success') { window.location.href = '/app/login'; return; }

    const u = profRes.data.user;
    const p = profRes.data.profile;

    document.getElementById('prof-name').textContent    = u.name;
    document.getElementById('prof-phone').textContent   = u.phone;
    document.getElementById('prof-points').textContent  = parseInt(p.total_points).toLocaleString();
    document.getElementById('prof-lifetime').textContent= parseInt(p.lifetime_points).toLocaleString();
    document.getElementById('avatar-circle').textContent = u.name.charAt(0).toUpperCase();

    const tier = p.tier || 'bronze';
    document.getElementById('prof-tier-badge').innerHTML =
        `<span class="tier-badge tier-${tier}">${tierIcons[tier]} ${tier.charAt(0).toUpperCase()+tier.slice(1)}</span>`;

    // Pre-fill edit form
    document.getElementById('edit-name').value   = u.name;
    document.getElementById('edit-email').value  = u.email || '';
    document.getElementById('edit-dob').value    = p.date_of_birth || '';
    document.getElementById('edit-gender').value = p.gender || '';

    if (refRes.status === 'success') {
        document.getElementById('prof-referrals').textContent = refRes.data.total_referrals;
    }
}

document.getElementById('link-Edit_Profile').onclick = e => {
    e.preventDefault(); editCanvas.show();
};
document.getElementById('link-Change_Password').onclick = e => {
    e.preventDefault();
    document.getElementById('pw-current').value = '';
    document.getElementById('pw-new').value     = '';
    document.getElementById('pw-confirm').value = '';
    passwordCanvas.show();
};
document.getElementById('link-Loyalty_History').onclick = e => {
    e.preventDefault();
    loyaltyCanvas.show();
    loadLoyaltyHistory();
};
document.getElementById('link-Sign_Out').onclick = async e => {
    e.preventDefault();
    await apiCall('auth/logout', 'POST');
    localStorage.removeItem('fnb_token');
    localStorage.removeItem('fnb_user');
    window.location.href = '/app/login';
};

document.getElementById('btn-save-profile').onclick = async () => {
    const btn = document.getElementById('btn-save-profile');
    btn.disabled = true; btn.textContent = 'Saving…';

    const res = await apiCall('customers/profile', 'PUT', {
        name:          document.getElementById('edit-name').value,
        email:         document.getElementById('edit-email').value,
        date_of_birth: document.getElementById('edit-dob').value,
        gender:        document.getElementById('edit-gender').value,
    });

    if (res.status === 'success') {
        showToast('Profile updated!', 'success');
        editCanvas.hide();
        loadProfile();
    } else {
        showToast(res.message, 'error');
    }
    btn.disabled = false; btn.textContent = 'Save Changes';
};

document.getElementById('btn-save-password').onclick = async () => {
    const current = document.getElementById('pw-current').value;
    const nw      = document.getElementById('pw-new').value;
    const confirm = document.getElementById('pw-confirm').value;

    if (!nw) { showToast('Enter a new password.', 'error'); return; }
    if (nw.length < 8) { showToast('Password must be at least 8 characters.', 'error'); return; }
    if (nw !== confirm) { showToast('Passwords do not match.', 'error'); return; }

    const btn = document.getElementById('btn-save-password');
    btn.disabled = true; btn.textContent = 'Saving…';

    const res = await apiCall('auth/set-password', 'POST', {
        current_password: current || undefined,
        new_password:     nw,
        confirm_password: confirm,
    });

    if (res.status === 'success') {
        showToast('Password saved! You can now sign in with password.', 'success');
        passwordCanvas.hide();
    } else {
        showToast(res.message || 'Failed to save password.', 'error');
    }
    btn.disabled = false; btn.textContent = 'Save Password';
};

async function loadLoyaltyHistory() {
    const res = await apiCall('loyalty/history?per_page=30');
    const body = document.getElementById('loyalty-history-body');
    if (res.status !== 'success' || !res.data.transactions.length) {
        body.innerHTML = '<div class="text-center text-muted py-4">No transactions yet.</div>';
        return;
    }
    body.innerHTML = res.data.transactions.map(t => `
        <div class="list-item-clean">
            <div class="list-item-icon" style="background:${t.points>0?'rgba(25,135,84,.1)':'rgba(220,53,69,.1)'};">
                <i class="bi bi-${t.points>0?'plus-circle-fill':'dash-circle-fill'}" style="color:${t.points>0?'#198754':'#dc3545'};"></i>
            </div>
            <div class="flex-grow-1">
                <div class="small fw-semibold">${t.description || t.type}</div>
                <div class="text-muted" style="font-size:.73rem;">${new Date(t.created_at).toLocaleString('en-MY',{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'})}</div>
            </div>
            <div class="${t.points>0?'text-success':'text-danger'} fw-bold small">${t.points>0?'+':''}${t.points.toLocaleString()} pts</div>
        </div>`).join('');
}

if (!localStorage.getItem('fnb_token')) { window.location.href = '/app/login'; }
else { loadProfile(); }
</script>

<?php require BASE_PATH . '/public/layout/app_footer.php'; ?>
