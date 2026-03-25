<?php
/**
 * Admin Router
 * /admin/index.php
 *
 * Simple front-controller for the admin panel.
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/inc/bootstrap.php';

Auth::requireAdmin();

// Parse requested page from URL
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri  = preg_replace('#^/admin/?#', '', $uri);
$uri  = trim($uri, '/');
$page = empty($uri) ? 'dashboard' : $uri;

// Map routes to files
$routes = [
    'dashboard'           => 'pages/dashboard.php',
    'customers'           => 'pages/customers.php',
    'customers/view'      => 'pages/customers_view.php',
    'customers/edit'      => 'pages/customers_edit.php',
    'outlets'             => 'pages/outlets.php',
    'outlets/create'      => 'pages/outlets_form.php',
    'outlets/edit'        => 'pages/outlets_form.php',
    'rewards'             => 'pages/rewards.php',
    'rewards/create'      => 'pages/rewards_form.php',
    'rewards/edit'        => 'pages/rewards_form.php',
    'campaigns'           => 'pages/campaigns.php',
    'campaigns/create'    => 'pages/campaigns_form.php',
    'campaigns/edit'      => 'pages/campaigns_form.php',
    'reservations'        => 'pages/reservations.php',
    'orders'              => 'pages/orders.php',
    'orders/view'         => 'pages/orders_view.php',
    'loyalty'             => 'pages/loyalty.php',
    'notifications'       => 'pages/notifications.php',
    'settings'            => 'pages/settings.php',
    'profile'             => 'pages/profile.php',
    'logout'              => 'actions/logout.php',
];

if (isset($routes[$page])) {
    $file = __DIR__ . '/' . $routes[$page];
    if (file_exists($file)) {
        require $file;
        exit;
    }
}

// 404 fallback
http_response_code(404);
require __DIR__ . '/pages/404.php';
