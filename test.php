<?php
/**
 * Hostinger Diagnostic – DELETE this file after testing!
 * Visit: https://yourdomain.com/test.php
 */
header('Content-Type: text/html; charset=utf-8');

$checks = [];

// PHP version
$checks['PHP Version'] = [PHP_VERSION, version_compare(PHP_VERSION, '7.4', '>=') ? 'ok' : 'warn'];

// Paths
$base = dirname(__FILE__);
$checks['Document Root']   = [$_SERVER['DOCUMENT_ROOT'], 'info'];
$checks['Script Path']     = [__FILE__, 'info'];
$checks['REQUEST_URI']     = [$_SERVER['REQUEST_URI'], 'info'];
$checks['SERVER_NAME']     = [$_SERVER['SERVER_NAME'], 'info'];

// Key files
foreach ([
    'inc/bootstrap.php',
    'inc/Database.php',
    'inc/Auth.php',
    'config/config.php',
    '.env',
    'public/index.php',
    'admin/index.php',
    'api/index.php',
    '.htaccess',
    'public/.htaccess',
    'admin/.htaccess',
    'api/.htaccess',
] as $f) {
    $exists = file_exists($base . '/' . $f);
    $checks["File: {$f}"] = [$exists ? '✅ Exists' : '❌ MISSING', $exists ? 'ok' : 'error'];
}

// .htaccess content check
$ht = $base . '/.htaccess';
if (file_exists($ht)) {
    $content = file_get_contents($ht);
    $checks['.htaccess: RewriteEngine'] = [
        strpos($content, 'RewriteEngine') !== false ? '✅ Present' : '❌ Not found',
        strpos($content, 'RewriteEngine') !== false ? 'ok' : 'error'
    ];
}

// mod_rewrite
$checks['mod_rewrite'] = [
    function_exists('apache_get_modules')
        ? (in_array('mod_rewrite', apache_get_modules()) ? '✅ Enabled' : '❌ Not in module list')
        : '⚠️ apache_get_modules() unavailable (normal on some hosts – may still work)',
    'info'
];

// PHP extensions
foreach (['pdo', 'pdo_mysql', 'json', 'mbstring', 'curl'] as $ext) {
    $loaded = extension_loaded($ext);
    $checks["PHP ext: {$ext}"] = [$loaded ? '✅ Loaded' : '❌ Missing', $loaded ? 'ok' : 'error'];
}

// .env + MySQL test
$envFile = $base . '/.env';
if (file_exists($envFile)) {
    $env = [];
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
    $host = $env['DB_HOST'] ?? 'localhost';
    $db   = $env['DB_NAME'] ?? '';
    $user = $env['DB_USER'] ?? '';
    $pass = $env['DB_PASS'] ?? '';
    $checks['.env DB_HOST'] = [$host, 'info'];
    $checks['.env DB_NAME'] = [$db,   'info'];
    $checks['.env DB_USER'] = [$user, 'info'];
    try {
        $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $checks['MySQL Connection']  = ['✅ Connected', 'ok'];
        $checks['Tables in DB']      = [count($tables) . ' table(s): ' . implode(', ', array_slice($tables,0,10)), count($tables) >= 18 ? 'ok' : 'warn'];
    } catch (Exception $e) {
        $checks['MySQL Connection'] = ['❌ ' . $e->getMessage(), 'error'];
    }
} else {
    $checks['.env file'] = ['❌ MISSING – create it from .env.example', 'error'];
}

$hasErrors = array_filter($checks, fn($c) => $c[1] === 'error');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>F&B Platform – Diagnostic</title>
<style>
*{box-sizing:border-box;}
body{font-family:monospace;background:#0f0f1a;color:#e0e0e0;padding:1.5rem;margin:0;}
h1{color:#e94560;margin:0 0 1.5rem;}
.summary{padding:.75rem 1rem;border-radius:8px;margin-bottom:1.2rem;font-size:.9rem;}
.summary.ok{background:rgba(25,135,84,.2);border:1px solid #198754;color:#75e0a0;}
.summary.fail{background:rgba(233,69,96,.15);border:1px solid #e94560;color:#f99;}
table{width:100%;border-collapse:collapse;font-size:.82rem;}
th{text-align:left;color:#666;padding:.35rem .7rem;border-bottom:1px solid #222;font-size:.75rem;text-transform:uppercase;}
td{padding:.38rem .7rem;border-bottom:1px solid #1a1a2e;vertical-align:top;word-break:break-all;}
tr.ok   td:first-child{border-left:3px solid #198754;}
tr.error td:first-child{border-left:3px solid #e94560;}
tr.warn  td:first-child{border-left:3px solid #ffc107;}
tr.info  td:first-child{border-left:3px solid #0dcaf0;}
.label{color:#a8b2d8;width:240px;min-width:180px;}
.tip{background:#1a1a2e;border:1px solid #333;border-radius:8px;padding:1rem 1.2rem;margin-top:1.5rem;font-size:.85rem;line-height:1.7;}
.tip h3{color:#e94560;margin:0 0 .5rem;font-size:.95rem;}
a{color:#e94560;}
</style>
</head>
<body>
<h1>🔧 F&B Platform – Diagnostic</h1>

<div class="summary <?= $hasErrors ? 'fail' : 'ok' ?>">
    <?= $hasErrors
        ? '❌ ' . count($hasErrors) . ' issue(s) found. See red rows below.'
        : '✅ All checks passed! The platform should be working.' ?>
</div>

<table>
<tr><th class="label">Check</th><th>Result</th></tr>
<?php foreach ($checks as $label => [$value, $type]): ?>
<tr class="<?= $type ?>">
    <td class="label"><?= htmlspecialchars($label) ?></td>
    <td><?= htmlspecialchars($value) ?></td>
</tr>
<?php endforeach; ?>
</table>

<div class="tip">
    <h3>⚠️ Delete this file after testing!</h3>
    <strong>Correct URLs:</strong><br>
    • Customer app → <a href="/app/">/app/</a><br>
    • Admin panel → <a href="/admin/">/admin/</a><br><br>
    <strong>If /app/ shows 404 (Page Not Found):</strong><br>
    mod_rewrite may be disabled. Log into Hostinger hPanel → Hosting → Manage →
    look for <em>Apache/.htaccess settings</em> or contact support:
    <em>"Please enable mod_rewrite and AllowOverride All for my domain."</em>
</div>
</body>
</html>
