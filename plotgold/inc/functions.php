<?php
/**
 * PlotGold Malaysia — Global Helper Functions
 */

defined('PLOTGOLD') or die('Direct access not permitted.');

// ── Output Sanitization ───────────────────────────────────────────────────────

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function e(mixed $value): string
{
    return h($value);
}

// ── URL / Slug Helpers ────────────────────────────────────────────────────────

function pg_url(string $path = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function asset_url(string $path): string
{
    return rtrim(BASE_URL, '/') . '/assets/' . ltrim($path, '/');
}

function slug(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s\-]/', '', $text);
    $text = preg_replace('/[\s\-]+/', '-', $text);
    return trim($text, '-');
}

function listing_url(string $listingSlug): string
{
    return pg_url('listing/' . $listingSlug);
}

function park_url(string $parkSlug): string
{
    return pg_url('parks/' . $parkSlug);
}

function city_url(string $citySlug): string
{
    return pg_url('city/' . $citySlug);
}

// ── UUID ──────────────────────────────────────────────────────────────────────

function pg_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// ── Currency / Number Formatting ──────────────────────────────────────────────

function format_currency(mixed $amount, string $symbol = 'RM'): string
{
    if ($amount === null || $amount === '') return '—';
    return $symbol . ' ' . number_format((float)$amount, 2);
}

function format_number(mixed $n, int $decimals = 0): string
{
    return number_format((float)$n, $decimals);
}

// ── Date / Time Helpers ───────────────────────────────────────────────────────

function format_date(mixed $date, string $format = 'd M Y'): string
{
    if (!$date) return '—';
    return date($format, is_numeric($date) ? $date : strtotime($date));
}

function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)         return 'just now';
    if ($diff < 3600)       return floor($diff / 60) . ' min ago';
    if ($diff < 86400)      return floor($diff / 3600) . ' hr ago';
    if ($diff < 2592000)    return floor($diff / 86400) . ' days ago';
    return format_date($datetime, 'd M Y');
}

// ── Redirect ──────────────────────────────────────────────────────────────────

function redirect(string $url, int $code = 302): never
{
    if (!str_starts_with($url, 'http')) {
        $url = rtrim(BASE_URL, '/') . '/' . ltrim($url, '/');
    }
    header("Location: $url", true, $code);
    exit;
}

function redirect_back(string $fallback = '/'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    redirect($ref ?: $fallback);
}

// ── Flash / Alerts ────────────────────────────────────────────────────────────

function render_flash(): string
{
    $html = '';
    foreach (flash_all() as $type => $messages) {
        foreach ($messages as $msg) {
            $html .= '<div class="alert alert-' . h($type) . ' alert-dismissible fade show" role="alert">'
                   . h($msg)
                   . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                   . '</div>';
        }
    }
    return $html;
}

// ── Pagination ────────────────────────────────────────────────────────────────

function paginate(string $sql, array $params, int $page, int $perPage): array
{
    $countSql = 'SELECT COUNT(*) as total FROM (' . $sql . ') AS _count';
    $total    = (int)(Database::fetchOne($countSql, $params)['total'] ?? 0);
    $pages    = (int)ceil($total / $perPage);
    $offset   = ($page - 1) * $perPage;

    $rows = Database::fetchAll($sql . " LIMIT {$perPage} OFFSET {$offset}", $params);

    return [
        'rows'       => $rows,
        'total'      => $total,
        'pages'      => $pages,
        'page'       => $page,
        'per_page'   => $perPage,
        'has_prev'   => $page > 1,
        'has_next'   => $page < $pages,
    ];
}

function pagination_links(array $pagData, string $baseUrl): string
{
    if ($pagData['pages'] <= 1) return '';
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';

    $sep = str_contains($baseUrl, '?') ? '&' : '?';

    if ($pagData['has_prev']) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $sep . 'page=' . ($pagData['page'] - 1) . '">&laquo; Prev</a></li>';
    }

    $start = max(1, $pagData['page'] - 2);
    $end   = min($pagData['pages'], $pagData['page'] + 2);
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $pagData['page'] ? ' active' : '';
        $html  .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $baseUrl . $sep . 'page=' . $i . '">' . $i . '</a></li>';
    }

    if ($pagData['has_next']) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $sep . 'page=' . ($pagData['page'] + 1) . '">Next &raquo;</a></li>';
    }
    $html .= '</ul></nav>';
    return $html;
}

// ── File Upload ───────────────────────────────────────────────────────────────

function upload_file(array $file, string $destDir, array $allowedTypes = [], int $maxSize = 0): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error code: ' . $file['error']];
    }

    $maxSize = $maxSize ?: MAX_UPLOAD_SIZE;
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File is too large. Maximum ' . ($maxSize / 1048576) . 'MB.'];
    }

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if ($allowedTypes && !in_array($mimeType, $allowedTypes, true)) {
        return ['success' => false, 'error' => 'File type not allowed: ' . $mimeType];
    }

    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destPath = rtrim($destDir, '/') . '/' . $filename;

    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file.'];
    }

    return [
        'success'   => true,
        'filename'  => $filename,
        'path'      => $destPath,
        'mime_type' => $mimeType,
        'size'      => $file['size'],
    ];
}

// ── Activity Logging ──────────────────────────────────────────────────────────

function activity_log(?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null): void
{
    try {
        Database::query(
            'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $action,
                $entityType,
                $entityId,
                $description,
                ip_address(),
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 499),
            ]
        );
    } catch (Throwable) {
        // Never break page flow for a log failure
    }
}

function ip_address(): string
{
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = explode(',', $_SERVER[$h])[0];
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

// ── Listing Helpers ───────────────────────────────────────────────────────────

function generate_listing_code(): string
{
    return 'PG-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

function listing_badge_html(string $badge): string
{
    $badges = [
        'verified'       => '<span class="badge badge-verified"><i class="fas fa-shield-alt me-1"></i>Verified</span>',
        'partial'        => '<span class="badge badge-partial"><i class="fas fa-check-circle me-1"></i>Partially Verified</span>',
        'transfer_check' => '<span class="badge badge-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i>Transfer Check</span>',
        'pending'        => '<span class="badge bg-secondary">Pending Review</span>',
        'none'           => '',
    ];
    return $badges[$badge] ?? '';
}

function listing_urgency_html(string $urgency): string
{
    if ($urgency === 'urgent' || $urgency === 'immediate') {
        return '<span class="badge bg-danger ms-1">Urgent Sale</span>';
    }
    return '';
}

function verification_badge_from_score(int $score): string
{
    if ($score >= 90) return BADGE_VERIFIED;
    if ($score >= 60) return BADGE_PARTIAL;
    if ($score > 0)   return BADGE_PENDING;
    return BADGE_NONE;
}

function calc_verification_score(array $checks): int
{
    $totalWeight  = array_sum(array_column(VERIFICATION_CHECKS, 'weight'));
    $earnedWeight = 0;
    foreach (VERIFICATION_CHECKS as $key => $cfg) {
        if (($checks[$key] ?? '') === 'passed') {
            $earnedWeight += $cfg['weight'];
        }
    }
    return $totalWeight > 0 ? (int)round($earnedWeight / $totalWeight * 100) : 0;
}

// ── Settings ──────────────────────────────────────────────────────────────────

function get_setting(string $key, mixed $default = null): mixed
{
    static $cache = [];
    if (!array_key_exists($key, $cache)) {
        $row = Database::fetchOne('SELECT setting_value, setting_type FROM settings WHERE setting_key = ?', [$key]);
        if (!$row) {
            $cache[$key] = $default;
        } else {
            $v = $row['setting_value'];
            $cache[$key] = match($row['setting_type']) {
                'int'  => (int)$v,
                'bool' => (bool)(int)$v,
                'json' => json_decode($v, true),
                default => $v,
            };
        }
    }
    return $cache[$key] ?? $default;
}

// ── Input Sanitization ────────────────────────────────────────────────────────

function clean(mixed $value): string
{
    return trim(strip_tags((string)$value));
}

function clean_int(mixed $value): int
{
    return (int)filter_var($value, FILTER_SANITIZE_NUMBER_INT);
}

function clean_float(mixed $value): float
{
    return (float)filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

function clean_email(mixed $value): string
{
    return strtolower(trim((string)filter_var($value, FILTER_SANITIZE_EMAIL)));
}

function validate_phone(string $phone): bool
{
    return (bool)preg_match('/^\+?[0-9\s\-]{8,20}$/', $phone);
}

// ── WhatsApp Link ─────────────────────────────────────────────────────────────

function whatsapp_link(string $message = '', string $number = ''): string
{
    $number  = $number ?: WHATSAPP_NUMBER;
    $message = urlencode($message);
    return "https://wa.me/{$number}?text={$message}";
}

// ── Enquiry Code Generator ────────────────────────────────────────────────────

function generate_enquiry_code(): string
{
    return 'ENQ-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

function generate_quote_code(): string
{
    return 'QT-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}
