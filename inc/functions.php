<?php
declare(strict_types=1);

// ─── SilverDeals MY — General Helpers ───────────────────────────────────────

function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void
{
    header("Location: {$url}");
    exit;
}

function active_nav(string $path): string
{
    return (str_contains($_SERVER['REQUEST_URI'], $path)) ? 'active' : '';
}

function format_points(int $pts): string
{
    return number_format($pts) . ' pts';
}

function format_myr(float $amount): string
{
    return 'RM ' . number_format($amount, 2);
}

function referral_link(string $code): string
{
    return BASE_URL . '/join-member.php?ref=' . urlencode($code);
}

function generate_referral_code(int $userId): string
{
    return 'SD' . strtoupper(base_convert((string)$userId, 10, 36)) . strtoupper(substr(uniqid(), -4));
}

function generate_member_number(): string
{
    return 'SLV' . date('Y') . str_pad((string)rand(1, 99999), 5, '0', STR_PAD_LEFT);
}

function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)       return 'just now';
    if ($diff < 3600)     return floor($diff / 60) . ' min ago';
    if ($diff < 86400)    return floor($diff / 3600) . ' hr ago';
    if ($diff < 2592000)  return floor($diff / 86400) . ' days ago';
    return date('d M Y', strtotime($datetime));
}

function flash_html(): string
{
    $flash = auth_get_flash();
    if (empty($flash)) return '';
    $html = '';
    foreach ($flash as $type => $msg) {
        $icon = match($type) {
            'success' => '✓',
            'error'   => '✕',
            'warning' => '⚠',
            default   => 'ℹ',
        };
        $html .= '<div class="alert alert--' . e($type) . '">'
               . '<span class="alert__icon">' . $icon . '</span>'
               . '<span>' . e($msg) . '</span>'
               . '</div>';
    }
    return $html;
}

function whatsapp_url(string $message = ''): string
{
    $msg = $message ?: 'Hello, I would like to enquire about SilverDeals MY.';
    return 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . rawurlencode($msg);
}

// Simple rate-limit check using session (suitable for shared hosting)
// For production scale, replace with Redis/DB-backed rate limiting.
function rate_limit(string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool
{
    $now = time();
    if (!isset($_SESSION['rl'][$key])) {
        $_SESSION['rl'][$key] = ['count' => 0, 'start' => $now];
    }
    $rl = &$_SESSION['rl'][$key];
    if (($now - $rl['start']) > $windowSeconds) {
        $rl = ['count' => 0, 'start' => $now];
    }
    $rl['count']++;
    return $rl['count'] <= $maxAttempts;
}

function slugify(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', trim($text));
    return $text;
}
