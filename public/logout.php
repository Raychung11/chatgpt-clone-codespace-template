<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';

if (auth_check()) {
    db()->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'member.logout', ?)")
        ->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? null]);
}

auth_logout();
auth_set_flash('success', 'You have been logged out successfully.');
redirect('/public/login.php');
