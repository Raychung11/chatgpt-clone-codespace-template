<?php
/**
 * API Middleware helpers
 * /api/middleware.php
 *
 * Include this in API route files that require authentication.
 */

/**
 * Require Bearer token auth. Returns user array or exits with 401.
 */
function api_require_auth(): array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        json_error('Unauthenticated. Provide a Bearer token.', null, 401);
    }

    $userId = Auth::validateApiToken(trim($matches[1]));
    if (!$userId) {
        json_error('Invalid or expired token.', null, 401);
    }

    $user = Database::fetchOne('SELECT * FROM users WHERE id = ? AND status = "active"', [$userId]);
    if (!$user) {
        json_error('User not found or deactivated.', null, 401);
    }

    return $user;
}

/**
 * Simple rate limiter using DB (session-free).
 * Blocks if > $max requests within $window seconds.
 */
function api_rate_limit(string $key, int $max = 10, int $window = 60): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    // This is a lightweight implementation using DB.
    // For production, replace with Redis/APCu for better performance.
    $cacheKey = hash('sha256', $ip . ':' . $key);
    $row = Database::fetchOne(
        'SELECT COUNT(*) AS cnt FROM admin_logs WHERE description = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)',
        [$cacheKey, $window]
    );
    if ((int)($row['cnt'] ?? 0) >= $max) {
        json_error('Too many requests. Please slow down.', null, 429);
    }
    Database::insert('INSERT INTO admin_logs (action, module, description) VALUES ("rate_limit","api",?)', [$cacheKey]);
}
