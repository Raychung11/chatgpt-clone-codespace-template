<?php
/**
 * Customer-side Debug Page
 * Visit: /app/debug  (or directly /public/debug.php)
 * DELETE after fixing!
 */
if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/inc/bootstrap.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Debug</title>
<style>
*{box-sizing:border-box;} body{font-family:monospace;background:#0f0f1a;color:#e0e0e0;padding:1rem;margin:0;font-size:.82rem;}
h2{color:#e94560;margin:.5rem 0;font-size:1rem;}
.card{background:#1a1a2e;border:1px solid #333;border-radius:8px;padding:.75rem;margin-bottom:.75rem;}
.ok{color:#75e040;} .err{color:#f87;} .info{color:#7af;}
pre{white-space:pre-wrap;word-break:break-all;background:#111;padding:.5rem;border-radius:4px;margin:.3rem 0;max-height:180px;overflow:auto;}
button{background:#e94560;color:#fff;border:none;padding:.4rem 1rem;border-radius:6px;cursor:pointer;font-size:.82rem;margin:.2rem;}
hr{border:none;border-top:1px solid #333;margin:.75rem 0;}
</style>
</head>
<body>
<h2>🔧 Customer App Debug</h2>
<p style="color:#aaa;font-size:.75rem;">DELETE this file after debugging!</p>

<!-- PHP checks -->
<div class="card">
<h2>PHP / Server</h2>
<?php
$items = [
    'PHP Version'      => [PHP_VERSION, version_compare(PHP_VERSION,'7.4','>=')],
    'BASE_PATH'        => [BASE_PATH, is_dir(BASE_PATH)],
    'public/pages dir' => [BASE_PATH.'/public/pages', is_dir(BASE_PATH.'/public/pages')],
    'inc/Auth.php'     => [file_exists(BASE_PATH.'/inc/Auth.php') ? '✅' : '❌ MISSING', file_exists(BASE_PATH.'/inc/Auth.php')],
    'inc/Database.php' => [file_exists(BASE_PATH.'/inc/Database.php') ? '✅' : '❌ MISSING', file_exists(BASE_PATH.'/inc/Database.php')],
    '.env file'        => [file_exists(BASE_PATH.'/.env') ? '✅' : '❌ MISSING', file_exists(BASE_PATH.'/.env')],
];
foreach ($items as $k => [$v, $ok]) {
    echo "<div class='".($ok?'ok':'err')."'><b>$k:</b> ".htmlspecialchars($v)."</div>";
}
?>
</div>

<!-- DB check -->
<div class="card">
<h2>Database</h2>
<?php
try {
    $pdo = Database::getInstance();
    echo "<div class='ok'>✅ Connected</div>";
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "<div class='info'>Tables (".count($tables)."): ".implode(', ',$tables)."</div>";
    $missing = array_diff(['users','api_tokens','customer_profiles','rewards','outlets','menu_categories','menu_items','settings'], $tables);
    if ($missing) echo "<div class='err'>❌ Missing tables: ".implode(', ',$missing)."</div>";
    else echo "<div class='ok'>✅ All required tables exist</div>";

    // Row counts
    foreach (['rewards','outlets','menu_categories','menu_items','settings','api_tokens'] as $t) {
        if (in_array($t,$tables)) {
            $n = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            echo "<div class='info'>$t: $n rows</div>";
        }
    }
} catch (Exception $e) {
    echo "<div class='err'>❌ ".$e->getMessage()."</div>";
}
?>
</div>

<!-- JS / API checks -->
<div class="card">
<h2>Token &amp; API Tests</h2>
<div id="js-results"><div class="info">Running JS tests…</div></div>
</div>

<div class="card">
<h2>Quick Fix</h2>
<button onclick="localStorage.clear();alert('Cleared! Now go to /app/login');window.location.href='/app/login'">
  Clear localStorage &amp; go to Login
</button>
<button onclick="showToken()">Show stored token</button>
<div id="token-display" style="margin-top:.4rem;"></div>
</div>

<script>
const token = localStorage.getItem('fnb_token');

function showToken() {
    document.getElementById('token-display').innerHTML =
        token ? '<span class="info">Token: ' + token + '</span>'
               : '<span class="err">No token in localStorage</span>';
}

async function testApi(label, url, headers) {
    try {
        const r = await fetch(url, { headers });
        const text = await r.text();
        let parsed = null;
        try { parsed = JSON.parse(text); } catch(e) {}
        return { label, status: r.status, ok: r.ok && parsed?.status === 'success', text: text.substring(0, 400), parsed };
    } catch(e) {
        return { label, status: 'NETWORK ERROR', ok: false, text: e.message };
    }
}

async function runTests() {
    const out = document.getElementById('js-results');
    out.innerHTML = '';

    const log = (html) => { out.innerHTML += html; };

    // Token check
    if (!token) {
        log('<div class="err">❌ No fnb_token in localStorage → <a href="/app/login" style="color:#e94560">Login first</a></div>');
        return;
    }
    log('<div class="ok">✅ Token found: ' + token.substring(0,16) + '…</div>');

    const authHeaders = {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + token,
        'X-Auth-Token': token,
    };

    const tests = [
        ['customers/dashboard', '/api/customers/dashboard'],
        ['loyalty/balance',     '/api/loyalty/balance'],
        ['rewards/list',        '/api/rewards/list'],
        ['menu/categories-with-items', '/api/menu/categories-with-items'],
    ];

    for (const [label, url] of tests) {
        log('<div style="margin-top:.5rem;"><b class="info">' + label + '</b></div>');
        const res = await testApi(label, url, authHeaders);
        const cls = res.ok ? 'ok' : 'err';
        log('<div class="' + cls + '">HTTP ' + res.status + ' – ' + (res.ok ? '✅ OK' : '❌ FAILED') + '</div>');
        if (!res.ok) {
            log('<pre>' + (res.text || '') + '</pre>');
        } else {
            log('<div class="info" style="font-size:.75rem;">Response preview: ' +
                JSON.stringify(res.parsed?.data).substring(0,120) + '…</div>');
        }
    }
}

runTests();
</script>
</body>
</html>
