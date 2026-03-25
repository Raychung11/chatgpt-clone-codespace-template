<?php
/**
 * API – Customers (self-service profile)
 * /api/routes/customers.php
 *
 * GET  /api/customers/profile   – My full profile
 * PUT  /api/customers/profile   – Update profile
 * GET  /api/customers/dashboard – Dashboard summary
 */

require_once BASE_PATH . '/api/middleware.php';
$user = api_require_auth();

switch ($action) {

    // GET /api/customers/profile
    case 'profile':
        if ($method === 'GET') {
            $profile = Database::fetchOne(
                'SELECT cp.*, o.name AS preferred_outlet_name
                 FROM customer_profiles cp
                 LEFT JOIN outlets o ON o.id = cp.preferred_outlet_id
                 WHERE cp.user_id = ?',
                [$user['id']]
            );

            json_success('OK', [
                'user'    => [
                    'id'    => $user['id'],
                    'name'  => $user['name'],
                    'phone' => $user['phone'],
                    'email' => $user['email'],
                ],
                'profile' => $profile,
            ]);

        } elseif ($method === 'PUT') {
            $name        = sanitize_string($body['name']             ?? $user['name']);
            $email       = sanitize_string($body['email']            ?? '');
            $dob         = sanitize_string($body['date_of_birth']    ?? '');
            $gender      = in_array($body['gender'] ?? '', ['male','female','other']) ? $body['gender'] : null;
            $address     = sanitize_string($body['address']          ?? '', 255);
            $prefOutlet  = sanitize_int($body['preferred_outlet_id'] ?? 0) ?: null;

            if ($email && !validate_email($email)) json_error('Invalid email address.');

            Database::execute('UPDATE users SET name=?, email=? WHERE id=?', [$name, $email?:null, $user['id']]);
            Database::execute(
                'UPDATE customer_profiles SET date_of_birth=?, gender=?, address=?, preferred_outlet_id=? WHERE user_id=?',
                [$dob?:null, $gender, $address?:null, $prefOutlet, $user['id']]
            );

            json_success('Profile updated.');
        } else {
            json_error('Method not allowed.', null, 405);
        }
        break;

    // GET /api/customers/dashboard
    case 'dashboard':
        if ($method !== 'GET') json_error('Method not allowed.', null, 405);

        $profile = Database::fetchOne(
            'SELECT total_points, lifetime_points, tier FROM customer_profiles WHERE user_id = ?',
            [$user['id']]
        );

        $totalOrders = (int) Database::fetchOne(
            'SELECT COUNT(*) AS c FROM orders WHERE user_id = ? AND status = "completed"', [$user['id']]
        )['c'];

        $totalVisits = (int) Database::fetchOne(
            'SELECT COUNT(*) AS c FROM reservations WHERE user_id = ? AND status = "completed"', [$user['id']]
        )['c'];

        $latestTransactions = Database::fetchAll(
            'SELECT points, type, description, created_at FROM loyalty_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5',
            [$user['id']]
        );

        $availableRewards = (int) Database::fetchOne(
            "SELECT COUNT(*) AS c FROM rewards WHERE status='active' AND points_required <= ? AND (valid_until IS NULL OR valid_until >= CURDATE())",
            [(int)($profile['total_points'] ?? 0)]
        )['c'];

        $unread = Notification::getUnreadCount($user['id']);

        json_success('OK', [
            'name'              => $user['name'],
            'tier'              => $profile['tier'] ?? 'bronze',
            'total_points'      => (int)($profile['total_points']    ?? 0),
            'lifetime_points'   => (int)($profile['lifetime_points'] ?? 0),
            'total_orders'      => $totalOrders,
            'total_visits'      => $totalVisits,
            'available_rewards' => $availableRewards,
            'unread_notifications' => $unread,
            'recent_transactions'  => $latestTransactions,
        ]);
        break;

    default:
        json_error('Invalid customers endpoint.', null, 404);
}
