<?php
declare(strict_types=1);

// ─── SilverDeals MY — CSRF Protection ───────────────────────────────────────

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time']) ||
        (time() - $_SESSION['csrf_token_time']) > CSRF_LIFETIME) {
        $_SESSION['csrf_token']      = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_abort(): void
{
    if (!csrf_verify()) {
        http_response_code(403);
        die('Invalid CSRF token. Please go back and try again.');
    }
}
