<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pageTitle = 'Marketplace - Browse 101 AI Agents';

// Filters
$cat     = $_GET['cat']    ?? '';
$q       = trim($_GET['q'] ?? '');
$sort    = $_GET['sort']   ?? 'featured';
$minPrice= (int)($_GET['min'] ?? 0);
$maxPrice= (int)($_GET['max'] ?? 999);
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

// Build query
$where = ['p.is_active = 1'];
$params = [];

if ($cat) {
    $where[] = 'c.slug = ?';
    $params[] = $cat;
}
if ($q) {
    $where[] = '(p.name LIKE ? OR p.tagline LIKE ? OR p.description LIKE ?)';
    $params = array_merge($params, ["%$q%", "%$q%", "%$q%"]);
}
if ($minPrice > 0) {
    $where[] = 'p.price_monthly >= ?';
    $params[] = $minPrice;
}
if ($maxPrice < 999) {
    $where[] = 'p.price_monthly <= ?';
    $params[] = $maxPrice;
}

$orderBy = match($sort) {
    'price_asc'  => 'p.price_monthly ASC',
    'price_desc' => 'p.price_monthly DESC',
    'newest'     => 'p.created_at DESC',
    'popular'    => 'p.sales_count DESC',
    default      => 'p.is_featured DESC, p.sort_order ASC',
};

$whereStr = implode(' AND ', $where);
$total = DB::fetch("SELECT COUNT(*) as n FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE $whereStr", $params)['n'];
$products = DB::fetchAll(
    "SELECT p.*, c.name as cat_name, c.icon as cat_icon, c.color as cat_color, c.slug as cat_slug
     FROM products p LEFT JOIN categories c ON p.category_id=c.id
     WHERE $whereStr ORDER BY $orderBy LIMIT $perPage OFFSET $offset",
    $params
);

$categories = DB::fetchAll('SELECT *, (SELECT COUNT(*) FROM products WHERE category_id=categories.id AND is_active=1) as cnt FROM categories ORDER BY sort_order');
$totalPages = ceil($total / $perPage);
$currentCategory = $cat ? DB::fetch('SELECT * FROM categories WHERE slug=?', [$cat]) : null;

require_once 'includes/header.php';
?>

<!-- Page Header -->
<section class="page-header py-5">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-6">
                <h1 class="fw-bold text-white mb-2">
                    <?= $currentCategory ? htmlspecialchars($currentCategory['name']) : 'AI Agent Marketplace' ?>
                </h1>
                <p class="text-muted mb-0">
                    <?= $total ?> agents available
                    <?= $q ? " matching \"<strong class='text-white'>$q</strong>\"" : '' ?>
                </p>
            </div>
            <div class="col-lg-6">
                <form method="GET" class="d-flex gap-2">
                    <?php if ($cat): ?><input type="hidden" name="cat" value="<?= htmlspecialchars($cat) ?>"><?php endif; ?>
                    <input type="search" name="q" value="<?= htmlspecialchars($q) ?>"
                           class="form-control bg-dark border-secondary text-white"
                           placeholder="Search AI agents...">
                    <button class="btn btn-primary px-4"><i class="bi bi-search"></i></button>
                </form>
            </div>
        </div>
    </div>
</section>

<div class="container py-5">
    <div class="row g-4">

        <!-- Sidebar Filters -->
        <div class="col-lg-3">
            <div class="filter-sidebar rounded-4 p-4 sticky-top" style="top:80px">
                <h6 class="text-white fw-semibold mb-3">Categories</h6>
                <ul class="list-unstyled mb-4">
                    <li class="mb-1">
                        <a href="/marketplace.php<?= $q ? "?q=$q" : '' ?>"
                           class="filter-link <?= !$cat ? 'active' : '' ?>">
                            All Categories <span class="ms-auto text-muted small"><?= DB::fetch('SELECT COUNT(*) as n FROM products WHERE is_active=1')['n'] ?></span>
                        </a>
                    </li>
                    <?php foreach ($categories as $c): ?>
                    <li class="mb-1">
                        <a href="/marketplace.php?cat=<?= $c['slug'] ?><?= $q ? "&q=$q" : '' ?>"
                           class="filter-link <?= $cat === $c['slug'] ? 'active' : '' ?>">
                            <i class="bi <?= $c['icon'] ?> me-2" style="color:<?= $c['color'] ?>"></i>
                            <?= htmlspecialchars($c['name']) ?>
                            <span class="ms-auto text-muted small"><?= $c['cnt'] ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <h6 class="text-white fw-semibold mb-3">Sort By</h6>
                <div class="d-grid gap-1 mb-4">
                    <?php $sorts = ['featured'=>'Featured','popular'=>'Most Popular','newest'=>'Newest','price_asc'=>'Price: Low to High','price_desc'=>'Price: High to Low']; ?>
                    <?php foreach ($sorts as $val => $label): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['sort'=>$val,'page'=>1])) ?>"
                       class="filter-link <?= $sort === $val ? 'active' : '' ?> small"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>

                <h6 class="text-white fw-semibold mb-3">Monthly Price</h6>
                <form method="GET">
                    <?php if ($cat): ?><input type="hidden" name="cat" value="<?= htmlspecialchars($cat) ?>"><?php endif; ?>
                    <?php if ($q): ?><input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>"><?php endif; ?>
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <input type="number" name="min" value="<?= $minPrice ?>" class="form-control form-control-sm bg-dark border-secondary text-white" placeholder="Min">
                        </div>
                        <div class="col-6">
                            <input type="number" name="max" value="<?= $maxPrice < 999 ? $maxPrice : '' ?>" class="form-control form-control-sm bg-dark border-secondary text-white" placeholder="Max">
                        </div>
                    </div>
                    <button class="btn btn-outline-primary btn-sm w-100">Apply Filter</button>
                </form>
            </div>
        </div>

        <!-- Product Grid -->
        <div class="col-lg-9">
            <?php if (empty($products)): ?>
            <div class="text-center py-5">
                <i class="bi bi-search fs-1 text-muted mb-3 d-block"></i>
                <h5 class="text-white">No agents found</h5>
                <p class="text-muted">Try adjusting your search or filters</p>
                <a href="/marketplace.php" class="btn btn-outline-primary">Clear Filters</a>
            </div>
            <?php else: ?>
            <div class="row g-4">
                <?php foreach ($products as $p): ?>
                <div class="col-sm-6 col-xl-4">
                    <div class="product-card h-100 rounded-4 overflow-hidden">
                        <div class="product-card-header p-4" style="background:linear-gradient(135deg,<?= $p['cat_color'] ?? '#6366f1' ?>22,transparent)">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="cat-icon-sm" style="background:<?= $p['cat_color'] ?? '#6366f1' ?>22;color:<?= $p['cat_color'] ?? '#6366f1' ?>">
                                    <i class="bi <?= $p['cat_icon'] ?? 'bi-cpu' ?>"></i>
                                </div>
                                <div class="d-flex gap-1">
                                    <?php if ($p['badge']): ?>
                                    <span class="badge badge-hot"><?= htmlspecialchars($p['badge']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($p['is_featured']): ?>
                                    <span class="badge bg-warning text-dark">Featured</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <h6 class="text-white fw-bold mb-1"><?= htmlspecialchars($p['name']) ?></h6>
                            <p class="text-muted" style="font-size:13px;margin-bottom:0"><?= htmlspecialchars($p['tagline']) ?></p>
                        </div>
                        <div class="product-card-body p-4">
                            <?php $features = json_decode($p['features'] ?? '[]', true); ?>
                            <ul class="list-unstyled mb-0" style="font-size:12px">
                                <?php foreach (array_slice($features, 0, 3) as $f): ?>
                                <li class="mb-1 text-muted"><i class="bi bi-check-circle-fill text-success me-2"></i><?= htmlspecialchars($f) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="d-flex align-items-center justify-content-between mt-3 pt-3 border-top border-secondary border-opacity-25">
                                <div>
                                    <span class="fw-bold text-white"><?= CURRENCY_SYMBOL ?><?= number_format($p['price_monthly'], 0) ?></span>
                                    <span class="text-muted" style="font-size:12px">/mo</span>
                                </div>
                                <div class="d-flex gap-2">
                                    <?php if ($p['demo_url']): ?>
                                    <a href="<?= htmlspecialchars($p['demo_url']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm" title="Try demo">
                                        <i class="bi bi-play-circle"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="/product.php?slug=<?= $p['slug'] ?>" class="btn btn-primary btn-sm">View</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav class="mt-5">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
