<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'https://bizai.my';
$today = date('Y-m-d');

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

/**
 * Helper: output a <url> block.
 */
function sitemapUrl(string $loc, string $lastmod = '', string $changefreq = 'monthly', string $priority = '0.5'): void {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
    if ($lastmod) echo "    <lastmod>" . htmlspecialchars($lastmod) . "</lastmod>\n";
    echo "    <changefreq>" . htmlspecialchars($changefreq) . "</changefreq>\n";
    echo "    <priority>" . htmlspecialchars($priority) . "</priority>\n";
    echo "  </url>\n";
}

// ── Static core pages ────────────────────────────────────────────────────────
sitemapUrl("$base/",                  $today, 'weekly',   '1.0');
sitemapUrl("$base/marketplace.php",   $today, 'daily',    '0.9');
sitemapUrl("$base/pricing.php",       $today, 'weekly',   '0.8');
sitemapUrl("$base/about.php",         $today, 'monthly',  '0.7');
sitemapUrl("$base/contact.php",       $today, 'monthly',  '0.6');
sitemapUrl("$base/register.php",      $today, 'monthly',  '0.7');
sitemapUrl("$base/blog/",             $today, 'daily',    '0.8');
sitemapUrl("$base/affiliate.php",     $today, 'monthly',  '0.7');

// ── Legal pages ──────────────────────────────────────────────────────────────
sitemapUrl("$base/privacy-policy.php",    $today, 'yearly', '0.3');
sitemapUrl("$base/terms-of-service.php",  $today, 'yearly', '0.3');
sitemapUrl("$base/refund-policy.php",     $today, 'yearly', '0.3');
sitemapUrl("$base/cookie-policy.php",     $today, 'yearly', '0.3');

// ── Active products ───────────────────────────────────────────────────────────
try {
    $products = DB::fetchAll(
        "SELECT slug, updated_at FROM products WHERE is_active = 1 ORDER BY sort_order"
    );
    foreach ($products as $p) {
        $lastmod = $p['updated_at'] ? date('Y-m-d', strtotime($p['updated_at'])) : $today;
        sitemapUrl("$base/product.php?slug=" . rawurlencode($p['slug']), $lastmod, 'weekly', '0.8');
    }
} catch (Exception $e) { /* products table missing — skip */ }

// ── Categories ───────────────────────────────────────────────────────────────
try {
    $categories = DB::fetchAll("SELECT slug FROM categories ORDER BY sort_order");
    foreach ($categories as $c) {
        sitemapUrl("$base/marketplace.php?cat=" . rawurlencode($c['slug']), $today, 'weekly', '0.7');
    }
} catch (Exception $e) { /* categories table missing — skip */ }

// ── Blog posts ────────────────────────────────────────────────────────────────
try {
    $posts = DB::fetchAll(
        "SELECT slug, updated_at, published_at FROM blog_posts WHERE status = 'published' ORDER BY published_at DESC"
    );
    foreach ($posts as $post) {
        $lastmod = $post['updated_at'] ? date('Y-m-d', strtotime($post['updated_at'])) : $today;
        sitemapUrl("$base/blog/" . rawurlencode($post['slug']), $lastmod, 'monthly', '0.6');
    }
} catch (Exception $e) { /* blog_posts table doesn't exist yet — skip */ }

echo '</urlset>' . "\n";
