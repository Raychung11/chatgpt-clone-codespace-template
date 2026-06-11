<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
auth_require(ROLE_MEMBER);

$user       = auth_user();
$page_title = 'Dashboard';
$active_nav = 'dashboard';

// Load member data
$profile   = null;
$wallet    = ['balance' => 0, 'lifetime_earned' => 0];
$referrals = ['total' => 0, 'pending' => 0, 'rewarded' => 0];
$recent_redemptions = [];
$recent_deals = [];

try {
    $pdo = db();

    // Profile
    $stmt = $pdo->prepare("SELECT * FROM member_profiles WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user['id']]);
    $profile = $stmt->fetch();

    // Wallet
    $stmt = $pdo->prepare("SELECT balance, lifetime_earned, lifetime_spent FROM points_wallets WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user['id']]);
    $wallet = $stmt->fetch() ?: $wallet;

    // Referral stats
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(status='pending')  AS pending,
            SUM(status='rewarded') AS rewarded
        FROM referrals WHERE referrer_id = ?
    ");
    $stmt->execute([$user['id']]);
    $referrals = $stmt->fetch() ?: $referrals;

    // Recent vouchers
    $stmt = $pdo->prepare("
        SELECT r.voucher_code, r.status, r.created_at, d.title AS deal_title, m.business_name AS merchant
        FROM redemptions r
        JOIN deals d     ON d.id = r.deal_id
        JOIN merchants m ON m.id = r.merchant_id
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC LIMIT 5
    ");
    $stmt->execute([$user['id']]);
    $recent_redemptions = $stmt->fetchAll();

    // Latest deals (active, members-only or public)
    $stmt = $pdo->prepare("
        SELECT d.id, d.title, d.slug, d.deal_price, d.discount_pct,
               m.business_name AS merchant_name,
               dc.icon AS category_icon,
               di.image_path AS image
        FROM deals d
        JOIN merchants m ON m.id = d.merchant_id
        LEFT JOIN deal_categories dc ON dc.id = d.category_id
        LEFT JOIN deal_images di     ON di.deal_id = d.id AND di.is_primary = 1
        WHERE d.status = 'active'
          AND (d.valid_until IS NULL OR d.valid_until >= CURDATE())
        ORDER BY d.updated_at DESC LIMIT 4
    ");
    $stmt->execute();
    $recent_deals = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[Member Dashboard] ' . $e->getMessage());
}

$profile_pct = 0;
if ($profile) {
    $fields = ['date_of_birth','gender','address_line1','city','state'];
    $filled = array_filter($fields, fn($f) => !empty($profile[$f]));
    $profile_pct = (int)(count($filled) / count($fields) * 100);
}

include __DIR__ . '/../inc/member_layout.php';
?>

<!-- ─── Welcome banner ──────────────────────────────────────────────────── -->
<div style="background:linear-gradient(135deg,var(--orange-primary),var(--orange-secondary));border-radius:var(--radius-lg);padding:var(--space-xl);color:#fff;margin-bottom:var(--space-xl);position:relative;overflow:hidden;">
  <div style="position:absolute;top:-30px;right:-30px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,.1);"></div>
  <div style="position:relative;z-index:1;">
    <h2 style="color:#fff;margin-bottom:4px;">Good <?= (date('H') < 12) ? 'morning' : ((date('H') < 18) ? 'afternoon' : 'evening') ?>, <?= e(explode(' ', $user['name'])[0]) ?>! 👋</h2>
    <p style="opacity:.9;margin:0;font-size:16px;">
      <?php if ($user['status'] === 'pending'): ?>
        Your account is <strong>pending review</strong>. Complete your profile to unlock all benefits.
      <?php else: ?>
        Welcome to your SilverDeals MY member dashboard.
      <?php endif; ?>
    </p>
  </div>
</div>

<!-- ─── Stats row ────────────────────────────────────────────────────────── -->
<div class="grid grid-4" style="margin-bottom:var(--space-xl);">
  <div class="stat-card">
    <div class="stat-card__number"><?= number_format((int)$wallet['balance']) ?></div>
    <div class="stat-card__label">SilverPoints Balance</div>
  </div>
  <div class="stat-card" style="border-left-color:var(--success);">
    <div class="stat-card__number" style="color:var(--success);"><?= number_format((int)$wallet['lifetime_earned']) ?></div>
    <div class="stat-card__label">Total Points Earned</div>
  </div>
  <div class="stat-card" style="border-left-color:#3B82F6;">
    <div class="stat-card__number" style="color:#3B82F6;"><?= (int)($referrals['total'] ?? 0) ?></div>
    <div class="stat-card__label">Friends Referred</div>
  </div>
  <div class="stat-card" style="border-left-color:#8B5CF6;">
    <div class="stat-card__number" style="color:#8B5CF6;"><?= count($recent_redemptions) ?></div>
    <div class="stat-card__label">Vouchers Used</div>
  </div>
</div>

<!-- ─── 2-column layout ──────────────────────────────────────────────────── -->
<div class="grid grid-2" style="margin-bottom:var(--space-xl);align-items:start;">

  <!-- Membership Card Preview -->
  <div>
    <h3 style="margin-bottom:var(--space-md);">🃏 My Membership Card</h3>
    <div class="member-card">
      <div class="member-card__tier">
        <?php
        $tier_labels = ['free'=>'🆓 Free Member','silver'=>'🥈 Silver Member','gold'=>'🥇 Gold Member'];
        echo $tier_labels[$user['status'] === 'active' ? 'free' : 'free'];
        ?>
      </div>
      <div class="member-card__name"><?= e($user['name']) ?></div>
      <div class="member-card__number">
        <?= $profile ? e($profile['member_number'] ?? 'Pending') : 'Complete your profile' ?>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:flex-end;">
        <div>
          <div class="member-card__points"><?= number_format((int)$wallet['balance']) ?></div>
          <div class="member-card__points-label">SilverPoints</div>
        </div>
        <?php if ($user['status'] === 'active'): ?>
          <div class="member-card__qr" style="display:flex;align-items:center;justify-content:center;font-size:10px;color:var(--text-muted);">
            <a href="/member/membership_card.php" style="text-decoration:none;color:inherit;text-align:center;">
              QR Code<br>→ View Full
            </a>
          </div>
        <?php else: ?>
          <div style="font-size:13px;opacity:.7;text-align:right;">
            Verify account<br>to activate QR
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div style="margin-top:var(--space-md);">
      <a href="/member/membership_card.php" class="btn btn--secondary btn--sm">View Full Card →</a>
    </div>
  </div>

  <!-- Profile Completion + Quick Actions -->
  <div>
    <h3 style="margin-bottom:var(--space-md);">🚀 Quick Actions</h3>
    <div style="display:flex;flex-direction:column;gap:var(--space-sm);">

      <?php if ($profile_pct < 100): ?>
      <div class="card" style="padding:var(--space-md);border-left:3px solid var(--warning);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-sm);">
          <span style="font-weight:600;font-size:15px;">Complete Your Profile</span>
          <span class="badge badge--warning"><?= $profile_pct ?>% done</span>
        </div>
        <div style="background:var(--border-light);border-radius:var(--radius-pill);height:8px;margin-bottom:var(--space-sm);">
          <div style="background:var(--orange-primary);height:8px;border-radius:var(--radius-pill);width:<?= $profile_pct ?>%;transition:width .5s;"></div>
        </div>
        <a href="/member/profile.php" class="btn btn--primary btn--sm">Complete Profile</a>
      </div>
      <?php endif; ?>

      <a href="/member/referrals.php" class="card" style="padding:var(--space-md);display:flex;align-items:center;gap:var(--space-md);text-decoration:none;color:var(--text-dark);">
        <span style="font-size:32px;">🤝</span>
        <div>
          <div style="font-weight:700;">Refer a Friend</div>
          <div style="font-size:14px;color:var(--text-muted);">Earn 200 points per successful referral</div>
        </div>
        <span style="margin-left:auto;color:var(--text-muted);">→</span>
      </a>

      <a href="/member/deals.php" class="card" style="padding:var(--space-md);display:flex;align-items:center;gap:var(--space-md);text-decoration:none;color:var(--text-dark);">
        <span style="font-size:32px;">🎁</span>
        <div>
          <div style="font-weight:700;">Browse Deals</div>
          <div style="font-size:14px;color:var(--text-muted);">Discover exclusive senior deals today</div>
        </div>
        <span style="margin-left:auto;color:var(--text-muted);">→</span>
      </a>

      <a href="/member/rewards.php" class="card" style="padding:var(--space-md);display:flex;align-items:center;gap:var(--space-md);text-decoration:none;color:var(--text-dark);">
        <span style="font-size:32px;">💰</span>
        <div>
          <div style="font-weight:700;">My Points Wallet</div>
          <div style="font-size:14px;color:var(--text-muted);"><?= number_format((int)$wallet['balance']) ?> pts available</div>
        </div>
        <span style="margin-left:auto;color:var(--text-muted);">→</span>
      </a>
    </div>
  </div>
</div>

<!-- ─── Latest Deals ──────────────────────────────────────────────────────── -->
<?php if (!empty($recent_deals)): ?>
<div style="margin-bottom:var(--space-xl);">
  <div class="flex-between" style="margin-bottom:var(--space-md);">
    <h3>🎁 Latest Deals For You</h3>
    <a href="/member/deals.php" class="btn btn--secondary btn--sm">View All</a>
  </div>
  <div class="grid grid-4">
    <?php foreach ($recent_deals as $deal): ?>
      <div class="card deal-card">
        <?php if ($deal['image']): ?>
          <img src="<?= e($deal['image']) ?>" alt="<?= e($deal['title']) ?>" class="card__image" style="height:140px;">
        <?php else: ?>
          <div class="card__image" style="height:140px;display:flex;align-items:center;justify-content:center;background:var(--orange-bg);font-size:36px;">
            <?= e($deal['category_icon'] ?? '🎁') ?>
          </div>
        <?php endif; ?>
        <?php if ($deal['discount_pct']): ?>
          <div class="deal-card__badge"><?= (int)$deal['discount_pct'] ?>% OFF</div>
        <?php endif; ?>
        <div class="card__body" style="padding:var(--space-md);">
          <h5 style="font-size:15px;margin-bottom:4px;"><?= e($deal['title']) ?></h5>
          <div style="font-size:13px;color:var(--text-muted);margin-bottom:var(--space-sm);"><?= e($deal['merchant_name']) ?></div>
          <?php if ($deal['deal_price']): ?>
            <div style="font-size:16px;font-weight:700;color:var(--orange-primary);"><?= format_myr((float)$deal['deal_price']) ?></div>
          <?php endif; ?>
          <a href="/deal.php?slug=<?= urlencode($deal['slug']) ?>" class="btn btn--primary btn--sm btn--full" style="margin-top:var(--space-sm);">Get Deal</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ─── Recent Vouchers ───────────────────────────────────────────────────── -->
<div>
  <div class="flex-between" style="margin-bottom:var(--space-md);">
    <h3>🎫 Recent Vouchers</h3>
    <a href="/member/redemptions.php" class="btn btn--secondary btn--sm">View All</a>
  </div>
  <?php if (!empty($recent_redemptions)): ?>
    <div class="card" style="overflow:hidden;">
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Deal</th>
              <th>Merchant</th>
              <th>Code</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_redemptions as $r): ?>
              <tr>
                <td style="font-weight:600;"><?= e($r['deal_title']) ?></td>
                <td><?= e($r['merchant']) ?></td>
                <td><code style="background:var(--bg-light);padding:4px 8px;border-radius:4px;font-size:13px;"><?= e($r['voucher_code']) ?></code></td>
                <td>
                  <span class="badge badge--<?= match($r['status']) { 'active'=>'success', 'used'=>'muted', 'expired'=>'error', default=>'muted' } ?>">
                    <?= ucfirst($r['status']) ?>
                  </span>
                </td>
                <td style="font-size:14px;color:var(--text-muted);"><?= time_ago($r['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php else: ?>
    <div class="empty-state" style="padding:var(--space-2xl);">
      <div class="empty-state__icon">🎫</div>
      <h4 class="empty-state__title">No vouchers yet</h4>
      <p class="empty-state__text">Browse deals and grab your first voucher!</p>
      <a href="/member/deals.php" class="btn btn--primary">Browse Deals</a>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/member_layout_end.php'; ?>
