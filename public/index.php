<?php
/**
 * Customer PWA Router
 * /public/index.php
 */

// Defensive BASE_PATH – works whether Apache rewrites or file is hit directly
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Show errors during setup; config will override once loaded
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Bootstrap
$bootstrapFile = BASE_PATH . '/inc/bootstrap.php';
if (!file_exists($bootstrapFile)) {
    http_response_code(500);
    die('<h3>Setup error:</h3><p>Cannot find <code>inc/bootstrap.php</code>. '
      . 'Please verify files are uploaded to the correct directory.</p>'
      . '<p>Expected path: <code>' . htmlspecialchars($bootstrapFile) . '</code></p>');
}
require_once $bootstrapFile;

// Parse route from URI  e.g. /app/dashboard → "dashboard"
$uri   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri   = preg_replace('#^/app/?#', '', $uri);
$route = trim($uri, '/') ?: 'home';

// Customer PWA uses Bearer token auth handled entirely in JavaScript.
// PHP session auth is NOT used for customer routes – removing server-side
// redirect prevents the JS-token ↔ PHP-session redirect loop.

// Route map
$pages = [
    ''             => 'pages/home.php',
    'home'         => 'pages/home.php',
    'login'        => 'pages/login.php',
    'register'     => 'pages/register.php',
    'dashboard'    => 'pages/dashboard.php',
    'rewards'      => 'pages/rewards.php',
    'outlets'      => 'pages/outlets.php',
    'reservations' => 'pages/reservations.php',
    'orders'       => 'pages/orders.php',
    'profile'      => 'pages/profile.php',
    'notifications'=> 'pages/notifications.php',
    'referrals'    => 'pages/referrals.php',
];

$file = isset($pages[$route]) ? __DIR__ . '/' . $pages[$route] : null;

if ($file && file_exists($file)) {
    require $file;
} else {
    http_response_code(404);
    require __DIR__ . '/pages/404.php';
}
