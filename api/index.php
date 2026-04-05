<?php
/**
 * API Router / Front Controller
 * /api/index.php
 *
 * All API requests routed through here via .htaccess
 * Endpoints: /api/{resource}/{action}
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/inc/bootstrap.php';

// CORS headers (adjust origin for production)
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Parse URI: /api/{resource}/{action?}/{id?}
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = preg_replace('#^/api/?#', '', $uri);
$parts  = array_filter(explode('/', trim($uri, '/')));
$parts  = array_values($parts);

$resource = $parts[0] ?? '';
$action   = $parts[1] ?? '';
$id       = isset($parts[2]) ? (int) $parts[2] : null;

$method = strtoupper($_SERVER['REQUEST_METHOD']);

// Parse JSON body
$body = [];
if (in_array($method, ['POST','PUT','PATCH'])) {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? $_POST;
}

// Route to resource handlers
$routes = [
    'auth'          => BASE_PATH . '/api/routes/auth.php',
    'customers'     => BASE_PATH . '/api/routes/customers.php',
    'loyalty'       => BASE_PATH . '/api/routes/loyalty.php',
    'rewards'       => BASE_PATH . '/api/routes/rewards.php',
    'outlets'       => BASE_PATH . '/api/routes/outlets.php',
    'reservations'  => BASE_PATH . '/api/routes/reservations.php',
    'orders'        => BASE_PATH . '/api/routes/orders.php',
    'menu'          => BASE_PATH . '/api/routes/menu.php',
    'referrals'     => BASE_PATH . '/api/routes/referrals.php',
    'notifications' => BASE_PATH . '/api/routes/notifications.php',
];

if (isset($routes[$resource]) && file_exists($routes[$resource])) {
    require $routes[$resource];
} else {
    json_error('Endpoint not found.', null, 404);
}
