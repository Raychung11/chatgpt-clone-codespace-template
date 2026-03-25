<?php
$pageTitle  = '404 Not Found';
$activePage = '';
require __DIR__ . '/../layout/header.php';
?>
<div class="text-center py-5">
    <div style="font-size:4rem;">🔍</div>
    <h4 class="mt-3">Page Not Found</h4>
    <p class="text-muted">The page you're looking for doesn't exist.</p>
    <a href="/admin/dashboard" class="btn btn-primary">Back to Dashboard</a>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
