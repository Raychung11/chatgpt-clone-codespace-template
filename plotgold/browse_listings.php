<?php
require_once __DIR__ . '/inc/bootstrap.php';

// ── Filters from GET ────────────────────────────────────────────────────────
$q         = clean($_GET['q']      ?? '');
$typeSlug  = clean($_GET['type']   ?? '');
$state     = clean($_GET['state']  ?? '');
$city      = clean($_GET['city']   ?? '');
$religion  = clean($_GET['religion'] ?? '');
$parkId    = clean_int($_GET['park'] ?? 0);
$priceMin  = clean_float($_GET['price_min'] ?? 0);
$priceMax  = clean_float($_GET['price_max'] ?? 0);
$urgency   = clean($_GET['urgency'] ?? '');
$sort      = in_array($_GET['sort'] ?? '', ['price_asc','price_desc','newest','featured']) ? $_GET['sort'] : 'newest';
$page      = max(1, clean_int($_GET['page'] ?? 1));

// ── Build Query ─────────────────────────────────────────────────────────────
$where  = ['l.status = ?'];
$params = [LISTING_ACTIVE];

if ($q) {
    $where[]  = '(l.title LIKE ? OR l.public_description LIKE ? OR mp.name LIKE ? OR l.city LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($typeSlug) {
    $where[]  = 'lt.slug = ?';
    $params[] = $typeSlug;
}
if ($state) {
    $where[]  = 'l.state = ?';
    $params[] = $state;
}
if ($city) {
    $where[]  = 'l.city LIKE ?';
    $params[] = "%$city%";
}
if ($religion) {
    $where[]  = 'rc.slug = ?';
    $params[] = $religion;
}
if ($parkId) {
    $where[]  = 'l.park_id = ?';
    $params[] = $parkId;
}
if ($priceMin) {
    $where[]  = 'l.asking_price >= ?';
    $params[] = $priceMin;
}
if ($priceMax) {
    $where[]  = 'l.asking_price <= ?';
    $params[] = $priceMax;
}
if ($urgency === 'urgent') {
    $where[]  = 'l.urgency_level IN (?,?)';
    $params[] = 'urgent'; $params[] = 'immediate';
}

$orderBy = match($sort) {
    'price_asc'  => 'l.asking_price ASC',
    'price_desc' => 'l.asking_price DESC',
    'featured'   => 'l.is_featured DESC, l.listed_at DESC',
    default      => 'l.listed_at DESC',
};

$baseSql = "SELECT l.*, lt.label_en AS type_label, lt.slug AS type_slug,
                   mp.name AS park_name, rc.label_en AS religion_label,
                   lm.file_path AS primary_image
            FROM listings l
            LEFT JOIN listing_types lt    ON lt.id = l.listing_type_id
            LEFT JOIN memorial_parks mp   ON mp.id = l.park_id
            LEFT JOIN religion_categories rc ON rc.id = l.religion_id
            LEFT JOIN listing_media lm    ON lm.listing_id = l.id AND lm.is_primary = 1
            WHERE " . implode(' AND ', $where);

$countSql  = "SELECT COUNT(*) AS total FROM listings l
              LEFT JOIN listing_types lt ON lt.id = l.listing_type_id
              LEFT JOIN memorial_parks mp ON mp.id = l.park_id
              LEFT JOIN religion_categories rc ON rc.id = l.religion_id
              WHERE " . implode(' AND ', $where);
$total     = (int)(Database::fetchOne($countSql, $params)['total'] ?? 0);
$perPage   = LISTINGS_PER_PAGE;
$pages     = (int)ceil($total / $perPage);
$offset    = ($page - 1) * $perPage;
$listings  = Database::fetchAll("$baseSql ORDER BY $orderBy LIMIT $perPage OFFSET $offset", $params);

// ── Sidebar data ────────────────────────────────────────────────────────────
$listingTypes = Database::fetchAll('SELECT slug, label_en FROM listing_types WHERE is_active = 1 ORDER BY sort_order');
$religions    = Database::fetchAll('SELECT slug, label_en FROM religion_categories WHERE is_active = 1 ORDER BY sort_order');
$parks        = Database::fetchAll('SELECT id, name, city FROM memorial_parks WHERE is_active = 1 ORDER BY name');

$page_title       = __('browse.title');
$meta_description = 'Browse verified resale burial plots, family lots, and columbarium niches across Malaysia. Filter by location, price, religion, and memorial park.';
include INC_PATH . '/header.php';
include INC_PATH . '/nav.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="bg-white border-bottom">
    <div class="container py-2">
        <ol class="breadcrumb pg-breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= pg_url() ?>"><?= _e('nav.home') ?></a></li>
            <li class="breadcrumb-item active"><?= _e('browse.title') ?></li>
        </ol>
    </div>
</nav>

<div class="container py-4">
    <div class="row g-4">

        <!-- ── SIDEBAR ─────────────────────────────────────────────────── -->
        <div class="col-lg-3 d-none d-lg-block">
            <form id="searchFilterForm" method="GET" action="">
                <div class="filter-sidebar">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-700 mb-0"><?= _e('browse.filter_title') ?></h6>
                        <a href="<?= pg_url('browse_listings.php') ?>" class="btn btn-link btn-sm p-0 text-muted"><?= _e('btn.clear') ?></a>
                    </div>

                    <!-- Listing Type -->
                    <div class="filter-group-title"><?= _e('browse.filter_type') ?></div>
                    <?php foreach ($listingTypes as $t): ?>
                        <div class="form-check mb-1">
                            <input type="radio" name="type" value="<?= h($t['slug']) ?>" id="type_<?= h($t['slug']) ?>"
                                   class="form-check-input" <?= $typeSlug === $t['slug'] ? 'checked' : '' ?> onchange="this.form.submit()">
                            <label class="form-check-label small" for="type_<?= h($t['slug']) ?>"><?= h($t['label_en']) ?></label>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($typeSlug): ?>
                        <div class="form-check mb-1">
                            <input type="radio" name="type" value="" id="type_all" class="form-check-input" onchange="this.form.submit()">
                            <label class="form-check-label small text-muted" for="type_all"><?= _e('browse.all_types') ?></label>
                        </div>
                    <?php endif; ?>

                    <!-- State -->
                    <div class="filter-group-title"><?= _e('browse.filter_state') ?></div>
                    <select name="state" class="form-select form-select-sm" data-auto-submit>
                        <option value=""><?= _e('browse.any_state') ?></option>
                        <?php foreach (MY_STATES as $s): ?>
                            <option value="<?= h($s) ?>" <?= $state === $s ? 'selected' : '' ?>><?= h($s) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Religion -->
                    <div class="filter-group-title"><?= _e('browse.filter_religion') ?></div>
                    <select name="religion" class="form-select form-select-sm" data-auto-submit>
                        <option value=""><?= _e('browse.any_religion') ?></option>
                        <?php foreach ($religions as $r): ?>
                            <option value="<?= h($r['slug']) ?>" <?= $religion === $r['slug'] ? 'selected' : '' ?>><?= h($r['label_en']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Memorial Park -->
                    <div class="filter-group-title"><?= _e('browse.filter_park') ?></div>
                    <select name="park" class="form-select form-select-sm" data-auto-submit>
                        <option value=""><?= _e('browse.any_park') ?></option>
                        <?php foreach ($parks as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= $parkId === (int)$p['id'] ? 'selected' : '' ?>>
                                <?= h($p['name']) ?> (<?= h($p['city']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Price -->
                    <div class="filter-group-title"><?= _e('browse.filter_price') ?></div>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="number" name="price_min" class="form-control form-control-sm" placeholder="<?= _e('browse.filter_price_min') ?>" value="<?= $priceMin ?: '' ?>">
                        </div>
                        <div class="col-6">
                            <input type="number" name="price_max" class="form-control form-control-sm" placeholder="<?= _e('browse.filter_price_max') ?>" value="<?= $priceMax ?: '' ?>">
                        </div>
                    </div>

                    <!-- Urgency -->
                    <div class="filter-group-title"><?= _e('browse.seller_motivation') ?></div>
                    <div class="form-check mb-1">
                        <input type="checkbox" name="urgency" value="urgent" id="urgentSale" class="form-check-input"
                               <?= $urgency === 'urgent' ? 'checked' : '' ?> onchange="this.form.submit()">
                        <label class="form-check-label small" for="urgentSale">
                            <span class="badge-urgent"><?= _e('browse.filter_urgent') ?></span>
                        </label>
                    </div>

                    <input type="hidden" name="q" value="<?= h($q) ?>">
                    <input type="hidden" name="sort" value="<?= h($sort) ?>">
                    <button type="submit" class="btn btn-gold btn-sm w-100 mt-3"><?= _e('btn.filter') ?></button>
                </div>
            </form>
        </div>

        <!-- ── MAIN CONTENT ────────────────────────────────────────────── -->
        <div class="col-lg-9">

            <!-- Top bar -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h5 class="fw-600 mb-0">
                        <?= $total ?> <?= $typeSlug ? h($typeSlug) . ' ' : '' ?>Listing<?= $total !== 1 ? 's' : '' ?>
                        <?= $state ? 'in ' . h($state) : '' ?>
                    </h5>
                    <?php if ($q): ?>
                        <p class="small text-muted mb-0">Results for "<?= h($q) ?>"</p>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <!-- Mobile filter button -->
                    <button class="btn btn-outline-secondary btn-sm d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#filterOffcanvas">
                        <i class="fas fa-sliders-h me-1"></i>Filters
                    </button>
                    <select class="form-select form-select-sm" style="width:160px" onchange="window.location=this.value">
                        <?php
                        $sortBase = pg_url('browse_listings.php') . '?' . http_build_query(array_diff_key($_GET, ['sort' => '', 'page' => '']));
                        $sortOptions = ['newest' => __('browse.sort_newest'), 'price_asc' => __('browse.sort_price_asc'), 'price_desc' => __('browse.sort_price_desc'), 'featured' => __('browse.sort_featured')];
                        foreach ($sortOptions as $val => $label):
                        ?>
                            <option value="<?= h($sortBase . '&sort=' . $val) ?>" <?= $sort === $val ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Active filter pills -->
            <?php
            $activeFilters = array_filter(['Type' => $typeSlug, 'State' => $state, 'Religion' => $religion]);
            if ($activeFilters || $urgency):
            ?>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <?php foreach ($activeFilters as $label => $val): ?>
                    <span class="badge rounded-pill bg-light text-dark border">
                        <?= h($label) ?>: <?= h($val) ?>
                        <a href="<?= pg_url('browse_listings.php') ?>?<?= http_build_query(array_diff_key($_GET, [strtolower($label) => ''])) ?>" class="ms-1 text-muted text-decoration-none">&times;</a>
                    </span>
                <?php endforeach; ?>
                <?php if ($urgency): ?>
                    <span class="badge rounded-pill" style="background:#FFF5F5;color:var(--pg-danger);border:1px solid #FED7D7">
                        Urgent Only
                        <a href="<?= pg_url('browse_listings.php') ?>?<?= http_build_query(array_diff_key($_GET, ['urgency' => ''])) ?>" class="ms-1 text-decoration-none" style="color:inherit">&times;</a>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($listings): ?>
            <div class="row g-3">
                <?php foreach ($listings as $listing): ?>
                <div class="col-sm-6 col-xl-4">
                    <?php include INC_PATH . '/partials/listing_card.php'; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <div class="mt-4">
                <?= pagination_links(['rows' => $listings, 'total' => $total, 'pages' => $pages, 'page' => $page, 'per_page' => $perPage, 'has_prev' => $page > 1, 'has_next' => $page < $pages], pg_url('browse_listings.php') . '?' . http_build_query(array_diff_key($_GET, ['page' => '']))) ?>
            </div>

            <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h5 class="text-muted"><?= _e('browse.no_results') ?></h5>
                <p class="text-muted small"><?= _e('browse.no_results_hint') ?></p>
                <a href="<?= pg_url('sell_plot.php') ?>" class="btn btn-outline-gold mt-2"><?= _e('browse.be_first') ?></a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Mobile filter offcanvas -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="filterOffcanvas">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title"><?= _e('browse.filter_title') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <form method="GET" action="">
            <div class="mb-3">
                <label class="form-label fw-500"><?= _e('browse.filter_type') ?></label>
                <select name="type" class="form-select">
                    <option value=""><?= _e('browse.all_types') ?></option>
                    <?php foreach ($listingTypes as $t): ?>
                        <option value="<?= h($t['slug']) ?>" <?= $typeSlug === $t['slug'] ? 'selected' : '' ?>><?= h($t['label_en']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-500"><?= _e('browse.filter_state') ?></label>
                <select name="state" class="form-select">
                    <option value=""><?= _e('browse.any_state') ?></option>
                    <?php foreach (MY_STATES as $s): ?>
                        <option value="<?= h($s) ?>" <?= $state === $s ? 'selected' : '' ?>><?= h($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-500"><?= _e('browse.filter_religion') ?></label>
                <select name="religion" class="form-select">
                    <option value=""><?= _e('browse.any_religion') ?></option>
                    <?php foreach ($religions as $r): ?>
                        <option value="<?= h($r['slug']) ?>" <?= $religion === $r['slug'] ? 'selected' : '' ?>><?= h($r['label_en']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-500"><?= _e('browse.filter_price') ?></label>
                <div class="row g-2">
                    <div class="col-6"><input type="number" name="price_min" class="form-control" placeholder="<?= _e('browse.filter_price_min') ?>" value="<?= $priceMin ?: '' ?>"></div>
                    <div class="col-6"><input type="number" name="price_max" class="form-control" placeholder="<?= _e('browse.filter_price_max') ?>" value="<?= $priceMax ?: '' ?>"></div>
                </div>
            </div>
            <input type="hidden" name="q" value="<?= h($q) ?>">
            <input type="hidden" name="sort" value="<?= h($sort) ?>">
            <button type="submit" class="btn btn-gold w-100"><?= _e('btn.filter') ?></button>
        </form>
    </div>
</div>

<!-- Sticky compare bar -->
<div id="compareBar" class="position-fixed bottom-0 start-0 end-0 p-3 bg-navy text-white" style="display:none;z-index:999;border-top:3px solid var(--pg-gold)">
    <div class="container d-flex justify-content-between align-items-center">
        <span><strong><span id="compareCount">0</span></strong> <?= _e('browse.compare_bar', ['count' => '']) ?></span>
        <div class="d-flex gap-2">
            <button onclick="Compare.ids=[]; Compare.updateBar(); document.querySelectorAll('.compare-btn.active').forEach(b=>b.classList.remove('active'))" class="btn btn-outline-light btn-sm"><?= _e('btn.clear') ?></button>
            <button onclick="Compare.go()" class="btn btn-gold btn-sm"><?= _e('browse.compare_go') ?></button>
        </div>
    </div>
</div>

<?php include INC_PATH . '/footer.php'; ?>
