<?php
/**
 * API – Outlets / Store Locator
 * /api/routes/outlets.php
 *
 * GET /api/outlets/list    – All active outlets
 * GET /api/outlets/nearby  – Outlets near lat/lng
 * GET /api/outlets/detail  – Single outlet detail
 * GET /api/outlets/menu    – Outlet menu
 */

// No auth required for outlet browsing

switch ($action) {

    // GET /api/outlets/list
    case 'list':
        if ($method !== 'GET') json_error('Method not allowed.', null, 405);

        $outlets = Database::fetchAll(
            "SELECT id, name, address, city, state, postcode, phone, lat, lng, image_url, opening_hours, status
             FROM outlets
             WHERE status = 'active'
             ORDER BY name"
        );

        // Decode opening hours JSON
        foreach ($outlets as &$o) {
            $o['opening_hours'] = json_decode($o['opening_hours'] ?? '{}', true);
        }

        json_success('OK', ['outlets' => $outlets]);
        break;

    // GET /api/outlets/nearby?lat=x&lng=y&radius=10
    case 'nearby':
        if ($method !== 'GET') json_error('Method not allowed.', null, 405);

        $lat    = floatval($_GET['lat']    ?? 0);
        $lng    = floatval($_GET['lng']    ?? 0);
        $radius = min(50, max(1, floatval($_GET['radius'] ?? 10)));

        if (!$lat || !$lng) json_error('lat and lng are required.');

        // Haversine formula in SQL
        $outlets = Database::fetchAll(
            "SELECT id, name, address, city, phone, lat, lng, image_url, opening_hours,
                    (6371 * ACOS(
                        COS(RADIANS(?)) * COS(RADIANS(lat)) *
                        COS(RADIANS(lng) - RADIANS(?)) +
                        SIN(RADIANS(?)) * SIN(RADIANS(lat))
                    )) AS distance_km
             FROM outlets
             WHERE status = 'active' AND lat IS NOT NULL AND lng IS NOT NULL
             HAVING distance_km <= ?
             ORDER BY distance_km ASC",
            [$lat, $lng, $lat, $radius]
        );

        foreach ($outlets as &$o) {
            $o['opening_hours'] = json_decode($o['opening_hours'] ?? '{}', true);
            $o['distance_km']   = round((float)$o['distance_km'], 2);
        }

        json_success('OK', ['outlets' => $outlets]);
        break;

    // GET /api/outlets/detail?id=1
    case 'detail':
        if ($method !== 'GET') json_error('Method not allowed.', null, 405);

        $outletId = sanitize_int($_GET['id'] ?? 0);
        if (!$outletId) json_error('id is required.');

        $outlet = Database::fetchOne(
            "SELECT * FROM outlets WHERE id = ? AND status = 'active'",
            [$outletId]
        );
        if (!$outlet) json_error('Outlet not found.', null, 404);

        $outlet['opening_hours'] = json_decode($outlet['opening_hours'] ?? '{}', true);

        json_success('OK', ['outlet' => $outlet]);
        break;

    // GET /api/outlets/menu?outlet_id=1
    case 'menu':
        if ($method !== 'GET') json_error('Method not allowed.', null, 405);

        $outletId = sanitize_int($_GET['outlet_id'] ?? 0);

        $categories = Database::fetchAll(
            "SELECT * FROM menu_categories
             WHERE status = 'active' AND (outlet_id IS NULL OR outlet_id = ?)
             ORDER BY sort_order, name",
            [$outletId ?: 0]
        );

        foreach ($categories as &$cat) {
            $cat['items'] = Database::fetchAll(
                "SELECT id, name, description, image_url, price, is_available, is_featured
                 FROM menu_items
                 WHERE category_id = ? AND is_available = 1
                 ORDER BY sort_order, name",
                [$cat['id']]
            );
        }

        json_success('OK', ['categories' => $categories]);
        break;

    default:
        json_error('Invalid outlets endpoint.', null, 404);
}
