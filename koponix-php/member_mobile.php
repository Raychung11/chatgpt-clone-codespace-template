<?php
// ============================================================
//  KOPONIX – Mobile-First Member Dashboard
//  Standalone page – does NOT use layout.php
// ============================================================
require_once __DIR__ . '/functions.php';
session_start_safe();

// ── Auth: handle login form submission ───────────────────────
$login_error = '';
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $kop_id = trim($_POST['koperasi_id'] ?? '');
    $pw     = $_POST['password'] ?? '';
    if (!$kop_id || !$pw) {
        $login_error = 'Please enter your Member ID and password.';
    } else {
        $m = authenticate_member($kop_id, $pw);
        if ($m) {
            login_member($m);
            header('Location: member_mobile.php');
            exit;
        } else {
            $login_error = 'Invalid Member ID or password.';
        }
    }
}

// ── Flash message ────────────────────────────────────────────
$flash = get_flash();

// ── Not logged in: show login screen ────────────────────────
if (!is_logged_in()):
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1a5276">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title>Koponix – Member Login</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🤝</text></svg>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; }
body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(160deg, #1a5276 0%, #2e86c1 55%, #5dade2 100%);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
}
.login-card {
    background: #fff;
    border-radius: 20px;
    padding: 32px 24px;
    width: 100%;
    max-width: 400px;
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
}
.login-logo { text-align: center; margin-bottom: 24px; }
.login-logo .licon { font-size: 3rem; }
.login-logo .lbrand { font-size: 1.6rem; font-weight: 800; color: #1a5276; letter-spacing: -.5px; margin-top: 4px; }
.login-logo .lsub   { font-size: .8rem; color: #777; }
.form-label { font-size: .82rem; font-weight: 600; color: #333; }
.form-control { border-radius: 10px; font-size: .9rem; padding: 10px 14px; }
.form-control:focus { border-color: #2e86c1; box-shadow: 0 0 0 3px rgba(46,134,193,.15); }
.btn-login {
    background: #1a5276; color: #fff; border: none;
    border-radius: 12px; padding: 12px; font-size: 1rem;
    font-weight: 700; width: 100%; margin-top: 4px; transition: background .15s;
    cursor: pointer; font-family: 'Inter', sans-serif;
}
.btn-login:hover { background: #2e86c1; }
.login-footer { text-align: center; margin-top: 16px; font-size: .78rem; color: #999; }
.login-footer a { color: #2e86c1; text-decoration: none; }
.alert-err { background: #fde8e8; color: #c0392b; border-radius: 10px; padding: 10px 14px; font-size: .84rem; margin-bottom: 16px; }
</style>
</head>
<body>
<div class="login-card">
    <div class="login-logo">
        <div class="licon">🤝</div>
        <div class="lbrand">Koponix</div>
        <div class="lsub">Koperasi Kakitangan Bank Rakyat</div>
    </div>
    <?php if ($login_error): ?>
        <div class="alert-err"><?= e($login_error) ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['login_required'])): ?>
        <div class="alert-err">Please log in to continue.</div>
    <?php endif; ?>
    <form method="post" autocomplete="on">
        <input type="hidden" name="action" value="login">
        <div class="mb-3">
            <label class="form-label">Koperasi Member ID</label>
            <input type="text" name="koperasi_id" class="form-control"
                placeholder="e.g. KOP-2024-001"
                value="<?= e($_POST['koperasi_id'] ?? '') ?>"
                autocomplete="username" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control"
                placeholder="Enter your password"
                autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn-login">Sign In</button>
    </form>
    <div class="login-footer">
        <a href="member_portal.php">Full desktop view</a>
        &nbsp;·&nbsp;
        <a href="index.php">Back to home</a>
    </div>
</div>
</body>
</html>
<?php
    exit;
endif;

// ── Logged in: gather data ───────────────────────────────────
$member      = current_member();
$kop_id      = $member['koperasi_id'];
$mem_name    = $member['name'];
$initial     = mb_strtoupper(mb_substr($mem_name, 0, 1));
$my_listings = get_sellers(['koperasi_id' => $kop_id]);
$my_requests = get_requests(['member_kop_id' => $kop_id]);
$my_convs    = get_conversations_for_member($kop_id);
$active_l    = array_filter($my_listings, fn($s) => $s['status'] === 'active');
$open_r      = array_filter($my_requests, fn($r) => $r['status'] === 'open');
$recent_l    = array_slice($my_listings, 0, 3);
$credits     = get_credits($kop_id);
$ref_code    = ensure_referral_code($kop_id);

// Greeting by time
$hour     = (int) date('G');
$greeting = match(true) {
    $hour < 12  => 'Good morning',
    $hour < 17  => 'Good afternoon',
    default     => 'Good evening',
};

$cat_colors = cat_colors();
$cat_icons  = cat_icons();

// Status pill helper
function status_pill(string $status): string {
    $map = [
        'active'      => ['#d5f5e3', '#1e8449'],
        'inactive'    => ['#fde8e8', '#c0392b'],
        'pending'     => ['#fef9e7', '#d4ac0d'],
        'open'        => ['#d6eaf8', '#1a5276'],
        'in progress' => ['#fef3cd', '#856404'],
        'matched'     => ['#d1f2eb', '#117a65'],
        'closed'      => ['#e8e8e8', '#555'],
    ];
    $c = $map[strtolower($status)] ?? ['#e0e0e0', '#555'];
    return "<span style='background:{$c[0]};color:{$c[1]};font-size:.68rem;font-weight:700;"
         . "padding:2px 8px;border-radius:20px;white-space:nowrap'>" . e($status) . "</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1a5276">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title>Koponix Member</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🤝</text></svg>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ── Reset & root ─────────────────────────────────────────── */
:root {
    --primary:   #1a5276;
    --accent:    #2e86c1;
    --light:     #5dade2;
    --bg:        #f0f4f8;
    --card:      #ffffff;
    --text:      #1c2b3a;
    --muted:     #6b7a8d;
    --border:    #e3eaf2;
    --hdr:       56px;
    --nav:       66px;
}
* { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
body {
    font-family: 'Inter', sans-serif;
    background: var(--bg);
    color: var(--text);
    margin: 0;
    padding-top: var(--hdr);
    padding-bottom: var(--nav);
    min-height: 100vh;
    overscroll-behavior-y: none;
}

/* ── Fixed top header ─────────────────────────────────────── */
#top-hdr {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: var(--hdr);
    background: var(--primary);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    z-index: 1000;
    box-shadow: 0 2px 10px rgba(0,0,0,.22);
}
.hdr-brand { display: flex; align-items: center; gap: 9px; }
.hdr-logo  { font-size: 1.4rem; line-height: 1; }
.hdr-text  {}
.hdr-name  { font-size: .95rem; font-weight: 800; color: #fff; letter-spacing: -.3px; line-height: 1.15; }
.hdr-sub   { font-size: .6rem; color: rgba(255,255,255,.65); line-height: 1; }
.hdr-right { display: flex; align-items: center; gap: 8px; }
.hdr-mname {
    font-size: .76rem; font-weight: 600; color: #fff;
    max-width: 110px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.hdr-avatar {
    width: 34px; height: 34px;
    border-radius: 50%;
    background: var(--accent);
    border: 2px solid rgba(255,255,255,.45);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 800; font-size: .85rem;
    overflow: hidden; flex-shrink: 0;
}
.hdr-avatar img { width: 100%; height: 100%; object-fit: cover; }

/* ── Fixed bottom tab nav ─────────────────────────────────── */
#bot-nav {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    height: var(--nav);
    background: var(--card);
    border-top: 1px solid var(--border);
    display: flex;
    z-index: 1000;
    box-shadow: 0 -2px 12px rgba(0,0,0,.08);
    padding-bottom: env(safe-area-inset-bottom, 0px);
}
.tbn {
    flex: 1;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 3px;
    background: none; border: none; cursor: pointer;
    color: var(--muted); padding: 6px 0;
    transition: color .15s;
    font-family: 'Inter', sans-serif;
}
.tbn .ti { font-size: 1.22rem; line-height: 1; }
.tbn .tl { font-size: .58rem; font-weight: 600; letter-spacing: .2px; }
.tbn.active { color: var(--accent); }
.tbn.active .tl { font-weight: 800; }

/* ── Tab sections ─────────────────────────────────────────── */
.tsec { display: none; padding: 14px 14px 4px; }
.tsec.active { display: block; }

/* ── Flash ────────────────────────────────────────────────── */
.flash-bar {
    margin: 0 14px 12px;
    padding: 10px 14px;
    border-radius: 10px;
    font-size: .83rem; font-weight: 600;
}
.flash-success { background: #d5f5e3; color: #1e8449; }
.flash-danger   { background: #fde8e8; color: #c0392b; }
.flash-info     { background: #d6eaf8; color: #1a5276; }

/* ── Welcome banner ───────────────────────────────────────── */
.welcome {
    background: linear-gradient(135deg, var(--primary), var(--accent));
    border-radius: 16px; padding: 18px 18px 16px;
    color: #fff; margin-bottom: 14px;
}
.welcome .wg { font-size: .72rem; opacity: .8; margin-bottom: 3px; }
.welcome .wn { font-size: 1.15rem; font-weight: 800; line-height: 1.2; }
.welcome .wd { font-size: .7rem; opacity: .65; margin-top: 4px; }

/* ── Stat grid ────────────────────────────────────────────── */
.stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px; }
.sbox {
    background: var(--card); border-radius: 14px;
    padding: 14px 10px; text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}
.sbox .sv { font-size: 1.8rem; font-weight: 800; color: var(--primary); line-height: 1; }
.sbox .sl { font-size: .68rem; color: var(--muted); margin-top: 4px; font-weight: 600; }

/* ── Section label ────────────────────────────────────────── */
.sec-lbl {
    font-size: .7rem; font-weight: 700; color: var(--muted);
    text-transform: uppercase; letter-spacing: .5px;
    margin-bottom: 10px; padding-left: 2px;
}

/* ── Quick actions 2x2 ────────────────────────────────────── */
.qa-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 14px; }
.qa-btn {
    background: var(--card); border-radius: 12px; padding: 12px 4px;
    display: flex; flex-direction: column; align-items: center; gap: 5px;
    text-decoration: none; color: var(--text);
    box-shadow: 0 2px 6px rgba(0,0,0,.06);
    font-size: .62rem; font-weight: 700; text-align: center;
    transition: transform .1s;
}
.qa-btn:active { transform: scale(.95); }
.qa-btn .qi { font-size: 1.28rem; }

/* ── Cards ────────────────────────────────────────────────── */
.mcard {
    background: var(--card); border-radius: 14px;
    padding: 13px 13px; margin-bottom: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    overflow: hidden;
}
.mcard.cat-bdr { border-left: 4px solid #ccc; }

/* ── Listing card ─────────────────────────────────────────── */
.lrow { display: flex; gap: 11px; align-items: flex-start; }
.lthumb {
    width: 60px; height: 60px;
    border-radius: 10px; object-fit: cover; flex-shrink: 0;
}
.lthumb-ph {
    width: 60px; height: 60px; border-radius: 10px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
}
.linfo { flex: 1; min-width: 0; }
.ltitle { font-size: .88rem; font-weight: 700; margin: 3px 0 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lmeta  { font-size: .72rem; color: var(--muted); margin-bottom: 5px; }
.ledit  {
    display: inline-block; font-size: .7rem; font-weight: 700;
    color: var(--accent); text-decoration: none;
    border: 1px solid var(--accent); border-radius: 20px; padding: 2px 10px;
}

/* ── Request card ─────────────────────────────────────────── */
.rdesc { font-size: .8rem; color: #444; margin: 6px 0 4px; line-height: 1.45;
         overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.rmeta { font-size: .72rem; color: var(--muted); }

/* ── Conversations ────────────────────────────────────────── */
.conv-list { background: var(--card); border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,.06); overflow: hidden; }
.conv-row {
    display: flex; align-items: center; gap: 12px;
    padding: 13px 14px;
    border-bottom: 1px solid var(--border);
    text-decoration: none; color: var(--text);
    transition: background .1s;
}
.conv-row:last-child { border-bottom: none; }
.conv-row:active { background: #f5f8fa; }
.cav {
    width: 42px; height: 42px; border-radius: 50%;
    background: var(--accent); color: #fff;
    font-weight: 800; font-size: .95rem;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.cinfo { flex: 1; min-width: 0; }
.cname { font-size: .87rem; font-weight: 700; }
.csub  { font-size: .73rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cdate { font-size: .67rem; color: var(--muted); white-space: nowrap; }

/* ── Profile ──────────────────────────────────────────────── */
.prof-top { text-align: center; padding: 22px 0 14px; }
.prof-av {
    width: 80px; height: 80px; border-radius: 50%;
    background: var(--primary); border: 3px solid var(--accent);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 800; font-size: 2rem;
    margin: 0 auto 10px; overflow: hidden;
}
.prof-av img { width: 100%; height: 100%; object-fit: cover; }
.prof-name { font-size: 1.12rem; font-weight: 800; }
.prof-sub  { font-size: .76rem; color: var(--muted); margin-top: 2px; }
.prof-btns { display: flex; flex-direction: column; gap: 10px; margin-top: 18px; }
.pb {
    display: block; width: 100%; padding: 13px 16px;
    border-radius: 12px; border: none; cursor: pointer;
    font-family: 'Inter', sans-serif;
    font-size: .9rem; font-weight: 700;
    text-decoration: none; text-align: center;
    transition: opacity .15s;
}
.pb:hover { opacity: .87; }
.pb-blue    { background: var(--primary); color: #fff; }
.pb-outline { background: var(--card); color: var(--primary); border: 2px solid var(--primary); }
.pb-red     { background: #c0392b; color: #fff; }
.pb-grey    { background: #e5eaf0; color: #555; font-size: .8rem; padding: 10px; margin-top: 2px; }

/* ── Add button ───────────────────────────────────────────── */
.add-btn {
    display: block; width: 100%; padding: 13px;
    border-radius: 12px; background: var(--primary); color: #fff;
    font-size: .9rem; font-weight: 700; text-align: center;
    text-decoration: none; margin-top: 14px;
    box-shadow: 0 4px 12px rgba(26,82,118,.22);
}
.add-btn:hover { background: var(--accent); color: #fff; }

/* ── Empty state ──────────────────────────────────────────── */
.empty { text-align: center; padding: 38px 20px; color: var(--muted); }
.empty .ei { font-size: 2.8rem; margin-bottom: 10px; }
.empty .et { font-size: .95rem; font-weight: 700; color: var(--text); margin-bottom: 6px; }
.empty .es { font-size: .8rem; }

/* ── Cat badge ────────────────────────────────────────────── */
.cat-badge {
    display: inline-block; padding: 2px 10px;
    border-radius: 30px; font-size: .67rem; font-weight: 700; color: #fff;
    margin-bottom: 3px;
}
</style>
</head>
<body>

<!-- ── Fixed Top Header ──────────────────────────────────────── -->
<header id="top-hdr">
    <div class="hdr-brand">
        <span class="hdr-logo">🤝</span>
        <div class="hdr-text">
            <div class="hdr-name">Koponix</div>
            <div class="hdr-sub">Koperasi Kakitangan Bank Rakyat</div>
        </div>
    </div>
    <div class="hdr-right">
        <span class="hdr-mname"><?= e($mem_name) ?></span>
        <div class="hdr-avatar">
            <?php if (!empty($member['avatar'])): ?>
                <img src="<?= e(img_url($member['avatar'])) ?>" alt="<?= e($initial) ?>">
            <?php else: ?>
                <?= e($initial) ?>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- ── Main Content ──────────────────────────────────────────── -->
<main id="main">

    <!-- Flash -->
    <?php if ($flash): ?>
        <div class="flash-bar flash-<?= e($flash['type'] ?? 'info') ?>">
            <?= e($flash['msg']) ?>
        </div>
    <?php endif; ?>

    <!-- ========== HOME ========================================= -->
    <section id="sec-home" class="tsec active">

        <div class="welcome">
            <div class="wg">Member Dashboard</div>
            <div class="wn"><?= e($greeting) ?>, <?= e(explode(' ', $mem_name)[0]) ?>! 👋</div>
            <div class="wd"><?= date('l, j F Y') ?></div>
        </div>

        <div class="sec-lbl">Overview</div>
        <div class="stat-grid">
            <div class="sbox"><div class="sv"><?= count($my_listings) ?></div><div class="sl">My Listings</div></div>
            <div class="sbox"><div class="sv"><?= count($active_l) ?></div><div class="sl">Active</div></div>
            <div class="sbox"><div class="sv"><?= count($my_requests) ?></div><div class="sl">My Requests</div></div>
            <div class="sbox"><div class="sv"><?= count($open_r) ?></div><div class="sl">Open</div></div>
        </div>
        <!-- Credits banner -->
        <a href="member_portal.php?tab=credits" style="display:flex;align-items:center;gap:12px;
            background:linear-gradient(135deg,#f6d365,#fda085);border-radius:14px;
            padding:12px 16px;margin-bottom:14px;text-decoration:none">
            <span style="font-size:1.8rem">💰</span>
            <div>
                <div style="font-weight:800;font-size:1.1rem;color:#7d3200"><?= $credits ?> Credits</div>
                <div style="font-size:.72rem;color:rgba(125,50,0,.8)">
                    ≈ RM <?= number_format($credits, 2) ?> &nbsp;·&nbsp; Referral code: <strong><?= e($ref_code) ?></strong>
                </div>
            </div>
            <span style="margin-left:auto;color:rgba(125,50,0,.6);font-size:1.1rem">›</span>
        </a>

        <div class="sec-lbl">Quick Actions</div>
        <div class="qa-grid">
            <a href="find_services.php"    class="qa-btn"><span class="qi">🔍</span>Browse<br>Services</a>
            <a href="request_service.php"  class="qa-btn"><span class="qi">🛒</span>Post<br>Request</a>
            <a href="register_service.php" class="qa-btn"><span class="qi">➕</span>New<br>Listing</a>
            <a href="messages.php"         class="qa-btn"><span class="qi">💬</span>Messages</a>
        </div>

        <div class="sec-lbl">Recent Listings</div>
        <?php if (!$recent_l): ?>
            <div class="mcard" style="text-align:center;color:var(--muted);font-size:.84rem;padding:20px">
                No listings yet. <a href="register_service.php" style="color:var(--accent)">Add one →</a>
            </div>
        <?php else: ?>
            <?php foreach ($recent_l as $s):
                $cc = $cat_colors[$s['category']] ?? '#607d8b'; ?>
            <div class="mcard cat-bdr" style="border-left-color:<?= e($cc) ?>">
                <div class="lrow">
                    <?php if (!empty($s['image'])): ?>
                        <img class="lthumb" src="<?= e(img_url($s['image'])) ?>" alt="">
                    <?php else: ?>
                        <div class="lthumb-ph" style="background:<?= e($cc) ?>22"><?= $cat_icons[$s['category']] ?? '⭐' ?></div>
                    <?php endif; ?>
                    <div class="linfo">
                        <div><?= cat_badge($s['category']) ?> <?= status_pill($s['status']) ?></div>
                        <div class="ltitle"><?= e($s['service_title']) ?></div>
                        <div class="lmeta">📍 <?= e($s['area']) ?> &nbsp;·&nbsp; 💰 <?= e($s['price_range']) ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </section>

    <!-- ========== LISTINGS ===================================== -->
    <section id="sec-listings" class="tsec">

        <div class="sec-lbl">My Listings (<?= count($my_listings) ?>)</div>

        <?php if (!$my_listings): ?>
            <div class="empty">
                <div class="ei">📋</div>
                <div class="et">No listings yet</div>
                <div class="es">Add your first service listing to start getting found.</div>
            </div>
        <?php else: ?>
            <?php foreach ($my_listings as $s):
                $cc = $cat_colors[$s['category']] ?? '#607d8b'; ?>
            <div class="mcard cat-bdr" style="border-left-color:<?= e($cc) ?>">
                <div class="lrow">
                    <?php if (!empty($s['image'])): ?>
                        <img class="lthumb" src="<?= e(img_url($s['image'])) ?>" alt="">
                    <?php else: ?>
                        <div class="lthumb-ph" style="background:<?= e($cc) ?>22"><?= $cat_icons[$s['category']] ?? '⭐' ?></div>
                    <?php endif; ?>
                    <div class="linfo">
                        <div><?= cat_badge($s['category']) ?> <?= status_pill($s['status']) ?></div>
                        <div class="ltitle"><?= e($s['service_title']) ?></div>
                        <div class="lmeta">📍 <?= e($s['area']) ?> &nbsp;·&nbsp; 💰 <?= e($s['price_range']) ?></div>
                        <a class="ledit" href="edit_listing.php?id=<?= urlencode($s['id']) ?>">✏️ Edit</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <a href="register_service.php" class="add-btn">+ Add New Listing</a>

    </section>

    <!-- ========== REQUESTS ===================================== -->
    <section id="sec-requests" class="tsec">

        <div class="sec-lbl">My Requests (<?= count($my_requests) ?>)</div>

        <?php if (!$my_requests): ?>
            <div class="empty">
                <div class="ei">🛒</div>
                <div class="et">No requests yet</div>
                <div class="es">Post a service request to find the right provider.</div>
            </div>
        <?php else: ?>
            <?php foreach ($my_requests as $r): ?>
            <div class="mcard">
                <div><?= cat_badge($r['category']) ?> <?= status_pill($r['status']) ?></div>
                <div class="rdesc"><?= e($r['service_description']) ?></div>
                <div class="rmeta">
                    📍 <?= e($r['location']) ?>
                    &nbsp;·&nbsp; 💰 <?= e($r['budget'] ?: '—') ?>
                    &nbsp;·&nbsp; <?= e($r['submitted_date']) ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <a href="request_service.php" class="add-btn">+ New Request</a>

    </section>

    <!-- ========== MESSAGES ===================================== -->
    <section id="sec-messages" class="tsec">

        <div class="sec-lbl">Conversations (<?= count($my_convs) ?>)</div>

        <?php if (!$my_convs): ?>
            <div class="empty">
                <div class="ei">💬</div>
                <div class="et">No messages yet</div>
                <div class="es">Go to <a href="find_services.php" style="color:var(--accent)">Browse Services</a> and tap <strong>Message</strong> on any listing.</div>
            </div>
        <?php else: ?>
            <div class="conv-list">
                <?php foreach ($my_convs as $conv):
                    $other = ($conv['buyer_kop_id'] === $kop_id) ? $conv['seller_name'] : $conv['buyer_name'];
                    $oi    = mb_strtoupper(mb_substr($other, 0, 1));
                ?>
                <a class="conv-row" href="messages.php?conv=<?= urlencode($conv['id']) ?>">
                    <div class="cav"><?= e($oi) ?></div>
                    <div class="cinfo">
                        <div class="cname"><?= e($other) ?></div>
                        <div class="csub"><?= e($conv['subject']) ?></div>
                    </div>
                    <div class="cdate"><?= e($conv['created_date']) ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </section>

    <!-- ========== PROFILE ====================================== -->
    <section id="sec-profile" class="tsec">

        <div class="prof-top">
            <div class="prof-av">
                <?php if (!empty($member['avatar'])): ?>
                    <img src="<?= e(img_url($member['avatar'])) ?>" alt="<?= e($initial) ?>">
                <?php else: ?>
                    <?= e($initial) ?>
                <?php endif; ?>
            </div>
            <div class="prof-name"><?= e($mem_name) ?></div>
            <div class="prof-sub"><?= e($kop_id) ?></div>
            <?php if (!empty($member['email'])): ?>
                <div class="prof-sub"><?= e($member['email']) ?></div>
            <?php endif; ?>
            <div class="prof-sub">Joined <?= e($member['joined_date'] ?? '—') ?></div>
        </div>

        <div class="prof-btns">
            <a href="member_portal.php?tab=profile" class="pb pb-blue">✏️ Edit Profile</a>
            <a href="member_portal.php?tab=profile" class="pb pb-outline">🔒 Change Password</a>
            <a href="logout.php" class="pb pb-red">Sign Out</a>
            <a href="index.php"  class="pb pb-grey">🖥 Desktop View</a>
        </div>

    </section>

</main>

<!-- ── Fixed Bottom Tab Nav ──────────────────────────────────── -->
<nav id="bot-nav">
    <button class="tbn active" data-sec="home">
        <span class="ti">🏠</span><span class="tl">Home</span>
    </button>
    <button class="tbn" data-sec="listings">
        <span class="ti">📋</span><span class="tl">Listings</span>
    </button>
    <button class="tbn" data-sec="requests">
        <span class="ti">🛒</span><span class="tl">Requests</span>
    </button>
    <button class="tbn" data-sec="messages">
        <span class="ti">💬</span><span class="tl">Messages</span>
    </button>
    <button class="tbn" data-sec="profile">
        <span class="ti">👤</span><span class="tl">Profile</span>
    </button>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var btns  = document.querySelectorAll('.tbn');
    var secs  = document.querySelectorAll('.tsec');
    var valid = ['home','listings','requests','messages','profile'];

    function show(target) {
        btns.forEach(function(b){ b.classList.toggle('active', b.dataset.sec === target); });
        secs.forEach(function(s){ s.classList.toggle('active', s.id === 'sec-' + target); });
        window.scrollTo({ top: 0, behavior: 'auto' });
        history.replaceState(null, '', '#' + target);
    }

    btns.forEach(function(b){
        b.addEventListener('click', function(){ show(b.dataset.sec); });
    });

    // Deep-link via URL hash
    var hash = window.location.hash.replace('#', '');
    if (hash && valid.indexOf(hash) !== -1) show(hash);
})();
</script>
</body>
</html>
