<?php
/**
 * API – Auth Routes
 * /api/routes/auth.php
 *
 * POST /api/auth/request-otp   – Request OTP for phone
 * POST /api/auth/verify-otp    – Verify OTP, login/register
 * POST /api/auth/logout        – Invalidate token
 * GET  /api/auth/me            – Get current user
 */

require_once BASE_PATH . '/api/middleware.php';

// Create api_tokens table if not exist (safe migration)
// NOTE: Run schema.sql separately in production.

switch ($action) {

    // ---------------------------------------------------
    // POST /api/auth/request-otp
    // ---------------------------------------------------
    case 'request-otp':
        if ($method !== 'POST') json_error('Method not allowed.', null, 405);

        api_rate_limit('otp_request', 5, 300); // 5 per 5 minutes

        $phone   = sanitize_string($body['phone'] ?? '');
        $purpose = in_array($body['purpose'] ?? '', ['login','register']) ? $body['purpose'] : 'login';

        if (!$phone) json_error('Phone number is required.');
        if (!validate_phone($phone)) json_error('Invalid phone number format. Use 60xxxxxxxxx.');

        // For register: check if not already exists
        if ($purpose === 'register') {
            $existing = Database::fetchOne('SELECT id FROM users WHERE phone = ?', [$phone]);
            if ($existing) json_error('Phone number already registered. Please login.');
        }

        // For login: check if exists
        if ($purpose === 'login') {
            $existing = Database::fetchOne('SELECT id FROM users WHERE phone = ? AND role = "customer"', [$phone]);
            if (!$existing) json_error('Phone number not found. Please register first.');
        }

        $otp = Auth::generateOtp($phone, $purpose);

        // Send via WhatsApp
        $sent = Notification::sendWhatsApp($phone, "Your F&B Platform OTP is: {$otp}. Valid for 10 minutes. Do not share this code.");

        // In dev mode, return OTP in response (NEVER in production)
        $cfg = require BASE_PATH . '/config/app.php';
        $responseData = ['phone' => $phone];
        if ($cfg['app_env'] === 'local' || $cfg['app_debug']) {
            $responseData['otp_debug'] = $otp; // remove in production
        }

        json_success('OTP sent successfully.', $responseData);
        break;

    // ---------------------------------------------------
    // POST /api/auth/verify-otp
    // ---------------------------------------------------
    case 'verify-otp':
        if ($method !== 'POST') json_error('Method not allowed.', null, 405);

        $phone   = sanitize_string($body['phone']   ?? '');
        $otp     = sanitize_string($body['otp']     ?? '');
        $purpose = sanitize_string($body['purpose'] ?? 'login');
        $name    = sanitize_string($body['name']    ?? '');  // required for register

        if (!$phone || !$otp) json_error('Phone and OTP are required.');

        if (!Auth::verifyOtp($phone, $otp, $purpose)) {
            json_error('Invalid or expired OTP.');
        }

        // Register new user
        if ($purpose === 'register') {
            if (!$name) json_error('Name is required for registration.');

            Database::beginTransaction();
            try {
                $userId = Database::insert(
                    'INSERT INTO users (name, phone, role, status, phone_verified) VALUES (?, ?, "customer", "active", 1)',
                    [$name, $phone]
                );

                $refCode = generate_referral_code($name);
                $referredBy = null;

                // Handle referral
                if (!empty($body['referral_code'])) {
                    $refUser = Database::fetchOne(
                        'SELECT user_id FROM customer_profiles WHERE referral_code = ?',
                        [sanitize_string($body['referral_code'])]
                    );
                    if ($refUser) {
                        $referredBy = $refUser['user_id'];
                    }
                }

                Database::insert(
                    'INSERT INTO customer_profiles (user_id, referral_code, referred_by) VALUES (?, ?, ?)',
                    [$userId, $refCode, $referredBy]
                );

                // Award referral points
                if ($referredBy) {
                    $settings = get_settings(['referral_reward_points', 'referral_referee_points']);
                    add_loyalty_points($referredBy, (int)($settings['referral_reward_points'] ?? 100), 'referral', 'referral', $userId, 'Referral reward');
                    add_loyalty_points($userId, (int)($settings['referral_referee_points'] ?? 50), 'referral', 'referral', $referredBy, 'Welcome bonus');
                }

                Database::commit();

                $user = Database::fetchOne('SELECT * FROM users WHERE id = ?', [$userId]);
            } catch (Exception $e) {
                Database::rollback();
                error_log('[API Auth] Register error: ' . $e->getMessage());
                json_error('Registration failed. Please try again.', null, 500);
            }
        } else {
            $user = Database::fetchOne('SELECT * FROM users WHERE phone = ? AND role = "customer"', [$phone]);
            if (!$user) json_error('User not found.');
        }

        // Generate API token
        $token = Auth::generateApiToken($user['id']);

        // Update last login
        Database::execute('UPDATE users SET last_login_at = NOW(), phone_verified = 1 WHERE id = ?', [$user['id']]);

        $profile = Database::fetchOne('SELECT * FROM customer_profiles WHERE user_id = ?', [$user['id']]);

        json_success('Login successful.', [
            'token'   => $token,
            'user'    => [
                'id'      => $user['id'],
                'name'    => $user['name'],
                'phone'   => $user['phone'],
                'email'   => $user['email'],
            ],
            'profile' => [
                'tier'          => $profile['tier'] ?? 'bronze',
                'total_points'  => (int) ($profile['total_points'] ?? 0),
                'referral_code' => $profile['referral_code'] ?? '',
            ],
        ]);
        break;

    // ---------------------------------------------------
    // GET /api/auth/me
    // ---------------------------------------------------
    case 'me':
        if ($method !== 'GET') json_error('Method not allowed.', null, 405);
        $user = api_require_auth();

        $profile = Database::fetchOne('SELECT * FROM customer_profiles WHERE user_id = ?', [$user['id']]);
        $unread  = Notification::getUnreadCount($user['id']);

        json_success('OK', [
            'user'    => [
                'id'           => $user['id'],
                'name'         => $user['name'],
                'phone'        => $user['phone'],
                'email'        => $user['email'],
            ],
            'profile' => [
                'tier'           => $profile['tier']          ?? 'bronze',
                'total_points'   => (int)($profile['total_points']   ?? 0),
                'lifetime_points'=> (int)($profile['lifetime_points'] ?? 0),
                'referral_code'  => $profile['referral_code'] ?? '',
                'date_of_birth'  => $profile['date_of_birth'] ?? null,
                'gender'         => $profile['gender']        ?? null,
                'avatar_url'     => $profile['avatar_url']    ?? null,
            ],
            'unread_notifications' => $unread,
        ]);
        break;

    // ---------------------------------------------------
    // POST /api/auth/logout
    // ---------------------------------------------------
    case 'logout':
        if ($method !== 'POST') json_error('Method not allowed.', null, 405);
        $user = api_require_auth();

        Database::execute('DELETE FROM api_tokens WHERE user_id = ?', [$user['id']]);
        json_success('Logged out successfully.');
        break;

    // ---------------------------------------------------
    // PUT /api/auth/profile
    // ---------------------------------------------------
    case 'profile':
        if ($method !== 'PUT') json_error('Method not allowed.', null, 405);
        $user = api_require_auth();

        $name  = sanitize_string($body['name']          ?? $user['name']);
        $email = sanitize_string($body['email']         ?? '');
        $dob   = sanitize_string($body['date_of_birth'] ?? '');
        $gender= in_array($body['gender'] ?? '', ['male','female','other']) ? $body['gender'] : null;

        if ($email && !validate_email($email)) json_error('Invalid email format.');

        Database::execute(
            'UPDATE users SET name = ?, email = ? WHERE id = ?',
            [$name, $email ?: null, $user['id']]
        );
        Database::execute(
            'UPDATE customer_profiles SET date_of_birth = ?, gender = ? WHERE user_id = ?',
            [$dob ?: null, $gender, $user['id']]
        );

        json_success('Profile updated.');
        break;

    default:
        json_error('Invalid auth endpoint.', null, 404);
}
