<?php
/**
 * Provider Portal — Sidebar Navigation
 */
defined('PLOTGOLD') or die('Direct access not permitted.');

$currentPath = basename($_SERVER['PHP_SELF']);

$navItems = [
    ['icon' => 'fa-th-large',      'label' => 'Dashboard',       'file' => 'dashboard.php', 'url' => 'provider/dashboard.php'],
    ['icon' => 'fa-file-invoice',  'label' => 'Quote Requests',  'file' => 'quotes.php',    'url' => 'provider/quotes.php'],
    ['icon' => 'fa-concierge-bell','label' => 'My Services',     'file' => 'services.php',  'url' => 'provider/services.php'],
    ['icon' => 'fa-map-marker-alt','label' => 'Service Areas',   'file' => 'areas.php',     'url' => 'provider/areas.php'],
    ['icon' => 'fa-user-cog',      'label' => 'Profile',         'file' => 'profile.php',   'url' => 'provider/profile.php'],
];
?>
<nav class="portal-sidebar d-none d-md-flex flex-column">
    <div class="px-4 pb-3 border-bottom mb-2">
        <a href="<?= pg_url() ?>" class="text-decoration-none">
            <span class="brand-pg">Plot</span><span class="brand-gold">Gold</span>
        </a>
        <div class="small text-muted mt-1">Provider Portal</div>
    </div>

    <?php foreach ($navItems as $item): ?>
    <a href="<?= pg_url($item['url']) ?>"
       class="nav-link <?= $currentPath === $item['file'] ? 'active' : '' ?>">
        <i class="fas <?= $item['icon'] ?>"></i>
        <?= h($item['label']) ?>
    </a>
    <?php endforeach; ?>

    <div class="mt-auto px-4 py-3 border-top">
        <div class="small text-muted mb-2 d-flex align-items-center gap-2">
            <div class="nav-avatar"><i class="fas fa-briefcase"></i></div>
            <span class="text-truncate"><?= h(auth_user_name()) ?></span>
        </div>
        <a href="<?= pg_url('logout.php') ?>"
           class="small text-muted d-flex align-items-center gap-2 text-decoration-none">
            <i class="fas fa-sign-out-alt"></i>Logout
        </a>
    </div>
</nav>
