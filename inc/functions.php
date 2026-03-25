<?php
/**
 * Global Helper Functions
 * /inc/functions.php
 */

// -------------------------------------------------
// App bootstrap
// -------------------------------------------------

/**
 * Bootstrap: load config, start session, set timezone.
 */
function app_init(): array
{
    $cfg = require BASE_PATH . '/config/app.php';

    date_default_timezone_set($cfg['app_timezone']);

    ini_set('display_errors', ($cfg['app_debug'] ? '1' : '0'));
    error_reporting(E_ALL);

    Auth::startSession();

    return $cfg;
}

// -------------------------------------------------
// Response helpers
// -------------------------------------------------

/**
 * Send a JSON API response and exit.
 */
function json_response(string $status, string $message, $data = null, int $httpCode = 200): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'status'  => $status,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_success(string $message = 'OK', $data = null, int $httpCode = 200): void
{
    json_response('success', $message, $data, $httpCode);
}

function json_error(string $message = 'Error', $data = null, int $httpCode = 400): void
{
    json_response('error', $message, $data, $httpCode);
}

// -------------------------------------------------
// Validation
// -------------------------------------------------

/**
 * Validate required fields. Returns array of error messages.
 */
function validate_required(array $data, array $fields): array
{
    $errors = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            $errors[] = "Field '{$field}' is required.";
        }
    }
    return $errors;
}

function validate_phone(string $phone): bool
{
    // Malaysian format: 601xxxxxxxx (10-12 digits starting with 60)
    return (bool) preg_match('/^60\d{8,10}$/', $phone);
}

function validate_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function sanitize_string(string $input, int $maxLen = 255): string
{
    return substr(trim(htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, $maxLen);
}

function sanitize_int($value): int
{
    return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
}

// -------------------------------------------------
// Pagination
// -------------------------------------------------

function paginate(string $table, string $where = '1=1', array $params = [], int $page = 1, int $perPage = 20): array
{
    $page    = max(1, $page);
    $offset  = ($page - 1) * $perPage;
    $total   = (int) Database::fetchOne("SELECT COUNT(*) AS c FROM {$table} WHERE {$where}", $params)['c'];
    $pages   = (int) ceil($total / $perPage);

    return [
        'total'    => $total,
        'per_page' => $perPage,
        'current'  => $page,
        'pages'    => $pages,
        'offset'   => $offset,
    ];
}

// -------------------------------------------------
// Loyalty / Points
// -------------------------------------------------

/**
 * Add or deduct points for a user and record the transaction.
 */
function add_loyalty_points(int $userId, int $points, string $type, string $refType = null, int $refId = null, string $desc = null, int $outletId = null): bool
{
    try {
        Database::beginTransaction();

        // Get current balance
        $profile = Database::fetchOne('SELECT total_points FROM customer_profiles WHERE user_id = ?', [$userId]);
        if (!$profile) {
            Database::rollback();
            return false;
        }

        $newBalance = max(0, (int) $profile['total_points'] + $points);

        // Update profile
        $lifetimeUpdate = $points > 0 ? ', lifetime_points = lifetime_points + ' . (int) $points : '';
        Database::execute(
            "UPDATE customer_profiles SET total_points = ? {$lifetimeUpdate} WHERE user_id = ?",
            [$newBalance, $userId]
        );

        // Update tier
        update_loyalty_tier($userId, $newBalance);

        // Record transaction
        Database::insert(
            'INSERT INTO loyalty_transactions (user_id, points, type, reference_type, reference_id, description, balance_after, outlet_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $points, $type, $refType, $refId, $desc, $newBalance, $outletId]
        );

        Database::commit();
        return true;
    } catch (Exception $e) {
        Database::rollback();
        error_log('[Loyalty] Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Recalculate and update user tier based on lifetime points.
 */
function update_loyalty_tier(int $userId, int $currentPoints): void
{
    $settings = get_settings(['tier_silver_threshold', 'tier_gold_threshold', 'tier_platinum_threshold']);

    $silver   = (int) ($settings['tier_silver_threshold']   ?? 500);
    $gold     = (int) ($settings['tier_gold_threshold']     ?? 2000);
    $platinum = (int) ($settings['tier_platinum_threshold'] ?? 5000);

    $lifetime = (int) (Database::fetchOne('SELECT lifetime_points FROM customer_profiles WHERE user_id = ?', [$userId])['lifetime_points'] ?? 0);

    $tier = 'bronze';
    if ($lifetime >= $platinum) $tier = 'platinum';
    elseif ($lifetime >= $gold)   $tier = 'gold';
    elseif ($lifetime >= $silver) $tier = 'silver';

    Database::execute('UPDATE customer_profiles SET tier = ? WHERE user_id = ?', [$tier, $userId]);
}

// -------------------------------------------------
// Settings
// -------------------------------------------------

function get_settings(array $keys = []): array
{
    if (empty($keys)) {
        $rows = Database::fetchAll('SELECT `key`, `value` FROM settings');
    } else {
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $rows = Database::fetchAll("SELECT `key`, `value` FROM settings WHERE `key` IN ({$placeholders})", $keys);
    }

    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['key']] = $row['value'];
    }
    return $settings;
}

function set_setting(string $key, string $value, string $group = 'general'): void
{
    Database::execute(
        'INSERT INTO settings (`key`, `value`, `group`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
        [$key, $value, $group]
    );
}

// -------------------------------------------------
// Utilities
// -------------------------------------------------

function generate_order_no(): string
{
    return 'ORD' . strtoupper(date('ymd')) . random_int(1000, 9999);
}

function generate_reservation_no(): string
{
    return 'RSV' . strtoupper(date('ymd')) . random_int(100, 999);
}

function generate_voucher_code(): string
{
    return strtoupper(bin2hex(random_bytes(5)));
}

function generate_referral_code(string $name): string
{
    $base = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', substr($name, 0, 4)));
    return $base . strtoupper(bin2hex(random_bytes(3)));
}

function format_points(int $points): string
{
    return number_format($points) . ' pts';
}

function format_currency(float $amount, string $currency = 'MYR'): string
{
    return $currency . ' ' . number_format($amount, 2);
}

function time_ago(string $datetime): string
{
    $now  = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->diff($then);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'just now';
}

// -------------------------------------------------
// Admin log
// -------------------------------------------------

function admin_log(string $action, string $module, int $refId = null, string $desc = null): void
{
    $userId = Auth::currentUserId();
    $ip     = $_SERVER['REMOTE_ADDR'] ?? null;
    Database::insert(
        'INSERT INTO admin_logs (user_id, action, module, reference_id, description, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)',
        [$userId, $action, $module, $refId, $desc, $ip]
    );
}

// -------------------------------------------------
// Flash messages (admin UI)
// -------------------------------------------------

function flash(string $key, string $msg): void
{
    Auth::startSession();
    $_SESSION['flash'][$key] = $msg;
}

function get_flash(string $key): ?string
{
    Auth::startSession();
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}
