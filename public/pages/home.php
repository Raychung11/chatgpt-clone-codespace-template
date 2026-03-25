<?php
/**
 * PWA Home – redirect to dashboard if logged in, else show landing
 * /public/pages/home.php
 */

$appTitle = 'F&B Loyalty Platform';
$hideNav  = true;
require BASE_PATH . '/public/layout/app_shell.php';
?>

<style>
body { background: linear-gradient(135deg, #1a1a2e 0%, #0f3460 60%, #16213e 100%); min-height: 100vh; padding-bottom: 0; }
.container-fluid { padding: 0 !important; }
</style>

<div class="d-flex flex-column align-items-center justify-content-center min-vh-100 text-white text-center px-4">
    <div style="font-size: 5rem; margin-bottom: 1rem;">🍽️</div>
    <h1 class="fw-bold mb-2" style="font-size: 2rem;">F&B Loyalty</h1>
    <p class="mb-4" style="color: #a8b2d8; max-width: 280px;">
        Earn points, redeem rewards, and enjoy exclusive member benefits.
    </p>

    <div class="d-flex flex-column gap-3 w-100" style="max-width: 300px;">
        <a href="/app/login" class="btn-brand text-center text-decoration-none" style="border-radius:14px;padding:.85rem;">
            Sign In
        </a>
        <a href="/app/register" class="btn btn-outline-light w-100" style="border-radius:14px;padding:.85rem;font-weight:600;">
            Create Account
        </a>
    </div>

    <div class="mt-5 d-flex gap-4">
        <?php foreach ([['🏆','Earn Points'],['🎁','Redeem Rewards'],['📍','Find Outlets']] as [$icon,$label]): ?>
        <div class="text-center">
            <div style="font-size: 1.8rem;"><?= $icon ?></div>
            <div style="font-size: .7rem; color: #a8b2d8; margin-top: 4px;"><?= $label ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
// Auto-redirect if already logged in
if (localStorage.getItem('fnb_token')) {
    window.location.href = '/app/dashboard';
}
</script>

<?php require BASE_PATH . '/public/layout/app_footer.php'; ?>
