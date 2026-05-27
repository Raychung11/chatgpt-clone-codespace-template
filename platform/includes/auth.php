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
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_role']  = $user['role'];
            $_SESSION['user_email'] = $user['email'];
            // Cache company info if columns exist
            if (array_key_exists('company_id', $user)) {
                $_SESSION['company_id']   = $user['company_id'];
                $_SESSION['company_role'] = $user['company_role'] ?? 'member';
            }
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

    /**
     * True if the current user may use AI modules.
     * Passes if: admin, active trial, active subscription, or completed purchase.
     */
    public static function hasModuleAccess(): bool {
        if (!self::check()) return false;
        if (self::isAdmin()) return true;

        $userId = self::id();

        // Trial period (calculated from account creation)
        try {
            $u = DB::fetch('SELECT created_at FROM users WHERE id = ?', [$userId]);
            if ($u && defined('TRIAL_DAYS')) {
                $trialEnd = strtotime($u['created_at']) + (TRIAL_DAYS * 86400);
                if (time() <= $trialEnd) return true;
            }
        } catch (Throwable $e) {}

        // Active subscription
        try {
            $sub = DB::fetch(
                "SELECT id FROM subscriptions WHERE user_id = ? AND status IN ('active','trialing') LIMIT 1",
                [$userId]
            );
            if ($sub) return true;
        } catch (Throwable $e) {}

        // Completed one-time purchase
        try {
            $pur = DB::fetch(
                "SELECT id FROM purchases WHERE user_id = ? AND status = 'completed' LIMIT 1",
                [$userId]
            );
            if ($pur) return true;
        } catch (Throwable $e) {}

        return false;
    }

    /** Require login + active access; redirect to upgrade page if expired. */
    public static function requireModuleAccess(string $module = ''): void {
        self::requireLogin();
        if (!self::hasModuleAccess()) {
            $q = $module ? '?from=' . urlencode($module) : '';
            header('Location: /upgrade.php' . $q);
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

    public static function register(string $name, string $email, string $password, string $company = '', string $referralCode = ''): int|false {
        $exists = DB::fetch('SELECT id FROM users WHERE email = ?', [strtolower(trim($email))]);
        if ($exists) return false;
        $hash  = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $token = bin2hex(random_bytes(32));
        $userId = DB::insert('users', [
            'name'        => htmlspecialchars($name),
            'email'       => strtolower(trim($email)),
            'password'    => $hash,
            'company'     => htmlspecialchars($company),
            'email_token' => $token,
            'role'        => 'customer',
        ]);

        // Create company workspace and link user as owner
        try {
            self::ensureCompanyColumns();
            $wsName    = $company ?: (trim($name) . "'s Workspace");
            $slug      = self::makeCompanySlug($wsName);
            $companyId = DB::insert('companies', [
                'name'     => htmlspecialchars($wsName),
                'slug'     => $slug,
                'owner_id' => $userId,
            ]);
            DB::update('users', ['company_id' => $companyId, 'company_role' => 'owner'], 'id = ?', [$userId]);
        } catch (Throwable $e) {}

        // Auto-generate referral code for new user
        try {
            self::ensureReferralTables();
            $newCode = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
            while (DB::fetch('SELECT id FROM referral_codes WHERE code = ?', [$newCode])) {
                $newCode = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
            }
            DB::insert('referral_codes', ['user_id' => $userId, 'code' => $newCode]);
        } catch (Throwable $e) {}

        // Record referral if a valid code was passed
        if ($referralCode) {
            try {
                $referrer = DB::fetch('SELECT user_id FROM referral_codes WHERE code = ?', [strtoupper(trim($referralCode))]);
                if ($referrer && (int)$referrer['user_id'] !== $userId) {
                    DB::insert('referrals', [
                        'referrer_id'    => $referrer['user_id'],
                        'referred_id'    => $userId,
                        'referred_email' => strtolower(trim($email)),
                        'status'         => 'pending',
                    ]);
                }
            } catch (Throwable $e) {}
        }

        return $userId;
    }

    public static function ensureReferralTables(): void {
        DB::query("CREATE TABLE IF NOT EXISTS referral_codes (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            user_id    INT NOT NULL UNIQUE,
            code       VARCHAR(16) NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        DB::query("CREATE TABLE IF NOT EXISTS referrals (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            referrer_id    INT NOT NULL,
            referred_id    INT DEFAULT NULL,
            referred_email VARCHAR(200) NOT NULL,
            status         ENUM('pending','converted','rewarded','expired') DEFAULT 'pending',
            reward_amount  DECIMAL(10,2) DEFAULT 0.00,
            notes          TEXT DEFAULT NULL,
            converted_at   DATETIME DEFAULT NULL,
            rewarded_at    DATETIME DEFAULT NULL,
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE SET NULL
        )");
    }

    /** Get or create a referral code for the current user */
    public static function getReferralCode(int $userId): string {
        try {
            self::ensureReferralTables();
            $row = DB::fetch('SELECT code FROM referral_codes WHERE user_id = ?', [$userId]);
            if ($row) return $row['code'];
            $code = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
            while (DB::fetch('SELECT id FROM referral_codes WHERE code = ?', [$code])) {
                $code = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
            }
            DB::insert('referral_codes', ['user_id' => $userId, 'code' => $code]);
            return $code;
        } catch (Throwable $e) {
            return '';
        }
    }

    public static function id(): ?int {
        self::start();
        return $_SESSION['user_id'] ?? null;
    }

    public static function isAdmin(): bool {
        self::start();
        return ($_SESSION['user_role'] ?? '') === 'admin';
    }

    public static function companyId(): ?int {
        self::start();
        if (!self::check()) return null;
        if (array_key_exists('company_id', $_SESSION)) return $_SESSION['company_id'] ?: null;
        try {
            $u = DB::fetch('SELECT company_id FROM users WHERE id = ?', [self::id()]);
            $_SESSION['company_id'] = $u['company_id'] ?? null;
        } catch (Throwable $e) {
            $_SESSION['company_id'] = null;
        }
        return $_SESSION['company_id'];
    }

    public static function companyRole(): string {
        self::start();
        if (!self::check()) return '';
        if (isset($_SESSION['company_role'])) return $_SESSION['company_role'];
        try {
            $u = DB::fetch('SELECT company_role FROM users WHERE id = ?', [self::id()]);
            $_SESSION['company_role'] = $u['company_role'] ?? 'member';
        } catch (Throwable $e) {
            $_SESSION['company_role'] = 'member';
        }
        return $_SESSION['company_role'];
    }

    public static function isCompanyOwner(): bool {
        return in_array(self::companyRole(), ['owner', 'admin']);
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

    public static function ensureCompanyColumns(): void {
        try { DB::query("ALTER TABLE users ADD COLUMN company_id INT DEFAULT NULL"); } catch (Throwable $e) {}
        try { DB::query("ALTER TABLE users ADD COLUMN company_role ENUM('owner','admin','member') DEFAULT 'owner'"); } catch (Throwable $e) {}
    }

    private static function makeCompanySlug(string $name): string {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        $base = $slug ?: 'company';
        $i    = 1;
        while (DB::fetch('SELECT id FROM companies WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}

// Auto-start session
Auth::start();
