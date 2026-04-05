<?php
/**
 * Customer Logout
 * /public/pages/logout.php
 * Visiting /app/logout clears the session and redirects to login.
 */
$appTitle = 'Signing out…';
$hideNav  = true;
require BASE_PATH . '/public/layout/app_shell.php';
?>
<div style="display:flex;align-items:center;justify-content:center;min-height:60vh;flex-direction:column;gap:1rem;">
    <div style="font-size:2.5rem;">👋</div>
    <div class="fw-semibold">Signing you out…</div>
</div>
<script>
// Call logout API if token exists, then clear everything
(async () => {
    const token = localStorage.getItem('fnb_token');
    if (token) {
        try {
            await fetch('/api/auth/logout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token,
                    'X-Auth-Token': token,
                }
            });
        } catch(e) {}
    }
    localStorage.clear();
    window.location.href = '/app/login';
})();
</script>
<?php require BASE_PATH . '/public/layout/app_footer.php'; ?>
