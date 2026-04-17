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

/**
 * Resize portrait image to max 1280px and return base64 JPEG string.
 * Keeps payload small so BytePlus doesn't time out on large uploads.
 */
function _avatar_image_to_base64(string $path): ?string
{
    if (!is_file($path) || !is_readable($path)) return null;
    $maxDim = 1280;

    if (function_exists('imagecreatefromstring')) {
        $raw = file_get_contents($path);
        if ($raw === false) return null;
        $src = @imagecreatefromstring($raw);
        if ($src) {
            $w = imagesx($src);
            $h = imagesy($src);
            if ($w > $maxDim || $h > $maxDim) {
                $ratio = min($maxDim / $w, $maxDim / $h);
                $nw = (int)round($w * $ratio);
                $nh = (int)round($h * $ratio);
                $dst = imagecreatetruecolor($nw, $nh);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($src);
                ob_start();
                imagejpeg($dst, null, 88);
                imagedestroy($dst);
                return base64_encode(ob_get_clean());
            }
            imagedestroy($src);
        }
    }
    // GD not available or image already small — encode as-is
    $raw = file_get_contents($path);
    return $raw !== false ? base64_encode($raw) : null;
}

boot_session();
$user = require_auth('/public/login.php');
$uid  = (int)$user['id'];
$pdo  = db();

$balance    = wallet_balance($uid);
$creditCost = (float)(setting('avatar_credit_cost', '5') ?: '5');
$errors     = [];
$jobs       = [];

// ── AJAX: cancel a processing/queued job ──────────────────────────────────────
if (($_GET['_action'] ?? '') === 'cancel') {
    csrf_verify();
    $jobId = (int)($_GET['job_id'] ?? 0);
    $stmt  = $pdo->prepare(
        'SELECT id, status, api_task_id, credit_cost FROM avatar_jobs WHERE id=? AND user_id=?'
    );
    $stmt->execute([$jobId, $uid]);
    $job = $stmt->fetch();
    if (!$job) { json_response(['ok' => false, 'error' => 'Not found'], 404); }

    if (!in_array($job['status'], ['queued', 'processing'])) {
        json_response(['ok' => false, 'error' => 'Job is not in a cancellable state.']);
    }

    // Best-effort: tell BytePlus to cancel the remote task
    if ($job['api_task_id']) {
        $cancelUrl  = rtrim(setting('vision_ai_url', VISION_AI_URL) ?: VISION_AI_URL, '/');
        $cancelUrl .= '/?Action=CVCancelTask&Version=2024-06-06';
        $reqKey     = setting('omnihuman_req_key', OMNIHUMAN_REQ_KEY) ?: OMNIHUMAN_REQ_KEY;
        vision_post($cancelUrl,
            ['req_key' => $reqKey, 'task_id' => $job['api_task_id']],
            setting('vision_ai_ak', VISION_AI_AK) ?: VISION_AI_AK,
            setting('vision_ai_sk', VISION_AI_SK) ?: VISION_AI_SK
        ); // ignore result — always refund locally
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE avatar_jobs SET status="refunded", error_message="Cancelled by user", refunded_at=NOW() WHERE id=?'
        )->execute([$jobId]);
        wallet_refund($uid, (float)$job['credit_cost'], 'avatar_job', $jobId, 'Refund: avatar job #' . $jobId . ' cancelled');
        $pdo->commit();
        json_response(['ok' => true]);
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_response(['ok' => false, 'error' => 'DB error: ' . $e->getMessage()], 500);
    }
}

// ── AJAX: delete a job record ──────────────────────────────────────────────────
if (($_GET['_action'] ?? '') === 'delete') {
    csrf_verify();
    $jobId = (int)($_GET['job_id'] ?? 0);
    $stmt  = $pdo->prepare(
        'SELECT id, status, portrait_path, audio_path FROM avatar_jobs WHERE id=? AND user_id=?'
    );
    $stmt->execute([$jobId, $uid]);
    $job = $stmt->fetch();
    if (!$job) { json_response(['ok' => false, 'error' => 'Not found'], 404); }

    if (in_array($job['status'], ['queued', 'processing'])) {
        json_response(['ok' => false, 'error' => 'Cancel the job before deleting.']);
    }

    // Delete uploaded files
    if ($job['portrait_path'] && is_file($job['portrait_path'])) @unlink($job['portrait_path']);
    if ($job['audio_path']    && is_file($job['audio_path']))    @unlink($job['audio_path']);

    $pdo->prepare('DELETE FROM avatar_jobs WHERE id=? AND user_id=?')->execute([$jobId, $uid]);
    json_response(['ok' => true]);
}


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
                } elseif ($result['status'] === 'completed' && !$result['video_url']) {
                    // Done but no video_url — log raw and treat as failed
                    $errMsg = 'Task completed but no video URL returned. Raw: ' . json_encode($result['raw']);
                    $pdo->prepare(
                        'UPDATE avatar_jobs SET status="failed", error_message=?, api_response=? WHERE id=?'
                    )->execute([$errMsg, json_encode($result['raw']), $jobId]);
                    wallet_refund($uid, $creditCost, 'avatar_job', $jobId, 'Refund: no video URL');
                    $pdo->prepare('UPDATE avatar_jobs SET status="refunded", refunded_at=NOW() WHERE id=?')->execute([$jobId]);
                    $job['status'] = 'refunded';
                    $job['error_message'] = $errMsg;
                } elseif ($result['status'] === 'failed') {
                    $errMsg = $result['error'] ?: ('OmniHuman failed. Raw: ' . json_encode($result['raw']));
                    $pdo->prepare(
                        'UPDATE avatar_jobs SET status="failed", error_message=?, api_response=? WHERE id=?'
                    )->execute([$errMsg, json_encode($result['raw']), $jobId]);
                    wallet_refund($uid, $creditCost, 'avatar_job', $jobId,
                        'Refund: avatar job #' . $jobId . ' failed');
                    $pdo->prepare(
                        'UPDATE avatar_jobs SET status="refunded", refunded_at=NOW() WHERE id=?'
                    )->execute([$jobId]);
                    $job['status'] = 'refunded';
                    $job['error_message'] = $errMsg;
                } else {
                    $pdo->prepare(
                        'UPDATE avatar_jobs SET status=? WHERE id=? AND status != "completed"'
                    )->execute([$result['status'], $jobId]);
                    $job['status'] = $result['status'];
                }
            } else {
                // API query itself failed (network error, auth error, or BytePlus rejected the query).
                // Only auto-fail on permanent BytePlus error codes — transient errors (DNS, timeout)
                // leave the job untouched so the next poll can retry.
                $apiErrCode = (int)($result['raw']['code'] ?? $result['raw']['status'] ?? 0);
                $permanentCodes = [
                    50215, // Input invalid for this service (task submitted with bad params)
                    50204, // Task not found / expired
                    50200, // req_key not supported
                ];
                if (in_array($apiErrCode, $permanentCodes, true)) {
                    $errMsg = 'BytePlus rejected task (code ' . $apiErrCode . '): '
                            . ($result['error'] ?? 'permanent API error') . '. Credits refunded.';
                    $pdo->prepare(
                        'UPDATE avatar_jobs SET status="failed", error_message=?, api_response=? WHERE id=?'
                    )->execute([$errMsg, json_encode($result['raw']), $jobId]);
                    wallet_refund($uid, $creditCost, 'avatar_job', $jobId,
                        'Refund: avatar job #' . $jobId . ' — code ' . $apiErrCode);
                    $pdo->prepare(
                        'UPDATE avatar_jobs SET status="refunded", refunded_at=NOW() WHERE id=?'
                    )->execute([$jobId]);
                    $job['status']        = 'refunded';
                    $job['error_message'] = $errMsg;
                }
                // else: transient error — leave status unchanged, will retry next poll
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
                // cv.byteplusapi.com ONLY accepts image_url / audio_url (not base64).
                // Files must be publicly reachable by BytePlus servers.
                $portraitName = basename($portraitPath);
                $imageUrl     = rtrim(BASE_URL, '/') . '/uploads/avatars/' . rawurlencode($portraitName);
                $audioUrl     = null;
                if ($audioPath) {
                    $audioUrl = rtrim(BASE_URL, '/') . '/uploads/avatar_audio/' . rawurlencode(basename($audioPath));
                }

                // OmniHuman 1.5 (realman_avatar_picture_omni15_cv) only accepts:
                //   req_key, image_url, audio_url (or text for TTS).
                // Do NOT send extra fields like output_resolution — they are not
                // documented for this req_key and cause BytePlus to return code 50215.
                $apiResult = omnihuman_create_task(
                    '',     // imageBase64 — not supported by this req_key
                    null,   // audioBase64 — not supported by this req_key
                    $audioMode === 'tts' ? $ttsText : null,
                    [],     // No extra params — undocumented fields cause 50215
                    $imageUrl,
                    $audioUrl
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
                    flash_success('Avatar video submitted! Generation typically takes 5–20 minutes for 10s videos. Stay on this page or check back later.');
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

// ── Debug action: comprehensive connectivity diagnostics ─────────────────────
// Must run BEFORE any HTML is output so json_response() can set headers cleanly.
if (($_GET['_action'] ?? '') === 'debug_test') {
    csrf_verify();
    [$ak, $sk, $apiBase, $reqKey] = _omnihuman_creds();

    $results = [];

    // ── 1. Config: what is actually being used (DB vs constant fallback) ────────
    $dbUrl    = setting('vision_ai_url', '');
    $results['config'] = [
        'source_url'         => $dbUrl ? "DB: $dbUrl" : ('constant: ' . VISION_AI_URL),
        'active_url'         => $apiBase ?: 'NOT SET',
        'vision_ai_ak'       => $ak  ? ('set (' . substr($ak, 0, 4) . '…)') : 'NOT SET',
        'vision_ai_sk'       => $sk  ? 'set (***)' : 'NOT SET',
        'omnihuman_req_key'  => $reqKey ?: 'NOT SET',
        'VISION_AI_URL_const'=> VISION_AI_URL,
        'db_vision_ai_url'   => $dbUrl ?: '(empty — using constant)',
    ];

    // ── 2. DNS resolution test ───────────────────────────────────────────────────
    $host = parse_url($apiBase ?: VISION_AI_URL, PHP_URL_HOST) ?: '';
    $ip   = gethostbyname($host);
    $results['dns'] = [
        'hostname'  => $host,
        'resolved'  => ($ip !== $host) ? "OK → $ip" : 'FAILED (no DNS record or blocked)',
    ];

    $altHosts = [
        'cv.byteplusapi.com',
        'visual.ap-singapore-1.byteplus.com',
        'visual.byteplus.com',
        'open.byteplus.com',
        'visual.volcengineapi.com',
        'visual.ap-singapore-1.volcengineapi.com',
    ];
    $dnsAlts = [];
    foreach ($altHosts as $h) {
        $r = gethostbyname($h);
        $dnsAlts[$h] = ($r !== $h) ? "✓ resolves → $r" : '✗ no DNS';
    }
    $results['dns_alternatives'] = $dnsAlts;

    // ── 2b. Direct DNS check for cv.byteplusapi.com (the correct BytePlus endpoint) ─
    $cvHost = 'cv.byteplusapi.com';
    $cvIp   = gethostbyname($cvHost);
    $cvResolved = ($cvIp !== $cvHost);
    $results['cv_byteplusapi_dns'] = [
        'hostname' => $cvHost,
        'resolved' => $cvResolved ? "✓ OK → $cvIp" : '✗ FAILED (not in server DNS)',
    ];

    // If server DNS resolves cv.byteplusapi.com, probe it directly.
    // Use CVGetResult with a dummy task_id — verifies auth + connectivity without creating a real task.
    if ($cvResolved && $ak && $sk) {
        $cvUrl   = 'https://cv.byteplusapi.com/?Action=CVGetResult&Version=2024-06-06';
        $cvBody  = json_encode(['req_key' => $reqKey, 'task_id' => 'probe_connectivity_test'], JSON_UNESCAPED_SLASHES);
        $cvHdrs  = volcengine_v4_headers('POST', $cvHost, '/', 'Action=CVGetResult&Version=2024-06-06', $cvBody, $ak, $sk, 'ap-singapore-1', 'cv');
        $cvLines = array_map(fn($k,$v) => "$k: $v", array_keys($cvHdrs), array_values($cvHdrs));
        $cvCh = curl_init($cvUrl);
        curl_setopt_array($cvCh, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>12,
            CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$cvBody, CURLOPT_HTTPHEADER=>$cvLines,
            CURLOPT_SSL_VERIFYPEER=>true]);
        $cvResp = curl_exec($cvCh);
        $cvCode = curl_getinfo($cvCh, CURLINFO_HTTP_CODE);
        $cvErr  = curl_error($cvCh);
        curl_close($cvCh);
        $cvDecoded = json_decode($cvResp ?: '', true, 512, JSON_BIGINT_AS_STRING) ?? [];
        $cvMsg     = $cvDecoded['message'] ?? ($cvDecoded['ResponseMetadata']['Error']['Message'] ?? '');
        $cvApiCode = (int)($cvDecoded['code'] ?? 0);
        $results['cv_byteplusapi_probe'] = [
            'url'       => $cvUrl,
            'http_code' => $cvCode ?: 0,
            'curl_error'=> $cvErr ?: 'none',
            'response'  => $cvDecoded ?: ($cvResp ? substr($cvResp, 0, 300) : '(empty)'),
            'diagnosis' => match(true) {
                (bool)$cvErr                                            => "FAILED: $cvErr",
                isset($cvDecoded['ResponseMetadata']['Error'])         => 'AUTH/SIGN ERROR: ' . ($cvDecoded['ResponseMetadata']['Error']['Message'] ?? 'check AK/SK'),
                $cvCode === 401 || $cvCode === 403                     => 'Reachable but auth rejected — check AK/SK',
                // "task not found" with dummy id = endpoint + auth working perfectly
                stripos($cvMsg, 'not exist') !== false                 => '✓ SUCCESS — endpoint + auth OK (dummy task_id not found, expected)',
                stripos($cvMsg, 'not found') !== false                 => '✓ SUCCESS — endpoint + auth OK (dummy task_id not found, expected)',
                stripos($cvMsg, 'task') !== false                      => '✓ SUCCESS — endpoint + auth OK',
                $cvApiCode === 10000                                   => '✓ SUCCESS — endpoint fully working',
                $cvCode === 200                                        => '✓ OK — HTTP 200',
                default                                                => "HTTP $cvCode: $cvMsg",
            },
        ];
    }

    // ── 2c. DoH lookup for cv.byteplusapi.com + old visual.ap-singapore-1.byteplus.com ─
    $dohChecks = [
        'cv.byteplusapi.com'                  => [],
        'visual.ap-singapore-1.byteplus.com'  => [],
    ];
    foreach ($dohChecks as $byteplusHost => $_) {
        $dohProviders = [
            'Google'     => 'https://dns.google/resolve?name=' . urlencode($byteplusHost) . '&type=A',
            'Cloudflare' => 'https://cloudflare-dns.com/dns-query?name=' . urlencode($byteplusHost) . '&type=A',
        ];
        $dohIps = [];
        $dohResults = [];
        foreach ($dohProviders as $provider => $dohUrl) {
            $dohCh = curl_init($dohUrl);
            curl_setopt_array($dohCh, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>6,
                CURLOPT_HTTPHEADER=>['accept: application/dns-json'], CURLOPT_SSL_VERIFYPEER=>true]);
            $dohResp = curl_exec($dohCh);
            curl_close($dohCh);
            $dohData = json_decode($dohResp ?: '', true);
            $provIps = [];
            foreach (($dohData['Answer'] ?? []) as $rec) {
                if (($rec['type'] ?? 0) === 1) { $provIps[] = $rec['data']; $dohIps[] = $rec['data']; }
            }
            $cname = '';
            foreach (($dohData['Answer'] ?? []) as $rec) {
                if (($rec['type'] ?? 0) === 5) { $cname = ' → CNAME: ' . $rec['data']; break; }
            }
            $dohResults[$provider] = $provIps ? ('✓ ' . implode(', ', $provIps)) : ('✗ no record' . $cname);
        }
        $dohIps = array_unique($dohIps);
        $dohChecks[$byteplusHost] = array_merge(['hostname' => $byteplusHost], $dohResults);

        // If cv.byteplusapi.com resolved via DoH but not server DNS, try IP test
        if ($dohIps && $byteplusHost === 'cv.byteplusapi.com' && !$cvResolved && $ak && $sk) {
            $ip     = $dohIps[0];
            $cvUrl  = 'https://cv.byteplusapi.com/?Action=CVGetResult&Version=2024-06-06';
            $cvBody = json_encode(['req_key' => $reqKey, 'task_id' => 'probe_connectivity_test'], JSON_UNESCAPED_SLASHES);
            $cvHdrs = volcengine_v4_headers('POST', 'cv.byteplusapi.com', '/', 'Action=CVGetResult&Version=2024-06-06', $cvBody, $ak, $sk, 'ap-singapore-1', 'cv');
            $cvLines = array_map(fn($k,$v) => "$k: $v", array_keys($cvHdrs), array_values($cvHdrs));
            $cvCh2 = curl_init($cvUrl);
            curl_setopt_array($cvCh2, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>12,
                CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$cvBody, CURLOPT_HTTPHEADER=>$cvLines,
                CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_RESOLVE=>["cv.byteplusapi.com:443:$ip"]]);
            $cvResp2 = curl_exec($cvCh2);
            $cvCode2 = curl_getinfo($cvCh2, CURLINFO_HTTP_CODE);
            $cvErr2  = curl_error($cvCh2);
            curl_close($cvCh2);
            $cvDec2 = json_decode($cvResp2 ?: '', true, 512, JSON_BIGINT_AS_STRING) ?? [];
            $cvMsg2 = $cvDec2['message'] ?? ($cvDec2['ResponseMetadata']['Error']['Message'] ?? '');
            $results['cv_byteplusapi_doh_ip_test'] = [
                'ip_used'    => $ip,
                'http_code'  => $cvCode2 ?: 0,
                'curl_error' => $cvErr2 ?: 'none',
                'response'   => $cvDec2 ?: ($cvResp2 ? substr($cvResp2, 0, 300) : '(empty)'),
                'diagnosis'  => match(true) {
                    (bool)$cvErr2                            => "FAILED: $cvErr2",
                    ($cvDec2['code'] ?? 0) == 10000         => '✓ SUCCESS — set vision_ai_dns_override=' . $ip,
                    str_contains($cvMsg2, 'req_key')        => "req_key issue (reachable!): $cvMsg2",
                    $cvCode2 === 400                        => '✓ REACHABLE via IP — set vision_ai_dns_override=' . $ip,
                    $cvCode2 === 401 || $cvCode2 === 403    => 'Auth rejected via IP — check AK/SK',
                    default                                 => "HTTP $cvCode2: $cvMsg2",
                },
            ];
        }
    }
    $results['byteplus_doh_lookup'] = $dohChecks;

    // ── 3. Upload directories ────────────────────────────────────────────────────
    $avatarDir = BASE_PATH . '/uploads/avatars';
    $audioDir  = BASE_PATH . '/uploads/avatar_audio';
    foreach ([$avatarDir => 'uploads/avatars', $audioDir => 'uploads/avatar_audio'] as $path => $label) {
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
            $results['dirs'][$label] = is_dir($path) ? 'created OK' : 'MISSING (could not create)';
        } else {
            $results['dirs'][$label] = is_writable($path) ? 'OK (writable)' : 'EXISTS but not writable';
        }
    }

    // ── 3b. Test whether uploaded files are publicly reachable by BytePlus ────────
    // BytePlus fetches image_url / audio_url from its own servers.
    // If those URLs return 403/404, BytePlus silently marks the task as invalid (code 50215).
    $lastJobForUrlTest = $pdo->prepare(
        'SELECT portrait_path, audio_path FROM avatar_jobs WHERE user_id=? ORDER BY id DESC LIMIT 1'
    );
    $lastJobForUrlTest->execute([$uid]);
    $lastJobRow = $lastJobForUrlTest->fetch();
    if ($lastJobRow) {
        $testUrls = [];
        if ($lastJobRow['portrait_path']) {
            $pName = basename($lastJobRow['portrait_path']);
            $testUrls['portrait_url'] = rtrim(BASE_URL, '/') . '/uploads/avatars/' . rawurlencode($pName);
        }
        if ($lastJobRow['audio_path']) {
            $aName = basename($lastJobRow['audio_path']);
            $testUrls['audio_url'] = rtrim(BASE_URL, '/') . '/uploads/avatar_audio/' . rawurlencode($aName);
        }
        foreach ($testUrls as $label => $testUrl) {
            $tCh = curl_init($testUrl);
            curl_setopt_array($tCh, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_NOBODY         => true,   // HEAD-like: no body download
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_SSL_VERIFYPEER => false,  // test reachability, not cert
            ]);
            curl_exec($tCh);
            $tCode = curl_getinfo($tCh, CURLINFO_HTTP_CODE);
            $tErr  = curl_error($tCh);
            curl_close($tCh);
            $results['url_accessibility'][$label] = [
                'url'       => $testUrl,
                'http_code' => $tCode,
                'error'     => $tErr ?: 'none',
                'diagnosis' => match(true) {
                    (bool)$tErr               => 'NETWORK ERROR: ' . $tErr . ' — BytePlus cannot fetch this file',
                    $tCode === 200            => '✓ Publicly accessible (HTTP 200)',
                    $tCode === 403            => 'BLOCKED (HTTP 403) — directory not public; BytePlus will get 50215',
                    $tCode === 404            => 'NOT FOUND (HTTP 404) — file missing; BytePlus will get 50215',
                    $tCode === 401            => 'AUTH REQUIRED (HTTP 401) — protect removed or add allow rule',
                    $tCode >= 300 && $tCode < 400 => "REDIRECT ($tCode) — may work if BytePlus follows redirects",
                    $tCode === 0              => 'NO RESPONSE — server or DNS unreachable from this host',
                    default                   => "HTTP $tCode — unexpected; BytePlus may reject task",
                },
            ];
        }
    } else {
        $results['url_accessibility'] = 'No jobs yet — submit a job first to test URL accessibility';
    }

    // ── 4. Basic TCP connect test (port 443) ─────────────────────────────────────
    if ($host) {
        $fp = @fsockopen('ssl://' . $host, 443, $errno, $errstr, 8);
        $results['tcp_connect'] = $fp
            ? 'OK — TCP+TLS to port 443 succeeded'
            : "FAILED (errno=$errno): $errstr";
        if ($fp) fclose($fp);
    }

    // ── 5. API probe — test generate URL with correct signing ────────────────────
    if ($ak && $sk && $apiBase) {
        // Use CVGetResult with a dummy task_id — proves auth + connectivity without creating a real task.
        $testPayload = ['req_key' => $reqKey, 'task_id' => 'probe_connectivity_test'];
        $probeUrl    = _omnihuman_url($apiBase, 'query');
        $body        = json_encode($testPayload, JSON_UNESCAPED_SLASHES);

        if (_is_volcengine_host($apiBase)) {
            $host    = parse_url($probeUrl, PHP_URL_HOST);
            $path    = parse_url($probeUrl, PHP_URL_PATH) ?? '/';
            $query   = parse_url($probeUrl, PHP_URL_QUERY) ?? '';
            $region  = setting('vision_ai_region',  'ap-singapore-1') ?: 'ap-singapore-1';
            $service = setting('vision_ai_service',  'cv')             ?: 'cv';
            $hdrs    = volcengine_v4_headers('POST', $host, $path, $query, $body, $ak, $sk, $region, $service);
            $signing = "Volcengine V4 (service=$service, region=$region)";
        } else {
            $hdrs    = vision_signed_headers('POST', parse_url($probeUrl, PHP_URL_PATH) ?? '/', $body, $ak, $sk);
            $signing = 'BytePlus HMAC256';
        }
        $hLines = array_map(fn($k,$v) => "$k: $v", array_keys($hdrs), array_values($hdrs));

        $ch = curl_init($probeUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $hLines,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp    = curl_exec($ch);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        $curlNo  = curl_errno($ch);
        curl_close($ch);

        $decoded = $resp ? (json_decode($resp, true, 512, JSON_BIGINT_AS_STRING) ?? $resp) : '(empty)';
        $volErr  = is_array($decoded) ? ($decoded['ResponseMetadata']['Error'] ?? null) : null;

        $results['api_probe'] = [
            'url'          => $probeUrl,
            'signing'      => $signing,
            'http_code'    => $code ?: 0,
            'curl_error'   => $curlErr ?: 'none',
            'response'     => $decoded,
            'diagnosis'    => match(true) {
                (bool)$curlErr && str_contains($curlErr, 'resolve')                    => 'DNS FAILURE',
                (bool)$curlErr && str_contains($curlErr, 'timed out')                  => 'TIMEOUT — server unreachable',
                $volErr && ($volErr['Code'] ?? '') === 'ServiceNotFound'                => 'WRONG SERVICE NAME — change vision_ai_service in Admin→Settings (try: cv, imagex, dreamina)',
                $volErr && ($volErr['Code'] ?? '') === 'InvalidAction'                  => 'WRONG ACTION NAME — change omnihuman_action_query in Admin→Settings',
                $volErr && str_contains($volErr['Code'] ?? '', 'Auth')                 => 'AUTH ERROR — check AK/SK',
                $volErr && str_contains($volErr['Code'] ?? '', 'SignatureDoesNotMatch') => 'SIGNATURE ERROR — AK/SK mismatch or clock skew',
                $volErr                                                                 => 'API ERROR: ' . ($volErr['Message'] ?? $volErr['Code'] ?? '?'),
                $code === 401 || $code === 403                                          => 'AUTH ERROR — AK/SK rejected',
                // "task not found" with dummy id = endpoint + auth working perfectly (no task created)
                $code === 200 && is_array($decoded) && stripos($decoded['message'] ?? '', 'not') !== false => 'HTTP 200 ✓ SUCCESS — endpoint + auth OK',
                $code === 200                                                           => 'HTTP 200 ✓ SUCCESS — endpoint fully working',
                $code >= 500                                                            => "SERVER ERROR $code",
                default                                                                 => $curlErr ? "cURL: $curlErr" : "HTTP $code",
            },
        ];

        // ── 5b. Multi req_key scan (only for Volcengine/BytePlus, only when req_key is unsupported) ─
        $reqKeyUnsupported = isset($decoded['code']) && $decoded['code'] == 50200
            && str_contains($decoded['message'] ?? '', 'not supported');

        if (_is_volcengine_host($apiBase) && $reqKeyUnsupported) {
            $region  = setting('vision_ai_region',  'ap-singapore-1') ?: 'ap-singapore-1';
            $service = setting('vision_ai_service',  'cv')             ?: 'cv';
            $scanHostN = parse_url($apiBase, PHP_URL_HOST);

            // Candidates: all combos of CVSubmitTask + realman_avatar_* + legacy dreamina_* names.
            // The docs confirm realman_avatar_picture_create_role_omni_cv is the Step-1 req_key;
            // Step-2 video generation likely uses a similar realman_avatar_video_* req_key.
            // Each candidate carries:
            //   body_key: which JSON field the API expects for the image (image_url or image_base64)
            // req_key=realman_avatar_picture_create_role_omni_cv is confirmed as Step-1
            // (subject recognition only). We need to find the Step-2 video generation key.
            // Scan ALL candidates and report every result — do NOT stop on Step-1 success.
            // Video-gen payload includes both image + audio fields since that's required.
            // Helper closure to build a video-gen payload with image_url + audio/text
            $vPay = fn(array $extra = []) => array_merge([
                'image_url' => 'https://www.gstatic.com/webp/gallery/1.jpg',
                'audio_url' => '',          // empty — API may still accept req_key if not null
                'text'      => 'hello world',
            ], $extra);
            $candidates = [
                // ── CONFIRMED from official BytePlus docs (Video Generation, Step-4) ──
                ['req_key' => 'realman_avatar_picture_omni15_cv',
                 'label' => '⭐ OmniHuman 1.5 Video Gen (official docs)',
                 'payload' => $vPay()],

                // ── Step-1 only (confirmed from docs) — keep to show API access works ──
                ['req_key' => 'realman_avatar_picture_create_role_omni_cv',
                 'label' => '(Step-1 subject detect — confirmed)',
                 'payload' => ['image_url' => 'https://www.gstatic.com/webp/gallery/1.jpg']],

                // ── Batch A: realman_avatar_video_* (with / without "role") ──
                ['req_key' => 'realman_avatar_video_create_role_omni_cv',  'label' => '(video create role?)', 'payload' => $vPay()],
                ['req_key' => 'realman_avatar_video_generate_role_omni_cv','label' => '(video gen role?)',    'payload' => $vPay()],
                ['req_key' => 'realman_avatar_video_omni_cv',              'label' => '(video bare?)',        'payload' => $vPay()],
                ['req_key' => 'realman_avatar_video_create_omni_cv',       'label' => '(video create norl?)', 'payload' => $vPay()],
                ['req_key' => 'realman_avatar_video_generate_omni_cv',     'label' => '(video gen norl?)',    'payload' => $vPay()],

                // ── Batch B: realman_omni_human_* variants ──
                ['req_key' => 'realman_omni_human_v1_5',                   'label' => '(omni 1.5?)',          'payload' => $vPay()],
                ['req_key' => 'realman_omni_human',                        'label' => '(omni bare?)',         'payload' => $vPay()],
                ['req_key' => 'realman_omni_human_video_v1_5',             'label' => '(omni video 1.5?)',    'payload' => $vPay()],
                ['req_key' => 'realman_omni_human_video',                  'label' => '(omni video bare?)',   'payload' => $vPay()],

                // ── Batch C: talking-head / lipsync patterns ──
                ['req_key' => 'realman_avatar_talking_head_omni_cv',       'label' => '(talking head?)',      'payload' => $vPay()],
                ['req_key' => 'realman_avatar_lipsync_omni_cv',            'label' => '(lipsync?)',           'payload' => $vPay()],
                ['req_key' => 'realman_avatar_talk_omni_cv',               'label' => '(talk?)',              'payload' => $vPay()],
                ['req_key' => 'realman_avatar_animation_role_omni_cv',     'label' => '(animation role?)',    'payload' => $vPay()],
                ['req_key' => 'realman_portrait_animation_omni_cv',        'label' => '(portrait anim?)',     'payload' => $vPay()],

                // ── Batch D: dreamina_* legacy names ──
                ['req_key' => 'dreamina_omni_human_v1_5',                  'label' => '(dreamina 1.5?)',      'payload' => $vPay()],
                ['req_key' => 'dreamina_omni_human',                       'label' => '(dreamina bare?)',     'payload' => $vPay()],
                ['req_key' => 'dreamina_avatar_video_create_role',         'label' => '(dreamina vid role?)', 'payload' => $vPay()],
                ['req_key' => 'dreamina_avatar_talking_head',              'label' => '(dreamina talk hd?)',  'payload' => $vPay()],
                ['req_key' => 'dreamina_lipsync',                          'label' => '(dreamina lipsync?)',  'payload' => $vPay()],

                // ── Batch E: shorter / alternate naming ──
                ['req_key' => 'omni_human_video',                          'label' => '(omni_human_video?)',  'payload' => $vPay()],
                ['req_key' => 'omni_human_v1_5',                           'label' => '(omni_human_v1_5?)',   'payload' => $vPay()],
                ['req_key' => 'avatar_video_create',                       'label' => '(avatar_video_creat?)','payload' => $vPay()],
                ['req_key' => 'cv_talking_head',                           'label' => '(cv_talking_head?)',   'payload' => $vPay()],
                ['req_key' => 'cv_omni_human',                             'label' => '(cv_omni_human?)',     'payload' => $vPay()],
            ];
            $scanVersion = str_contains($scanHostN, 'byteplusapi.com') ? '2024-06-06' : '2022-08-31';
            $scanResults = [];
            foreach ($candidates as $c) {
                $scanUrl  = rtrim($apiBase, '/') . '/?Action=CVSubmitTask&Version=' . $scanVersion;
                $scanBody = json_encode(
                    array_merge(['req_key' => $c['req_key']], $c['payload']),
                    JSON_UNESCAPED_SLASHES
                );
                $scanQ    = 'Action=CVSubmitTask&Version=' . $scanVersion;
                $scanHdrs = volcengine_v4_headers('POST', $scanHostN, '/', $scanQ, $scanBody, $ak, $sk, $region, $service);
                $scanLines = array_map(fn($k,$v) => "$k: $v", array_keys($scanHdrs), array_values($scanHdrs));

                $ch2 = curl_init($scanUrl);
                curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15,
                    CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$scanBody, CURLOPT_HTTPHEADER=>$scanLines,
                    CURLOPT_SSL_VERIFYPEER=>true]);
                $sResp = curl_exec($ch2);
                $sCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                curl_close($ch2);

                $sDecoded  = json_decode($sResp ?: '', true, 512, JSON_BIGINT_AS_STRING) ?? [];
                $sMsg      = $sDecoded['message']
                             ?? $sDecoded['ResponseMetadata']['Error']['Message']
                             ?? ($sDecoded['error']['message'] ?? '');
                $sApiCode  = (int)($sDecoded['code'] ?? 0);
                // "not supported" = req_key unrecognised; all other errors = req_key might be valid
                $notSupp      = stripos($sMsg, 'not supported') !== false
                             || stripos($sMsg, 'req_key') !== false && stripos($sMsg, 'not') !== false;
                $inputInvalid = stripos($sMsg, 'Input invalid') !== false
                             || stripos($sMsg, 'param') !== false
                             || $sApiCode === 50215;
                $unactivated  = stripos($sMsg, 'not activated') !== false
                             || stripos($sMsg, 'not enable') !== false
                             || stripos($sMsg, 'permission') !== false
                             || stripos($sMsg, 'not open') !== false;

                $sLabel = match(true) {
                    $sApiCode === 10000   => '✓ TASK CREATED — use this req_key!',
                    $sCode === 429        => '⏱ rate-limited (429) — req_key may be valid, retry later',
                    $notSupp             => '✗ not supported (req_key unknown)',
                    $unactivated         => '⚠ req_key exists but service NOT ACTIVATED in console',
                    $inputInvalid        => '✓ ACCEPTED — req_key valid (input rejected, not req_key error)',
                    isset($sDecoded['ResponseMetadata']['Error'])
                                         => '✗ ' . ($sDecoded['ResponseMetadata']['Error']['Code'] ?? 'error'),
                    default              => "HTTP $sCode: " . mb_substr($sMsg, 0, 80),
                };
                usleep(300000); // 300ms between candidates to avoid 429 concurrency limit

                $scanResults[] = [
                    'req_key' => $c['req_key'],
                    'label'   => $c['label'] ?? '',
                    'result'  => $sLabel,
                    'code'    => $sApiCode,
                    'msg'     => mb_substr($sMsg, 0, 120),
                ];
            }
            $results['req_key_scan'] = $scanResults;
        }
    } else {
        $results['api_probe'] = 'SKIPPED — AK, SK or URL not configured in DB settings';
    }

    // ── 6. Last job record + live task query ────────────────────────────────────
    $lastJob = $pdo->prepare(
        'SELECT id, status, api_task_id, api_response, error_message, created_at
         FROM avatar_jobs WHERE user_id=? ORDER BY id DESC LIMIT 1'
    );
    $lastJob->execute([$uid]);
    $last = $lastJob->fetch();
    $results['last_job'] = $last ? [
        'id'            => $last['id'],
        'status'        => $last['status'],
        'api_task_id'   => $last['api_task_id'] ?: '(none)',
        'error_message' => $last['error_message'] ?: '(none)',
        'created_at'    => $last['created_at'],
        'api_response'  => $last['api_response'] ? json_decode($last['api_response'], true) : null,
    ] : 'No jobs yet';

    // ── 7. Live query for any processing/queued job ──────────────────────────────
    if ($ak && $sk && $last && $last['api_task_id'] &&
        in_array($last['status'], ['queued', 'processing'])) {
        $liveTaskId  = $last['api_task_id'];
        $liveUrl     = rtrim($apiBase, '/') . '/?Action=CVGetResult&Version=2024-06-06';
        $liveBody    = json_encode(['req_key' => $reqKey, 'task_id' => $liveTaskId], JSON_UNESCAPED_SLASHES);
        $liveQ       = 'Action=CVGetResult&Version=2024-06-06';
        $liveHost    = parse_url($apiBase, PHP_URL_HOST) ?? 'cv.byteplusapi.com';
        $liveHdrs    = volcengine_v4_headers('POST', $liveHost, '/', $liveQ, $liveBody, $ak, $sk, $region ?? 'ap-singapore-1', $service ?? 'cv');
        $liveLines   = array_map(fn($k,$v) => "$k: $v", array_keys($liveHdrs), array_values($liveHdrs));
        $liveCh = curl_init($liveUrl);
        curl_setopt_array($liveCh, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>20,
            CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$liveBody, CURLOPT_HTTPHEADER=>$liveLines,
            CURLOPT_SSL_VERIFYPEER=>true]);
        $liveResp = curl_exec($liveCh);
        $liveCode = curl_getinfo($liveCh, CURLINFO_HTTP_CODE);
        $liveCurlErr = curl_error($liveCh);
        curl_close($liveCh);
        $liveDec  = json_decode($liveResp ?: '', true, 512, JSON_BIGINT_AS_STRING) ?? [];
        $liveData = $liveDec['data'] ?? [];
        // Extract error message from all possible locations
        $liveErrMsg = $liveDec['ResponseMetadata']['Error']['Message']
                   ?? $liveDec['ResponseMetadata']['Error']['Code']
                   ?? $liveDec['message']
                   ?? $liveDec['error']
                   ?? '';
        $results['live_task_query'] = [
            'task_id'      => $liveTaskId,
            'http_code'    => $liveCode,
            'curl_error'   => $liveCurlErr ?: 'none',
            'raw_status'   => $liveData['status'] ?? '(none)',
            'resp_data'    => $liveData['resp_data'] ?? '(none)',
            'error_msg'    => $liveErrMsg,
            'api_code'     => $liveDec['code'] ?? $liveDec['status'] ?? 0,
            'full_response' => $liveDec,
            'raw_body'     => $liveResp ?: '(empty)',
        ];
    }

    json_response(['ok' => true, 'debug' => $results]);
}
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
        .prog-bar { height: 6px; background: var(--color-border); border-radius: 3px; margin: 8px 0 4px; overflow: hidden; }
        .prog-fill { height: 100%; background: linear-gradient(90deg,var(--color-primary),var(--color-accent)); width: 20%; border-radius: 3px; transition: width .6s ease; }
        .prog-steps { display:flex; gap:0; margin: 6px 0 2px; }
        .prog-step { flex:1; text-align:center; font-size:.68rem; font-weight:600; padding:4px 2px; border-radius:4px; opacity:.35; transition: opacity .3s; }
        .prog-step.done  { opacity:1; color:#22c55e; }
        .prog-step.active{ opacity:1; color:var(--color-primary); }
        .prog-elapsed { font-size:.72rem; color:var(--color-muted); margin-top:2px; }
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
                                <div class="prog-steps">
                                    <div class="prog-step <?= $job['status'] === 'queued' ? 'active' : 'done' ?>">✓ Submitted</div>
                                    <div class="prog-step <?= $job['status'] === 'processing' ? 'active' : ($job['status'] === 'queued' ? '' : 'done') ?>">⚙ Rendering</div>
                                    <div class="prog-step">✓ Done</div>
                                </div>
                                <div class="prog-bar">
                                    <div class="prog-fill" id="ajob_bar_<?= (int)$job['id'] ?>"
                                         style="width:<?= $job['status'] === 'processing' ? '55' : '15' ?>%"></div>
                                </div>
                                <div class="prog-elapsed" id="ajob_elapsed_<?= (int)$job['id'] ?>">
                                    Elapsed: <span>0s</span> · typically 5–25 min (10s videos take longer)
                                </div>
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
                    <!-- Action buttons row -->
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:10px;border-top:1px solid var(--color-border);padding-top:10px">
                        <?php if ($inProgress): ?>
                            <button class="btn btn-ghost btn-sm"
                                    style="color:#ef4444;border-color:#ef4444"
                                    onclick="cancelAvatarJob(<?= (int)$job['id'] ?>)">
                                ✕ Cancel &amp; Refund
                            </button>
                        <?php else: ?>
                            <button class="btn btn-ghost btn-sm"
                                    style="color:var(--color-muted)"
                                    onclick="deleteAvatarJob(<?= (int)$job['id'] ?>)">
                                🗑 Delete
                            </button>
                        <?php endif; ?>
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
        fn($j) => ['id' => (int)$j['id'], 'created_at' => $j['created_at']],
        array_filter($jobs, fn($j) => in_array($j['status'], ['queued','processing']))
    ))
) ?>;
const csrfToken = <?= json_encode($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>;

// Elapsed time counters
const jobStartTimes = {};
pendingJobs.forEach(j => {
    jobStartTimes[j.id] = new Date(j.created_at.replace(' ', 'T') + 'Z').getTime();
});

function fmtElapsed(ms) {
    const s = Math.floor(ms / 1000);
    if (s < 60) return s + 's';
    return Math.floor(s / 60) + 'm ' + (s % 60) + 's';
}

// Tick elapsed timers every second + show warning if stuck > 5 min
setInterval(() => {
    pendingJobs.forEach(j => {
        const el = document.getElementById('ajob_elapsed_' + j.id);
        if (!el) return;
        const elapsed = Date.now() - (jobStartTimes[j.id] || Date.now());
        const secs = Math.floor(elapsed / 1000);
        el.querySelector('span').textContent = fmtElapsed(elapsed);
        // Slowly advance the bar (max 88% before complete)
        const bar = document.getElementById('ajob_bar_' + j.id);
        if (bar) {
            const pct = Math.min(88, 15 + secs * 0.6);
            bar.style.width = pct + '%';
        }
        // After 10 minutes show a note (normal for 10s videos)
        if (secs > 600 && !el.dataset.warned) {
            el.dataset.warned = '1';
            el.innerHTML = `<span style="color:#f59e0b">⏳ Still rendering — 10-second videos can take 15–25 min on BytePlus. Keep this page open.</span>`;
        }
        // After 30 minutes auto-stop polling (cron timeout will refund)
        if (secs > 1800) {
            el.innerHTML = `<span style="color:#ef4444">⏱ Timed out after 30 min — credits will be auto-refunded on next cron run. Use Cancel &amp; Refund to refund now.</span>`;
            const idx = pendingJobs.findIndex(pj => pj.id === j.id);
            if (idx !== -1) pendingJobs.splice(idx, 1); // stop polling
        }
    });
}, 1000);

function pollJobs(jobs) {
    if (!jobs.length) return;
    jobs.forEach(job => {
        const id = job.id;
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

            // Update step indicators
            const card = document.getElementById('ajob_' + id);
            if (card && data.status === 'processing') {
                const steps = card.querySelectorAll('.prog-step');
                if (steps.length >= 2) {
                    steps[0].className = 'prog-step done';
                    steps[1].className = 'prog-step active';
                }
            }

            if (data.status === 'completed' && data.video_url) {
                if (card) {
                    const info = card.querySelector('.job-info');
                    // Fill bar to 100%
                    const bar = document.getElementById('ajob_bar_' + id);
                    if (bar) bar.style.width = '100%';
                    // Mark all steps done
                    card.querySelectorAll('.prog-step').forEach(s => s.className = 'prog-step done');

                    setTimeout(() => {
                        // Remove progress UI
                        card.querySelectorAll('.prog-steps,.prog-bar,.prog-elapsed').forEach(e => e.remove());
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
                    }, 600);
                }
                const idx = pendingJobs.findIndex(j => j.id === id);
                if (idx !== -1) pendingJobs.splice(idx, 1);
            } else if (['failed','refunded'].includes(data.status)) {
                if (card) card.querySelectorAll('.prog-steps,.prog-bar,.prog-elapsed').forEach(e => e.remove());
                if (data.error) {
                    const info = card?.querySelector('.job-info');
                    if (info) {
                        const errDiv = document.createElement('div');
                        errDiv.className = 'text-sm';
                        errDiv.style.color = 'var(--color-danger)';
                        errDiv.style.marginTop = '4px';
                        errDiv.textContent = data.error;
                        info.appendChild(errDiv);
                    }
                }
                const idx = pendingJobs.findIndex(j => j.id === id);
                if (idx !== -1) pendingJobs.splice(idx, 1);
            }
        })
        .catch(() => {});
    });
}

if (pendingJobs.length) {
    setInterval(() => pollJobs([...pendingJobs]), 8000);
    pollJobs([...pendingJobs]);
}

// ── Cancel job ────────────────────────────────────────────────────────────────
function cancelAvatarJob(id) {
    if (!confirm('Cancel this job and refund credits?')) return;
    const btn = document.querySelector(`#ajob_${id} button`);
    if (btn) { btn.disabled = true; btn.textContent = 'Cancelling…'; }

    fetch(`<?= BASE_URL ?>/client/avatar.php?_action=cancel&job_id=${id}`, {
        headers: { 'X-CSRF-Token': csrfToken }
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            // Update badge
            const badge = document.getElementById('ajob_status_' + id);
            if (badge) { badge.textContent = 'refunded'; badge.className = 'status-badge refunded'; }
            // Remove progress UI
            const card = document.getElementById('ajob_' + id);
            if (card) {
                card.querySelectorAll('.prog-steps,.prog-bar,.prog-elapsed').forEach(e => e.remove());
                // Swap cancel button for delete button
                const actRow = card.querySelector('[data-action-row]') || card.querySelector('div[style*="flex-end"]');
                if (actRow) actRow.innerHTML = `<button class="btn btn-ghost btn-sm" style="color:var(--color-muted)" onclick="deleteAvatarJob(${id})">🗑 Delete</button>`;
                // Show "Cancelled by user" text
                const info = card.querySelector('.job-info');
                if (info && !info.querySelector('.cancel-msg')) {
                    const msg = document.createElement('div');
                    msg.className = 'text-sm cancel-msg';
                    msg.style.color = 'var(--color-muted)';
                    msg.style.marginTop = '4px';
                    msg.textContent = 'Cancelled by user · credits refunded';
                    info.appendChild(msg);
                }
            }
            // Remove from polling
            const idx = pendingJobs.findIndex(j => j.id === id);
            if (idx !== -1) pendingJobs.splice(idx, 1);
        } else {
            alert(data.error || 'Cancel failed.');
            if (btn) { btn.disabled = false; btn.textContent = '✕ Cancel & Refund'; }
        }
    })
    .catch(() => { alert('Network error.'); if (btn) { btn.disabled = false; } });
}

// ── Delete job ────────────────────────────────────────────────────────────────
function deleteAvatarJob(id) {
    if (!confirm('Delete this record? This cannot be undone.')) return;

    fetch(`<?= BASE_URL ?>/client/avatar.php?_action=delete&job_id=${id}`, {
        headers: { 'X-CSRF-Token': csrfToken }
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            const card = document.getElementById('ajob_' + id);
            if (card) {
                card.style.transition = 'opacity .3s';
                card.style.opacity = '0';
                setTimeout(() => card.remove(), 300);
            }
        } else {
            alert(data.error || 'Delete failed.');
        }
    })
    .catch(() => alert('Network error.'));
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

        // ── Config ────────────────────────────────────────────────────────────
        alog('── Configuration ─────────────────────', '#facc15');
        Object.entries(d.config).forEach(([k, v]) => {
            const bad = String(v).includes('NOT SET') || String(v).includes('(empty');
            alog(`  ${k}: ${v}`, bad ? '#ef4444' : '#a8e063');
        });

        // ── DNS ───────────────────────────────────────────────────────────────
        alog('── DNS Resolution ────────────────────', '#facc15');
        const dnsOk = d.dns.resolved.startsWith('OK');
        alog(`  ${d.dns.hostname} → ${d.dns.resolved}`, dnsOk ? '#a8e063' : '#ef4444');
        if (!dnsOk) {
            alog('  ⚠ Trying alternative hostnames:', '#f59e0b');
        }
        alog('  Alternative hosts:', '#64b5f6');
        Object.entries(d.dns_alternatives).forEach(([h, r]) => {
            const ok = r.startsWith('✓');
            alog(`    ${h}: ${r}`, ok ? '#a8e063' : '#475569');
            if (ok) alog(`    ↑ USE THIS URL in Admin → Settings → vision_ai_url`, '#fbbf24');
        });

        // ── cv.byteplusapi.com DNS + probe ───────────────────────────────────
        if (d.cv_byteplusapi_dns) {
            alog('── cv.byteplusapi.com DNS ────────────', '#facc15');
            const cvok = d.cv_byteplusapi_dns.resolved.startsWith('✓');
            alog(`  ${d.cv_byteplusapi_dns.hostname}: ${d.cv_byteplusapi_dns.resolved}`,
                 cvok ? '#a8e063' : '#ef4444');
            if (cvok) alog('  ✓ Correct endpoint resolves — update vision_ai_url in Admin→Settings', '#fbbf24');
        }
        if (d.cv_byteplusapi_probe) {
            alog('── cv.byteplusapi.com API Probe ──────', '#facc15');
            const p = d.cv_byteplusapi_probe;
            const pok = p.diagnosis && p.diagnosis.startsWith('✓');
            alog(`  HTTP: ${p.http_code || 'N/A'}  ${p.diagnosis}`, pok ? '#a8e063' : '#ef4444');
            if (p.curl_error && p.curl_error !== 'none') alog(`  cURL: ${p.curl_error}`, '#ef4444');
            if (p.response && typeof p.response === 'object') {
                alog('  Response:', '#94a3b8');
                JSON.stringify(p.response, null, 2).split('\n').forEach(l => alog('    ' + l, '#475569'));
            }
        }

        // ── DoH lookup for cv.byteplusapi.com + visual.ap-singapore-1.byteplus.com ─
        if (d.byteplus_doh_lookup) {
            alog('── BytePlus DoH DNS Lookup ───────────', '#facc15');
            Object.values(d.byteplus_doh_lookup).forEach(doh => {
                if (typeof doh !== 'object' || !doh.hostname) return;
                alog(`  ${doh.hostname}:`, '#64b5f6');
                ['Google', 'Cloudflare'].forEach(prov => {
                    if (doh[prov] !== undefined) {
                        const ok = doh[prov].startsWith('✓');
                        alog(`    ${prov}: ${doh[prov]}`, ok ? '#a8e063' : '#475569');
                    }
                });
            });
        }
        if (d.cv_byteplusapi_doh_ip_test) {
            alog('── cv.byteplusapi.com via DoH IP ─────', '#facc15');
            const t = d.cv_byteplusapi_doh_ip_test;
            const tok = t.diagnosis && t.diagnosis.startsWith('✓');
            alog(`  IP: ${t.ip_used}  HTTP: ${t.http_code || 'failed'}`, '#64b5f6');
            alog(`  ${t.diagnosis}`, tok ? '#a8e063' : '#ef4444');
            if (tok) {
                alog('  → Set vision_ai_url = https://cv.byteplusapi.com in Admin→Settings', '#fbbf24');
                alog('  → Set vision_ai_dns_override = ' + t.ip_used + ' in Admin→Settings', '#fbbf24');
            }
            if (t.response && typeof t.response === 'object') {
                alog('  Response:', '#94a3b8');
                JSON.stringify(t.response, null, 2).split('\n').forEach(l => alog('    ' + l, '#475569'));
            }
        }

        // ── TCP connect ───────────────────────────────────────────────────────
        if (d.tcp_connect) {
            alog('── TCP+TLS Connect ───────────────────', '#facc15');
            alog('  ' + d.tcp_connect, d.tcp_connect.startsWith('OK') ? '#a8e063' : '#ef4444');
        }

        // ── Upload dirs ───────────────────────────────────────────────────────
        alog('── Upload Directories ────────────────', '#facc15');
        Object.entries(d.dirs).forEach(([k, v]) => {
            alog(`  ${k}: ${v}`, v.includes('OK') || v.includes('created') ? '#a8e063' : '#ef4444');
        });

        // ── API probe ─────────────────────────────────────────────────────────
        alog('── API Probe ─────────────────────────', '#facc15');
        if (typeof d.api_probe === 'string') {
            alog('  ' + d.api_probe, '#f59e0b');
        } else {
            alog(`  Signing: ${d.api_probe.signing}`, '#64b5f6');
            alog(`  URL: ${d.api_probe.url}`, '#94a3b8');
            const httpOk = d.api_probe.http_code > 0;
            alog(`  HTTP: ${d.api_probe.http_code || 'N/A (cURL failed)'}`, httpOk ? '#a8e063' : '#ef4444');
            const diagColor = (d.api_probe.diagnosis.startsWith('DNS') || d.api_probe.diagnosis.startsWith('WRONG') ||
                               d.api_probe.diagnosis.startsWith('AUTH') || d.api_probe.diagnosis.startsWith('TIMEOUT'))
                ? '#ef4444' : d.api_probe.diagnosis.startsWith('BAD') ? '#fbbf24' : '#a8e063';
            alog(`  Diagnosis: ${d.api_probe.diagnosis}`, diagColor);
            if (d.api_probe.curl_error !== 'none') {
                alog(`  cURL error: ${d.api_probe.curl_error}`, '#ef4444');
            }
            if (d.api_probe.response && typeof d.api_probe.response === 'object') {
                alog('  Response:', '#94a3b8');
                JSON.stringify(d.api_probe.response, null, 2).split('\n').forEach(l => alog('    ' + l, '#475569'));
            }
        }

        // ── req_key scan ──────────────────────────────────────────────────────
        if (d.req_key_scan) {
            alog('── req_key / Action Scan ─────────────', '#facc15');
            alog('  (testing all candidates — look for ✓ ACCEPTED or ✓ TASK CREATED)', '#94a3b8');
            d.req_key_scan.forEach(item => {
                // Item is an object: {req_key, label, result, code, msg}
                if (typeof item === 'string') {
                    // Fallback for old string format
                    const ok = item.includes('ACCEPTED') || item.includes('SUCCESS');
                    alog('  ' + item, ok ? '#a8e063' : '#475569');
                    return;
                }
                const ok = item.result.startsWith('✓');
                const warn = item.result.startsWith('⚠');
                const color = ok ? '#a8e063' : (warn ? '#fbbf24' : '#475569');
                alog(`  ${item.req_key} ${item.label || ''} → ${item.result}`, color);
                // Show msg for non-trivial results (not plain "not supported")
                if (item.msg && !item.result.includes('not supported (req_key unknown)')) {
                    alog(`    msg: ${item.msg}`, '#64748b');
                }
                if (ok && !item.label.includes('Step-1')) {
                    alog(`  ↑ Set omnihuman_req_key = ${item.req_key} in Admin→Settings`, '#fbbf24');
                }
                if (warn) {
                    alog(`  ↑ Activate this service in BytePlus console → Vision AI → Model Plaza`, '#f97316');
                }
            });
        }

        // ── Last job ──────────────────────────────────────────────────────────
        alog('── Last Job ──────────────────────────', '#facc15');
        if (typeof d.last_job === 'string') {
            alog('  ' + d.last_job, '#94a3b8');
        } else {
            alog(`  Job #${d.last_job.id}  status: ${d.last_job.status}  created: ${d.last_job.created_at}`, '#94a3b8');
            alog(`  api_task_id: ${d.last_job.api_task_id}`, '#94a3b8');
            alog(`  error_message: ${d.last_job.error_message}`,
                 d.last_job.error_message === '(none)' ? '#a8e063' : '#ef4444');
            if (d.last_job.api_response) {
                alog('  api_response:', '#94a3b8');
                JSON.stringify(d.last_job.api_response, null, 2).split('\n').forEach(l => alog('    ' + l, '#475569'));
            }
        }

        // ── Live task query ──────────────────────────────────────────────────
        if (d.live_task_query) {
            const lq = d.live_task_query;
            alog('── Live Task Query (BytePlus) ────────', '#facc15');
            alog(`  task_id: ${lq.task_id}`, '#94a3b8');
            alog(`  HTTP: ${lq.http_code}  api_code: ${lq.api_code}  curl_error: ${lq.curl_error}`,
                 lq.http_code === 200 ? '#94a3b8' : '#ef4444');
            if (lq.error_msg) alog(`  ERROR: ${lq.error_msg}`, '#ef4444');
            const rawSt = lq.raw_status;
            const stColor = rawSt === 'done' ? '#a8e063' : (rawSt === 'failed' || rawSt === 'not_found' ? '#ef4444' : '#fbbf24');
            alog(`  BytePlus status: ${rawSt}`, stColor);
            if (rawSt === 'done') alog(`  resp_data: ${lq.resp_data}`, '#a8e063');
            // Always dump full raw response so nothing is hidden
            alog('  full raw response:', '#94a3b8');
            JSON.stringify(lq.full_response, null, 2).split('\n').forEach(l => alog('    ' + l, '#475569'));
            if (lq.http_code !== 200 && (!lq.full_response || Object.keys(lq.full_response).length === 0)) {
                alog(`  raw body: ${lq.raw_body}`, '#ef4444');
            }
            // Interpret
            if (rawSt === 'done') alog('  → Task DONE — resp_data should contain video_url above', '#a8e063');
            else if (rawSt === 'not_found') alog('  → Task no longer exists on BytePlus — cancel and retry', '#ef4444');
            else if (rawSt === 'failed') alog('  → Task FAILED on BytePlus — cancel and retry', '#ef4444');
            else if (['generating','in_queue'].includes(rawSt)) alog('  → Still running on BytePlus — wait', '#fbbf24');
            else if (lq.http_code === 400) alog('  → HTTP 400: BytePlus rejected the query — check ERROR message above', '#ef4444');
            else alog(`  → Unknown status "${rawSt}" — see full raw response above`, '#fbbf24');
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
