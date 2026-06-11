<?php
// ─── SilverDeals MY — Admin Layout ──────────────────────────────────────────
// Include at top of admin pages (after bootstrap + auth_require_admin())
// Expects: $page_title (string), $active_nav (string)

$admin      = auth_user();
$initials   = strtoupper(substr($admin['name'] ?? 'A', 0, 2));
$active_nav = $active_nav ?? '';

// Pending approval counts
$pending = ['members'=>0, 'merchants'=>0, 'community'=>0, 'verifications'=>0];
try {
    $pdo = db();
    $pending['members']       = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='pending' AND role='member'")->fetchColumn();
    $pending['merchants']     = (int)$pdo->query("SELECT COUNT(*) FROM merchants WHERE status='pending'")->fetchColumn();
    $pending['community']     = (int)$pdo->query("SELECT COUNT(*) FROM community_partners WHERE status='pending'")->fetchColumn();
    $pending['verifications'] = (int)$pdo->query("SELECT COUNT(*) FROM senior_verifications WHERE status='pending'")->fetchColumn();
} catch (PDOException) {}

$total_pending = array_sum($pending);
?>
<!DOCTYPE html>
<html lang="en-MY">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title ?? 'Admin') ?> — SilverDeals MY Admin</title>
  <meta name="robots" content="noindex,nofollow">
  <link rel="stylesheet" href="/assets/css/theme.css">
  <style>
    /* Admin-specific overrides */
    .dash-sidebar { background: #111827; }
    .dash-topbar  { background: #fff; }
    .admin-badge  {
      display:inline-flex;align-items:center;justify-content:center;
      min-width:20px;height:20px;padding:0 5px;
      background:var(--error);color:#fff;
      border-radius:var(--radius-pill);font-size:11px;font-weight:700;
      margin-left:auto;flex-shrink:0;
    }
  </style>
</head>
<body>

<div class="dash-layout" id="dashLayout">

  <!-- ── Sidebar ─────────────────────────────────────────────────────────── -->
  <aside class="dash-sidebar">
    <div class="dash-sidebar__logo">
      <div class="dash-sidebar__logo-text">🟠 SilverDeals MY</div>
      <div class="dash-sidebar__logo-role">Admin Portal</div>
    </div>

    <nav class="dash-nav">
      <div class="dash-nav__group-label">Overview</div>
      <a href="/admin/dashboard.php"  class="dash-nav__link <?= $active_nav==='dashboard' ?'active':'' ?>">
        <span class="dash-nav__icon">📊</span> Dashboard
        <?php if ($total_pending > 0): ?><span class="admin-badge"><?= $total_pending ?></span><?php endif; ?>
      </a>

      <div class="dash-nav__group-label">People</div>
      <a href="/admin/members.php"      class="dash-nav__link <?= $active_nav==='members'?'active':'' ?>">
        <span class="dash-nav__icon">👥</span> Members
        <?php if ($pending['members']>0): ?><span class="admin-badge"><?= $pending['members'] ?></span><?php endif; ?>
      </a>
      <a href="/admin/verifications.php" class="dash-nav__link <?= $active_nav==='verifications'?'active':'' ?>">
        <span class="dash-nav__icon">✅</span> Verifications
        <?php if ($pending['verifications']>0): ?><span class="admin-badge"><?= $pending['verifications'] ?></span><?php endif; ?>
      </a>
      <a href="/admin/merchants.php"    class="dash-nav__link <?= $active_nav==='merchants'?'active':'' ?>">
        <span class="dash-nav__icon">🏪</span> Merchants
        <?php if ($pending['merchants']>0): ?><span class="admin-badge"><?= $pending['merchants'] ?></span><?php endif; ?>
      </a>
      <a href="/admin/community.php"    class="dash-nav__link <?= $active_nav==='community'?'active':'' ?>">
        <span class="dash-nav__icon">🏢</span> Community Partners
        <?php if ($pending['community']>0): ?><span class="admin-badge"><?= $pending['community'] ?></span><?php endif; ?>
      </a>

      <div class="dash-nav__group-label">Deals & Commerce</div>
      <a href="/admin/deals.php"        class="dash-nav__link <?= $active_nav==='deals'?'active':'' ?>"><span class="dash-nav__icon">🎁</span> Deals</a>
      <a href="/admin/redemptions.php"  class="dash-nav__link <?= $active_nav==='redemptions'?'active':'' ?>"><span class="dash-nav__icon">🎫</span> Redemptions</a>
      <a href="/admin/commissions.php"  class="dash-nav__link <?= $active_nav==='commissions'?'active':'' ?>"><span class="dash-nav__icon">💹</span> Commissions</a>
      <a href="/admin/payouts.php"      class="dash-nav__link <?= $active_nav==='payouts'?'active':'' ?>"><span class="dash-nav__icon">💸</span> Payouts</a>

      <div class="dash-nav__group-label">Rewards</div>
      <a href="/admin/points.php"       class="dash-nav__link <?= $active_nav==='points'?'active':'' ?>"><span class="dash-nav__icon">💰</span> Points Management</a>
      <a href="/admin/referrals.php"    class="dash-nav__link <?= $active_nav==='referrals'?'active':'' ?>"><span class="dash-nav__icon">🤝</span> Referrals</a>
      <a href="/admin/fraud.php"        class="dash-nav__link <?= $active_nav==='fraud'?'active':'' ?>"><span class="dash-nav__icon">🚨</span> Fraud Monitor</a>

      <div class="dash-nav__group-label">Content & Config</div>
      <a href="/admin/banners.php"      class="dash-nav__link <?= $active_nav==='banners'?'active':'' ?>"><span class="dash-nav__icon">🖼️</span> Banners</a>
      <a href="/admin/subscriptions.php" class="dash-nav__link <?= $active_nav==='subscriptions'?'active':'' ?>"><span class="dash-nav__icon">⭐</span> Subscriptions</a>
      <a href="/admin/reports.php"      class="dash-nav__link <?= $active_nav==='reports'?'active':'' ?>"><span class="dash-nav__icon">📈</span> Reports</a>
      <a href="/admin/audit-logs.php"   class="dash-nav__link <?= $active_nav==='audit'?'active':'' ?>"><span class="dash-nav__icon">📋</span> Audit Logs</a>
      <a href="/admin/settings.php"     class="dash-nav__link <?= $active_nav==='settings'?'active':'' ?>"><span class="dash-nav__icon">⚙️</span> Settings</a>

      <div class="dash-nav__group-label">Account</div>
      <a href="/index.php" class="dash-nav__link" target="_blank"><span class="dash-nav__icon">🌐</span> View Public Site</a>
      <a href="/logout.php" class="dash-nav__link" data-confirm="Logout from admin?"><span class="dash-nav__icon">🚪</span> Logout</a>
    </nav>

    <div style="padding:var(--space-md) var(--space-lg);border-top:1px solid rgba(255,255,255,.08);font-size:12px;color:rgba(255,255,255,.3);">
      <?= APP_NAME ?> v<?= APP_VERSION ?> | Admin
    </div>
  </aside>

  <!-- ── Main ────────────────────────────────────────────────────────────── -->
  <main class="dash-main">
    <div class="dash-topbar">
      <div style="display:flex;align-items:center;gap:var(--space-md);">
        <button id="sidebarToggle" style="display:none;background:none;border:none;cursor:pointer;font-size:22px;padding:4px;" aria-label="Toggle sidebar">☰</button>
        <h1 class="dash-topbar__title"><?= e($page_title ?? 'Admin') ?></h1>
      </div>
      <div class="dash-topbar__user">
        <?php if ($total_pending > 0): ?>
          <span style="font-size:14px;color:var(--error);font-weight:600;">⚠ <?= $total_pending ?> pending</span>
        <?php endif; ?>
        <div class="dash-topbar__avatar" style="background:#1F2937;border-color:var(--orange-border);"><?= $initials ?></div>
        <div>
          <div style="font-size:14px;font-weight:700;"><?= e(explode(' ', $admin['name'])[0]) ?></div>
          <div style="font-size:12px;color:var(--text-muted);text-transform:capitalize;"><?= e($admin['role']) ?></div>
        </div>
      </div>
    </div>

    <!-- Flash messages -->
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

    <div class="dash-content">
