<?php
defined('PLOTGOLD') or die('Direct access not permitted.');
$currentPath = basename($_SERVER['PHP_SELF']);
$navItems = [
    ['icon' => 'fa-th-large',     'label' => 'Dashboard',         'file' => 'dashboard.php',       'url' => 'seller/dashboard.php'],
    ['icon' => 'fa-plus-circle',  'label' => 'New Listing',        'file' => 'new_listing.php',     'url' => 'seller/new_listing.php'],
    ['icon' => 'fa-list',         'label' => 'My Listings',        'file' => 'my_listings.php',     'url' => 'seller/my_listings.php'],
    ['icon' => 'fa-envelope',     'label' => 'Enquiries',          'file' => 'enquiries.php',       'url' => 'seller/enquiries.php'],
    ['icon' => 'fa-chart-bar',    'label' => 'Offers',             'file' => 'offers.php',          'url' => 'seller/offers.php'],
    ['icon' => 'fa-money-bill',   'label' => 'Payouts',            'file' => 'payouts.php',         'url' => 'seller/payouts.php'],
    ['icon' => 'fa-user-cog',     'label' => 'Profile',            'file' => 'profile.php',         'url' => 'seller/profile.php'],
];
?>
<nav class="portal-sidebar d-none d-md-flex flex-column">
    <div class="px-4 pb-3 border-bottom mb-2">
        <a href="<?= pg_url() ?>"><span class="brand-pg">Plot</span><span class="brand-gold">Gold</span></a>
        <div class="small text-muted mt-1">Seller Portal</div>
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
