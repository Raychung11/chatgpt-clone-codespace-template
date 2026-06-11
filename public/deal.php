<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header('Location: /public/deals.php'); exit; }

$deal = null; $merchant = null; $images = []; $related = [];
try {
    $pdo  = db();
    $stmt = $pdo->prepare("
        SELECT d.*,
               m.business_name AS merchant_name, m.slug AS merchant_slug, m.logo AS merchant_logo,
               m.description AS merchant_desc, m.phone AS merchant_phone,
               dc.name AS category, dc.icon AS cat_icon, dc.slug AS cat_slug
        FROM deals d
        JOIN merchants m ON m.id=d.merchant_id
        LEFT JOIN deal_categories dc ON dc.id=d.category_id
        WHERE d.slug=? AND d.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$slug]);
    $deal = $stmt->fetch();

    if (!$deal) { header('HTTP/1.1 404 Not Found'); include __DIR__.'/errors/404.php'; exit; }

    // Gate: members-only deals require login
    if ($deal['status'] !== 'active') {
        if (!auth_check() || !in_array($_SESSION['user_role']??'', [ROLE_ADMIN,ROLE_SUPERADMIN])) {
            auth_set_flash('info','This deal is no longer available.');
            redirect('/deals.php');
        }
    }

    $stmt = $pdo->prepare("SELECT image_path,is_primary FROM deal_images WHERE deal_id=? ORDER BY is_primary DESC,sort_order ASC");
    $stmt->execute([$deal['id']]);
    $images = $stmt->fetchAll();

    // Related deals
    $stmt = $pdo->prepare("
        SELECT d.id,d.title,d.slug,d.deal_price,d.discount_pct,m.business_name AS merchant_name,
               dc.icon AS cat_icon,di.image_path AS image
        FROM deals d
        JOIN merchants m ON m.id=d.merchant_id AND m.status='active'
        LEFT JOIN deal_categories dc ON dc.id=d.category_id
        LEFT JOIN deal_images di ON di.deal_id=d.id AND di.is_primary=1
        WHERE d.status='active' AND d.id!=? AND d.category_id=? AND d.deleted_at IS NULL
          AND (d.valid_until IS NULL OR d.valid_until>=CURDATE())
        LIMIT 3
    ");
    $stmt->execute([$deal['id'],$deal['category_id']??0]);
    $related = $stmt->fetchAll();
} catch (PDOException $e) { error_log('[Deal detail] '.$e->getMessage()); }

$page_title = $deal['title'];
$meta_desc  = $deal['short_desc'] ?? 'Exclusive senior deal at ' . $deal['merchant_name'];

// ─── Handle claim voucher POST ────────────────────────────────────────────
$claim_success  = false;
$claim_voucher  = '';
$claim_error    = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['claim_deal'])) {
    csrf_abort();
    if (!auth_check()) {
        auth_set_flash('info','Please login or register to claim this deal.');
        redirect('/login.php?redirect='.urlencode($_SERVER['REQUEST_URI']));
    }
    if ($_SESSION['user_role'] !== ROLE_MEMBER) {
        $claim_error = 'Only member accounts can claim deals.';
    } elseif ($_SESSION['user_status'] !== 'active') {
        $claim_error = 'Your membership must be active and verified to claim deals. Please complete verification.';
    } else {
        try {
            $pdo = db();
            // Check members-only gate
            if ($deal['is_members_only']) {
                $stmt = $pdo->prepare("SELECT id FROM member_cards WHERE user_id=? AND is_active=1 LIMIT 1");
                $stmt->execute([$_SESSION['user_id']]);
                if (!$stmt->fetch()) { $claim_error = 'This deal is for verified members only.'; }
            }

            // Check points requirement
            if (!$claim_error && $deal['points_required'] > 0) {
                require_once __DIR__ . '/../inc/points.php';
                $bal = points_balance((int)$_SESSION['user_id']);
                if ($bal < $deal['points_required']) {
                    $claim_error = 'You need ' . number_format($deal['points_required']) . ' SilverPoints to claim this deal. You have ' . number_format($bal) . '.';
                }
            }

            // Check max redemptions
            if (!$claim_error && $deal['max_redemptions'] > 0 && $deal['redemption_count'] >= $deal['max_redemptions']) {
                $claim_error = 'Sorry, this deal has reached its maximum redemption limit.';
            }

            // Check if already claimed an active voucher for this deal
            if (!$claim_error) {
                $stmt = $pdo->prepare("SELECT voucher_code FROM redemptions WHERE user_id=? AND deal_id=? AND status='active' LIMIT 1");
                $stmt->execute([$_SESSION['user_id'],$deal['id']]);
                $existing = $stmt->fetch();
                if ($existing) {
                    $claim_success = true;
                    $claim_voucher = $existing['voucher_code'];
                }
            }

            if (!$claim_error && !$claim_success) {
                // Deduct points if required
                if ($deal['points_required'] > 0) {
                    require_once __DIR__ . '/../inc/points.php';
                    $ok = points_spend((int)$_SESSION['user_id'], (int)$deal['points_required'], 'redemption',
                        'Points used for deal: '.$deal['title'], (int)$deal['id']);
                    if (!$ok) { $claim_error = 'Insufficient points.'; }
                }

                if (!$claim_error) {
                    $voucher   = generate_voucher_code();
                    $expiresAt = $deal['valid_until'] ? date('Y-m-d 23:59:59', strtotime($deal['valid_until'])) : null;

                    $pdo->prepare("
                        INSERT INTO redemptions (user_id,deal_id,merchant_id,voucher_code,status,expires_at)
                        VALUES (?,?,?,?,'active',?)
                    ")->execute([$_SESSION['user_id'],$deal['id'],$deal['merchant_id'],$voucher,$expiresAt]);

                    require_once __DIR__ . '/../inc/points.php';
                    notify((int)$_SESSION['user_id'],'Deal Claimed! 🎁',
                        'You claimed "' . $deal['title'] . '". Show your voucher code at ' . $deal['merchant_name'] . '.',
                        'deal',(int)$deal['id'],'deal');

                    $pdo->prepare("INSERT INTO audit_logs(user_id,action,target_type,target_id,ip_address) VALUES(?,'member.claim_deal','deal',?,?)")
                        ->execute([$_SESSION['user_id'],$deal['id'],$_SERVER['REMOTE_ADDR']??null]);

                    $claim_success = true;
                    $claim_voucher = $voucher;
                }
            }
        } catch (PDOException $e) {
            error_log('[Claim deal] '.$e->getMessage());
            $claim_error = 'Failed to claim deal. Please try again.';
        }
    }
}

include __DIR__ . '/../inc/public_header.php';
?>

<section class="section--sm">
  <div class="container">
    <!-- Breadcrumb -->
    <div style="font-size:14px;color:var(--text-muted);margin-bottom:var(--space-lg);">
      <a href="/deals.php">Deals</a>
      <?php if ($deal['category']): ?> → <a href="/deals.php?cat=<?= urlencode($deal['cat_slug']) ?>"><?= e($deal['cat_icon'].' '.$deal['category']) ?></a><?php endif; ?>
      → <span style="color:var(--text-dark);"><?= e($deal['title']) ?></span>
    </div>

    <div class="grid grid-2" style="align-items:start;gap:var(--space-2xl);">

      <!-- Left: images + merchant -->
      <div>
        <!-- Hero image -->
        <div style="border-radius:var(--radius-xl);overflow:hidden;box-shadow:var(--shadow-lg);margin-bottom:var(--space-md);">
          <?php $hero = $images[0]['image_path'] ?? null; ?>
          <?php if ($hero): ?>
            <img src="<?= e($hero) ?>" alt="<?= e($deal['title']) ?>" style="width:100%;max-height:400px;object-fit:cover;">
          <?php else: ?>
            <div style="height:280px;background:var(--orange-bg);display:flex;align-items:center;justify-content:center;font-size:80px;"><?= e($deal['cat_icon'] ?? '🎁') ?></div>
          <?php endif; ?>
        </div>
        <!-- Thumbnails -->
        <?php if (count($images) > 1): ?>
          <div style="display:flex;gap:var(--space-sm);flex-wrap:wrap;margin-bottom:var(--space-lg);">
            <?php foreach (array_slice($images,1,4) as $img): ?>
              <img src="<?= e($img['image_path']) ?>" alt="" style="width:80px;height:60px;object-fit:cover;border-radius:var(--radius-sm);cursor:pointer;border:2px solid var(--border-light);"
                   onclick="document.querySelector('.hero-img img').src=this.src">
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- Merchant card -->
        <div class="card" style="padding:var(--space-lg);">
          <div style="display:flex;align-items:center;gap:var(--space-md);margin-bottom:var(--space-md);">
            <?php if ($deal['merchant_logo']): ?>
              <img src="<?= e($deal['merchant_logo']) ?>" alt="logo" style="width:52px;height:52px;object-fit:cover;border-radius:var(--radius-md);">
            <?php else: ?>
              <div style="width:52px;height:52px;background:var(--orange-bg);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:800;color:var(--orange-primary);">🏪</div>
            <?php endif; ?>
            <div>
              <div style="font-size:18px;font-weight:700;"><?= e($deal['merchant_name']) ?></div>
              <?php if ($deal['category']): ?><div style="font-size:14px;color:var(--text-muted);"><?= e($deal['cat_icon'].' '.$deal['category']) ?></div><?php endif; ?>
            </div>
          </div>
          <?php if ($deal['merchant_desc']): ?>
            <p style="font-size:15px;color:var(--text-muted);margin-bottom:var(--space-md);"><?= e($deal['merchant_desc']) ?></p>
          <?php endif; ?>
          <a href="/merchants.php?slug=<?= urlencode($deal['merchant_slug']) ?>" style="font-size:14px;color:var(--orange-primary);font-weight:600;">View all deals from this merchant →</a>
        </div>
      </div>

      <!-- Right: deal info + CTA -->
      <div>
        <!-- Badges -->
        <div style="display:flex;gap:var(--space-sm);flex-wrap:wrap;margin-bottom:var(--space-md);">
          <?php if ($deal['is_members_only']): ?><span class="badge badge--orange">🔒 Members Only</span><?php endif; ?>
          <?php if ($deal['is_featured']): ?><span class="badge badge--premium">⭐ Featured</span><?php endif; ?>
          <?php if ($deal['category']): ?><span class="badge badge--muted"><?= e($deal['cat_icon'].' '.$deal['category']) ?></span><?php endif; ?>
          <?php if ($deal['valid_until'] && strtotime($deal['valid_until'])<strtotime('+7 days')): ?>
            <span class="badge badge--error">⏰ Expiring Soon</span>
          <?php endif; ?>
        </div>

        <h1 style="font-size:clamp(22px,3vw,32px);margin-bottom:var(--space-md);"><?= e($deal['title']) ?></h1>

        <!-- Price -->
        <div class="deal-card__price" style="margin-bottom:var(--space-lg);">
          <?php if ($deal['deal_price']): ?>
            <span class="price-new" style="font-size:32px;"><?= format_myr((float)$deal['deal_price']) ?></span>
            <?php if ($deal['original_price']): ?>
              <span class="price-old" style="font-size:18px;"><?= format_myr((float)$deal['original_price']) ?></span>
              <span class="badge badge--orange" style="font-size:14px;">Save <?= format_myr((float)$deal['original_price']-(float)$deal['deal_price']) ?></span>
            <?php endif; ?>
          <?php elseif ($deal['discount_pct']): ?>
            <span class="price-new" style="font-size:32px;"><?= (int)$deal['discount_pct'] ?>% Off</span>
          <?php else: ?>
            <span class="price-new" style="font-size:24px;">Members Exclusive</span>
          <?php endif; ?>
        </div>

        <!-- Short desc -->
        <?php if ($deal['short_desc']): ?>
          <p style="font-size:17px;color:var(--text-muted);margin-bottom:var(--space-lg);"><?= e($deal['short_desc']) ?></p>
        <?php endif; ?>

        <!-- Quick stats -->
        <div style="display:flex;gap:var(--space-lg);margin-bottom:var(--space-xl);padding:var(--space-md);background:var(--bg-light);border-radius:var(--radius-md);">
          <div style="text-align:center;">
            <div style="font-size:20px;font-weight:800;color:var(--orange-primary);"><?= $deal['redemption_count'] ?></div>
            <div style="font-size:12px;color:var(--text-muted);">Redeemed</div>
          </div>
          <?php if ($deal['valid_until']): ?>
          <div style="text-align:center;">
            <div style="font-size:20px;font-weight:800;"><?= date('d M', strtotime($deal['valid_until'])) ?></div>
            <div style="font-size:12px;color:var(--text-muted);">Expires</div>
          </div>
          <?php endif; ?>
          <?php if ($deal['points_required'] > 0): ?>
          <div style="text-align:center;">
            <div style="font-size:20px;font-weight:800;color:#8B5CF6;"><?= number_format($deal['points_required']) ?></div>
            <div style="font-size:12px;color:var(--text-muted);">Pts Required</div>
          </div>
          <?php endif; ?>
        </div>

        <!-- CTA / Voucher Box -->
        <?php if ($claim_error): ?>
          <div class="alert alert--error" style="margin-bottom:var(--space-lg);">
            <span class="alert__icon">✕</span><span><?= e($claim_error) ?></span>
          </div>
        <?php endif; ?>

        <?php if ($claim_success): ?>
          <div style="background:var(--success-bg);border:2px solid var(--success);border-radius:var(--radius-xl);padding:var(--space-xl);text-align:center;margin-bottom:var(--space-lg);">
            <div style="font-size:40px;margin-bottom:var(--space-sm);">🎉</div>
            <h3 style="color:#065F46;margin-bottom:var(--space-sm);">Deal Claimed!</h3>
            <p style="font-size:15px;color:#065F46;margin-bottom:var(--space-md);">Show this voucher code at <?= e($deal['merchant_name']) ?></p>
            <div style="background:#fff;border:2px dashed var(--success);border-radius:var(--radius-md);padding:var(--space-lg);margin-bottom:var(--space-md);">
              <div style="font-size:28px;font-weight:800;letter-spacing:.15em;color:var(--text-dark);"><?= e($claim_voucher) ?></div>
            </div>
            <a href="/member/redemptions.php" class="btn btn--primary btn--sm">View My Vouchers →</a>
          </div>

        <?php elseif (auth_check() && $_SESSION['user_role']===ROLE_MEMBER): ?>
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="claim_deal" value="1">
            <button type="submit" class="btn btn--primary btn--full btn--lg" style="margin-bottom:var(--space-sm);">
              🎁 <?= $deal['points_required'] > 0 ? 'Claim with ' . number_format($deal['points_required']) . ' Points' : 'Get This Deal' ?>
            </button>
            <p style="font-size:13px;color:var(--text-muted);text-align:center;">You'll receive a voucher code to show at the merchant.</p>
          </form>

        <?php else: ?>
          <div style="display:flex;flex-direction:column;gap:var(--space-sm);">
            <a href="/register.php" class="btn btn--primary btn--full btn--lg">🎉 Join Free to Claim</a>
            <a href="/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn--secondary btn--full">Already a member? Login</a>
          </div>
          <p style="font-size:13px;color:var(--text-muted);text-align:center;margin-top:var(--space-md);">Free membership. No credit card required.</p>
        <?php endif; ?>

        <!-- Terms -->
        <?php if ($deal['terms_conditions']): ?>
          <details style="margin-top:var(--space-xl);">
            <summary style="cursor:pointer;font-weight:600;font-size:15px;color:var(--text-muted);">📋 Terms &amp; Conditions</summary>
            <div style="margin-top:var(--space-md);font-size:14px;color:var(--text-muted);line-height:1.7;background:var(--bg-light);padding:var(--space-md);border-radius:var(--radius-md);">
              <?= nl2br(e($deal['terms_conditions'])) ?>
            </div>
          </details>
        <?php endif; ?>
      </div>
    </div>

    <!-- Full description -->
    <?php if ($deal['description']): ?>
      <div class="card" style="padding:var(--space-xl);margin-top:var(--space-xl);">
        <h3 style="margin-bottom:var(--space-lg);">About This Deal</h3>
        <div style="font-size:17px;line-height:1.8;color:var(--text-dark);"><?= nl2br(e($deal['description'])) ?></div>
      </div>
    <?php endif; ?>

    <!-- Related deals -->
    <?php if (!empty($related)): ?>
      <div style="margin-top:var(--space-2xl);">
        <h3 style="margin-bottom:var(--space-lg);">More <?= e($deal['category']) ?> Deals</h3>
        <div class="grid grid-3">
          <?php foreach ($related as $d): include __DIR__ . '/../inc/deal_card.php'; endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/../inc/public_footer.php'; ?>
