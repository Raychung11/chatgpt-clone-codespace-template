<?php
// ─── SilverDeals MY — Member Deal Card Partial ────────────────────────────────
// Expects: $d (deal row), $is_claimed (bool), $is_active_member (bool),
//          $points_balance (int), csrf_field() available
$detail_url = '/public/deal.php?slug=' . urlencode($d['slug']);
$can_claim  = $is_active_member && !$is_claimed
              && ($d['points_required'] <= 0 || $points_balance >= $d['points_required']);
?>
<div class="card deal-card" style="position:relative;">

  <?php if ($d['image']): ?>
    <img src="<?= e($d['image']) ?>" alt="<?= e($d['title']) ?>" class="card__image">
  <?php else: ?>
    <div class="card__image" style="display:flex;align-items:center;justify-content:center;background:var(--orange-bg);font-size:48px;">
      <?= e($d['cat_icon'] ?? '🎁') ?>
    </div>
  <?php endif; ?>

  <?php if ($is_claimed): ?>
    <span class="deal-card__badge" style="background:var(--success);">✓ Claimed</span>
  <?php elseif ($d['discount_pct']): ?>
    <span class="deal-card__badge"><?= (int)$d['discount_pct'] ?>% OFF</span>
  <?php elseif ($d['is_members_only'] ?? false): ?>
    <span class="deal-card__badge">Members</span>
  <?php endif; ?>

  <div class="card__body">
    <div style="display:flex;align-items:center;gap:var(--space-sm);margin-bottom:var(--space-sm);flex-wrap:wrap;">
      <?php if ($d['category'] ?? null): ?>
        <span class="badge badge--muted" style="font-size:12px;"><?= e($d['cat_icon'].' '.$d['category']) ?></span>
      <?php endif; ?>
      <?php if ($d['points_required'] > 0): ?>
        <span class="badge badge--orange" style="font-size:11px;">🪙 <?= format_points((int)$d['points_required']) ?> pts</span>
      <?php endif; ?>
    </div>

    <h4 class="card__title" style="font-size:16px;margin-bottom:var(--space-sm);">
      <a href="<?= $detail_url ?>" style="color:var(--text-dark);text-decoration:none;"><?= e($d['title']) ?></a>
    </h4>

    <?php if ($d['short_desc'] ?? null): ?>
      <p class="card__text" style="font-size:13px;-webkit-line-clamp:2;display:-webkit-box;-webkit-box-orient:vertical;overflow:hidden;"><?= e($d['short_desc']) ?></p>
    <?php endif; ?>

    <div class="deal-card__price" style="margin-bottom:var(--space-sm);">
      <?php if ($d['deal_price']): ?>
        <span class="price-new"><?= format_myr((float)$d['deal_price']) ?></span>
        <?php if ($d['original_price']): ?>
          <span class="price-old"><?= format_myr((float)$d['original_price']) ?></span>
        <?php endif; ?>
      <?php elseif ($d['discount_pct']): ?>
        <span class="price-new" style="font-size:18px;"><?= (int)$d['discount_pct'] ?>% Off</span>
      <?php else: ?>
        <span class="price-new" style="font-size:16px;">Members Deal</span>
      <?php endif; ?>
    </div>

    <div style="font-size:13px;color:var(--text-muted);margin-bottom:var(--space-sm);">
      <?= e($d['merchant_name']) ?>
      <?php if ($d['valid_until']): ?>
        &nbsp;·&nbsp;<span style="color:<?= strtotime($d['valid_until']) < strtotime('+7 days') ? 'var(--error)' : 'inherit' ?>;">
          <?= strtotime($d['valid_until']) < strtotime('+7 days') ? '⏰ ' : '' ?>Exp <?= date('d M', strtotime($d['valid_until'])) ?>
        </span>
      <?php endif; ?>
    </div>

    <?php if ($is_claimed): ?>
      <a href="/member/redemptions.php" class="btn btn--muted btn--sm btn--full">View My Voucher</a>
    <?php elseif (!$is_active_member): ?>
      <a href="/member/verification.php" class="btn btn--muted btn--sm btn--full">Verify to Claim</a>
    <?php elseif ($d['points_required'] > 0 && $points_balance < $d['points_required']): ?>
      <button class="btn btn--muted btn--sm btn--full" disabled title="Need <?= format_points((int)$d['points_required']) ?> pts">
        Need <?= format_points((int)$d['points_required']) ?> pts
      </button>
    <?php else: ?>
      <form method="POST" action="/member/deals.php<?= $cat_slug||$search||$sort!=='newest' ? '?'.http_build_query(array_filter(['cat'=>$cat_slug,'q'=>$search,'sort'=>$sort!=='newest'?$sort:''])) : '' ?>" style="margin:0;">
        <?= csrf_field() ?>
        <input type="hidden" name="claim_deal_id" value="<?= (int)$d['id'] ?>">
        <button type="submit" class="btn btn--primary btn--sm btn--full"
                onclick="return confirm('Claim this deal?<?= $d['points_required']>0 ? ' This will use '.format_points((int)$d['points_required']).' SilverPoints.' : '' ?>')">
          🎫 Get Voucher
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>
