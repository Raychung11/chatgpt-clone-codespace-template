<?php
declare(strict_types=1);

/**
 * admin/settings.php
 * System settings management.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/layout.php';

boot_session();
$admin = require_admin('/admin/login.php', 'super_admin');
$pdo   = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $updates = $_POST['settings'] ?? [];
    $stmt = $pdo->prepare('UPDATE `settings` SET `value` = ? WHERE `key` = ?');

    foreach ($updates as $key => $value) {
        // Sanitize key (allow only safe characters)
        if (!preg_match('/^[a-z0-9_]+$/', $key)) continue;
        $stmt->execute([trim($value), $key]);
    }

    log_activity('admin', (int)$admin['id'], 'update_settings', 'Updated system settings');
    flash_success('Settings saved.');
    redirect(BASE_URL . '/admin/settings.php');
}

$allSettings = $pdo->query(
    'SELECT * FROM `settings` ORDER BY `group`, `id`'
)->fetchAll();

// Group them
$grouped = [];
foreach ($allSettings as $s) {
    $grouped[$s['group'] ?? 'general'][] = $s;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — <?= e(setting('site_name','Motions')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
</head>
<body>
<?php render_admin_navbar($admin); ?>
<div class="admin-wrap">
    <?php render_admin_sidebar('settings'); ?>
    <main class="admin-content">
        <?= render_flash() ?>

        <div class="page-header">
            <h1 class="page-title">System Settings</h1>
        </div>

        <form method="POST">
            <?= csrf_field() ?>

            <?php foreach ($grouped as $group => $items): ?>
                <div class="card mb-4">
                    <div class="card-header"><span class="card-title"><?= e(ucfirst($group)) ?></span></div>
                    <?php foreach ($items as $s): ?>
                        <div class="form-group">
                            <label class="form-label" for="s_<?= e($s['key']) ?>">
                                <?= e($s['label'] ?? $s['key']) ?>
                            </label>
                            <?php if ($s['type'] === 'boolean'): ?>
                                <select name="settings[<?= e($s['key']) ?>]" class="form-control" id="s_<?= e($s['key']) ?>">
                                    <option value="1" <?= $s['value'] ? 'selected' : '' ?>>Enabled</option>
                                    <option value="0" <?= !$s['value'] ? 'selected' : '' ?>>Disabled</option>
                                </select>
                            <?php else: ?>
                                <input type="<?= $s['key'] === 'byteplus_api_key' ? 'password' : 'text' ?>"
                                       id="s_<?= e($s['key']) ?>"
                                       name="settings[<?= e($s['key']) ?>]"
                                       class="form-control"
                                       value="<?= e($s['value'] ?? '') ?>">
                            <?php endif; ?>
                            <div class="form-hint">Key: <code><?= e($s['key']) ?></code></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-primary btn-lg">Save All Settings</button>
        </form>
    </main>
</div>
</body>
</html>
