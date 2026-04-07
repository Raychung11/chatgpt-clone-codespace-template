<?php
declare(strict_types=1);
/**
 * admin/reset-admin-password.php
 * Emergency admin password reset tool.
 * DELETE THIS FILE after use.
 */

// ── Hardcoded safety token — change this to something secret before using ──
const RESET_TOKEN = 'changeme123';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';

boot_session();

$token   = $_GET['token'] ?? '';
$message = '';
$error   = '';

if ($token !== RESET_TOKEN) {
    http_response_code(403);
    die('Access denied. Provide ?token=YOUR_TOKEN in the URL.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email']    ?? ''));
    $password = $_POST['password']                 ?? '';
    $confirm  = $_POST['password_confirm']         ?? '';

    if ($email === '' || $password === '' || $confirm === '') {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // Upsert: update if exists, insert if not
        $stmt = db()->prepare('SELECT id FROM admins WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            $upd = db()->prepare('UPDATE admins SET password_hash = ?, is_active = 1 WHERE email = ?');
            $upd->execute([$hash, $email]);
            $message = "Password updated for <strong>" . htmlspecialchars($email) . "</strong>.";
        } else {
            $ins = db()->prepare(
                'INSERT INTO admins (name, email, password_hash, role, is_active) VALUES (?,?,?,?,1)'
            );
            $ins->execute(['Super Admin', $email, $hash, 'super_admin']);
            $message = "New super admin created: <strong>" . htmlspecialchars($email) . "</strong>.";
        }
    }
}

// List current admins
$admins = db()->query('SELECT id, name, email, role, is_active FROM admins ORDER BY id')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Password Reset</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-box" style="max-width:540px">
        <div class="auth-card">
            <h1 class="auth-title" style="color:var(--color-danger)">&#9888; Emergency Reset</h1>
            <p class="auth-sub">Delete this file after use.</p>

            <?php if ($message): ?>
                <div class="alert alert--success"><?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert--error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="?token=<?= e($token) ?>">
                <div class="form-group">
                    <label class="form-label">Admin Email</label>
                    <input type="email" name="email" class="form-control"
                           value="admin@example.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="password" class="form-control"
                           placeholder="Min 8 characters" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="password_confirm" class="form-control"
                           placeholder="Repeat password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Set Password</button>
            </form>

            <hr style="margin:24px 0;border-color:var(--color-border)">
            <p class="text-muted text-sm" style="margin-bottom:10px">Current admins in DB:</p>
            <table class="table table-sm">
                <thead><tr><th>Email</th><th>Role</th><th>Active</th></tr></thead>
                <tbody>
                <?php foreach ($admins as $a): ?>
                    <tr>
                        <td><?= e($a['email']) ?></td>
                        <td><span class="badge badge-primary"><?= e($a['role']) ?></span></td>
                        <td><?= $a['is_active'] ? '&#10003;' : '&#10007;' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$admins): ?>
                    <tr><td colspan="3" class="text-muted">No admins found</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
