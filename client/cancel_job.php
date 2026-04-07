<?php
declare(strict_types=1);

/**
 * client/cancel_job.php
 * Cancel a queued/processing video job and refund credits.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/wallet.php';

boot_session();
$user = require_auth('/public/login.php');
$uid  = (int)$user['id'];
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    json_response(['ok' => false, 'error' => 'Method not allowed']);
}

csrf_verify();

$jobId = (int)($_POST['job_id'] ?? 0);
if (!$jobId) {
    json_response(['ok' => false, 'error' => 'Missing job_id']);
}

// Verify job belongs to user and is still cancellable
$stmt = $pdo->prepare(
    'SELECT * FROM `video_jobs`
     WHERE `id` = ? AND `user_id` = ? AND `status` IN ("queued","processing")
     LIMIT 1'
);
$stmt->execute([$jobId, $uid]);
$job = $stmt->fetch();

if (!$job) {
    json_response(['ok' => false, 'error' => 'Job not found or cannot be cancelled.']);
}

$pdo->beginTransaction();
try {
    // Mark as failed first so refund can proceed
    $pdo->prepare(
        'UPDATE `video_jobs`
         SET `status` = "failed", `error_message` = "Cancelled by user"
         WHERE `id` = ?'
    )->execute([$jobId]);

    // Refund credits
    $refund = wallet_refund(
        $uid,
        (float)$job['credit_cost'],
        'video_job',
        $jobId,
        'Refund: job #' . $jobId . ' cancelled by user'
    );

    if (!$refund['ok']) {
        $pdo->rollBack();
        json_response(['ok' => false, 'error' => 'Refund failed: ' . $refund['error']]);
    }

    // Mark as refunded
    $pdo->prepare(
        'UPDATE `video_jobs`
         SET `status` = "refunded", `refunded_at` = NOW()
         WHERE `id` = ?'
    )->execute([$jobId]);

    $pdo->commit();

    log_activity('user', $uid, 'cancel_job',
        'Job #' . $jobId . ' cancelled — ' . $job['credit_cost'] . ' credits refunded');

    json_response([
        'ok'      => true,
        'message' => number_format((float)$job['credit_cost'], 2) . ' credits refunded.',
    ]);

} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[cancel_job] ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Something went wrong. Please try again.']);
}
