</div><!-- /.container-fluid -->

<!-- Bottom Navigation -->
<?php if (!isset($hideNav)): ?>
<?php
$unreadCount = Auth::check() ? Notification::getUnreadCount(Auth::currentUserId()) : 0;
$currentRoute = trim(preg_replace('#^/app/?#', '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), '/') ?: 'home';
$navItems = [
    'home'         => ['icon' => 'house-fill',          'label' => 'Home'],
    'menu'         => ['icon' => 'menu-button-wide-fill','label' => 'Menu'],
    'rewards'      => ['icon' => 'gift-fill',            'label' => 'Rewards'],
    'reservations' => ['icon' => 'calendar-check',       'label' => 'Book'],
    'profile'      => ['icon' => 'person-circle',        'label' => 'Profile'],
];
?>
<nav class="bottom-nav">
    <?php foreach ($navItems as $route => $item): ?>
    <a href="/app/<?= $route === 'home' ? '' : $route ?>"
       class="<?= $currentRoute === $route || ($route === 'home' && $currentRoute === '') ? 'active' : '' ?>"
       style="position:relative;">
        <i class="bi bi-<?= $item['icon'] ?>"></i>
        <span><?= $item['label'] ?></span>
        <?php if ($route === 'profile' && $unreadCount > 0): ?>
        <span class="notif-badge"><?= min(9, $unreadCount) ?></span>
        <?php endif; ?>
        <?php if ($route === 'menu'): ?>
        <span class="notif-badge" id="nav-cart-badge" style="display:none;background:#e94560;"></span>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<!-- Toast container -->
<div id="toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// PWA Service Worker
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/public/sw.js').catch(console.error);
}

// Cart badge in bottom nav
(function updateNavCartBadge() {
    try {
        const cart  = JSON.parse(localStorage.getItem('fnb_cart') || '[]');
        const total = cart.reduce((s, i) => s + (i.qty || 0), 0);
        const badge = document.getElementById('nav-cart-badge');
        if (badge && total > 0) {
            badge.textContent    = total > 9 ? '9+' : total;
            badge.style.display  = '';
        }
    } catch {}
})();

// Toast helper
function showToast(msg, type = 'info') {
    const el = document.createElement('div');
    el.className = `toast-msg ${type}`;
    el.textContent = msg;
    document.getElementById('toast-container').appendChild(el);
    setTimeout(() => el.remove(), 3000);
}

// API helper
async function apiCall(endpoint, method = 'GET', body = null) {
    const token = localStorage.getItem('fnb_token');
    const opts = {
        method,
        headers: {
            'Content-Type': 'application/json',
            // Send token two ways: Authorization (standard) + X-Auth-Token
            // (fallback for Hostinger/LiteSpeed which strips Authorization)
            ...(token ? {
                'Authorization': `Bearer ${token}`,
                'X-Auth-Token': token,
            } : {}),
        },
    };
    if (body) opts.body = JSON.stringify(body);
    const res = await fetch('/api/' + endpoint, opts);
    return res.json();
}
</script>
<?php if (isset($extraScript)) echo $extraScript; ?>
</body>
</html>
