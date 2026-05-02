<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireAdmin();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = ['site_name','site_tagline','site_email','site_phone','site_address','currency','trial_days','maintenance_mode'];
    foreach ($keys as $k) {
        $v = htmlspecialchars(trim($_POST[$k] ?? ''));
        $exists = DB::fetch("SELECT `key` FROM settings WHERE `key`=?", [$k]);
        if ($exists) {
            DB::update('settings', ['value' => $v], '`key`=?', [$k]);
        } else {
            DB::insert('settings', ['key' => $k, 'value' => $v]);
        }
    }
    $msg = 'Settings saved.';
}

/* Load all settings */
$rows = DB::fetchAll("SELECT `key`, value FROM settings");
$s = [];
foreach ($rows as $r) $s[$r['key']] = $r['value'];

/* Defaults */
$s = array_merge([
    'site_name'        => SITE_NAME,
    'site_tagline'     => 'The Business Operating System for SMEs',
    'site_email'       => ADMIN_EMAIL,
    'site_phone'       => '',
    'site_address'     => '',
    'currency'         => APP_CURRENCY,
    'trial_days'       => TRIAL_DAYS,
    'maintenance_mode' => '0',
], $s);

$pageTitle = 'Settings';
require_once '../includes/admin-header.php';
?>
<div class="admin-content px-4 py-4">
<h4 class="fw-bold mb-4">Site Settings</h4>

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<form method="post">
<div class="row g-4">
    <div class="col-md-6">
        <div class="glass-card p-4">
            <h6 class="fw-semibold mb-3 text-primary"><i class="bi bi-globe me-2"></i>Site Information</h6>
            <div class="mb-3">
                <label class="form-label">Site Name</label>
                <input type="text" name="site_name" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($s['site_name']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Tagline</label>
                <input type="text" name="site_tagline" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($s['site_tagline']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Contact Email</label>
                <input type="email" name="site_email" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($s['site_email']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="site_phone" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($s['site_phone']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Address</label>
                <textarea name="site_address" class="form-control bg-dark text-white border-secondary" rows="2"><?= htmlspecialchars($s['site_address']) ?></textarea>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="glass-card p-4">
            <h6 class="fw-semibold mb-3 text-primary"><i class="bi bi-gear me-2"></i>Platform Settings</h6>
            <div class="mb-3">
                <label class="form-label">Currency Symbol</label>
                <input type="text" name="currency" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($s['currency']) ?>" maxlength="5">
            </div>
            <div class="mb-3">
                <label class="form-label">Free Trial Days</label>
                <input type="number" name="trial_days" class="form-control bg-dark text-white border-secondary" value="<?= (int)$s['trial_days'] ?>" min="0" max="90">
            </div>
            <div class="mb-3">
                <label class="form-label">Maintenance Mode</label>
                <select name="maintenance_mode" class="form-select bg-dark text-white border-secondary">
                    <option value="0" <?= $s['maintenance_mode']==='0'?'selected':'' ?>>Off (site live)</option>
                    <option value="1" <?= $s['maintenance_mode']==='1'?'selected':'' ?>>On (visitors see maintenance page)</option>
                </select>
            </div>
            <hr class="border-secondary">
            <h6 class="fw-semibold mb-2 text-muted small">Database Info</h6>
            <div class="small text-muted">
                <div>Host: <?= DB_HOST ?></div>
                <div>Database: <?= DB_NAME ?></div>
                <div>Charset: utf8mb4_unicode_ci</div>
            </div>
        </div>
    </div>
</div>

<div class="mt-3">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Settings</button>
</div>
</form>
</div>
<?php require_once '../includes/admin-footer.php'; ?>
