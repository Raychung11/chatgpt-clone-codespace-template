<?php
// ============================================================
//  KOPONIX – Shared Layout
//  Top header + role-based nav + footer + mobile bottom nav
//  Roles: public | member | provider | admin
// ============================================================
require_once __DIR__ . '/functions.php';
session_start_safe();

$flash  = get_flash();
$member = current_member();
$page   = basename($_SERVER['PHP_SELF']);
$role   = view_role(); // public | member | provider | admin

// ── Avatar initials helper ───────────────────────────────────
function avatar_initials(string $name): string {
    $parts = explode(' ', trim($name));
    $init  = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) $init .= strtoupper(substr(end($parts), 0, 1));
    return $init;
}

// ── Active nav link helper ───────────────────────────────────
function nav_active(string $file): string {
    global $page;
    return $page === $file ? 'active fw-semibold' : '';
}

function html_head(string $title = 'Koponix'): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — Koponix | <?= defined('KOPERASI_ABBREV') ? KOPERASI_ABBREV : 'KKBR' ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🤝</text></svg>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ── Base ─────────────────────────────────────────────── */
:root{--primary:#1a5276;--accent:#2e86c1;--light-bg:#f0f4f8;}
*{box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--light-bg);min-height:100vh;
    padding-top:64px; /* offset for fixed topnav */}

/* ── Top Navigation Bar ───────────────────────────────── */
#topnav{position:fixed;top:0;left:0;right:0;height:64px;z-index:1050;
    background:#fff;border-bottom:1px solid #e2e8f0;
    box-shadow:0 2px 8px rgba(0,0,0,.06);}
#topnav .brand{font-size:1.25rem;font-weight:800;color:var(--primary);
    text-decoration:none;letter-spacing:-.5px;display:flex;align-items:center;gap:.4rem;}
#topnav .brand span{color:#2e86c1;}
#topnav .brand-sub{font-size:.6rem;color:#888;font-weight:500;line-height:1.2;
    max-width:130px;display:block;}
#topnav .nav-link{color:#555;font-size:.85rem;font-weight:500;padding:.4rem .7rem;
    border-radius:6px;transition:all .15s;}
#topnav .nav-link:hover,#topnav .nav-link.active{color:var(--primary);background:#eef4fb;}
#topnav .nav-link.active{font-weight:600;}

/* Role colour chips on nav links */
.role-badge{font-size:.6rem;padding:1px 5px;border-radius:4px;vertical-align:middle;
    margin-left:3px;font-weight:700;}
.role-badge.admin{background:#fde8e8;color:#c0392b;}
.role-badge.provider{background:#d5f5e3;color:#1e8449;}

/* Avatar circle */
.av-circle{width:36px;height:36px;border-radius:50%;background:var(--primary);
    color:#fff;font-size:.75rem;font-weight:700;display:flex;align-items:center;
    justify-content:center;overflow:hidden;flex-shrink:0;}
.av-circle img{width:100%;height:100%;object-fit:cover;}

/* Dropdown */
#topnav .dropdown-menu{border:none;box-shadow:0 8px 24px rgba(0,0,0,.12);
    border-radius:10px;min-width:200px;padding:.5rem;}
#topnav .dropdown-item{border-radius:6px;font-size:.85rem;padding:.45rem .85rem;}
#topnav .dropdown-divider{margin:.3rem 0;}
.dropdown-section-label{font-size:.65rem;text-transform:uppercase;letter-spacing:.06em;
    color:#aaa;padding:.3rem .85rem .1rem;font-weight:600;}

/* ── Main Content ─────────────────────────────────────── */
#content{max-width:1200px;margin:0 auto;padding:1.8rem 1.5rem 2rem;}
.page-title{font-size:1.5rem;font-weight:700;color:var(--primary);margin-bottom:1rem;}

/* ── Cards & Common Components ────────────────────────── */
.card{border:none;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.07);}
.card-header{border-radius:12px 12px 0 0!important;border-bottom:none;}
.btn-primary{background:var(--primary);border-color:var(--primary);}
.btn-primary:hover{background:var(--accent);border-color:var(--accent);}
.cat-badge{display:inline-block;padding:2px 10px;border-radius:30px;font-size:.72rem;
    font-weight:600;color:#fff;margin-bottom:.4rem;}
.seller-card{background:#fff;border-radius:12px;padding:1rem 1.1rem;
    box-shadow:0 2px 8px rgba(0,0,0,.07);margin-bottom:1rem;}
.seller-card h5{font-size:.95rem;font-weight:700;color:#1a3a52;margin:.4rem 0 .25rem;}
.seller-card .meta{font-size:.78rem;color:#555;margin-bottom:.15rem;}
.seller-card .desc{font-size:.8rem;color:#666;margin-top:.4rem;
    border-top:1px solid #eee;padding-top:.4rem;}
.stat-box{background:#fff;border-radius:10px;padding:1rem;text-align:center;
    box-shadow:0 1px 4px rgba(0,0,0,.06);}
.stat-box .val{font-size:1.6rem;font-weight:800;color:var(--primary);}
.stat-box .lbl{font-size:.75rem;color:#777;margin-top:.1rem;}
.hero{background:linear-gradient(135deg,var(--primary),var(--accent));
    border-radius:14px;color:#fff;padding:2rem 1.5rem;margin-bottom:1.5rem;}
.hero h2{font-weight:800;font-size:1.6rem;}
.section-head{font-size:1.1rem;font-weight:700;color:var(--primary);
    border-left:4px solid var(--accent);padding-left:.75rem;margin:.5rem 0 1rem;}

/* ── Status Pills ─────────────────────────────────────── */
.pill-active{background:#d5f5e3;color:#1e8449;font-size:.7rem;padding:2px 8px;border-radius:20px;font-weight:600;}
.pill-inactive{background:#fde8e8;color:#c0392b;font-size:.7rem;padding:2px 8px;border-radius:20px;font-weight:600;}
.pill-open{background:#fde8e8;color:#c0392b;font-size:.7rem;padding:2px 8px;border-radius:20px;font-weight:600;}
.pill-matched{background:#d5f5e3;color:#1e8449;font-size:.7rem;padding:2px 8px;border-radius:20px;font-weight:600;}
.pill-closed{background:#eee;color:#777;font-size:.7rem;padding:2px 8px;border-radius:20px;font-weight:600;}

/* ── Chat ─────────────────────────────────────────────── */
.chat-box{height:420px;overflow-y:auto;border:1px solid #dee2e6;border-radius:10px;
    padding:1rem;background:#fafbfc;}
.chat-msg{margin-bottom:.75rem;}
.chat-msg .bubble{display:inline-block;padding:.5rem .85rem;border-radius:14px;
    font-size:.85rem;max-width:80%;}
.chat-msg.mine{text-align:right;}
.chat-msg.mine .bubble{background:var(--primary);color:#fff;border-radius:14px 14px 0 14px;}
.chat-msg.theirs .bubble{background:#fff;color:#333;border:1px solid #e0e0e0;border-radius:14px 14px 14px 0;}
.chat-msg.ai-msg .bubble{background:#eaf4fb;color:#1a5276;border:1px solid #b8d9f0;border-radius:14px 14px 14px 0;}
.chat-ts{font-size:.68rem;color:#aaa;margin-top:2px;}

/* ── Mobile ───────────────────────────────────────────── */
@media(max-width:768px){
    body{padding-top:56px;}
    #topnav{height:56px;}
    #topnav .brand-sub{display:none;}
    #topnav .desktop-nav{display:none!important;}
    #content{padding:1rem .9rem 80px;}
    .page-title{font-size:1.2rem;}
    #mobile-nav{display:flex!important;}
}
@media(min-width:769px){
    #mobile-nav{display:none!important;}
    #topnav .mob-toggle{display:none!important;}
}

/* ── Mobile Bottom Nav ────────────────────────────────── */
#mobile-nav{position:fixed;bottom:0;left:0;right:0;height:62px;background:#fff;
    border-top:1px solid #e2e8f0;z-index:1000;align-items:stretch;
    box-shadow:0 -2px 10px rgba(0,0,0,.08);}
#mobile-nav a{flex:1;display:flex;flex-direction:column;align-items:center;
    justify-content:center;text-decoration:none;color:#888;font-size:.6rem;
    font-weight:600;gap:2px;transition:color .15s;}
#mobile-nav a .mn-icon{font-size:1.3rem;line-height:1;}
#mobile-nav a.active,#mobile-nav a:hover{color:var(--primary);}
#mobile-nav a.active .mn-icon{filter:drop-shadow(0 0 3px rgba(26,82,118,.4));}
</style>
<?php } ?>

<?php function html_body_open(): void {
    global $member, $page, $role, $flash;

    // Provider / admin unread message count (optional, kept simple)
    $avatar_src = '';
    if ($member && !empty($member['avatar'])) {
        $avatar_src = img_url($member['avatar']);
    }
    $initials = $member ? avatar_initials($member['name']) : '';
?>
</head>
<body>

<!-- ════════════════════════════════════════════════════
     TOP NAVIGATION BAR
════════════════════════════════════════════════════ -->
<header id="topnav">
<div class="d-flex align-items-center h-100 px-3 gap-2">

    <!-- Logo -->
    <a href="index.php" class="brand me-3">
        🤝 Kopo<span>nix</span>
        <span class="brand-sub"><?= defined('KOPERASI_NAME') ? e(KOPERASI_NAME) : 'KKBR' ?><br>Digital Marketplace</span>
    </a>

    <!-- ── Desktop Nav Links (role-based) ─────────── -->
    <nav class="desktop-nav d-flex align-items-center gap-1 flex-grow-1">

        <!-- Public: Home always visible -->
        <a href="index.php" class="nav-link <?= nav_active('index.php') ?>">Home</a>
        <a href="find_services.php" class="nav-link <?= nav_active('find_services.php') ?>">Browse Services</a>

        <?php if ($role === 'public'): ?>
            <!-- Public only -->
            <a href="request_service.php" class="nav-link <?= nav_active('request_service.php') ?>">Request Service</a>

        <?php elseif ($role === 'member'): ?>
            <!-- Member: logged in, no listings yet -->
            <a href="request_service.php" class="nav-link <?= nav_active('request_service.php') ?>">Request Service</a>
            <a href="messages.php" class="nav-link <?= nav_active('messages.php') ?>">Messages</a>
            <a href="register_service.php" class="nav-link <?= nav_active('register_service.php') ?>"
                style="color:#1e8449">+ List My Service</a>

        <?php elseif ($role === 'provider'): ?>
            <!-- Provider: listed their service -->
            <a href="request_service.php" class="nav-link <?= nav_active('request_service.php') ?>">Request Service</a>
            <a href="messages.php" class="nav-link <?= nav_active('messages.php') ?>">Messages</a>
            <a href="member_portal.php?tab=listings" class="nav-link <?= nav_active('member_portal.php') ?>">
                My Listings <span class="role-badge provider">PROVIDER</span>
            </a>

        <?php elseif ($role === 'admin'): ?>
            <!-- Admin: all links -->
            <a href="request_service.php" class="nav-link <?= nav_active('request_service.php') ?>">Request Service</a>
            <a href="messages.php" class="nav-link <?= nav_active('messages.php') ?>">Messages</a>
            <a href="member_portal.php?tab=listings" class="nav-link <?= nav_active('member_portal.php') ?>">My Listings</a>
            <a href="admin_dashboard.php" class="nav-link <?= nav_active('admin_dashboard.php') ?>">
                Admin <span class="role-badge admin">ADMIN</span>
            </a>
        <?php endif; ?>

    </nav>

    <!-- ── Right Side: Auth Controls ─────────────── -->
    <div class="ms-auto d-flex align-items-center gap-2">

    <?php if ($role === 'public'): ?>
        <!-- Public: Login + Register -->
        <a href="member_portal.php" class="btn btn-sm btn-outline-primary d-none d-md-inline-flex">Login</a>
        <a href="member_portal.php?tab=register" class="btn btn-sm btn-primary d-none d-md-inline-flex">Join Free</a>
        <!-- Mobile: just Login -->
        <a href="member_portal.php" class="btn btn-sm btn-primary d-md-none">Login</a>

    <?php else: ?>
        <!-- Logged in: Avatar dropdown -->
        <div class="dropdown">
            <button class="btn p-0 border-0 d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <div class="av-circle">
                    <?php if ($avatar_src): ?>
                        <img src="<?= e($avatar_src) ?>" alt="avatar">
                    <?php else: ?>
                        <?= e($initials) ?>
                    <?php endif; ?>
                </div>
                <div class="d-none d-md-block text-start" style="line-height:1.2">
                    <div style="font-size:.8rem;font-weight:600;color:#1a3a52"><?= e($member['name']) ?></div>
                    <div style="font-size:.65rem;color:#888">
                        <?= e($member['koperasi_id']) ?>
                        &nbsp;·&nbsp; 💰 <strong><?= get_credits($member['koperasi_id']) ?></strong> credits
                    </div>
                </div>
                <svg class="d-none d-md-block" width="14" height="14" fill="#aaa" viewBox="0 0 16 16">
                    <path d="M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z"/>
                </svg>
            </button>

            <ul class="dropdown-menu dropdown-menu-end">

                <!-- My Account section -->
                <li><span class="dropdown-section-label">My Account</span></li>
                <li><a class="dropdown-item" href="member_portal.php?tab=profile">👤 My Profile</a></li>
                <li><a class="dropdown-item" href="member_portal.php?tab=listings">📋 My Listings</a></li>
                <li><a class="dropdown-item" href="member_portal.php?tab=requests">🛒 My Requests</a></li>
                <li><a class="dropdown-item" href="messages.php">💬 Messages</a></li>
                <li><a class="dropdown-item" href="member_portal.php?tab=credits">
                    💰 Credits &amp; Referral
                    <span style="float:right;background:#f6d365;color:#7d3200;font-size:.65rem;
                        padding:1px 6px;border-radius:10px;font-weight:700">
                        <?= get_credits($member['koperasi_id']) ?> cr
                    </span>
                </a></li>

                <?php if ($role === 'member'): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="register_service.php" style="color:#1e8449">
                        ✨ Become a Provider</a></li>
                <?php endif; ?>

                <?php if ($role === 'provider' || $role === 'admin'): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><span class="dropdown-section-label">Provider Tools</span></li>
                    <li><a class="dropdown-item" href="register_service.php">➕ Add New Listing</a></li>
                    <li><a class="dropdown-item" href="promo_generator.php">📣 Promo Generator</a></li>
                    <li><a class="dropdown-item" href="monthly_report.php">📊 Monthly Report</a></li>
                    <li><a class="dropdown-item" href="ai_assistant.php">🤖 AI Assistant</a></li>
                <?php endif; ?>

                <?php if ($role === 'admin'): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><span class="dropdown-section-label">Admin Tools</span></li>
                    <li><a class="dropdown-item" href="admin_dashboard.php">🛡️ Admin Dashboard</a></li>
                    <li><a class="dropdown-item" href="match_engine.php">🎯 Match Engine</a></li>
                <?php endif; ?>

                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="logout.php">⬅️ Logout</a></li>
            </ul>
        </div><!-- /dropdown -->
    <?php endif; ?>

    </div><!-- /right side -->
</div>
</header>
<!-- ── End Top Nav ── -->

<!-- Main content wrapper -->
<main id="content">
<?php
    if ($flash):
        $ftype = $flash['type'] === 'error' ? 'danger' : ($flash['type'] ?: 'info');
?>
<div class="alert alert-<?= e($ftype) ?> alert-dismissible fade show" role="alert">
    <?= e($flash['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php } // end html_body_open ?>


<?php function html_footer(): void {
    global $member, $page, $role;
    $account_href  = $member ? 'member_mobile.php'  : 'member_portal.php';
    $account_label = $member ? 'Account'             : 'Login';
    $account_icon  = $member ? '👤'                  : '🔑';
?>
</main>

<!-- ════════════════════════════════════════════════════
     MOBILE BOTTOM NAVIGATION (≤768px only)
════════════════════════════════════════════════════ -->
<nav id="mobile-nav">
    <a href="index.php" class="<?= $page==='index.php'?'active':'' ?>">
        <span class="mn-icon">🏠</span>Home
    </a>
    <a href="find_services.php" class="<?= $page==='find_services.php'?'active':'' ?>">
        <span class="mn-icon">🔍</span>Browse
    </a>
    <a href="request_service.php" class="<?= $page==='request_service.php'?'active':'' ?>">
        <span class="mn-icon">🛒</span>Request
    </a>
    <?php if ($member): ?>
    <a href="messages.php" class="<?= $page==='messages.php'?'active':'' ?>">
        <span class="mn-icon">💬</span>Messages
    </a>
    <?php else: ?>
    <a href="find_services.php">
        <span class="mn-icon">🔍</span>Services
    </a>
    <?php endif; ?>
    <a href="<?= $account_href ?>"
        class="<?= in_array($page,['member_mobile.php','member_portal.php'])?'active':'' ?>">
        <span class="mn-icon"><?= $account_icon ?></span><?= $account_label ?>
    </a>
</nav>

<!-- ════════════════════════════════════════════════════
     SITE FOOTER
════════════════════════════════════════════════════ -->
<footer style="background:#1a3a52;color:rgba(255,255,255,.75);font-size:.8rem;padding:2rem 1.5rem 1.5rem;margin-top:2rem;">
<div style="max-width:1000px;margin:auto;">
    <div style="display:flex;flex-wrap:wrap;gap:2rem;justify-content:space-between;margin-bottom:1.5rem;">

        <!-- Brand column -->
        <div style="min-width:200px">
            <div style="font-size:1.1rem;font-weight:800;color:#fff;margin-bottom:.4rem;">
                🤝 Koponix
            </div>
            <div style="color:rgba(255,255,255,.6);font-size:.78rem;line-height:1.6">
                <?= defined('KOPERASI_NAME')    ? e(KOPERASI_NAME)    : '' ?><br>
                <?= defined('KOPERASI_TAGLINE') ? e(KOPERASI_TAGLINE) : '' ?><br>
                <?= defined('KOPERASI_SKM_NO')  ? 'No. SKM: ' . e(KOPERASI_SKM_NO) : '' ?>
            </div>
        </div>

        <!-- Quick links: Public -->
        <div style="min-width:140px">
            <div style="font-weight:700;color:#fff;margin-bottom:.6rem;font-size:.8rem;">Platform</div>
            <div style="display:flex;flex-direction:column;gap:.3rem">
                <a href="index.php" style="color:rgba(255,255,255,.65);text-decoration:none">Home</a>
                <a href="find_services.php" style="color:rgba(255,255,255,.65);text-decoration:none">Browse Services</a>
                <a href="request_service.php" style="color:rgba(255,255,255,.65);text-decoration:none">Request Service</a>
                <a href="register_service.php" style="color:rgba(255,255,255,.65);text-decoration:none">Become a Provider</a>
            </div>
        </div>

        <!-- Quick links: Members -->
        <div style="min-width:140px">
            <div style="font-weight:700;color:#fff;margin-bottom:.6rem;font-size:.8rem;">Members</div>
            <div style="display:flex;flex-direction:column;gap:.3rem">
                <a href="member_portal.php" style="color:rgba(255,255,255,.65);text-decoration:none">Member Login</a>
                <a href="member_portal.php?tab=register" style="color:rgba(255,255,255,.65);text-decoration:none">Register</a>
                <a href="messages.php" style="color:rgba(255,255,255,.65);text-decoration:none">Messages</a>
                <a href="member_portal.php?tab=profile" style="color:rgba(255,255,255,.65);text-decoration:none">My Account</a>
            </div>
        </div>

        <!-- Contact column -->
        <div style="min-width:180px">
            <div style="font-weight:700;color:#fff;margin-bottom:.6rem;font-size:.8rem;">Hubungi Kami</div>
            <?php if (defined('KOPERASI_ADDRESS') && KOPERASI_ADDRESS): ?>
                <div style="margin-bottom:.3rem">📍 <?= e(KOPERASI_ADDRESS) ?></div>
            <?php endif; ?>
            <?php if (defined('KOPERASI_PHONE') && KOPERASI_PHONE): ?>
                <div style="margin-bottom:.3rem">📞 <?= e(KOPERASI_PHONE) ?></div>
            <?php endif; ?>
            <?php if (defined('KOPERASI_EMAIL') && KOPERASI_EMAIL): ?>
                <div style="margin-bottom:.3rem">✉️ <a href="mailto:<?= e(KOPERASI_EMAIL) ?>"
                    style="color:#7fc3f5"><?= e(KOPERASI_EMAIL) ?></a></div>
            <?php endif; ?>
            <?php if (defined('KOPERASI_FB') && KOPERASI_FB): ?>
                <div><a href="<?= e(KOPERASI_FB) ?>" target="_blank" style="color:#7fc3f5">Facebook</a></div>
            <?php endif; ?>
            <?php if (defined('KOPERASI_IG') && KOPERASI_IG): ?>
                <div><a href="<?= e(KOPERASI_IG) ?>" target="_blank" style="color:#7fc3f5">Instagram</a></div>
            <?php endif; ?>
        </div>

    </div><!-- /flex row -->

    <!-- Bottom bar -->
    <div style="border-top:1px solid rgba(255,255,255,.12);padding-top:1rem;
        display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:.5rem;">
        <div style="color:rgba(255,255,255,.4);font-size:.72rem;">
            &copy; <?= date('Y') ?> Koponix &mdash; <?= defined('KOPERASI_NAME') ? e(KOPERASI_NAME) : '' ?>. Hak cipta terpelihara.
        </div>
        <div style="font-size:.72rem;color:rgba(255,255,255,.35);">
            Powered by <strong style="color:rgba(255,255,255,.5)">Koponix Platform</strong>
        </div>
    </div>

</div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php } // end html_footer ?>
