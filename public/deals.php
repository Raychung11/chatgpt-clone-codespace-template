<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';

$page_title = 'Browse Deals — SilverDeals MY';
$meta_desc  = 'Exclusive senior deals on dining, health, retail and more. Browse hundreds of verified deals for Malaysians 50+.';

// ─── Filters ──────────────────────────────────────────────────────────────
$cat_slug = trim($_GET['cat']   ?? '');
$search   = trim($_GET['q']    ?? '');
$sort     = in_array($_GET['sort']??'',['newest','popular','expiring']) ? $_GET['sort'] : 'newest';
$per_page = 12;
$page_num = max(1,(int)($_GET['page']??1));
$offset   = ($page_num-1)*$per_page;

// ─── Load categories ──────────────────────────────────────────────────────
$categories = [];
$active_cat = null;
try {
    $categories = db()->query("SELECT * FROM deal_categories WHERE is_active=1 ORDER BY sort_order")->fetchAll();
    if ($cat_slug) {
        foreach ($categories as $c) { if ($c['slug']===$cat_slug) { $active_cat=$c; break; } }
    }
} catch (PDOException) {}

// ─── Deal query ───────────────────────────────────────────────────────────
$deals      = [];
$total      = 0;
$featured   = [];

try {
    $pdo = db();

    $where  = ["d.status='active'","d.deleted_at IS NULL","(d.valid_until IS NULL OR d.valid_until>=CURDATE())"];
    $params = [];

    if ($active_cat) { $where[]="d.category_id=?"; $params[]=$active_cat['id']; }
    if ($search)     { $where[]="(d.title LIKE ? OR d.short_desc LIKE ? OR m.business_name LIKE ?)"; $params=array_merge($params,["%$search%","%$search%","%$search%"]); }

    $ws = 'WHERE '.implode(' AND ',$where);

    $orderBy = match($sort){
        'popular'  => 'd.redemption_count DESC, d.updated_at DESC',
        'expiring' => 'd.valid_until ASC, d.updated_at DESC',
        default    => 'd.updated_at DESC',
    };

    // Featured (for top of page, only on first page with no filters)
    if ($page_num===1 && !$search && !$cat_slug) {
        $st = $pdo->prepare("
            SELECT d.id,d.title,d.slug,d.short_desc,d.original_price,d.deal_price,d.discount_pct,
                   d.is_members_only,d.valid_until,d.redemption_count,
                   m.business_name AS merchant_name,m.slug AS merchant_slug,
                   dc.name AS category,dc.icon AS cat_icon,
                   di.image_path AS image
            FROM deals d
            JOIN merchants m ON m.id=d.merchant_id AND m.status='active'
            LEFT JOIN deal_categories dc ON dc.id=d.category_id
            LEFT JOIN deal_images di ON di.deal_id=d.id AND di.is_primary=1
            WHERE d.status='active' AND d.is_featured=1 AND d.deleted_at IS NULL
              AND (d.valid_until IS NULL OR d.valid_until>=CURDATE())
            ORDER BY d.updated_at DESC LIMIT 3
        ");
        $st->execute();
        $featured = $st->fetchAll();
    }

    $countSql = "SELECT COUNT(*) FROM deals d JOIN merchants m ON m.id=d.merchant_id AND m.status='active' LEFT JOIN deal_categories dc ON dc.id=d.category_id {$ws}";
    $st = $pdo->prepare($countSql); $st->execute($params); $total=(int)$st->fetchColumn();

    $st = $pdo->prepare("
        SELECT d.id,d.title,d.slug,d.short_desc,d.original_price,d.deal_price,d.discount_pct,
               d.is_members_only,d.valid_until,d.redemption_count,
               m.business_name AS merchant_name,m.slug AS merchant_slug,
               dc.name AS category,dc.icon AS cat_icon,
               di.image_path AS image
        FROM deals d
        JOIN merchants m ON m.id=d.merchant_id AND m.status='active'
        LEFT JOIN deal_categories dc ON dc.id=d.category_id
        LEFT JOIN deal_images di ON di.deal_id=d.id AND di.is_primary=1
        {$ws} ORDER BY {$orderBy} LIMIT ? OFFSET ?
    ");
    $st->execute(array_merge($params,[$per_page,$offset]));
    $deals = $st->fetchAll();

} catch (PDOException $e) { error_log('[Public deals] '.$e->getMessage()); }

$total_pages = (int)ceil($total/$per_page);
include __DIR__ . '/../inc/public_header.php';
?>

<!-- Page header -->
<div style="background:var(--orange-bg);padding:var(--space-2xl) 0 var(--space-lg);border-bottom:1px solid var(--orange-border);">
  <div class="container">
    <h1 style="margin-bottom:var(--space-sm);">🎁 <?= $active_cat ? e($active_cat['icon'].' '.$active_cat['name'].' Deals') : 'Browse All Deals' ?></h1>
    <p style="color:var(--text-muted);font-size:17px;">Curated deals for Malaysians aged 50+. All merchants verified by SilverDeals MY.</p>
  </div>
</div>

<section class="section--sm">
  <div class="container">

    <!-- Search + sort bar -->
    <form method="GET" style="margin-bottom:var(--space-lg);">
      <div style="display:flex;gap:var(--space-sm);flex-wrap:wrap;align-items:center;">
        <input type="text" name="q" class="form-control" style="flex:1;min-width:200px;max-width:400px;"
               value="<?= e($search) ?>" placeholder="Search deals, merchants…">
        <?php if ($cat_slug): ?><input type="hidden" name="cat" value="<?= e($cat_slug) ?>"><?php endif; ?>
        <select name="sort" class="form-control" style="width:180px;">
          <option value="newest"   <?= $sort==='newest'  ?'selected':'' ?>>Newest First</option>
          <option value="popular"  <?= $sort==='popular' ?'selected':'' ?>>Most Popular</option>
          <option value="expiring" <?= $sort==='expiring'?'selected':'' ?>>Expiring Soon</option>
        </select>
        <button class="btn btn--primary btn--sm">Search</button>
        <?php if ($search||$cat_slug): ?><a href="/deals.php" class="btn btn--muted btn--sm">✕ Clear</a><?php endif; ?>
      </div>
    </form>

    <!-- Categories -->
    <div class="pill-list" style="margin-bottom:var(--space-xl);">
      <a href="/deals.php" class="pill <?= !$cat_slug?'active':'' ?>">All Deals</a>
      <?php foreach ($categories as $cat): ?>
        <a href="/deals.php?cat=<?= urlencode($cat['slug']) ?>" class="pill <?= $cat_slug===$cat['slug']?'active':'' ?>">
          <?= e($cat['icon'].' '.$cat['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Featured deals -->
    <?php if (!empty($featured)): ?>
      <div style="margin-bottom:var(--space-2xl);">
        <div class="section-header" style="margin-bottom:var(--space-lg);text-align:left;">
          <div class="section-header__tag">⭐ Featured</div>
          <h2 style="font-size:24px;margin:4px 0 0;">Hand-Picked for You</h2>
        </div>
        <div class="grid grid-3">
          <?php foreach ($featured as $d): include __DIR__ . '/../inc/deal_card.php'; endforeach; ?>
        </div>
      </div>
      <hr class="divider" style="margin-bottom:var(--space-2xl);">
    <?php endif; ?>

    <!-- Results count -->
    <div class="flex-between" style="margin-bottom:var(--space-lg);">
      <div style="font-size:16px;color:var(--text-muted);">
        <?= $total ?> deal<?= $total!==1?'s':'' ?> found
        <?php if ($search): ?> for "<strong><?= e($search) ?></strong>"<?php endif; ?>
      </div>
    </div>

    <!-- Deal grid -->
    <?php if (!empty($deals)): ?>
      <div class="grid grid-3" style="margin-bottom:var(--space-xl);">
        <?php foreach ($deals as $d): include __DIR__ . '/../inc/deal_card.php'; endforeach; ?>
      </div>

      <?php if ($total_pages > 1): ?>
        <div class="pagination" style="justify-content:center;">
          <?php if ($page_num>1): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page_num-1])) ?>" class="pagination__btn">← Prev</a><?php endif; ?>
          <?php for($i=max(1,$page_num-2);$i<=min($total_pages,$page_num+2);$i++): ?>
            <a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="pagination__btn <?= $i===$page_num?'active':'' ?>"><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($page_num<$total_pages): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page_num+1])) ?>" class="pagination__btn">Next →</a><?php endif; ?>
        </div>
      <?php endif; ?>

    <?php else: ?>
      <div class="empty-state">
        <div class="empty-state__icon">🎁</div>
        <h3 class="empty-state__title">No deals found</h3>
        <p class="empty-state__text">Try a different search or category, or check back soon — new deals are added every week.</p>
        <a href="/deals.php" class="btn btn--primary">View All Deals</a>
      </div>
    <?php endif; ?>

    <!-- CTA for non-members -->
    <?php if (!auth_check()): ?>
      <div style="margin-top:var(--space-2xl);background:linear-gradient(135deg,var(--orange-primary),var(--orange-secondary));border-radius:var(--radius-xl);padding:var(--space-2xl);text-align:center;color:#fff;">
        <h3 style="color:#fff;margin-bottom:var(--space-sm);">🔒 Some deals are for members only</h3>
        <p style="opacity:.9;margin-bottom:var(--space-lg);font-size:17px;">Join free to unlock all exclusive senior deals and earn SilverPoints.</p>
        <a href="/register.php" class="btn btn--ghost btn--lg">Join Free Today →</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/../inc/public_footer.php'; ?>
