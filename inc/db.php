<?php
declare(strict_types=1);

// ─── SilverDeals MY — Database Connection (Singleton PDO) ───────────────────

function db(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $cfg = require __DIR__ . '/../config/database.php';

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $cfg['host'],
        $cfg['port'],
        $cfg['dbname'],
        $cfg['charset']
    );

    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $cfg['options']);
    } catch (PDOException $e) {
        // Log to server error log; never expose credentials to browser
        error_log('[SilverDeals DB] Connection failed: ' . $e->getMessage());
        http_response_code(503);
        include __DIR__ . '/../public/errors/503.php';
        exit;
    }

    return $pdo;
}
