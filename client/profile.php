<?php
declare(strict_types=1);

/**
 * client/profile.php
 * User profile settings — name, phone, avatar upload, password change.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/wallet.php';
require_once __DIR__ . '/../inc/layout.php';

boot_session();
$user = require_auth('/public/login.php');
$uid  = (int)$user['id'];
$pdo  = db();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    // ── Update profile info ───────────────────────────────────────────────────
    if ($action === 'profile') {
        $name  = trim($_POST['name']  ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (mb_strlen($name) < 2) {
            $errors['name'] = 'Name must be at least 2 characters.';
        }

        // Avatar upload (optional)
        $avatarPath = $user['avatar'];
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['avatar'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors['avatar'] = 'Upload error.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $errors['avatar'] = 'Avatar must be under 2 MB.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($file['tmp_name']);
                if (!in_array($mime, ['image/jpeg','image/png','image/gif','image/webp'], true)) {
                    $errors['avatar'] = 'Only JPEG, PNG, GIF, or WebP images allowed.';
                } else {
                    $ext  = match ($mime) {
                        'image/png'  => 'png',
                        'image/gif'  => 'gif',
                        'image/webp' => 'webp',
                        default      => 'jpg',
                    };
                    $dest = UPLOAD_PATH . '/avatars';
                    if (!is_dir($dest)) mkdir($dest, 0755, true);

                    $filename   = 'avatar_' . $uid . '_' . time() . '.' . $ext;
                    $destPath   = $dest . '/' . $filename;
                    if (move_uploaded_file($file['tmp_name'], $destPath)) {
                        // Delete old avatar
                        if ($avatarPath && file_exists(BASE_PATH . '/' . $avatarPath)) {
                            @unlink(BASE_PATH . '/' . $avatarPath);
                        }
                        $avatarPath = 'uploads/avatars/' . $filename;
                    } else {
                        $errors['avatar'] = 'Could not save avatar.';
                    }
                }
            }
        }

        if (empty($errors)) {
            $pdo->prepare(
                'UPDATE `users` SET `name`=?, `phone`=?, `avatar`=? WHERE `id`=?'
            )->execute([$name, $phone ?: null, $avatarPath, $uid]);

            // Refresh session name
            $_SESSION['user_name'] = $name;

            log_activity('user', $uid, 'profile_update', 'Profile updated');
            flash_success('Profile updated.');
            redirect(BASE_URL . '/client/profile.php');
        }
    }

    // ── Change password ───────────────────────────────────────────────────────
    if ($action === 'password') {
        $current  = $_POST['current_password']  ?? '';
        $new_pass = $_POST['new_password']       ?? '';
        $confirm  = $_POST['confirm_password']   ?? '';

        // Re-fetch hash
        $stmt = $pdo->prepare('SELECT `password_hash` FROM `users` WHERE `id`=? LIMIT 1');
        $stmt->execute([$uid]);
        $row = $stmt->fetch();

        if (!password_verify($current, $row['password_hash'])) {
            $errors['current'] = 'Current password is incorrect.';
        } elseif (!validate_password($new_pass)) {
            $errors['new_pass'] = 'New password must be at least 8 characters with letters and numbers.';
        } elseif ($new_pass !== $confirm) {
            $errors['confirm'] = 'Passwords do not match.';
        } else {
            $hash = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
            $pdo->prepare('UPDATE `users` SET `password_hash`=? WHERE `id`=?')->execute([$hash, $uid]);

            log_activity('user', $uid, 'password_changed', 'Password changed via profile');
            flash_success('Password updated successfully.');
            redirect(BASE_URL . '/client/profile.php');
        }
    }
}

// Refresh user data
$stmt = $pdo->prepare('SELECT * FROM `users` WHERE `id`=? LIMIT 1');
$stmt->execute([$uid]);
$user = $stmt->fetch();

$balance = wallet_balance($uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile — <?= e(setting('site_name','VideoSaaS')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
</head>
<body>
<?php render_client_navbar($user, ''); ?>

<div class="container main-content">
    <?= render_flash() ?>

    <div class="page-header">
        <div>
            <h1 class="page-title">Profile Settings</h1>
            <p class="page-sub">Update your account details</p>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">

        <!-- ── Profile info ─────────────────────────────────────────── -->
        <div class="card">
            <div class="card-header"><span class="card-title">Personal Info</span></div>

            <?php if (!empty($errors['name']) || !empty($errors['avatar'])): ?>
                <div class="alert alert--error">
                    <?= e($errors['name'] ?? $errors['avatar'] ?? '') ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="profile">

                <!-- Avatar preview -->
                <div style="text-align:center;margin-bottom:20px">
                    <?php if ($user['avatar']): ?>
                        <img src="<?= BASE_URL . '/' . e($user['avatar']) ?>"
                             alt="Avatar"
                             style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid var(--color-primary)">
                    <?php else: ?>
                        <div style="width:80px;height:80px;border-radius:50%;background:var(--color-primary);display:inline-flex;align-items:center;justify-content:center;font-size:2rem;font-weight:900;color:#fff">
                            <?= mb_strtoupper(mb_substr($user['name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="text-muted text-sm mt-2">Click below to change avatar</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="avatar">Avatar <span class="text-muted">(optional)</span></label>
                    <input type="file" id="avatar" name="avatar" class="form-control"
                           accept=".jpg,.jpeg,.png,.gif,.webp">
                    <div class="form-hint">JPEG, PNG, GIF or WebP. Max 2 MB.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="name">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control"
                           value="<?= e($user['name']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email <span class="text-muted">(read-only)</span></label>
                    <input type="email" id="email" class="form-control"
                           value="<?= e($user['email']) ?>" disabled>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone <span class="text-muted">(optional)</span></label>
                    <input type="tel" id="phone" name="phone" class="form-control"
                           value="<?= e($user['phone'] ?? '') ?>"
                           placeholder="+60 12 345 6789">
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>

        <div style="display:flex;flex-direction:column;gap:24px">

            <!-- ── Change password ──────────────────────────────────── -->
            <div class="card">
                <div class="card-header"><span class="card-title">Change Password</span></div>

                <?php if (!empty($errors['current']) || !empty($errors['new_pass']) || !empty($errors['confirm'])): ?>
                    <div class="alert alert--error">
                        <?= e($errors['current'] ?? $errors['new_pass'] ?? $errors['confirm'] ?? '') ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">

                    <div class="form-group">
                        <label class="form-label" for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password"
                               class="form-control" required autocomplete="current-password">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password"
                               class="form-control" required autocomplete="new-password"
                               placeholder="Min 8 chars with letters & numbers">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password"
                               class="form-control" required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-primary">Update Password</button>
                </form>
            </div>

            <!-- ── Account info ─────────────────────────────────────── -->
            <div class="card">
                <div class="card-header"><span class="card-title">Account Info</span></div>
                <table style="font-size:.9rem;width:100%">
                    <tr>
                        <td style="padding:7px 0;color:var(--color-muted);width:40%">Referral Code</td>
                        <td>
                            <code style="color:var(--color-primary);font-weight:700">
                                <?= e($user['referral_code']) ?>
                            </code>
                            <button onclick="navigator.clipboard.writeText('<?= e($user['referral_code']) ?>').then(()=>alert('Copied!'))"
                                    class="btn btn-ghost btn-sm" style="margin-left:8px">Copy</button>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:7px 0;color:var(--color-muted)">Wallet Balance</td>
                        <td class="fw-bold text-accent"><?= e(format_credits($balance)) ?> credits</td>
                    </tr>
                    <tr>
                        <td style="padding:7px 0;color:var(--color-muted)">Member Since</td>
                        <td class="text-muted"><?= e(format_datetime($user['created_at'])) ?></td>
                    </tr>
                    <tr>
                        <td style="padding:7px 0;color:var(--color-muted)">Last Login</td>
                        <td class="text-muted"><?= e(format_datetime($user['last_login_at'])) ?></td>
                    </tr>
                </table>
                <div class="mt-3" style="display:flex;gap:10px;flex-wrap:wrap">
                    <a href="<?= BASE_URL ?>/client/referral.php"    class="btn btn-ghost btn-sm">My Referrals</a>
                    <a href="<?= BASE_URL ?>/client/wallet.php"      class="btn btn-ghost btn-sm">Wallet History</a>
                    <a href="<?= BASE_URL ?>/public/logout.php"
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('Sign out?')">Sign Out</a>
                </div>
            </div>

        </div>
    </div>
</div>
</body>
</html>
