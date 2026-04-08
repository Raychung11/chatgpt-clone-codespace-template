<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_admin();

$group  = clean($_GET['group'] ?? 'general');
$groups = ['general', 'listings', 'uploads', 'email', 'integrations', 'commercial'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();
    foreach ($_POST['settings'] ?? [] as $key => $value) {
        $key   = preg_replace('/[^a-z0-9_]/', '', $key);
        $value = trim($value);
        Database::query(
            'UPDATE settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?',
            [$value, auth_user_id(), $key]
        );
    }
    activity_log(auth_user_id(), 'settings_updated', null, null, "Settings group '$group' updated");
    flash_set(FLASH_SUCCESS, 'Settings saved.');
    redirect('admin/settings.php?group=' . $group);
}

$settings = Database::fetchAll('SELECT * FROM settings WHERE group_name = ? ORDER BY setting_key', [$group]);

$page_title = 'Site Settings';
$body_class = 'admin-layout';
include INC_PATH . '/header.php';
?>
<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="admin-main">
    <?= render_flash() ?>
    <h4 class="fw-700 text-navy mb-4">Settings</h4>

    <div class="row g-4">
        <!-- Groups sidebar -->
        <div class="col-lg-2">
            <nav class="nav flex-column">
                <?php foreach ($groups as $g): ?>
                    <a href="?group=<?= $g ?>" class="nav-link py-2 px-3 rounded-2 mb-1 <?= $group === $g ? 'bg-pale-gold text-gold fw-600' : 'text-muted' ?>">
                        <?= ucfirst($g) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
        <div class="col-lg-10">
            <div class="pg-card p-4">
                <h6 class="fw-600 text-uppercase text-muted mb-4" style="font-size:.75rem;letter-spacing:.08em"><?= ucfirst($group) ?> Settings</h6>
                <?php if ($settings): ?>
                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <?php foreach ($settings as $s): ?>
                        <div class="col-md-6">
                            <label class="form-label">
                                <?= h($s['label'] ?? $s['setting_key']) ?>
                                <?php if ($s['is_public']): ?><span class="badge bg-light text-muted border ms-1" style="font-size:.65rem">Public</span><?php endif; ?>
                            </label>
                            <?php if ($s['setting_type'] === 'bool'): ?>
                                <select name="settings[<?= h($s['setting_key']) ?>]" class="form-select">
                                    <option value="0" <?= !$s['setting_value'] ? 'selected' : '' ?>>Disabled</option>
                                    <option value="1" <?= $s['setting_value'] ? 'selected' : '' ?>>Enabled</option>
                                </select>
                            <?php elseif ($s['setting_type'] === 'encrypted'): ?>
                                <input type="password" name="settings[<?= h($s['setting_key']) ?>]"
                                       class="form-control" value="<?= h($s['setting_value'] ?? '') ?>"
                                       autocomplete="off">
                            <?php else: ?>
                                <input type="<?= $s['setting_type'] === 'int' ? 'number' : 'text' ?>"
                                       name="settings[<?= h($s['setting_key']) ?>]"
                                       class="form-control" value="<?= h($s['setting_value'] ?? '') ?>">
                            <?php endif; ?>
                            <?php if ($s['description']): ?>
                                <div class="form-text text-muted small"><?= h($s['description']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <div class="col-12">
                            <button type="submit" class="btn btn-gold">Save Settings</button>
                        </div>
                    </div>
                </form>
                <?php else: ?>
                    <p class="text-muted">No settings in this group.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>
<?php include INC_PATH . '/footer.php'; ?>
