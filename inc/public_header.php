<?php
// ─── SilverDeals MY — Public Header ─────────────────────────────────────────
// Usage: include at top of every public page after require bootstrap.php
// Variables expected: $page_title (optional), $meta_desc (optional), $page_class (optional)

$page_title = $page_title ?? APP_NAME;
$meta_title = $page_title . ($page_title !== APP_NAME ? ' — ' . APP_NAME : '');
$meta_desc  = $meta_desc  ?? 'SilverDeals MY — Malaysia\'s premier senior membership, rewards and deals ecosystem for Malaysians aged 50+.';
$canonical  = BASE_URL . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
?>
<!DOCTYPE html>
<html lang="en-MY">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($meta_title) ?></title>
  <meta name="description" content="<?= e($meta_desc) ?>">
  <link rel="canonical" href="<?= e($canonical) ?>">

  <!-- OG Tags -->
  <meta property="og:type"        content="website">
  <meta property="og:title"       content="<?= e($meta_title) ?>">
  <meta property="og:description" content="<?= e($meta_desc) ?>">
  <meta property="og:url"         content="<?= e($canonical) ?>">
  <meta property="og:image"       content="<?= BASE_URL ?>/assets/img/og-default.jpg">
  <meta property="og:site_name"   content="<?= APP_NAME ?>">
  <meta name="twitter:card"       content="summary_large_image">

  <!-- Favicon (replace with actual favicon) -->
  <link rel="icon" href="/assets/img/favicon.ico" type="image/x-icon">

  <!-- Theme CSS -->
  <link rel="stylesheet" href="/assets/css/theme.css">

  <!-- PWA manifest -->
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#FF6B00">
</head>
<body class="<?= e($page_class ?? '') ?>">

<!-- ─── Navigation ─────────────────────────────────────────────── -->
<nav class="navbar" id="mainNav">
  <div class="navbar__inner">
    <a href="/public/index.php" class="navbar__logo">
      <div>
        <span class="navbar__logo-text">🟠 SilverDeals MY</span>
        <span class="navbar__logo-sub">Senior Membership &amp; Rewards</span>
      </div>
    </a>

    <ul class="navbar__menu" id="navMenu">
      <li><a href="/public/index.php"       class="navbar__link <?= active_nav('/public/index') ?>">Home</a></li>
      <li><a href="/public/deals.php"       class="navbar__link <?= active_nav('/public/deals') ?>">Deals</a></li>
      <li><a href="/public/merchants.php"   class="navbar__link <?= active_nav('/merchants') ?>">Merchants</a></li>
      <li><a href="/public/how-it-works.php" class="navbar__link <?= active_nav('how-it-works') ?>">How It Works</a></li>
      <li><a href="/public/about.php"       class="navbar__link <?= active_nav('/about') ?>">About</a></li>
      <li><a href="/public/contact.php"     class="navbar__link <?= active_nav('/contact') ?>">Contact</a></li>
    </ul>

    <div class="navbar__actions">
      <?php if (auth_check()): ?>
        <a href="/member/dashboard.php" class="btn btn--primary btn--sm">My Dashboard</a>
      <?php else: ?>
        <a href="/public/login.php"       class="btn btn--muted btn--sm">Login</a>
        <a href="/public/register.php"    class="btn btn--primary btn--sm">Join Free</a>
      <?php endif; ?>
    </div>

    <button class="navbar__toggle" id="navToggle" aria-label="Toggle menu">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<!-- Flash messages -->
<?php $flash = auth_get_flash(); if (!empty($flash)): ?>
<div class="container" style="margin-top:16px;">
  <?php foreach ($flash as $type => $msg): ?>
    <div class="alert alert--<?= e($type) ?>">
      <span class="alert__icon"><?= match($type) { 'success'=>'✓','error'=>'✕','warning'=>'⚠',default=>'ℹ' } ?></span>
      <span><?= e($msg) ?></span>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
