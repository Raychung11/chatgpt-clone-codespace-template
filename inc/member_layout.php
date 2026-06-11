<?php
// ─── SilverDeals MY — Member Dashboard Layout ────────────────────────────────
// Include at top of member pages (after bootstrap + auth_require)
// Expects: $page_title (string), $active_nav (string key)

$user     = auth_user();
$initials = strtoupper(substr($user['name'] ?? 'M', 0, 2));

// Get unread notification count
$notif_count = 0;
try {
    $stmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user['id']]);
    $notif_count = (int)$stmt->fetchColumn();
} catch (PDOException) {}

// Get wallet balance
$wallet_balance = 0;
try {
    $stmt = db()->prepare("SELECT balance FROM points_wallets WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user['id']]);
    $wallet_balance = (int)($stmt->fetchColumn() ?: 0);
} catch (PDOException) {}

$active_nav = $active_nav ?? '';
?>
<!DOCTYPE html>
<html lang="en-MY">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title ?? 'Dashboard') ?> — SilverDeals MY</title>
  <meta name="robots" content="noindex,nofollow">
  <link rel="stylesheet" href="/assets/css/theme.css">
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#FF6B00">
</head>
<body>

<div class="dash-layout" id="dashLayout">

  <!-- ── Sidebar ──────────────────────────────────────────────────────────── -->
  <aside class="dash-sidebar">
    <div class="dash-sidebar__logo">
      <div class="dash-sidebar__logo-text">🟠 SilverDeals MY</div>
      <div class="dash-sidebar__logo-role">Member Portal</div>
    </div>

    <nav class="dash-nav">
      <div class="dash-nav__group-label">Main</div>
      <a href="/member/dashboard.php"      class="dash-nav__link <?= $active_nav==='dashboard'?'active':'' ?>"><span class="dash-nav__icon">🏠</span> Dashboard</a>
      <a href="/member/deals.php"          class="dash-nav__link <?= $active_nav==='deals'?'active':'' ?>"><span class="dash-nav__icon">🎁</span> Browse Deals</a>
      <a href="/member/redemptions.php"    class="dash-nav__link <?= $active_nav==='redemptions'?'active':'' ?>"><span class="dash-nav__icon">🎫</span> My Vouchers</a>

      <div class="dash-nav__group-label">Membership</div>
      <a href="/member/membership_card.php" class="dash-nav__link <?= $active_nav==='card'?'active':'' ?>"><span class="dash-nav__icon">🃏</span> My Card</a>
      <a href="/member/profile.php"         class="dash-nav__link <?= $active_nav==='profile'?'active':'' ?>"><span class="dash-nav__icon">👤</span> My Profile</a>
      <a href="/member/subscription.php"    class="dash-nav__link <?= $active_nav==='subscription'?'active':'' ?>"><span class="dash-nav__icon">⭐</span> Upgrade Plan</a>

      <div class="dash-nav__group-label">Rewards</div>
      <a href="/member/rewards.php"    class="dash-nav__link <?= $active_nav==='rewards'?'active':'' ?>"><span class="dash-nav__icon">💰</span> Points Wallet</a>
      <a href="/member/referrals.php"  class="dash-nav__link <?= $active_nav==='referrals'?'active':'' ?>"><span class="dash-nav__icon">🤝</span> Refer &amp; Earn</a>

      <div class="dash-nav__group-label">Account</div>
      <a href="/deals.php"      class="dash-nav__link"><span class="dash-nav__icon">🌐</span> Public Site</a>
      <a href="/logout.php"     class="dash-nav__link" data-confirm="Are you sure you want to logout?"><span class="dash-nav__icon">🚪</span> Logout</a>
    </nav>

    <div style="padding:var(--space-lg);border-top:1px solid rgba(255,255,255,.08);">
      <div style="font-size:13px;color:rgba(255,255,255,.4);">
        <?= APP_NAME ?> v<?= APP_VERSION ?>
      </div>
    </div>
  </aside>

  <!-- ── Main Content ──────────────────────────────────────────────────────── -->
  <main class="dash-main">
    <!-- Top bar -->
    <div class="dash-topbar">
      <div style="display:flex;align-items:center;gap:var(--space-md);">
        <button id="sidebarToggle" style="display:none;background:none;border:none;cursor:pointer;font-size:22px;padding:4px;" aria-label="Toggle sidebar">☰</button>
        <h1 class="dash-topbar__title"><?= e($page_title ?? 'Dashboard') ?></h1>
      </div>
      <div class="dash-topbar__user">
        <!-- Points quick badge -->
        <a href="/member/rewards.php" style="display:flex;align-items:center;gap:6px;background:var(--orange-bg);border:1px solid var(--orange-border);border-radius:var(--radius-pill);padding:8px 14px;text-decoration:none;color:var(--orange-primary);font-weight:700;font-size:15px;">
          💰 <?= number_format($wallet_balance) ?> pts
        </a>
        <!-- Notifications -->
        <a href="/member/notifications.php" style="position:relative;font-size:22px;text-decoration:none;" title="Notifications">
          🔔
          <?php if ($notif_count > 0): ?>
            <span style="position:absolute;top:-4px;right:-4px;background:var(--error);color:#fff;border-radius:50%;width:18px;height:18px;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;"><?= min($notif_count, 9) ?></span>
          <?php endif; ?>
        </a>
        <!-- Avatar -->
        <div class="dash-topbar__avatar"><?= $initials ?></div>
        <div style="font-size:15px;font-weight:600;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e(explode(' ', $user['name'])[0]) ?></div>
      </div>
    </div>

    <!-- Status banners -->
    <?php
    $flash = auth_get_flash();
    if (!empty($flash)):
    ?>
    <div style="padding:var(--space-md) var(--space-xl) 0;">
      <?php foreach ($flash as $type => $msg): ?>
        <div class="alert alert--<?= e($type) ?>">
          <span class="alert__icon"><?= match($type) { 'success'=>'✓','error'=>'✕','warning'=>'⚠',default=>'ℹ' } ?></span>
          <span><?= e($msg) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($user['status'] === 'pending'): ?>
    <div style="padding:var(--space-md) var(--space-xl) 0;">
      <div class="alert alert--warning">
        <span class="alert__icon">⚠</span>
        <span>Your membership is <strong>pending review</strong>. Please <a href="/member/profile.php">complete your profile</a> and submit verification to unlock all features.</span>
      </div>
    </div>
    <?php endif; ?>

    <div class="dash-content">
