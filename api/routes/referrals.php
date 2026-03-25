<?php
/**
 * API – Referrals Routes
 * /api/routes/referrals.php
 *
 * GET  /api/referrals/my      – My referral stats
 * GET  /api/referrals/track   – Track a referral click (public)
 */

require_once BASE_PATH . '/api/middleware.php';

switch ($action) {

    // GET /api/referrals/my
    case 'my':
        if ($method !== 'GET') json_error('Method not allowed.', null, 405);
        $user = api_require_auth();

        $profile = Database::fetchOne(
            'SELECT referral_code FROM customer_profiles WHERE user_id = ?',
            [$user['id']]
        );

        $referrals = Database::fetchAll(
            'SELECT u.name, u.created_at, cp.total_points
             FROM users u
             JOIN customer_profiles cp ON cp.user_id = u.id
             WHERE cp.referred_by = ?
             ORDER BY u.created_at DESC',
            [$user['id']]
        );

        $totalEarned = (int) (Database::fetchOne(
            "SELECT COALESCE(SUM(points),0) AS p FROM loyalty_transactions WHERE user_id = ? AND type = 'referral'",
            [$user['id']]
        )['p'] ?? 0);

        $cfg = require BASE_PATH . '/config/app.php';

        json_success('OK', [
            'referral_code'     => $profile['referral_code'] ?? '',
            'referral_link'     => $cfg['app_url'] . '/app/register?ref=' . ($profile['referral_code'] ?? ''),
            'total_referrals'   => count($referrals),
            'total_pts_earned'  => $totalEarned,
            'referrals'         => $referrals,
        ]);
        break;

    // GET /api/referrals/track?code=XXXX (just logs a click)
    case 'track':
        if ($method !== 'GET') json_error('Method not allowed.', null, 405);

        $code = sanitize_string($_GET['code'] ?? '');
        if (!$code) json_error('Referral code is required.');

        $ref = Database::fetchOne(
            'SELECT user_id FROM customer_profiles WHERE referral_code = ?', [$code]
        );
        if (!$ref) json_error('Invalid referral code.', null, 404);

        // In production: track clicks in referral_links table
        json_success('Valid referral code.', ['valid' => true]);
        break;

    default:
        json_error('Invalid referrals endpoint.', null, 404);
}
