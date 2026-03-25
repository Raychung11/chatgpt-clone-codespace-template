<?php
/**
 * API – Orders Routes
 * /api/routes/orders.php
 *
 * GET  /api/orders/my       – My orders
 * POST /api/orders/create   – Create an order
 * GET  /api/orders/detail   – Single order with items
 */

require_once BASE_PATH . '/api/middleware.php';
$user = api_require_auth();

switch ($action) {

    // GET /api/orders/my
    case 'my':
        if ($method !== 'GET') json_error('Method not allowed.', null, 405);

        $orders = Database::fetchAll(
            'SELECT o.id, o.order_no, o.order_type, o.total, o.payment_status, o.status,
                    o.points_earned, o.created_at, ot.name AS outlet_name
             FROM orders o
             JOIN outlets ot ON ot.id = o.outlet_id
             WHERE o.user_id = ?
             ORDER BY o.created_at DESC
             LIMIT 30',
            [$user['id']]
        );

        json_success('OK', ['orders' => $orders]);
        break;

    // POST /api/orders/create
    case 'create':
        if ($method !== 'POST') json_error('Method not allowed.', null, 405);

        $errors = validate_required($body, ['outlet_id', 'items']);
        if ($errors) json_error(implode(' ', $errors));

        $outletId   = sanitize_int($body['outlet_id']);
        $orderType  = in_array($body['order_type'] ?? '', ['dine_in','takeaway','delivery']) ? $body['order_type'] : 'dine_in';
        $tableNo    = sanitize_string($body['table_no'] ?? '', 20);
        $notes      = sanitize_string($body['notes']    ?? '', 500);
        $items      = $body['items'] ?? [];
        $pointsUsed = sanitize_int($body['points_used'] ?? 0);

        if (empty($items) || !is_array($items)) json_error('At least one item is required.');

        // Validate outlet
        $outlet = Database::fetchOne("SELECT * FROM outlets WHERE id = ? AND status = 'active'", [$outletId]);
        if (!$outlet) json_error('Outlet not found or inactive.');

        Database::beginTransaction();
        try {
            $settings  = get_settings(['tax_rate','loyalty_points_per_myr']);
            $taxRate   = (float)($settings['tax_rate'] ?? 0.06);
            $ppm       = (int)($settings['loyalty_points_per_myr'] ?? 1);

            $subtotal  = 0;
            $lineItems = [];

            foreach ($items as $item) {
                $menuItemId = sanitize_int($item['menu_item_id'] ?? 0);
                $qty        = max(1, sanitize_int($item['qty']      ?? 1));
                $itemNotes  = sanitize_string($item['notes'] ?? '', 100);

                $menuItem = Database::fetchOne(
                    'SELECT id, name, price, is_available FROM menu_items WHERE id = ?',
                    [$menuItemId]
                );
                if (!$menuItem || !$menuItem['is_available']) {
                    throw new Exception("Menu item #{$menuItemId} not available.");
                }

                $lineTotal = $menuItem['price'] * $qty;
                $subtotal += $lineTotal;

                $lineItems[] = [
                    'menu_item_id' => $menuItemId,
                    'name'         => $menuItem['name'],
                    'price'        => $menuItem['price'],
                    'qty'          => $qty,
                    'subtotal'     => $lineTotal,
                    'notes'        => $itemNotes ?: null,
                ];
            }

            $tax      = round($subtotal * $taxRate, 2);
            $discount = 0;

            // Points redemption (1 point = MYR 0.01)
            if ($pointsUsed > 0) {
                $profile = Database::fetchOne('SELECT total_points FROM customer_profiles WHERE user_id = ?', [$user['id']]);
                $available = (int)($profile['total_points'] ?? 0);
                $pointsUsed = min($pointsUsed, $available);
                $discount   = round($pointsUsed * 0.01, 2);
            }

            $total       = max(0, $subtotal + $tax - $discount);
            $ptsEarned   = (int) floor($total * $ppm);
            $orderNo     = generate_order_no();

            $orderId = Database::insert(
                'INSERT INTO orders (order_no,user_id,outlet_id,order_type,table_no,subtotal,tax,discount,total,points_earned,points_used,status,notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,"pending",?)',
                [$orderNo,$user['id'],$outletId,$orderType,$tableNo?:null,$subtotal,$tax,$discount,$total,$ptsEarned,$pointsUsed,$notes?:null]
            );

            // Insert items
            foreach ($lineItems as $li) {
                Database::insert(
                    'INSERT INTO order_items (order_id,menu_item_id,name,price,qty,subtotal,notes) VALUES (?,?,?,?,?,?,?)',
                    [$orderId,$li['menu_item_id'],$li['name'],$li['price'],$li['qty'],$li['subtotal'],$li['notes']]
                );
            }

            // Deduct points if used
            if ($pointsUsed > 0) {
                add_loyalty_points($user['id'], -$pointsUsed, 'redeem', 'order', $orderId, 'Points used for order');
            }

            Database::commit();

            // Notification
            Notification::send($user['id'], 'Order Placed! 🛍️', "Order #{$orderNo} placed. Total: " . format_currency($total), 'order', 'in_app', 'order', $orderId);

            json_success('Order created.', [
                'order_id'      => $orderId,
                'order_no'      => $orderNo,
                'subtotal'      => $subtotal,
                'tax'           => $tax,
                'discount'      => $discount,
                'total'         => $total,
                'points_earned' => $ptsEarned,
                'points_used'   => $pointsUsed,
                'status'        => 'pending',
            ], 201);

        } catch (Exception $e) {
            Database::rollback();
            json_error($e->getMessage(), null, 400);
        }
        break;

    // GET /api/orders/detail?id=x
    case 'detail':
        if ($method !== 'GET') json_error('Method not allowed.', null, 405);

        $orderId = sanitize_int($_GET['id'] ?? 0);
        if (!$orderId) json_error('id is required.');

        $order = Database::fetchOne(
            'SELECT o.*, ot.name AS outlet_name, ot.address AS outlet_address
             FROM orders o JOIN outlets ot ON ot.id=o.outlet_id
             WHERE o.id = ? AND o.user_id = ?',
            [$orderId, $user['id']]
        );
        if (!$order) json_error('Order not found.', null, 404);

        $order['items'] = Database::fetchAll(
            'SELECT * FROM order_items WHERE order_id = ?',
            [$orderId]
        );

        json_success('OK', ['order' => $order]);
        break;

    default:
        json_error('Invalid orders endpoint.', null, 404);
}
