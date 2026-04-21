<?php
declare(strict_types=1);

/**
 * client/clone-avatar.php
 * Clone Avatar — generate a talking-head video using a BytePlus-trained avatar + audio.
 *
 * Each user stores their own resource_id (returned after training in the Python scripts).
 * Falls back to the admin-configured clone_avatar_resource_id setting if user has none set.
 * Audio can be a public URL or an uploaded MP3/WAV file (auto-served from uploads/).
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/wallet.php';
require_once __DIR__ . '/../inc/clone_avatar_api.php';
require_once __DIR__ . '/../inc/layout.php';

boot_session();
$user = require_auth('/public/login.php');
$uid  = (int)$user['id'];
$pdo  = db();

$balance     = wallet_balance($uid);
$creditCost  = (float)(setting('clone_avatar_credit_cost', '10') ?: '10');

// Per-user resource_id; fall back to admin-configured default
$userRow    = $pdo->prepare('SELECT clone_avatar_resource_id FROM users WHERE id=?');
$userRow->execute([$uid]);
$userResourceId = trim((string)($userRow->fetchColumn() ?: ''));
$adminResourceId = trim(setting('clone_avatar_resource_id', '') ?: '');
$resourceId = $userResourceId ?: $adminResourceId;

// ── AJAX: save_resource_id ────────────────────────────────────────────────────
if (($_GET['_action'] ?? '') === 'save_resource_id') {
    csrf_verify();
    $rid = trim($_POST['resource_id'] ?? '');
    if ($rid && !preg_match('/^[\w\-]+$/', $rid)) {
        json_response(['ok' => false, 'error' => 'Invalid resource ID format.']);
    }
    $pdo->prepare('UPDATE users SET clone_avatar_resource_id=? WHERE id=?')->execute([$rid ?: null, $uid]);
    json_response(['ok' => true, 'resource_id' => $rid]);
}

// ── AJAX: cancel ──────────────────────────────────────────────────────────────
if (($_GET['_action'] ?? '') === 'cancel') {
    csrf_verify();
    $jobId = (int)($_GET['job_id'] ?? 0);
    $stmt  = $pdo->prepare(
        'SELECT id, status, api_task_id, credit_cost FROM clone_avatar_jobs WHERE id=? AND user_id=?'
    );
    $stmt->execute([$jobId, $uid]);
    $job = $stmt->fetch();
    if (!$job) { json_response(['ok' => false, 'error' => 'Not found'], 404); }

    if (!in_array($job['status'], ['queued', 'processing'])) {
        json_response(['ok' => false, 'error' => 'Job is not cancellable.']);
    }

    if ($job['api_task_id']) {
        $ak  = (setting('vision_ai_ak', VISION_AI_AK) ?: VISION_AI_AK) ?: '';
        $sk  = (setting('vision_ai_sk', VISION_AI_SK) ?: VISION_AI_SK) ?: '';
        $base = rtrim(setting('vision_ai_url', VISION_AI_URL) ?: VISION_AI_URL, '/');
        $host = parse_url($base, PHP_URL_HOST) ?? '';
        $ver  = str_contains($host, 'byteplusapi.com') ? '2024-06-06' : '2022-08-31';
        vision_post($base . '/?Action=CVCancelTask&Version=' . $ver,
            ['req_key' => CLONE_AVATAR_REQ_KEY, 'task_id' => $job['api_task_id']],
            $ak, $sk);
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE clone_avatar_jobs SET status="refunded", error_message="Cancelled by user", refunded_at=NOW() WHERE id=?'
        )->execute([$jobId]);
        wallet_refund($uid, (float)$job['credit_cost'], 'clone_avatar_job', $jobId,
            'Refund: clone avatar job #' . $jobId . ' cancelled');
        $pdo->commit();
        json_response(['ok' => true]);
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_response(['ok' => false, 'error' => 'DB error'], 500);
    }
}

// ── AJAX: delete ──────────────────────────────────────────────────────────────
if (($_GET['_action'] ?? '') === 'delete') {
    csrf_verify();
    $jobId = (int)($_GET['job_id'] ?? 0);
    $stmt  = $pdo->prepare('SELECT id, status, audio_path FROM clone_avatar_jobs WHERE id=? AND user_id=?');
    $stmt->execute([$jobId, $uid]);
    $job = $stmt->fetch();
    if (!$job) { json_response(['ok' => false, 'error' => 'Not found'], 404); }

    if (in_array($job['status'], ['queued', 'processing'])) {
        json_response(['ok' => false, 'error' => 'Cancel before deleting.']);
    }

    if ($job['audio_path'] && is_file($job['audio_path'])) @unlink($job['audio_path']);
    $pdo->prepare('DELETE FROM clone_avatar_jobs WHERE id=? AND user_id=?')->execute([$jobId, $uid]);
    json_response(['ok' => true]);
}

// ── AJAX: poll ────────────────────────────────────────────────────────────────
if (($_GET['_action'] ?? '') === 'poll') {
    csrf_verify();
    $jobId = (int)($_GET['job_id'] ?? 0);
    $stmt  = $pdo->prepare(
        'SELECT id, status, video_url, error_message, credit_cost, started_at, created_at
         FROM clone_avatar_jobs WHERE id=? AND user_id=?'
    );
    $stmt->execute([$jobId, $uid]);
    $job = $stmt->fetch();
    if (!$job) { json_response(['error' => 'Not found'], 404); }

    if (in_array($job['status'], ['queued', 'processing'])) {
        $taskId = $pdo->prepare('SELECT api_task_id FROM clone_avatar_jobs WHERE id=?')
                      ->execute([$jobId]) ? null : null;
        $row = $pdo->prepare('SELECT api_task_id FROM clone_avatar_jobs WHERE id=?');
        $row->execute([$jobId]);
        $taskId = $row->fetchColumn();

        if ($taskId) {
            $result = clone_avatar_query_task($taskId);
            if ($result['ok']) {
                if ($result['status'] === 'completed' && $result['video_url']) {
                    $pdo->prepare(
                        'UPDATE clone_avatar_jobs
                         SET status="completed", video_url=?, api_response=?, completed_at=NOW()
                         WHERE id=?'
                    )->execute([$result['video_url'], json_encode($result['raw']), $jobId]);
                    $job['status']    = 'completed';
                    $job['video_url'] = $result['video_url'];

                } elseif ($result['status'] === 'completed' && !$result['video_url']) {
                    $errMsg = 'Task completed but no video URL returned.';
                    $pdo->prepare(
                        'UPDATE clone_avatar_jobs SET status="failed", error_message=?, api_response=? WHERE id=?'
                    )->execute([$errMsg, json_encode($result['raw']), $jobId]);
                    wallet_refund($uid, (float)$job['credit_cost'], 'clone_avatar_job', $jobId, 'Refund: no video URL');
                    $pdo->prepare('UPDATE clone_avatar_jobs SET status="refunded", refunded_at=NOW() WHERE id=?')->execute([$jobId]);
                    $job['status'] = 'refunded';

                } elseif ($result['status'] === 'failed') {
                    $errMsg = $result['error'] ?: 'Clone Avatar generation failed.';
                    $pdo->prepare(
                        'UPDATE clone_avatar_jobs SET status="failed", error_message=?, api_response=? WHERE id=?'
                    )->execute([$errMsg, json_encode($result['raw']), $jobId]);
                    wallet_refund($uid, (float)$job['credit_cost'], 'clone_avatar_job', $jobId,
                        'Refund: clone avatar job #' . $jobId . ' failed');
                    $pdo->prepare('UPDATE clone_avatar_jobs SET status="refunded", refunded_at=NOW() WHERE id=?')->execute([$jobId]);
                    $job['status']        = 'refunded';
                    $job['error_message'] = $errMsg;

                } else {
                    $pdo->prepare(
                        'UPDATE clone_avatar_jobs SET status=? WHERE id=? AND status != "completed"'
                    )->execute([$result['status'], $jobId]);
                    $job['status'] = $result['status'];
                }
            } else {
                $apiErrCode      = (int)($result['raw']['code'] ?? 0);
                $jobAge          = time() - strtotime($job['started_at'] ?: $job['created_at']);
                $pastGrace       = ($jobAge > 300);
                $permanentCodes  = [50204, 50200];
                $delayedCodes    = [50215];

                if (in_array($apiErrCode, $permanentCodes, true)
                    || (in_array($apiErrCode, $delayedCodes, true) && $pastGrace)) {
                    $errMsg = 'BytePlus rejected task (code ' . $apiErrCode . '). Credits refunded.';
                    $pdo->prepare(
                        'UPDATE clone_avatar_jobs SET status="failed", error_message=?, api_response=? WHERE id=?'
                    )->execute([$errMsg, json_encode($result['raw']), $jobId]);
                    wallet_refund($uid, (float)$job['credit_cost'], 'clone_avatar_job', $jobId,
                        'Refund: clone avatar job #' . $jobId . ' — code ' . $apiErrCode);
                    $pdo->prepare('UPDATE clone_avatar_jobs SET status="refunded", refunded_at=NOW() WHERE id=?')->execute([$jobId]);
                    $job['status']        = 'refunded';
                    $job['error_message'] = $errMsg;
                }
            }
        }
    }

    json_response([
        'status'    => $job['status'],
        'video_url' => $job['video_url'] ?? null,
        'error'     => $job['error_message'] ?? null,
    ]);
}

// ── POST: submit new job ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['_action'])) {
    csrf_verify();

    $errors = [];

    // Re-read user resource_id in case it was just saved
    $freshRow = $pdo->prepare('SELECT clone_avatar_resource_id FROM users WHERE id=?');
    $freshRow->execute([$uid]);
    $resourceId = trim((string)($freshRow->fetchColumn() ?: '')) ?: $adminResourceId;

    if (!$resourceId) {
        $errors[] = 'No Avatar ID set. Enter your resource_id above before submitting.';
    }

    if ($balance < $creditCost) {
        $errors[] = 'Insufficient credits. You need ' . format_credits($creditCost) . ' credits.';
    }

    // Resolve audio: file upload takes priority, then URL
    $audioUrl  = '';
    $audioPath = null;

    if (!empty($_FILES['audio_file']['tmp_name'])) {
        $file    = $_FILES['audio_file'];
        $allowed = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/wave', 'audio/x-wav', 'audio/ogg', 'audio/webm'];
        $mime    = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            $errors[] = 'Invalid audio format. Upload MP3 or WAV.';
        } elseif ($file['size'] > 50 * 1024 * 1024) {
            $errors[] = 'Audio file too large (max 50 MB).';
        } else {
            $ext      = in_array($mime, ['audio/wav', 'audio/wave', 'audio/x-wav']) ? 'wav' : 'mp3';
            $dir      = __DIR__ . '/../uploads/clone-audio/' . $uid;
            @mkdir($dir, 0755, true);
            $filename  = bin2hex(random_bytes(12)) . '.' . $ext;
            $audioPath = $dir . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $audioPath)) {
                $errors[] = 'Failed to save audio file.';
                $audioPath = null;
            } else {
                $audioUrl = BASE_URL . '/uploads/clone-audio/' . $uid . '/' . $filename;
            }
        }
    } elseif (!empty($_POST['audio_url'])) {
        $u = trim($_POST['audio_url']);
        if (!filter_var($u, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $u)) {
            $errors[] = 'Enter a valid http(s) audio URL.';
        } else {
            $audioUrl = $u;
        }
    } else {
        $errors[] = 'Provide an audio file upload or a public audio URL.';
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            wallet_deduct($uid, $creditCost, 'clone_avatar_job', 0, 'Clone Avatar job');

            $ins = $pdo->prepare(
                'INSERT INTO clone_avatar_jobs
                 (user_id, resource_id, audio_path, audio_url, credit_cost, status)
                 VALUES (?, ?, ?, ?, ?, "queued")'
            );
            $ins->execute([$uid, $resourceId, $audioPath, $audioUrl, $creditCost]);
            $jobId = (int)$pdo->lastInsertId();

            // Update wallet deduction reference
            $pdo->prepare(
                'UPDATE wallet_transactions SET reference_id=? WHERE user_id=? AND reference_type="clone_avatar_job" AND reference_id=0 ORDER BY id DESC LIMIT 1'
            )->execute([$jobId, $uid]);

            // Submit to BytePlus immediately
            $submit = clone_avatar_create_task($resourceId, $audioUrl);
            if ($submit['ok']) {
                $pdo->prepare(
                    'UPDATE clone_avatar_jobs SET api_task_id=?, status="processing", started_at=NOW() WHERE id=?'
                )->execute([$submit['task_id'], $jobId]);
            } else {
                $pdo->prepare(
                    'UPDATE clone_avatar_jobs SET error_message=?, api_response=? WHERE id=?'
                )->execute([$submit['error'] ?? 'Submit failed', json_encode($submit['raw']), $jobId]);
            }

            $pdo->commit();
            flash_success('Clone Avatar job submitted! Generation takes ~15–60 seconds.');
            redirect(BASE_URL . '/client/clone-avatar.php');
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($audioPath && is_file($audioPath)) @unlink($audioPath);
            $errors[] = 'Server error: ' . $e->getMessage();
        }
    } else {
        if ($audioPath && is_file($audioPath)) @unlink($audioPath);
    }
}

// ── Load recent jobs ──────────────────────────────────────────────────────────
$jobs = $pdo->prepare(
    'SELECT * FROM clone_avatar_jobs WHERE user_id=? ORDER BY created_at DESC LIMIT 20'
);
$jobs->execute([$uid]);
$jobs = $jobs->fetchAll();

$configured = (bool)$resourceId; // true if user or admin has a resource_id
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clone Avatar — <?= e(setting('site_name','Motions')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
    <style>
        .ca-form      { max-width: 640px; }
        .ca-notice    { background: var(--color-warning-bg, #fffbe6); border: 1px solid var(--color-warning, #f0c040); border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; }
        .job-row      { display: flex; align-items: center; gap: 14px; padding: 14px 0; border-bottom: 1px solid var(--color-border); }
        .job-row:last-child { border-bottom: none; }
        .job-status   { font-size: .8rem; font-weight: 600; padding: 3px 10px; border-radius: 20px; white-space: nowrap; }
        .s-queued, .s-processing { background: #e8f4ff; color: #1a73e8; }
        .s-completed  { background: #e6f4ea; color: #1e7e34; }
        .s-failed, .s-refunded { background: #fde8e8; color: #c0392b; }
        .job-actions  { margin-left: auto; display: flex; gap: 8px; }
        .audio-tabs   { display: flex; gap: 0; margin-bottom: 12px; border-bottom: 2px solid var(--color-border); }
        .audio-tab    { padding: 8px 18px; cursor: pointer; font-size: .9rem; color: var(--color-muted); border-bottom: 2px solid transparent; margin-bottom: -2px; }
        .audio-tab.active { color: var(--color-primary); border-bottom-color: var(--color-primary); font-weight: 600; }
    </style>
</head>
<body>
<?php render_client_navbar($user, 'clone_avatar'); ?>
<div class="container" style="max-width:860px;padding-top:32px">

    <?= render_flash() ?>

    <div class="page-header">
        <h1 class="page-title">Clone Avatar</h1>
        <p class="text-muted" style="margin-top:4px">Generate a video of your trained avatar speaking any audio.</p>
    </div>

    <!-- ── Avatar ID card ────────────────────────────────────────────────── -->
    <div class="card ca-form mb-4">
        <div class="card-header"><span class="card-title">Your Avatar ID</span></div>
        <p class="text-muted text-sm" style="padding:0 0 10px">
            After training an avatar with the Python scripts, paste the <code>resource_id</code>
            from <code>output/avatar_result.json</code> here.
            <?php if ($adminResourceId && !$userResourceId): ?>
                A shared avatar is active (set by admin).
            <?php endif; ?>
        </p>
        <div style="display:flex;gap:10px;align-items:flex-end">
            <div style="flex:1">
                <input type="text" id="inputResourceId" class="form-control"
                       placeholder="e.g. 250623-zhibo-linyunzhi"
                       value="<?= e($userResourceId) ?>">
            </div>
            <button class="btn btn-secondary" onclick="saveResourceId()">Save</button>
        </div>
        <div id="ridMsg" class="form-hint" style="margin-top:6px"></div>
    </div>

    <!-- ── Submit form ─────────────────────────────────────────────────── -->
    <div class="card ca-form mb-5">
        <div class="card-header"><span class="card-title">New Clone Avatar Job</span></div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger mb-3">
                <?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <!-- Audio source tabs -->
            <div class="form-group">
                <label class="form-label">Audio Source</label>
                <div class="audio-tabs">
                    <div class="audio-tab active" id="tabUpload" onclick="switchTab('upload')">Upload File</div>
                    <div class="audio-tab" id="tabUrl" onclick="switchTab('url')">Public URL</div>
                </div>

                <div id="panelUpload">
                    <input type="file" name="audio_file" class="form-control" accept="audio/mp3,audio/wav,audio/mpeg,.mp3,.wav">
                    <div class="form-hint">MP3 or WAV, max 50 MB</div>
                </div>

                <div id="panelUrl" style="display:none">
                    <input type="url" name="audio_url" class="form-control"
                           placeholder="https://cdn.example.com/speech.mp3"
                           value="<?= e($_POST['audio_url'] ?? '') ?>">
                    <div class="form-hint">Must be a publicly accessible http(s) URL</div>
                </div>
            </div>

            <div class="form-group">
                <div style="display:flex;align-items:center;justify-content:space-between">
                    <div>
                        <strong><?= format_credits($creditCost) ?> credits</strong>
                        <span class="text-muted text-sm"> per job &nbsp;·&nbsp; Balance: <?= format_credits($balance) ?></span>
                    </div>
                    <button type="submit" class="btn btn-primary" <?= (!$configured || $balance < $creditCost) ? 'disabled' : '' ?>>
                        Generate Video
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- ── Jobs list ──────────────────────────────────────────────────── -->
    <?php if (!empty($jobs)): ?>
    <div class="card">
        <div class="card-header"><span class="card-title">Recent Jobs</span></div>
        <div style="padding: 0 20px">
        <?php foreach ($jobs as $job): ?>
            <div class="job-row" id="jobRow<?= $job['id'] ?>">
                <div>
                    <div class="text-sm text-muted">#<?= $job['id'] ?> &nbsp;·&nbsp; <?= e(date('M j, H:i', strtotime($job['created_at']))) ?></div>
                    <div style="font-size:.85rem;color:var(--color-muted);margin-top:2px;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        <?= e(basename($job['audio_url'])) ?>
                    </div>
                </div>

                <span class="job-status s-<?= e($job['status']) ?>" id="status<?= $job['id'] ?>">
                    <?= e(ucfirst($job['status'])) ?>
                </span>

                <?php if ($job['status'] === 'completed' && $job['video_url']): ?>
                    <a href="<?= e($job['video_url']) ?>" target="_blank" class="btn btn-sm btn-primary">Watch</a>
                <?php endif; ?>

                <?php if ($job['status'] === 'failed' || $job['status'] === 'refunded'): ?>
                    <span class="text-sm text-danger" style="max-width:200px;overflow:hidden;text-overflow:ellipsis" title="<?= e($job['error_message'] ?? '') ?>">
                        <?= e(substr($job['error_message'] ?? 'Error', 0, 60)) ?>
                    </span>
                <?php endif; ?>

                <div class="job-actions">
                    <?php if (in_array($job['status'], ['queued', 'processing'])): ?>
                        <button class="btn btn-sm btn-ghost" onclick="cancelJob(<?= $job['id'] ?>)">Cancel</button>
                        <button class="btn btn-sm btn-ghost" id="pollBtn<?= $job['id'] ?>"
                                onclick="pollJob(<?= $job['id'] ?>)">Refresh</button>
                    <?php else: ?>
                        <button class="btn btn-sm btn-ghost" onclick="deleteJob(<?= $job['id'] ?>)">Delete</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
const CSRF  = <?= json_encode(csrf_token()) ?>;
const BASE  = <?= json_encode(BASE_URL) ?>;

// Tab switching
function switchTab(tab) {
    document.getElementById('panelUpload').style.display = tab === 'upload' ? '' : 'none';
    document.getElementById('panelUrl').style.display    = tab === 'url'    ? '' : 'none';
    document.getElementById('tabUpload').classList.toggle('active', tab === 'upload');
    document.getElementById('tabUrl').classList.toggle('active',    tab === 'url');
}

// Auto-poll any queued/processing jobs on page load
document.addEventListener('DOMContentLoaded', () => {
    <?php foreach ($jobs as $job): ?>
        <?php if (in_array($job['status'], ['queued', 'processing'])): ?>
            scheduleAutoPoll(<?= $job['id'] ?>);
        <?php endif; ?>
    <?php endforeach; ?>
});

const activePollers = {};

function scheduleAutoPoll(jobId, delayMs = 8000) {
    if (activePollers[jobId]) return;
    activePollers[jobId] = setTimeout(() => {
        delete activePollers[jobId];
        pollJob(jobId, true);
    }, delayMs);
}

function pollJob(jobId, auto = false) {
    const btn = document.getElementById('pollBtn' + jobId);
    if (btn && !auto) btn.textContent = '…';

    fetch(BASE + '/client/clone-avatar.php?_action=poll&job_id=' + jobId, {
        method: 'GET',
        headers: { 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
    })
    .then(r => r.json())
    .then(data => {
        const statusEl = document.getElementById('status' + jobId);
        if (statusEl) {
            statusEl.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
            statusEl.className   = 'job-status s-' + data.status;
        }

        if (data.status === 'completed' && data.video_url) {
            const row = document.getElementById('jobRow' + jobId);
            if (row) {
                const actions = row.querySelector('.job-actions');
                if (actions) actions.innerHTML = '<a href="' + data.video_url + '" target="_blank" class="btn btn-sm btn-primary">Watch</a> <button class="btn btn-sm btn-ghost" onclick="deleteJob(' + jobId + ')">Delete</button>';
            }
        } else if (data.status === 'queued' || data.status === 'processing') {
            scheduleAutoPoll(jobId, 10000);
            if (btn && !auto) btn.textContent = 'Refresh';
        } else {
            if (btn) btn.textContent = 'Refresh';
        }
    })
    .catch(() => { if (btn) btn.textContent = 'Refresh'; });
}

function saveResourceId() {
    const rid = document.getElementById('inputResourceId').value.trim();
    const msg = document.getElementById('ridMsg');
    msg.textContent = 'Saving…';
    fetch(BASE + '/client/clone-avatar.php?_action=save_resource_id', {
        method: 'POST',
        headers: { 'X-CSRF-Token': CSRF, 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'resource_id=' + encodeURIComponent(rid),
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            msg.textContent = rid ? '✅ Saved — ' + rid : '✅ Cleared.';
            msg.style.color = 'var(--color-success, green)';
        } else {
            msg.textContent = '❌ ' + (d.error || 'Error');
            msg.style.color = 'var(--color-danger, red)';
        }
    })
    .catch(() => { msg.textContent = '❌ Network error'; });
}

function jobAction(action, jobId) {
    return fetch(BASE + '/client/clone-avatar.php?_action=' + action + '&job_id=' + jobId, {
        method: 'GET',
        headers: { 'X-CSRF-Token': CSRF },
    }).then(r => r.json());
}

function cancelJob(jobId) {
    if (!confirm('Cancel this job and refund credits?')) return;
    jobAction('cancel', jobId).then(d => { if (d.ok) location.reload(); else alert(d.error || 'Error'); });
}

function deleteJob(jobId) {
    if (!confirm('Delete this job record?')) return;
    jobAction('delete', jobId).then(d => {
        if (d.ok) {
            const row = document.getElementById('jobRow' + jobId);
            if (row) row.remove();
        } else {
            alert(d.error || 'Error');
        }
    });
}
</script>
</body>
</html>
