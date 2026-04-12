<?php
declare(strict_types=1);

/**
 * client/avatar.php
 * AI Avatar (OmniHuman 1.5) — upload a portrait + audio/text → talking-head video.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/wallet.php';
require_once __DIR__ . '/../inc/omnihuman.php';
require_once __DIR__ . '/../inc/layout.php';

boot_session();
$user = require_auth('/public/login.php');
$uid  = (int)$user['id'];
$pdo  = db();

$balance    = wallet_balance($uid);
$creditCost = (float)(setting('avatar_credit_cost', '5') ?: '5');
$errors     = [];
$jobs       = [];

// ── AJAX: poll status ─────────────────────────────────────────────────────────
if (($_GET['_action'] ?? '') === 'poll') {
    csrf_verify();
    $jobId = (int)($_GET['job_id'] ?? 0);
    $stmt  = $pdo->prepare(
        'SELECT id, status, video_url, error_message FROM avatar_jobs WHERE id=? AND user_id=?'
    );
    $stmt->execute([$jobId, $uid]);
    $job = $stmt->fetch();
    if (!$job) { json_response(['error' => 'Not found'], 404); }

    // If still processing, call OmniHuman API
    if (in_array($job['status'], ['queued', 'processing'])) {
        $taskStmt = $pdo->prepare('SELECT api_task_id FROM avatar_jobs WHERE id=?');
        $taskStmt->execute([$jobId]);
        $taskId = $taskStmt->fetchColumn();

        if ($taskId) {
            $result = omnihuman_query_task($taskId);
            if ($result['ok']) {
                if ($result['status'] === 'completed' && $result['video_url']) {
                    $pdo->prepare(
                        'UPDATE avatar_jobs SET status="completed", video_url=?, api_response=?, completed_at=NOW() WHERE id=?'
                    )->execute([
                        $result['video_url'],
                        json_encode($result['raw']),
                        $jobId,
                    ]);
                    $job['status']    = 'completed';
                    $job['video_url'] = $result['video_url'];
                } elseif ($result['status'] === 'failed') {
                    $pdo->prepare(
                        'UPDATE avatar_jobs SET status="failed", error_message=? WHERE id=?'
                    )->execute([$result['error'] ?? 'OmniHuman failed', $jobId]);
                    // Refund
                    wallet_refund($uid, $creditCost, 'avatar_job', $jobId,
                        'Refund: avatar job #' . $jobId . ' failed');
                    $pdo->prepare(
                        'UPDATE avatar_jobs SET status="refunded", refunded_at=NOW() WHERE id=?'
                    )->execute([$jobId]);
                    $job['status'] = 'refunded';
                } else {
                    $pdo->prepare(
                        'UPDATE avatar_jobs SET status=? WHERE id=? AND status != "completed"'
                    )->execute([$result['status'], $jobId]);
                    $job['status'] = $result['status'];
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

// ── POST: submit new avatar job ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['_action'])) {
    csrf_verify();

    $audioMode = $_POST['audio_mode'] ?? 'upload'; // 'upload' | 'tts'
    $ttsText   = trim($_POST['tts_text'] ?? '');
    $duration  = min(30, max(5, (int)($_POST['duration'] ?? 10)));

    // ── Validate portrait ────────────────────────────────────────────────────
    $portrait = $_FILES['portrait'] ?? null;
    if (!$portrait || $portrait['error'] !== UPLOAD_ERR_OK) {
        $errors['portrait'] = 'Please upload a portrait image.';
    } elseif (!in_array($portrait['type'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
        $errors['portrait'] = 'Portrait must be a JPG, PNG, or WebP image.';
    } elseif ($portrait['size'] > 8 * 1024 * 1024) {
        $errors['portrait'] = 'Portrait image must be under 8 MB.';
    }

    // ── Validate audio / TTS ─────────────────────────────────────────────────
    $audioFile = $_FILES['audio'] ?? null;
    if ($audioMode === 'upload') {
        if (!$audioFile || $audioFile['error'] !== UPLOAD_ERR_OK) {
            $errors['audio'] = 'Please upload an audio file.';
        } elseif (!in_array($audioFile['type'], ['audio/mpeg','audio/mp3','audio/wav','audio/x-wav','audio/ogg'], true)) {
            $errors['audio'] = 'Audio must be MP3, WAV, or OGG.';
        } elseif ($audioFile['size'] > 20 * 1024 * 1024) {
            $errors['audio'] = 'Audio file must be under 20 MB.';
        }
    } elseif (mb_strlen($ttsText) < 5) {
        $errors['tts_text'] = 'TTS text must be at least 5 characters.';
    } elseif (mb_strlen($ttsText) > 2000) {
        $errors['tts_text'] = 'TTS text cannot exceed 2000 characters.';
    }

    // ── Balance check ────────────────────────────────────────────────────────
    if (empty($errors) && $balance < $creditCost) {
        $errors['balance'] = sprintf(
            'Insufficient credits. Need %.2f, you have %.2f. <a href="%s">Top up →</a>',
            $creditCost, $balance, BASE_URL . '/client/buy-credits.php'
        );
    }

    if (empty($errors)) {
        // Save portrait
        $portraitExt  = pathinfo($portrait['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $portraitName = 'portrait_' . $uid . '_' . bin2hex(random_bytes(8)) . '.' . $portraitExt;
        $portraitPath = BASE_PATH . '/uploads/avatars/' . $portraitName;
        move_uploaded_file($portrait['tmp_name'], $portraitPath);

        // Save audio (if uploaded)
        $audioPath = null;
        if ($audioMode === 'upload' && $audioFile) {
            $audioExt  = pathinfo($audioFile['name'], PATHINFO_EXTENSION) ?: 'mp3';
            $audioName = 'audio_' . $uid . '_' . bin2hex(random_bytes(8)) . '.' . $audioExt;
            $audioPath = BASE_PATH . '/uploads/avatar_audio/' . $audioName;
            move_uploaded_file($audioFile['tmp_name'], $audioPath);
        }

        $pdo->beginTransaction();
        try {
            // Create DB record
            $stmt = $pdo->prepare(
                'INSERT INTO avatar_jobs
                 (user_id, portrait_path, audio_path, tts_text, duration, resolution, credit_cost, status)
                 VALUES (?,?,?,?,?,?,?,"queued")'
            );
            $stmt->execute([
                $uid,
                $portraitPath,
                $audioPath,
                $audioMode === 'tts' ? $ttsText : null,
                $duration,
                '720p',
                $creditCost,
            ]);
            $jobId = (int)$pdo->lastInsertId();

            // Deduct credits
            $deduct = wallet_deduct($uid, $creditCost, 'deduction', 'avatar_job', $jobId,
                'AI Avatar job #' . $jobId);
            if (!$deduct['ok']) {
                $pdo->rollBack();
                @unlink($portraitPath);
                if ($audioPath) @unlink($audioPath);
                $errors['balance'] = $deduct['error'];
            } else {
                // Convert files to base64 and submit to OmniHuman
                $imgB64   = omnihuman_file_to_base64($portraitPath);
                $audioB64 = $audioPath ? omnihuman_file_to_base64($audioPath) : null;

                $apiResult = omnihuman_create_task(
                    $imgB64 ?? '',
                    $audioB64,
                    $audioMode === 'tts' ? $ttsText : null,
                    ['duration' => $duration, 'resolution' => '720p']
                );

                if ($apiResult['ok']) {
                    $pdo->prepare(
                        'UPDATE avatar_jobs
                         SET status="processing", api_task_id=?, api_response=?, started_at=NOW()
                         WHERE id=?'
                    )->execute([
                        $apiResult['task_id'],
                        json_encode($apiResult['raw']),
                        $jobId,
                    ]);
                    $pdo->commit();
                    flash_success('Avatar video is being generated! Track progress below.');
                    redirect(BASE_URL . '/client/avatar.php');
                } else {
                    // API failed — refund
                    $pdo->prepare(
                        'UPDATE avatar_jobs SET status="failed", error_message=? WHERE id=?'
                    )->execute([$apiResult['error'], $jobId]);
                    wallet_refund($uid, $creditCost, 'avatar_job', $jobId,
                        'Refund: API failed for avatar job #' . $jobId);
                    $pdo->prepare(
                        'UPDATE avatar_jobs SET status="refunded", refunded_at=NOW() WHERE id=?'
                    )->execute([$jobId]);
                    $pdo->commit();
                    flash_error('Avatar generation failed: ' . $apiResult['error'] . ' Credits refunded.');
                    redirect(BASE_URL . '/client/avatar.php');
                }
            }
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[avatar] ' . $e->getMessage());
            $errors['general'] = 'Something went wrong. Please try again.';
        }
    }
}

// ── Load job history ──────────────────────────────────────────────────────────
// Wrap in try/catch in case migrate_avatar.sql hasn't been run yet
$avatarTableMissing = false;
try {
    $stmt = $pdo->prepare(
        'SELECT id, status, duration, credit_cost, tts_text, audio_path, portrait_path,
                video_url, error_message, created_at, completed_at
         FROM avatar_jobs WHERE user_id=? ORDER BY created_at DESC LIMIT 20'
    );
    $stmt->execute([$uid]);
    $jobs = $stmt->fetchAll();
} catch (\PDOException $e) {
    $jobs = [];
    $avatarTableMissing = true;
    error_log('[avatar] avatar_jobs table missing — run sql/migrate_avatar.sql: ' . $e->getMessage());
}

$balance = wallet_balance($uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Avatar — <?= e(setting('site_name','VideoSaaS')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
    <style>
        .upload-zone {
            border: 2px dashed var(--color-border);
            border-radius: var(--radius);
            padding: 28px 20px;
            text-align: center;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            position: relative;
        }
        .upload-zone:hover, .upload-zone.dragover {
            border-color: var(--color-primary);
            background: rgba(108,71,255,.05);
        }
        .upload-zone input[type=file] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }
        .upload-zone .icon { font-size: 2.2rem; margin-bottom: 6px; }
        .upload-preview {
            max-width: 160px;
            max-height: 160px;
            border-radius: 8px;
            margin: 10px auto 0;
            display: block;
            box-shadow: 0 2px 12px rgba(0,0,0,.3);
        }
        .audio-preview { width: 100%; margin-top: 10px; }
        .mode-tabs { display: flex; gap: 0; border: 1px solid var(--color-border); border-radius: var(--radius); overflow: hidden; margin-bottom: 14px; }
        .mode-tab { flex: 1; padding: 9px; text-align: center; cursor: pointer; font-size: .85rem; font-weight: 600; border: none; background: transparent; color: var(--color-muted); transition: all .15s; }
        .mode-tab.active { background: var(--color-primary); color: #fff; }
        .job-card { display: flex; gap: 14px; align-items: flex-start; }
        .job-portrait { width: 64px; height: 64px; border-radius: 50%; object-fit: cover; flex-shrink: 0; background: var(--color-surface2); }
        .job-info { flex: 1; min-width: 0; }
        .status-badge { display: inline-block; padding: 2px 10px; border-radius: 99px; font-size: .72rem; font-weight: 700; text-transform: uppercase; }
        .status-badge.queued     { background: rgba(255,193,7,.15); color: #ffc107; }
        .status-badge.processing { background: rgba(108,71,255,.15); color: var(--color-primary); }
        .status-badge.completed  { background: rgba(34,197,94,.15);  color: #22c55e; }
        .status-badge.failed,
        .status-badge.refunded   { background: rgba(239,68,68,.15);  color: #ef4444; }
        .prog-bar { height: 4px; background: var(--color-border); border-radius: 2px; margin: 6px 0 0; overflow: hidden; }
        .prog-fill { height: 100%; background: linear-gradient(90deg,var(--color-primary),var(--color-accent)); width: 60%; animation: progAnim 2s ease-in-out infinite alternate; }
        @keyframes progAnim { from{width:20%} to{width:90%} }
        .video-thumb-wrap { position: relative; cursor: pointer; display: inline-block; }
        .video-thumb-wrap video { width: 120px; height: 68px; object-fit: cover; border-radius: 6px; display: block; }
        .play-overlay { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,.35); border-radius: 6px; font-size: 1.5rem; }
        /* Modal */
        .vmodal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.85); z-index:9999; align-items:center; justify-content:center; }
        .vmodal.open { display:flex; }
        .vmodal video { max-width:90vw; max-height:85vh; border-radius:8px; }
        .vmodal-close { position:absolute; top:18px; right:24px; font-size:2rem; color:#fff; cursor:pointer; line-height:1; }
    </style>
</head>
<?php
// ── Debug action: test config + API connectivity ──────────────────────────────
if (($_GET['_action'] ?? '') === 'debug_test') {
    csrf_verify();
    [$ak, $sk, $apiBase, $reqKey] = _omnihuman_creds();

    $results = [];

    // 1. Config check
    $results['config'] = [
        'vision_ai_ak'       => $ak  ? ('set (' . substr($ak, 0, 4) . '…)') : 'NOT SET',
        'vision_ai_sk'       => $sk  ? 'set (***)' : 'NOT SET',
        'vision_ai_url'      => $apiBase ?: 'NOT SET',
        'omnihuman_req_key'  => $reqKey  ?: 'NOT SET',
    ];

    // 2. Upload directory check
    $avatarDir = BASE_PATH . '/uploads/avatars';
    $audioDir  = BASE_PATH . '/uploads/avatar_audio';
    $results['dirs'] = [
        'uploads/avatars'       => is_dir($avatarDir) ? (is_writable($avatarDir) ? 'OK (writable)' : 'EXISTS but not writable') : 'MISSING',
        'uploads/avatar_audio'  => is_dir($audioDir)  ? (is_writable($audioDir)  ? 'OK (writable)' : 'EXISTS but not writable') : 'MISSING',
    ];

    // Auto-create missing dirs
    if (!is_dir($avatarDir)) { @mkdir($avatarDir, 0755, true); $results['dirs']['uploads/avatars'] .= ' → created'; }
    if (!is_dir($audioDir))  { @mkdir($audioDir,  0755, true); $results['dirs']['uploads/avatar_audio'] .= ' → created'; }

    // 3. Live API connectivity test (submit a minimal dummy payload to see the error)
    if ($ak && $sk && $apiBase) {
        $url = rtrim($apiBase, '/') . '/api/v1/ai_video_generate';
        // Send a minimal (intentionally invalid) payload — we just want to see
        // if the server responds at all and what auth error looks like
        $testPayload = ['req_key' => $reqKey, 'image_base64' => 'test', 'text' => 'test'];
        $path   = '/api/v1/ai_video_generate';
        $body   = json_encode($testPayload, JSON_UNESCAPED_SLASHES);
        $headers = vision_signed_headers('POST', $path, $body, $ak, $sk);
        $headerLines = array_map(fn($k,$v) => "$k: $v", array_keys($headers), array_values($headers));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headerLines,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp    = curl_exec($ch);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $results['api_test'] = [
            'url'        => $url,
            'http_code'  => $code,
            'curl_error' => $curlErr ?: 'none',
            'raw_response' => $resp ?: '(empty)',
        ];
    } else {
        $results['api_test'] = 'SKIPPED — AK/SK or URL not configured';
    }

    // 4. Last job API response (most recent job for this user)
    $lastJob = $pdo->prepare('SELECT id,status,api_response,error_message FROM avatar_jobs WHERE user_id=? ORDER BY id DESC LIMIT 1');
    $lastJob->execute([$uid]);
    $last = $lastJob->fetch();
    if ($last) {
        $results['last_job'] = [
            'id'            => $last['id'],
            'status'        => $last['status'],
            'error_message' => $last['error_message'] ?: '(none)',
            'api_response'  => $last['api_response'] ? json_decode($last['api_response'], true) : null,
        ];
    } else {
        $results['last_job'] = 'No jobs yet';
    }

    json_response(['ok' => true, 'debug' => $results]);
}
?>
<body>
<?php render_client_navbar($user, 'avatar'); ?>

<div class="container main-content">
    <?= render_flash() ?>
    <?php if ($avatarTableMissing): ?>
    <div class="alert alert--error">
        ⚠️ <strong>Database setup required.</strong>
        The <code>avatar_jobs</code> table is missing.
        Please run <code>sql/migrate_avatar.sql</code> in phpMyAdmin, then refresh this page.
    </div>
    <?php endif; ?>

    <div class="page-header">
        <div>
            <h1 class="page-title">AI Avatar</h1>
            <p class="page-sub">Upload a portrait + audio to generate a talking-head video (OmniHuman 1.5)</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center">
            <button onclick="toggleAvatarDebug()" class="btn btn-ghost btn-sm" style="font-size:.75rem;opacity:.7">🔧 Debug</button>
            <span class="navbar-wallet">⚡ <?= e(format_credits($balance)) ?> credits</span>
        </div>
    </div>

    <!-- ── Debug panel ──────────────────────────────────────────────────────── -->
    <div id="avatarDebugWrap" style="display:none;margin-bottom:20px">
        <div style="background:#0f172a;border:1px solid #334155;border-radius:8px;padding:14px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                <span style="color:#facc15;font-weight:700;font-size:.85rem">🔧 Avatar Debug Console</span>
                <div style="display:flex;gap:8px">
                    <button onclick="runAvatarDebug()" class="btn btn-sm"
                            style="background:#1e40af;color:#fff;font-size:.75rem">▶ Run Diagnostics</button>
                    <button onclick="document.getElementById('avatarDebugLog').innerHTML=''"
                            class="btn btn-ghost btn-sm" style="font-size:.75rem">Clear</button>
                </div>
            </div>
            <div id="avatarDebugLog"
                 style="font-family:monospace;font-size:.75rem;line-height:1.7;max-height:420px;overflow-y:auto;color:#94a3b8">
                Click "Run Diagnostics" to test configuration and API connectivity.
            </div>
        </div>
    </div>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert--error"><?= e($errors['general']) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors['balance'])): ?>
        <div class="alert alert--error"><?= $errors['balance'] ?></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">

        <!-- ── Upload form ─────────────────────────────────────────────────── -->
        <div class="card">
            <div class="card-header"><span class="card-title">Create Avatar Video</span></div>

            <form method="POST" enctype="multipart/form-data" id="avatarForm">
                <?= csrf_field() ?>

                <!-- Portrait upload -->
                <div class="form-group">
                    <label class="form-label">Portrait Photo <span style="color:var(--color-danger)">*</span></label>
                    <p class="text-muted text-sm" style="margin-bottom:8px">
                        Clear frontal face photo. JPG/PNG/WebP, max 8 MB.
                    </p>
                    <?php if (!empty($errors['portrait'])): ?>
                        <div class="alert alert--error" style="margin-bottom:8px"><?= e($errors['portrait']) ?></div>
                    <?php endif; ?>
                    <div class="upload-zone" id="portraitZone"
                         ondragover="zoneDrag(event,this)" ondragleave="zoneDrag(event,this,true)"
                         ondrop="zoneDrop(event,'portrait')">
                        <input type="file" name="portrait" id="portraitInput" accept="image/*"
                               onchange="previewFile(this,'portraitPreview','portraitZoneText','image')">
                        <div id="portraitZoneText">
                            <div class="icon">🤳</div>
                            <div style="font-weight:600">Click or drag portrait here</div>
                            <div class="text-muted text-sm">JPG · PNG · WebP</div>
                        </div>
                        <img id="portraitPreview" class="upload-preview" style="display:none">
                    </div>
                </div>

                <!-- Audio / TTS tabs -->
                <div class="form-group" style="margin-top:16px">
                    <label class="form-label">Voice Input <span style="color:var(--color-danger)">*</span></label>
                    <div class="mode-tabs">
                        <button type="button" class="mode-tab active" onclick="setAudioMode('upload')" id="tabUpload">
                            🎵 Upload Audio
                        </button>
                        <button type="button" class="mode-tab" onclick="setAudioMode('tts')" id="tabTts">
                            💬 Text-to-Speech
                        </button>
                    </div>
                    <input type="hidden" name="audio_mode" id="audioMode" value="upload">

                    <!-- Upload panel -->
                    <div id="panelUpload">
                        <?php if (!empty($errors['audio'])): ?>
                            <div class="alert alert--error" style="margin-bottom:8px"><?= e($errors['audio']) ?></div>
                        <?php endif; ?>
                        <div class="upload-zone" id="audioZone"
                             ondragover="zoneDrag(event,this)" ondragleave="zoneDrag(event,this,true)"
                             ondrop="zoneDrop(event,'audio')">
                            <input type="file" name="audio" id="audioInput" accept="audio/*"
                                   onchange="previewFile(this,'audioPreview','audioZoneText','audio')">
                            <div id="audioZoneText">
                                <div class="icon">🎙️</div>
                                <div style="font-weight:600">Click or drag audio here</div>
                                <div class="text-muted text-sm">MP3 · WAV · OGG · max 20 MB</div>
                            </div>
                        </div>
                        <audio id="audioPreview" class="audio-preview" controls style="display:none"></audio>
                    </div>

                    <!-- TTS panel -->
                    <div id="panelTts" style="display:none">
                        <?php if (!empty($errors['tts_text'])): ?>
                            <div class="alert alert--error" style="margin-bottom:8px"><?= e($errors['tts_text']) ?></div>
                        <?php endif; ?>
                        <textarea name="tts_text" id="ttsText" class="form-control" rows="5" maxlength="2000"
                                  placeholder="Type what you want the avatar to say…
Example: Welcome to our platform! We help businesses create stunning AI marketing videos in minutes."
                                  oninput="document.getElementById('ttsCount').textContent=this.value.length+'/2000'"><?= e($_POST['tts_text'] ?? '') ?></textarea>
                        <div id="ttsCount" style="font-size:.78rem;color:var(--color-muted);text-align:right;margin-top:3px">
                            0/2000
                        </div>
                    </div>
                </div>

                <!-- Duration -->
                <div class="form-group" style="margin-top:14px">
                    <label class="form-label">Duration</label>
                    <div style="display:flex;gap:10px;flex-wrap:wrap">
                        <?php foreach ([5,10,15,20,30] as $d): ?>
                            <label style="cursor:pointer;display:flex;align-items:center;gap:5px;font-size:.87rem">
                                <input type="radio" name="duration" value="<?= $d ?>"
                                       <?= ($d === 10) ? 'checked' : '' ?>>
                                <?= $d ?>s
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Cost + Submit -->
                <div style="display:flex;align-items:center;justify-content:space-between;margin-top:20px;flex-wrap:wrap;gap:12px">
                    <div>
                        <div class="text-muted text-sm">Cost</div>
                        <div style="font-size:1.5rem;font-weight:900;color:var(--color-accent)">
                            <?= e(format_credits($creditCost)) ?>
                            <span style="font-size:.8rem;color:var(--color-muted);font-weight:400">credits</span>
                        </div>
                        <div style="font-size:.78rem;color:var(--color-muted)">
                            Balance after: <?= e(format_credits($balance - $creditCost)) ?> credits
                        </div>
                    </div>
                    <button type="submit" id="submitBtn" class="btn btn-primary btn-lg"
                            <?= $balance < $creditCost ? 'disabled' : '' ?>>
                        🎭 Generate Avatar
                    </button>
                </div>

                <?php if ($balance < $creditCost): ?>
                    <div class="text-sm text-muted mt-1">
                        <a href="<?= BASE_URL ?>/client/buy-credits.php">Buy credits first →</a>
                    </div>
                <?php endif; ?>

            </form>
        </div>

        <!-- ── How it works ────────────────────────────────────────────────── -->
        <div>
            <div class="card mb-4">
                <div class="card-header"><span class="card-title">How it works</span></div>
                <ol style="margin:0 0 0 20px;line-height:2;font-size:.88rem;color:var(--color-muted)">
                    <li><strong style="color:var(--color-text)">Upload a portrait</strong> — clear frontal photo of a real person</li>
                    <li><strong style="color:var(--color-text)">Add voice</strong> — upload an MP3/WAV recording OR type text for AI speech</li>
                    <li><strong style="color:var(--color-text)">Generate</strong> — OmniHuman 1.5 lip-syncs the person to your audio</li>
                    <li><strong style="color:var(--color-text)">Download</strong> — get your talking avatar video in minutes</li>
                </ol>
            </div>
            <div class="card">
                <div class="card-header"><span class="card-title">Tips for best results</span></div>
                <ul style="margin:0 0 0 20px;line-height:2;font-size:.84rem;color:var(--color-muted)">
                    <li>Use a <strong style="color:var(--color-text)">well-lit frontal photo</strong> with clear face visibility</li>
                    <li>Avoid hats, sunglasses, or heavy obstructions</li>
                    <li>Audio should be <strong style="color:var(--color-text)">clear speech</strong> with minimal background noise</li>
                    <li>MP3 at 128kbps+ gives best results</li>
                    <li>Keep TTS text natural and conversational</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ── Job history ──────────────────────────────────────────────────────── -->
    <?php if (!empty($jobs)): ?>
    <div class="card mt-4">
        <div class="card-header"><span class="card-title">Avatar History</span></div>
        <div style="display:flex;flex-direction:column;gap:14px">
            <?php foreach ($jobs as $job): ?>
                <?php
                    $portraitThumb = '';
                    if ($job['portrait_path'] && is_file($job['portrait_path'])) {
                        // Serve via data URI (small portrait thumb)
                        $portraitThumb = 'data:image/jpeg;base64,' .
                            base64_encode(file_get_contents($job['portrait_path']));
                    }
                    $label = $job['tts_text']
                        ? mb_strimwidth($job['tts_text'], 0, 60, '…')
                        : 'Audio upload · ' . $job['duration'] . 's';
                    $inProgress = in_array($job['status'], ['queued', 'processing']);
                ?>
                <div class="card" style="padding:14px" id="ajob_<?= (int)$job['id'] ?>">
                    <div class="job-card">
                        <?php if ($portraitThumb): ?>
                            <img src="<?= e($portraitThumb) ?>" class="job-portrait" alt="Portrait">
                        <?php else: ?>
                            <div class="job-portrait" style="display:flex;align-items:center;justify-content:center;font-size:1.8rem">🧑</div>
                        <?php endif; ?>

                        <div class="job-info">
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">
                                <span class="status-badge <?= e($job['status']) ?>"
                                      id="ajob_status_<?= (int)$job['id'] ?>">
                                    <?= e($job['status']) ?>
                                </span>
                                <span class="text-muted text-sm">
                                    <?= e($job['duration']) ?>s · <?= e(format_credits((float)$job['credit_cost'])) ?> credits
                                </span>
                                <span class="text-muted text-sm" style="margin-left:auto">
                                    <?= e(date('d M Y H:i', strtotime($job['created_at']))) ?>
                                </span>
                            </div>

                            <div style="font-size:.85rem;color:var(--color-muted);margin-bottom:6px">
                                <?= e($label) ?>
                            </div>

                            <?php if ($inProgress): ?>
                                <div class="prog-bar"><div class="prog-fill"></div></div>
                                <div class="text-muted text-sm" style="margin-top:4px">Processing…</div>
                            <?php elseif ($job['status'] === 'completed' && $job['video_url']): ?>
                                <div style="display:flex;align-items:center;gap:10px;margin-top:6px">
                                    <div class="video-thumb-wrap" onclick="openVideo(<?= htmlspecialchars(json_encode($job['video_url'])) ?>)">
                                        <video src="<?= e($job['video_url']) ?>#t=0.5" preload="metadata"
                                               muted playsinline style="pointer-events:none"></video>
                                        <div class="play-overlay">▶</div>
                                    </div>
                                    <div style="display:flex;flex-direction:column;gap:6px">
                                        <button class="btn btn-primary btn-sm"
                                                onclick="openVideo(<?= htmlspecialchars(json_encode($job['video_url'])) ?>)">
                                            ▶ Preview
                                        </button>
                                        <a href="<?= e($job['video_url']) ?>" download
                                           class="btn btn-ghost btn-sm">⬇ Download</a>
                                    </div>
                                </div>
                            <?php elseif (!empty($job['error_message'])): ?>
                                <div class="text-sm" style="color:var(--color-danger);margin-top:4px">
                                    <?= e($job['error_message']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Video modal -->
<div class="vmodal" id="videoModal">
    <span class="vmodal-close" onclick="closeVideo()">✕</span>
    <video id="modalVideo" controls autoplay></video>
</div>

<script>
// ── File upload previews ──────────────────────────────────────────────────────
function previewFile(input, previewId, textId, type) {
    const file = input.files[0];
    if (!file) return;
    const url = URL.createObjectURL(file);
    if (type === 'image') {
        const img = document.getElementById(previewId);
        img.src = url; img.style.display = 'block';
        document.getElementById(textId).style.display = 'none';
    } else {
        const audio = document.getElementById(previewId);
        audio.src = url; audio.style.display = 'block';
        document.getElementById(textId).querySelector('div.icon').textContent = '✅';
    }
}

function zoneDrag(e, el, leave) {
    e.preventDefault();
    el.classList.toggle('dragover', !leave);
}

function zoneDrop(e, field) {
    e.preventDefault();
    document.getElementById(field + 'Zone').classList.remove('dragover');
    const dt = e.dataTransfer;
    if (!dt.files.length) return;
    const input = document.getElementById(field + 'Input');
    // Assign file to input via DataTransfer
    const transfer = new DataTransfer();
    transfer.items.add(dt.files[0]);
    input.files = transfer.files;
    input.dispatchEvent(new Event('change'));
}

// ── Audio mode toggle ─────────────────────────────────────────────────────────
function setAudioMode(mode) {
    document.getElementById('audioMode').value = mode;
    document.getElementById('panelUpload').style.display = mode === 'upload' ? 'block' : 'none';
    document.getElementById('panelTts').style.display    = mode === 'tts'    ? 'block' : 'none';
    document.getElementById('tabUpload').classList.toggle('active', mode === 'upload');
    document.getElementById('tabTts').classList.toggle('active',    mode === 'tts');
    // Clear the inactive field so validation doesn't fire
    if (mode === 'tts') {
        const inp = document.getElementById('audioInput');
        inp.value = '';
    }
}

// ── Video modal ───────────────────────────────────────────────────────────────
function openVideo(url) {
    const modal = document.getElementById('videoModal');
    document.getElementById('modalVideo').src = url;
    modal.classList.add('open');
}
function closeVideo() {
    const modal = document.getElementById('videoModal');
    modal.classList.remove('open');
    document.getElementById('modalVideo').pause();
    document.getElementById('modalVideo').src = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeVideo(); });

// ── Prevent double-submit ─────────────────────────────────────────────────────
document.getElementById('avatarForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = 'Submitting…';
});

// ── Poll in-progress jobs ─────────────────────────────────────────────────────
const pendingJobs = <?= json_encode(
    array_values(array_map(
        fn($j) => (int)$j['id'],
        array_filter($jobs, fn($j) => in_array($j['status'], ['queued','processing']))
    ))
) ?>;
const csrfToken = <?= json_encode($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>;

function pollJobs(ids) {
    if (!ids.length) return;
    ids.forEach(id => {
        fetch(`<?= BASE_URL ?>/client/avatar.php?_action=poll&job_id=${id}`, {
            headers: { 'X-CSRF-Token': csrfToken }
        })
        .then(r => r.json())
        .then(data => {
            const badge = document.getElementById('ajob_status_' + id);
            if (badge) {
                badge.textContent = data.status;
                badge.className   = 'status-badge ' + data.status;
            }
            if (data.status === 'completed' && data.video_url) {
                // Inject video preview
                const card = document.getElementById('ajob_' + id);
                if (card) {
                    const info = card.querySelector('.job-info');
                    const progBar = info.querySelector('.prog-bar');
                    if (progBar) progBar.remove();
                    const procText = Array.from(info.querySelectorAll('div')).find(d => d.textContent.trim() === 'Processing…');
                    if (procText) procText.remove();
                    const url = data.video_url;
                    const div = document.createElement('div');
                    div.style.cssText = 'display:flex;align-items:center;gap:10px;margin-top:6px';
                    div.innerHTML = `
                        <div class="video-thumb-wrap" onclick="openVideo(${JSON.stringify(url)})">
                            <video src="${url}#t=0.5" preload="metadata" muted playsinline style="pointer-events:none;width:120px;height:68px;object-fit:cover;border-radius:6px"></video>
                            <div class="play-overlay">▶</div>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:6px">
                            <button class="btn btn-primary btn-sm" onclick="openVideo(${JSON.stringify(url)})">▶ Preview</button>
                            <a href="${url}" download class="btn btn-ghost btn-sm">⬇ Download</a>
                        </div>`;
                    info.appendChild(div);
                }
                pendingJobs.splice(pendingJobs.indexOf(id), 1);
            } else if (['failed','refunded'].includes(data.status)) {
                pendingJobs.splice(pendingJobs.indexOf(id), 1);
            }
        })
        .catch(() => {});
    });
}

if (pendingJobs.length) {
    setInterval(() => pollJobs([...pendingJobs]), 8000);
    pollJobs([...pendingJobs]);
}

// ── Debug panel ────────────────────────────────────────────────────────────────
function toggleAvatarDebug() {
    const wrap = document.getElementById('avatarDebugWrap');
    wrap.style.display = wrap.style.display === 'none' ? 'block' : 'none';
}

function alog(msg, color) {
    const log = document.getElementById('avatarDebugLog');
    const line = document.createElement('div');
    line.style.color = color || '#94a3b8';
    const ts = new Date().toLocaleTimeString();
    line.textContent = `[${ts}] ${msg}`;
    log.appendChild(line);
    log.scrollTop = log.scrollHeight;
}

function alogJson(label, obj, color) {
    alog(label, color || '#64b5f6');
    const lines = JSON.stringify(obj, null, 2).split('\n');
    lines.forEach(l => alog('  ' + l, '#475569'));
}

async function runAvatarDebug() {
    const log = document.getElementById('avatarDebugLog');
    log.innerHTML = '';
    alog('Running diagnostics…', '#facc15');

    try {
        const resp = await fetch('<?= BASE_URL ?>/client/avatar.php?_action=debug_test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken,
            },
            body: '<?= CSRF_TOKEN_NAME ?>=<?= csrf_token() ?>',
        });

        if (!resp.ok) {
            const txt = await resp.text();
            alog(`HTTP ${resp.status}: ${txt.slice(0,200)}`, '#ef4444');
            return;
        }

        const data = await resp.json();
        const d = data.debug;

        // Config
        alog('── Configuration ─────────────────────', '#facc15');
        Object.entries(d.config).forEach(([k, v]) => {
            const ok = !v.includes('NOT SET');
            alog(`  ${k}: ${v}`, ok ? '#a8e063' : '#ef4444');
        });

        // Directories
        alog('── Upload Directories ────────────────', '#facc15');
        Object.entries(d.dirs).forEach(([k, v]) => {
            alog(`  ${k}: ${v}`, v.includes('OK') ? '#a8e063' : '#ef4444');
        });

        // API test
        alog('── API Connectivity Test ─────────────', '#facc15');
        if (typeof d.api_test === 'string') {
            alog('  ' + d.api_test, '#f59e0b');
        } else {
            alog(`  URL: ${d.api_test.url}`, '#94a3b8');
            alog(`  HTTP code: ${d.api_test.http_code}`, d.api_test.http_code === 0 ? '#ef4444' : '#a8e063');
            if (d.api_test.curl_error !== 'none') {
                alog(`  cURL error: ${d.api_test.curl_error}`, '#ef4444');
            }
            alog('  Raw response:', '#94a3b8');
            try {
                const parsed = JSON.parse(d.api_test.raw_response);
                JSON.stringify(parsed, null, 2).split('\n').forEach(l => alog('    ' + l, '#475569'));
            } catch(_) {
                alog('    ' + d.api_test.raw_response.slice(0, 500), '#475569');
            }
        }

        // Last job
        alog('── Last Job ──────────────────────────', '#facc15');
        if (typeof d.last_job === 'string') {
            alog('  ' + d.last_job, '#94a3b8');
        } else {
            alog(`  Job #${d.last_job.id}  status: ${d.last_job.status}`, '#94a3b8');
            alog(`  error_message: ${d.last_job.error_message}`,
                 d.last_job.error_message === '(none)' ? '#a8e063' : '#ef4444');
            if (d.last_job.api_response) {
                alog('  api_response:', '#94a3b8');
                JSON.stringify(d.last_job.api_response, null, 2)
                    .split('\n').forEach(l => alog('    ' + l, '#475569'));
            }
        }

        alog('── Done ──────────────────────────────', '#facc15');

    } catch(e) {
        alog('Fetch error: ' + e.message, '#ef4444');
    }
}

// Also intercept form submission to show what's happening
document.getElementById('avatarForm').addEventListener('submit', function(e) {
    const wrap = document.getElementById('avatarDebugWrap');
    if (wrap.style.display !== 'none') {
        alog('Form submitted — waiting for server response…', '#facc15');
        const fd = new FormData(this);
        alog(`  audio_mode: ${fd.get('audio_mode')}`, '#94a3b8');
        alog(`  duration: ${fd.get('duration')}s`, '#94a3b8');
        alog(`  portrait: ${fd.get('portrait')?.name || 'none'}  (${((fd.get('portrait')?.size||0)/1024).toFixed(0)} KB)`, '#94a3b8');
        alog(`  audio: ${fd.get('audio')?.name || 'none'}  (${((fd.get('audio')?.size||0)/1024).toFixed(0)} KB)`, '#94a3b8');
        alog(`  tts_text: ${(fd.get('tts_text')||'').slice(0,60)}`, '#94a3b8');
    }
});
</script>
</body>
</html>
