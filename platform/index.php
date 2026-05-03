<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pageTitle = 'BizAI — Business Operating System for SMEs';
$pageDesc  = 'BizAI is the Business Operating System for SMEs. Deploy AI Capsules to automate operations, grow revenue, and run your business automatically.';

$featuredProducts = DB::fetchAll(
    'SELECT p.*, c.name as cat_name, c.icon as cat_icon, c.color as cat_color
     FROM products p LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.is_featured = 1 AND p.is_active = 1 ORDER BY p.sort_order LIMIT 6'
);
$categories = DB::fetchAll('SELECT *, (SELECT COUNT(*) FROM products WHERE category_id=categories.id AND is_active=1) as product_count FROM categories ORDER BY sort_order');
$stats = [
    'products' => DB::fetch('SELECT COUNT(*) as n FROM products WHERE is_active=1')['n'],
    'customers' => DB::fetch('SELECT COUNT(*) as n FROM users WHERE role="customer"')['n'],
    'categories' => DB::fetch('SELECT COUNT(*) as n FROM categories')['n'],
];

require_once 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section d-flex align-items-center">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="badge bg-primary-soft text-primary mb-3 px-3 py-2 rounded-pill fs-6">
                    <i class="bi bi-stars me-1"></i> <?= $stats['products'] ?>+ AI Capsules Ready to Deploy
                </span>
                <h1 class="display-4 fw-bold text-white lh-sm mb-4">
                    Run Your Business with <span class="text-gradient">AI Automation</span>
                </h1>
                <p class="lead text-muted mb-5">
                    Deploy plug-and-play AI Capsules across every department. Customer service, sales, HR, finance — running automatically.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="/marketplace.php" class="btn btn-primary btn-lg px-5 shadow">
                        <i class="bi bi-grid me-2"></i>Explore Capsule Store
                    </a>
                    <a href="/register.php" class="btn btn-outline-light btn-lg px-5">
                        Start Free Trial <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="d-flex gap-4 mt-5">
                    <div>
                        <div class="fs-4 fw-bold text-white"><?= $stats['products'] ?>+</div>
                        <div class="text-muted small">Capsules</div>
                    </div>
                    <div class="vr bg-secondary opacity-25"></div>
                    <div>
                        <div class="fs-4 fw-bold text-white"><?= number_format($stats['customers'] ?: 500) ?>+</div>
                        <div class="text-muted small">SMEs Served</div>
                    </div>
                    <div class="vr bg-secondary opacity-25"></div>
                    <div>
                        <div class="fs-4 fw-bold text-white"><?= $stats['categories'] ?>+</div>
                        <div class="text-muted small">Categories</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 d-none d-lg-block">
                <div class="hero-visual position-relative">
                    <div class="glass-card p-4 rounded-4 shadow-lg">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="avatar-circle bg-primary"><i class="bi bi-robot text-white fs-5"></i></div>
                            <div>
                                <div class="text-white fw-semibold">SmartSupport AI</div>
                                <div class="text-success small"><i class="bi bi-circle-fill me-1" style="font-size:8px"></i>Online</div>
                            </div>
                        </div>
                        <div class="chat-bubble user-bubble mb-2">Hi! I need help with my order #4521</div>
                        <div class="chat-bubble ai-bubble mb-2">I found order #4521! It's currently in transit and estimated to arrive on Friday. Would you like me to send you a tracking link?</div>
                        <div class="chat-bubble user-bubble">Yes please!</div>
                        <div class="mt-3 p-3 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-25">
                            <div class="text-success small fw-semibold"><i class="bi bi-check-circle me-1"></i>Resolution time: 47 seconds</div>
                        </div>
                    </div>
                    <div class="glass-card-mini p-3 rounded-3 position-absolute" style="top:-20px;right:-20px">
                        <div class="text-warning fw-bold fs-5">98%</div>
                        <div class="text-muted small">Satisfaction</div>
                    </div>
                    <div class="glass-card-mini p-3 rounded-3 position-absolute" style="bottom:10px;left:-20px">
                        <div class="text-primary fw-bold fs-5">3.2x</div>
                        <div class="text-muted small">Faster support</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Trusted By -->
<section class="py-4 border-top border-bottom border-secondary border-opacity-25">
    <div class="container">
        <p class="text-center text-muted small mb-3">TRUSTED BY SMES ACROSS INDUSTRIES</p>
        <div class="d-flex flex-wrap justify-content-center gap-4 align-items-center opacity-50">
            <span class="text-white fw-semibold fs-5">RetailCo</span>
            <span class="text-white fw-semibold fs-5">FinServe Ltd</span>
            <span class="text-white fw-semibold fs-5">BuildTech</span>
            <span class="text-white fw-semibold fs-5">HealthPlus</span>
            <span class="text-white fw-semibold fs-5">LogiFlow</span>
            <span class="text-white fw-semibold fs-5">EduGroup</span>
        </div>
    </div>
</section>

<!-- Categories -->
<section class="py-6">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold text-white">Capsules for Every Business Need</h2>
            <p class="text-muted">Browse by category and deploy the right Capsule for your business</p>
        </div>
        <div class="row g-3">
            <?php foreach ($categories as $cat): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="/marketplace.php?cat=<?= $cat['slug'] ?>" class="category-card d-block p-4 rounded-4 text-decoration-none h-100">
                    <div class="cat-icon mb-3" style="background:<?= $cat['color'] ?>22;color:<?= $cat['color'] ?>">
                        <i class="bi <?= $cat['icon'] ?> fs-4"></i>
                    </div>
                    <h6 class="text-white fw-semibold mb-1"><?= htmlspecialchars($cat['name']) ?></h6>
                    <div class="text-muted small"><?= $cat['product_count'] ?> capsules</div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Featured Products -->
<section class="py-6 bg-section">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold text-white mb-1">Featured Capsules</h2>
                <p class="text-muted mb-0">Hand-picked top performers across all categories</p>
            </div>
            <a href="/marketplace.php" class="btn btn-outline-primary">View All <i class="bi bi-arrow-right ms-1"></i></a>
        </div>
        <div class="row g-4">
            <?php foreach ($featuredProducts as $product): ?>
            <div class="col-md-6 col-lg-4">
                <div class="product-card h-100 rounded-4 overflow-hidden">
                    <div class="product-card-header p-4" style="background: linear-gradient(135deg, <?= $product['cat_color'] ?? '#6366f1' ?>22, transparent)">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="cat-icon-sm" style="background:<?= $product['cat_color'] ?? '#6366f1' ?>22;color:<?= $product['cat_color'] ?? '#6366f1' ?>">
                                <i class="bi <?= $product['cat_icon'] ?? 'bi-cpu' ?>"></i>
                            </div>
                            <?php if ($product['badge']): ?>
                            <span class="badge badge-hot"><?= htmlspecialchars($product['badge']) ?></span>
                            <?php endif; ?>
                        </div>
                        <h5 class="text-white fw-bold mb-1"><?= htmlspecialchars($product['name']) ?></h5>
                        <p class="text-muted small mb-0"><?= htmlspecialchars($product['tagline']) ?></p>
                    </div>
                    <div class="product-card-body p-4">
                        <?php
                        $features = json_decode($product['features'] ?? '[]', true);
                        $showFeatures = array_slice($features, 0, 3);
                        ?>
                        <ul class="list-unstyled small mb-3">
                            <?php foreach ($showFeatures as $f): ?>
                            <li class="mb-1 text-muted"><i class="bi bi-check-circle-fill text-success me-2"></i><?= htmlspecialchars($f) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="d-flex justify-content-between align-items-center mt-auto pt-3 border-top border-secondary border-opacity-25">
                            <div>
                                <span class="fs-5 fw-bold text-white"><?= APP_CURRENCY ?><?= number_format($product['price_monthly'], 0) ?></span>
                                <span class="text-muted small">/mo</span>
                            </div>
                            <a href="/product.php?slug=<?= $product['slug'] ?>" class="btn btn-primary btn-sm px-3">
                                Learn More <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- How It Works -->
<section class="py-6">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold text-white">Get Started in 3 Simple Steps</h2>
            <p class="text-muted">Deploy AI to your business in minutes, not months</p>
        </div>
        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="step-circle mb-4">1</div>
                <h5 class="text-white fw-semibold">Browse & Choose</h5>
                <p class="text-muted small">Browse our Capsule Store. Filter by department, business type, or use case to find the right Capsule for your business.</p>
            </div>
            <div class="col-md-4">
                <div class="step-circle mb-4">2</div>
                <h5 class="text-white fw-semibold">Try Before You Buy</h5>
                <p class="text-muted small">Every Capsule comes with a live demo. Test it with your real data before committing to a subscription.</p>
            </div>
            <div class="col-md-4">
                <div class="step-circle mb-4">3</div>
                <h5 class="text-white fw-semibold">Deploy & Automate</h5>
                <p class="text-muted small">Subscribe, follow the simple setup guide, and watch your business run on autopilot within hours.</p>
            </div>
        </div>
    </div>
</section>

<!-- Pricing -->
<section class="py-6 bg-section" id="pricing">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold text-white">Simple, Transparent Pricing</h2>
            <p class="text-muted">Start free. Scale as you grow. Cancel any time.</p>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-md-4">
                <div class="pricing-card rounded-4 p-4 h-100">
                    <div class="text-muted small fw-semibold mb-2 text-uppercase tracking-wide">Starter</div>
                    <div class="display-5 fw-bold text-white mb-1">RM3,500<span class="fs-6 text-muted fw-normal">/mo</span></div>
                    <p class="text-muted small mb-4">BOS Core + Customer Service Capsule. Solve your #1 pain immediately.</p>
                    <ul class="list-unstyled small mb-4">
                        <li class="mb-2 text-muted"><i class="bi bi-check-circle-fill text-success me-2"></i>BOS Core Platform</li>
                        <li class="mb-2 text-muted"><i class="bi bi-check-circle-fill text-success me-2"></i>Customer Service Capsule</li>
                        <li class="mb-2 text-muted"><i class="bi bi-check-circle-fill text-success me-2"></i>FAQ automation</li>
                        <li class="mb-2 text-muted"><i class="bi bi-check-circle-fill text-success me-2"></i>Multi-language support</li>
                        <li class="mb-2 text-muted opacity-50"><i class="bi bi-x-circle me-2"></i>Sales &amp; Marketing Capsules</li>
                    </ul>
                    <a href="/register.php" class="btn btn-outline-primary w-100">Get Started</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="pricing-card pricing-card-featured rounded-4 p-4 h-100 position-relative">
                    <div class="badge bg-primary position-absolute top-0 start-50 translate-middle px-3 py-2">Most Popular</div>
                    <div class="text-primary small fw-semibold mb-2 text-uppercase">Growth</div>
                    <div class="display-5 fw-bold text-white mb-1">RM10,000<span class="fs-6 text-muted fw-normal">/mo</span></div>
                    <p class="text-muted small mb-4">BOS Core + 3 Capsules. Automate customer service, sales, and marketing.</p>
                    <ul class="list-unstyled small mb-4">
                        <li class="mb-2 text-muted"><i class="bi bi-check-circle-fill text-success me-2"></i>BOS Core Platform</li>
                        <li class="mb-2 text-muted"><i class="bi bi-check-circle-fill text-success me-2"></i>Customer Service Capsule</li>
                        <li class="mb-2 text-muted"><i class="bi bi-check-circle-fill text-success me-2"></i>Sales Conversion Capsule</li>
                        <li class="mb-2 text-muted"><i class="bi bi-check-circle-fill text-success me-2"></i>Marketing Automation Capsule</li>
                        <li class="mb-2 text-muted"><i class="bi bi-check-circle-fill text-success me-2"></i>Advanced analytics + reports</li>
                    </ul>
                    <a href="/register.php" class="btn btn-primary w-100">Start Free Trial</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="pricing-card rounded-4 p-4 h-100">
                    <div class="text-muted small fw-semibold mb-2 text-uppercase">Enterprise</div>
                    <div class="display-5 fw-bold text-white mb-1">RM22,500<span class="fs-6 text-muted fw-normal">/mo</span></div>
                    <p class="text-muted small mb-4">All Capsules + AI Decision Layer. Your full Business Operating System.</p>
                    <ul class="list-unstyled small mb-4">
                        <li class="mb-2 text-muted"><i class="bi bi-check-circle-fill text-success me-2"></i>All 6 Capsules included</li>
                        <li class="mb-2 text-muted"><i class="bi bi-check-circle-fill text-success me-2"></i>AI Decision Layer</li>
                        <li class="mb-2 text-muted"><i class="bi bi-check-circle-fill text-success me-2"></i>Custom workflows</li>
                        <li class="mb-2 text-muted"><i class="bi bi-check-circle-fill text-success me-2"></i>Dedicated account manager</li>
                        <li class="mb-2 text-muted"><i class="bi bi-check-circle-fill text-success me-2"></i>White-label options</li>
                    </ul>
                    <a href="/contact.php" class="btn btn-outline-primary w-100">Contact Sales</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="py-6">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold text-white">What SME Owners Say</h2>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="testimonial-card p-4 rounded-4">
                    <div class="text-warning mb-3">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                    <p class="text-muted small">"SmartSupport AI cut our customer response time by 80%. Our team now focuses on high-value tasks while the AI handles routine queries 24/7."</p>
                    <div class="d-flex align-items-center gap-3 mt-3">
                        <div class="avatar-initials">SL</div>
                        <div>
                            <div class="text-white fw-semibold small">Sarah L.</div>
                            <div class="text-muted" style="font-size:12px">E-commerce Manager, RetailCo</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="testimonial-card p-4 rounded-4">
                    <div class="text-warning mb-3">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                    <p class="text-muted small">"LeadHunter AI tripled our qualified leads in the first month. The ROI was immediate. I can't imagine running sales without it now."</p>
                    <div class="d-flex align-items-center gap-3 mt-3">
                        <div class="avatar-initials">MK</div>
                        <div>
                            <div class="text-white fw-semibold small">Mark K.</div>
                            <div class="text-muted" style="font-size:12px">Sales Director, FinServe Ltd</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="testimonial-card p-4 rounded-4">
                    <div class="text-warning mb-3">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                    <p class="text-muted small">"HireBot AI screens 200 CVs in the time it took us to read 10. We hired 3 great candidates last month that we might have missed otherwise."</p>
                    <div class="d-flex align-items-center gap-3 mt-3">
                        <div class="avatar-initials">JP</div>
                        <div>
                            <div class="text-white fw-semibold small">James P.</div>
                            <div class="text-muted" style="font-size:12px">HR Lead, BuildTech</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="py-6 bg-section">
    <div class="container text-center">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <h2 class="fw-bold text-white mb-3">Ready to Automate Your Business?</h2>
                <p class="text-muted mb-5">Join <?= number_format($stats['customers'] ?: 500) ?>+ SMEs already saving time and money with AI. Start your 14-day free trial today.</p>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <a href="/register.php" class="btn btn-primary btn-lg px-5">Start Free Trial</a>
                    <a href="/marketplace.php" class="btn btn-outline-light btn-lg px-5">Browse Capsules</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact -->
<section class="py-6" id="contact">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 text-center mb-5">
                <h2 class="fw-bold text-white">Get in Touch</h2>
                <p class="text-muted">Have questions? Our team is here to help.</p>
            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="glass-card p-5 rounded-4">
                    <?php
                    $msg = '';
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
                        $name    = htmlspecialchars(trim($_POST['name'] ?? ''));
                        $email   = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
                        $company = htmlspecialchars(trim($_POST['company'] ?? ''));
                        $message = htmlspecialchars(trim($_POST['message'] ?? ''));
                        if ($name && $email && $message) {
                            DB::insert('leads', ['name'=>$name,'email'=>$email,'company'=>$company,'message'=>$message,'source'=>'homepage']);
                            $msg = '<div class="alert alert-success">Thanks! We\'ll be in touch within 24 hours.</div>';
                        } else {
                            $msg = '<div class="alert alert-danger">Please fill in all required fields.</div>';
                        }
                    }
                    echo $msg;
                    ?>
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label text-muted small">Name *</label>
                                <input type="text" name="name" class="form-control bg-dark border-secondary text-white" required placeholder="Your name">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted small">Email *</label>
                                <input type="email" name="email" class="form-control bg-dark border-secondary text-white" required placeholder="you@company.com">
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted small">Company</label>
                                <input type="text" name="company" class="form-control bg-dark border-secondary text-white" placeholder="Your company">
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted small">Message *</label>
                                <textarea name="message" rows="4" class="form-control bg-dark border-secondary text-white" required placeholder="How can we help?"></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="contact_submit" class="btn btn-primary w-100">
                                    <i class="bi bi-send me-2"></i>Send Message
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
