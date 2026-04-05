<?php
/**
 * Auth Debug – DELETE after testing!
 * Visit: /auth-debug.php
 * Then visit: /auth-debug.php?token=YOUR_TOKEN_HERE
 */
header('Content-Type: text/html; charset=utf-8');

$base = dirname(__FILE__);

// Try to bootstrap the app
$bootstrapped = false;
$bootstrapError = '';
try {
    if (!defined('BASE_PATH')) define('BASE_PATH', $base);
    if (file_exists($base . '/inc/bootstrap.php')) {
        require_once $base . '/inc/bootstrap.php';
        $bootstrapped = true;
    }
} catch (Throwable $e) {
    $bootstrapError = $e->getMessage();
}

// Collect all headers every possible way
$headerSources = [];

// 1. $_SERVER HTTP_ keys
$serverHeaders = [];
foreach ($_SERVER as $k => $v) {
    if (strpos($k, 'HTTP_') === 0) {
        $serverHeaders[$k] = $v;
    }
}
$headerSources['$_SERVER HTTP_* keys'] = $serverHeaders;

// 2. REDIRECT_ keys
$redirectHeaders = [];
foreach ($_SERVER as $k => $v) {
    if (strpos($k, 'REDIRECT_') === 0) {
        $redirectHeaders[$k] = $v;
    }
}
$headerSources['$_SERVER REDIRECT_* keys'] = $redirectHeaders;

// 3. apache_request_headers()
if (function_exists('apache_request_headers')) {
    $headerSources['apache_request_headers()'] = apache_request_headers();
} else {
    $headerSources['apache_request_headers()'] = ['N/A' => 'function not available'];
}

// 4. getallheaders()
if (function_exists('getallheaders')) {
    $headerSources['getallheaders()'] = getallheaders();
} else {
    $headerSources['getallheaders()'] = ['N/A' => 'function not available'];
}

// Find the Authorization header from any source
$foundAuth = null;
$foundIn   = null;
foreach ($headerSources as $src => $hdrs) {
    foreach ($hdrs as $k => $v) {
        if (strtolower($k) === 'authorization' || $k === 'HTTP_AUTHORIZATION' || $k === 'REDIRECT_HTTP_AUTHORIZATION') {
            $foundAuth = $v;
            $foundIn   = $src . ' → ' . $k;
            break 2;
        }
    }
}

// Token from URL for manual test
$manualToken = $_GET['token'] ?? null;

// Test DB + token lookup
$dbResult   = null;
$tokenResult = null;
if ($bootstrapped && ($foundAuth || $manualToken)) {
    $rawToken = $manualToken;
    if (!$rawToken && $foundAuth && preg_match('/^Bearer\s+(.+)$/i', $foundAuth, $m)) {
        $rawToken = trim($m[1]);
    }
    if ($rawToken) {
        try {
            $hash = hash('sha256', $rawToken);
            $row  = Database::fetchOne(
                'SELECT at.*, u.name, u.phone, u.status FROM api_tokens at JOIN users u ON u.id = at.user_id WHERE at.token_hash = ?',
                [$hash]
            );
            $tokenResult = $row ?: 'No matching token found in DB';
        } catch (Throwable $e) {
            $tokenResult = 'DB Error: ' . $e->getMessage();
        }
    }
}

// Key server vars
$serverVars = [
    'SERVER_SOFTWARE'   => $_SERVER['SERVER_SOFTWARE']  ?? '–',
    'GATEWAY_INTERFACE' => $_SERVER['GATEWAY_INTERFACE'] ?? '–',
    'REQUEST_URI'       => $_SERVER['REQUEST_URI']       ?? '–',
    'PHP_SAPI'          => PHP_SAPI,
    'PHP_VERSION'       => PHP_VERSION,
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Auth Debug</title>
<style>
*{box-sizing:border-box;}
body{font-family:monospace;background:#0f0f1a;color:#e0e0e0;padding:1.2rem;margin:0;font-size:.83rem;}
h1{color:#e94560;margin:0 0 1rem;font-size:1.1rem;}
h2{color:#a8b2d8;font-size:.9rem;margin:1.2rem 0 .4rem;border-bottom:1px solid #222;padding-bottom:.3rem;}
.ok{color:#75e0a0;} .err{color:#f99;} .warn{color:#ffc107;} .info{color:#7dd3fc;}
table{width:100%;border-collapse:collapse;margin-bottom:.5rem;}
td,th{padding:.3rem .6rem;border-bottom:1px solid #1a1a2e;vertical-align:top;word-break:break-all;}
th{color:#555;font-size:.75rem;text-transform:uppercase;text-align:left;}
.key{color:#a8b2d8;width:40%;} .val{color:#e0e0e0;}
.box{background:#1a1a2e;border:1px solid #333;border-radius:6px;padding:.75rem 1rem;margin-bottom:1rem;}
.section-empty{color:#444;font-style:italic;}
a{color:#e94560;}
input{background:#1a1a2e;border:1px solid #333;color:#e0e0e0;padding:.4rem .7rem;border-radius:4px;width:100%;margin:.3rem 0;}
button{background:#e94560;color:#fff;border:none;padding:.45rem 1rem;border-radius:4px;cursor:pointer;margin-top:.3rem;}
.highlight{background:rgba(233,69,96,.15);border:1px solid #e94560;border-radius:6px;padding:.6rem .9rem;margin-bottom:1rem;}
</style>
</head>
<body>
<h1>🔐 Authorization Header Debug</h1>

<!-- Bootstrap status -->
<div class="box <?= $bootstrapped ? 'ok' : 'err' ?>">
    App bootstrap: <?= $bootstrapped ? '✅ OK' : '❌ Failed – ' . htmlspecialchars($bootstrapError) ?>
</div>

<!-- Auth header detection result -->
<div class="highlight">
    <?php if ($foundAuth): ?>
    <div class="ok">✅ Authorization header FOUND</div>
    <div class="info">Source: <?= htmlspecialchars($foundIn) ?></div>
    <div>Value: <code><?= htmlspecialchars(substr($foundAuth, 0, 40)) ?>…</code></div>
    <?php else: ?>
    <div class="err">❌ Authorization header NOT FOUND in any source</div>
    <div class="warn">This is why API calls fail – fix needed in .htaccess</div>
    <?php endif; ?>
</div>

<!-- Manual token test -->
<h2>🧪 Test Token Manually</h2>
<div class="box">
    <div>Paste your token from browser localStorage (<code>fnb_token</code>) and click Test:</div>
    <form method="GET">
        <input type="text" name="token" value="<?= htmlspecialchars($manualToken ?? '') ?>"
               placeholder="Paste fnb_token value here…">
        <button type="submit">Test Token →</button>
    </form>
    <?php if ($manualToken): ?>
    <div style="margin-top:.7rem;">
        <?php if (is_array($tokenResult)): ?>
        <div class="ok">✅ Token valid</div>
        <table>
            <?php foreach ($tokenResult as $k => $v): ?>
            <tr><td class="key"><?= htmlspecialchars($k) ?></td><td class="val"><?= htmlspecialchars((string)$v) ?></td></tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?>
        <div class="err">❌ <?= htmlspecialchars((string)$tokenResult) ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Server info -->
<h2>🖥 Server Info</h2>
<table>
<?php foreach ($serverVars as $k => $v): ?>
<tr><td class="key"><?= $k ?></td><td class="val"><?= htmlspecialchars($v) ?></td></tr>
<?php endforeach; ?>
</table>

<!-- All header sources -->
<?php foreach ($headerSources as $source => $headers): ?>
<h2>📦 <?= htmlspecialchars($source) ?></h2>
<?php if (empty($headers)): ?>
<div class="section-empty">(empty)</div>
<?php else: ?>
<table>
<tr><th>Key</th><th>Value</th></tr>
<?php foreach ($headers as $k => $v): ?>
<tr>
    <td class="key <?= stripos($k,'auth') !== false ? 'ok' : '' ?>"><?= htmlspecialchars($k) ?></td>
    <td class="val"><?= htmlspecialchars(strlen($v) > 120 ? substr($v,0,120).'…' : $v) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<?php endforeach; ?>

<!-- Instructions -->
<h2>📋 How to get your token</h2>
<div class="box">
    1. Open browser DevTools (F12) → Console<br>
    2. Type: <code>localStorage.getItem('fnb_token')</code><br>
    3. Copy the value and paste it above<br><br>
    <strong>If Authorization header is not found:</strong><br>
    The .htaccess RewriteRule to pass the header may not be working.<br>
    Try adding to the TOP of your .htaccess (before any RewriteRule):<br>
    <code>SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1</code>
</div>

<div style="color:#444;margin-top:1.5rem;">⚠️ Delete this file after debugging: <code>auth-debug.php</code></div>

<script>
// Auto-fill token from localStorage
const t = localStorage.getItem('fnb_token');
if (t) {
    const inp = document.querySelector('input[name=token]');
    if (inp && !inp.value) {
        inp.value = t;
        inp.style.border = '1px solid #198754';
    }
    // Also make a live test call to show the actual API response
    fetch('/api/customers/dashboard', {
        headers: { 'Authorization': 'Bearer ' + t, 'Content-Type': 'application/json' }
    }).then(r => r.json()).then(d => {
        const box = document.createElement('div');
        box.className = 'box';
        box.style.marginTop = '1rem';
        box.innerHTML = '<strong>Live API test result (/api/customers/dashboard):</strong><br><pre style="color:' +
            (d.status==='success'?'#75e0a0':'#f99') + ';white-space:pre-wrap;word-break:break-all;">' +
            JSON.stringify(d, null, 2) + '</pre>';
        document.body.appendChild(box);
    }).catch(e => {
        const box = document.createElement('div');
        box.className = 'box err';
        box.textContent = 'Fetch error: ' + e.message;
        document.body.appendChild(box);
    });
}
</script>
</body>
</html>
