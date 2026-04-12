<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_role(ROLE_SELLER, '/register.php?type=seller');

$seller = Database::fetchOne('SELECT * FROM sellers WHERE user_id = ?', [auth_user_id()]);
if (!$seller) redirect('/register.php?type=seller');

$userId      = auth_user_id();
$portalType  = 'seller';
$page_title  = is_lang('zh') ? '推荐好友' : 'Refer a Friend';
include INC_PATH . '/header.php';
?>
<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="portal-content">
    <?= render_flash() ?>
    <?php include INC_PATH . '/partials/referral_dashboard.php'; ?>
</div>
</div>
<?php include INC_PATH . '/footer.php'; ?>
