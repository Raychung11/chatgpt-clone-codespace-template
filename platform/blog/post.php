<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/seo.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header('Location: /blog/'); exit; }

$post = null;
try {
    $post = DB::fetch(
        "SELECT bp.*, u.name as author_name
         FROM blog_posts bp LEFT JOIN users u ON bp.author_id = u.id
         WHERE bp.slug = ? AND bp.status = 'published' AND bp.published_at <= NOW()",
        [$slug]
    );
} catch (Exception $e) {
    // blog_posts table may not exist yet
}

if (!$post) {
    header('HTTP/1.0 404 Not Found');
    header('Location: /blog/');
    exit;
}

// Increment views counter (best-effort, ignore failure)
try {
    DB::update('blog_posts', ['views' => ($post['views'] + 1)], 'id = ?', [$post['id']]);
} catch (Exception $e) {}

// Related posts (same category, exclude current)
$relatedPosts = [];
try {
    $relatedPosts = DB::fetchAll(
        "SELECT id, title, slug, excerpt, category, published_at
         FROM blog_posts
         WHERE status = 'published' AND published_at <= NOW() AND category = ? AND id != ?
         ORDER BY published_at DESC LIMIT 3",
        [$post['category'], $post['id']]
    );
} catch (Exception $e) {}

// SEO fields
$siteUrl   = defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'https://bizai.my';
$ogImage   = !empty($post['featured_image']) ? $post['featured_image'] : $siteUrl . '/assets/img/blog-og.png';

$pageTitle   = $post['meta_title'] ?: $post['title'] . ' | AiServe Blog';
$pageDesc    = $post['meta_description'] ?: $post['excerpt'];
$ogType      = 'article';

$extraHead  = SEO::blogPost(array_merge($post, ['author_name' => $post['author_name'] ?? 'AiServe Team']));
$extraHead .= SEO::breadcrumbs([
    ['name' => 'Home', 'url' => '/'],
    ['name' => 'Blog', 'url' => '/blog/'],
    ['name' => $post['title'], 'url' => ''],
]);
$extraHead .= SEO::organization();

// Compute reading time
$wordCount = str_word_count(strip_tags($post['content'] ?? ''));
$readMins  = max(1, (int)round($wordCount / 200));
$pubDate   = $post['published_at'] ? date('d M Y', strtotime($post['published_at'])) : '';
$authorDisplay = $post['author_name'] ?? 'AiServe Team';

// Category → color
$catColors = [
    'AI Strategy'        => '#6366f1',
    'Customer Service'   => '#06b6d4',
    'Sales'              => '#10b981',
    'HR & Operations'    => '#f59e0b',
    'Finance'            => '#ef4444',
    'Project Management' => '#8b5cf6',
];
$catColor = $catColors[$post['category'] ?? ''] ?? '#6366f1';

require_once '../includes/header.php';
?>

<!-- Breadcrumb -->
<div class="container py-3">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/" class="text-muted text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="/blog/" class="text-muted text-decoration-none">Blog</a></li>
            <li class="breadcrumb-item active text-white"><?= htmlspecialchars($post['title']) ?></li>
        </ol>
    </nav>
</div>

<!-- Main Content -->
<div class="container pb-6">
    <div class="row g-5">

        <!-- Article -->
        <div class="col-lg-8">
            <article>
                <!-- Header -->
                <header class="mb-5">
                    <span class="badge mb-3 px-3 py-2" style="background:<?= $catColor ?>22;color:<?= $catColor ?>">
                        <?= htmlspecialchars($post['category'] ?? 'General') ?>
                    </span>
                    <h1 class="fw-bold text-white lh-sm mb-3" style="font-size:2rem">
                        <?= htmlspecialchars($post['title']) ?>
                    </h1>
                    <?php if ($post['excerpt']): ?>
                    <p class="lead text-muted mb-4"><?= htmlspecialchars($post['excerpt']) ?></p>
                    <?php endif; ?>

                    <!-- Meta bar -->
                    <div class="d-flex flex-wrap align-items-center gap-3 py-3 border-top border-bottom border-secondary border-opacity-25">
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-initials sm" style="background:<?= $catColor ?>22;color:<?= $catColor ?>">
                                <?= strtoupper(substr($authorDisplay, 0, 2)) ?>
                            </div>
                            <span class="text-white small fw-semibold"><?= htmlspecialchars($authorDisplay) ?></span>
                        </div>
                        <?php if ($pubDate): ?>
                        <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i><?= $pubDate ?></span>
                        <?php endif; ?>
                        <span class="text-muted small"><i class="bi bi-clock me-1"></i><?= $readMins ?> min read</span>
                        <span class="text-muted small"><i class="bi bi-eye me-1"></i><?= number_format((int)$post['views']) ?> views</span>
                    </div>
                </header>

                <!-- Featured image -->
                <?php if ($post['featured_image']): ?>
                <div class="mb-5 rounded-4 overflow-hidden" style="max-height:420px">
                    <img src="<?= htmlspecialchars($post['featured_image'], ENT_QUOTES) ?>"
                         alt="<?= htmlspecialchars($post['title'], ENT_QUOTES) ?>"
                         class="w-100 object-fit-cover">
                </div>
                <?php endif; ?>

                <!-- Body -->
                <div class="blog-content text-muted lh-lg" style="font-size:1.05rem">
                    <?= nl2br(htmlspecialchars($post['content'] ?? '')) ?>
                </div>

                <!-- Tags -->
                <?php if ($post['tags']): ?>
                <div class="mt-5 pt-4 border-top border-secondary border-opacity-25">
                    <span class="text-muted small me-2">Tags:</span>
                    <?php foreach (array_filter(array_map('trim', explode(',', $post['tags']))) as $tag): ?>
                    <a href="/blog/?q=<?= urlencode($tag) ?>"
                       class="badge bg-secondary bg-opacity-25 text-muted text-decoration-none me-1 px-2 py-1">
                        <?= htmlspecialchars($tag) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Share buttons -->
                <div class="mt-5 pt-4 border-top border-secondary border-opacity-25">
                    <p class="text-muted small mb-3 fw-semibold">Share this article</p>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php
                        $shareUrl   = urlencode($siteUrl . '/blog/' . $post['slug']);
                        $shareTitle = urlencode($post['title']);
                        ?>
                        <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?= $shareUrl ?>&title=<?= $shareTitle ?>"
                           target="_blank" rel="noopener noreferrer"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-linkedin me-1"></i>LinkedIn
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?= $shareUrl ?>&text=<?= $shareTitle ?>"
                           target="_blank" rel="noopener noreferrer"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-twitter-x me-1"></i>Twitter
                        </a>
                        <a href="https://wa.me/?text=<?= $shareTitle ?>%20<?= $shareUrl ?>"
                           target="_blank" rel="noopener noreferrer"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-whatsapp me-1"></i>WhatsApp
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrl ?>"
                           target="_blank" rel="noopener noreferrer"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-facebook me-1"></i>Facebook
                        </a>
                    </div>
                </div>
            </article>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <div class="sticky-top" style="top:90px">

                <!-- Newsletter CTA -->
                <div class="glass-card p-4 rounded-4 mb-4">
                    <div class="mb-3">
                        <i class="bi bi-envelope-paper fs-3 text-primary"></i>
                    </div>
                    <h5 class="text-white fw-bold mb-2">Get AI Tips Weekly</h5>
                    <p class="text-muted small mb-3">Join 2,000+ Malaysian SME owners getting actionable AI automation strategies every week.</p>
                    <form action="/contact.php" method="GET" class="d-flex flex-column gap-2">
                        <input type="email" name="email" class="form-control bg-dark border-secondary text-white"
                               placeholder="your@email.com" required>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-send me-2"></i>Subscribe Free
                        </button>
                    </form>
                    <p class="text-muted" style="font-size:11px;margin-top:8px">No spam. Unsubscribe anytime.</p>
                </div>

                <!-- Related Posts -->
                <?php if (!empty($relatedPosts)): ?>
                <div class="glass-card p-4 rounded-4">
                    <h6 class="text-white fw-bold mb-3">Related Articles</h6>
                    <?php foreach ($relatedPosts as $related): ?>
                    <?php $rDate = $related['published_at'] ? date('d M Y', strtotime($related['published_at'])) : ''; ?>
                    <a href="/blog/<?= htmlspecialchars($related['slug'], ENT_QUOTES) ?>"
                       class="d-block text-decoration-none mb-3 pb-3 border-bottom border-secondary border-opacity-25">
                        <span class="badge mb-1 px-2 py-1" style="background:<?= $catColor ?>22;color:<?= $catColor ?>;font-size:10px">
                            <?= htmlspecialchars($related['category'] ?? '') ?>
                        </span>
                        <div class="text-white small fw-semibold lh-sm mb-1">
                            <?= htmlspecialchars($related['title']) ?>
                        </div>
                        <?php if ($rDate): ?>
                        <div class="text-muted" style="font-size:11px"><?= $rDate ?></div>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                    <a href="/blog/?cat=<?= urlencode($post['category'] ?? '') ?>" class="text-primary small text-decoration-none">
                        More in <?= htmlspecialchars($post['category'] ?? 'this category') ?> <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                <?php endif; ?>

                <!-- CTA Banner -->
                <div class="glass-card p-4 rounded-4 mt-4" style="background:linear-gradient(135deg,<?= $catColor ?>11,transparent)">
                    <h6 class="text-white fw-bold mb-2">Ready to Automate?</h6>
                    <p class="text-muted small mb-3">Deploy AI Capsules in your business. Start with a free 14-day trial — no credit card needed.</p>
                    <a href="/register.php" class="btn btn-primary btn-sm w-100">
                        Start Free Trial <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>

            </div>
        </div>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
