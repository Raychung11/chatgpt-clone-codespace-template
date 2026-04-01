<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
auth_require(ROLE_MERCHANT);

$page_title = 'Dashboard';
$active_nav = 'dashboard';
$user = auth_user();

// Load merchant record
$merchant = null;
try {
    $stmt = db()->prepare("SELECT * FROM merchants WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user['id']]);
    $merchant = $stmt->fetch();
} catch (PDOException $e) { error_log('[Merchant dashboard] '.$e->getMessage()); }

if (!$merchant) { auth_set_flash('error','Merchant profile not found.'); redirect('/merchant/profile.php'); }

$stats = ['total_deals'=>0,'active_deals'=>0,'total_redemptions'=>0,'pending_vouchers'=>0,'used_vouchers'=>0,'total_commission'=>0.00,'pending_commission'=>0.00];
$recent_redemptions = [];
$recent_deals       = [];

try {
    $pdo = db();
    $mid = $merchant['id'];

    $stats['total_deals']       = (int)$pdo->prepare("SELECT COUNT(*) FROM deals WHERE merchant_id=?")->execute([$mid]) ? (int)$pdo->query("SELECT COUNT(*) FROM deals WHERE merchant_id={$mid}")->fetchColumn() : 0;

    // Cleaner stats queries
    $q = fn(string $sql, array $p=[]) => (function() use($pdo,$sql,$p){ $s=$pdo->prepare($sql);$s->execute($p);return $s->fetchColumn(); })();

    $stats['total_deals']        = (int)$q("SELECT COUNT(*) FROM deals WHERE merchant_id=?",[$mid]);
    $stats['active_deals']       = (int)$q("SELECT COUNT(*) FROM deals WHERE merchant_id=? AND status='active'",[$mid]);
    $stats['total_redemptions']  = (int)$q("SELECT COUNT(*) FROM redemptions WHERE merchant_id=?",[$mid]);
    $stats['pending_vouchers']   = (int)$q("SELECT COUNT(*) FROM redemptions WHERE merchant_id=? AND status='active'",[$mid]);
    $stats['used_vouchers']      = (int)$q("SELECT COUNT(*) FROM redemptions WHERE merchant_id=? AND status='used'",[$mid]);
    $stats['total_commission']   = (float)($q("SELECT COALESCE(SUM(commission_amt),0) FROM commissions WHERE merchant_id=?",[$mid]) ?: 0);
    $stats['pending_commission'] = (float)($q("SELECT COALESCE(SUM(commission_amt),0) FROM commissions WHERE merchant_id=? AND status='pending'",[$mid]) ?: 0);

    $stmt = $pdo->prepare("
        SELECT r.id, r.voucher_code, r.status, r.created_at, r.redeemed_at,
               d.title AS deal_title, u.name AS member_name, u.phone AS member_phone
        FROM redemptions r
        JOIN deals d ON d.id = r.deal_id
        JOIN users u ON u.id = r.user_id
        WHERE r.merchant_id = ?
        ORDER BY r.created_at DESC LIMIT 8
    ");
    $stmt->execute([$mid]);
    $recent_redemptions = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT id, title, status, redemption_count, deal_price, discount_pct, valid_until
        FROM deals WHERE merchant_id = ? ORDER BY updated_at DESC LIMIT 5
    ");
    $stmt->execute([$mid]);
    $recent_deals = $stmt->fetchAll();

} catch (PDOException $e) { error_log('[Merchant dashboard stats] '.$e->getMessage()); }

include __DIR__ . '/../inc/merchant_layout.php';
?>

<!-- Welcome -->
<div style="background:linear-gradient(135deg,#0F172A,#1E3A5F);border-radius:var(--radius-lg);padding:var(--space-xl);color:#fff;margin-bottom:var(--space-xl);position:relative;overflow:hidden;">
  <div style="position:absolute;top:-40px;right:-40px;width:200px;height:200px;border-radius:50%;background:rgba(255,107,0,.15);"></div>
  <div style="position:relative;z-index:1;">
    <h2 style="color:#fff;margin-bottom:4px;">Welcome back, <?= e(explode(' ',$user['name'])[0]) ?>! 🏪</h2>
    <p style="opacity:.8;margin:0;font-size:16px;"><?= e($merchant['business_name']) ?></p>
  </div>
</div>

<!-- KPIs -->
<div class="grid grid-4" style="margin-bottom:var(--space-xl);">
  <div class="stat-card">
    <div class="stat-card__number"><?= $stats['active_deals'] ?></div>
    <div class="stat-card__label">Active Deals</div>
    <div style="font-size:13px;color:var(--text-muted);margin-top:4px;"><?= $stats['total_deals'] ?> total created</div>
  </div>
  <div class="stat-card" style="border-left-color:#3B82F6;">
    <div class="stat-card__number" style="color:#3B82F6;"><?= $stats['total_redemptions'] ?></div>
    <div class="stat-card__label">Total Redemptions</div>
    <div style="font-size:13px;color:var(--text-muted);margin-top:4px;"><?= $stats['used_vouchers'] ?> used, <?= $stats['pending_vouchers'] ?> active</div>
  </div>
  <div class="stat-card" style="border-left-color:var(--success);">
    <div class="stat-card__number" style="color:var(--success);"><?= format_myr($stats['total_commission']) ?></div>
    <div class="stat-card__label">Total Commission Due</div>
    <div style="font-size:13px;color:var(--text-muted);margin-top:4px;"><?= format_myr($stats['pending_commission']) ?> pending</div>
  </div>
  <a href="/merchant/redemptions.php" class="stat-card" style="border-left-color:var(--warning);text-decoration:none;display:block;<?= $stats['pending_vouchers']>0?'border-color:var(--warning);':'' ?>">
    <div class="stat-card__number" style="color:var(--warning);"><?= $stats['pending_vouchers'] ?></div>
    <div class="stat-card__label">Vouchers to Validate</div>
    <div style="font-size:13px;color:var(--orange-primary);margin-top:4px;">Validate now →</div>
  </a>
</div>

<div class="grid grid-2" style="align-items:start;">

  <!-- Recent deals -->
  <div>
    <div class="flex-between" style="margin-bottom:var(--space-md);">
      <h3>🎁 My Deals</h3>
      <a href="/merchant/deals.php?action=create" class="btn btn--primary btn--sm">+ Create Deal</a>
    </div>
    <?php if (!empty($recent_deals)): ?>
      <div class="card" style="overflow:hidden;">
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Deal</th><th>Price</th><th>Redeemed</th><th>Status</th><th>Expires</th></tr></thead>
            <tbody>
              <?php foreach ($recent_deals as $d): ?>
                <tr>
                  <td style="font-weight:600;font-size:14px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($d['title']) ?></td>
                  <td style="font-size:13px;"><?= $d['deal_price'] ? format_myr((float)$d['deal_price']) : ($d['discount_pct'] ? $d['discount_pct'].'% OFF' : '—') ?></td>
                  <td style="font-size:13px;color:var(--orange-primary);font-weight:700;"><?= $d['redemption_count'] ?></td>
                  <td><span class="badge badge--<?= match($d['status']){'active'=>'success','pending'=>'warning','draft'=>'muted','paused'=>'warning',default=>'error'} ?>" style="font-size:11px;"><?= ucfirst($d['status']) ?></span></td>
                  <td style="font-size:12px;color:var(--text-muted);"><?= $d['valid_until'] ? date('d M Y', strtotime($d['valid_until'])) : '—' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php else: ?>
      <div class="empty-state" style="padding:var(--space-xl);">
        <div class="empty-state__icon">🎁</div>
        <h4 class="empty-state__title">No deals yet</h4>
        <p class="empty-state__text">Create your first deal to start reaching senior customers.</p>
        <a href="/merchant/deals.php?action=create" class="btn btn--primary">+ Create First Deal</a>
      </div>
    <?php endif; ?>
    <div style="margin-top:var(--space-md);"><a href="/merchant/deals.php" class="btn btn--secondary btn--sm">View All Deals</a></div>
  </div>

  <!-- Recent redemptions -->
  <div>
    <div class="flex-between" style="margin-bottom:var(--space-md);">
      <h3>🎫 Recent Vouchers</h3>
      <a href="/merchant/redemptions.php" class="btn btn--secondary btn--sm">Validate Voucher</a>
    </div>
    <?php if (!empty($recent_redemptions)): ?>
      <div class="card" style="overflow:hidden;">
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Member</th><th>Deal</th><th>Code</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach ($recent_redemptions as $r): ?>
                <tr>
                  <td style="font-size:13px;">
                    <div style="font-weight:600;"><?= e($r['member_name']) ?></div>
                    <div style="color:var(--text-muted);"><?= e($r['member_phone']) ?></div>
                  </td>
                  <td style="font-size:12px;max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($r['deal_title']) ?></td>
                  <td><code style="font-size:11px;background:var(--bg-light);padding:2px 6px;border-radius:4px;"><?= e($r['voucher_code']) ?></code></td>
                  <td><span class="badge badge--<?= match($r['status']){'active'=>'success','used'=>'muted','expired'=>'error',default=>'muted'} ?>" style="font-size:11px;"><?= ucfirst($r['status']) ?></span></td>
                  <td style="font-size:11px;color:var(--text-muted);"><?= time_ago($r['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php else: ?>
      <div class="empty-state" style="padding:var(--space-xl);">
        <div class="empty-state__icon">🎫</div>
        <h4 class="empty-state__title">No redemptions yet</h4>
        <p class="empty-state__text">Vouchers claimed by members will appear here.</p>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php include __DIR__ . '/../inc/merchant_layout_end.php'; ?>
