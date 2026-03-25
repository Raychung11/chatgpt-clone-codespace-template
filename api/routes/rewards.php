<?php
/**
 * API – Rewards Routes
 * /api/routes/rewards.php
 *
 * GET  /api/rewards/list        – List available rewards
 * GET  /api/rewards/my          – My redemptions
 * POST /api/rewards/redeem      – Redeem a reward
 */

require_once BASE_PATH . '/api/middleware.php';
$user = api_require_auth();

switch ($action) {

    // GET /api/rewards/list
    case 'list':
        if ($method !== 'GET') json_error('Method not allowed.', null, 405);

        $rewards = Database::fetchAll(
            "SELECT r.id, r.name, r.description, r.image_url, r.points_required,
                    r.reward_type, r.discount_value, r.discount_type, r.stock,
                    r.valid_from, r.valid_until, o.name AS outlet_name
             FROM rewards r
             LEFT JOIN outlets o ON o.id = r.outlet_id
             WHERE r.status = 'active'
               AND (r.valid_until IS NULL OR r.valid_until >= CURDATE())
               AND (r.stock IS NULL OR r.stock > 0)
             ORDER BY r.points_required ASC"
        );

        json_success('OK', ['rewards' => $rewards]);
        break;

    // GET /api/rewards/my
    case 'my':
        if ($method !== 'GET') json_error('Method not allowed.', null, 405);

        $redemptions = Database::fetchAll(
            'SELECT rr.*, r.name AS reward_name, r.image_url, r.reward_type
             FROM reward_redemptions rr
             JOIN rewards r ON r.id = rr.reward_id
             WHERE rr.user_id = ?
             ORDER BY rr.redeemed_at DESC
             LIMIT 50',
            [$user['id']]
        );

        json_success('OK', ['redemptions' => $redemptions]);
        break;

    // POST /api/rewards/redeem
    case 'redeem':
        if ($method !== 'POST') json_error('Method not allowed.', null, 405);

        $rewardId = sanitize_int($body['reward_id'] ?? 0);
        if (!$rewardId) json_error('reward_id is required.');

        // Get reward
        $reward = Database::fetchOne(
            "SELECT * FROM rewards WHERE id = ? AND status = 'active'
             AND (valid_until IS NULL OR valid_until >= CURDATE())
             AND (stock IS NULL OR stock > 0)",
            [$rewardId]
        );
        if (!$reward) json_error('Reward not available or expired.');

        // Get customer points
        $profile = Database::fetchOne('SELECT total_points FROM customer_profiles WHERE user_id = ?', [$user['id']]);
        $currentPoints = (int)($profile['total_points'] ?? 0);

        if ($currentPoints < $reward['points_required']) {
            json_error("Insufficient points. You have {$currentPoints} pts but need {$reward['points_required']} pts.");
        }

        Database::beginTransaction();
        try {
            $voucherCode = generate_voucher_code();

            // Create redemption record
            $redemptionId = Database::insert(
                'INSERT INTO reward_redemptions (user_id, reward_id, points_used, voucher_code, status)
                 VALUES (?, ?, ?, ?, "pending")',
                [$user['id'], $rewardId, $reward['points_required'], $voucherCode]
            );

            // Deduct points
            add_loyalty_points(
                $user['id'],
                -$reward['points_required'],
                'redeem',
                'redemption',
                $redemptionId,
                "Redeemed: {$reward['name']}"
            );

            // Decrement stock
            if ($reward['stock'] !== null) {
                Database::execute('UPDATE rewards SET stock = stock - 1 WHERE id = ?', [$rewardId]);
            }

            Database::commit();

            // Send notification
            Notification::send($user['id'], 'Reward Redeemed! 🎁', "You've redeemed '{$reward['name']}'. Voucher: {$voucherCode}", 'reward', 'in_app', 'redemption', $redemptionId);

            json_success('Reward redeemed successfully!', [
                'redemption_id' => $redemptionId,
                'voucher_code'  => $voucherCode,
                'reward_name'   => $reward['name'],
                'points_used'   => $reward['points_required'],
            ]);
        } catch (Exception $e) {
            Database::rollback();
            error_log('[API Rewards] Redemption error: ' . $e->getMessage());
            json_error('Redemption failed. Please try again.', null, 500);
        }
        break;

    default:
        json_error('Invalid rewards endpoint.', null, 404);
}
