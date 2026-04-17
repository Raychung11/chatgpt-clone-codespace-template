<?php
declare(strict_types=1);

/**
 * admin/debug.php
 * System diagnostic page — requires admin login.
 * DELETE or password-protect this file in production when no longer needed.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/layout.php';

boot_session();
$admin = require_admin('/admin/login.php');

// ── Run checks ────────────────────────────────────────────────────────────────

$checks = [];

function chk(string $label, bool $pass, string $detail = '', string $fix = ''): array {
    return compact('label','pass','detail','fix');
}

// 1. PHP version
$phpVer  = PHP_VERSION;
$phpOk   = version_compare($phpVer, '8.0.0', '>=');
$checks[] = chk('PHP Version', $phpOk, $phpVer, $phpOk ? '' : 'Upgrade to PHP 8.0+');

// 2. Required extensions
$required = ['pdo','pdo_mysql','curl','fileinfo','mbstring','json','openssl'];
foreach ($required as $ext) {
    $loaded   = extension_loaded($ext);
    $checks[] = chk("Extension: $ext", $loaded, $loaded ? 'Loaded' : 'Missing', $loaded ? '' : "Install/enable php-$ext");
}

// 3. Database connection
try {
    $pdo = db();
    $pdo->query('SELECT 1');
    $checks[] = chk('Database Connection', true, DB_HOST . ' / ' . DB_NAME);
} catch (Throwable $e) {
    $checks[] = chk('Database Connection', false, $e->getMessage(), 'Check DB_HOST, DB_NAME, DB_USER, DB_PASS in config.php');
}

// 4. Database tables exist
$tables = ['admins','users','wallets','wallet_transactions','credit_packages',
           'payment_orders','payment_receipts','generation_pricing_rules',
           'video_jobs','video_outputs','prompt_templates','referrals',
           'referral_rewards','settings','activity_logs','social_share_logs'];
try {
    $existing = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        $found    = in_array($t, $existing);
        $checks[] = chk("Table: $t", $found, $found ? 'Exists' : 'Missing', $found ? '' : 'Import sql/schema.sql');
    }
} catch (Throwable $e) {
    $checks[] = chk('Table check', false, $e->getMessage(), 'DB connection failed above');
}

// 5. Settings loaded
$settingKeys = ['site_name','byteplus_api_key','bank_name','referral_reward_credits','mail_driver'];
foreach ($settingKeys as $k) {
    $val      = setting($k, '__MISSING__');
    $found    = $val !== '__MISSING__';
    $display  = in_array($k, ['byteplus_api_key','smtp_pass']) ? ($val ? '(set)' : '(empty)') : (string)$val;
    $checks[] = chk("Setting: $k", $found, $display, $found ? '' : 'Run sql/schema.sql seed INSERT');
}

// 6. Writable directories
$dirs = [
    UPLOAD_PATH                => 'uploads/',
    UPLOAD_PATH . '/receipts'  => 'uploads/receipts/',
    UPLOAD_PATH . '/videos'    => 'uploads/videos/',
    UPLOAD_PATH . '/avatars'   => 'uploads/avatars/',
    BASE_PATH   . '/logs'      => 'logs/',
];
foreach ($dirs as $path => $label) {
    if (!is_dir($path)) @mkdir($path, 0755, true);
    $writable = is_dir($path) && is_writable($path);
    $checks[] = chk("Writable: $label", $writable,
        $writable ? 'OK' : 'Not writable',
        $writable ? '' : "chmod 755 $label  or  chown www-data $label");
}

// 7. BASE_URL reachable (simple check)
$baseUrl  = BASE_URL;
$urlOk    = !empty($baseUrl) && $baseUrl !== 'http://localhost';
$checks[] = chk('BASE_URL set', $urlOk, $baseUrl, $urlOk ? '' : 'Set APP_URL env var or edit config.php');

// 8. cURL can reach BytePlus API host
$apiUrl = setting('byteplus_api_url', BYTEPLUS_API_URL);
$apiKey = setting('byteplus_api_key', BYTEPLUS_API_KEY);
$apiHost = parse_url($apiUrl, PHP_URL_HOST);
try {
    $ch = curl_init('https://' . $apiHost);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>5, CURLOPT_NOBODY=>true]);
    curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr   = curl_error($ch);
    curl_close($ch);
    $reachable = $httpCode > 0 && empty($curlErr);
    $checks[] = chk('BytePlus API host reachable', $reachable,
        $reachable ? "HTTP $httpCode" : "cURL error: $curlErr",
        $reachable ? '' : 'Check server outbound firewall / curl');
} catch (Throwable $e) {
    $checks[] = chk('BytePlus API host reachable', false, $e->getMessage(), 'cURL extension missing?');
}
$checks[] = chk('BytePlus API Key set', !empty($apiKey), $apiKey ? '(set)' : '(empty)', $apiKey ? '' : 'Add key in Admin > Settings');

// 9. Session config
$sessionOk = session_status() === PHP_SESSION_ACTIVE;
$checks[] = chk('Session active', $sessionOk, $sessionOk ? 'Active' : 'Inactive');
$checks[] = chk('Session name', true, session_name());
$checks[] = chk('Session save path writable', is_writable(session_save_path() ?: sys_get_temp_dir()),
    session_save_path() ?: sys_get_temp_dir());

// 10. PHP config
$uploadMax   = ini_get('upload_max_filesize');
$postMax     = ini_get('post_max_size');
$maxExec     = ini_get('max_execution_time');
$displayErr  = ini_get('display_errors');
$checks[] = chk('upload_max_filesize', true, $uploadMax);
$checks[] = chk('post_max_size',       true, $postMax);
$checks[] = chk('max_execution_time',  (int)$maxExec >= 30, $maxExec . 's', (int)$maxExec < 30 ? 'Set to 60+ for video processing' : '');
$checks[] = chk('display_errors OFF (production)', $displayErr === '' || $displayErr === '0',
    $displayErr ? 'ON — exposes info' : 'OFF', $displayErr ? 'Set display_errors=Off in php.ini or .htaccess' : '');

// 11. .htaccess protection test
$protectedPaths = [
    BASE_PATH . '/config/.htaccess' => 'config/.htaccess',
    BASE_PATH . '/inc/.htaccess'    => 'inc/.htaccess',
    BASE_PATH . '/uploads/.htaccess'=> 'uploads/.htaccess',
    BASE_PATH . '/sql/.htaccess'    => 'sql/.htaccess',
];
foreach ($protectedPaths as $path => $label) {
    $exists   = file_exists($path);
    $checks[] = chk(".htaccess: $label", $exists, $exists ? 'Present' : 'Missing', $exists ? '' : 'Create the .htaccess protection file');
}

// ── Summary ───────────────────────────────────────────────────────────────────
$total  = count($checks);
$passed = count(array_filter($checks, fn($c) => $c['pass']));
$failed = $total - $passed;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Debug — <?= e(setting('site_name','Motions')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
    <style>
        .check-row { display:flex; align-items:flex-start; gap:12px; padding:10px 0; border-bottom:1px solid var(--color-border); }
        .check-icon { font-size:1.1rem; flex-shrink:0; margin-top:1px; }
        .check-label { font-weight:600; font-size:.9rem; }
        .check-detail { font-size:.82rem; color:var(--color-muted); margin-top:2px; font-family:monospace; word-break:break-all; }
        .check-fix { font-size:.8rem; color:var(--color-warn); margin-top:3px; }
        .section-title { font-size:.75rem; text-transform:uppercase; letter-spacing:.08em; color:var(--color-muted); font-weight:700; padding:14px 0 4px; }
        pre { background:var(--color-surface2); padding:14px; border-radius:var(--radius); font-size:.8rem; overflow-x:auto; border:1px solid var(--color-border); }
    </style>
</head>
<body>
<?php render_admin_navbar($admin); ?>
<div class="admin-wrap">
    <?php render_admin_sidebar(''); ?>
    <main class="admin-content">

        <div class="page-header">
            <div>
                <h1 class="page-title">System Debug</h1>
                <p class="page-sub">Post-deployment diagnostic — remove this file when done</p>
            </div>
            <div style="display:flex;gap:8px">
                <span class="badge badge-success" style="padding:8px 16px;font-size:.9rem"><?= $passed ?> passed</span>
                <?php if ($failed): ?>
                    <span class="badge badge-danger" style="padding:8px 16px;font-size:.9rem"><?= $failed ?> failed</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Overall status banner -->
        <?php if ($failed === 0): ?>
            <div class="alert alert--success">
                &#10003; All <?= $total ?> checks passed. Your platform is configured correctly.
            </div>
        <?php else: ?>
            <div class="alert alert--error">
                &#9888; <?= $failed ?> check<?= $failed > 1 ? 's' : '' ?> failed. Review the items below.
            </div>
        <?php endif; ?>

        <!-- Check groups -->
        <?php
        $groups = [
            'PHP Environment'       => array_slice($checks, 0, 1 + count($required)),
            'Database'              => array_filter($checks, fn($c) => str_starts_with($c['label'], 'Database') || str_starts_with($c['label'], 'Table:')),
            'Configuration'         => array_filter($checks, fn($c) => str_starts_with($c['label'], 'Setting:') || str_starts_with($c['label'], 'BASE_URL')),
            'File System'           => array_filter($checks, fn($c) => str_starts_with($c['label'], 'Writable:')),
            'API Connectivity'      => array_filter($checks, fn($c) => str_starts_with($c['label'], 'BytePlus')),
            'Session & PHP Config'  => array_filter($checks, fn($c) => str_starts_with($c['label'], 'Session') || in_array($c['label'], ['upload_max_filesize','post_max_size','max_execution_time','display_errors OFF (production)'])),
            'Security (.htaccess)'  => array_filter($checks, fn($c) => str_starts_with($c['label'], '.htaccess:')),
        ];
        foreach ($groups as $groupName => $groupChecks):
            if (empty($groupChecks)) continue;
        ?>
            <div class="card mb-4">
                <div class="card-header">
                    <span class="card-title"><?= e($groupName) ?></span>
                </div>
                <?php foreach ($groupChecks as $c): ?>
                    <div class="check-row">
                        <div class="check-icon"><?= $c['pass'] ? '&#10003;' : '&#10007;' ?></div>
                        <div style="flex:1">
                            <div class="check-label" style="color:<?= $c['pass'] ? 'var(--color-text)' : 'var(--color-danger)' ?>">
                                <?= e($c['label']) ?>
                            </div>
                            <?php if ($c['detail']): ?>
                                <div class="check-detail"><?= e($c['detail']) ?></div>
                            <?php endif; ?>
                            <?php if (!$c['pass'] && $c['fix']): ?>
                                <div class="check-fix">Fix: <?= e($c['fix']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <!-- Session dump -->
        <div class="card mb-4">
            <div class="card-header"><span class="card-title">Session Data</span></div>
            <pre><?= e(print_r($_SESSION, true)) ?></pre>
        </div>

        <!-- DB settings snapshot -->
        <div class="card mb-4">
            <div class="card-header"><span class="card-title">Settings Table Snapshot</span></div>
            <?php
            try {
                $rows = $pdo->query('SELECT `key`, `value`, `group` FROM `settings` ORDER BY `group`, `key`')->fetchAll();
                $sensitive = ['byteplus_api_key','smtp_pass','smtp_user','stripe_secret_key','stripe_webhook_secret','billplz_api_key','billplz_x_signature_key'];
            ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Group</th><th>Key</th><th>Value</th></tr></thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><span class="badge badge-muted"><?= e($row['group'] ?? '') ?></span></td>
                                <td style="font-family:monospace;font-size:.82rem"><?= e($row['key']) ?></td>
                                <td style="font-family:monospace;font-size:.82rem;color:var(--color-muted)">
                                    <?= in_array($row['key'], $sensitive) ? ($row['value'] ? '<span class="badge badge-success">Set</span>' : '<span class="badge badge-danger">Empty</span>') : e($row['value'] ?? '(null)') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php } catch (Throwable $e) { echo '<p class="text-danger">' . e($e->getMessage()) . '</p>'; } ?>
        </div>

        <!-- Loaded PHP extensions -->
        <div class="card mb-4">
            <div class="card-header"><span class="card-title">All Loaded PHP Extensions</span></div>
            <?php $exts = get_loaded_extensions(); sort($exts); ?>
            <div style="display:flex;flex-wrap:wrap;gap:6px;padding:4px 0">
                <?php foreach ($exts as $ext): ?>
                    <span class="badge <?= in_array($ext, $required) ? 'badge-success' : 'badge-muted' ?>">
                        <?= e($ext) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- PHP info summary -->
        <div class="card mb-4">
            <div class="card-header"><span class="card-title">PHP Info</span></div>
            <pre><?php
echo 'PHP Version   : ' . PHP_VERSION . "\n";
echo 'SAPI          : ' . PHP_SAPI . "\n";
echo 'OS            : ' . PHP_OS . "\n";
echo 'Server        : ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . "\n";
echo 'Document Root : ' . ($_SERVER['DOCUMENT_ROOT'] ?? 'unknown') . "\n";
echo 'BASE_PATH     : ' . BASE_PATH . "\n";
echo 'BASE_URL      : ' . BASE_URL . "\n";
echo 'UPLOAD_PATH   : ' . UPLOAD_PATH . "\n";
echo 'APP_ENV       : ' . APP_ENV . "\n";
echo 'APP_DEBUG     : ' . (APP_DEBUG ? 'true' : 'false') . "\n";
echo 'memory_limit  : ' . ini_get('memory_limit') . "\n";
echo 'max_exec_time : ' . ini_get('max_execution_time') . "s\n";
echo 'upload_max    : ' . ini_get('upload_max_filesize') . "\n";
echo 'post_max      : ' . ini_get('post_max_size') . "\n";
echo 'display_errors: ' . (ini_get('display_errors') ? 'On' : 'Off') . "\n";
echo 'error_log     : ' . (ini_get('error_log') ?: '(not set)') . "\n";
?></pre>
        </div>

        <div class="alert alert--warning">
            <strong>Security reminder:</strong> Delete or restrict access to this file
            (<code>admin/debug.php</code>) once your platform is stable.
        </div>

    </main>
</div>
</body>
</html>
