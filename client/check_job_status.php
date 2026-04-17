<?php
declare(strict_types=1);

/**
 * client/check_job_status.php
 * Called by AJAX from history.php to poll BytePlus for a specific job.
 * Updates DB and returns current status. Works without a cron job.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/wallet.php';
require_once __DIR__ . '/../inc/byteplus.php';

boot_session();
$user = require_auth('/public/login.php');
$uid  = (int)$user['id'];
$pdo  = db();

// ── Long-video status check ───────────────────────────────────────────────────
$lvId = (int)($_GET['lv'] ?? 0);
if ($lvId) {
    $lvRow = $pdo->prepare(
        'SELECT id, status, clip1_url, clip2_url, clip3_url, final_video_url, error_message
         FROM `long_video_jobs` WHERE id=? AND user_id=? LIMIT 1'
    );
    $lvRow->execute([$lvId, $uid]);
    $lv = $lvRow->fetch();
    if (!$lv) { json_response(['ok' => false, 'error' => 'Not found']); }
    $statusLabels = [
        'queued'=>'Queued','clip1'=>'Clip 1/3','clip2'=>'Clip 2/3',
        'clip3'=>'Clip 3/3','stitching'=>'Stitching…',
        'completed'=>'Completed','failed'=>'Failed','refunded'=>'Refunded',
    ];
    json_response([
        'ok'           => true,
        'status'       => $lv['status'],
        'status_label' => $statusLabels[$lv['status']] ?? $lv['status'],
        'final_url'    => $lv['final_video_url'],
        'clip1_url'    => $lv['clip1_url'],
        'clip2_url'    => $lv['clip2_url'],
        'clip3_url'    => $lv['clip3_url'],
        'error'        => $lv['error_message'],
    ]);
}

$jobId = (int)($_GET['job_id'] ?? 0);
if (!$jobId) {
    json_response(['ok' => false, 'error' => 'Missing job_id']);
}

// Verify the job belongs to this user and is still running
$stmt = $pdo->prepare(
    'SELECT * FROM `video_jobs`
     WHERE `id` = ? AND `user_id` = ? AND `status` IN ("queued","processing")
     LIMIT 1'
);
$stmt->execute([$jobId, $uid]);
$job = $stmt->fetch();

if (!$job) {
    // Job already completed/failed — just return current status from DB
    $row = $pdo->prepare(
        'SELECT vj.status, vo.cdn_url, vo.thumbnail
         FROM video_jobs vj
         LEFT JOIN video_outputs vo ON vo.job_id = vj.id
         WHERE vj.id = ? AND vj.user_id = ? LIMIT 1'
    );
    $row->execute([$jobId, $uid]);
    $cur = $row->fetch();
    json_response(['ok' => true, 'status' => $cur['status'] ?? 'unknown',
                   'cdn_url' => $cur['cdn_url'], 'from_cache' => true]);
}

if (!$job['api_task_id']) {
    json_response(['ok' => true, 'status' => $job['status'], 'note' => 'no task id yet']);
}

// ── Ask BytePlus for current status ──────────────────────────────────────────
$result = byteplus_query_task($job['api_task_id']);

if (!$result['ok']) {
    json_response(['ok' => false, 'error' => $result['error'], 'status' => $job['status']]);
}

$status   = $result['status'];
$videoUrl = $result['video_url'];
$thumbUrl = $result['thumbnail_url'];
$errorMsg = $result['error_message'];
$raw      = $result['raw'];

$tokensUsed = $raw['usage']['total_tokens']  ?? $raw['usage']['completion_tokens'] ?? null;
$costUsd    = $raw['usage']['total_cost']    ?? null;

// ── Update DB based on result ─────────────────────────────────────────────────
if ($status === 'completed' && $videoUrl) {

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE `video_jobs`
             SET `status`="completed", `completed_at`=NOW(),
                 `api_response`=?, `tokens_used`=?, `api_cost_usd`=?
             WHERE `id`=?'
        )->execute([json_encode($raw), $tokensUsed, $costUsd, $jobId]);

        $pdo->prepare(
            'INSERT INTO `video_outputs` (`job_id`,`user_id`,`cdn_url`,`thumbnail`)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE `cdn_url`=VALUES(`cdn_url`), `thumbnail`=VALUES(`thumbnail`)'
        )->execute([$jobId, $uid, $videoUrl, $thumbUrl]);

        $pdo->commit();
        log_activity('system', null, 'job_completed', 'Job #' . $jobId . ' completed via client poll');
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }

    json_response(['ok' => true, 'status' => 'completed', 'cdn_url' => $videoUrl, 'thumbnail' => $thumbUrl]);

} elseif ($status === 'failed') {

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE `video_jobs`
             SET `status`="failed", `error_message`=?, `api_response`=?,
                 `tokens_used`=?, `api_cost_usd`=?
             WHERE `id`=?'
        )->execute([$errorMsg, json_encode($raw), $tokensUsed, $costUsd, $jobId]);

        wallet_refund($uid, (float)$job['credit_cost'], 'video_job', $jobId,
            'Auto-refund: job #' . $jobId . ' failed');

        $pdo->prepare(
            'UPDATE `video_jobs` SET `status`="refunded", `refunded_at`=NOW() WHERE `id`=?'
        )->execute([$jobId]);

        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }

    json_response(['ok' => true, 'status' => 'refunded', 'error' => $errorMsg]);

} else {
    // Still queued/processing — update started_at
    $pdo->prepare(
        'UPDATE `video_jobs` SET `status`=?, `started_at`=COALESCE(`started_at`,NOW()) WHERE `id`=?'
    )->execute([$status, $jobId]);

    json_response(['ok' => true, 'status' => $status]);
}
