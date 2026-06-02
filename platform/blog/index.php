<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/seo.php';

$pageTitle    = 'Blog | AiServe';
$pageDesc     = 'AI insights, tips, and guides for Malaysian SMEs. Learn how to automate your business with AI Capsules.';
$pageKeywords = 'AI blog Malaysia, SME automation tips, WhatsApp AI guide, business AI strategy';

$extraHead  = SEO::breadcrumbs([
    ['name' => 'Home', 'url' => '/'],
    ['name' => 'Blog', 'url' => '/blog/'],
]);
$extraHead .= SEO::organization();

// Pagination & filters
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;
$offset  = ($page - 1) * $perPage;
$q       = trim($_GET['q'] ?? '');
$catFilter = trim($_GET['cat'] ?? '');

$posts      = [];
$total      = 0;
$categories = [];

try {
    // Build WHERE
    $where  = ["status = 'published'", "published_at <= NOW()"];
    $params = [];

    if ($q) {
        $where[]  = '(title LIKE ? OR excerpt LIKE ? OR content LIKE ?)';
        $params   = array_merge($params, ["%$q%", "%$q%", "%$q%"]);
    }
    if ($catFilter) {
        $where[]  = 'category = ?';
        $params[] = $catFilter;
    }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);

    $total = (int)(DB::fetch("SELECT COUNT(*) as n FROM blog_posts $whereSQL", $params)['n'] ?? 0);

    $posts = DB::fetchAll(
        "SELECT bp.*, u.name as author_name
         FROM blog_posts bp LEFT JOIN users u ON bp.author_id = u.id
         $whereSQL
         ORDER BY published_at DESC
         LIMIT $perPage OFFSET $offset",
        $params
    );

    // Distinct categories for filter chips
    $categories = DB::fetchAll(
        "SELECT DISTINCT category FROM blog_posts WHERE status='published' AND published_at <= NOW() ORDER BY category"
    );
} catch (Exception $e) {
    // blog_posts table not yet created — show empty state gracefully
}

$totalPages = $total > 0 ? ceil($total / $perPage) : 1;

require_once '../includes/header.php';
?>

<!-- Page Header -->
<section class="py-5 bg-section border-bottom border-secondary border-opacity-25">
    <div class="container">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/" class="text-muted text-decoration-none">Home</a></li>
                <li class="breadcrumb-item active text-white">Blog</li>
            </ol>
        </nav>
        <div class="row align-items-end g-4">
            <div class="col-lg-7">
                <h1 class="fw-bold text-white mb-2">AiServe Blog</h1>
                <p class="text-muted mb-0">AI insights, automation tips, and practical guides for Malaysian SMEs.</p>
            </div>
            <div class="col-lg-5">
                <form method="GET" action="/blog/" class="d-flex gap-2">
                    <?php if ($catFilter): ?>
                    <input type="hidden" name="cat" value="<?= htmlspecialchars($catFilter, ENT_QUOTES) ?>">
                    <?php endif; ?>
                    <input type="search" name="q" class="form-control bg-dark border-secondary text-white"
                           placeholder="Search articles..." value="<?= htmlspecialchars($q, ENT_QUOTES) ?>">
                    <button type="submit" class="btn btn-primary px-3">
                        <i class="bi bi-search"></i>
                    </button>
                </form>
            </div>
        </div>

        <?php if (!empty($categories)): ?>
        <!-- Category filter chips -->
        <div class="d-flex flex-wrap gap-2 mt-4">
            <a href="/blog/<?= $q ? '?q=' . urlencode($q) : '' ?>"
               class="badge rounded-pill px-3 py-2 text-decoration-none <?= !$catFilter ? 'bg-primary' : 'bg-secondary bg-opacity-25 text-muted' ?>">
                All Topics
            </a>
            <?php foreach ($categories as $c): ?>
            <?php $active = ($catFilter === $c['category']); ?>
            <a href="/blog/?cat=<?= urlencode($c['category']) ?><?= $q ? '&q=' . urlencode($q) : '' ?>"
               class="badge rounded-pill px-3 py-2 text-decoration-none <?= $active ? 'bg-primary' : 'bg-secondary bg-opacity-25 text-muted' ?>">
                <?= htmlspecialchars($c['category']) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Blog Posts Grid -->
<section class="py-6">
    <div class="container">
        <?php if (!empty($posts)): ?>

        <?php if ($q || $catFilter): ?>
        <p class="text-muted small mb-4">
            Showing <?= $total ?> result<?= $total !== 1 ? 's' : '' ?>
            <?= $catFilter ? ' in <strong class="text-white">' . htmlspecialchars($catFilter) . '</strong>' : '' ?>
            <?= $q ? ' for <strong class="text-white">"' . htmlspecialchars($q) . '"</strong>' : '' ?>
        </p>
        <?php endif; ?>

        <div class="row g-4">
            <?php
            // Category → gradient colors mapping
            $catGradients = [
                'AI Strategy'        => ['#6366f1', '#8b5cf6'],
                'Customer Service'   => ['#06b6d4', '#0891b2'],
                'Sales'              => ['#10b981', '#059669'],
                'HR & Operations'    => ['#f59e0b', '#d97706'],
                'Finance'            => ['#ef4444', '#dc2626'],
                'Project Management' => ['#8b5cf6', '#7c3aed'],
            ];
            foreach ($posts as $post):
                $pubDate   = $post['published_at'] ? date('d M Y', strtotime($post['published_at'])) : '';
                $wordCount = str_word_count(strip_tags($post['content'] ?? ''));
                $readMins  = max(1, (int)round($wordCount / 200));
                $catName   = $post['category'] ?? 'General';
                $colors    = $catGradients[$catName] ?? ['#6366f1', '#8b5cf6'];
                $authorDisplay = $post['author_name'] ?? 'AiServe Team';
            ?>
            <div class="col-md-6 col-lg-4">
                <a href="/blog/<?= htmlspecialchars($post['slug'], ENT_QUOTES) ?>" class="text-decoration-none d-block h-100">
                    <div class="product-card h-100 rounded-4 overflow-hidden">
                        <!-- Featured image / gradient placeholder -->
                        <div class="position-relative overflow-hidden" style="height:180px;background:linear-gradient(135deg,<?= $colors[0] ?>22,<?= $colors[1] ?>44);">
                            <?php if ($post['featured_image']): ?>
                            <img src="<?= htmlspecialchars($post['featured_image'], ENT_QUOTES) ?>"
                                 alt="<?= htmlspecialchars($post['title'], ENT_QUOTES) ?>"
                                 class="w-100 h-100 object-fit-cover">
                            <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center h-100">
                                <i class="bi bi-file-richtext text-white opacity-25" style="font-size:3rem"></i>
                            </div>
                            <?php endif; ?>
                            <span class="badge position-absolute top-0 start-0 m-3 px-2 py-1"
                                  style="background:<?= $colors[0] ?>;font-size:11px">
                                <?= htmlspecialchars($catName) ?>
                            </span>
                        </div>

                        <div class="p-4">
                            <h5 class="text-white fw-bold mb-2 lh-sm" style="font-size:1rem">
                                <?= htmlspecialchars($post['title']) ?>
                            </h5>
                            <?php if ($post['excerpt']): ?>
                            <p class="text-muted small mb-3" style="display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden">
                                <?= htmlspecialchars($post['excerpt']) ?>
                            </p>
                            <?php endif; ?>

                            <div class="d-flex align-items-center justify-content-between mt-auto pt-2 border-top border-secondary border-opacity-25">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-initials sm" style="background:<?= $colors[0] ?>33;color:<?= $colors[0] ?>;font-size:10px;width:28px;height:28px">
                                        <?= strtoupper(substr($authorDisplay, 0, 2)) ?>
                                    </div>
                                    <div>
                                        <div class="text-white" style="font-size:11px;font-weight:600"><?= htmlspecialchars($authorDisplay) ?></div>
                                        <div class="text-muted" style="font-size:10px"><?= $pubDate ?></div>
                                    </div>
                                </div>
                                <span class="text-muted" style="font-size:11px">
                                    <i class="bi bi-clock me-1"></i><?= $readMins ?> min read
                                </span>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav class="mt-5 d-flex justify-content-center" aria-label="Blog pagination">
            <ul class="pagination">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page - 1 ?><?= $catFilter ? '&cat=' . urlencode($catFilter) : '' ?><?= $q ? '&q=' . urlencode($q) : '' ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?><?= $catFilter ? '&cat=' . urlencode($catFilter) : '' ?><?= $q ? '&q=' . urlencode($q) : '' ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page + 1 ?><?= $catFilter ? '&cat=' . urlencode($catFilter) : '' ?><?= $q ? '&q=' . urlencode($q) : '' ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>

        <?php else: ?>
        <!-- Empty state -->
        <div class="text-center py-6">
            <i class="bi bi-file-richtext text-muted" style="font-size:3rem;opacity:.3"></i>
            <h4 class="text-white mt-3 mb-2">
                <?= ($q || $catFilter) ? 'No articles found' : 'Coming Soon' ?>
            </h4>
            <p class="text-muted">
                <?php if ($q || $catFilter): ?>
                    Try a different search term or browse all topics.
                    <a href="/blog/" class="text-primary text-decoration-none ms-1">Clear filters</a>
                <?php else: ?>
                    Our team is working on insightful content for Malaysian SMEs. Check back soon!
                <?php endif; ?>
            </p>
            <a href="/" class="btn btn-outline-primary mt-3">Back to Home</a>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once '../includes/footer.php'; ?>
