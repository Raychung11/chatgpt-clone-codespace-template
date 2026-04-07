<?php
declare(strict_types=1);
// Force error display regardless of production settings
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * admin/debug_byteplus.php
 * Diagnose BytePlus ModelArk API connectivity and job status.
 * DELETE after use.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/byteplus.php';

boot_session();
// Simple admin check without layout dependency
if (empty($_SESSION['admin_id'])) {
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}
$admin = ['name' => 'Admin'];

$apiKey     = setting('byteplus_api_key',     BYTEPLUS_API_KEY)     ?: BYTEPLUS_API_KEY;
$endpointId = setting('byteplus_endpoint_id', BYTEPLUS_ENDPOINT_ID) ?: BYTEPLUS_ENDPOINT_ID;
$apiBase    = rtrim(setting('byteplus_api_url', BYTEPLUS_API_URL)   ?: BYTEPLUS_API_URL, '/');

// ── Check stuck jobs ──────────────────────────────────────────────────────────
$stuckJobs = db()->query(
    'SELECT id, api_task_id, status, prompt, credit_cost, created_at
     FROM video_jobs
     WHERE status IN ("queued","processing") AND api_task_id IS NOT NULL
     ORDER BY created_at DESC LIMIT 10'
)->fetchAll();

// ── Actions ───────────────────────────────────────────────────────────────────
$testResult    = null;
$queryResult   = null;
$submitResult  = null;
$saveMsg       = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Save API credentials to settings table
    if ($action === 'save_keys') {
        $newKey      = trim($_POST['new_api_key']      ?? '');
        $newEndpoint = trim($_POST['new_endpoint_id']  ?? '');
        $newUrl      = trim($_POST['new_api_url']      ?? '');
        $pdo         = db();
        $upsert      = $pdo->prepare(
            'INSERT INTO `settings` (`key`,`value`,`type`,`label`,`group`)
             VALUES (?,?,\'string\',?,\'api\')
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        if ($newKey)      { $upsert->execute(['byteplus_api_key',     $newKey,      'BytePlus API Key']); }
        if ($newEndpoint) { $upsert->execute(['byteplus_endpoint_id', $newEndpoint, 'BytePlus Endpoint ID']); }
        if ($newUrl)      { $upsert->execute(['byteplus_api_url',     $newUrl,      'BytePlus API URL']); }
        // Reload
        $apiKey     = $newKey      ?: $apiKey;
        $endpointId = $newEndpoint ?: $endpointId;
        $apiBase    = $newUrl      ? rtrim($newUrl, '/') : $apiBase;
        $saveMsg    = 'Settings saved. Keys are now active.';
    }

    // Test raw API connectivity
    if ($action === 'test_connection') {
        $ch = curl_init($apiBase . '/models');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey, 'Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        $testResult = ['code' => $code, 'error' => $err, 'body' => $body];
    }

    // Query a specific task ID
    if ($action === 'query_task') {
        $taskId = trim($_POST['task_id'] ?? '');
        if ($taskId) {
            $queryResult = ['task_id' => $taskId, 'result' => byteplus_query_task($taskId)];

            // Also do a raw curl to see unfiltered response
            $ch = curl_init($apiBase . '/contents/generations/tasks/' . urlencode($taskId));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey, 'Accept: application/json'],
            ]);
            $rawBody = curl_exec($ch);
            $rawCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $queryResult['raw_http'] = $rawCode;
            $queryResult['raw_body'] = $rawBody;
        }
    }

    // Submit a test task
    if ($action === 'submit_test') {
        $prompt = trim($_POST['test_prompt'] ?? 'A short 5-second test video of a blue sky.');
        $submitResult = byteplus_create_task($prompt, '720p', 5);
    }

    // Repair: re-query BytePlus live + extract video URL
    if ($action === 'repair_job') {
        $repairId  = (int)($_POST['repair_job_id'] ?? 0);
        $repairMsg = '';
        if ($repairId) {
            $row = db()->prepare('SELECT id, user_id, api_response, api_task_id FROM video_jobs WHERE id = ? LIMIT 1');
            $row->execute([$repairId]);
            $jobRow = $row->fetch();

            if ($jobRow) {
                $taskId   = $jobRow['api_task_id'];
                $videoUrl = null;
                $thumbUrl = null;
                $liveRaw  = null;

                // Step 1: Re-query BytePlus live
                if ($taskId && $apiKey) {
                    $ch = curl_init($apiBase . '/contents/generations/tasks/' . urlencode($taskId));
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT        => 15,
                        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey, 'Accept: application/json'],
                    ]);
                    $liveBody = curl_exec($ch);
                    $liveCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    $liveRaw  = json_decode($liveBody, true) ?? [];
                    $repairMsg .= "Live API HTTP $liveCode. ";

                    // Update stored api_response with fresh data
                    if ($liveCode === 200 && $liveRaw) {
                        db()->prepare('UPDATE video_jobs SET api_response=? WHERE id=?')
                            ->execute([json_encode($liveRaw), $repairId]);
                    }
                }

                // Step 2: Extract video URL — handles object OR array content shape
                $sources = array_filter([$liveRaw, json_decode($jobRow['api_response'] ?? '{}', true)]);
                foreach ($sources as $raw) {
                    if ($videoUrl) break;
                    $c = $raw['content'] ?? null;
                    if (is_array($c)) {
                        if (isset($c['video_url'])) {
                            // Object: {"video_url":"..."}
                            $videoUrl = $c['video_url'];
                            $thumbUrl = $c['thumbnail_url'] ?? $c['cover_image_url'] ?? null;
                        } else {
                            // Array: [{"type":"video","video_url":"..."}]
                            foreach ($c as $item) {
                                if (($item['type'] ?? '') === 'video') {
                                    $videoUrl = $videoUrl ?? $item['video_url'] ?? $item['url'] ?? null;
                                    $thumbUrl = $thumbUrl ?? $item['cover_image_url'] ?? $item['thumbnail_url'] ?? null;
                                }
                            }
                        }
                    }
                    $videoUrl = $videoUrl ?? $raw['video_url'] ?? $raw['data']['video_url'] ?? null;
                    $thumbUrl = $thumbUrl ?? $raw['thumbnail_url'] ?? null;
                    foreach ($raw['videos'] ?? [] as $v) {
                        $videoUrl = $videoUrl ?? $v['url'] ?? $v['video_url'] ?? null;
                    }
                }

                $repairMsg .= 'video_url: ' . ($videoUrl ?? 'NOT FOUND in response');

                if ($videoUrl) {
                    db()->prepare(
                        'INSERT INTO video_outputs (job_id, user_id, cdn_url, thumbnail)
                         VALUES (?,?,?,?)
                         ON DUPLICATE KEY UPDATE cdn_url=VALUES(cdn_url), thumbnail=VALUES(thumbnail)'
                    )->execute([$repairId, $jobRow['user_id'], $videoUrl, $thumbUrl]);
                    $repairMsg .= ' — ✓ video_outputs updated!';
                }

                $queryResult = [
                    'task_id'    => $taskId ?? "job #$repairId",
                    'result'     => ['ok' => true, 'status' => 'repaired', 'video_url' => $videoUrl],
                    'raw_http'   => $liveCode ?? 0,
                    'raw_body'   => json_encode($liveRaw ?? json_decode($jobRow['api_response'] ?? '{}', true),
                                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    'repair_msg' => $repairMsg,
                ];
            }
        }
    }
}

function jdump($v): string {
    return htmlspecialchars(json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
function badge(bool $ok): string {
    return $ok
        ? '<span style="color:#4ade80;font-weight:700">✓ PASS</span>'
        : '<span style="color:#f87171;font-weight:700">✗ FAIL</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>BytePlus Diagnostics</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
<style>
pre { background:var(--color-surface2); padding:14px; border-radius:8px; overflow-x:auto;
      font-size:.8rem; max-height:300px; overflow-y:auto; white-space:pre-wrap; word-break:break-all; }
.diag-row { display:flex; justify-content:space-between; align-items:center;
            padding:10px 0; border-bottom:1px solid var(--color-border); }
.diag-label { color:var(--color-muted); font-size:.9rem; }
.diag-val   { font-family:monospace; font-size:.85rem; max-width:60%; word-break:break-all; text-align:right; }
</style>
</head>
<body style="padding:24px;max-width:900px;margin:0 auto">
<main>

<div class="alert alert--warning" style="margin-bottom:20px">
    ⚠️ <strong>Diagnostic page — delete after use.</strong>
</div>

<h1 class="page-title">BytePlus API Diagnostics</h1>

<!-- ── Config snapshot ─────────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header"><span class="card-title">Configuration</span></div>
    <div class="diag-row">
        <span class="diag-label">API Base URL</span>
        <span class="diag-val"><?= e($apiBase) ?></span>
    </div>
    <div class="diag-row">
        <span class="diag-label">API Key set?</span>
        <span class="diag-val">
            <?= badge(!empty($apiKey)) ?>
            <?= $apiKey ? '…' . e(substr($apiKey, -6)) : '<span style="color:#f87171">EMPTY</span>' ?>
        </span>
    </div>
    <div class="diag-row">
        <span class="diag-label">Endpoint ID set?</span>
        <span class="diag-val">
            <?= badge(!empty($endpointId)) ?>
            <?= $endpointId ? e($endpointId) : '<span style="color:#f87171">EMPTY — set byteplus_endpoint_id in Settings or config.php</span>' ?>
        </span>
    </div>
    <div class="diag-row">
        <span class="diag-label">cURL available?</span>
        <span class="diag-val"><?= badge(function_exists('curl_init')) ?></span>
    </div>
    <div class="diag-row">
        <span class="diag-label">Host reachable?</span>
        <?php
        $host = parse_url($apiBase, PHP_URL_HOST);
        $reach = @fsockopen($host, 443, $errno, $errstr, 5);
        $reachOk = (bool)$reach;
        if ($reach) fclose($reach);
        ?>
        <span class="diag-val">
            <?= badge($reachOk) ?>
            <?= $reachOk ? e($host) . ':443 open' : 'Cannot reach ' . e($host) . ' — check server firewall/SSL' ?>
        </span>
    </div>
</div>

<!-- ── Save keys ──────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px;border:2px solid var(--color-primary)">
    <div class="card-header"><span class="card-title">0. Set API Credentials</span></div>
    <?php if ($saveMsg): ?>
        <div class="alert alert--success"><?= e($saveMsg) ?></div>
    <?php endif; ?>
    <form method="POST">
        <input type="hidden" name="action" value="save_keys">
        <div class="form-group">
            <label class="form-label">API Key <span class="text-muted text-sm">(from BytePlus Console → ModelArk → API keys)</span></label>
            <input type="text" name="new_api_key" class="form-control"
                   placeholder="Paste your API key here"
                   value="<?= e($apiKey) ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Endpoint ID <span class="text-muted text-sm">(from ModelArk → Online inference → your endpoint)</span></label>
            <input type="text" name="new_endpoint_id" class="form-control"
                   placeholder="ep-xxxxxxxx-xxxxxxxx"
                   value="<?= e($endpointId) ?>">
        </div>
        <div class="form-group">
            <label class="form-label">API Base URL</label>
            <input type="text" name="new_api_url" class="form-control"
                   value="<?= e($apiBase) ?>">
        </div>
        <button class="btn btn-primary">Save &amp; Activate</button>
    </form>
</div>

<!-- ── Test connection ─────────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header"><span class="card-title">1. Test API Connection</span></div>
    <form method="POST" style="padding:4px 0 12px">
        <input type="hidden" name="action" value="test_connection">
        <button class="btn btn-ghost btn-sm">Ping API</button>
    </form>
    <?php if ($testResult): ?>
        <p>HTTP <?= (int)$testResult['code'] ?> <?= badge($testResult['code'] < 400) ?></p>
        <?php if ($testResult['error']): ?>
            <p style="color:var(--color-danger)"><?= e($testResult['error']) ?></p>
        <?php endif; ?>
        <pre><?= jdump(json_decode($testResult['body'], true) ?? $testResult['body']) ?></pre>
    <?php endif; ?>
</div>

<!-- ── Query stuck jobs ────────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header"><span class="card-title">2. Query Processing Jobs</span></div>
    <?php if (empty($stuckJobs)): ?>
        <p class="text-muted text-sm" style="padding:8px 0">No queued/processing jobs found.</p>
    <?php else: ?>
        <?php foreach ($stuckJobs as $j): ?>
        <form method="POST" style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--color-border)">
            <input type="hidden" name="action"  value="query_task">
            <input type="hidden" name="task_id" value="<?= e($j['api_task_id']) ?>">
            <div style="flex:1">
                <span class="text-sm fw-bold">Job #<?= (int)$j['id'] ?></span>
                <span class="text-muted text-sm"> · <?= e($j['api_task_id']) ?></span>
                <span class="text-muted text-sm"> · <?= e(truncate($j['prompt'],60)) ?></span>
            </div>
            <button class="btn btn-primary btn-sm">Query BytePlus</button>
        </form>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Manual task ID query -->
    <form method="POST" style="display:flex;gap:10px;margin-top:14px">
        <input type="hidden" name="action" value="query_task">
        <input type="text" name="task_id" class="form-control btn-sm"
               placeholder="Paste task_id manually…" style="flex:1">
        <button class="btn btn-ghost btn-sm">Query</button>
    </form>

    <?php if ($queryResult): ?>
        <div style="margin-top:16px">
            <p><strong>Task:</strong> <?= e($queryResult['task_id']) ?></p>
            <p><strong>HTTP:</strong> <?= (int)$queryResult['raw_http'] ?> <?= badge($queryResult['raw_http'] === 200) ?></p>
            <p><strong>Normalised status:</strong>
                <span style="color:var(--color-accent)"><?= e($queryResult['result']['status'] ?? '—') ?></span>
                <?php if (!$queryResult['result']['ok']): ?>
                    &nbsp;<span style="color:var(--color-danger)"><?= e($queryResult['result']['error']) ?></span>
                <?php endif; ?>
            </p>
            <?php if ($queryResult['result']['video_url']): ?>
                <p><strong>Video URL:</strong> <a href="<?= e($queryResult['result']['video_url']) ?>" target="_blank">Open video ↗</a></p>
            <?php endif; ?>
            <p style="margin-top:8px"><strong>Raw API response:</strong></p>
            <pre><?= jdump(json_decode($queryResult['raw_body'], true) ?? $queryResult['raw_body']) ?></pre>
        </div>
    <?php endif; ?>
</div>

<!-- ── Repair completed jobs ───────────────────────────────────── -->
<?php
$completedJobs = db()->query(
    'SELECT vj.id, vj.prompt, vj.api_task_id, vo.cdn_url
     FROM video_jobs vj
     LEFT JOIN video_outputs vo ON vo.job_id = vj.id
     WHERE vj.status = "completed"
     ORDER BY vj.id DESC LIMIT 10'
)->fetchAll();
?>
<div class="card" style="margin-bottom:20px;border:2px solid var(--color-accent)">
    <div class="card-header"><span class="card-title">2b. Repair Completed Jobs (fix missing video URL)</span></div>
    <?php if (isset($queryResult['repair_msg'])): ?>
        <div class="alert alert--<?= str_contains($queryResult['repair_msg'], '✓') ? 'success' : 'warning' ?>" style="margin-bottom:10px">
            <?= e($queryResult['repair_msg']) ?>
        </div>
        <?php if (!empty($queryResult['raw_body'])): ?>
            <p style="font-weight:700;margin:10px 0 4px">Full raw BytePlus response (copy this):</p>
            <textarea style="width:100%;height:220px;font-family:monospace;font-size:.78rem;
                             background:var(--color-surface2);color:var(--color-text);
                             border:1px solid var(--color-border);border-radius:6px;padding:10px"
                      onclick="this.select()"><?= htmlspecialchars(
                          is_string($queryResult['raw_body'])
                              ? $queryResult['raw_body']
                              : json_encode($queryResult['raw_body'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)
                      ) ?></textarea>
        <?php endif; ?>
    <?php endif; ?>
    <?php foreach ($completedJobs as $cj): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--color-border)">
        <div style="flex:1">
            <span class="text-sm fw-bold">Job #<?= (int)$cj['id'] ?></span>
            <span class="text-muted text-sm"> · <?= e(truncate($cj['prompt'], 50)) ?></span><br>
            <span class="text-sm" style="color:<?= $cj['cdn_url'] ? '#4ade80' : '#f87171' ?>">
                <?= $cj['cdn_url'] ? '✓ Has video URL' : '✗ Missing video URL' ?>
            </span>
        </div>
        <form method="POST" style="display:inline">
            <input type="hidden" name="action"        value="repair_job">
            <input type="hidden" name="repair_job_id" value="<?= (int)$cj['id'] ?>">
            <button class="btn btn-accent btn-sm">Inspect &amp; Repair</button>
        </form>
    </div>
    <?php endforeach; ?>
    <?php if (empty($completedJobs)): ?>
        <p class="text-muted text-sm" style="padding:8px 0">No completed jobs found.</p>
    <?php endif; ?>
</div>

<!-- ── Submit test task ────────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header"><span class="card-title">3. Submit a Test Task</span></div>
    <form method="POST">
        <input type="hidden" name="action" value="submit_test">
        <div class="form-group">
            <textarea name="test_prompt" class="form-control" rows="2"
                >A 5-second video of a sunny beach with gentle waves.</textarea>
        </div>
        <button class="btn btn-accent btn-sm">Submit to BytePlus</button>
    </form>
    <?php if ($submitResult): ?>
        <div style="margin-top:12px">
            <p><?= badge($submitResult['ok']) ?>
               <?= $submitResult['ok']
                   ? 'Task created: <strong>' . e($submitResult['task_id']) . '</strong>'
                   : '<span style="color:var(--color-danger)">' . e($submitResult['error']) . '</span>' ?>
            </p>
            <pre><?= jdump($submitResult['raw']) ?></pre>
        </div>
    <?php endif; ?>
</div>

</main>
</body>
</html>
