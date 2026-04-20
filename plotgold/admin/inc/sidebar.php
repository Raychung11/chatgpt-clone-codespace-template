<?php
defined('PLOTGOLD') or die('Direct access not permitted.');

$currentFile = basename($_SERVER['PHP_SELF']);

// Live badge counts — wrapped in try/catch so a missing table never breaks the nav
try { $pendingReview    = (int)(Database::fetchOne("SELECT COUNT(*) c FROM listings    WHERE status = 'pending_review'")['c'] ?? 0); } catch (\Throwable $e) { $pendingReview    = 0; }
try { $openEnquiries    = (int)(Database::fetchOne("SELECT COUNT(*) c FROM enquiries   WHERE status = 'new'")['c']            ?? 0); } catch (\Throwable $e) { $openEnquiries    = 0; }
try { $pendingQuotes    = (int)(Database::fetchOne("SELECT COUNT(*) c FROM quotations  WHERE status = 'in_review'")['c']      ?? 0); } catch (\Throwable $e) { $pendingQuotes    = 0; }
try { $pendingProviders = (int)(Database::fetchOne("SELECT COUNT(*) c FROM providers   WHERE approval_status = 'pending'")['c'] ?? 0); } catch (\Throwable $e) { $pendingProviders = 0; }
try { $stockAlerts     = (int)(Database::fetchOne("SELECT COUNT(*) c FROM stock_alerts WHERE is_acknowledged = 0")['c']                       ?? 0); } catch (\Throwable $e) { $stockAlerts     = 0; }

$sections = [
    'Overview' => [
        ['icon' => 'fa-chart-pie',       'label' => 'Dashboard',          'file' => 'index.php',          'url' => 'admin/'],
    ],
    'Listings' => [
        ['icon' => 'fa-list-ul',         'label' => 'All Listings',        'file' => 'listings.php',       'url' => 'admin/listings.php'],
        ['icon' => 'fa-search-plus',     'label' => 'Review Queue',        'file' => 'listing_review.php', 'url' => 'admin/listing_review.php',
         'badge' => $pendingReview ?: null, 'badge_class' => 'bg-danger'],
        ['icon' => 'fa-tree',            'label' => 'Memorial Parks',      'file' => 'memorial_parks.php', 'url' => 'admin/memorial_parks.php'],
        ['icon' => 'fa-boxes',           'label' => 'Stock Control',        'file' => 'stock_control.php',  'url' => 'admin/stock_control.php',
         'badge' => $stockAlerts ?: null, 'badge_class' => 'bg-danger'],
    ],
    'Providers & Quotes' => [
        ['icon' => 'fa-briefcase',       'label' => 'Providers',           'file' => 'providers.php',      'url' => 'admin/providers.php',
         'badge' => $pendingProviders ?: null, 'badge_class' => 'bg-warning text-dark'],
        ['icon' => 'fa-file-invoice',    'label' => 'Quotes',              'file' => 'quotes.php',         'url' => 'admin/quotes.php',
         'badge' => $pendingQuotes ?: null, 'badge_class' => 'bg-warning text-dark'],
    ],
    'CRM' => [
        ['icon' => 'fa-headset',         'label' => 'Leads / Enquiries',   'file' => 'enquiries.php',      'url' => 'admin/enquiries.php',
         'badge' => $openEnquiries ?: null, 'badge_class' => 'bg-danger'],
        ['icon' => 'fa-robot',           'label' => 'AI Leads',            'file' => 'ai_leads.php',       'url' => 'admin/ai_leads.php',
         'soon' => true],
    ],
    'Content' => [
        ['icon' => 'fa-file-alt',        'label' => 'CMS Pages',           'file' => 'cms_pages.php',      'url' => 'admin/cms_pages.php'],
        ['icon' => 'fa-question-circle', 'label' => 'FAQs',                'file' => 'faqs.php',           'url' => 'admin/faqs.php'],
    ],
    'System' => [
        ['icon' => 'fa-users',           'label' => 'Users',               'file' => 'users.php',          'url' => 'admin/users.php'],
        ['icon' => 'fa-file-export',     'label' => 'Activity Logs',       'file' => 'activity_logs.php',  'url' => 'admin/activity_logs.php'],
        ['icon' => 'fa-cog',             'label' => 'Settings',            'file' => 'settings.php',       'url' => 'admin/settings.php'],
    ],
];
?>
<nav class="admin-sidebar">
    <div class="sidebar-brand d-flex align-items-center gap-2">
        <span class="brand-pg text-white">Plot</span><span class="brand-gold">Gold</span>
        <span class="text-white-50 small ms-1">Admin</span>
    </div>

    <?php foreach ($sections as $sectionTitle => $items): ?>
        <div class="sidebar-section-label"><?= $sectionTitle ?></div>
        <?php foreach ($items as $item): ?>
            <?php
            $isActive   = $currentFile === $item['file'];
            $hasBadge   = !empty($item['badge']);
            $isSoon     = !empty($item['soon']);
            ?>
            <a href="<?= $isSoon ? '#' : pg_url($item['url']) ?>"
               class="nav-link <?= $isActive ? 'active' : '' ?> <?= $isSoon ? 'text-white-50' : '' ?>"
               <?= $isSoon ? 'onclick="return false;" style="cursor:default;opacity:.55;"' : '' ?>>
                <i class="fas <?= $item['icon'] ?>"></i>
                <span class="flex-fill"><?= $item['label'] ?></span>
                <?php if ($hasBadge): ?>
                    <span class="badge <?= $item['badge_class'] ?> ms-1" style="font-size:.65rem;">
                        <?= $item['badge'] ?>
                    </span>
                <?php endif; ?>
                <?php if ($isSoon): ?>
                    <span class="badge bg-secondary ms-1" style="font-size:.6rem;opacity:.8;">Soon</span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <!-- Footer -->
    <div class="mt-auto" style="padding:1rem .75rem;border-top:1px solid rgba(255,255,255,.08);">
        <div class="d-flex align-items-center gap-2 mb-2">
            <div class="d-flex align-items-center justify-content-center rounded-circle text-white fw-700"
                 style="width:30px;height:30px;background:var(--pg-gold);font-size:.75rem;flex-shrink:0;">
                <?= strtoupper(substr(auth_user_name(), 0, 1)) ?>
            </div>
            <div style="min-width:0;">
                <div class="text-white small fw-500 text-truncate"><?= h(auth_user_name()) ?></div>
                <div class="text-white-50" style="font-size:.7rem;">Administrator</div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= pg_url() ?>" target="_blank"
               class="btn btn-sm flex-fill text-white-50"
               style="background:rgba(255,255,255,.07);font-size:.72rem;border:1px solid rgba(255,255,255,.12);">
                <i class="fas fa-external-link-alt me-1"></i>View Site
            </a>
            <a href="<?= pg_url('logout.php') ?>"
               class="btn btn-sm text-white-50"
               style="background:rgba(255,255,255,.07);font-size:.72rem;border:1px solid rgba(255,255,255,.12);"
               title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</nav>
