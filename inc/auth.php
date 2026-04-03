<?php
declare(strict_types=1);

/**
 * inc/auth.php
 * Authentication helpers for both clients and admins.
 */

// ── Client auth ───────────────────────────────────────────────────────────────

/** Log in a client: set session and update last_login_at */
function auth_login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email']= $user['email'];

    db()->prepare('UPDATE `users` SET `last_login_at` = NOW() WHERE `id` = ?')
        ->execute([$user['id']]);

    log_activity('user', (int)$user['id'], 'login', 'User logged in');
}

/** Returns current logged-in user row, or null */
function auth_user(): ?array
{
    if (empty($_SESSION['user_id'])) return null;

    static $cache = null;
    if ($cache !== null) return $cache;

    $stmt = db()->prepare(
        'SELECT `id`,`name`,`email`,`phone`,`avatar`,`referral_code`,`is_active`
         FROM `users` WHERE `id` = ? LIMIT 1'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $cache = $stmt->fetch() ?: null;

    if ($cache && !$cache['is_active']) {
        auth_logout_user();
        return null;
    }
    return $cache;
}

/** Require a logged-in client; redirect otherwise */
function require_auth(string $redirect = '/login.php'): array
{
    $user = auth_user();
    if (!$user) {
        flash_error('Please log in to continue.');
        redirect(BASE_URL . $redirect);
    }
    return $user;
}

/** Log out client */
function auth_logout_user(): void
{
    $uid = $_SESSION['user_id'] ?? null;
    if ($uid) log_activity('user', (int)$uid, 'logout', 'User logged out');

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ── Admin auth ────────────────────────────────────────────────────────────────

/** Log in an admin */
function auth_login_admin(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id']   = $admin['id'];
    $_SESSION['admin_name'] = $admin['name'];
    $_SESSION['admin_role'] = $admin['role'];

    db()->prepare('UPDATE `admins` SET `last_login_at` = NOW() WHERE `id` = ?')
        ->execute([$admin['id']]);

    log_activity('admin', (int)$admin['id'], 'admin_login', 'Admin logged in');
}

/** Returns current logged-in admin row, or null */
function auth_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) return null;

    static $cache = null;
    if ($cache !== null) return $cache;

    $stmt = db()->prepare(
        'SELECT `id`,`name`,`email`,`role`,`is_active`
         FROM `admins` WHERE `id` = ? LIMIT 1'
    );
    $stmt->execute([$_SESSION['admin_id']]);
    $cache = $stmt->fetch() ?: null;

    if ($cache && !$cache['is_active']) {
        auth_logout_admin();
        return null;
    }
    return $cache;
}

/** Require a logged-in admin; redirect otherwise */
function require_admin(string $redirect = '/admin/login.php', ?string $role = null): array
{
    $admin = auth_admin();
    if (!$admin) {
        flash_error('Admin login required.');
        redirect(BASE_URL . $redirect);
    }
    if ($role && $admin['role'] !== $role && $admin['role'] !== 'super_admin') {
        flash_error('You do not have permission to access this page.');
        redirect(BASE_URL . '/admin/index.php');
    }
    return $admin;
}

/** Log out admin */
function auth_logout_admin(): void
{
    $aid = $_SESSION['admin_id'] ?? null;
    if ($aid) log_activity('admin', (int)$aid, 'admin_logout', 'Admin logged out');

    unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_role']);
    session_regenerate_id(true);
}

// ── Registration ──────────────────────────────────────────────────────────────

/**
 * Register a new client.
 * Returns ['ok' => true, 'user_id' => int] or ['ok' => false, 'error' => string]
 */
function register_user(string $name, string $email, string $password, ?string $referral_code = null): array
{
    $pdo = db();

    // Check for duplicate email
    $stmt = $pdo->prepare('SELECT `id` FROM `users` WHERE `email` = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['ok' => false, 'error' => 'This email address is already registered.'];
    }

    // Resolve referrer
    $referrer_id = null;
    if ($referral_code) {
        $stmt = $pdo->prepare('SELECT `id` FROM `users` WHERE `referral_code` = ? LIMIT 1');
        $stmt->execute([$referral_code]);
        $referrer = $stmt->fetch();
        if ($referrer) {
            $referrer_id = (int)$referrer['id'];
        }
    }

    $hash  = password_hash($password, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
    $myCode = generate_referral_code();

    // Ensure code uniqueness
    while (true) {
        $check = $pdo->prepare('SELECT `id` FROM `users` WHERE `referral_code` = ? LIMIT 1');
        $check->execute([$myCode]);
        if (!$check->fetch()) break;
        $myCode = generate_referral_code();
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO `users` (`name`,`email`,`password_hash`,`referral_code`,`referred_by`)
             VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$name, $email, $hash, $myCode, $referrer_id]);
        $userId = (int)$pdo->lastInsertId();

        // Create wallet
        $pdo->prepare('INSERT INTO `wallets` (`user_id`,`balance`) VALUES (?,0.00)')
            ->execute([$userId]);

        // Track referral
        if ($referrer_id) {
            $pdo->prepare(
                'INSERT INTO `referrals` (`referrer_id`,`referee_id`) VALUES (?,?)'
            )->execute([$referrer_id, $userId]);
        }

        $pdo->commit();
        log_activity('user', $userId, 'register', 'New user registered');
        return ['ok' => true, 'user_id' => $userId];

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('[register_user] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Registration failed. Please try again.'];
    }
}

// ── Password reset ────────────────────────────────────────────────────────────

/**
 * Generate and store a password reset token.
 * Returns the token (to be emailed) or false on failure.
 */
function create_password_reset_token(string $email): string|false
{
    $stmt = db()->prepare('SELECT `id` FROM `users` WHERE `email` = ? AND `is_active` = 1 LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) return false;

    $token  = generate_token(32);
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

    db()->prepare(
        'UPDATE `users` SET `reset_token` = ?, `reset_token_expiry` = ? WHERE `id` = ?'
    )->execute([$token, $expiry, $user['id']]);

    return $token;
}

/**
 * Validate a password reset token.
 * Returns the user row or null.
 */
function validate_reset_token(string $token): ?array
{
    $stmt = db()->prepare(
        'SELECT `id`,`email` FROM `users`
         WHERE `reset_token` = ? AND `reset_token_expiry` > NOW() AND `is_active` = 1
         LIMIT 1'
    );
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

/**
 * Apply a new password and clear the reset token.
 */
function apply_password_reset(int $user_id, string $new_password): void
{
    $hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
    db()->prepare(
        'UPDATE `users` SET `password_hash` = ?, `reset_token` = NULL, `reset_token_expiry` = NULL
         WHERE `id` = ?'
    )->execute([$hash, $user_id]);

    log_activity('user', $user_id, 'password_reset', 'Password reset successfully');
}
