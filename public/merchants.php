<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';

$page_title       = 'Our Merchant Partners — SilverDeals MY';
$page_description = 'Discover our growing network of trusted merchant partners offering exclusive deals for Malaysian seniors.';

// ─── Filters ──────────────────────────────────────────────────────────────
$search   = trim($_GET['q']     ?? '');
$state    = trim($_GET['state'] ?? '');
$cat      = trim($_GET['cat']   ?? '');
$per_page = 18;
$page_num = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page_num - 1) * $per_page;

$malaysian_states = ['Johor','Kedah','Kelantan','Kuala Lumpur','Labuan','Melaka','Negeri Sembilan','Pahang','Penang','Perak','Perlis','Putrajaya','Sabah','Sarawak','Selangor','Terengganu'];

// ─── Merchants query ──────────────────────────────────────────────────────
$merchants = []; $total = 0;
try {
    $pdo    = db();
    $where  = ["m.status='active'"];
    $params = [];

    if ($search) { $where[] = "(m.business_name LIKE ? OR m.description LIKE ? OR m.category LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
    if ($state)  { $where[] = "m.state=?"; $params[] = $state; }
    if ($cat)    { $where[] = "m.category LIKE ?"; $params[] = "%$cat%"; }

    $ws = 'WHERE ' . implode(' AND ', $where);

    $st = $pdo->prepare("SELECT COUNT(*) FROM merchants m {$ws}");
    $st->execute($params); $total = (int)$st->fetchColumn();

    $st = $pdo->prepare("
        SELECT m.id,m.business_name,m.slug,m.logo,m.description,m.category,m.state,m.address,m.website,
               (SELECT COUNT(*) FROM deals d WHERE d.merchant_id=m.id AND d.status='active' AND d.deleted_at IS NULL AND (d.valid_until IS NULL OR d.valid_until>=CURDATE())) AS active_deals
        FROM merchants m
        {$ws}
        ORDER BY active_deals DESC, m.business_name ASC
        LIMIT ? OFFSET ?
    ");
    $st->execute(array_merge($params, [$per_page, $offset]));
    $merchants = $st->fetchAll();
} catch (PDOException $e) { error_log('[Public merchants] '.$e->getMessage()); }

$total_pages = (int)ceil($total / $per_page);

// ─── Category pills (from merchant categories) ────────────────────────────
$categories = [];
try {
    $st = db()->query("SELECT DISTINCT category FROM merchants WHERE status='active' AND category IS NOT NULL AND category!='' ORDER BY category");
    $categories = array_column($st->fetchAll(), 'category');
} catch (PDOException) {}

// ─── Stats for hero ───────────────────────────────────────────────────────
$stats = ['merchants' => 0, 'deals' => 0, 'states' => 0];
try {
    $pdo = db();
    $st = $pdo->query("SELECT COUNT(*) FROM merchants WHERE status='active'");       $stats['merchants'] = (int)$st->fetchColumn();
    $st = $pdo->query("SELECT COUNT(*) FROM deals WHERE status='active' AND deleted_at IS NULL AND (valid_until IS NULL OR valid_until>=CURDATE())"); $stats['deals'] = (int)$st->fetchColumn();
    $st = $pdo->query("SELECT COUNT(DISTINCT state) FROM merchants WHERE status='active' AND state IS NOT NULL AND state!=''"); $stats['states'] = (int)$st->fetchColumn();
} catch (PDOException) {}

include __DIR__ . '/../inc/public_header.php';
?>

<!-- Hero -->
<section style="background:linear-gradient(135deg,#1F2937,#374151);padding:var(--space-2xl) 0;">
  <div class="container" style="text-align:center;">
    <div style="font-size:13px;font-weight:700;letter-spacing:.1em;color:var(--orange-secondary);text-transform:uppercase;margin-bottom:var(--space-sm);">MERCHANT PARTNERS</div>
    <h1 style="color:#fff;font-size:clamp(28px,4vw,48px);margin-bottom:var(--space-md);">Trusted Businesses,<br>Exceptional Deals</h1>
    <p style="color:rgba(255,255,255,.75);font-size:17px;max-width:520px;margin:0 auto var(--space-xl);">Browse our growing network of verified merchants offering exclusive savings for Malaysian seniors.</p>

    <div style="display:flex;justify-content:center;gap:var(--space-2xl);flex-wrap:wrap;">
      <div style="text-align:center;">
        <div style="font-size:32px;font-weight:800;color:var(--orange-primary);"><?= number_format($stats['merchants']) ?>+</div>
        <div style="font-size:13px;color:rgba(255,255,255,.6);">Merchants</div>
      </div>
      <div style="text-align:center;">
        <div style="font-size:32px;font-weight:800;color:var(--orange-primary);"><?= number_format($stats['deals']) ?>+</div>
        <div style="font-size:13px;color:rgba(255,255,255,.6);">Active Deals</div>
      </div>
      <div style="text-align:center;">
        <div style="font-size:32px;font-weight:800;color:var(--orange-primary);"><?= number_format($stats['states']) ?></div>
        <div style="font-size:13px;color:rgba(255,255,255,.6);">States</div>
      </div>
    </div>
  </div>
</section>

<!-- Filters -->
<section style="background:#fff;border-bottom:1px solid var(--border-color);padding:var(--space-lg) 0;position:sticky;top:0;z-index:90;">
  <div class="container">
    <form method="GET" style="display:flex;gap:var(--space-sm);flex-wrap:wrap;align-items:center;">
      <input type="text" name="q" class="form-control" style="flex:1;min-width:200px;max-width:300px;" value="<?= e($search) ?>" placeholder="Search merchants…">
      <select name="state" class="form-control" style="width:160px;">
        <option value="">All States</option>
        <?php foreach ($malaysian_states as $s): ?>
          <option value="<?= $s ?>" <?= $state===$s?'selected':'' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn--primary btn--sm">Search</button>
      <?php if ($search || $state || $cat): ?><a href="/merchants.php" class="btn btn--muted btn--sm">✕ Clear</a><?php endif; ?>
    </form>
  </div>
</section>

<!-- Category pills -->
<?php if (!empty($categories)): ?>
  <section style="background:#fff;padding:var(--space-md) 0;border-bottom:1px solid var(--border-color);">
    <div class="container">
      <div class="pill-list">
        <a href="/merchants.php?<?= $search ? 'q='.urlencode($search).'&' : '' ?><?= $state ? 'state='.urlencode($state) : '' ?>" class="pill <?= !$cat?'active':'' ?>">All Categories</a>
        <?php foreach ($categories as $c): ?>
          <a href="?cat=<?= urlencode($c) ?><?= $search?'&q='.urlencode($search):'' ?><?= $state?'&state='.urlencode($state):'' ?>" class="pill <?= $cat===$c?'active':'' ?>"><?= e($c) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<section class="section">
  <div class="container">

    <div style="margin-bottom:var(--space-lg);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:var(--space-sm);">
      <div style="color:var(--text-muted);font-size:15px;">
        <?= $total ?> merchant<?= $total!==1?'s':'' ?>
        <?= $search ? ' for "<strong>'.e($search).'</strong>"' : '' ?>
        <?= $state ? ' in <strong>'.e($state).'</strong>' : '' ?>
      </div>
      <?php if (auth_user()): ?>
        <a href="/member/deals.php" class="btn btn--primary btn--sm">Browse Deals →</a>
      <?php else: ?>
        <a href="/register.php" class="btn btn--primary btn--sm">Join Free to Claim Deals</a>
      <?php endif; ?>
    </div>

    <?php if (!empty($merchants)): ?>
      <div class="grid grid-3" style="margin-bottom:var(--space-xl);">
        <?php foreach ($merchants as $m): ?>
          <div class="card" style="display:flex;flex-direction:column;">
            <!-- Logo -->
            <div style="height:100px;display:flex;align-items:center;justify-content:center;background:var(--bg-light);border-radius:var(--radius-md) var(--radius-md) 0 0;overflow:hidden;flex-shrink:0;">
              <?php if ($m['logo']): ?>
                <img src="<?= e($m['logo']) ?>" alt="<?= e($m['business_name']) ?>" style="max-height:80px;max-width:80%;object-fit:contain;">
              <?php else: ?>
                <div style="font-size:40px;">🏪</div>
              <?php endif; ?>
            </div>

            <div class="card__body" style="flex:1;display:flex;flex-direction:column;">
              <div style="display:flex;align-items:start;justify-content:space-between;gap:var(--space-sm);margin-bottom:var(--space-sm);">
                <h4 style="font-size:16px;margin:0;line-height:1.3;"><?= e($m['business_name']) ?></h4>
                <?php if ($m['active_deals'] > 0): ?>
                  <span class="badge badge--orange" style="font-size:11px;white-space:nowrap;flex-shrink:0;"><?= $m['active_deals'] ?> deal<?= $m['active_deals']!==1?'s':'' ?></span>
                <?php endif; ?>
              </div>

              <?php if ($m['category']): ?>
                <span class="badge badge--muted" style="font-size:12px;width:fit-content;margin-bottom:var(--space-sm);"><?= e($m['category']) ?></span>
              <?php endif; ?>

              <?php if ($m['description']): ?>
                <p style="font-size:13px;color:var(--text-muted);flex:1;overflow:hidden;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;line-clamp:2;margin-bottom:var(--space-md);"><?= e($m['description']) ?></p>
              <?php endif; ?>

              <?php if ($m['state']): ?>
                <div style="font-size:13px;color:var(--text-muted);margin-bottom:var(--space-md);">📍 <?= e($m['state']) ?></div>
              <?php endif; ?>

              <div style="margin-top:auto;display:flex;gap:var(--space-sm);">
                <?php if ($m['active_deals'] > 0): ?>
                  <a href="/deals.php?merchant=<?= urlencode($m['slug']) ?>" class="btn btn--primary btn--sm" style="flex:1;">View Deals</a>
                <?php else: ?>
                  <span class="btn btn--muted btn--sm" style="flex:1;opacity:.6;cursor:default;">No Active Deals</span>
                <?php endif; ?>
                <?php if ($m['website']): ?>
                  <a href="<?= e($m['website']) ?>" target="_blank" rel="noopener nofollow" class="btn btn--muted btn--sm">🌐</a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($total_pages > 1): ?>
        <div class="pagination" style="justify-content:center;">
          <?php if ($page_num > 1): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page_num-1])) ?>" class="pagination__btn">← Prev</a><?php endif; ?>
          <?php for ($i = max(1,$page_num-2); $i <= min($total_pages,$page_num+2); $i++): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="pagination__btn <?= $i===$page_num?'active':'' ?>"><?= $i ?></a><?php endfor; ?>
          <?php if ($page_num < $total_pages): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page_num+1])) ?>" class="pagination__btn">Next →</a><?php endif; ?>
        </div>
      <?php endif; ?>

    <?php else: ?>
      <div class="empty-state">
        <div class="empty-state__icon">🏪</div>
        <h3 class="empty-state__title">No merchants found</h3>
        <p class="empty-state__text">Try adjusting your search or filters.</p>
        <a href="/merchants.php" class="btn btn--primary">View All Merchants</a>
      </div>
    <?php endif; ?>

  </div>
</section>

<!-- Partner CTA -->
<section style="background:var(--orange-bg);padding:var(--space-2xl) 0;text-align:center;border-top:1px solid var(--border-color);">
  <div class="container" style="max-width:560px;">
    <div style="font-size:40px;margin-bottom:var(--space-md);">🤝</div>
    <h2 style="margin-bottom:var(--space-md);">Want to Reach Malaysian Seniors?</h2>
    <p style="color:var(--text-muted);font-size:16px;margin-bottom:var(--space-xl);">Join our merchant network and offer exclusive deals to thousands of engaged members aged 50+.</p>
    <div style="display:flex;justify-content:center;gap:var(--space-md);flex-wrap:wrap;">
      <a href="/join-merchant.php" class="btn btn--primary btn--lg">Become a Partner</a>
      <a href="<?= whatsapp_url('Hi, I\'m interested in becoming a SilverDeals MY merchant partner.') ?>" target="_blank" class="btn btn--muted btn--lg">💬 Talk to Us</a>
    </div>
  </div>
</section>

<?php include __DIR__ . '/../inc/public_footer.php'; ?>
