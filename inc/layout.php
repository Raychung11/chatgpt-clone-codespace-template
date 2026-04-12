<?php
declare(strict_types=1);

/**
 * inc/layout.php
 * Shared layout partials for client and admin areas.
 */

/**
 * Render the client navbar.
 * @param array $user  Current logged-in user
 * @param string $active  Nav item key
 */
function render_client_navbar(array $user, string $active = ''): void
{
    $balance = format_credits(wallet_balance((int)$user['id']));
    $nav = [
        'dashboard'   => ['label' => 'Dashboard',  'url' => BASE_URL . '/client/dashboard.php'],
        'generate'    => ['label' => 'Generate',   'url' => BASE_URL . '/client/generate.php'],
        'avatar'      => ['label' => 'AI Avatar',  'url' => BASE_URL . '/client/avatar.php'],
        'history'     => ['label' => 'History',    'url' => BASE_URL . '/client/history.php'],
        'editor'      => ['label' => 'Editor',     'url' => BASE_URL . '/client/editor.php'],
        'wallet'      => ['label' => 'Wallet',     'url' => BASE_URL . '/client/wallet.php'],
        'referral'    => ['label' => 'Referral',   'url' => BASE_URL . '/client/referral.php'],
    ];
    ?>
    <nav class="navbar">
        <div class="navbar-inner">
            <a href="<?= BASE_URL ?>/client/dashboard.php" class="navbar-brand">Video<span>SaaS</span></a>
            <ul class="navbar-nav">
                <?php foreach ($nav as $key => $item): ?>
                    <li>
                        <a href="<?= e($item['url']) ?>" class="<?= $active === $key ? 'active' : '' ?>">
                            <?= e($item['label']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div style="margin-left:auto;display:flex;align-items:center;gap:12px;">
                <span class="navbar-wallet">&#9889; <?= e($balance) ?> credits</span>
                <a href="<?= BASE_URL ?>/public/logout.php" class="btn btn-ghost btn-sm">Logout</a>
            </div>
            <!-- Mobile hamburger -->
            <button class="hamburger" id="hamburgerBtn" aria-label="Menu" style="margin-left:12px">
                <span></span><span></span><span></span>
            </button>
        </div>
    </nav>

    <!-- Mobile slide-down nav -->
    <div class="mobile-nav" id="mobileNav" style="display:none">
        <div class="nav-wallet">&#9889; <?= e($balance) ?> credits</div>
        <?php foreach ($nav as $key => $item): ?>
            <a href="<?= e($item['url']) ?>" class="<?= $active === $key ? 'active' : '' ?>">
                <?= e($item['label']) ?>
            </a>
        <?php endforeach; ?>
        <a href="<?= BASE_URL ?>/client/profile.php">Profile</a>
        <a href="<?= BASE_URL ?>/public/logout.php" style="color:var(--color-danger)">Sign Out</a>
    </div>
    <script src="<?= BASE_URL ?>/public/assets/js/app.js" defer></script>
    <?php
}

/**
 * Render the admin navbar.
 */
function render_admin_navbar(array $admin): void
{
    ?>
    <nav class="navbar">
        <div class="navbar-inner">
            <a href="<?= BASE_URL ?>/admin/index.php" class="navbar-brand">Video<span>SaaS</span>
                <span style="font-size:.7rem;color:var(--color-primary);margin-left:6px;font-weight:400">Admin</span>
            </a>
            <div style="margin-left:auto;display:flex;align-items:center;gap:12px;">
                <span class="text-muted text-sm"><?= e($admin['name']) ?></span>
                <span class="badge badge-primary"><?= e($admin['role']) ?></span>
                <a href="<?= BASE_URL ?>/admin/logout.php" class="btn btn-ghost btn-sm">Logout</a>
            </div>
        </div>
    </nav>
    <?php
}

/**
 * Render the admin sidebar.
 */
function render_admin_sidebar(string $active = ''): void
{
    $sections = [
        'Overview' => [
            'dashboard' => ['Dashboard',  BASE_URL . '/admin/index.php'],
        ],
        'Users & Wallets' => [
            'users'    => ['Users',     BASE_URL . '/admin/users.php'],
            'wallets'  => ['Wallets',   BASE_URL . '/admin/wallets.php'],
        ],
        'Payments' => [
            'packages' => ['Packages',  BASE_URL . '/admin/packages.php'],
            'payments' => ['Payments',  BASE_URL . '/admin/payments.php'],
        ],
        'Video' => [
            'jobs'      => ['Video Jobs', BASE_URL . '/admin/jobs.php'],
            'pricing'   => ['Pricing',    BASE_URL . '/admin/pricing.php'],
            'templates' => ['Templates',  BASE_URL . '/admin/templates.php'],
        ],
        'Growth' => [
            'referrals'=> ['Referrals', BASE_URL . '/admin/referrals.php'],
            'reports'  => ['Reports',   BASE_URL . '/admin/reports.php'],
        ],
        'System' => [
            'settings' => ['Settings',  BASE_URL . '/admin/settings.php'],
            'logs'     => ['Activity Logs', BASE_URL . '/admin/logs.php'],
        ],
    ];
    ?>
    <aside class="sidebar">
        <?php foreach ($sections as $section => $items): ?>
            <div class="sidebar-section"><?= e($section) ?></div>
            <ul class="sidebar-nav">
                <?php foreach ($items as $key => [$label, $url]): ?>
                    <li>
                        <a href="<?= e($url) ?>" class="<?= $active === $key ? 'active' : '' ?>">
                            <?= e($label) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </aside>
    <?php
}

/**
 * Render HTML pagination links.
 */
function render_pagination(array $pager): void
{
    if ($pager['total_pages'] <= 1) return;
    ?>
    <div class="pagination">
        <?php if ($pager['has_prev']): ?>
            <a href="<?= page_url($pager['current'] - 1) ?>">&laquo;</a>
        <?php else: ?>
            <span class="disabled">&laquo;</span>
        <?php endif; ?>

        <?php
        $start = max(1, $pager['current'] - 2);
        $end   = min($pager['total_pages'], $pager['current'] + 2);
        if ($start > 1) echo '<span>…</span>';
        for ($i = $start; $i <= $end; $i++):
        ?>
            <?php if ($i === $pager['current']): ?>
                <span class="active"><?= $i ?></span>
            <?php else: ?>
                <a href="<?= page_url($i) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($end < $pager['total_pages']) echo '<span>…</span>'; ?>

        <?php if ($pager['has_next']): ?>
            <a href="<?= page_url($pager['current'] + 1) ?>">&raquo;</a>
        <?php else: ?>
            <span class="disabled">&raquo;</span>
        <?php endif; ?>
    </div>
    <?php
}
