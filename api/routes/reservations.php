<?php
/**
 * API – Reservations Routes
 * /api/routes/reservations.php
 *
 * GET  /api/reservations/my          – My reservations
 * POST /api/reservations/create      – Book a reservation
 * PUT  /api/reservations/cancel      – Cancel reservation
 * GET  /api/reservations/availability – Check time slots
 */

require_once BASE_PATH . '/api/middleware.php';
$user = api_require_auth();

switch ($action) {

    // GET /api/reservations/my
    case 'my':
        if ($method !== 'GET') json_error('Method not allowed.', null, 405);

        $reservations = Database::fetchAll(
            'SELECT r.id, r.reservation_no, r.party_size, r.reserved_date, r.reserved_time,
                    r.occasion, r.special_request, r.status, r.points_earned, r.created_at,
                    o.name AS outlet_name, o.address AS outlet_address, o.phone AS outlet_phone
             FROM reservations r
             JOIN outlets o ON o.id = r.outlet_id
             WHERE r.user_id = ?
             ORDER BY r.reserved_date DESC, r.reserved_time DESC
             LIMIT 20',
            [$user['id']]
        );

        json_success('OK', ['reservations' => $reservations]);
        break;

    // POST /api/reservations/create
    case 'create':
        if ($method !== 'POST') json_error('Method not allowed.', null, 405);

        $errors = validate_required($body, ['outlet_id', 'party_size', 'reserved_date', 'reserved_time']);
        if ($errors) json_error(implode(' ', $errors));

        $outletId     = sanitize_int($body['outlet_id']);
        $partySize    = min(20, max(1, sanitize_int($body['party_size'])));
        $reservedDate = sanitize_string($body['reserved_date'] ?? '');
        $reservedTime = sanitize_string($body['reserved_time'] ?? '');
        $occasion     = sanitize_string($body['occasion']         ?? '', 80);
        $specialReq   = sanitize_string($body['special_request']  ?? '', 500);

        // Validate outlet
        $outlet = Database::fetchOne("SELECT id, name FROM outlets WHERE id = ? AND status = 'active'", [$outletId]);
        if (!$outlet) json_error('Outlet not found or inactive.');

        // Validate date (not in the past)
        if (strtotime($reservedDate) < strtotime('today')) {
            json_error('Reservation date cannot be in the past.');
        }

        $resNo = generate_reservation_no();

        $resId = Database::insert(
            'INSERT INTO reservations (reservation_no, user_id, outlet_id, party_size, reserved_date, reserved_time, occasion, special_request, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, "pending")',
            [$resNo, $user['id'], $outletId, $partySize, $reservedDate, $reservedTime, $occasion ?: null, $specialReq ?: null]
        );

        // Notify customer
        Notification::send(
            $user['id'],
            'Reservation Confirmed! 🍽️',
            "Your reservation at {$outlet['name']} on {$reservedDate} at {$reservedTime} for {$partySize} pax has been received. Ref: {$resNo}",
            'reservation', 'in_app', 'reservation', $resId
        );

        json_success('Reservation created successfully.', [
            'reservation_id'  => $resId,
            'reservation_no'  => $resNo,
            'status'          => 'pending',
        ], 201);
        break;

    // PUT /api/reservations/cancel
    case 'cancel':
        if ($method !== 'PUT') json_error('Method not allowed.', null, 405);

        $resId = sanitize_int($body['reservation_id'] ?? 0);
        if (!$resId) json_error('reservation_id is required.');

        $res = Database::fetchOne(
            'SELECT * FROM reservations WHERE id = ? AND user_id = ?',
            [$resId, $user['id']]
        );
        if (!$res) json_error('Reservation not found.', null, 404);

        if (in_array($res['status'], ['completed','cancelled'])) {
            json_error('This reservation cannot be cancelled.');
        }

        // Prevent cancellation within 2 hours
        $resDateTime = $res['reserved_date'] . ' ' . $res['reserved_time'];
        if (strtotime($resDateTime) < strtotime('+2 hours')) {
            json_error('Reservations cannot be cancelled within 2 hours of the booking time.');
        }

        Database::execute('UPDATE reservations SET status = "cancelled" WHERE id = ?', [$resId]);

        json_success('Reservation cancelled.');
        break;

    // GET /api/reservations/availability?outlet_id=1&date=2024-12-25
    case 'availability':
        if ($method !== 'GET') json_error('Method not allowed.', null, 405);

        $outletId     = sanitize_int($_GET['outlet_id'] ?? 0);
        $date         = sanitize_string($_GET['date']   ?? date('Y-m-d'));

        if (!$outletId) json_error('outlet_id is required.');

        // Count existing active reservations per time slot
        $booked = Database::fetchAll(
            "SELECT reserved_time, COUNT(*) AS count
             FROM reservations
             WHERE outlet_id = ? AND reserved_date = ? AND status IN ('pending','confirmed','seated')
             GROUP BY reserved_time",
            [$outletId, $date]
        );
        $bookedMap = array_column($booked, 'count', 'reserved_time');

        // Generate time slots 11:00 – 21:30, every 30 min
        $slots = [];
        $start = strtotime('11:00');
        $end   = strtotime('21:30');
        for ($t = $start; $t <= $end; $t += 1800) {
            $timeStr   = date('H:i', $t);
            $maxTables = 10; // assumption: 10 tables per slot
            $booked_c  = (int)($bookedMap[$timeStr . ':00'] ?? $bookedMap[$timeStr] ?? 0);
            $slots[]   = [
                'time'      => $timeStr,
                'available' => $booked_c < $maxTables,
                'remaining' => max(0, $maxTables - $booked_c),
            ];
        }

        json_success('OK', ['date' => $date, 'slots' => $slots]);
        break;

    default:
        json_error('Invalid reservations endpoint.', null, 404);
}
