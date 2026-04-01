<?php
declare(strict_types=1);

// ─── SilverDeals MY — Auth Helpers ──────────────────────────────────────────

function session_start_secure(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => (APP_ENV === 'production'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function auth_check(): bool
{
    return !empty($_SESSION['user_id']);
}

function auth_user(): ?array
{
    if (!auth_check()) return null;
    return [
        'id'       => $_SESSION['user_id'],
        'name'     => $_SESSION['user_name']     ?? '',
        'email'    => $_SESSION['user_email']    ?? '',
        'role'     => $_SESSION['user_role']     ?? '',
        'avatar'   => $_SESSION['user_avatar']   ?? '',
        'status'   => $_SESSION['user_status']   ?? '',
    ];
}

function auth_require(string $role = ''): void
{
    if (!auth_check()) {
        $back = urlencode($_SERVER['REQUEST_URI']);
        header("Location: /public/login.php?redirect={$back}");
        exit;
    }
    if ($role !== '' && ($_SESSION['user_role'] ?? '') !== $role) {
        // Allow superadmin through any role gate
        if (($_SESSION['user_role'] ?? '') !== ROLE_SUPERADMIN) {
            http_response_code(403);
            include __DIR__ . '/../public/errors/403.php';
            exit;
        }
    }
}

function auth_require_admin(): void
{
    if (!auth_check() || !in_array($_SESSION['user_role'] ?? '', [ROLE_ADMIN, ROLE_SUPERADMIN], true)) {
        header('Location: /admin/login.php');
        exit;
    }
}

function auth_login(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']     = $user['id'];
    $_SESSION['user_name']   = $user['name'];
    $_SESSION['user_email']  = $user['email'];
    $_SESSION['user_role']   = $user['role'];
    $_SESSION['user_avatar'] = $user['avatar'] ?? '';
    $_SESSION['user_status'] = $user['status'] ?? '';
}

function auth_logout(): void
{
    session_unset();
    session_destroy();
    session_start_secure();
    session_regenerate_id(true);
}

function auth_set_flash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

function auth_get_flash(): array
{
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flash;
}
