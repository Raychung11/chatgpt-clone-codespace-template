<?php
/**
 * API – Loyalty Routes
 * /api/routes/loyalty.php
 *
 * GET  /api/loyalty/balance      – Get current points balance
 * GET  /api/loyalty/history      – Transaction history
 * GET  /api/loyalty/tiers        – Tier info & thresholds
 */

require_once BASE_PATH . '/api/middleware.php';
$user = api_require_auth();

switch ($action) {

    // GET /api/loyalty/balance
    case 'balance':
        if ($method !== 'GET') json_error('Method not allowed.', null, 405);

        $profile = Database::fetchOne(
            'SELECT total_points, lifetime_points, tier FROM customer_profiles WHERE user_id = ?',
            [$user['id']]
        );

        $settings = get_settings(['tier_silver_threshold','tier_gold_threshold','tier_platinum_threshold']);
        $tiers = [
            'bronze'   => 0,
            'silver'   => (int)($settings['tier_silver_threshold']    ?? 500),
            'gold'     => (int)($settings['tier_gold_threshold']      ?? 2000),
            'platinum' => (int)($settings['tier_platinum_threshold']  ?? 5000),
        ];

        $currentTier  = $profile['tier'] ?? 'bronze';
        $tierKeys     = array_keys($tiers);
        $currentIndex = array_search($currentTier, $tierKeys);
        $nextTier     = $tierKeys[$currentIndex + 1] ?? null;
        $pointsToNext = $nextTier ? max(0, $tiers[$nextTier] - (int)($profile['lifetime_points'] ?? 0)) : 0;

        json_success('OK', [
            'total_points'    => (int)($profile['total_points']    ?? 0),
            'lifetime_points' => (int)($profile['lifetime_points'] ?? 0),
            'tier'            => $currentTier,
            'next_tier'       => $nextTier,
            'points_to_next_tier' => $pointsToNext,
            'tier_thresholds' => $tiers,
        ]);
        break;

    // GET /api/loyalty/history
    case 'history':
        if ($method !== 'GET') json_error('Method not allowed.', null, 405);

        $page    = max(1, sanitize_int($_GET['page']  ?? 1));
        $perPage = min(50, max(1, sanitize_int($_GET['per_page'] ?? 20)));
        $offset  = ($page - 1) * $perPage;

        $total = (int) Database::fetchOne(
            'SELECT COUNT(*) AS c FROM loyalty_transactions WHERE user_id = ?', [$user['id']]
        )['c'];

        $transactions = Database::fetchAll(
            'SELECT id, points, type, description, balance_after, reference_type, reference_id, created_at
             FROM loyalty_transactions
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?',
            [$user['id'], $perPage, $offset]
        );

        json_success('OK', [
            'total'        => $total,
            'page'         => $page,
            'per_page'     => $perPage,
            'transactions' => $transactions,
        ]);
        break;

    // GET /api/loyalty/tiers
    case 'tiers':
        if ($method !== 'GET') json_error('Method not allowed.', null, 405);

        $settings = get_settings(['tier_silver_threshold','tier_gold_threshold','tier_platinum_threshold']);

        json_success('OK', [
            'tiers' => [
                ['name'=>'Bronze',   'min_points'=>0,                                            'icon'=>'🥉', 'color'=>'#cd7f32'],
                ['name'=>'Silver',   'min_points'=>(int)($settings['tier_silver_threshold']??500),   'icon'=>'🥈', 'color'=>'#a8a9ad'],
                ['name'=>'Gold',     'min_points'=>(int)($settings['tier_gold_threshold']??2000),     'icon'=>'🥇', 'color'=>'#ffd700'],
                ['name'=>'Platinum', 'min_points'=>(int)($settings['tier_platinum_threshold']??5000), 'icon'=>'💎', 'color'=>'#b5c4d4'],
            ],
        ]);
        break;

    default:
        json_error('Invalid loyalty endpoint.', null, 404);
}
