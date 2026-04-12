<?php
/**
 * PlotGold Malaysia — Authentication & Authorization
 */

defined('PLOTGOLD') or die('Direct access not permitted.');

// ── Login ──────────────────────────────────────────────────────────────────

function auth_login(string $email, string $password, bool $remember = false): array
{
    $email = strtolower(trim($email));

    $user = Database::fetchOne(
        'SELECT u.*, up.full_name FROM users u
         LEFT JOIN user_profiles up ON up.user_id = u.id
         WHERE u.email = ? AND u.status != ?',
        [$email, 'deleted']
    );

    if (!$user) {
        return ['success' => false, 'error' => 'Invalid email or password.'];
    }

    // Check account status
    if ($user['status'] === 'suspended') {
        return ['success' => false, 'error' => 'Your account has been suspended. Please contact support.'];
    }
    if ($user['status'] === 'pending') {
        return ['success' => false, 'error' => 'Your account is pending verification. Check your email.'];
    }

    // Check lockout
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        $wait = ceil((strtotime($user['locked_until']) - time()) / 60);
        return ['success' => false, 'error' => "Account locked. Try again in {$wait} minute(s)."];
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        auth_record_failed_attempt($user['id']);
        return ['success' => false, 'error' => 'Invalid email or password.'];
    }

    // Reset failed attempts
    Database::query(
        'UPDATE users SET login_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?',
        [$user['id']]
    );

    // Fetch roles
    $roles = Database::fetchAll(
        'SELECT r.name FROM roles r
         JOIN user_role_map urm ON urm.role_id = r.id
         WHERE urm.user_id = ?',
        [$user['id']]
    );
    $roleNames = array_column($roles, 'name');

    // Set session
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name']  = $user['full_name'] ?? 'User';
    $_SESSION['user_roles'] = $roleNames;
    pg_session_regenerate();

    // Remember me cookie
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $expires = time() + REMEMBER_LIFETIME;
        Database::query(
            'UPDATE users SET remember_token = ? WHERE id = ?',
            [hash('sha256', $token), $user['id']]
        );
        setcookie('pg_remember', $user['id'] . ':' . $token, $expires, '/', '', true, true);
    }

    // Log activity
    activity_log($user['id'], 'user_login', 'users', $user['id'], 'User logged in');

    return ['success' => true, 'user' => $user, 'roles' => $roleNames];
}

function auth_logout(): void
{
    if (isset($_SESSION['user_id'])) {
        activity_log($_SESSION['user_id'], 'user_logout', 'users', $_SESSION['user_id'], 'User logged out');
    }
    // Clear remember cookie
    setcookie('pg_remember', '', time() - 3600, '/', '', true, true);
    pg_session_destroy();
}

function auth_record_failed_attempt(int $userId): void
{
    Database::query(
        'UPDATE users SET login_attempts = login_attempts + 1 WHERE id = ?',
        [$userId]
    );
    $user = Database::fetchOne('SELECT login_attempts FROM users WHERE id = ?', [$userId]);
    if ($user && $user['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
        $lockUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_MINUTES * 60);
        Database::query(
            'UPDATE users SET locked_until = ? WHERE id = ?',
            [$lockUntil, $userId]
        );
    }
}

// ── Check Cookie Auth ─────────────────────────────────────────────────────────

function auth_check_remember(): void
{
    if (!auth_check() && isset($_COOKIE['pg_remember'])) {
        [$userId, $token] = explode(':', $_COOKIE['pg_remember'], 2) + [null, null];
        if ($userId && $token) {
            $user = Database::fetchOne(
                'SELECT u.*, up.full_name FROM users u
                 LEFT JOIN user_profiles up ON up.user_id = u.id
                 WHERE u.id = ? AND u.status = ? AND u.remember_token = ?',
                [(int)$userId, 'active', hash('sha256', $token)]
            );
            if ($user) {
                $roles = Database::fetchAll(
                    'SELECT r.name FROM roles r JOIN user_role_map urm ON urm.role_id = r.id WHERE urm.user_id = ?',
                    [$user['id']]
                );
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name']  = $user['full_name'] ?? 'User';
                $_SESSION['user_roles'] = array_column($roles, 'name');
                pg_session_regenerate();
            }
        }
    }
}

// ── Session Checks ────────────────────────────────────────────────────────────

function auth_check(): bool
{
    return !empty($_SESSION['user_id']);
}

function auth_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function auth_user_name(): string
{
    return $_SESSION['user_name'] ?? 'Guest';
}

function auth_user_email(): string
{
    return $_SESSION['user_email'] ?? '';
}

function auth_roles(): array
{
    return $_SESSION['user_roles'] ?? [];
}

function auth_has_role(string $role): bool
{
    return in_array($role, auth_roles(), true);
}

function auth_has_any_role(array $roles): bool
{
    return !empty(array_intersect($roles, auth_roles()));
}

function auth_is_admin(): bool
{
    return auth_has_any_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);
}

function auth_is_super_admin(): bool
{
    return auth_has_role(ROLE_SUPER_ADMIN);
}

// ── Access Guards ─────────────────────────────────────────────────────────────

function require_auth(string $redirect = '/login.php'): void
{
    auth_check_remember();
    if (!auth_check()) {
        $redirect = BASE_URL . $redirect;
        flash_set(FLASH_WARNING, 'Please log in to continue.');
        header('Location: ' . $redirect);
        exit;
    }
}

function require_role(string $role, string $redirect = '/'): void
{
    require_auth();
    if (!auth_has_role($role)) {
        flash_set(FLASH_ERROR, 'Access denied.');
        header('Location: ' . BASE_URL . $redirect);
        exit;
    }
}

function require_admin(): void
{
    require_auth();
    if (!auth_is_admin()) {
        flash_set(FLASH_ERROR, 'Access denied. Admin only.');
        header('Location: ' . BASE_URL . '/');
        exit;
    }
}

// ── Registration ──────────────────────────────────────────────────────────────

function auth_register(array $data, string $roleName = ROLE_BUYER): array
{
    $email = strtolower(trim($data['email'] ?? ''));

    // Check email unique
    $exists = Database::fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
    if ($exists) {
        return ['success' => false, 'error' => 'An account with this email already exists.'];
    }

    $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    $uuid = pg_uuid();

    $userId = Database::insert(
        'INSERT INTO users (uuid, email, phone, password_hash, status) VALUES (?, ?, ?, ?, ?)',
        [$uuid, $email, $data['phone'] ?? null, $hash, 'active']
    );

    Database::query(
        'INSERT INTO user_profiles (user_id, full_name) VALUES (?, ?)',
        [$userId, trim($data['full_name'] ?? 'User')]
    );

    $role = Database::fetchOne('SELECT id FROM roles WHERE name = ?', [$roleName]);
    if ($role) {
        Database::query(
            'INSERT INTO user_role_map (user_id, role_id) VALUES (?, ?)',
            [$userId, $role['id']]
        );
    }

    // Create role-specific profile
    if ($roleName === ROLE_SELLER) {
        Database::query('INSERT INTO sellers (user_id) VALUES (?)', [$userId]);
    } elseif ($roleName === ROLE_BUYER) {
        Database::query('INSERT INTO buyers (user_id) VALUES (?)', [$userId]);
    } elseif ($roleName === ROLE_PROVIDER) {
        Database::query(
            'INSERT INTO providers (user_id, business_name) VALUES (?, ?)',
            [$userId, trim($data['business_name'] ?? 'My Business')]
        );
    }

    // Assign a unique referral code to the new user
    $refCode = generate_referral_code();
    Database::query('UPDATE users SET referral_code = ? WHERE id = ?', [$refCode, $userId]);

    // Process referral if a valid code was provided
    if (!empty($data['referral_code'])) {
        $referrer = Database::fetchOne(
            "SELECT id FROM users WHERE referral_code = ? AND status = 'active'",
            [strtoupper(trim($data['referral_code']))]
        );
        if ($referrer && (int)$referrer['id'] !== $userId) {
            referral_record((int)$referrer['id'], $userId);
        }
    }

    activity_log($userId, 'user_registered', 'users', $userId, 'New user registered as ' . $roleName);

    return ['success' => true, 'user_id' => $userId];
}
