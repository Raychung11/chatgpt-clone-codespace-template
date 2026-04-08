<?php
defined('PLOTGOLD') or die('Direct access not permitted.');
$currentPath = basename($_SERVER['PHP_SELF']);
$navItems = [
    ['icon' => 'fa-th-large',  'label' => 'Dashboard',     'file' => 'dashboard.php', 'url' => 'buyer/dashboard.php'],
    ['icon' => 'fa-heart',     'label' => 'Saved Listings', 'file' => 'saved.php',     'url' => 'buyer/saved.php'],
    ['icon' => 'fa-balance-scale','label' => 'Compare',    'file' => 'compare.php',   'url' => 'buyer/compare.php'],
    ['icon' => 'fa-envelope',  'label' => 'Enquiries',      'file' => 'enquiries.php', 'url' => 'buyer/enquiries.php'],
    ['icon' => 'fa-file-invoice','label' => 'My Quotes',   'file' => 'quotes.php',    'url' => 'buyer/quotes.php'],
    ['icon' => 'fa-clipboard-list','label' => 'My Plan',   'file' => 'planner.php',   'url' => 'buyer/planner.php'],
    ['icon' => 'fa-user-cog',  'label' => 'Profile',        'file' => 'profile.php',   'url' => 'buyer/profile.php'],
];
?>
<nav class="portal-sidebar d-none d-md-flex flex-column">
    <div class="px-4 pb-3 border-bottom mb-2">
        <a href="<?= pg_url() ?>"><span class="brand-pg">Plot</span><span class="brand-gold">Gold</span></a>
        <div class="small text-muted mt-1">Buyer Portal</div>
    </div>
    <?php foreach ($navItems as $item): ?>
        <a href="<?= pg_url($item['url']) ?>" class="nav-link <?= $currentPath === $item['file'] ? 'active' : '' ?>">
            <i class="fas <?= $item['icon'] ?>"></i><?= $item['label'] ?>
        </a>
    <?php endforeach; ?>
    <div class="mt-auto px-4 py-3 border-top">
        <a href="<?= pg_url('logout.php') ?>" class="small text-muted d-flex align-items-center gap-2" style="text-decoration:none">
            <i class="fas fa-sign-out-alt"></i>Logout
        </a>
    </div>
</nav>
