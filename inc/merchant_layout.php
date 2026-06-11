<?php
// ─── SilverDeals MY — Merchant Layout ───────────────────────────────────────
// Expects: $page_title, $active_nav, $merchant (array from merchants table)

$admin_user = auth_user();
$initials   = strtoupper(substr($admin_user['name'] ?? 'M', 0, 2));
$active_nav = $active_nav ?? '';

// Load merchant record for this user
$merchant = $merchant ?? null;
if (!$merchant) {
    try {
        $stmt = db()->prepare("SELECT * FROM merchants WHERE user_id = ? AND status != 'rejected' LIMIT 1");
        $stmt->execute([$admin_user['id']]);
        $merchant = $stmt->fetch() ?: null;
    } catch (PDOException) {}
}

// Pending redemptions count
$pending_redeem_count = 0;
if ($merchant) {
    try {
        $stmt = db()->prepare("SELECT COUNT(*) FROM redemptions WHERE merchant_id = ? AND status = 'active'");
        $stmt->execute([$merchant['id']]);
        $pending_redeem_count = (int)$stmt->fetchColumn();
    } catch (PDOException) {}
}
?>
<!DOCTYPE html>
<html lang="en-MY">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title ?? 'Dashboard') ?> — SilverDeals MY Merchant</title>
  <meta name="robots" content="noindex,nofollow">
  <link rel="stylesheet" href="/assets/css/theme.css">
  <style>
    .dash-sidebar { background: #0F172A; }
    .dash-sidebar__logo-role { color: #38BDF8; }
  </style>
</head>
<body>
<div class="dash-layout" id="dashLayout">
  <aside class="dash-sidebar">
    <div class="dash-sidebar__logo">
      <div class="dash-sidebar__logo-text">🟠 SilverDeals MY</div>
      <div class="dash-sidebar__logo-role">Merchant Portal</div>
      <?php if ($merchant): ?>
        <div style="font-size:13px;color:rgba(255,255,255,.5);margin-top:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($merchant['business_name']) ?></div>
      <?php endif; ?>
    </div>

    <nav class="dash-nav">
      <div class="dash-nav__group-label">Overview</div>
      <a href="/merchant/dashboard.php" class="dash-nav__link <?= $active_nav==='dashboard'?'active':'' ?>"><span class="dash-nav__icon">📊</span> Dashboard</a>

      <div class="dash-nav__group-label">Deals</div>
      <a href="/merchant/deals.php"     class="dash-nav__link <?= $active_nav==='deals'?'active':'' ?>"><span class="dash-nav__icon">🎁</span> My Deals</a>
      <a href="/merchant/deals.php?action=create" class="dash-nav__link <?= $active_nav==='create_deal'?'active':'' ?>"><span class="dash-nav__icon">➕</span> Create Deal</a>

      <div class="dash-nav__group-label">Redemptions</div>
      <a href="/merchant/redemptions.php" class="dash-nav__link <?= $active_nav==='redemptions'?'active':'' ?>">
        <span class="dash-nav__icon">🎫</span> Validate Vouchers
        <?php if ($pending_redeem_count > 0): ?>
          <span style="margin-left:auto;background:var(--orange-primary);color:#fff;border-radius:100px;padding:2px 8px;font-size:11px;font-weight:700;"><?= $pending_redeem_count ?></span>
        <?php endif; ?>
      </a>

      <div class="dash-nav__group-label">Business</div>
      <a href="/merchant/profile.php"     class="dash-nav__link <?= $active_nav==='profile'?'active':'' ?>"><span class="dash-nav__icon">🏪</span> Business Profile</a>
      <a href="/merchant/commissions.php" class="dash-nav__link <?= $active_nav==='commissions'?'active':'' ?>"><span class="dash-nav__icon">💹</span> Commissions</a>

      <div class="dash-nav__group-label">Account</div>
      <a href="/index.php"  class="dash-nav__link" target="_blank"><span class="dash-nav__icon">🌐</span> Public Site</a>
      <a href="/logout.php" class="dash-nav__link" data-confirm="Logout?"><span class="dash-nav__icon">🚪</span> Logout</a>
    </nav>
  </aside>

  <main class="dash-main">
    <div class="dash-topbar">
      <div style="display:flex;align-items:center;gap:var(--space-md);">
        <button id="sidebarToggle" style="display:none;background:none;border:none;cursor:pointer;font-size:22px;padding:4px;">☰</button>
        <h1 class="dash-topbar__title"><?= e($page_title ?? 'Dashboard') ?></h1>
      </div>
      <div class="dash-topbar__user">
        <?php if ($pending_redeem_count > 0): ?>
          <a href="/merchant/redemptions.php" style="font-size:14px;color:var(--orange-primary);font-weight:600;text-decoration:none;">🎫 <?= $pending_redeem_count ?> pending</a>
        <?php endif; ?>
        <?php if ($merchant && $merchant['status'] !== 'active'): ?>
          <span class="badge badge--warning" style="font-size:13px;"><?= ucfirst($merchant['status']) ?></span>
        <?php endif; ?>
        <div class="dash-topbar__avatar"><?= $initials ?></div>
        <div style="font-size:15px;font-weight:600;"><?= e(explode(' ', $admin_user['name'])[0]) ?></div>
      </div>
    </div>

    <?php $flash = auth_get_flash(); if (!empty($flash)): ?>
      <div style="padding:var(--space-md) var(--space-xl) 0;">
        <?php foreach ($flash as $type => $msg): ?>
          <div class="alert alert--<?= e($type) ?>">
            <span class="alert__icon"><?= match($type){'success'=>'✓','error'=>'✕','warning'=>'⚠',default=>'ℹ'} ?></span>
            <span><?= e($msg) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($merchant && $merchant['status'] === 'pending'): ?>
      <div style="padding:var(--space-md) var(--space-xl) 0;">
        <div class="alert alert--warning">
          <span class="alert__icon">⚠</span>
          <span>Your merchant account is <strong>pending approval</strong>. You can set up your profile and draft deals while you wait. They will go live once admin approves your account.</span>
        </div>
      </div>
    <?php endif; ?>

    <div class="dash-content">
