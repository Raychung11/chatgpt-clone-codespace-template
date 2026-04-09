<?php
declare(strict_types=1);

/**
 * inc/functions.php
 * General helper functions used across the platform.
 */

// ── Output helpers ────────────────────────────────────────────────────────────

/** Escape for HTML output */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Redirect and exit */
function redirect(string $url, int $code = 302): never
{
    http_response_code($code);
    header('Location: ' . $url);
    exit;
}

/** JSON response helper */
function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── Flash messages ────────────────────────────────────────────────────────────

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flash_success(string $msg): void { flash('success', $msg); }
function flash_error(string $msg): void   { flash('error',   $msg); }
function flash_info(string $msg): void    { flash('info',    $msg); }

function render_flash(): string
{
    if (empty($_SESSION['_flash'])) return '';

    $html = '';
    foreach ($_SESSION['_flash'] as $f) {
        $type = e($f['type']);
        $msg  = e($f['message']);
        $html .= "<div class=\"alert alert--{$type}\" role=\"alert\">{$msg}</div>\n";
    }
    unset($_SESSION['_flash']);
    return $html;
}

// ── Validation ────────────────────────────────────────────────────────────────

function validate_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_password(string $password): bool
{
    // Min 8 chars, at least one letter and one number
    return strlen($password) >= 8
        && preg_match('/[A-Za-z]/', $password)
        && preg_match('/[0-9]/', $password);
}

// ── String helpers ────────────────────────────────────────────────────────────

/** Generate a cryptographically random token */
function generate_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

/** Generate a unique referral code */
function generate_referral_code(): string
{
    return strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
}

/** Format credits for display */
function format_credits(float $amount): string
{
    return number_format($amount, 2);
}

/** Format currency for display */
function format_currency(float $amount, string $currency = 'MYR'): string
{
    return $currency . ' ' . number_format($amount, 2);
}

/** Format datetime for display in app timezone (Asia/Kuala_Lumpur / MYT) */
function format_datetime(string|null $datetime): string
{
    if (!$datetime) return '—';
    $tz = new \DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Kuala_Lumpur');
    $dt = new \DateTime($datetime, new \DateTimeZone('UTC'));
    $dt->setTimezone($tz);
    return $dt->format('d M Y, h:i A');
}

/** Truncate text */
function truncate(string $text, int $length = 80): string
{
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . '…';
}

// ── IP & Request ──────────────────────────────────────────────────────────────

function client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

function user_agent(): string
{
    return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
}

// ── Activity logging ──────────────────────────────────────────────────────────

function log_activity(
    string $actor_type,
    ?int   $actor_id,
    string $action,
    string $description = ''
): void {
    try {
        $stmt = db()->prepare(
            'INSERT INTO `activity_logs`
             (`actor_type`,`actor_id`,`action`,`description`,`ip_address`,`user_agent`)
             VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute([
            $actor_type,
            $actor_id,
            $action,
            $description,
            client_ip(),
            user_agent(),
        ]);
    } catch (PDOException $e) {
        error_log('[activity_log] ' . $e->getMessage());
    }
}

// ── Pagination ────────────────────────────────────────────────────────────────

function paginate(int $total, int $page, int $perPage = ITEMS_PER_PAGE): array
{
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page       = max(1, min($page, $totalPages));
    $offset     = ($page - 1) * $perPage;

    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $page,
        'total_pages' => $totalPages,
        'offset'      => $offset,
        'has_prev'    => $page > 1,
        'has_next'    => $page < $totalPages,
    ];
}

/** Build pagination URL preserving existing query params */
function page_url(int $page): string
{
    $params        = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

// ── Secure file upload ────────────────────────────────────────────────────────

/**
 * Handle a receipt file upload.
 * Returns ['ok' => true, 'path' => ..., 'name' => ..., 'size' => ..., 'mime' => ...]
 * or      ['ok' => false, 'error' => ...]
 */
function upload_receipt(array $file, int $payment_order_id): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload error: code ' . $file['error']];
    }

    if ($file['size'] > MAX_RECEIPT_SIZE_BYTES) {
        return ['ok' => false, 'error' => 'File exceeds maximum allowed size of 5 MB.'];
    }

    // Verify MIME via finfo (do NOT trust $_FILES['type'])
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);

    if (!in_array($mime, ALLOWED_RECEIPT_TYPES, true)) {
        return ['ok' => false, 'error' => 'Only JPEG, PNG, and PDF files are allowed.'];
    }

    $ext       = match ($mime) {
        'image/jpeg'       => 'jpg',
        'image/png'        => 'png',
        'application/pdf'  => 'pdf',
        default            => 'bin',
    };
    $filename  = 'receipt_' . $payment_order_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destDir   = UPLOAD_PATH . '/receipts';
    $destPath  = $destDir . '/' . $filename;

    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['ok' => false, 'error' => 'Could not save the uploaded file.'];
    }

    return [
        'ok'   => true,
        'path' => 'uploads/receipts/' . $filename,
        'name' => $file['name'],
        'size' => $file['size'],
        'mime' => $mime,
    ];
}
