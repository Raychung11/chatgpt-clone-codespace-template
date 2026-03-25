<?php
/**
 * Auth Helper
 * /inc/Auth.php
 *
 * Handles: OTP generation, session management, role checks
 */

class Auth
{
    // -------------------------------------------------
    // Session helpers
    // -------------------------------------------------

    public static function startSession(): void
    {
        $cfg = require BASE_PATH . '/config/app.php';

        if (session_status() === PHP_SESSION_NONE) {
            session_name($cfg['session_name']);
            session_set_cookie_params([
                'lifetime' => $cfg['session_lifetime'],
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function login(array $user): void
    {
        self::startSession();
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_phone']= $user['phone'];
        $_SESSION['logged_in'] = true;
    }

    public static function logout(): void
    {
        self::startSession();
        session_unset();
        session_destroy();
    }

    public static function check(): bool
    {
        self::startSession();
        return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
    }

    public static function isAdmin(): bool
    {
        return self::check() && in_array($_SESSION['user_role'] ?? '', ['admin', 'superadmin', 'staff']);
    }

    public static function isSuperAdmin(): bool
    {
        return self::check() && ($_SESSION['user_role'] ?? '') === 'superadmin';
    }

    public static function currentUserId(): ?int
    {
        return self::check() ? (int) $_SESSION['user_id'] : null;
    }

    public static function currentUser(): ?array
    {
        if (!self::check()) return null;
        return Database::fetchOne('SELECT * FROM users WHERE id = ? AND status = "active"', [$_SESSION['user_id']]);
    }

    /**
     * Require login or redirect.
     */
    public static function requireLogin(string $redirect = '/admin/login'): void
    {
        if (!self::check()) {
            header('Location: ' . $redirect);
            exit;
        }
    }

    /**
     * Require admin role or redirect.
     */
    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            header('Location: /admin/login');
            exit;
        }
    }

    // -------------------------------------------------
    // OTP
    // -------------------------------------------------

    /**
     * Generate and store OTP for a phone number.
     * Returns the OTP code (to be sent via WhatsApp/SMS).
     */
    public static function generateOtp(string $phone, string $purpose = 'login'): string
    {
        $cfg = require BASE_PATH . '/config/app.php';

        // Invalidate existing OTPs for this phone+purpose
        Database::execute(
            'UPDATE otp_tokens SET is_used = 1 WHERE phone = ? AND purpose = ? AND is_used = 0',
            [$phone, $purpose]
        );

        $otp     = str_pad((string) random_int(0, (10 ** $cfg['otp_length']) - 1), $cfg['otp_length'], '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+' . $cfg['otp_expiry_min'] . ' minutes'));

        Database::insert(
            'INSERT INTO otp_tokens (phone, otp, purpose, expires_at) VALUES (?, ?, ?, ?)',
            [$phone, password_hash($otp, PASSWORD_BCRYPT), $purpose, $expires]
        );

        return $otp;
    }

    /**
     * Verify OTP. Returns true on success, false otherwise.
     */
    public static function verifyOtp(string $phone, string $otp, string $purpose = 'login'): bool
    {
        $row = Database::fetchOne(
            'SELECT * FROM otp_tokens
             WHERE phone = ? AND purpose = ? AND is_used = 0 AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1',
            [$phone, $purpose]
        );

        if (!$row) return false;

        // Increment attempts
        Database::execute('UPDATE otp_tokens SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);

        if ((int) $row['attempts'] >= 5) return false;  // too many attempts

        if (!password_verify($otp, $row['otp'])) return false;

        // Mark as used
        Database::execute('UPDATE otp_tokens SET is_used = 1 WHERE id = ?', [$row['id']]);

        return true;
    }

    // -------------------------------------------------
    // API Token (simple Bearer token for API auth)
    // -------------------------------------------------

    /**
     * Generate a secure API token for a user (stored in sessions table or users table).
     * Simple approach: store hashed token in users.api_token (add column as needed).
     */
    public static function generateApiToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);

        Database::execute(
            'INSERT INTO api_tokens (user_id, token_hash, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
             ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at)',
            [$userId, $hash]
        );

        return $token;
    }

    /**
     * Validate a Bearer token. Returns user_id or null.
     */
    public static function validateApiToken(string $token): ?int
    {
        $hash = hash('sha256', $token);
        $row  = Database::fetchOne(
            'SELECT user_id FROM api_tokens WHERE token_hash = ? AND expires_at > NOW()',
            [$hash]
        );

        return $row ? (int) $row['user_id'] : null;
    }

    // -------------------------------------------------
    // CSRF
    // -------------------------------------------------

    public static function generateCsrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCsrfToken(string $token): bool
    {
        self::startSession();
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
}
