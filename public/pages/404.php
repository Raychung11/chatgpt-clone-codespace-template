<?php
$appTitle   = 'Page Not Found';
$showHeader = false;
$hideNav    = true;
require BASE_PATH . '/public/layout/app_shell.php';
?>
<div class="d-flex flex-column align-items-center justify-content-center" style="min-height:80vh;">
    <div style="font-size:4rem;">🔍</div>
    <h5 class="mt-3 fw-bold">Page Not Found</h5>
    <p class="text-muted small">The page you're looking for doesn't exist.</p>
    <a href="/app/" class="btn-brand" style="width:auto;padding:.7rem 2rem;border-radius:12px;">Go Home</a>
</div>
<?php require BASE_PATH . '/public/layout/app_footer.php'; ?>
