<?php
// ─── SilverDeals MY — Reusable Deal Card Partial ─────────────────────────────
// Include inside a foreach loop. Expects $d (deal row with joins).
// Works on both public/deals.php and member/deals.php
$is_member   = auth_check() && ($_SESSION['user_role'] ?? '') === ROLE_MEMBER;
$can_view    = !($d['is_members_only'] ?? false) || auth_check();
$detail_url  = '/deal.php?slug=' . urlencode($d['slug']);
?>
<div class="card deal-card">
  <?php if ($d['image']): ?>
    <img src="<?= e($d['image']) ?>" alt="<?= e($d['title']) ?>" class="card__image">
  <?php else: ?>
    <div class="card__image" style="display:flex;align-items:center;justify-content:center;background:var(--orange-bg);font-size:48px;">
      <?= e($d['cat_icon'] ?? '🎁') ?>
    </div>
  <?php endif; ?>

  <?php if ($d['discount_pct']): ?>
    <span class="deal-card__badge"><?= (int)$d['discount_pct'] ?>% OFF</span>
  <?php elseif ($d['is_members_only'] ?? false): ?>
    <span class="deal-card__badge">Members</span>
  <?php endif; ?>

  <div class="card__body">
    <div style="display:flex;align-items:center;gap:var(--space-sm);margin-bottom:var(--space-sm);">
      <?php if ($d['category'] ?? null): ?>
        <span class="badge badge--muted" style="font-size:12px;"><?= e($d['cat_icon'].' '.$d['category']) ?></span>
      <?php endif; ?>
      <?php if ($d['is_members_only'] ?? false): ?>
        <span class="badge badge--orange" style="font-size:11px;">🔒 Members</span>
      <?php endif; ?>
    </div>

    <h4 class="card__title" style="font-size:17px;margin-bottom:var(--space-sm);">
      <a href="<?= $detail_url ?>" style="color:var(--text-dark);text-decoration:none;"><?= e($d['title']) ?></a>
    </h4>

    <?php if ($d['short_desc'] ?? null): ?>
      <p class="card__text" style="font-size:14px;-webkit-line-clamp:2;display:-webkit-box;-webkit-box-orient:vertical;overflow:hidden;"><?= e($d['short_desc']) ?></p>
    <?php endif; ?>

    <div class="deal-card__price">
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

    <div style="display:flex;align-items:center;justify-content:space-between;gap:var(--space-sm);">
      <div>
        <div style="font-size:13px;color:var(--text-muted);"><?= e($d['merchant_name']) ?></div>
        <?php if ($d['valid_until']): ?>
          <div style="font-size:12px;color:<?= strtotime($d['valid_until']) < strtotime('+7 days') ? 'var(--error)' : 'var(--text-muted)' ?>;">
            <?= strtotime($d['valid_until']) < strtotime('+7 days') ? '⏰ ' : '' ?>Expires <?= date('d M Y', strtotime($d['valid_until'])) ?>
          </div>
        <?php endif; ?>
      </div>
      <a href="<?= $detail_url ?>" class="btn btn--primary btn--sm" style="flex-shrink:0;">
        <?= ($d['is_members_only'] && !auth_check()) ? '🔒 Join' : 'Get Deal' ?>
      </a>
    </div>
  </div>
</div>
