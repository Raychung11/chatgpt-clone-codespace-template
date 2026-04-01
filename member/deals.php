<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
auth_require(ROLE_MEMBER);

$user    = auth_user();
$page_title = 'Browse Deals — SilverDeals MY';
$active_nav = 'deals';

// ─── Filters ──────────────────────────────────────────────────────────────
$cat_slug = trim($_GET['cat']  ?? '');
$search   = trim($_GET['q']   ?? '');
$sort     = in_array($_GET['sort'] ?? '', ['newest','popular','expiring','saved']) ? $_GET['sort'] : 'newest';
$per_page = 12;
$page_num = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page_num - 1) * $per_page;

// ─── Member profile & membership card ────────────────────────────────────
$member_profile = null;
$member_card    = null;
try {
    $stmt = db()->prepare("SELECT mp.*, pw.balance AS points_balance FROM member_profiles mp LEFT JOIN points_wallets pw ON pw.user_id=mp.user_id WHERE mp.user_id=? LIMIT 1");
    $stmt->execute([$user['id']]);
    $member_profile = $stmt->fetch();

    $stmt2 = db()->prepare("SELECT * FROM member_cards WHERE user_id=? AND is_active=1 LIMIT 1");
    $stmt2->execute([$user['id']]);
    $member_card = $stmt2->fetch();
} catch (PDOException $e) { error_log('[Member deals profile] '.$e->getMessage()); }

$is_active_member = ($user['status'] === 'active');

// ─── Claim voucher POST ───────────────────────────────────────────────────
$claim_msg = '';
$claim_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_deal_id'])) {
    csrf_abort();
    $deal_id = (int)$_POST['claim_deal_id'];

    if (!$is_active_member) {
        $claim_err = 'Your account must be active to claim deals.';
    } else {
        try {
            $pdo = db();
            $deal = $pdo->prepare("
                SELECT d.*,m.business_name AS merchant_name,m.slug AS merchant_slug
                FROM deals d JOIN merchants m ON m.id=d.merchant_id
                WHERE d.id=? AND d.status='active' AND d.deleted_at IS NULL
                  AND (d.valid_until IS NULL OR d.valid_until>=CURDATE())
                LIMIT 1
            ");
            $deal->execute([$deal_id]);
            $d = $deal->fetch();

            if (!$d) {
                $claim_err = 'Deal not found or no longer available.';
            } elseif ($d['is_members_only'] && !$member_card) {
                $claim_err = 'This deal is for verified members only. Please complete verification first.';
            } elseif ($d['points_required'] > 0 && points_balance($user['id']) < $d['points_required']) {
                $claim_err = 'You need ' . format_points($d['points_required']) . ' SilverPoints to claim this deal.';
            } else {
                // Max redemptions check
                if ($d['max_redemptions'] > 0) {
                    $cnt = (int)$pdo->prepare("SELECT COUNT(*) FROM redemptions WHERE deal_id=? AND status IN ('active','used')")->execute([$d['id']]) ? 0 : 0;
                    $st  = $pdo->prepare("SELECT COUNT(*) FROM redemptions WHERE deal_id=? AND status IN ('active','used')");
                    $st->execute([$d['id']]);
                    $cnt = (int)$st->fetchColumn();
                    if ($cnt >= $d['max_redemptions']) {
                        $claim_err = 'This deal has reached its maximum redemptions.';
                    }
                }

                if (!$claim_err) {
                    // Duplicate check
                    $dup = $pdo->prepare("SELECT voucher_code FROM redemptions WHERE user_id=? AND deal_id=? AND status='active' LIMIT 1");
                    $dup->execute([$user['id'], $d['id']]);
                    $existing = $dup->fetchColumn();

                    if ($existing) {
                        $claim_msg = 'You already have an active voucher for this deal: <strong>' . e($existing) . '</strong>';
                    } else {
                        // Spend points if required
                        if ($d['points_required'] > 0) {
                            if (!points_spend($user['id'], (int)$d['points_required'], 'deal_claim', 'Points used for: '.$d['title'], $d['id'])) {
                                $claim_err = 'Insufficient SilverPoints.';
                            }
                        }

                        if (!$claim_err) {
                            $voucher_code = generate_voucher_code();
                            $expires_at   = $d['valid_until'] ?? null;

                            $pdo->prepare("
                                INSERT INTO redemptions (user_id,deal_id,merchant_id,voucher_code,status,points_used,expires_at,created_at)
                                VALUES (?,?,?,?,'active',?,?,NOW())
                            ")->execute([$user['id'], $d['id'], $d['merchant_id'], $voucher_code, $d['points_required'], $expires_at]);

                            $red_id = (int)$pdo->lastInsertId();

                            notify($user['id'],
                                'Voucher Claimed! 🎉',
                                'Your voucher for "' . $d['title'] . '" is ready. Code: ' . $voucher_code,
                                'reward', $red_id, 'redemption');

                            $pdo->prepare("INSERT INTO audit_logs(user_id,action,target_type,target_id,ip_address) VALUES(?,'member.claim_deal','deal',?,?)")
                                ->execute([$user['id'], $d['id'], $_SERVER['REMOTE_ADDR'] ?? null]);

                            $claim_msg = 'Voucher claimed! Your code: <strong>' . e($voucher_code) . '</strong> — <a href="/member/redemptions.php">View My Vouchers</a>';
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            error_log('[Member claim deal] '.$e->getMessage());
            $claim_err = 'Failed to claim deal. Please try again.';
        }
    }
}

// ─── Load categories ──────────────────────────────────────────────────────
$categories = [];
$active_cat  = null;
try {
    $categories = db()->query("SELECT * FROM deal_categories WHERE is_active=1 ORDER BY sort_order")->fetchAll();
    if ($cat_slug) {
        foreach ($categories as $c) { if ($c['slug'] === $cat_slug) { $active_cat = $c; break; } }
    }
} catch (PDOException) {}

// ─── Member's already-claimed deal IDs (for "Claimed" badge) ─────────────
$claimed_ids = [];
try {
    $st = db()->prepare("SELECT DISTINCT deal_id FROM redemptions WHERE user_id=? AND status IN ('active','used')");
    $st->execute([$user['id']]);
    $claimed_ids = array_column($st->fetchAll(), 'deal_id');
} catch (PDOException) {}

// ─── Deal query ───────────────────────────────────────────────────────────
$deals = []; $total = 0; $featured = [];
try {
    $pdo = db();

    $where  = ["d.status='active'","d.deleted_at IS NULL","(d.valid_until IS NULL OR d.valid_until>=CURDATE())"];
    $params = [];

    if ($active_cat) { $where[] = "d.category_id=?"; $params[] = $active_cat['id']; }
    if ($search)     {
        $where[] = "(d.title LIKE ? OR d.short_desc LIKE ? OR m.business_name LIKE ?)";
        $params  = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
    }

    $ws = 'WHERE ' . implode(' AND ', $where);

    $orderBy = match($sort) {
        'popular'  => 'd.redemption_count DESC, d.updated_at DESC',
        'expiring' => 'd.valid_until ASC, d.updated_at DESC',
        default    => 'd.updated_at DESC',
    };

    // Featured — only page 1, no filters
    if ($page_num === 1 && !$search && !$cat_slug) {
        $st = $pdo->prepare("
            SELECT d.id,d.title,d.slug,d.short_desc,d.original_price,d.deal_price,d.discount_pct,
                   d.is_members_only,d.valid_until,d.redemption_count,d.points_required,
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
    $st = $pdo->prepare($countSql); $st->execute($params); $total = (int)$st->fetchColumn();

    $st = $pdo->prepare("
        SELECT d.id,d.title,d.slug,d.short_desc,d.original_price,d.deal_price,d.discount_pct,
               d.is_members_only,d.valid_until,d.redemption_count,d.points_required,
               m.business_name AS merchant_name,m.slug AS merchant_slug,
               dc.name AS category,dc.icon AS cat_icon,
               di.image_path AS image
        FROM deals d
        JOIN merchants m ON m.id=d.merchant_id AND m.status='active'
        LEFT JOIN deal_categories dc ON dc.id=d.category_id
        LEFT JOIN deal_images di ON di.deal_id=d.id AND di.is_primary=1
        {$ws} ORDER BY {$orderBy} LIMIT ? OFFSET ?
    ");
    $st->execute(array_merge($params, [$per_page, $offset]));
    $deals = $st->fetchAll();

} catch (PDOException $e) { error_log('[Member deals] '.$e->getMessage()); }

$total_pages = (int)ceil($total / $per_page);
$points_balance = (int)($member_profile['points_balance'] ?? 0);

include __DIR__ . '/../inc/member_layout.php';
?>

<div style="margin-bottom:var(--space-xl);">
  <h2 style="margin-bottom:var(--space-xs);">🎁 Browse Deals</h2>
  <p style="color:var(--text-muted);font-size:16px;">Exclusive deals curated for Malaysian seniors. Your balance: <strong style="color:var(--orange-primary);"><?= format_points($points_balance) ?> SilverPoints</strong></p>
</div>

<?php if ($claim_msg): ?>
  <div class="alert alert--success" style="margin-bottom:var(--space-lg);">
    <span class="alert__icon">✓</span><span><?= $claim_msg ?></span>
  </div>
<?php endif; ?>
<?php if ($claim_err): ?>
  <div class="alert alert--error" style="margin-bottom:var(--space-lg);">
    <span class="alert__icon">✕</span><span><?= e($claim_err) ?></span>
  </div>
<?php endif; ?>

<?php if (!$is_active_member): ?>
  <div class="alert alert--warning" style="margin-bottom:var(--space-xl);">
    <span class="alert__icon">⚠</span>
    <span>Your account is pending verification. <a href="/member/verification.php" style="font-weight:600;">Complete verification</a> to claim deals.</span>
  </div>
<?php endif; ?>

<!-- Search + Sort -->
<form method="GET" style="margin-bottom:var(--space-lg);">
  <div style="display:flex;gap:var(--space-sm);flex-wrap:wrap;align-items:center;">
    <input type="text" name="q" class="form-control" style="flex:1;min-width:200px;max-width:360px;"
           value="<?= e($search) ?>" placeholder="Search deals, merchants…">
    <?php if ($cat_slug): ?><input type="hidden" name="cat" value="<?= e($cat_slug) ?>"><?php endif; ?>
    <select name="sort" class="form-control" style="width:170px;">
      <option value="newest"   <?= $sort==='newest'  ? 'selected' : '' ?>>Newest First</option>
      <option value="popular"  <?= $sort==='popular' ? 'selected' : '' ?>>Most Popular</option>
      <option value="expiring" <?= $sort==='expiring'? 'selected' : '' ?>>Expiring Soon</option>
    </select>
    <button class="btn btn--primary btn--sm">Search</button>
    <?php if ($search || $cat_slug): ?>
      <a href="/member/deals.php" class="btn btn--muted btn--sm">✕ Clear</a>
    <?php endif; ?>
  </div>
</form>

<!-- Category pills -->
<div class="pill-list" style="margin-bottom:var(--space-xl);">
  <a href="/member/deals.php" class="pill <?= !$cat_slug ? 'active' : '' ?>">All Deals</a>
  <?php foreach ($categories as $cat): ?>
    <a href="/member/deals.php?cat=<?= urlencode($cat['slug']) ?>" class="pill <?= $cat_slug===$cat['slug'] ? 'active' : '' ?>">
      <?= e($cat['icon'].' '.$cat['name']) ?>
    </a>
  <?php endforeach; ?>
</div>

<!-- Featured -->
<?php if (!empty($featured)): ?>
  <div style="margin-bottom:var(--space-2xl);">
    <div style="margin-bottom:var(--space-md);">
      <span style="background:var(--orange-primary);color:#fff;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;letter-spacing:.05em;">⭐ FEATURED</span>
      <h3 style="margin-top:var(--space-sm);margin-bottom:0;">Hand-Picked for You</h3>
    </div>
    <div class="grid grid-3">
      <?php foreach ($featured as $d): ?>
        <?php $is_claimed = in_array($d['id'], $claimed_ids, true); ?>
        <?php include __DIR__ . '/../inc/member_deal_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <hr class="divider" style="margin-bottom:var(--space-2xl);">
<?php endif; ?>

<!-- Results count -->
<div style="margin-bottom:var(--space-lg);font-size:15px;color:var(--text-muted);">
  <?= $total ?> deal<?= $total !== 1 ? 's' : '' ?> found
  <?php if ($search): ?> for "<strong><?= e($search) ?></strong>"<?php endif; ?>
</div>

<!-- Deal grid -->
<?php if (!empty($deals)): ?>
  <div class="grid grid-3" style="margin-bottom:var(--space-xl);">
    <?php foreach ($deals as $d): ?>
      <?php $is_claimed = in_array($d['id'], $claimed_ids, true); ?>
      <?php include __DIR__ . '/../inc/member_deal_card.php'; ?>
    <?php endforeach; ?>
  </div>

  <?php if ($total_pages > 1): ?>
    <div class="pagination" style="justify-content:center;">
      <?php if ($page_num > 1): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page_num-1])) ?>" class="pagination__btn">← Prev</a><?php endif; ?>
      <?php for ($i = max(1,$page_num-2); $i <= min($total_pages,$page_num+2); $i++): ?>
        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="pagination__btn <?= $i===$page_num?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($page_num < $total_pages): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page_num+1])) ?>" class="pagination__btn">Next →</a><?php endif; ?>
    </div>
  <?php endif; ?>

<?php else: ?>
  <div class="empty-state">
    <div class="empty-state__icon">🎁</div>
    <h3 class="empty-state__title">No deals found</h3>
    <p class="empty-state__text">Try a different search or category — new deals are added every week.</p>
    <a href="/member/deals.php" class="btn btn--primary">View All Deals</a>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../inc/member_layout_end.php'; ?>
