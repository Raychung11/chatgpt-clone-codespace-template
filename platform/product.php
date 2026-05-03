<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header('Location: /marketplace.php'); exit; }

$product = DB::fetch(
    'SELECT p.*, c.name as cat_name, c.icon as cat_icon, c.color as cat_color, c.slug as cat_slug
     FROM products p LEFT JOIN categories c ON p.category_id=c.id
     WHERE p.slug=? AND p.is_active=1',
    [$slug]
);
if (!$product) { header('HTTP/1.0 404 Not Found'); die('<h1>Product not found</h1>'); }

$features  = json_decode($product['features']  ?? '[]', true);
$useCases  = json_decode($product['use_cases'] ?? '[]', true);
$reviews   = DB::fetchAll('SELECT r.*, u.name FROM reviews r JOIN users u ON r.user_id=u.id WHERE r.product_id=? AND r.is_approved=1 ORDER BY r.created_at DESC LIMIT 6', [$product['id']]);
$related   = DB::fetchAll('SELECT * FROM products WHERE category_id=? AND id!=? AND is_active=1 LIMIT 3', [$product['category_id'], $product['id']]);
$isOwned   = Auth::owns($product['id']);

$pageTitle = $product['name'] . ' — BizAI Capsule';
$pageDesc  = $product['tagline'];

require_once 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="container py-3">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/" class="text-muted text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="/marketplace.php" class="text-muted text-decoration-none">Marketplace</a></li>
            <?php if ($product['cat_name']): ?>
            <li class="breadcrumb-item"><a href="/marketplace.php?cat=<?= $product['cat_slug'] ?>" class="text-muted text-decoration-none"><?= htmlspecialchars($product['cat_name']) ?></a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active text-white"><?= htmlspecialchars($product['name']) ?></li>
        </ol>
    </nav>
</div>

<!-- Product Hero -->
<section class="py-5">
    <div class="container">
        <div class="row g-5 align-items-start">
            <div class="col-lg-7">
                <!-- Header -->
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="avatar-circle-lg" style="background:<?= $product['cat_color'] ?? '#6366f1' ?>22;color:<?= $product['cat_color'] ?? '#6366f1' ?>">
                        <i class="bi <?= $product['cat_icon'] ?? 'bi-cpu' ?> fs-3"></i>
                    </div>
                    <div>
                        <?php if ($product['badge']): ?>
                        <span class="badge badge-hot mb-1"><?= htmlspecialchars($product['badge']) ?></span>
                        <?php endif; ?>
                        <div class="text-muted small"><?= htmlspecialchars($product['cat_name'] ?? '') ?></div>
                    </div>
                </div>

                <h1 class="display-5 fw-bold text-white mb-3"><?= htmlspecialchars($product['name']) ?></h1>
                <p class="lead text-muted mb-4"><?= htmlspecialchars($product['tagline']) ?></p>

                <!-- Stats row -->
                <div class="d-flex flex-wrap gap-4 mb-5">
                    <?php if ($product['rating'] > 0): ?>
                    <div class="d-flex align-items-center gap-2">
                        <div class="text-warning">
                            <?php for ($i=1;$i<=5;$i++) echo $i <= round($product['rating']) ? '&#9733;' : '&#9734;'; ?>
                        </div>
                        <span class="text-white fw-semibold"><?= $product['rating'] ?></span>
                        <span class="text-muted small">(<?= $product['review_count'] ?> reviews)</span>
                    </div>
                    <?php endif; ?>
                    <div class="text-muted small"><i class="bi bi-people me-1"></i><?= number_format($product['sales_count']) ?>+ users</div>
                </div>

                <!-- Description -->
                <h5 class="text-white fw-semibold mb-3">About This Capsule</h5>
                <p class="text-muted"><?= nl2br(htmlspecialchars($product['description'])) ?></p>

                <!-- Features -->
                <?php if ($features): ?>
                <h5 class="text-white fw-semibold mb-3 mt-4">What's Included</h5>
                <div class="row g-2">
                    <?php foreach ($features as $f): ?>
                    <div class="col-sm-6">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-success"></i>
                            <span class="text-muted small"><?= htmlspecialchars($f) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Use Cases -->
                <?php if ($useCases): ?>
                <h5 class="text-white fw-semibold mb-3 mt-5">Use Cases</h5>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($useCases as $uc): ?>
                    <span class="badge bg-secondary bg-opacity-30 text-muted border border-secondary border-opacity-25 px-3 py-2"><?= htmlspecialchars($uc) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Demo Section -->
                <?php if ($product['demo_url']): ?>
                <div class="glass-card rounded-4 p-4 mt-5">
                    <h5 class="text-white fw-semibold mb-2"><i class="bi bi-play-circle me-2 text-primary"></i>Try the Live Demo</h5>
                    <p class="text-muted small mb-3">Try this Capsule with real data — no account required.</p>
                    <a href="<?= htmlspecialchars($product['demo_url']) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary">
                        <i class="bi bi-box-arrow-up-right me-2"></i>Launch Demo
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pricing Card -->
            <div class="col-lg-5">
                <div class="pricing-sticky-card rounded-4 p-4 sticky-top" style="top:80px">
                    <?php if ($isOwned): ?>
                    <div class="alert alert-success mb-4">
                        <i class="bi bi-check-circle-fill me-2"></i>You already have access to this Capsule!
                    </div>
                    <a href="/dashboard.php" class="btn btn-success w-100 mb-2">
                        <i class="bi bi-grid me-2"></i>Go to Dashboard
                    </a>
                    <?php else: ?>
                    <!-- Monthly Price -->
                    <div class="text-center mb-4">
                        <div class="text-muted small mb-1">Starting from</div>
                        <div class="display-4 fw-bold text-white">
                            <?= APP_CURRENCY ?><?= number_format($product['price_monthly'], 0) ?>
                        </div>
                        <div class="text-muted">per month</div>
                        <?php if ($product['price_yearly'] > 0): ?>
                        <div class="mt-2">
                            <span class="badge bg-success">Save <?= round((1 - ($product['price_yearly'] / ($product['price_monthly'] * 12))) * 100) ?>% yearly</span>
                            <div class="text-muted small"><?= APP_CURRENCY ?><?= number_format($product['price_yearly'], 0) ?>/year</div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-grid gap-2 mb-4">
                        <a href="/checkout.php?product=<?= $product['id'] ?>&plan=monthly" class="btn btn-primary btn-lg">
                            <i class="bi bi-lightning-charge me-2"></i>Start Free Trial
                        </a>
                        <?php if ($product['price_yearly'] > 0): ?>
                        <a href="/checkout.php?product=<?= $product['id'] ?>&plan=yearly" class="btn btn-outline-primary">
                            Get Yearly (Save <?= APP_CURRENCY ?><?= number_format($product['price_monthly'] * 12 - $product['price_yearly'], 0) ?>)
                        </a>
                        <?php endif; ?>
                        <?php if ($product['demo_url']): ?>
                        <a href="<?= htmlspecialchars($product['demo_url']) ?>" target="_blank" class="btn btn-outline-secondary">
                            <i class="bi bi-play-circle me-2"></i>Try Demo First
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Trust signals -->
                    <div class="border-top border-secondary border-opacity-25 pt-3">
                        <div class="d-flex flex-column gap-2">
                            <div class="d-flex align-items-center gap-2 text-muted small">
                                <i class="bi bi-shield-check text-success"></i> <?= TRIAL_DAYS ?>-day free trial, no credit card required
                            </div>
                            <div class="d-flex align-items-center gap-2 text-muted small">
                                <i class="bi bi-arrow-counterclockwise text-primary"></i> Cancel anytime, no lock-in
                            </div>
                            <div class="d-flex align-items-center gap-2 text-muted small">
                                <i class="bi bi-lock text-warning"></i> Secure payment via Stripe
                            </div>
                            <div class="d-flex align-items-center gap-2 text-muted small">
                                <i class="bi bi-headset text-info"></i> 24/7 dedicated support
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Reviews -->
<?php if ($reviews): ?>
<section class="py-5 bg-section">
    <div class="container">
        <h4 class="text-white fw-bold mb-4">Customer Reviews</h4>
        <div class="row g-4">
            <?php foreach ($reviews as $r): ?>
            <div class="col-md-6">
                <div class="testimonial-card p-4 rounded-4">
                    <div class="text-warning mb-2">
                        <?php for ($i=1;$i<=5;$i++) echo $i<=$r['rating'] ? '&#9733;' : '&#9734;'; ?>
                    </div>
                    <?php if ($r['title']): ?>
                    <h6 class="text-white fw-semibold mb-2"><?= htmlspecialchars($r['title']) ?></h6>
                    <?php endif; ?>
                    <p class="text-muted small"><?= htmlspecialchars($r['body']) ?></p>
                    <div class="text-muted" style="font-size:12px">— <?= htmlspecialchars($r['name']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Related Products -->
<?php if ($related): ?>
<section class="py-5">
    <div class="container">
        <h4 class="text-white fw-bold mb-4">Related Capsules</h4>
        <div class="row g-4">
            <?php foreach ($related as $rp): ?>
            <div class="col-md-4">
                <div class="product-card h-100 rounded-4 overflow-hidden">
                    <div class="p-4">
                        <h6 class="text-white fw-bold"><?= htmlspecialchars($rp['name']) ?></h6>
                        <p class="text-muted small"><?= htmlspecialchars($rp['tagline']) ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-white"><?= APP_CURRENCY ?><?= number_format($rp['price_monthly'], 0) ?>/mo</span>
                            <a href="/product.php?slug=<?= $rp['slug'] ?>" class="btn btn-primary btn-sm">View</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
