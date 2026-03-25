<?php
/**
 * Admin Logout
 * /admin/actions/logout.php
 */

define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/inc/bootstrap.php';

Auth::requireAdmin();
admin_log('logout', 'auth');
Auth::logout();

header('Location: /admin/login');
exit;
