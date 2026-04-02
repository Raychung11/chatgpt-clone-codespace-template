<?php
// ============================================================
//  KOPONIX – Shared Layout (header + sidebar + footer)
//  Usage: include at top/bottom of each page
// ============================================================
require_once __DIR__ . '/functions.php';
session_start_safe();

$flash   = get_flash();
$member  = current_member();
$page    = basename($_SERVER['PHP_SELF']);

function html_head(string $title = 'Koponix'): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> – Koponix</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🤝</text></svg>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--primary:#1a5276;--accent:#2e86c1;--light-bg:#f0f4f8;}
body{font-family:'Inter',sans-serif;background:var(--light-bg);min-height:100vh;}
#sidebar{width:230px;min-height:100vh;background:var(--primary);color:#fff;flex-shrink:0;position:sticky;top:0;height:100vh;overflow-y:auto;}
#sidebar .logo{font-size:1.4rem;font-weight:800;padding:1.2rem 1rem 0.4rem;letter-spacing:-0.5px;}
#sidebar .logo span{color:#5dade2;}
#sidebar .nav-link{color:rgba(255,255,255,.8);padding:.45rem 1rem;border-radius:6px;font-size:.85rem;transition:background .15s;}
#sidebar .nav-link:hover,#sidebar .nav-link.active{background:rgba(255,255,255,.15);color:#fff;}
#sidebar .member-box{background:rgba(255,255,255,.12);border-radius:8px;padding:.6rem .8rem;font-size:.8rem;margin:0 .8rem .8rem;}
#content{flex:1;min-width:0;padding:1.5rem;}
.page-title{font-size:1.5rem;font-weight:700;color:var(--primary);margin-bottom:1rem;}
.card{border:none;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.07);}
.card-header{border-radius:12px 12px 0 0!important;border-bottom:none;}
.btn-primary{background:var(--primary);border-color:var(--primary);}
.btn-primary:hover{background:var(--accent);border-color:var(--accent);}
.cat-badge{display:inline-block;padding:2px 10px;border-radius:30px;font-size:.72rem;font-weight:600;color:#fff;margin-bottom:.4rem;}
.seller-card{background:#fff;border-radius:12px;padding:1rem 1.1rem;box-shadow:0 2px 8px rgba(0,0,0,.07);margin-bottom:1rem;}
.seller-card h5{font-size:.95rem;font-weight:700;color:#1a3a52;margin:.4rem 0 .25rem;}
.seller-card .meta{font-size:.78rem;color:#555;margin-bottom:.15rem;}
.seller-card .desc{font-size:.8rem;color:#666;margin-top:.4rem;border-top:1px solid #eee;padding-top:.4rem;}
.stat-box{background:#fff;border-radius:10px;padding:1rem;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.06);}
.stat-box .val{font-size:1.6rem;font-weight:800;color:var(--primary);}
.stat-box .lbl{font-size:.75rem;color:#777;margin-top:.1rem;}
.hero{background:linear-gradient(135deg,var(--primary),var(--accent));border-radius:14px;color:#fff;padding:2rem 1.5rem;margin-bottom:1.5rem;}
.hero h2{font-weight:800;font-size:1.6rem;}
.section-head{font-size:1.1rem;font-weight:700;color:var(--primary);border-left:4px solid var(--accent);padding-left:.75rem;margin:.5rem 0 1rem;}
.pill-active{background:#d5f5e3;color:#1e8449;font-size:.7rem;padding:2px 8px;border-radius:20px;font-weight:600;}
.pill-inactive{background:#fde8e8;color:#c0392b;font-size:.7rem;padding:2px 8px;border-radius:20px;font-weight:600;}
.pill-open{background:#fde8e8;color:#c0392b;font-size:.7rem;padding:2px 8px;border-radius:20px;font-weight:600;}
.pill-matched{background:#d5f5e3;color:#1e8449;font-size:.7rem;padding:2px 8px;border-radius:20px;font-weight:600;}
.pill-closed{background:#eee;color:#777;font-size:.7rem;padding:2px 8px;border-radius:20px;font-weight:600;}
.chat-box{height:420px;overflow-y:auto;border:1px solid #dee2e6;border-radius:10px;padding:1rem;background:#fafbfc;}
.chat-msg{margin-bottom:.75rem;}
.chat-msg .bubble{display:inline-block;padding:.5rem .85rem;border-radius:14px;font-size:.85rem;max-width:80%;}
.chat-msg.mine{text-align:right;}
.chat-msg.mine .bubble{background:var(--primary);color:#fff;border-radius:14px 14px 0 14px;}
.chat-msg.theirs .bubble{background:#fff;color:#333;border:1px solid #e0e0e0;border-radius:14px 14px 14px 0;}
.chat-msg.ai-msg .bubble{background:#eaf4fb;color:#1a5276;border:1px solid #b8d9f0;border-radius:14px 14px 14px 0;}
.chat-ts{font-size:.68rem;color:#aaa;margin-top:2px;}
@media(max-width:768px){#sidebar{display:none;}}
</style>
<?php } ?>

<?php function html_body_open(): void { ?>
</head>
<body>
<div class="d-flex">
<?php
    global $member, $page;
    $links = [
        'index.php'             => ['🏠', 'Home'],
        'ai_assistant.php'      => ['💬', 'AI Assistant'],
        'find_services.php'     => ['🔍', 'Find Services'],
        'register_service.php'  => ['💼', 'Register Service'],
        'request_service.php'   => ['🛒', 'Request Service'],
        'match_engine.php'      => ['🎯', 'Match Engine'],
        'promo_generator.php'   => ['📣', 'Promo Generator'],
        'monthly_report.php'    => ['📊', 'Monthly Report'],
        'admin_dashboard.php'   => ['🛡️', 'Admin Dashboard'],
        'member_portal.php'     => ['👤', 'Member Portal'],
        'messages.php'          => ['💬', 'Messages'],
    ];
?>
<nav id="sidebar" class="d-flex flex-column">
    <div class="logo">🤝 Kopo<span>nix</span></div>
    <div style="font-size:.65rem;color:rgba(255,255,255,.55);padding:.1rem 1rem .8rem;line-height:1.3">
        Koperasi Sekata Rakyat<br>Digital Marketplace
    </div>
    <div class="px-2 py-2 flex-grow-1">
        <nav class="nav flex-column gap-1">
        <?php foreach ($links as $file => [$icon, $label]): ?>
            <a href="<?= $file ?>" class="nav-link <?= ($page === $file ? 'active' : '') ?>">
                <?= $icon ?> <?= $label ?>
            </a>
        <?php endforeach; ?>
        </nav>
    </div>
    <div class="pb-3">
    <?php if ($member): ?>
        <div class="member-box">
            <div class="fw-bold">👤 <?= e($member['name']) ?></div>
            <div class="opacity-75" style="font-size:.72rem"><?= e($member['koperasi_id']) ?></div>
        </div>
        <div class="px-2 d-flex gap-1">
            <a href="member_portal.php" class="btn btn-sm btn-outline-light flex-fill">Profile</a>
            <a href="logout.php" class="btn btn-sm btn-outline-light flex-fill">Logout</a>
        </div>
    <?php else: ?>
        <div class="px-2">
            <a href="member_portal.php" class="btn btn-sm btn-outline-light w-100">🔑 Member Login</a>
        </div>
    <?php endif; ?>
    </div>
</nav>
<main id="content">
<?php
    global $flash;
    if ($flash):
        $type = $flash['type'] === 'error' ? 'danger' : $flash['type'];
?>
    <div class="alert alert-<?= e($type) ?> alert-dismissible fade show" role="alert">
        <?= e($flash['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php } ?>

<?php function html_footer(): void { ?>
</main></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php } ?>
