<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/wallet.php';

boot_session();
$user = require_auth('/public/login.php');
$uid  = (int)$user['id'];
$pdo  = db();

// Grab the latest completed video for this user
$stmt = $pdo->prepare(
    'SELECT vj.id, vj.prompt, vo.cdn_url, vo.thumbnail
     FROM video_jobs vj
     JOIN video_outputs vo ON vo.job_id = vj.id
     WHERE vj.user_id = ? AND vj.status = "completed"
       AND vo.cdn_url IS NOT NULL AND vo.cdn_url != ""
     ORDER BY vj.created_at DESC LIMIT 1'
);
$stmt->execute([$uid]);
$video = $stmt->fetch();

// Test CORS headers on the video URL
$corsResult = null;
if ($video && $video['cdn_url']) {
    $ch = curl_init($video['cdn_url']);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY         => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Origin: ' . BASE_URL],
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $headers  = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);
    $corsResult = ['code' => $httpCode, 'headers' => $headers, 'error' => $curlErr];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Editor Debug</title>
    <style>
        body { font-family: monospace; background: #0d0d0f; color: #e0e0e0; padding: 24px; }
        h2 { color: #a78bfa; margin-top: 28px; }
        .ok  { color: #22c55e; }
        .err { color: #ef4444; }
        .warn { color: #f59e0b; }
        pre { background: #1a1a2e; padding: 12px; border-radius: 6px; overflow: auto; font-size:.82rem; white-space:pre-wrap; word-break:break-all; }
        .section { background: #161620; border: 1px solid #333; border-radius: 8px; padding: 16px; margin-bottom: 20px; }
        video { max-width: 100%; border: 2px solid #555; display: block; margin: 10px 0; background: #000; }
        #log  { background: #0a0a12; border: 1px solid #444; padding: 10px; min-height: 80px; border-radius: 6px; font-size:.8rem; }
        button { background: #6c47ff; border: none; color: #fff; padding: 8px 20px; border-radius: 6px; cursor: pointer; margin: 4px; font-size:.9rem; }
        button:hover { background: #7c57ff; }
    </style>
</head>
<body>
<h1>🔧 Editor Debug Page</h1>

<!-- ── 1. PHP / DB check ────────────────────────────────────────────── -->
<div class="section">
    <h2>1. Latest Completed Video (DB)</h2>
    <?php if (!$video): ?>
        <p class="err">❌ No completed video found for your account. Generate a video first.</p>
    <?php else: ?>
        <p class="ok">✅ Found video #<?= (int)$video['id'] ?></p>
        <p><strong>Prompt:</strong> <?= htmlspecialchars(mb_strimwidth($video['prompt'], 0, 120, '…')) ?></p>
        <p><strong>CDN URL:</strong><br><a href="<?= htmlspecialchars($video['cdn_url']) ?>" target="_blank" style="color:#93c5fd;word-break:break-all"><?= htmlspecialchars($video['cdn_url']) ?></a></p>
    <?php endif; ?>
</div>

<!-- ── 2. CORS header check ─────────────────────────────────────────── -->
<?php if ($corsResult): ?>
<div class="section">
    <h2>2. CORS / HTTP Header Check (server-side curl)</h2>
    <?php if ($corsResult['error']): ?>
        <p class="err">❌ cURL error: <?= htmlspecialchars($corsResult['error']) ?></p>
    <?php else: ?>
        <p>HTTP Status: <strong class="<?= $corsResult['code'] === 200 ? 'ok' : 'err' ?>"><?= (int)$corsResult['code'] ?></strong></p>
        <?php
        $hasACAO = stripos($corsResult['headers'], 'access-control-allow-origin') !== false;
        echo '<p>Access-Control-Allow-Origin header: <strong class="' . ($hasACAO ? 'ok' : 'warn') . '">' . ($hasACAO ? '✅ Present' : '⚠ Missing (canvas export will fail, but normal <video> playback is fine)') . '</strong></p>';
        ?>
        <details><summary style="cursor:pointer;color:#93c5fd">Show full response headers</summary>
        <pre><?= htmlspecialchars($corsResult['headers']) ?></pre>
        </details>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── 3. Plain <video> test ────────────────────────────────────────── -->
<?php if ($video): ?>
<div class="section">
    <h2>3. Plain &lt;video&gt; Tag — Does it play?</h2>
    <p class="warn">Press ▶ below. If this works, the problem is in the editor JS, not the URL.</p>
    <video id="testVideo" controls preload="metadata"
           src="<?= htmlspecialchars($video['cdn_url']) ?>"
           style="max-height:300px">
    </video>
    <div id="videoStatus" style="margin-top:8px;color:#f59e0b">Waiting for video events…</div>
</div>

<!-- ── 4. Simulate editor addToSequence ─────────────────────────────── -->
<div class="section">
    <h2>4. Simulate Editor Logic</h2>
    <p>Click the button to run the same code the editor uses when you click a video card.</p>
    <button onclick="simulateEditor()">▶ Simulate addToSequence()</button>
    <div style="margin-top:14px">
        <div id="simPlayer" style="position:relative;display:inline-flex;max-width:100%;line-height:0;display:none">
            <video id="simVideo" controls playsinline preload="metadata"
                   style="max-width:100%;max-height:280px;display:block;border-radius:6px;background:#000"></video>
            <div id="simOverlay" style="position:absolute;inset:0;pointer-events:none;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;padding-bottom:3%;border-radius:6px;overflow:hidden"></div>
        </div>
    </div>
    <div style="margin-top:10px;font-size:.85rem;color:#aaa" id="simStatus">Not started</div>
</div>

<!-- ── 5. Console log capture ───────────────────────────────────────── -->
<div class="section">
    <h2>5. JS Console Log</h2>
    <div id="log"></div>
    <button onclick="document.getElementById('log').innerHTML=''">Clear</button>
</div>

<script>
const VIDEO_URL = <?= json_encode($video['cdn_url']) ?>;

// ── Capture console ──────────────────────────────────────────────────
const logEl = document.getElementById('log');
const _origLog   = console.log.bind(console);
const _origWarn  = console.warn.bind(console);
const _origError = console.error.bind(console);
function appendLog(level, args) {
    const line = document.createElement('div');
    line.style.color = level === 'error' ? '#ef4444' : level === 'warn' ? '#f59e0b' : '#86efac';
    line.textContent = '[' + level.toUpperCase() + '] ' + Array.from(args).map(a => {
        try { return typeof a === 'object' ? JSON.stringify(a) : String(a); } catch(e){ return String(a); }
    }).join(' ');
    logEl.appendChild(line);
    logEl.scrollTop = logEl.scrollHeight;
}
console.log   = (...a) => { _origLog(...a);   appendLog('log',   a); };
console.warn  = (...a) => { _origWarn(...a);  appendLog('warn',  a); };
console.error = (...a) => { _origError(...a); appendLog('error', a); };
window.addEventListener('error', e => appendLog('error', [e.message + ' @ ' + e.filename + ':' + e.lineno]));

// ── Test video events ────────────────────────────────────────────────
const testVideo  = document.getElementById('testVideo');
const videoStatus = document.getElementById('videoStatus');
const events = ['loadstart','loadedmetadata','loadeddata','canplay','canplaythrough','play','playing','error','stalled','waiting','abort'];
events.forEach(ev => {
    testVideo.addEventListener(ev, () => {
        const msg = ev === 'error'
            ? '❌ ERROR code=' + testVideo.error?.code + ' msg=' + testVideo.error?.message
            : '✅ ' + ev;
        videoStatus.textContent = msg;
        videoStatus.style.color = ev === 'error' ? '#ef4444' : '#22c55e';
        console.log('testVideo event:', ev, ev === 'error' ? testVideo.error : '');
    });
});

// ── Simulate editor ──────────────────────────────────────────────────
function simulateEditor() {
    const player  = document.getElementById('simPlayer');
    const vid     = document.getElementById('simVideo');
    const overlay = document.getElementById('simOverlay');
    const status  = document.getElementById('simStatus');

    status.textContent = 'Loading ' + VIDEO_URL;
    console.log('simulateEditor: setting src =', VIDEO_URL);

    vid.src = VIDEO_URL;
    vid.load();
    player.style.display = 'inline-flex';

    vid.addEventListener('loadedmetadata', () => {
        console.log('sim loadedmetadata — duration:', vid.duration, 'readyState:', vid.readyState);
        vid.currentTime = 0.1;
        status.textContent = '✅ loadedmetadata — duration=' + vid.duration.toFixed(2) + 's';
        status.style.color = '#22c55e';
    }, { once: true });

    vid.addEventListener('error', () => {
        console.error('sim video error:', vid.error?.code, vid.error?.message);
        status.textContent = '❌ Video error code=' + vid.error?.code + ' message=' + vid.error?.message;
        status.style.color = '#ef4444';
    }, { once: true });

    // Add a test caption overlay at t=0
    const cap = document.createElement('div');
    cap.textContent = '🎬 Test Caption';
    cap.style.cssText = 'background:rgba(0,0,0,.6);color:#fff;padding:5px 16px;border-radius:5px;font-weight:700;font-size:18px';
    overlay.appendChild(cap);

    console.log('simulateEditor: load() called, waiting for events…');
}

// ── Browser / codec info ─────────────────────────────────────────────
console.log('Browser:', navigator.userAgent);
const v = document.createElement('video');
['video/mp4','video/webm','video/ogg'].forEach(t => {
    console.log('canPlayType(' + t + '):', v.canPlayType(t) || 'no');
});
console.log('MediaRecorder supported:', typeof MediaRecorder !== 'undefined');
console.log('captureStream supported:', typeof v.captureStream === 'function');
</script>
<?php endif; ?>

</body>
</html>
