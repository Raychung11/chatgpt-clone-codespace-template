<?php
require_once __DIR__ . '/layout.php';
html_head('Home');
html_body_open();

$total_sellers  = (int) db()->query("SELECT COUNT(*) FROM sellers WHERE status='active'")->fetchColumn();
$total_requests = (int) db()->query("SELECT COUNT(*) FROM requests")->fetchColumn();
$total_members  = (int) db()->query("SELECT COUNT(*) FROM members")->fetchColumn();
$categories     = categories();
$icons          = cat_icons();
$colors         = cat_colors();
?>

<div class="hero">
    <h2>🤝 Welcome to Koponix</h2>
    <p class="mb-3 opacity-90">AI-powered Koperasi Digital Economy Activation System — connecting members through trusted services.</p>
    <div class="d-flex gap-2 flex-wrap">
        <a href="find_services.php" class="btn btn-light btn-sm fw-bold">🔍 Find Services</a>
        <a href="register_service.php" class="btn btn-outline-light btn-sm">💼 List Your Service</a>
        <a href="request_service.php" class="btn btn-outline-light btn-sm">🛒 Request a Service</a>
    </div>
</div>

<!-- Stats -->
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

<!-- Categories -->
<div class="section-head">Browse by Category</div>
<div class="row g-2 mb-4">
<?php foreach ($categories as $cat): ?>
    <div class="col-6 col-md-4 col-lg-3">
        <a href="find_services.php?category=<?= urlencode($cat) ?>" class="text-decoration-none">
            <div class="card h-100 p-3 text-center" style="border-left:4px solid <?= e($colors[$cat] ?? '#607d8b') ?>">
                <div style="font-size:1.6rem"><?= $icons[$cat] ?? '⭐' ?></div>
                <div style="font-size:.8rem;font-weight:600;color:#1a5276;margin-top:.3rem"><?= e($cat) ?></div>
            </div>
        </a>
    </div>
<?php endforeach; ?>
</div>

<!-- How it works -->
<div class="section-head">How It Works</div>
<div class="row g-3">
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div style="font-size:1.8rem">📝</div>
            <div class="fw-bold mt-2" style="font-size:.85rem">1. Register</div>
            <div class="text-muted" style="font-size:.78rem">Sign up as a member and list your service skills.</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div style="font-size:1.8rem">🔍</div>
            <div class="fw-bold mt-2" style="font-size:.85rem">2. Discover</div>
            <div class="text-muted" style="font-size:.78rem">Browse or use AI search to find services you need.</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div style="font-size:1.8rem">🎯</div>
            <div class="fw-bold mt-2" style="font-size:.85rem">3. Match</div>
            <div class="text-muted" style="font-size:.78rem">Our AI engine matches buyers with the best-fit providers.</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div style="font-size:1.8rem">💬</div>
            <div class="fw-bold mt-2" style="font-size:.85rem">4. Connect</div>
            <div class="text-muted" style="font-size:.78rem">Message sellers directly with AI bot assistance.</div>
        </div>
    </div>
</div>

<?php html_footer(); ?>
