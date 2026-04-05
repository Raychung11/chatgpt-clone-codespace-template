<?php
/**
 * API – Menu Routes
 * /api/routes/menu.php
 *
 * Public (no auth required):
 *   GET /api/menu/categories          – List active categories (+ item count)
 *   GET /api/menu/items               – List available items (?outlet_id=&category_id=&featured=1)
 *   GET /api/menu/categories-with-items – Full menu grouped by category (?outlet_id=)
 *
 * Auth required:
 *   GET /api/menu/featured            – Featured items for the home page
 */

// menu browsing is public – do NOT call api_require_auth() globally
// Only specific sub-actions require auth; handled inline.

switch ($action) {

    // ----------------------------------------------------------------
    // GET /api/menu/categories
    // ----------------------------------------------------------------
    case 'categories':
        if ($method !== 'GET') json_error('Method not allowed.', null, 405);

        $outletId = sanitize_int($_GET['outlet_id'] ?? 0);

        $where  = "mc.status = 'active'";
        $params = [];
        if ($outletId) {
            $where   .= ' AND (mc.outlet_id = ? OR mc.outlet_id IS NULL)';
            $params[] = $outletId;
        }

        $cats = Database::fetchAll(
            "SELECT mc.id, mc.name, mc.sort_order, mc.outlet_id,
                    COUNT(mi.id) AS item_count
             FROM menu_categories mc
             LEFT JOIN menu_items mi ON mi.category_id = mc.id AND mi.is_available = 1
             WHERE {$where}
             GROUP BY mc.id
             ORDER BY mc.sort_order ASC, mc.name ASC",
            $params
        );

        json_success('OK', ['categories' => $cats]);
        break;

    // ----------------------------------------------------------------
    // GET /api/menu/items?category_id=&outlet_id=&featured=
    // ----------------------------------------------------------------
    case 'items':
        if ($method !== 'GET') json_error('Method not allowed.', null, 405);

        $categoryId = sanitize_int($_GET['category_id'] ?? 0);
        $outletId   = sanitize_int($_GET['outlet_id']   ?? 0);
        $featured   = isset($_GET['featured']) && $_GET['featured'] == '1';

        $where  = 'mi.is_available = 1';
        $params = [];

        if ($categoryId) {
            $where   .= ' AND mi.category_id = ?';
            $params[] = $categoryId;
        }
        if ($featured) {
            $where .= ' AND mi.is_featured = 1';
        }
        if ($outletId) {
            $where   .= ' AND (mc.outlet_id = ? OR mc.outlet_id IS NULL)';
            $params[] = $outletId;
        }

        $items = Database::fetchAll(
            "SELECT mi.id, mi.name, mi.description, mi.price, mi.image_url,
                    mi.is_featured, mi.sort_order, mi.category_id,
                    mc.name AS category_name
             FROM menu_items mi
             JOIN menu_categories mc ON mc.id = mi.category_id
             WHERE {$where}
             ORDER BY mc.sort_order ASC, mi.sort_order ASC, mi.name ASC",
            $params
        );

        json_success('OK', ['items' => $items]);
        break;

    // ----------------------------------------------------------------
    // GET /api/menu/categories-with-items?outlet_id=
    // Full menu for customer app (one request)
    // ----------------------------------------------------------------
    case 'categories-with-items':
        if ($method !== 'GET') json_error('Method not allowed.', null, 405);

        $outletId = sanitize_int($_GET['outlet_id'] ?? 0);

        $catWhere  = "mc.status = 'active'";
        $catParams = [];
        if ($outletId) {
            $catWhere   .= ' AND (mc.outlet_id = ? OR mc.outlet_id IS NULL)';
            $catParams[] = $outletId;
        }

        $cats = Database::fetchAll(
            "SELECT mc.id, mc.name, mc.sort_order
             FROM menu_categories mc
             WHERE {$catWhere}
             ORDER BY mc.sort_order ASC, mc.name ASC",
            $catParams
        );

        if (empty($cats)) {
            json_success('OK', ['menu' => []]);
            break;
        }

        $catIds     = array_column($cats, 'id');
        $inPlaceholders = implode(',', array_fill(0, count($catIds), '?'));

        $itemWhere  = "mi.is_available = 1 AND mi.category_id IN ({$inPlaceholders})";
        $itemParams = $catIds;

        if ($outletId) {
            $itemWhere   .= ' AND (mc.outlet_id = ? OR mc.outlet_id IS NULL)';
            $itemParams[] = $outletId;
        }

        $allItems = Database::fetchAll(
            "SELECT mi.id, mi.name, mi.description, mi.price, mi.image_url,
                    mi.is_featured, mi.sort_order, mi.category_id
             FROM menu_items mi
             JOIN menu_categories mc ON mc.id = mi.category_id
             WHERE {$itemWhere}
             ORDER BY mi.sort_order ASC, mi.name ASC",
            $itemParams
        );

        // Group items by category
        $itemsByCategory = [];
        foreach ($allItems as $item) {
            $itemsByCategory[$item['category_id']][] = [
                'id'          => (int) $item['id'],
                'name'        => $item['name'],
                'description' => $item['description'],
                'price'       => (float) $item['price'],
                'image_url'   => $item['image_url'],
                'is_featured' => (bool) $item['is_featured'],
            ];
        }

        $menu = [];
        foreach ($cats as $cat) {
            $catItems = $itemsByCategory[$cat['id']] ?? [];
            if (empty($catItems)) continue; // skip empty categories
            $menu[] = [
                'id'    => (int) $cat['id'],
                'name'  => $cat['name'],
                'items' => $catItems,
            ];
        }

        json_success('OK', ['menu' => $menu]);
        break;

    // ----------------------------------------------------------------
    // GET /api/menu/featured  (requires auth – for personalised home)
    // ----------------------------------------------------------------
    case 'featured':
        if ($method !== 'GET') json_error('Method not allowed.', null, 405);
        require_once BASE_PATH . '/api/middleware.php';
        $user = api_require_auth();

        $outletId = sanitize_int($_GET['outlet_id'] ?? 0);

        $where  = 'mi.is_available = 1 AND mi.is_featured = 1';
        $params = [];
        if ($outletId) {
            $where   .= ' AND (mc.outlet_id = ? OR mc.outlet_id IS NULL)';
            $params[] = $outletId;
        }

        $featured = Database::fetchAll(
            "SELECT mi.id, mi.name, mi.description, mi.price, mi.image_url, mc.name AS category_name
             FROM menu_items mi
             JOIN menu_categories mc ON mc.id = mi.category_id
             WHERE {$where}
             ORDER BY mi.sort_order ASC
             LIMIT 10",
            $params
        );

        json_success('OK', ['items' => $featured]);
        break;

    default:
        json_error('Invalid menu endpoint.', null, 404);
}
