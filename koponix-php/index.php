<?php
require_once __DIR__ . '/layout.php';
html_head('Home');
html_body_open();

$total_sellers  = (int) db()->query("SELECT COUNT(*) FROM sellers WHERE status='active'")->fetchColumn();
$total_requests = (int) db()->query("SELECT COUNT(*) FROM requests")->fetchColumn();
$total_members  = (int) db()->query("SELECT COUNT(*) FROM members")->fetchColumn();

// Latest 4 listings with photos for showcase
$stmt = db()->query("SELECT * FROM sellers WHERE status='active' ORDER BY registered_date DESC LIMIT 4");
$latest = $stmt->fetchAll();

$categories = categories();
$icons      = cat_icons();
$colors     = cat_colors();
?>

<!-- ── Hero ───────────────────────────────────────────────────── -->
<div style="background:linear-gradient(135deg,#0d3b5e 0%,#1a5276 50%,#1f618d 100%);
            border-radius:18px;padding:3rem 2rem 2.5rem;margin-bottom:2rem;
            color:#fff;position:relative;overflow:hidden">

    <!-- Decorative blobs -->
    <div style="position:absolute;top:-60px;right:-60px;width:280px;height:280px;
                border-radius:50%;background:rgba(255,255,255,.05)"></div>
    <div style="position:absolute;bottom:-80px;right:120px;width:200px;height:200px;
                border-radius:50%;background:rgba(255,255,255,.04)"></div>
    <div style="position:absolute;top:20px;right:200px;width:100px;height:100px;
                border-radius:50%;background:rgba(46,134,193,.25)"></div>

    <div style="position:relative">
        <div style="display:inline-block;background:rgba(255,255,255,.15);border-radius:20px;
                    padding:4px 14px;font-size:.78rem;font-weight:600;letter-spacing:.5px;margin-bottom:1rem">
            🤝 Koperasi Digital Economy Platform
        </div>
        <h1 style="font-size:2.2rem;font-weight:800;line-height:1.2;margin-bottom:.8rem">
            Earn. Discover.<br>Grow Together.
        </h1>
        <p style="font-size:1rem;opacity:.88;max-width:520px;margin-bottom:1.5rem;line-height:1.6">
            Koponix connects koperasi members as buyers and sellers — powered by AI matching,
            in-app messaging, and smart search. Your trusted marketplace, built for Malaysia.
        </p>

        <div class="d-flex gap-2 flex-wrap">
            <a href="find_services.php" class="btn btn-warning fw-bold px-4" style="border-radius:30px">
                🔍 Browse Services
            </a>
            <a href="register_service.php" class="btn btn-outline-light px-4" style="border-radius:30px">
                💼 List Your Service
            </a>
            <a href="request_service.php" class="btn btn-outline-light px-4" style="border-radius:30px">
                🛒 Post a Request
            </a>
        </div>
    </div>
</div>

<!-- ── About Koponix ──────────────────────────────────────────── -->
<div class="row g-4 mb-4 align-items-center">
    <div class="col-lg-7">
        <div class="section-head">About Koponix</div>
        <p style="font-size:.95rem;line-height:1.8;color:#333">
            <strong>Koponix</strong> is an AI-powered <em>Koperasi Digital Economy Activation System</em> built to
            help koperasi members earn income, discover trusted services, and transact in a structured,
            traceable way — all within their own community.
        </p>
        <p style="font-size:.93rem;line-height:1.8;color:#555">
            Whether you are a skilled member looking to offer your expertise, or a member in need of
            a reliable service provider, Koponix connects you directly — with AI-assisted matching,
            in-app messaging, and smart search built in. No middlemen. No hidden fees. Just member-to-member
            transactions you can trust.
        </p>
        <div class="d-flex flex-wrap gap-3 mt-3">
            <div style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;color:#1a5276;font-weight:600">
                <span style="background:#eaf4fb;border-radius:8px;padding:6px 10px;font-size:1.1rem">🤖</span>
                AI-Powered Matching
            </div>
            <div style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;color:#1a5276;font-weight:600">
                <span style="background:#eaf4fb;border-radius:8px;padding:6px 10px;font-size:1.1rem">💬</span>
                In-App Messaging
            </div>
            <div style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;color:#1a5276;font-weight:600">
                <span style="background:#eaf4fb;border-radius:8px;padding:6px 10px;font-size:1.1rem">🔒</span>
                Member-Verified Listings
            </div>
            <div style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;color:#1a5276;font-weight:600">
                <span style="background:#eaf4fb;border-radius:8px;padding:6px 10px;font-size:1.1rem">🇲🇾</span>
                Malaysian Koperasi Focus
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div style="background:linear-gradient(135deg,#eaf4fb,#d6eaf8);border-radius:14px;padding:1.5rem;border-left:5px solid #1a5276">
            <div style="font-size:.75rem;font-weight:700;letter-spacing:1px;color:#1a5276;text-transform:uppercase;margin-bottom:.8rem">
                🏢 Official Platform Partner
            </div>
            <div style="font-size:1.25rem;font-weight:800;color:#0d3b5e;margin-bottom:.3rem">
                Koperasi Sekata Rakyat
            </div>
            <div style="font-size:.85rem;color:#444;line-height:1.7;margin-bottom:.8rem">
                Koponix is proudly developed in collaboration with
                <strong>Koperasi Sekata Rakyat</strong> to empower its members through
                a structured digital marketplace — enabling sustainable income generation
                and community-driven economic growth.
            </div>
            <div style="font-size:.78rem;color:#1a5276;font-weight:600">
                🤝 Member-to-member economy &nbsp;|&nbsp; 📋 Traceable transactions &nbsp;|&nbsp; 🌱 Community growth
            </div>
        </div>
    </div>
</div>

<!-- ── Koperasi Sekata Rakyat Section ─────────────────────────── -->
<div style="background:#fff;border-radius:16px;padding:2rem;margin-bottom:2rem;box-shadow:0 2px 10px rgba(0,0,0,.07);border-top:5px solid #1a5276">
    <div class="row align-items-center g-4">
        <div class="col-lg-4 text-center">
            <div style="width:100px;height:100px;background:linear-gradient(135deg,#1a5276,#2e86c1);
                        border-radius:50%;display:flex;align-items:center;justify-content:center;
                        font-size:2.8rem;margin:0 auto .8rem">🤝</div>
            <div style="font-size:1.1rem;font-weight:800;color:#0d3b5e">Koperasi Sekata Rakyat</div>
            <div style="font-size:.8rem;color:#777;margin-top:.2rem">Registered Cooperative · Malaysia</div>
        </div>
        <div class="col-lg-8">
            <div class="section-head mb-2">Our Koperasi Partner</div>
            <p style="font-size:.9rem;line-height:1.8;color:#444;margin-bottom:.8rem">
                <strong>Koperasi Sekata Rakyat</strong> is a registered Malaysian cooperative committed to
                improving the economic well-being of its members through collective participation, mutual support,
                and structured business activities. The cooperative covers a diverse range of member skills and
                services across Klang Valley and beyond.
            </p>
            <p style="font-size:.88rem;line-height:1.75;color:#555;margin-bottom:1rem">
                Through Koponix, Koperasi Sekata Rakyat members can now list their professional services,
                respond to buyer requests, and grow their income — all within a trusted, AI-supported
                digital platform built specifically for the koperasi ecosystem.
            </p>
            <div class="row g-2">
                <div class="col-6 col-md-3">
                    <div style="background:#f0f9ff;border-radius:10px;padding:.7rem;text-align:center">
                        <div style="font-size:1.3rem;font-weight:800;color:#1a5276"><?= $total_members ?></div>
                        <div style="font-size:.72rem;color:#777">Members</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div style="background:#f0fdf4;border-radius:10px;padding:.7rem;text-align:center">
                        <div style="font-size:1.3rem;font-weight:800;color:#27ae60"><?= $total_sellers ?></div>
                        <div style="font-size:.72rem;color:#777">Services Listed</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div style="background:#fff9f0;border-radius:10px;padding:.7rem;text-align:center">
                        <div style="font-size:1.3rem;font-weight:800;color:#e67e22"><?= $total_requests ?></div>
                        <div style="font-size:.72rem;color:#777">Requests Posted</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div style="background:#fdf0f0;border-radius:10px;padding:.7rem;text-align:center">
                        <div style="font-size:1.3rem;font-weight:800;color:#e74c3c">9</div>
                        <div style="font-size:.72rem;color:#777">Service Categories</div>
                    </div>
                </div>
            </div>
            <div class="mt-3 d-flex gap-2 flex-wrap">
                <a href="find_services.php" class="btn btn-primary btn-sm">🔍 Browse Member Services</a>
                <a href="member_portal.php" class="btn btn-outline-primary btn-sm">👤 Member Login</a>
                <a href="register_service.php" class="btn btn-outline-secondary btn-sm">💼 List Your Service</a>
            </div>
        </div>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-4">
        <div class="stat-box">
            <div class="val"><?= $total_sellers ?></div>
            <div class="lbl">Active Services</div>
        </div>
    </div>
    <div class="col-4">
        <div class="stat-box">
            <div class="val"><?= $total_requests ?></div>
            <div class="lbl">Buyer Requests</div>
        </div>
    </div>
    <div class="col-4">
        <div class="stat-box">
            <div class="val"><?= $total_members ?></div>
            <div class="lbl">Members</div>
        </div>
    </div>
</div>

<!-- ── Latest Listings ─────────────────────────────────────────── -->
<?php if ($latest): ?>
<div class="section-head">✨ Latest Listings</div>
<div class="row g-3 mb-4">
<?php foreach ($latest as $s):
    $color   = $colors[$s['category']] ?? '#607d8b';
    $icon    = $icons[$s['category']]  ?? '⭐';
    $has_img = !empty($s['image']);
?>
    <div class="col-md-6 col-lg-3">
        <a href="find_services.php?category=<?= urlencode($s['category']) ?>" class="text-decoration-none">
        <div class="card h-100" style="border-radius:12px;overflow:hidden;border:none;box-shadow:0 3px 10px rgba(0,0,0,.09);transition:transform .15s" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
            <?php if ($has_img): ?>
                <img src="<?= e(img_url($s['image'])) ?>"
                    style="height:130px;object-fit:cover;width:100%">
            <?php else: ?>
                <div style="height:80px;background:linear-gradient(135deg,<?= $color ?>,<?= $color ?>88);
                            display:flex;align-items:center;justify-content:center;font-size:2.2rem">
                    <?= $icon ?>
                </div>
            <?php endif; ?>
            <div class="card-body p-2">
                <?= cat_badge($s['category']) ?>
                <div class="fw-bold small mt-1" style="color:#1a3a52;line-height:1.3"><?= e($s['service_title']) ?></div>
                <div style="font-size:.73rem;color:#777;margin-top:.2rem">📍 <?= e($s['area']) ?></div>
                <div style="font-size:.73rem;color:#1a5276;font-weight:600">💰 <?= e($s['price_range']) ?></div>
            </div>
        </div>
        </a>
    </div>
<?php endforeach; ?>
</div>
<div class="text-center mb-4">
    <a href="find_services.php" class="btn btn-outline-primary">View All Services →</a>
</div>
<?php endif; ?>

<!-- ── Categories ─────────────────────────────────────────────── -->
<div class="section-head">Browse by Category</div>
<div class="row g-2 mb-4">
<?php foreach ($categories as $cat): ?>
    <div class="col-6 col-md-4 col-lg-3">
        <a href="find_services.php?category=<?= urlencode($cat) ?>" class="text-decoration-none">
            <div class="card h-100 p-3 text-center" style="border-left:4px solid <?= e($colors[$cat] ?? '#607d8b') ?>;
                        border-radius:10px;border-top:none;border-right:none;border-bottom:none;
                        transition:transform .15s"
                onmouseover="this.style.transform='translateY(-2px)'"
                onmouseout="this.style.transform=''">
                <div style="font-size:1.8rem"><?= $icons[$cat] ?? '⭐' ?></div>
                <div style="font-size:.8rem;font-weight:700;color:#1a5276;margin-top:.3rem"><?= e($cat) ?></div>
            </div>
        </a>
    </div>
<?php endforeach; ?>
</div>

<!-- ── How It Works ───────────────────────────────────────────── -->
<div class="section-head">How It Works</div>
<div class="row g-3 mb-4">
    <?php $steps = [
        ['📝','Register','Sign up and list your skill. It\'s free for koperasi members.'],
        ['🔍','Discover','Browse services or use AI search to find exactly what you need.'],
        ['🎯','Match','Our AI engine scores and ranks the best providers for your request.'],
        ['💬','Connect','Message sellers directly with AI bot support — close deals faster.'],
    ];
    foreach ($steps as [$emoji,$title,$desc]): ?>
    <div class="col-md-3 col-6">
        <div class="card p-3 text-center h-100" style="border-radius:12px">
            <div style="font-size:2rem"><?= $emoji ?></div>
            <div class="fw-bold mt-2" style="font-size:.88rem"><?= $title ?></div>
            <div class="text-muted mt-1" style="font-size:.78rem"><?= $desc ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── CTA ────────────────────────────────────────────────────── -->
<div style="background:linear-gradient(135deg,#1a5276,#2e86c1);border-radius:14px;padding:2rem;color:#fff;text-align:center">
    <h4 style="font-weight:800;margin-bottom:.5rem">Ready to join the Koponix economy?</h4>
    <p style="opacity:.88;margin-bottom:1.2rem">Register now and start earning or discovering services within your koperasi network.</p>
    <div class="d-flex gap-2 justify-content-center flex-wrap">
        <a href="member_portal.php" class="btn btn-warning fw-bold px-4" style="border-radius:30px">
            🔑 Join / Login
        </a>
        <a href="find_services.php" class="btn btn-outline-light px-4" style="border-radius:30px">
            🔍 Browse First
        </a>
    </div>
</div>

<?php html_footer(); ?>
