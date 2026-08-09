<?php
/* ============================================================
   Membership & Tier System
   ============================================================ */

class Membership {

    // Default tier definitions (used if DB table has no rows)
    const DEFAULT_TIERS = [
        ['slug'=>'starter',  'name'=>'Starter',  'min_points'=>0,     'color'=>'#6b7280', 'icon'=>'bi-circle',         'benefits'=>['Access to all AI tools','14-day free trial']],
        ['slug'=>'bronze',   'name'=>'Bronze',   'min_points'=>500,   'color'=>'#cd7f32', 'icon'=>'bi-award',          'benefits'=>['Priority email support','5% renewal discount','+5 AI tools daily cap']],
        ['slug'=>'silver',   'name'=>'Silver',   'min_points'=>2000,  'color'=>'#94a3b8', 'icon'=>'bi-award-fill',     'benefits'=>['Priority support','10% renewal discount','Extended AI memory (10 exchanges)','Early access to new Capsules']],
        ['slug'=>'gold',     'name'=>'Gold',     'min_points'=>5000,  'color'=>'#f59e0b', 'icon'=>'bi-trophy',         'benefits'=>['Dedicated support','15% renewal discount','Extended AI memory (15 exchanges)','Custom branding options','Quarterly strategy call']],
        ['slug'=>'platinum', 'name'=>'Platinum', 'min_points'=>10000, 'color'=>'#6366f1', 'icon'=>'bi-trophy-fill',    'benefits'=>['Dedicated account manager','20% renewal discount','Unlimited AI memory','White-label options','Monthly strategy call']],
    ];

    public static function ensureTables(): void {
        DB::query("CREATE TABLE IF NOT EXISTS membership_tiers (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            slug       VARCHAR(50) NOT NULL UNIQUE,
            name       VARCHAR(50) NOT NULL,
            min_points INT NOT NULL DEFAULT 0,
            color      VARCHAR(20) DEFAULT '#6b7280',
            icon       VARCHAR(50) DEFAULT 'bi-award',
            benefits   TEXT DEFAULT '[]',
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        DB::query("CREATE TABLE IF NOT EXISTS user_points (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            user_id     INT NOT NULL,
            points      INT NOT NULL DEFAULT 0,
            type        ENUM('earn','redeem','adjust','expire') DEFAULT 'earn',
            source      VARCHAR(100) DEFAULT '',
            description VARCHAR(255) DEFAULT '',
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        // Add wallet + points columns to users if missing
        try { DB::query("ALTER TABLE users ADD COLUMN total_points INT DEFAULT 0"); }   catch (Throwable $e) {}
        try { DB::query("ALTER TABLE users ADD COLUMN wallet_balance DECIMAL(10,2) DEFAULT 0.00"); } catch (Throwable $e) {}

        // Seed default tiers if none exist
        $count = (int)(DB::fetch('SELECT COUNT(*) as n FROM membership_tiers')['n'] ?? 0);
        if ($count === 0) {
            foreach (self::DEFAULT_TIERS as $i => $t) {
                DB::insert('membership_tiers', [
                    'slug'       => $t['slug'],
                    'name'       => $t['name'],
                    'min_points' => $t['min_points'],
                    'color'      => $t['color'],
                    'icon'       => $t['icon'],
                    'benefits'   => json_encode($t['benefits']),
                    'sort_order' => $i,
                ]);
            }
        }
    }

    /** Award points to a user — capped daily per source */
    public static function awardPoints(int $userId, int $points, string $source, string $description, int $dailyCap = 0): bool {
        try {
            if ($dailyCap > 0) {
                $todayEarned = (int)(DB::fetch(
                    "SELECT SUM(points) as s FROM user_points
                     WHERE user_id=? AND source=? AND type='earn' AND DATE(created_at)=CURDATE()",
                    [$userId, $source]
                )['s'] ?? 0);
                if ($todayEarned >= $dailyCap) return false;
                $points = min($points, $dailyCap - $todayEarned);
            }
            DB::insert('user_points', [
                'user_id'     => $userId,
                'points'      => $points,
                'type'        => 'earn',
                'source'      => $source,
                'description' => $description,
            ]);
            DB::query('UPDATE users SET total_points = total_points + ? WHERE id = ?', [$points, $userId]);
            return true;
        } catch (Throwable $e) { return false; }
    }

    /** Adjust points (admin action — can be negative) */
    public static function adjustPoints(int $userId, int $delta, string $description): void {
        try {
            DB::insert('user_points', [
                'user_id'     => $userId,
                'points'      => abs($delta),
                'type'        => $delta >= 0 ? 'earn' : 'redeem',
                'source'      => 'admin_adjustment',
                'description' => $description,
            ]);
            if ($delta >= 0) {
                DB::query('UPDATE users SET total_points = total_points + ? WHERE id = ?', [$delta, $userId]);
            } else {
                DB::query('UPDATE users SET total_points = GREATEST(0, total_points + ?) WHERE id = ?', [$delta, $userId]);
            }
        } catch (Throwable $e) {}
    }

    /** Add wallet credit */
    public static function addWalletCredit(int $userId, float $amount, string $description): void {
        try {
            DB::query('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?', [$amount, $userId]);
        } catch (Throwable $e) {}
    }

    /** Get total points for a user */
    public static function getPoints(int $userId): int {
        try {
            return (int)(DB::fetch('SELECT total_points FROM users WHERE id = ?', [$userId])['total_points'] ?? 0);
        } catch (Throwable $e) { return 0; }
    }

    /** Get wallet balance */
    public static function getWallet(int $userId): float {
        try {
            return (float)(DB::fetch('SELECT wallet_balance FROM users WHERE id = ?', [$userId])['wallet_balance'] ?? 0);
        } catch (Throwable $e) { return 0.0; }
    }

    /** Get the tier object for a given points total */
    public static function getTierByPoints(int $points): array {
        try {
            $tiers = DB::fetchAll('SELECT * FROM membership_tiers ORDER BY min_points DESC');
            foreach ($tiers as $t) {
                if ($points >= (int)$t['min_points']) return $t;
            }
        } catch (Throwable $e) {}
        // Fallback
        return ['slug'=>'starter','name'=>'Starter','color'=>'#6b7280','icon'=>'bi-circle','min_points'=>0,'benefits'=>'[]'];
    }

    /** Get next tier (or null if already at max) */
    public static function getNextTier(int $points): ?array {
        try {
            return DB::fetch(
                'SELECT * FROM membership_tiers WHERE min_points > ? ORDER BY min_points ASC LIMIT 1',
                [$points]
            ) ?: null;
        } catch (Throwable $e) { return null; }
    }

    /** All tiers ordered lowest to highest */
    public static function getAllTiers(): array {
        try {
            $tiers = DB::fetchAll('SELECT * FROM membership_tiers ORDER BY min_points ASC');
            return $tiers ?: self::DEFAULT_TIERS;
        } catch (Throwable $e) { return self::DEFAULT_TIERS; }
    }

    /** Points history for a user */
    public static function getHistory(int $userId, int $limit = 20): array {
        try {
            return DB::fetchAll(
                'SELECT * FROM user_points WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . $limit,
                [$userId]
            );
        } catch (Throwable $e) { return []; }
    }
}
