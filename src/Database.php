<?php
/**
 * STRate AI — Database Layer
 * PDO singleton wrapper for MySQL/MariaDB.
 */
class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST, DB_NAME, DB_CHARSET
            );
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$instance;
    }

    // ─── Generic helpers ──────────────────────────────────────────────────

    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return (int) self::getInstance()->lastInsertId();
    }

    // ─── Properties ───────────────────────────────────────────────────────

    public static function getAllProperties(): array
    {
        return self::query(
            'SELECT p.*, u.name AS owner_name
             FROM properties p
             JOIN users u ON p.user_id = u.id
             WHERE p.active = 1
             ORDER BY p.id ASC'
        );
    }

    public static function getProperty(int $id): ?array
    {
        return self::queryOne('SELECT * FROM properties WHERE id = ?', [$id]);
    }

    public static function upsertProperty(array $data): int
    {
        if (!empty($data['id'])) {
            self::execute(
                'UPDATE properties
                 SET name=?, location=?, base_price=?, min_price=?, max_price=?, room_type=?, bedrooms=?
                 WHERE id=?',
                [$data['name'], $data['location'], $data['base_price'],
                 $data['min_price'], $data['max_price'], $data['room_type'],
                 $data['bedrooms'], $data['id']]
            );
            return (int) $data['id'];
        }
        return self::execute(
            'INSERT INTO properties (user_id, name, location, base_price, min_price, max_price, room_type, bedrooms)
             VALUES (?,?,?,?,?,?,?,?)',
            [$data['user_id'], $data['name'], $data['location'], $data['base_price'],
             $data['min_price'], $data['max_price'], $data['room_type'], $data['bedrooms']]
        );
    }

    public static function deleteProperty(int $id): void
    {
        self::execute('UPDATE properties SET active = 0 WHERE id = ?', [$id]);
    }

    // ─── Market Data ──────────────────────────────────────────────────────

    public static function upsertMarketData(array $d): void
    {
        self::execute(
            'INSERT INTO market_data
                (location, date, avg_price, min_price, max_price,
                 listing_count, occupancy_rate, event_flag, event_name, source)
             VALUES (?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                avg_price      = VALUES(avg_price),
                min_price      = VALUES(min_price),
                max_price      = VALUES(max_price),
                listing_count  = VALUES(listing_count),
                occupancy_rate = VALUES(occupancy_rate),
                event_flag     = VALUES(event_flag),
                event_name     = VALUES(event_name),
                source         = VALUES(source)',
            [
                $d['location'], $d['date'], $d['avg_price'], $d['min_price'],
                $d['max_price'], $d['listing_count'], $d['occupancy_rate'],
                $d['event_flag'] ?? 0, $d['event_name'] ?? null, $d['source'] ?? 'simulated',
            ]
        );
    }

    public static function getMarketData(string $location, string $date): ?array
    {
        return self::queryOne(
            'SELECT * FROM market_data WHERE location = ? AND date = ?',
            [$location, $date]
        );
    }

    public static function getMarketDataRange(string $location, int $days = 30): array
    {
        return self::query(
            'SELECT * FROM market_data WHERE location = ?
             ORDER BY date DESC LIMIT ?',
            [$location, $days]
        );
    }

    public static function getLocations(): array
    {
        $rows = self::query('SELECT DISTINCT location FROM market_data ORDER BY location ASC');
        return array_column($rows, 'location');
    }

    // ─── Recommendations ──────────────────────────────────────────────────

    public static function upsertRecommendation(array $d): void
    {
        self::execute(
            'INSERT INTO price_recommendations
                (property_id, date, base_price, suggested_price, confidence_score,
                 demand_factor, event_boost, competitor_gap, occupancy_adj, reason)
             VALUES (?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                suggested_price  = VALUES(suggested_price),
                confidence_score = VALUES(confidence_score),
                demand_factor    = VALUES(demand_factor),
                event_boost      = VALUES(event_boost),
                competitor_gap   = VALUES(competitor_gap),
                occupancy_adj    = VALUES(occupancy_adj),
                reason           = VALUES(reason)',
            [
                $d['property_id'], $d['date'], $d['base_price'],
                $d['suggested_price'], $d['confidence_score'],
                $d['demand_factor'] ?? 0, $d['event_boost'] ?? 0,
                $d['competitor_gap'] ?? 0, $d['occupancy_adj'] ?? 0,
                $d['reason'] ?? '',
            ]
        );
    }

    public static function getRecommendations(int $propertyId, int $days = 7): array
    {
        return self::query(
            'SELECT * FROM price_recommendations
             WHERE property_id = ?
             ORDER BY date ASC LIMIT ?',
            [$propertyId, $days]
        );
    }

    public static function getLatestRecommendation(int $propertyId, string $date = ''): ?array
    {
        if (!$date) $date = date('Y-m-d');
        return self::queryOne(
            'SELECT * FROM price_recommendations
             WHERE property_id = ? AND date = ?',
            [$propertyId, $date]
        );
    }

    // ─── Cron Logs ────────────────────────────────────────────────────────

    public static function logCron(string $job, string $status, string $msg = '', int $ms = 0): void
    {
        self::execute(
            'INSERT INTO cron_logs (job_name, status, message, duration_ms) VALUES (?,?,?,?)',
            [$job, $status, $msg, $ms]
        );
    }

    public static function getCronLogs(int $limit = 50): array
    {
        return self::query(
            'SELECT * FROM cron_logs ORDER BY created_at DESC LIMIT ?',
            [$limit]
        );
    }

    // ─── Scraper Runs ─────────────────────────────────────────────────────

    public static function getScraperRuns(int $limit = 50): array
    {
        return self::query(
            'SELECT * FROM scraper_runs ORDER BY created_at DESC LIMIT ?',
            [$limit]
        );
    }

    public static function getScraperStats(): array
    {
        $row = self::queryOne(
            "SELECT
                COUNT(*)                       AS total,
                SUM(status = 'done')           AS success,
                SUM(status = 'failed')         AS failed,
                ROUND(AVG(duration_ms))        AS avg_ms,
                MAX(created_at)                AS last_run,
                SUM(status = 'done' AND source != 'simulated_fallback') AS real_data
             FROM scraper_runs
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        return $row ?? ['total' => 0, 'success' => 0, 'failed' => 0, 'avg_ms' => 0, 'last_run' => null, 'real_data' => 0];
    }

    // ─── Users ────────────────────────────────────────────────────────────

    public static function getDemoUserId(): int
    {
        $row = self::queryOne("SELECT id FROM users WHERE email = 'demo@strate.ai'");
        if ($row) return (int) $row['id'];
        return self::execute(
            "INSERT INTO users (name, email, phone) VALUES ('Demo Owner','demo@strate.ai','+60123456789')"
        );
    }
}
