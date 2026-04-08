<?php
/**
 * PlotGold Malaysia — Secure Session Management
 */

defined('PLOTGOLD') or die('Direct access not permitted.');

function pg_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $cookieParams = [
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    session_name(SESSION_NAME);
    session_set_cookie_params($cookieParams);
    session_start();

    // Regenerate session ID periodically to prevent fixation
    if (empty($_SESSION['_initiated'])) {
        session_regenerate_id(true);
        $_SESSION['_initiated'] = true;
    }
}

function pg_session_regenerate(): void
{
    session_regenerate_id(true);
}

function pg_session_destroy(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function flash_set(string $type, string $message): void
{
    $_SESSION['_flash'][$type][] = $message;
}

function flash_get(string $type): array
{
    $msgs = $_SESSION['_flash'][$type] ?? [];
    unset($_SESSION['_flash'][$type]);
    return $msgs;
}

function flash_has(string $type): bool
{
    return !empty($_SESSION['_flash'][$type]);
}

function flash_all(): array
{
    $all = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $all;
}
