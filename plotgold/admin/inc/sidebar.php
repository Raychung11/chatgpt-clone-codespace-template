<?php
defined('PLOTGOLD') or die('Direct access not permitted.');
$currentFile = basename($_SERVER['PHP_SELF']);

$sections = [
    'Overview' => [
        ['icon' => 'fa-chart-pie',      'label' => 'Dashboard',         'file' => 'index.php',          'url' => 'admin/'],
    ],
    'Listings' => [
        ['icon' => 'fa-list',           'label' => 'All Listings',       'file' => 'listings.php',       'url' => 'admin/listings.php'],
        ['icon' => 'fa-search-plus',    'label' => 'Review Queue',       'file' => 'listing_review.php', 'url' => 'admin/listing_review.php'],
        ['icon' => 'fa-tree',           'label' => 'Memorial Parks',     'file' => 'memorial_parks.php', 'url' => 'admin/memorial_parks.php'],
    ],
    'Providers & Quotes' => [
        ['icon' => 'fa-briefcase',      'label' => 'Providers',          'file' => 'providers.php',      'url' => 'admin/providers.php'],
        ['icon' => 'fa-file-invoice',   'label' => 'Quotes',             'file' => 'quotes.php',         'url' => 'admin/quotes.php'],
    ],
    'CRM' => [
        ['icon' => 'fa-headset',        'label' => 'Leads / Enquiries',  'file' => 'enquiries.php',      'url' => 'admin/enquiries.php'],
        ['icon' => 'fa-robot',          'label' => 'AI Leads',           'file' => 'ai_leads.php',       'url' => 'admin/ai_leads.php'],
    ],
    'Content' => [
        ['icon' => 'fa-file-alt',       'label' => 'CMS Pages',          'file' => 'cms_pages.php',      'url' => 'admin/cms_pages.php'],
        ['icon' => 'fa-question-circle','label' => 'FAQs',               'file' => 'faqs.php',           'url' => 'admin/faqs.php'],
    ],
    'System' => [
        ['icon' => 'fa-users',          'label' => 'Users',              'file' => 'users.php',          'url' => 'admin/users.php'],
        ['icon' => 'fa-file-export',    'label' => 'Activity Logs',      'file' => 'activity_logs.php',  'url' => 'admin/activity_logs.php'],
        ['icon' => 'fa-cog',            'label' => 'Settings',           'file' => 'settings.php',       'url' => 'admin/settings.php'],
    ],
];
?>
<nav class="admin-sidebar">
    <div class="sidebar-brand d-flex align-items-center gap-2">
        <span class="brand-pg text-white">Plot</span><span class="brand-gold">Gold</span>
        <span class="text-white-50 small">Admin</span>
    </div>

    <?php foreach ($sections as $sectionTitle => $items): ?>
        <div class="sidebar-section-label"><?= $sectionTitle ?></div>
        <?php foreach ($items as $item): ?>
            <a href="<?= pg_url($item['url']) ?>" class="nav-link <?= $currentFile === $item['file'] ? 'active' : '' ?>">
                <i class="fas <?= $item['icon'] ?>"></i>
                <?= $item['label'] ?>
            </a>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <div class="p-3 mt-3 border-top border-white border-opacity-10">
        <div class="small text-white-50 mb-2"><?= h(auth_user_name()) ?></div>
        <a href="<?= pg_url('logout.php') ?>" class="small text-white-50 d-flex align-items-center gap-2" style="text-decoration:none">
            <i class="fas fa-sign-out-alt"></i>Logout
        </a>
    </div>
</nav>
