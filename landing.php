<?php
/**
 * Public Landing Page
 * Served at the root URL: https://yourdomain.com/
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}
require_once BASE_PATH . '/inc/bootstrap.php';

$cfg      = require BASE_PATH . '/config/app.php';
$appName  = $cfg['app_name'] ?? 'F&B Loyalty Platform';

// Load outlet count + settings for social proof
$settings = get_settings(['app_name','landing_tagline','landing_hero_desc','landing_feature1','landing_feature2','landing_feature3']);
$appName  = $settings['app_name'] ?? $appName;
$tagline  = $settings['landing_tagline'] ?? 'Earn Points. Redeem Rewards. Dine Better.';
$heroDesc = $settings['landing_hero_desc'] ?? 'Malaysia\'s smartest dining loyalty programme. Earn points every visit, redeem exclusive rewards, and enjoy VIP member benefits at our restaurants.';

try {
    $outletCount  = (int)(Database::fetchOne('SELECT COUNT(*) AS c FROM outlets WHERE status="active"')['c'] ?? 0);
    $memberCount  = (int)(Database::fetchOne('SELECT COUNT(*) AS c FROM users WHERE role="customer"')['c'] ?? 0);
    $rewardCount  = (int)(Database::fetchOne('SELECT COUNT(*) AS c FROM rewards WHERE status="active"')['c'] ?? 0);
} catch (Exception $e) {
    $outletCount = $memberCount = $rewardCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($appName) ?> – Earn. Redeem. Enjoy.</title>
<meta name="description" content="<?= htmlspecialchars($heroDesc) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
:root {
    --red:   #e94560;
    --dark:  #1a1a2e;
    --navy:  #0f3460;
    --mid:   #16213e;
    --muted: #a8b2d8;
    --gold:  #ffd700;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Inter', system-ui, sans-serif; background: var(--dark); color: #fff; -webkit-font-smoothing: antialiased; }

/* ── NAV ── */
nav.top-nav {
    position: fixed; top: 0; left: 0; right: 0; z-index: 999;
    display: flex; align-items: center; justify-content: space-between;
    padding: .9rem 1.5rem;
    background: rgba(26,26,46,.92);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(255,255,255,.07);
}
.nav-logo { font-weight: 800; font-size: 1.1rem; letter-spacing: -.3px; }
.nav-logo span { color: var(--red); }
.nav-btns { display: flex; gap: .6rem; }
.btn-nav { padding: .45rem 1.1rem; border-radius: 9px; font-size: .85rem; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: opacity .2s; }
.btn-nav:hover { opacity: .85; }
.btn-nav-outline { background: transparent; border: 1.5px solid rgba(255,255,255,.3); color: #fff; }
.btn-nav-solid   { background: var(--red); color: #fff; }

/* ── HERO ── */
.hero {
    min-height: 100vh;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    text-align: center; padding: 7rem 1.5rem 4rem;
    background: linear-gradient(160deg, var(--dark) 0%, var(--navy) 50%, var(--mid) 100%);
    position: relative; overflow: hidden;
}
.hero::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 60% 40%, rgba(233,69,96,.18) 0%, transparent 60%),
                radial-gradient(ellipse at 20% 80%, rgba(15,52,96,.5) 0%, transparent 50%);
}
.hero > * { position: relative; z-index: 1; }
.hero-badge {
    display: inline-flex; align-items: center; gap: .4rem;
    background: rgba(233,69,96,.15); border: 1px solid rgba(233,69,96,.4);
    color: #ff8fa3; font-size: .78rem; font-weight: 600; padding: .35rem .9rem;
    border-radius: 50px; margin-bottom: 1.5rem; letter-spacing: .3px;
}
.hero h1 {
    font-size: clamp(2.2rem, 7vw, 3.8rem); font-weight: 800;
    line-height: 1.15; letter-spacing: -.5px; margin-bottom: 1.2rem;
}
.hero h1 .accent { color: var(--red); }
.hero p.lead {
    font-size: clamp(.95rem, 2.5vw, 1.15rem); color: var(--muted);
    max-width: 520px; line-height: 1.7; margin-bottom: 2.2rem;
}
.hero-cta { display: flex; gap: .8rem; flex-wrap: wrap; justify-content: center; margin-bottom: 3rem; }
.btn-hero-primary {
    background: var(--red); color: #fff; padding: .9rem 2.2rem;
    border-radius: 14px; font-weight: 700; font-size: 1rem;
    text-decoration: none; border: none; cursor: pointer;
    box-shadow: 0 8px 30px rgba(233,69,96,.4); transition: transform .2s, box-shadow .2s;
}
.btn-hero-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 36px rgba(233,69,96,.5); color: #fff; }
.btn-hero-secondary {
    background: rgba(255,255,255,.08); color: #fff; padding: .9rem 2.2rem;
    border-radius: 14px; font-weight: 600; font-size: 1rem;
    text-decoration: none; border: 1.5px solid rgba(255,255,255,.2);
    transition: background .2s;
}
.btn-hero-secondary:hover { background: rgba(255,255,255,.15); color: #fff; }

/* ── STATS ── */
.hero-stats {
    display: flex; gap: 2.5rem; flex-wrap: wrap; justify-content: center;
}
.stat { text-align: center; }
.stat .num { font-size: 1.8rem; font-weight: 800; color: #fff; }
.stat .lbl { font-size: .75rem; color: var(--muted); margin-top: .15rem; }

/* ── FEATURES ── */
section { padding: 5rem 1.5rem; }
.section-label { color: var(--red); font-size: .8rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: .6rem; }
.section-title { font-size: clamp(1.7rem, 4vw, 2.4rem); font-weight: 800; margin-bottom: .8rem; }
.section-sub { color: var(--muted); font-size: .97rem; line-height: 1.7; max-width: 520px; }

.features-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.2rem; margin-top: 3rem; max-width: 960px; margin-left: auto; margin-right: auto;
}
.feat-card {
    background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08);
    border-radius: 20px; padding: 1.8rem;
    transition: background .25s, transform .25s;
}
.feat-card:hover { background: rgba(255,255,255,.07); transform: translateY(-4px); }
.feat-icon { font-size: 2rem; margin-bottom: 1rem; }
.feat-card h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: .5rem; }
.feat-card p { color: var(--muted); font-size: .88rem; line-height: 1.6; }

/* ── HOW IT WORKS ── */
.steps-wrap { max-width: 720px; margin: 3rem auto 0; }
.step {
    display: flex; gap: 1.3rem; align-items: flex-start;
    padding: 1.4rem; border-radius: 16px; margin-bottom: 1rem;
    background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.06);
}
.step-num {
    min-width: 42px; height: 42px; border-radius: 12px;
    background: var(--red); display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 1rem; flex-shrink: 0;
}
.step h4 { font-size: .97rem; font-weight: 700; margin-bottom: .3rem; }
.step p  { color: var(--muted); font-size: .85rem; line-height: 1.6; margin: 0; }

/* ── SUBSCRIBE ── */
.subscribe-section {
    background: linear-gradient(135deg, var(--navy), var(--mid));
    border-radius: 0; padding: 5rem 1.5rem;
    text-align: center;
}
.subscribe-card {
    max-width: 520px; margin: 0 auto;
    background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1);
    border-radius: 24px; padding: 2.5rem 2rem;
}
.subscribe-card h2 { font-size: 1.6rem; font-weight: 800; margin-bottom: .6rem; }
.subscribe-card p { color: var(--muted); font-size: .9rem; margin-bottom: 1.8rem; line-height: 1.6; }
.sub-form { display: flex; flex-direction: column; gap: .85rem; }
.sub-input {
    background: rgba(255,255,255,.08); border: 1.5px solid rgba(255,255,255,.12);
    border-radius: 12px; padding: .85rem 1.1rem; color: #fff;
    font-size: .95rem; font-family: inherit; outline: none; width: 100%;
    transition: border-color .2s;
}
.sub-input::placeholder { color: rgba(255,255,255,.35); }
.sub-input:focus { border-color: var(--red); }
.btn-subscribe {
    background: var(--red); color: #fff; padding: .9rem;
    border-radius: 12px; font-weight: 700; font-size: 1rem;
    border: none; cursor: pointer; width: 100%;
    box-shadow: 0 6px 24px rgba(233,69,96,.35); transition: opacity .2s, transform .2s;
}
.btn-subscribe:hover { opacity: .9; transform: translateY(-1px); }
.btn-subscribe:disabled { opacity: .6; cursor: not-allowed; transform: none; }
#sub-success { display: none; color: #4ade80; font-weight: 600; margin-top: .5rem; }
#sub-error   { display: none; color: #f87171; font-size: .85rem; margin-top: .5rem; }

/* ── FOOTER ── */
footer {
    background: rgba(0,0,0,.4); border-top: 1px solid rgba(255,255,255,.06);
    text-align: center; padding: 2rem 1.5rem;
    color: var(--muted); font-size: .82rem;
}
footer a { color: var(--muted); text-decoration: none; }
footer a:hover { color: #fff; }

/* ── WHATSAPP FLOAT ── */
.wa-float {
    position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 999;
    background: #25d366; color: #fff; border-radius: 50%;
    width: 56px; height: 56px; display: flex; align-items: center; justify-content: center;
    font-size: 1.7rem; text-decoration: none;
    box-shadow: 0 6px 24px rgba(37,211,102,.4); transition: transform .2s;
}
.wa-float:hover { transform: scale(1.1); color: #fff; }

/* ── RESPONSIVE ── */
@media (max-width: 600px) {
    nav.top-nav { padding: .75rem 1rem; }
    .btn-nav { padding: .4rem .85rem; font-size: .8rem; }
    section { padding: 3.5rem 1.2rem; }
}
</style>
</head>
<body>

<!-- Sticky Nav -->
<nav class="top-nav">
    <div class="nav-logo">🍽️ <span><?= htmlspecialchars($appName) ?></span></div>
    <div class="nav-btns">
        <a href="/app/login" class="btn-nav btn-nav-outline">Sign In</a>
        <a href="/app/register" class="btn-nav btn-nav-solid">Join Free</a>
    </div>
</nav>

<!-- Hero -->
<section class="hero">
    <div class="hero-badge"><i class="bi bi-star-fill"></i> Malaysia's #1 F&B Loyalty App</div>
    <h1><?= nl2br(htmlspecialchars($tagline)) ?></h1>
    <p class="lead"><?= htmlspecialchars($heroDesc) ?></p>
    <div class="hero-cta">
        <a href="/app/register" class="btn-hero-primary">
            <i class="bi bi-person-plus-fill me-2"></i>Create Free Account
        </a>
        <a href="#how-it-works" class="btn-hero-secondary">
            How It Works <i class="bi bi-arrow-down ms-1"></i>
        </a>
    </div>
    <div class="hero-stats">
        <?php
        $stats = [
            ['num' => $memberCount > 0 ? number_format($memberCount).'+' : '—', 'lbl' => 'Members'],
            ['num' => $outletCount > 0 ? $outletCount.'+' : '—', 'lbl' => 'Outlets'],
            ['num' => $rewardCount > 0 ? $rewardCount.'+' : '—', 'lbl' => 'Rewards'],
            ['num' => '10×', 'lbl' => 'More Savings'],
        ];
        foreach ($stats as $s):
        ?>
        <div class="stat">
            <div class="num"><?= $s['num'] ?></div>
            <div class="lbl"><?= $s['lbl'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Features -->
<section id="features" style="background:var(--dark);text-align:center;">
    <div class="section-label">Why Choose Us</div>
    <h2 class="section-title">Everything You Love, Rewarded</h2>
    <p class="section-sub" style="margin:0 auto;">Every ringgit you spend earns you points. Redeem them for free meals, discounts, and exclusive perks.</p>

    <div class="features-grid">
        <?php
        $features = [
            ['🏆', 'Earn Points Instantly',    'Get 1 point for every MYR 1 spent. Points credited to your account the moment you dine.'],
            ['🎁', 'Redeem Anytime',            'Use points for free dishes, percentage discounts, or exclusive member-only rewards.'],
            ['📅', 'Easy Reservations',         'Book a table at any outlet in seconds. No phone calls, no waiting — just tap and confirm.'],
            ['🤝', 'Refer & Earn',              'Share your referral code with friends. Both of you earn bonus points when they join.'],
            ['🎂', 'Birthday Surprises',        'We celebrate you! Get exclusive birthday rewards and double points during your birthday month.'],
            ['📱', 'Works on Any Device',       'Install as an app on your phone. No app store needed — just add to home screen and go.'],
        ];
        foreach ($features as [$icon, $title, $desc]):
        ?>
        <div class="feat-card">
            <div class="feat-icon"><?= $icon ?></div>
            <h3><?= $title ?></h3>
            <p><?= $desc ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- How It Works -->
<section id="how-it-works" style="background:linear-gradient(180deg,var(--mid),var(--dark));text-align:center;">
    <div class="section-label">Simple as 1-2-3</div>
    <h2 class="section-title">How It Works</h2>
    <p class="section-sub" style="margin:0 auto;">Getting started takes under 60 seconds.</p>

    <div class="steps-wrap">
        <?php
        $steps = [
            ['1', 'Create Your Free Account',   'Sign up with your phone number. No credit card, no forms — just a quick OTP verification.'],
            ['2', 'Dine & Earn Points',          'Visit any of our outlets, order your meal, and watch points land in your account instantly.'],
            ['3', 'Redeem Amazing Rewards',      'Browse the rewards catalogue and redeem your points for free food, drinks, and exclusive deals.'],
        ];
        foreach ($steps as [$num, $title, $desc]):
        ?>
        <div class="step">
            <div class="step-num"><?= $num ?></div>
            <div>
                <h4><?= $title ?></h4>
                <p><?= $desc ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Subscribe / Early Access -->
<div class="subscribe-section" id="subscribe">
    <div class="subscribe-card">
        <div style="font-size:2.5rem;margin-bottom:.8rem;">🔔</div>
        <h2>Stay in the Loop</h2>
        <p>Enter your phone number to get notified about exclusive promotions, new outlet openings, and member-only deals.</p>
        <div class="sub-form">
            <input type="text" id="sub-name" class="sub-input" placeholder="Your name" autocomplete="name">
            <div style="display:flex;align-items:center;background:rgba(255,255,255,.08);border:1.5px solid rgba(255,255,255,.12);border-radius:12px;overflow:hidden;">
                <span style="padding:.85rem .9rem .85rem 1.1rem;color:rgba(255,255,255,.5);font-size:.9rem;white-space:nowrap;">🇲🇾 +60</span>
                <input type="tel" id="sub-phone" class="sub-input"
                       style="border:none;border-radius:0;background:transparent;padding-left:0;"
                       placeholder="12 3456789" maxlength="10" inputmode="numeric">
            </div>
            <button class="btn-subscribe" id="btn-subscribe" onclick="subscribe()">
                <i class="bi bi-bell-fill me-2"></i>Notify Me
            </button>
        </div>
        <div id="sub-success">🎉 You're subscribed! We'll be in touch soon.</div>
        <div id="sub-error"></div>
        <div style="margin-top:1.5rem;border-top:1px solid rgba(255,255,255,.08);padding-top:1.2rem;">
            <p style="font-size:.82rem;color:var(--muted);margin-bottom:.8rem;">Already a member?</p>
            <a href="/app/login" class="btn-hero-secondary" style="display:inline-block;font-size:.9rem;padding:.7rem 1.8rem;">
                Sign In to Your Account
            </a>
        </div>
    </div>
</div>

<!-- Footer -->
<footer>
    <div style="margin-bottom:.8rem;font-size:1rem;font-weight:700;color:#fff;">🍽️ <?= htmlspecialchars($appName) ?></div>
    <div style="display:flex;justify-content:center;gap:1.5rem;margin-bottom:.8rem;flex-wrap:wrap;">
        <a href="/app/login">Sign In</a>
        <a href="/app/register">Register</a>
        <a href="/app/menu">Menu</a>
        <a href="/app/outlets">Our Outlets</a>
        <a href="#subscribe">Subscribe</a>
    </div>
    <div>&copy; <?= date('Y') ?> <?= htmlspecialchars($appName) ?>. All rights reserved.</div>
</footer>

<!-- WhatsApp Float Button -->
<a class="wa-float" href="https://api.whatsapp.com/send?text=<?= urlencode('Hey! Join ' . $appName . ' loyalty programme and earn rewards when you dine. Sign up free: ' . ($cfg['app_url'] ?? '') . '/app/register') ?>"
   target="_blank" title="Share on WhatsApp">
    <i class="bi bi-whatsapp"></i>
</a>

<script>
async function subscribe() {
    const name  = document.getElementById('sub-name').value.trim();
    const phone = document.getElementById('sub-phone').value.trim().replace(/\D/g,'').replace(/^0+/,'');
    const errEl = document.getElementById('sub-error');
    const sucEl = document.getElementById('sub-success');

    errEl.style.display = 'none';
    sucEl.style.display = 'none';

    if (!name)             { errEl.textContent = 'Please enter your name.';           errEl.style.display=''; return; }
    if (!phone||phone.length < 9) { errEl.textContent = 'Enter a valid phone number.'; errEl.style.display=''; return; }

    const btn = document.getElementById('btn-subscribe');
    btn.disabled = true; btn.textContent = 'Subscribing…';

    try {
        const res = await fetch('/api/auth/subscribe', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, phone: '60' + phone }),
        });
        const data = await res.json();
        if (data.status === 'success') {
            sucEl.style.display = '';
            document.getElementById('sub-name').value  = '';
            document.getElementById('sub-phone').value = '';
        } else {
            errEl.textContent = data.message || 'Something went wrong. Please try again.';
            errEl.style.display = '';
        }
    } catch {
        errEl.textContent = 'Network error. Please try again.';
        errEl.style.display = '';
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-bell-fill" style="margin-right:.5rem;"></i>Notify Me';
}

document.getElementById('sub-phone').addEventListener('keydown', e => {
    if (e.key === 'Enter') subscribe();
});
</script>
</body>
</html>
