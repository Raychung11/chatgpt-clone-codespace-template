<?php
// ============================================================
//  KOPONIX – Mobile Member Dashboard (standalone, no layout.php)
// ============================================================
require_once __DIR__ . '/functions.php';
session_start_safe();

$login_error = '';

// Handle login form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $kop_id = trim($_POST['koperasi_id'] ?? '');
    $pw     = $_POST['password'] ?? '';
    if ($kop_id && $pw) {
        $m = authenticate_member($kop_id, $pw);
        if ($m) { login_member($m); header('Location: member_mobile.php'); exit; }
        else $login_error = 'Invalid Member ID or password.';
    } else {
        $login_error = 'Please enter your Member ID and password.';
    }
}

// Flash
$flash = get_flash();

$member = current_member();
$hour   = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$today  = date('D, d M Y');

$kop_id      = $member['koperasi_id'] ?? '';
$my_listings = $member ? get_sellers(['koperasi_id' => $kop_id]) : [];
$my_requests = $member ? get_requests(['member_kop_id' => $kop_id]) : [];
$my_convs    = $member ? get_conversations_for_member($kop_id) : [];
$active_l    = array_filter($my_listings, fn($s) => $s['status'] === 'active');
$open_r      = array_filter($my_requests, fn($r) => $r['status'] === 'open');
$icons       = cat_icons();
$colors      = cat_colors();
$initial     = $member ? mb_strtoupper(mb_substr($member['name'], 0, 1)) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<meta name="theme-color" content="#1a5276">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title>My Account – Koponix</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🤝</text></svg>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--primary:#1a5276;--accent:#2e86c1;--light-bg:#f0f4f8;}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
body{font-family:'Inter',sans-serif;background:var(--light-bg);margin:0;padding:0;
     padding-top:56px;padding-bottom:66px;min-height:100vh;overflow-x:hidden;}

/* Top header */
#mob-header{position:fixed;top:0;left:0;right:0;height:56px;background:var(--primary);
            color:#fff;display:flex;align-items:center;justify-content:space-between;
            padding:0 1rem;z-index:999;box-shadow:0 2px 8px rgba(0,0,0,.2);}
#mob-header .brand{display:flex;flex-direction:column;line-height:1.2;}
#mob-header .brand .name{font-size:1.05rem;font-weight:800;letter-spacing:-.3px;}
#mob-header .brand .name span{color:#5dade2;}
#mob-header .brand .sub{font-size:.58rem;opacity:.65;}
#mob-header .avatar{width:36px;height:36px;border-radius:50%;background:#2e86c1;
                    display:flex;align-items:center;justify-content:center;
                    font-weight:800;font-size:.95rem;color:#fff;overflow:hidden;
                    border:2px solid rgba(255,255,255,.35);flex-shrink:0;}
#mob-header .avatar img{width:100%;height:100%;object-fit:cover;}
#mob-header .member-info{text-align:right;}
#mob-header .member-info .mname{font-size:.78rem;font-weight:700;max-width:120px;
                                 overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
#mob-header .member-info .mkop{font-size:.62rem;opacity:.65;}

/* Bottom nav */
#mob-nav{position:fixed;bottom:0;left:0;right:0;height:62px;background:#fff;
         border-top:1px solid #dde;display:flex;z-index:999;
         box-shadow:0 -3px 12px rgba(0,0,0,.08);}
#mob-nav a{flex:1;display:flex;flex-direction:column;align-items:center;
           justify-content:center;text-decoration:none;color:#9b9b9b;
           font-size:.6rem;font-weight:600;gap:1px;transition:color .15s;padding:.3rem 0;}
#mob-nav a .ni{font-size:1.25rem;line-height:1.1;}
#mob-nav a.active{color:var(--primary);}
#mob-nav a.active .ni{filter:drop-shadow(0 1px 3px rgba(26,82,118,.35));}

/* Sections */
.mob-section{display:none;padding:1rem;}
.mob-section.active{display:block;}

/* Cards */
.mob-card{background:#fff;border-radius:14px;box-shadow:0 2px 8px rgba(0,0,0,.07);
          margin-bottom:.85rem;overflow:hidden;}
.mob-card .mc-body{padding:.85rem 1rem;}
.mob-card .mc-head{font-size:.9rem;font-weight:700;color:#1a3a52;margin-bottom:.2rem;}
.mob-card .mc-meta{font-size:.75rem;color:#777;line-height:1.6;}
.mob-card .mc-footer{padding:.6rem 1rem;border-top:1px solid #f0f0f0;
                     display:flex;gap:.5rem;flex-wrap:wrap;}

/* Stat grid */
.stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:.7rem;margin-bottom:1rem;}
.stat-cell{background:#fff;border-radius:12px;padding:.85rem .7rem;text-align:center;
           box-shadow:0 1px 5px rgba(0,0,0,.06);}
.stat-cell .sv{font-size:1.7rem;font-weight:800;color:var(--primary);}
.stat-cell .sl{font-size:.7rem;color:#888;margin-top:.1rem;}

/* Quick actions */
.qa-grid{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-bottom:1.2rem;}
.qa-btn{background:#fff;border-radius:12px;padding:.75rem .5rem;text-align:center;
        box-shadow:0 1px 5px rgba(0,0,0,.06);text-decoration:none;color:#1a5276;
        font-size:.75rem;font-weight:600;display:flex;flex-direction:column;
        align-items:center;gap:.3rem;transition:transform .1s;}
.qa-btn:active{transform:scale(.96);}
.qa-btn .qi{font-size:1.5rem;}

/* Category badge */
.cat-badge{display:inline-block;padding:2px 9px;border-radius:20px;font-size:.68rem;
           font-weight:600;color:#fff;}
/* Pills */
.pill-active{background:#d5f5e3;color:#1e8449;font-size:.68rem;padding:2px 8px;border-radius:20px;font-weight:600;}
.pill-inactive{background:#fde8e8;color:#c0392b;font-size:.68rem;padding:2px 8px;border-radius:20px;font-weight:600;}
.pill-open{background:#fde8e8;color:#c0392b;font-size:.68rem;padding:2px 8px;border-radius:20px;font-weight:600;}
.pill-matched{background:#d5f5e3;color:#1e8449;font-size:.68rem;padding:2px 8px;border-radius:20px;font-weight:600;}
.pill-closed{background:#eee;color:#888;font-size:.68rem;padding:2px 8px;border-radius:20px;font-weight:600;}

/* Listing thumb */
.l-thumb{width:56px;height:56px;border-radius:10px;object-fit:cover;flex-shrink:0;}
.l-thumb-placeholder{width:56px;height:56px;border-radius:10px;flex-shrink:0;
                     display:flex;align-items:center;justify-content:center;font-size:1.4rem;}

/* Profile avatar */
.prof-avatar{width:80px;height:80px;border-radius:50%;background:var(--primary);
             display:flex;align-items:center;justify-content:center;
             font-size:2rem;font-weight:800;color:#fff;margin:0 auto .6rem;
             border:3px solid #fff;box-shadow:0 4px 14px rgba(26,82,118,.25);overflow:hidden;}
.prof-avatar img{width:100%;height:100%;object-fit:cover;}

/* Flash */
.mob-flash{margin:0 1rem .8rem;border-radius:10px;padding:.7rem 1rem;font-size:.82rem;}

/* Welcome banner */
.welcome-banner{background:linear-gradient(135deg,var(--primary),var(--accent));
                color:#fff;border-radius:14px;padding:1.1rem 1rem;margin-bottom:1rem;}
.welcome-banner .wg{font-size:.78rem;opacity:.8;margin-bottom:.1rem;}
.welcome-banner .wn{font-size:1.1rem;font-weight:800;}
.welcome-banner .wd{font-size:.72rem;opacity:.65;margin-top:.15rem;}

/* Conversation row */
.conv-row{display:flex;align-items:center;gap:.75rem;padding:.8rem 1rem;
          background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);
          margin-bottom:.7rem;text-decoration:none;color:inherit;transition:transform .1s;}
.conv-row:active{transform:scale(.98);}
.conv-avatar{width:42px;height:42px;border-radius:50%;background:var(--accent);
             display:flex;align-items:center;justify-content:center;
             font-size:1.1rem;font-weight:700;color:#fff;flex-shrink:0;}
.conv-info .ci-name{font-size:.85rem;font-weight:700;color:#1a3a52;}
.conv-info .ci-sub{font-size:.73rem;color:#777;margin-top:1px;
                   overflow:hidden;white-space:nowrap;text-overflow:ellipsis;max-width:200px;}
.conv-date{font-size:.65rem;color:#aaa;margin-left:auto;white-space:nowrap;}

/* Btn small */
.btn-mob{display:inline-block;padding:.45rem .9rem;border-radius:8px;font-size:.78rem;
         font-weight:600;text-decoration:none;border:none;cursor:pointer;}
.btn-mob-primary{background:var(--primary);color:#fff;}
.btn-mob-outline{background:#fff;color:var(--primary);border:1.5px solid var(--primary);}
.btn-mob-danger{background:#fff;color:#c0392b;border:1.5px solid #c0392b;}
.btn-mob-grey{background:#f0f0f0;color:#555;border:none;}
.add-btn{display:block;text-align:center;background:#fff;border:2px dashed #2e86c1;
         border-radius:12px;padding:.8rem;color:#2e86c1;font-weight:600;font-size:.85rem;
         text-decoration:none;margin-top:.5rem;}

/* Login screen */
.login-screen{min-height:calc(100vh - 56px);display:flex;align-items:center;justify-content:center;padding:1.5rem;}
.login-card{background:#fff;border-radius:18px;padding:1.8rem 1.4rem;
            box-shadow:0 4px 20px rgba(0,0,0,.1);width:100%;max-width:360px;}
.login-card h2{font-size:1.25rem;font-weight:800;color:var(--primary);margin-bottom:.2rem;}
.login-card .sub{font-size:.8rem;color:#777;margin-bottom:1.4rem;}
.form-control-mob{width:100%;border:1.5px solid #dde;border-radius:10px;padding:.65rem .9rem;
                  font-size:.9rem;outline:none;font-family:'Inter',sans-serif;}
.form-control-mob:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(46,134,193,.12);}

/* Section title */
.sec-title{font-size:.75rem;font-weight:700;color:#888;text-transform:uppercase;
           letter-spacing:.6px;margin-bottom:.7rem;padding-left:.1rem;}
</style>
</head>
<body>

<!-- Fixed top header -->
<header id="mob-header">
    <div class="brand">
        <div class="name">🤝 Kopo<span>nix</span></div>
        <div class="sub">Koperasi Sekata Rakyat</div>
    </div>
    <?php if ($member): ?>
    <div style="display:flex;align-items:center;gap:.5rem">
        <div class="member-info">
            <div class="mname"><?= e($member['name']) ?></div>
            <div class="mkop"><?= e($member['koperasi_id']) ?></div>
        </div>
        <div class="avatar">
            <?php if (!empty($member['avatar'])): ?>
                <img src="<?= e(img_url($member['avatar'])) ?>" alt="">
            <?php else: ?>
                <?= $initial ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</header>

<?php if (!$member): ?>
<!-- ── Login Screen ────────────────────────────────────────── -->
<div class="login-screen">
    <div class="login-card">
        <div style="text-align:center;font-size:2.5rem;margin-bottom:.8rem">🤝</div>
        <h2>Welcome Back</h2>
        <div class="sub">Sign in to your Koponix account</div>
        <?php if ($login_error): ?>
            <div style="background:#fde8e8;color:#c0392b;border-radius:8px;padding:.6rem .8rem;font-size:.82rem;margin-bottom:.9rem">
                <?= e($login_error) ?>
            </div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="action" value="login">
            <div style="margin-bottom:.8rem">
                <label style="font-size:.8rem;font-weight:600;color:#555;display:block;margin-bottom:.35rem">
                    Koperasi Member ID
                </label>
                <input type="text" name="koperasi_id" class="form-control-mob"
                    placeholder="e.g. KOP-2024-001"
                    value="<?= e($_POST['koperasi_id'] ?? '') ?>" required>
            </div>
            <div style="margin-bottom:1.2rem">
                <label style="font-size:.8rem;font-weight:600;color:#555;display:block;margin-bottom:.35rem">
                    Password
                </label>
                <input type="password" name="password" class="form-control-mob"
                    placeholder="Enter password" required>
            </div>
            <button type="submit"
                style="width:100%;background:var(--primary);color:#fff;border:none;
                       border-radius:10px;padding:.8rem;font-size:.95rem;font-weight:700;cursor:pointer">
                Sign In
            </button>
        </form>
        <div style="text-align:center;margin-top:1rem;font-size:.8rem;color:#888">
            <a href="member_portal.php" style="color:var(--accent)">Create account</a>
            &nbsp;·&nbsp;
            <a href="index.php" style="color:#aaa">Browse as guest</a>
        </div>
    </div>
</div>

<?php else: ?>

<?php if ($flash):
    $ftype = $flash['type'] === 'error' ? '#fde8e8;color:#c0392b' : '#d5f5e3;color:#1e8449';
?>
<div class="mob-flash" style="background:<?= $ftype ?>">
    <?= e($flash['msg']) ?>
</div>
<?php endif; ?>

<!-- ── HOME tab ───────────────────────────────────────────── -->
<section class="mob-section active" id="tab-home">
    <div class="welcome-banner">
        <div class="wg"><?= $greeting ?>,</div>
        <div class="wn"><?= e($member['name']) ?> 👋</div>
        <div class="wd"><?= $today ?></div>
    </div>

    <!-- Stats -->
    <div class="stat-grid">
        <div class="stat-cell">
            <div class="sv"><?= count($my_listings) ?></div>
            <div class="sl">My Listings</div>
        </div>
        <div class="stat-cell">
            <div class="sv"><?= count($active_l) ?></div>
            <div class="sl">Active</div>
        </div>
        <div class="stat-cell">
            <div class="sv"><?= count($my_requests) ?></div>
            <div class="sl">My Requests</div>
        </div>
        <div class="stat-cell">
            <div class="sv"><?= count($my_convs) ?></div>
            <div class="sl">Messages</div>
        </div>
    </div>

    <!-- Quick actions -->
    <div class="sec-title">Quick Actions</div>
    <div class="qa-grid">
        <a href="find_services.php" class="qa-btn">
            <span class="qi">🔍</span>Browse Services
        </a>
        <a href="request_service.php" class="qa-btn">
            <span class="qi">🛒</span>Post Request
        </a>
        <a href="register_service.php" class="qa-btn">
            <span class="qi">💼</span>New Listing
        </a>
        <a href="messages.php" class="qa-btn">
            <span class="qi">💬</span>Messages
        </a>
    </div>

    <!-- Recent listings -->
    <?php if ($my_listings): ?>
    <div class="sec-title">Recent Listings</div>
    <?php foreach (array_slice($my_listings, 0, 3) as $s):
        $col = $colors[$s['category']] ?? '#607d8b';
        $ico = $icons[$s['category']]  ?? '⭐';
    ?>
    <div class="mob-card">
        <div class="mc-body" style="display:flex;gap:.75rem;align-items:center">
            <?php if (!empty($s['image'])): ?>
                <img src="<?= e(img_url($s['image'])) ?>" class="l-thumb">
            <?php else: ?>
                <div class="l-thumb-placeholder" style="background:<?= $col ?>22"><?= $ico ?></div>
            <?php endif; ?>
            <div class="flex-grow-1 min-width-0">
                <span class="cat-badge" style="background:<?= $col ?>"><?= $ico ?> <?= e($s['category']) ?></span>
                <span class="ms-1 <?= $s['status']==='active'?'pill-active':'pill-inactive' ?>"><?= e($s['status']) ?></span>
                <div class="mc-head mt-1"><?= e($s['service_title']) ?></div>
                <div class="mc-meta">📍 <?= e($s['area']) ?> · 💰 <?= e($s['price_range']) ?></div>
            </div>
        </div>
        <div class="mc-footer">
            <a href="edit_listing.php?id=<?= urlencode($s['id']) ?>" class="btn-mob btn-mob-primary">✏️ Edit</a>
            <a href="find_services.php?category=<?= urlencode($s['category']) ?>" class="btn-mob btn-mob-outline">View Public</a>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (count($my_listings) > 3): ?>
        <div style="text-align:center;margin-bottom:.8rem">
            <a onclick="switchTab('listings')" href="#" style="font-size:.8rem;color:var(--accent)">
                View all <?= count($my_listings) ?> listings →
            </a>
        </div>
    <?php endif; ?>
    <?php else: ?>
    <div style="text-align:center;padding:1.5rem;background:#fff;border-radius:12px;margin-bottom:.8rem">
        <div style="font-size:2.5rem">📋</div>
        <div style="font-size:.85rem;font-weight:600;color:#555;margin:.5rem 0 .3rem">No listings yet</div>
        <a href="register_service.php" class="btn-mob btn-mob-primary">Add Your First Listing</a>
    </div>
    <?php endif; ?>
</section>

<!-- ── LISTINGS tab ───────────────────────────────────────── -->
<section class="mob-section" id="tab-listings">
    <div class="sec-title"><?= count($my_listings) ?> Listing<?= count($my_listings)!=1?'s':'' ?></div>
    <?php if (!$my_listings): ?>
    <div style="text-align:center;padding:2rem 1rem;background:#fff;border-radius:14px">
        <div style="font-size:3rem">📋</div>
        <div style="font-size:.9rem;font-weight:600;color:#555;margin:.6rem 0 .4rem">No listings yet</div>
        <div style="font-size:.8rem;color:#888;margin-bottom:1rem">List your skill or service to start earning</div>
        <a href="register_service.php" class="btn-mob btn-mob-primary">+ Add Listing</a>
    </div>
    <?php else: ?>
    <?php foreach ($my_listings as $s):
        $col = $colors[$s['category']] ?? '#607d8b';
        $ico = $icons[$s['category']]  ?? '⭐';
    ?>
    <div class="mob-card">
        <div class="mc-body" style="display:flex;gap:.75rem;align-items:flex-start">
            <?php if (!empty($s['image'])): ?>
                <img src="<?= e(img_url($s['image'])) ?>" class="l-thumb">
            <?php else: ?>
                <div class="l-thumb-placeholder" style="background:<?= $col ?>22"><?= $ico ?></div>
            <?php endif; ?>
            <div class="flex-grow-1">
                <span class="cat-badge" style="background:<?= $col ?>"><?= $ico ?> <?= e($s['category']) ?></span>
                <span class="ms-1 <?= $s['status']==='active'?'pill-active':'pill-inactive' ?>"><?= e($s['status']) ?></span>
                <div class="mc-head mt-1"><?= e($s['service_title']) ?></div>
                <div class="mc-meta">📍 <?= e($s['area']) ?></div>
                <div class="mc-meta">💰 <?= e($s['price_range']) ?></div>
                <div class="mc-meta">🕐 <?= e($s['availability']) ?></div>
            </div>
        </div>
        <div class="mc-footer">
            <a href="edit_listing.php?id=<?= urlencode($s['id']) ?>" class="btn-mob btn-mob-primary">✏️ Edit</a>
            <a href="find_services.php?category=<?= urlencode($s['category']) ?>" class="btn-mob btn-mob-outline">View Public</a>
            <a href="ai_assistant.php?prompt=<?= urlencode('Tips for my listing: '.$s['service_title']) ?>" class="btn-mob btn-mob-grey">🤖 AI Tips</a>
        </div>
    </div>
    <?php endforeach; ?>
    <a href="register_service.php" class="add-btn">+ Add New Listing</a>
    <?php endif; ?>
</section>

<!-- ── REQUESTS tab ──────────────────────────────────────── -->
<section class="mob-section" id="tab-requests">
    <div class="sec-title"><?= count($my_requests) ?> Request<?= count($my_requests)!=1?'s':'' ?></div>
    <?php if (!$my_requests): ?>
    <div style="text-align:center;padding:2rem 1rem;background:#fff;border-radius:14px">
        <div style="font-size:3rem">🛒</div>
        <div style="font-size:.9rem;font-weight:600;color:#555;margin:.6rem 0 .4rem">No requests yet</div>
        <div style="font-size:.8rem;color:#888;margin-bottom:1rem">Post a request and we'll match you with providers</div>
        <a href="request_service.php" class="btn-mob btn-mob-primary">Post a Request</a>
    </div>
    <?php else: ?>
    <?php
    $spill = ['open'=>'pill-open','in progress'=>'badge bg-warning text-dark','matched'=>'pill-matched','closed'=>'pill-closed'];
    foreach ($my_requests as $r):
        $col = $colors[$r['category']] ?? '#607d8b';
        $ico = $icons[$r['category']]  ?? '⭐';
    ?>
    <div class="mob-card">
        <div class="mc-body">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.4rem">
                <span class="cat-badge" style="background:<?= $col ?>"><?= $ico ?> <?= e($r['category']) ?></span>
                <span class="<?= $spill[$r['status']] ?? 'pill-closed' ?>"><?= e($r['status']) ?></span>
            </div>
            <div style="font-size:.82rem;color:#333;margin-bottom:.3rem">
                <?= e(substr($r['service_description'],0,90)) ?><?= strlen($r['service_description'])>90?'…':'' ?>
            </div>
            <div class="mc-meta">📍 <?= e($r['location']) ?>
                <?php if($r['budget']): ?> · 💰 <?= e($r['budget']) ?><?php endif; ?>
                · <?= e($r['submitted_date']) ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <a href="request_service.php" class="add-btn">+ New Request</a>
    <?php endif; ?>
</section>

<!-- ── MESSAGES tab ──────────────────────────────────────── -->
<section class="mob-section" id="tab-messages">
    <div class="sec-title"><?= count($my_convs) ?> Conversation<?= count($my_convs)!=1?'s':'' ?></div>
    <?php if (!$my_convs): ?>
    <div style="text-align:center;padding:2.5rem 1rem;background:#fff;border-radius:14px">
        <div style="font-size:3rem">💬</div>
        <div style="font-size:.9rem;font-weight:600;color:#555;margin:.6rem 0 .4rem">No messages yet</div>
        <div style="font-size:.8rem;color:#888;margin-bottom:1rem">Go to Find Services and tap Message on any listing</div>
        <a href="find_services.php" class="btn-mob btn-mob-primary">Browse Services</a>
    </div>
    <?php else: ?>
    <?php foreach ($my_convs as $c):
        $other = ($c['buyer_kop_id'] === $kop_id) ? $c['seller_name'] : $c['buyer_name'];
        $initial_other = mb_strtoupper(mb_substr($other, 0, 1));
    ?>
    <a href="messages.php?conv=<?= urlencode($c['id']) ?>" class="conv-row">
        <div class="conv-avatar"><?= $initial_other ?></div>
        <div class="conv-info" style="flex:1;min-width:0">
            <div class="ci-name"><?= e($other) ?></div>
            <div class="ci-sub"><?= e($c['subject']) ?></div>
        </div>
        <div class="conv-date"><?= e(substr($c['created_date'],5)) ?></div>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
</section>

<!-- ── PROFILE tab ───────────────────────────────────────── -->
<section class="mob-section" id="tab-profile">
    <div style="text-align:center;padding:1.2rem 0 .8rem">
        <div class="prof-avatar">
            <?php if (!empty($member['avatar'])): ?>
                <img src="<?= e(img_url($member['avatar'])) ?>" alt="">
            <?php else: ?>
                <?= $initial ?>
            <?php endif; ?>
        </div>
        <div style="font-size:1.05rem;font-weight:800;color:#1a3a52"><?= e($member['name']) ?></div>
        <div style="font-size:.78rem;color:#888;margin-top:.15rem"><?= e($member['koperasi_id']) ?></div>
        <?php if (!empty($member['bio'])): ?>
            <div style="font-size:.8rem;color:#666;margin-top:.5rem;font-style:italic;padding:0 1.5rem">
                <?= e($member['bio']) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Info card -->
    <div class="mob-card">
        <div class="mc-body">
            <div class="sec-title" style="margin-bottom:.6rem">Account Details</div>
            <?php foreach ([
                ['Email', $member['email'] ?: '—'],
                ['Member ID', $member['koperasi_id']],
                ['Joined', $member['joined_date']],
                ['Role', ucfirst($member['role'])],
            ] as [$lbl,$val]): ?>
            <div style="display:flex;justify-content:space-between;padding:.45rem 0;
                        border-bottom:1px solid #f5f5f5;font-size:.82rem">
                <span style="color:#888;font-weight:600"><?= $lbl ?></span>
                <span style="color:#333;font-weight:500"><?= e($val) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Actions -->
    <div style="display:flex;flex-direction:column;gap:.7rem;margin-bottom:1rem">
        <a href="member_portal.php?tab=profile" class="btn-mob btn-mob-primary"
            style="display:block;text-align:center;padding:.7rem">
            ✏️ Edit Profile &amp; Change Password
        </a>
        <a href="member_portal.php?tab=listings" class="btn-mob btn-mob-outline"
            style="display:block;text-align:center;padding:.7rem">
            📋 Manage Listings (Desktop View)
        </a>
        <a href="promo_generator.php" class="btn-mob btn-mob-outline"
            style="display:block;text-align:center;padding:.7rem">
            📣 Generate Promo Content
        </a>
        <a href="ai_assistant.php" class="btn-mob btn-mob-outline"
            style="display:block;text-align:center;padding:.7rem">
            🤖 AI Assistant
        </a>
        <a href="logout.php" class="btn-mob btn-mob-danger"
            style="display:block;text-align:center;padding:.7rem">
            Sign Out
        </a>
    </div>

    <div style="text-align:center;padding-bottom:.5rem">
        <a href="index.php" style="font-size:.75rem;color:#aaa">🖥️ Switch to Desktop View</a>
    </div>
</section>

<?php endif; ?>

<!-- Fixed bottom nav (only shown when logged in) -->
<?php if ($member): ?>
<nav id="mob-nav">
    <a href="#" onclick="switchTab('home')" class="active" id="nav-home">
        <span class="ni">🏠</span>Home
    </a>
    <a href="#" onclick="switchTab('listings')" id="nav-listings">
        <span class="ni">📋</span>Listings
    </a>
    <a href="#" onclick="switchTab('requests')" id="nav-requests">
        <span class="ni">🛒</span>Requests
    </a>
    <a href="#" onclick="switchTab('messages')" id="nav-messages">
        <span class="ni">💬</span>Messages
    </a>
    <a href="#" onclick="switchTab('profile')" id="nav-profile">
        <span class="ni">👤</span>Profile
    </a>
</nav>
<?php endif; ?>

<script>
function switchTab(name) {
    document.querySelectorAll('.mob-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('#mob-nav a').forEach(a => a.classList.remove('active'));
    const sec = document.getElementById('tab-' + name);
    const nav = document.getElementById('nav-' + name);
    if (sec) sec.classList.add('active');
    if (nav) nav.classList.add('active');
    window.scrollTo(0, 0);
    return false;
}
// Activate tab from URL hash
const hash = window.location.hash.replace('#','');
if (hash && document.getElementById('tab-' + hash)) switchTab(hash);
</script>
</body>
</html>
