<?php
/**
 * Rewards Debug Page – DELETE after fixing
 * Visit: https://yourdomain.com/public/rewards-debug.php
 */
if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/inc/bootstrap.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Rewards Debug</title>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<style>
*{box-sizing:border-box;}
body{font-family:monospace;background:#0f0f1a;color:#e0e0e0;padding:1rem;margin:0;font-size:.82rem;}
h2{color:#e94560;margin:.5rem 0 .3rem;font-size:.95rem;}
.card{background:#1a1a2e;border:1px solid #333;border-radius:8px;padding:.75rem;margin-bottom:.75rem;}
.ok{color:#75e040;} .err{color:#f87;} .info{color:#7af;} .warn{color:#ffc107;}
pre{white-space:pre-wrap;word-break:break-all;background:#111;padding:.5rem;border-radius:4px;margin:.3rem 0;max-height:250px;overflow:auto;font-size:.78rem;}
button{background:#e94560;color:#fff;border:none;padding:.35rem .9rem;border-radius:6px;cursor:pointer;margin:.2rem 0;font-size:.82rem;}
</style>
</head>
<body>
<h2>🎁 Rewards Page Debug</h2>
<p style="color:#888;font-size:.72rem;">DELETE after fixing!</p>

<!-- Step 1: PHP DB check -->
<div class="card">
<h2>Step 1 – DB Rewards Data</h2>
<?php
try {
    $rewards = Database::fetchAll("SELECT id, name, points_required, status FROM rewards ORDER BY id");
    if (empty($rewards)) {
        echo "<div class='err'>❌ rewards table is EMPTY – import seed.sql</div>";
    } else {
        echo "<div class='ok'>✅ " . count($rewards) . " rewards in DB:</div>";
        foreach ($rewards as $r) {
            echo "<div class='info'>&nbsp;#{$r['id']} {$r['name']} – {$r['points_required']} pts – {$r['status']}</div>";
        }
    }
} catch (Exception $e) {
    echo "<div class='err'>❌ DB error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
</div>

<!-- Step 2: Raw API response -->
<div class="card">
<h2>Step 2 – Raw API Response</h2>
<div id="step2"><div class="info">Testing…</div></div>
</div>

<!-- Step 3: JS Rendering test -->
<div class="card">
<h2>Step 3 – JS Rendering Test</h2>
<div id="step3-status"><div class="info">Waiting…</div></div>
<div id="step3-grid" style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.5rem;"></div>
</div>

<!-- Step 4: Bootstrap check -->
<div class="card">
<h2>Step 4 – Bootstrap JS Check</h2>
<div id="step4"></div>
</div>

<!-- Step 5: Page file version check -->
<div class="card">
<h2>Step 5 – rewards.php on Server</h2>
<?php
$rewardsFile = BASE_PATH . '/public/pages/rewards.php';
if (!file_exists($rewardsFile)) {
    echo "<div class='err'>❌ File not found: $rewardsFile</div>";
} else {
    $content = file_get_contents($rewardsFile);
    $hasLazyInit = strpos($content, 'let redeemModal = null') !== false;
    $hasOldInit  = strpos($content, 'new bootstrap.Modal(document.getElementById(\'redeemModal\'))') !== false
                || strpos($content, 'new bootstrap.Modal(document.getElementById("redeemModal"))') !== false;
    $hasTryCatch = strpos($content, 'catch(e)') !== false || strpos($content, 'catch (e)') !== false;

    echo "<div class='".($hasLazyInit?'ok':'err')."'>".($hasLazyInit?'✅':'❌')." Lazy init (let redeemModal = null): ".($hasLazyInit?'YES':'NO – OLD FILE!')."</div>";
    echo "<div class='".(!$hasOldInit?'ok':'err')."'>".(!$hasOldInit?'✅':'❌')." No top-level bootstrap.Modal() crash: ".(!$hasOldInit?'OK':'PROBLEM – OLD FILE!')."</div>";
    echo "<div class='".($hasTryCatch?'ok':'warn')."'>".($hasTryCatch?'✅':'⚠️')." Has try/catch: ".($hasTryCatch?'YES':'NO')."</div>";

    if (!$hasLazyInit || $hasOldInit) {
        echo "<div class='err' style='margin-top:.4rem;'>⚠️ You are running an OLD version of rewards.php. Upload the new file from GitHub!</div>";
    }
}
?>
</div>

<script>
const token = localStorage.getItem('fnb_token');

// Step 4 – Bootstrap check
(function() {
    const el = document.getElementById('step4');
    try {
        const v = bootstrap.Tooltip.VERSION || bootstrap.Modal.VERSION || 'loaded';
        el.innerHTML = '<div class="ok">✅ Bootstrap JS loaded – version ' + v + '</div>';
    } catch(e) {
        el.innerHTML = '<div class="err">❌ bootstrap is not defined – ' + e.message + '</div>';
    }
})();

// Step 2 + 3 – API test + render
(async function() {
    const step2 = document.getElementById('step2');
    const step3status = document.getElementById('step3-status');
    const step3grid   = document.getElementById('step3-grid');

    if (!token) {
        step2.innerHTML = '<div class="err">❌ No token in localStorage. <a href="/app/login" style="color:#e94560">Login first</a></div>';
        step3status.innerHTML = '<div class="warn">⚠️ Skipped – no token</div>';
        return;
    }

    // Raw fetch
    let rawText = '', parsed = null, httpStatus = 0;
    try {
        const r = await fetch('/api/rewards/list', {
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token,
                'X-Auth-Token': token,
            }
        });
        httpStatus = r.status;
        rawText = await r.text();
        try { parsed = JSON.parse(rawText); } catch(e) {
            step2.innerHTML = '<div class="err">❌ HTTP ' + httpStatus + ' – Response is NOT valid JSON!</div><pre>' + rawText.substring(0,600) + '</pre>';
            step3status.innerHTML = '<div class="err">❌ Cannot render – invalid API response</div>';
            return;
        }
    } catch(e) {
        step2.innerHTML = '<div class="err">❌ Network error: ' + e.message + '</div>';
        return;
    }

    step2.innerHTML = '<div class="' + (parsed.status==='success'?'ok':'err') + '">HTTP ' + httpStatus + ' – status: ' + parsed.status + '</div>';
    step2.innerHTML += '<pre>' + JSON.stringify(parsed, null, 2).substring(0, 800) + '</pre>';

    if (parsed.status !== 'success') {
        step3status.innerHTML = '<div class="err">❌ API returned error – cannot render</div>';
        return;
    }

    // Step 3 – try rendering
    try {
        const rewards = parsed.data.rewards;
        step3status.innerHTML = '<div class="ok">✅ Rendering ' + rewards.length + ' rewards…</div>';

        if (!rewards.length) {
            step3grid.innerHTML = '<div class="warn">⚠️ rewards array is empty</div>';
            return;
        }

        step3grid.innerHTML = rewards.map(r => `
            <div style="background:#1a1a2e;border:1px solid #333;border-radius:8px;padding:.5rem;min-width:120px;max-width:140px;">
                <div style="font-size:.75rem;color:#7af;font-weight:600;">${r.name}</div>
                <div style="font-size:.7rem;color:#ffc107;">${r.points_required} pts</div>
                <div style="font-size:.65rem;color:#aaa;">${r.status}</div>
            </div>`).join('');

        step3status.innerHTML += '<div class="ok">✅ Rendered successfully</div>';
    } catch(e) {
        step3status.innerHTML = '<div class="err">❌ JS rendering error: ' + e.message + '</div>';
    }
})();
</script>
</body>
</html>
