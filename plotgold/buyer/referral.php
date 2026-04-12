<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_role(ROLE_BUYER, '/register.php?type=buyer');

$buyer = Database::fetchOne('SELECT * FROM buyers WHERE user_id = ?', [auth_user_id()]);
if (!$buyer) redirect('/register.php?type=buyer');

$userId      = auth_user_id();
$portalType  = 'buyer';
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
