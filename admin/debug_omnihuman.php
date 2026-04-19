<?php
declare(strict_types=1);

/**
 * admin/debug_omnihuman.php
 * One-page OmniHuman API diagnostic tool.
 * Shows exactly what BytePlus returns so you can diagnose 50215 errors.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/vision_auth.php';
require_once __DIR__ . '/../inc/omnihuman.php';
require_once __DIR__ . '/../inc/layout.php';

boot_session();
$admin = require_admin('/admin/login.php', 'super_admin');
$pdo   = db();

// ── Run test on POST ─────────────────────────────────────────────────────────
$testLog    = [];
$submitRaw  = null;
$queryRaw   = null;
$taskId     = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$ak, $sk, $apiBase, $reqKey] = [
        setting('vision_ai_ak',  VISION_AI_AK)  ?: VISION_AI_AK,
        setting('vision_ai_sk',  VISION_AI_SK)  ?: VISION_AI_SK,
        setting('vision_ai_url', VISION_AI_URL) ?: VISION_AI_URL,
        setting('omnihuman_req_key', OMNIHUMAN_REQ_KEY) ?: OMNIHUMAN_REQ_KEY,
    ];

    $testLog[] = 'AK: ' . ($ak ? substr($ak, 0, 6) . '***' : '(empty)');
    $testLog[] = 'SK: ' . ($sk ? substr($sk, 0, 4) . '***' : '(empty)');
    $testLog[] = 'Base URL: ' . $apiBase;
    $testLog[] = 'req_key: ' . $reqKey;

    // 1×1 white JPEG in base64 (minimal valid image)
    $tinyJpeg = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8U'
              . 'HRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgN'
              . 'DRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIy'
              . 'MjL/wAARCAABAAEDASIAAhEBAxEB/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAA'
              . 'AAAAAAAAAAAAAP/EABQBAQAAAAAAAAAAAAAAAAAAAAD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA'
              . '/9oADAMBAAIRAxEAPwCwABmX/9k=';

    // 3-second TTS text — short so we get a fast result
    $testText = 'Hello, this is a test.';

    $payload = [
        'req_key'      => $reqKey,
        'image_base64' => $tinyJpeg,
        'text'         => $testText,
        'duration'     => 5,
    ];

    $testLog[] = '';
    $testLog[] = '── CVSubmitTask ──';
    $submitUrl = _omnihuman_url($apiBase, 'generate');
    $testLog[] = 'URL: ' . $submitUrl;

    $submitResult = vision_post($submitUrl, $payload, $ak, $sk);
    $submitRaw    = json_encode($submitResult['raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($submitResult['ok']) {
        $testLog[] = 'HTTP: OK';
        $raw = $submitResult['raw'];
        $taskId = $raw['Result']['task_id']
               ?? $raw['data']['task_id']
               ?? $raw['task_id']
               ?? $raw['data']['id']
               ?? null;
        $testLog[] = 'task_id: ' . ($taskId ?? '(not found in response)');
    } else {
        $testLog[] = 'FAILED: ' . $submitResult['error'];
    }

    // 2. If we got a task_id, immediately query it
    if ($taskId) {
        $testLog[] = '';
        $testLog[] = '── CVGetResult (immediate) ──';
        $queryUrl  = _omnihuman_url($apiBase, 'query');
        $testLog[] = 'URL: ' . $queryUrl;

        $queryPayload  = ['req_key' => $reqKey, 'task_id' => (string)$taskId];
        $queryResult   = vision_post($queryUrl, $queryPayload, $ak, $sk);
        $queryRaw      = json_encode($queryResult['raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($queryResult['ok']) {
            $raw    = $queryResult['raw'];
            $data   = $raw['Result'] ?? $raw['data'] ?? $raw;
            $status = $data['status'] ?? $data['Status'] ?? '(no status field)';
            $testLog[] = 'HTTP: OK';
            $testLog[] = 'status: ' . $status;
        } else {
            $testLog[] = 'FAILED: ' . $queryResult['error'];
        }
    }
}

// ── Recent avatar jobs ───────────────────────────────────────────────────────
$recentJobs = [];
try {
    $recentJobs = $pdo->query(
        'SELECT id, user_id, status, api_task_id, error_message, api_response,
                credit_cost, created_at, started_at
         FROM avatar_jobs ORDER BY id DESC LIMIT 10'
    )->fetchAll();
} catch (\Throwable $e) { /* table may not exist */ }

$ak = setting('vision_ai_ak', VISION_AI_AK) ?: VISION_AI_AK;
$sk = setting('vision_ai_sk', VISION_AI_SK) ?: VISION_AI_SK;
$reqKey = setting('omnihuman_req_key', OMNIHUMAN_REQ_KEY) ?: OMNIHUMAN_REQ_KEY;
$apiUrl = setting('vision_ai_url', VISION_AI_URL) ?: VISION_AI_URL;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OmniHuman Diagnostics — Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
    <style>
        pre { background:#111; color:#0f0; padding:1rem; border-radius:6px; overflow-x:auto; font-size:12px; max-height:400px; white-space:pre-wrap; word-break:break-all; }
        .status-badge { padding:2px 8px; border-radius:4px; font-size:12px; font-weight:600; }
        .badge-refunded,.badge-failed { background:#fee; color:#c00; }
        .badge-processing,.badge-queued { background:#fef9e7; color:#856404; }
        .badge-completed { background:#e6fbe6; color:#15803d; }
    </style>
</head>
<body>
<?php render_admin_navbar($admin); ?>
<div class="admin-wrap">
    <?php render_admin_sidebar(''); ?>
    <main class="admin-content">
        <div class="page-header">
            <h1 class="page-title">OmniHuman API Diagnostics</h1>
            <p style="color:var(--color-muted)">Use this page to verify that your BytePlus Vision AI credentials are correct and OmniHuman is activated.</p>
        </div>

        <!-- Current config -->
        <div class="card mb-4">
            <div class="card-header"><span class="card-title">Current Configuration</span></div>
            <div class="card-body">
                <table class="table">
                    <tr><th>AK</th><td><?= $ak ? e(substr($ak,0,6)) . '***' : '<span style="color:red">Not set</span>' ?></td></tr>
                    <tr><th>SK</th><td><?= $sk ? e(substr($sk,0,4)) . '***' : '<span style="color:red">Not set</span>' ?></td></tr>
                    <tr><th>API URL</th><td><?= e($apiUrl) ?></td></tr>
                    <tr><th>req_key</th><td><?= e($reqKey) ?></td></tr>
                    <tr><th>Signing</th><td><?= _is_volcengine_host($apiUrl) ? 'Volcengine V4 HMAC-SHA256' : 'BytePlus HMAC256' ?></td></tr>
                </table>
                <?php if (!$ak || !$sk): ?>
                    <div class="alert alert--error mt-3">AK or SK is empty — go to <a href="<?= BASE_URL ?>/admin/settings.php">Admin → Settings</a> and fill in Vision AI Access Key and Secret Key.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Activation checklist -->
        <div class="card mb-4">
            <div class="card-header"><span class="card-title">Activation Checklist</span></div>
            <div class="card-body">
                <ol style="line-height:2">
                    <li>BytePlus Console → <strong>Vision AI</strong> → <strong>Model Plaza</strong> → search "OmniHuman" → <strong>Activate service</strong></li>
                    <li>The AK/SK must belong to the account that activated OmniHuman (same console account)</li>
                    <li>After activation, wait 1–2 minutes then run the test below</li>
                    <li>If CVSubmitTask succeeds but CVGetResult returns 50215, the service is NOT yet active for your account</li>
                </ol>
            </div>
        </div>

        <!-- Test button -->
        <div class="card mb-4">
            <div class="card-header"><span class="card-title">API Test — Send a Minimal Request</span></div>
            <div class="card-body">
                <p style="color:var(--color-muted);margin-bottom:1rem">Sends a 1×1 pixel image + short TTS text to CVSubmitTask, then immediately calls CVGetResult. Shows the raw JSON from both calls.</p>
                <form method="POST">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary">Run OmniHuman Test</button>
                </form>

                <?php if ($testLog): ?>
                    <div class="mt-4">
                        <h3 style="font-size:14px;margin-bottom:.5rem">Test Log</h3>
                        <pre><?= e(implode("\n", $testLog)) ?></pre>
                    </div>
                    <?php if ($submitRaw): ?>
                        <div class="mt-3">
                            <h3 style="font-size:14px;margin-bottom:.5rem">CVSubmitTask Raw Response</h3>
                            <pre><?= e($submitRaw) ?></pre>
                        </div>
                    <?php endif; ?>
                    <?php if ($queryRaw): ?>
                        <div class="mt-3">
                            <h3 style="font-size:14px;margin-bottom:.5rem">CVGetResult Raw Response (immediate)</h3>
                            <pre><?= e($queryRaw) ?></pre>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent avatar jobs -->
        <div class="card">
            <div class="card-header"><span class="card-title">Last 10 Avatar Jobs</span></div>
            <?php if (empty($recentJobs)): ?>
                <div class="card-body"><p style="color:var(--color-muted)">No avatar jobs found.</p></div>
            <?php else: ?>
                <table class="table">
                    <thead><tr>
                        <th>#</th><th>Status</th><th>Task ID</th><th>Error</th><th>API Response (first 200 chars)</th><th>Created</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($recentJobs as $j): ?>
                        <tr>
                            <td><?= (int)$j['id'] ?></td>
                            <td><span class="status-badge badge-<?= e($j['status']) ?>"><?= e($j['status']) ?></span></td>
                            <td style="font-family:monospace;font-size:11px"><?= e($j['api_task_id'] ?? '—') ?></td>
                            <td style="font-size:12px;max-width:300px"><?= e($j['error_message'] ?? '—') ?></td>
                            <td style="font-family:monospace;font-size:11px;max-width:300px;word-break:break-all">
                                <?= e(mb_substr($j['api_response'] ?? '—', 0, 200)) ?>
                            </td>
                            <td style="font-size:12px"><?= e($j['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
