<?php
/**
 * Customer Dashboard / Home
 * /public/pages/dashboard.php
 */

$appTitle   = 'Home – F&B Loyalty';
$showHeader = false;

require BASE_PATH . '/public/layout/app_shell.php';
?>

<!-- DEBUG PANEL (temporary) -->
<div id="debug-panel" style="display:none;background:#1a1a2e;color:#e0e0e0;font-family:monospace;font-size:.8rem;padding:.75rem 1rem;margin-bottom:1rem;border-radius:10px;border:1px solid #e94560;"></div>

<!-- Hero greeting section (populated by JS) -->
<div class="points-hero mb-3" id="hero-card">
    <div class="d-flex justify-content-between align-items-start mb-2">
        <div>
            <div class="small mb-1" style="color:#a8b2d8;">Good <?= (date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening')) ?> 👋</div>
            <div class="fw-bold fs-5" id="hero-name"><span class="skeleton" style="width:120px;height:20px;display:inline-block;"></span></div>
        </div>
        <div id="hero-tier">
            <span class="skeleton" style="width:70px;height:22px;border-radius:20px;display:inline-block;"></span>
        </div>
    </div>

    <div class="big-pts" id="hero-points">–</div>
    <div class="pts-label">Loyalty Points</div>

    <!-- Progress to next tier -->
    <div class="mt-3" id="hero-progress" style="display:none;">
        <div class="d-flex justify-content-between small mb-1" style="color:#a8b2d8;">
            <span id="tier-progress-label">Progress to next tier</span>
            <span id="tier-progress-pct"></span>
        </div>
        <div class="tier-progress">
            <div class="tier-progress-fill" id="tier-progress-bar" style="width:0%;background:#e94560;"></div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="app-section">
    <div class="row g-2">
        <?php
        $quickActions = [
            ['icon'=>'menu-button-wide-fill','label'=>'Order Now',  'href'=>'/app/menu',         'bg'=>'rgba(233,69,96,.1)',    'color'=>'#e94560'],
            ['icon'=>'gift-fill',            'label'=>'Redeem',     'href'=>'/app/rewards',      'bg'=>'rgba(255,193,7,.1)',    'color'=>'#ffc107'],
            ['icon'=>'calendar-check',       'label'=>'Book Table', 'href'=>'/app/reservations', 'bg'=>'rgba(13,202,240,.1)',   'color'=>'#0dcaf0'],
            ['icon'=>'share',                'label'=>'Refer',      'href'=>'/app/referrals',    'bg'=>'rgba(25,135,84,.1)',    'color'=>'#198754'],
        ];
        foreach ($quickActions as $qa):
        ?>
        <div class="col-3">
            <a href="<?= $qa['href'] ?>" class="text-decoration-none">
                <div class="card-clean text-center py-3">
                    <div class="mb-1" style="font-size:1.5rem; background:<?= $qa['bg'] ?>; width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; margin:0 auto;">
                        <i class="bi bi-<?= $qa['icon'] ?>" style="color:<?= $qa['color'] ?>;"></i>
                    </div>
                    <div style="font-size:.72rem;color:#666;font-weight:600;"><?= $qa['label'] ?></div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Available Rewards Teaser -->
<div class="app-section">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="app-section-title mb-0">Rewards For You</div>
        <a href="/app/rewards" class="small text-decoration-none" style="color:#e94560;">See all →</a>
    </div>
    <div class="d-flex gap-2 overflow-auto pb-1" id="rewards-row" style="scrollbar-width:none;">
        <!-- Loaded by JS -->
        <div class="skeleton" style="min-width:140px;height:180px;border-radius:14px;"></div>
        <div class="skeleton" style="min-width:140px;height:180px;border-radius:14px;"></div>
        <div class="skeleton" style="min-width:140px;height:180px;border-radius:14px;"></div>
    </div>
</div>

<!-- Recent Activity -->
<div class="app-section">
    <div class="app-section-title">Recent Activity</div>
    <div class="card-clean" id="activity-list">
        <div class="list-item-clean">
            <div class="skeleton" style="width:38px;height:38px;border-radius:10px;"></div>
            <div class="flex-grow-1"><div class="skeleton" style="width:60%;height:14px;border-radius:4px;"></div></div>
        </div>
    </div>
</div>

<script>
const tierColors = { bronze:'#cd7f32', silver:'#a8a9ad', gold:'#ffd700', platinum:'#b5c4d4' };
const tierIcons  = { bronze:'🥉', silver:'🥈', gold:'🥇', platinum:'💎' };

async function loadDashboard() {
    try {
        const res = await apiCall('customers/dashboard');
        if (res.status !== 'success') { window.location.href = '/app/login'; return; }
        const d = res.data;

        // Hero
        document.getElementById('hero-name').textContent    = d.name;
        document.getElementById('hero-points').textContent  = d.total_points.toLocaleString() + ' pts';
        document.getElementById('hero-tier').innerHTML = `
            <span class="tier-badge tier-${d.tier}">${tierIcons[d.tier]} ${d.tier.charAt(0).toUpperCase()+d.tier.slice(1)}</span>`;

        // Progress bar
        if (d.points_to_next_tier !== undefined && d.next_tier) {
            document.getElementById('hero-progress').style.display = '';
            const tierThresholds = { bronze:0, silver:500, gold:2000, platinum:5000 };
            const curMin = tierThresholds[d.tier] || 0;
            const nextMin = tierThresholds[d.next_tier] || 5000;
            const pct = Math.min(100, Math.round((d.lifetime_points - curMin) / (nextMin - curMin) * 100));
            document.getElementById('tier-progress-bar').style.width = pct + '%';
            document.getElementById('tier-progress-bar').style.background = tierColors[d.next_tier];
            document.getElementById('tier-progress-label').textContent = `Progress to ${d.next_tier}`;
            document.getElementById('tier-progress-pct').textContent = pct + '%';
        }

        // Activity
        const actList = document.getElementById('activity-list');
        if (d.recent_transactions && d.recent_transactions.length > 0) {
            actList.innerHTML = d.recent_transactions.map(t => `
                <div class="list-item-clean">
                    <div class="list-item-icon" style="background:${t.points>0?'rgba(25,135,84,.1)':'rgba(220,53,69,.1)'};">
                        <i class="bi bi-${t.points>0?'plus-circle-fill':'dash-circle-fill'}" style="color:${t.points>0?'#198754':'#dc3545'};"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="small fw-semibold">${t.description || t.type}</div>
                        <div class="text-muted" style="font-size:.75rem;">${new Date(t.created_at).toLocaleDateString('en-MY', {day:'numeric',month:'short'})}</div>
                    </div>
                    <div class="${t.points>0?'text-success':'text-danger'} fw-bold small">${t.points>0?'+':''}${t.points.toLocaleString()} pts</div>
                </div>`).join('');
        } else {
            actList.innerHTML = '<div class="text-center text-muted py-4 small">No activity yet. Start earning points!</div>';
        }
    } catch(e) {
        console.error('Dashboard load error:', e);
        // Show fallback so skeleton doesn't stay forever
        const heroName = document.getElementById('hero-name');
        if (heroName && heroName.querySelector('.skeleton')) heroName.textContent = 'Welcome!';
        document.getElementById('hero-points').textContent = '0 pts';
        document.getElementById('hero-tier').innerHTML = '<span class="tier-badge tier-bronze">🥉 Bronze</span>';
        document.getElementById('activity-list').innerHTML =
            '<div class="text-center text-muted py-4 small">Could not load activity.</div>';
    }
}

async function loadRewards() {
    try {
        const res = await apiCall('rewards/list');
        if (res.status !== 'success') {
            document.getElementById('rewards-row').innerHTML =
                '<div class="text-muted small py-2">No rewards available yet.</div>';
            return;
        }
        const row = document.getElementById('rewards-row');
        if (!res.data.rewards.length) {
            row.innerHTML = '<div class="text-muted small py-2">No rewards available yet.</div>';
            return;
        }
        row.innerHTML = res.data.rewards.slice(0, 6).map(r => `
            <div class="reward-card" style="min-width:140px;" onclick="window.location='/app/rewards'">
                <div style="height:90px;background:linear-gradient(135deg,#1a1a2e,#0f3460);display:flex;align-items:center;justify-content:center;font-size:2rem;">🎁</div>
                <div class="reward-card-body">
                    <div class="small fw-semibold mb-1" style="font-size:.8rem;">${r.name}</div>
                    <span class="reward-pts">${parseInt(r.points_required).toLocaleString()} pts</span>
                </div>
            </div>`).join('');
    } catch(e) {}
}

// ── DEBUG PANEL (remove after fix) ──────────────────────────────────────
async function runDebug() {
    const dbg = document.getElementById('debug-panel');
    const token = localStorage.getItem('fnb_token');
    dbg.style.display = '';
    dbg.innerHTML = `<b>Token:</b> ${token ? token.substring(0,16)+'...' : '❌ NONE'}<br>`;

    if (!token) { dbg.innerHTML += '⛔ No token → redirecting to login'; return; }

    try {
        const raw = await fetch('/api/customers/dashboard', {
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token,
                'X-Auth-Token': token,
            }
        });
        const text = await raw.text();
        dbg.innerHTML += `<b>HTTP Status:</b> ${raw.status}<br>`;
        dbg.innerHTML += `<b>Response:</b><pre style="white-space:pre-wrap;word-break:break-all;font-size:.75rem;background:#111;padding:.5rem;border-radius:6px;max-height:200px;overflow:auto;">${text.substring(0, 800)}</pre>`;
    } catch(e) {
        dbg.innerHTML += `<b>Fetch error:</b> ${e.message}`;
    }
}

// Check token
if (!localStorage.getItem('fnb_token')) {
    window.location.href = '/app/login';
} else {
    runDebug();
    loadDashboard();
    loadRewards();
}
</script>

<?php require BASE_PATH . '/public/layout/app_footer.php'; ?>
