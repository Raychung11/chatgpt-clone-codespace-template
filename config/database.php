<?php
declare(strict_types=1);

/**
 * config/database.php
 * Returns a singleton PDO connection.
 * Usage:  $pdo = db();
 */

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::MYSQL_ATTR_FOUND_ROWS   => true,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Never expose DB credentials in output
            if (APP_DEBUG) {
                error_log('[DB] Connection failed: ' . $e->getMessage());
                die('<h1>Database connection failed.</h1><pre>' . htmlspecialchars($e->getMessage()) . '</pre>');
            }
            die('<h1>Service temporarily unavailable. Please try again later.</h1>');
        }
    }

    return $pdo;
}

/**
 * Convenience: fetch a single setting value from the settings table.
 */
function setting(string $key, mixed $default = null): mixed
{
    static $cache = [];

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = db()->prepare('SELECT `value`, `type` FROM `settings` WHERE `key` = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if (!$row) {
            $cache[$key] = $default;
            return $default;
        }

        $value = match ($row['type']) {
            'integer' => (int)   $row['value'],
            'float'   => (float) $row['value'],
            'boolean' => (bool)  $row['value'],
            'json'    => json_decode($row['value'], true),
            default   => $row['value'],
        };

        $cache[$key] = $value;
        return $value;
    } catch (PDOException) {
        return $default;
    }
}
