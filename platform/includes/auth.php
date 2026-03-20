<?php
require_once __DIR__ . '/db.php';

class Auth {
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
            session_set_cookie_params(SESSION_LIFETIME);
            session_start();
        }
    }

    public static function login(string $email, string $password): bool {
        self::start();
        $user = DB::fetch('SELECT * FROM users WHERE email = ? LIMIT 1', [strtolower(trim($email))]);
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_email']= $user['email'];
            return true;
        }
        return false;
    }

    public static function logout(): void {
        self::start();
        session_destroy();
        header('Location: /login.php');
        exit;
    }

    public static function check(): bool {
        self::start();
        return isset($_SESSION['user_id']);
    }

    public static function requireLogin(string $redirect = '/login.php'): void {
        if (!self::check()) {
            header("Location: $redirect");
            exit;
        }
    }

    public static function requireAdmin(): void {
        self::requireLogin('/login.php');
        if ($_SESSION['user_role'] !== 'admin') {
            header('Location: /dashboard.php');
            exit;
        }
    }

    public static function user(): ?array {
        self::start();
        if (!self::check()) return null;
        return DB::fetch('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
    }

    public static function register(string $name, string $email, string $password, string $company = ''): int|false {
        $exists = DB::fetch('SELECT id FROM users WHERE email = ?', [strtolower(trim($email))]);
        if ($exists) return false;
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $token = bin2hex(random_bytes(32));
        return DB::insert('users', [
            'name'          => htmlspecialchars($name),
            'email'         => strtolower(trim($email)),
            'password'      => $hash,
            'company'       => htmlspecialchars($company),
            'email_token'   => $token,
            'role'          => 'customer',
        ]);
    }

    public static function id(): ?int {
        self::start();
        return $_SESSION['user_id'] ?? null;
    }

    public static function isAdmin(): bool {
        self::start();
        return ($_SESSION['user_role'] ?? '') === 'admin';
    }

    /** Check if user owns a product (active subscription or purchase) */
    public static function owns(int $productId): bool {
        if (!self::check()) return false;
        $sub = DB::fetch(
            'SELECT id FROM subscriptions WHERE user_id=? AND product_id=? AND status="active"',
            [self::id(), $productId]
        );
        if ($sub) return true;
        $purchase = DB::fetch(
            'SELECT id FROM purchases WHERE user_id=? AND product_id=? AND status="completed"',
            [self::id(), $productId]
        );
        return (bool) $purchase;
    }
}

// Auto-start session
Auth::start();
