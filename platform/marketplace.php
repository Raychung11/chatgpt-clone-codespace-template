<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pageTitle    = 'Capsule Store';
$pageDesc     = 'Browse 20+ AI Capsules for Malaysian SMEs — customer service automation, sales AI, HR tools, finance reporting and more. From RM200/month.';
$pageKeywords = 'AI capsules Malaysia, AI tools for SME, WhatsApp chatbot Malaysia, business automation tools';

// Filters
$cat      = $_GET['cat']  ?? '';
$type     = $_GET['type'] ?? '';   // 'capsule' | 'tool' | ''
$q        = trim($_GET['q'] ?? '');
$sort     = $_GET['sort'] ?? 'featured';
$minPrice = (int)($_GET['min'] ?? 0);
$maxPrice = (int)($_GET['max'] ?? 99999);
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 12;
$offset   = ($page - 1) * $perPage;

// Build query
$where  = ['p.is_active = 1'];
$params = [];

if ($cat) {
    $where[]  = 'c.slug = ?';
    $params[] = $cat;
}
if ($q) {
    $where[]  = '(p.name LIKE ? OR p.tagline LIKE ? OR p.description LIKE ?)';
    $params   = array_merge($params, ["%$q%", "%$q%", "%$q%"]);
}
if ($minPrice > 0) {
    $where[]  = 'p.price_monthly >= ?';
    $params[] = $minPrice;
}
if ($maxPrice < 99999) {
    $where[]  = 'p.price_monthly <= ?';
    $params[] = $maxPrice;
}
// Type filter: capsules have higher price, tools are lower-priced standalone items
// We use sort_order: ≤24 = BOS capsules (sort_order 1-24), ≥25 = standalone tools, but
// a cleaner signal is price: capsules ≥RM800, tools < RM800. Use badge or featured flag.
// Best: sort_order 1-16 = core BOS capsules, 17-36 = standalone tools, 37+ = special.
if ($type === 'capsule') {
    $where[]  = 'p.sort_order BETWEEN 1 AND 16';
} elseif ($type === 'tool') {
    $where[]  = 'p.sort_order BETWEEN 17 AND 36';
} elseif ($type === 'platform') {
    $where[]  = 'p.sort_order >= 37';
}

$orderBy = match($sort) {
    'price_asc'  => 'p.price_monthly ASC',
    'price_desc' => 'p.price_monthly DESC',
    'newest'     => 'p.created_at DESC',
    'popular'    => 'p.sales_count DESC',
    default      => 'p.is_featured DESC, p.sort_order ASC',
};

$whereStr   = implode(' AND ', $where);
$total      = (int)(DB::fetch("SELECT COUNT(*) as n FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE $whereStr", $params)['n'] ?? 0);
$products   = DB::fetchAll(
    "SELECT p.*, c.name as cat_name, c.icon as cat_icon, c.color as cat_color, c.slug as cat_slug
     FROM products p LEFT JOIN categories c ON p.category_id=c.id
     WHERE $whereStr ORDER BY $orderBy LIMIT $perPage OFFSET $offset",
    $params
);

$categories       = DB::fetchAll('SELECT *, (SELECT COUNT(*) FROM products WHERE category_id=categories.id AND is_active=1) as cnt FROM categories ORDER BY sort_order');
$totalPages       = (int)ceil($total / $perPage);
$currentCategory  = $cat ? DB::fetch('SELECT * FROM categories WHERE slug=?', [$cat]) : null;
$totalAll         = (int)(DB::fetch('SELECT COUNT(*) as n FROM products WHERE is_active=1')['n'] ?? 0);

// Stats for hero
$capsulesCount = (int)(DB::fetch('SELECT COUNT(*) as n FROM products WHERE is_active=1 AND sort_order BETWEEN 1 AND 16')['n'] ?? 0);
$toolsCount    = (int)(DB::fetch('SELECT COUNT(*) as n FROM products WHERE is_active=1 AND sort_order BETWEEN 17 AND 36')['n'] ?? 0);

require_once 'includes/header.php';
?>

<style>
.mkt-hero {
    background: linear-gradient(160deg, #0a0a0f 0%, #0d0818 50%, #0a0a0f 100%);
    border-bottom: 1px solid rgba(99,102,241,0.12);
    padding: 60px 0 48px;
}
.mkt-hero h1 { font-size: clamp(2rem, 5vw, 3rem); font-weight: 800; letter-spacing: -0.03em; line-height: 1.15; }
.hero-stat { border-right: 1px solid rgba(255,255,255,0.08); }
.hero-stat:last-child { border-right: none; }

.type-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 16px; border-radius: 50px; font-size: 13px; font-weight: 500;
    border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.04);
    color: #9ca3af; text-decoration: none; transition: all 0.2s; cursor: pointer;
}
.type-pill:hover { border-color: #6366f1; color: #a5b4fc; background: rgba(99,102,241,0.08); }
.type-pill.active { border-color: #6366f1; color: #a5b4fc; background: rgba(99,102,241,0.15); }

.cat-chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 50px; font-size: 12px; font-weight: 500;
    border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.03);
    color: #9ca3af; text-decoration: none; transition: all 0.2s; white-space: nowrap;
}
.cat-chip:hover { color: #fff; background: rgba(255,255,255,0.07); border-color: rgba(255,255,255,0.2); }
.cat-chip.active { color: #fff; background: rgba(99,102,241,0.15); border-color: #6366f1; }

.product-card-new {
    background: #111118; border: 1px solid rgba(255,255,255,0.06);
    border-radius: 16px; overflow: hidden; transition: all 0.25s; display: flex; flex-direction: column;
}
.product-card-new:hover {
    border-color: rgba(99,102,241,0.35);
    box-shadow: 0 12px 40px rgba(99,102,241,0.1);
    transform: translateY(-4px);
}
.pcard-header { padding: 22px 22px 16px; }
.pcard-body { padding: 0 22px 22px; flex: 1; display: flex; flex-direction: column; }
.pcard-footer { padding: 16px 22px; border-top: 1px solid rgba(255,255,255,0.06); display: flex; align-items: center; justify-content: space-between; }

.type-tag {
    display: inline-block; font-size: 9px; font-weight: 700; letter-spacing: 0.08em;
    padding: 2px 7px; border-radius: 4px; text-transform: uppercase;
}
.type-tag-capsule { background: rgba(99,102,241,0.18); color: #a5b4fc; }
.type-tag-tool    { background: rgba(16,185,129,0.15); color: #6ee7b7; }
.type-tag-platform{ background: rgba(245,158,11,0.15); color: #fcd34d; }

.badge-featured-new {
    background: linear-gradient(135deg, #f59e0b, #f97316);
    color: #fff; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 4px;
}
.badge-popular { background: rgba(239,68,68,0.2); color: #fca5a5; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 4px; }
.badge-new-tag { background: rgba(16,185,129,0.2); color: #6ee7b7; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 4px; }
.badge-hot-tag { background: linear-gradient(135deg,#f97316,#ef4444); color: #fff; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 4px; }
.badge-premium { background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 4px; }

.price-big { font-size: 22px; font-weight: 800; color: #fff; letter-spacing: -0.02em; }
.price-unit { font-size: 12px; color: #6b7280; }

.filter-sidebar-new {
    background: #0d0d14; border: 1px solid rgba(255,255,255,0.06);
    border-radius: 16px; padding: 24px;
}

.search-bar-mkt {
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px; padding: 10px 16px; display: flex; align-items: center; gap: 10px;
    transition: border-color 0.2s;
}
.search-bar-mkt:focus-within { border-color: #6366f1; }
.search-bar-mkt input {
    background: none; border: none; outline: none; color: #fff; flex: 1; font-size: 14px;
}
.search-bar-mkt input::placeholder { color: #6b7280; }

.plan-strip {
    background: rgba(99,102,241,0.05);
    border-top: 1px solid rgba(99,102,241,0.12);
    border-bottom: 1px solid rgba(99,102,241,0.12);
}

.sort-btn { background: none; border: none; color: #6b7280; font-size: 13px; padding: 4px 10px; border-radius: 6px; cursor: pointer; }
.sort-btn.active, .sort-btn:hover { background: rgba(99,102,241,0.12); color: #a5b4fc; }

.results-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.results-count { font-size: 14px; color: #6b7280; }
</style>

<!-- ── Hero ── -->
<section class="mkt-hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="mb-3">
                    <span class="type-tag type-tag-capsule me-2">Capsule Store</span>
                    <span class="text-muted small">AiServe Business Operating System</span>
                </div>
                <h1 class="text-white mb-3">
                    The AI Stack<br>
                    <span style="background:linear-gradient(135deg,#6366f1,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent">Your Business Runs On</span>
                </h1>
                <p class="text-muted mb-4" style="font-size:17px;max-width:520px">
                    Plug-and-play AI Capsules for SMEs. Deploy customer service, sales automation,
                    HR, finance, and more — each Capsule connects directly into your BOS Core.
                </p>
                <form method="GET" class="d-flex gap-2 mb-4" style="max-width:520px">
                    <div class="search-bar-mkt flex-grow-1">
                        <i class="bi bi-search text-muted"></i>
                        <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search capsules, tools, or categories...">
                    </div>
                    <button class="btn btn-primary px-4 rounded-3">Search</button>
                </form>
                <!-- Stats row -->
                <div class="d-flex gap-0 flex-wrap">
                    <div class="hero-stat pe-4 me-4">
                        <div class="fs-4 fw-bold text-white"><?= $totalAll ?>+</div>
                        <div class="text-muted small">AI Capsules</div>
                    </div>
                    <div class="hero-stat pe-4 me-4">
                        <div class="fs-4 fw-bold text-white"><?= count($categories) ?></div>
                        <div class="text-muted small">Categories</div>
                    </div>
                    <div class="hero-stat pe-4 me-4">
                        <div class="fs-4 fw-bold text-white">RM200</div>
                        <div class="text-muted small">From / month</div>
                    </div>
                    <div class="hero-stat">
                        <div class="fs-4 fw-bold text-white">14-day</div>
                        <div class="text-muted small">Free trial</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 d-none d-lg-flex flex-wrap gap-2 justify-content-end">
                <?php
                $heroCategories = array_slice($categories, 0, 8);
                foreach ($heroCategories as $hc):
                ?>
                <a href="/marketplace.php?cat=<?= $hc['slug'] ?>"
                   class="d-flex align-items-center gap-2 px-3 py-2 rounded-3 text-decoration-none"
                   style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.07);color:#9ca3af;font-size:13px;transition:all 0.2s"
                   onmouseover="this.style.borderColor='<?= $hc['color'] ?>';this.style.color='<?= $hc['color'] ?>'"
                   onmouseout="this.style.borderColor='rgba(255,255,255,0.07)';this.style.color='#9ca3af'">
                    <i class="bi <?= $hc['icon'] ?>" style="color:<?= $hc['color'] ?>"></i>
                    <?= htmlspecialchars($hc['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- ── Plan Strip ── -->
<div class="plan-strip">
    <div class="container py-3">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <span class="text-muted small fw-semibold">Ready-made bundles:</span>
            <a href="/pricing.php" class="type-pill">
                <i class="bi bi-lightning-fill" style="color:#6366f1"></i>
                Starter <span class="text-muted">— RM3,500/mo</span>
            </a>
            <a href="/pricing.php" class="type-pill active">
                <i class="bi bi-graph-up-arrow" style="color:#f59e0b"></i>
                Growth <span class="text-muted">— RM10,000/mo</span>
                <span style="background:#6366f1;border-radius:20px;padding:1px 7px;font-size:10px;color:#fff">Popular</span>
            </a>
            <a href="/pricing.php" class="type-pill">
                <i class="bi bi-building" style="color:#8b5cf6"></i>
                Enterprise <span class="text-muted">— RM22,500/mo</span>
            </a>
            <a href="/pricing.php" class="text-muted small ms-auto text-decoration-none d-none d-md-inline">
                Compare plans <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</div>

<!-- ── Category Bar ── -->
<div style="background:#0a0a0f;border-bottom:1px solid rgba(255,255,255,0.05);overflow-x:auto">
    <div class="container">
        <div class="d-flex gap-2 py-3 flex-nowrap">
            <!-- Type filters -->
            <a href="?<?= http_build_query(array_merge(array_filter(['cat'=>$cat,'q'=>$q,'sort'=>$sort]), ['type'=>''])) ?>"
               class="cat-chip <?= !$type ? 'active' : '' ?>">
                <i class="bi bi-grid-3x3-gap"></i> All
            </a>
            <a href="?<?= http_build_query(array_merge(array_filter(['cat'=>$cat,'q'=>$q,'sort'=>$sort]), ['type'=>'capsule'])) ?>"
               class="cat-chip <?= $type === 'capsule' ? 'active' : '' ?>">
                <i class="bi bi-cpu-fill text-primary"></i> BOS Capsules <span class="opacity-50"><?= $capsulesCount ?></span>
            </a>
            <a href="?<?= http_build_query(array_merge(array_filter(['cat'=>$cat,'q'=>$q,'sort'=>$sort]), ['type'=>'tool'])) ?>"
               class="cat-chip <?= $type === 'tool' ? 'active' : '' ?>">
                <i class="bi bi-wrench text-success"></i> AI Tools <span class="opacity-50"><?= $toolsCount ?></span>
            </a>
            <a href="?<?= http_build_query(array_merge(array_filter(['cat'=>$cat,'q'=>$q,'sort'=>$sort]), ['type'=>'platform'])) ?>"
               class="cat-chip <?= $type === 'platform' ? 'active' : '' ?>">
                <i class="bi bi-kanban text-warning"></i> Platform OS
            </a>
            <div style="width:1px;background:rgba(255,255,255,0.08);flex-shrink:0;margin:4px 4px"></div>
            <!-- Category filters -->
            <?php foreach ($categories as $c): ?>
            <a href="?<?= http_build_query(array_merge(array_filter(['type'=>$type,'q'=>$q,'sort'=>$sort]), ['cat'=>$c['slug']])) ?>"
               class="cat-chip <?= $cat === $c['slug'] ? 'active' : '' ?>">
                <i class="bi <?= $c['icon'] ?>" style="color:<?= $c['color'] ?>"></i>
                <?= htmlspecialchars($c['name']) ?>
                <span class="opacity-50"><?= $c['cnt'] ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="container py-5">
    <div class="row g-4">

        <!-- ── Sidebar ── -->
        <div class="col-lg-3 d-none d-lg-block">
            <div class="filter-sidebar-new sticky-top" style="top:80px">
                <h6 class="text-white fw-semibold mb-4">Filter & Sort</h6>

                <div class="mb-4">
                    <div class="text-muted small fw-semibold mb-2 text-uppercase" style="font-size:10px;letter-spacing:.08em">Sort By</div>
                    <?php $sorts = ['featured'=>'Featured first','popular'=>'Most popular','price_asc'=>'Price: Low → High','price_desc'=>'Price: High → Low','newest'=>'Newest']; ?>
                    <?php foreach ($sorts as $val => $label): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['sort'=>$val,'page'=>1])) ?>"
                       class="d-block py-1 px-2 rounded text-decoration-none small mb-1 <?= $sort === $val ? 'text-primary' : 'text-muted' ?>"
                       style="<?= $sort === $val ? 'background:rgba(99,102,241,0.1)' : '' ?>">
                        <?= $sort === $val ? '<i class="bi bi-check2 me-2"></i>' : '<span class="me-4"></span>' ?><?= $label ?>
                    </a>
                    <?php endforeach; ?>
                </div>

                <div class="mb-4">
                    <div class="text-muted small fw-semibold mb-2 text-uppercase" style="font-size:10px;letter-spacing:.08em">Monthly Price</div>
                    <form method="GET">
                        <?php foreach (['cat','type','q','sort'] as $k): if (!empty($_GET[$k])): ?>
                        <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($_GET[$k]) ?>">
                        <?php endif; endforeach; ?>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <input type="number" name="min" value="<?= $minPrice ?: '' ?>"
                                       class="form-control form-control-sm text-white"
                                       style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1)"
                                       placeholder="Min">
                            </div>
                            <div class="col-6">
                                <input type="number" name="max" value="<?= $maxPrice < 99999 ? $maxPrice : '' ?>"
                                       class="form-control form-control-sm text-white"
                                       style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1)"
                                       placeholder="Max">
                            </div>
                        </div>
                        <button class="btn btn-outline-primary btn-sm w-100">Apply</button>
                        <?php if ($minPrice || $maxPrice < 99999): ?>
                        <a href="?<?= http_build_query(array_diff_key($_GET, ['min'=>1,'max'=>1,'page'=>1])) ?>"
                           class="btn btn-sm w-100 mt-1 text-muted">Clear price</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="mb-2">
                    <div class="text-muted small fw-semibold mb-2 text-uppercase" style="font-size:10px;letter-spacing:.08em">Categories</div>
                    <a href="/marketplace.php<?= $q ? "?q=".urlencode($q) : '' ?>"
                       class="d-flex align-items-center justify-content-between py-1 px-2 rounded mb-1 text-decoration-none small <?= !$cat ? 'text-primary' : 'text-muted' ?>"
                       style="<?= !$cat ? 'background:rgba(99,102,241,0.1)' : '' ?>">
                        <span>All Categories</span>
                        <span class="badge rounded-pill bg-secondary bg-opacity-50 text-muted"><?= $totalAll ?></span>
                    </a>
                    <?php foreach ($categories as $c): ?>
                    <a href="/marketplace.php?cat=<?= $c['slug'] ?><?= $type ? "&type=$type" : '' ?><?= $q ? "&q=".urlencode($q) : '' ?>"
                       class="d-flex align-items-center justify-content-between py-1 px-2 rounded mb-1 text-decoration-none small <?= $cat === $c['slug'] ? 'text-primary' : 'text-muted' ?>"
                       style="<?= $cat === $c['slug'] ? 'background:rgba(99,102,241,0.1)' : '' ?>">
                        <span><i class="bi <?= $c['icon'] ?> me-2" style="color:<?= $c['color'] ?>"></i><?= htmlspecialchars($c['name']) ?></span>
                        <span class="badge rounded-pill bg-secondary bg-opacity-50 text-muted"><?= $c['cnt'] ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>

                <?php if ($cat || $type || $q || $minPrice || ($maxPrice < 99999)): ?>
                <a href="/marketplace.php" class="btn btn-sm w-100 mt-3"
                   style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);color:#9ca3af">
                    <i class="bi bi-x-circle me-1"></i>Clear all filters
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Product Grid ── -->
        <div class="col-lg-9">

            <!-- Results bar -->
            <div class="results-bar">
                <div class="results-count">
                    <?php if ($cat || $type || $q || $minPrice || $maxPrice < 99999): ?>
                        <strong class="text-white"><?= $total ?></strong> result<?= $total !== 1 ? 's' : '' ?>
                        <?php if ($q): ?> for "<strong class="text-white"><?= htmlspecialchars($q) ?></strong>"<?php endif; ?>
                        <?php if ($currentCategory): ?> in <strong class="text-white"><?= htmlspecialchars($currentCategory['name']) ?></strong><?php endif; ?>
                    <?php else: ?>
                        Showing <strong class="text-white"><?= $total ?></strong> capsules & tools
                    <?php endif; ?>
                </div>
                <!-- Mobile filter toggle -->
                <div class="d-lg-none">
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="offcanvas" data-bs-target="#filterOffcanvas">
                        <i class="bi bi-sliders me-1"></i>Filters
                    </button>
                </div>
            </div>

            <?php if (empty($products)): ?>
            <div class="text-center py-5">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-4"
                     style="width:72px;height:72px;background:rgba(99,102,241,0.1)">
                    <i class="bi bi-search fs-3 text-primary"></i>
                </div>
                <h5 class="text-white mb-2">No capsules found</h5>
                <p class="text-muted mb-4">Try adjusting your filters or search terms</p>
                <a href="/marketplace.php" class="btn btn-outline-primary">Browse all capsules</a>
            </div>
            <?php else: ?>

            <div class="row g-3">
                <?php foreach ($products as $p):
                    // Determine type label
                    $so = (int)$p['sort_order'];
                    if ($so >= 37)      { $typeLabel = 'Platform OS'; $typeClass = 'type-tag-platform'; }
                    elseif ($so >= 17)  { $typeLabel = 'AI Tool';     $typeClass = 'type-tag-tool'; }
                    else                { $typeLabel = 'BOS Capsule';  $typeClass = 'type-tag-capsule'; }

                    // Badge
                    $badgeHtml = '';
                    if ($p['badge']) {
                        $badgeMap = ['Hot'=>'badge-hot-tag','Popular'=>'badge-popular','New'=>'badge-new-tag','Premium'=>'badge-premium','Featured'=>'badge-featured-new'];
                        $bc = $badgeMap[$p['badge']] ?? 'badge-popular';
                        $badgeHtml = "<span class='$bc'>{$p['badge']}</span>";
                    }
                    if ($p['is_featured']) {
                        $badgeHtml .= " <span class='badge-featured-new'>Featured</span>";
                    }

                    $features = json_decode($p['features'] ?? '[]', true) ?: [];
                    $cardColor = $p['cat_color'] ?? '#6366f1';
                ?>
                <div class="col-sm-6 col-xl-4">
                    <div class="product-card-new h-100">
                        <!-- Card header with gradient -->
                        <div class="pcard-header" style="background:linear-gradient(135deg,<?= $cardColor ?>18 0%,transparent 100%)">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="cat-icon-sm" style="background:<?= $cardColor ?>20;color:<?= $cardColor ?>">
                                    <i class="bi <?= $p['cat_icon'] ?? 'bi-cpu' ?>"></i>
                                </div>
                                <div class="d-flex gap-1 align-items-center flex-wrap justify-content-end">
                                    <span class="type-tag <?= $typeClass ?>"><?= $typeLabel ?></span>
                                    <?= $badgeHtml ?>
                                </div>
                            </div>
                            <h6 class="text-white fw-bold mb-1" style="font-size:15px"><?= htmlspecialchars($p['name']) ?></h6>
                            <p class="text-muted mb-0" style="font-size:12px;line-height:1.5"><?= htmlspecialchars($p['tagline']) ?></p>
                        </div>

                        <!-- Features list -->
                        <div class="pcard-body">
                            <ul class="list-unstyled mb-0" style="font-size:12px">
                                <?php foreach (array_slice($features, 0, 3) as $f): ?>
                                <li class="mb-1 d-flex align-items-start gap-2">
                                    <i class="bi bi-check-circle-fill flex-shrink-0 mt-1" style="color:#10b981;font-size:10px"></i>
                                    <span class="text-muted"><?= htmlspecialchars($f) ?></span>
                                </li>
                                <?php endforeach; ?>
                                <?php if (count($features) > 3): ?>
                                <li class="text-muted" style="font-size:11px;padding-left:18px">+<?= count($features)-3 ?> more features</li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <!-- Footer: price + CTA -->
                        <div class="pcard-footer">
                            <div>
                                <span class="price-big"><?= APP_CURRENCY ?><?= number_format($p['price_monthly'], 0) ?></span>
                                <span class="price-unit"> /mo</span>
                                <?php if ($p['price_yearly'] && $p['price_yearly'] > 0): ?>
                                <div class="text-muted" style="font-size:10px">
                                    <?= APP_CURRENCY ?><?= number_format($p['price_yearly'], 0) ?>/yr
                                    <span class="text-success ms-1">(<?= round((1 - $p['price_yearly'] / ($p['price_monthly']*12)) * 100) ?>% off)</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-2">
                                <?php if ($p['demo_url']): ?>
                                <a href="<?= htmlspecialchars($p['demo_url']) ?>" target="_blank"
                                   class="btn btn-sm rounded-3" style="background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);color:#9ca3af"
                                   title="Try demo"><i class="bi bi-play-circle"></i></a>
                                <?php endif; ?>
                                <a href="/product.php?slug=<?= $p['slug'] ?>"
                                   class="btn btn-sm btn-primary rounded-3 px-3">View</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav class="mt-5 d-flex justify-content-center">
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $page + 2);
                    if ($start > 1): ?>
                    <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>1])) ?>">1</a></li>
                    <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                    <?php endif; ?>
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                    <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$totalPages])) ?>"><?= $totalPages ?></a></li>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── CTA Section ── -->
<?php if (!Auth::check()): ?>
<section class="py-6 bg-section">
    <div class="container text-center">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-4"
             style="width:64px;height:64px;background:rgba(99,102,241,0.12)">
            <i class="bi bi-robot fs-3 text-primary"></i>
        </div>
        <h2 class="text-white fw-bold mb-3">Ready to automate your business?</h2>
        <p class="text-muted mb-4" style="max-width:500px;margin:auto">
            Start your 14-day free trial today. No credit card required.
            Pick a Capsule and your BOS Core is live in minutes.
        </p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="/register.php" class="btn btn-primary btn-lg px-5">Start Free Trial</a>
            <a href="/pricing.php" class="btn btn-outline-secondary btn-lg px-5">See Pricing</a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ── Mobile Filter Offcanvas ── -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="filterOffcanvas" style="background:#0d0d14;max-width:300px">
    <div class="offcanvas-header border-bottom border-secondary border-opacity-25">
        <h6 class="offcanvas-title text-white">Filter Capsules</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div class="mb-4">
            <div class="text-muted small fw-semibold mb-2 text-uppercase" style="font-size:10px;letter-spacing:.08em">Type</div>
            <?php foreach ([''  => 'All','capsule' => 'BOS Capsules','tool' => 'AI Tools','platform' => 'Platform OS'] as $val => $label): ?>
            <a href="?<?= http_build_query(array_merge(array_filter(['cat'=>$cat,'q'=>$q,'sort'=>$sort]), ['type'=>$val])) ?>"
               class="d-block py-1 px-2 rounded mb-1 text-decoration-none small <?= $type === $val ? 'text-primary' : 'text-muted' ?>"
               style="<?= $type === $val ? 'background:rgba(99,102,241,0.1)' : '' ?>">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="mb-4">
            <div class="text-muted small fw-semibold mb-2 text-uppercase" style="font-size:10px;letter-spacing:.08em">Category</div>
            <a href="/marketplace.php" class="d-block py-1 px-2 rounded mb-1 text-decoration-none small <?= !$cat ? 'text-primary' : 'text-muted' ?>">All</a>
            <?php foreach ($categories as $c): ?>
            <a href="?cat=<?= $c['slug'] ?><?= $type ? "&type=$type" : '' ?>"
               class="d-block py-1 px-2 rounded mb-1 text-decoration-none small <?= $cat === $c['slug'] ? 'text-primary' : 'text-muted' ?>">
                <i class="bi <?= $c['icon'] ?> me-2" style="color:<?= $c['color'] ?>"></i><?= htmlspecialchars($c['name']) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="mb-4">
            <div class="text-muted small fw-semibold mb-2 text-uppercase" style="font-size:10px;letter-spacing:.08em">Sort By</div>
            <?php foreach ($sorts as $val => $label): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['sort'=>$val,'page'=>1])) ?>"
               class="d-block py-1 px-2 rounded mb-1 text-decoration-none small <?= $sort === $val ? 'text-primary' : 'text-muted' ?>">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
