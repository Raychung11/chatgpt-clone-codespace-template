<?php
declare(strict_types=1);

/**
 * cron/poll_jobs.php
 * Polls BytePlus API for pending/processing video jobs and updates their status.
 *
 * Schedule (Hostinger cPanel / VPS crontab):
 *   * * * * * php /path/to/cron/poll_jobs.php >> /path/to/logs/cron.log 2>&1
 *
 * Or every 2 minutes:
 *   *\/2 * * * * php /path/to/cron/poll_jobs.php >> /path/to/logs/cron.log 2>&1
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/wallet.php';

$pdo = db();

// Load active jobs that need polling
$stmt = $pdo->prepare(
    'SELECT * FROM `video_jobs`
     WHERE `status` IN ("queued","processing")
       AND `api_task_id` IS NOT NULL
     ORDER BY `created_at` ASC
     LIMIT 20'
);
$stmt->execute();
$jobs = $stmt->fetchAll();

if (empty($jobs)) {
    echo date('[Y-m-d H:i:s]') . " No jobs to poll.\n";
    exit(0);
}

$apiKey  = setting('byteplus_api_key', BYTEPLUS_API_KEY);
$apiBase = rtrim(setting('byteplus_api_url', BYTEPLUS_API_URL), '/');

foreach ($jobs as $job) {
    $jobId    = (int)$job['id'];
    $taskId   = $job['api_task_id'];
    $userId   = (int)$job['user_id'];

    echo date('[Y-m-d H:i:s]') . " Polling job #$jobId (task: $taskId)…\n";

    // ── Call BytePlus status API ──────────────────────────────────────────────
    $url = "$apiBase/task/query?task_id=" . urlencode($taskId);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $httpCode !== 200) {
        echo date('[Y-m-d H:i:s]') . " ERROR job #$jobId: HTTP $httpCode $curlErr\n";
        continue;
    }

    $data = json_decode($response, true);
    if (!$data) {
        echo date('[Y-m-d H:i:s]') . " ERROR job #$jobId: invalid JSON\n";
        continue;
    }

    // ── Parse status from BytePlus response ───────────────────────────────────
    // Adjust the path below to match actual BytePlus API response schema.
    $apiStatus  = $data['data']['status']   ?? $data['status'] ?? 'unknown';
    $videoUrl   = $data['data']['video_url'] ?? $data['video_url'] ?? null;
    $thumbnailUrl = $data['data']['thumbnail_url'] ?? null;
    $errorMsg   = $data['data']['error_message'] ?? $data['message'] ?? '';

    // Map provider status → internal status
    $newStatus = match (strtolower($apiStatus)) {
        'succeeded', 'success', 'completed' => 'completed',
        'failed', 'error'                   => 'failed',
        'processing', 'running', 'pending'  => 'processing',
        default                             => null, // no change
    };

    if ($newStatus === null) {
        // Mark as processing if still running
        $pdo->prepare('UPDATE `video_jobs` SET `status`="processing", `started_at`=COALESCE(`started_at`,NOW()) WHERE `id`=?')
            ->execute([$jobId]);
        echo date('[Y-m-d H:i:s]') . " job #$jobId still running (api_status=$apiStatus)\n";
        continue;
    }

    $pdo->beginTransaction();
    try {
        if ($newStatus === 'completed' && $videoUrl) {
            $pdo->prepare(
                'UPDATE `video_jobs`
                 SET `status`="completed", `completed_at`=NOW(), `api_response`=?
                 WHERE `id`=?'
            )->execute([json_encode($data), $jobId]);

            // Store output
            $pdo->prepare(
                'INSERT INTO `video_outputs` (`job_id`,`user_id`,`cdn_url`,`thumbnail`)
                 VALUES (?,?,?,?)'
            )->execute([$jobId, $userId, $videoUrl, $thumbnailUrl]);

            echo date('[Y-m-d H:i:s]') . " job #$jobId COMPLETED ✓\n";

        } elseif ($newStatus === 'failed') {
            $pdo->prepare(
                'UPDATE `video_jobs`
                 SET `status`="failed", `error_message`=?, `api_response`=?
                 WHERE `id`=?'
            )->execute([$errorMsg, json_encode($data), $jobId]);

            // Refund credits
            $refund = wallet_refund($userId, (float)$job['credit_cost'], 'video_job', $jobId,
                'Auto-refund: job #' . $jobId . ' failed');

            $pdo->prepare('UPDATE `video_jobs` SET `status`="refunded", `refunded_at`=NOW() WHERE `id`=?')
                ->execute([$jobId]);

            echo date('[Y-m-d H:i:s]') . " job #$jobId FAILED — refunded " . $job['credit_cost'] . " credits to user #$userId\n";
        }

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo date('[Y-m-d H:i:s]') . " DB ERROR job #$jobId: " . $e->getMessage() . "\n";
    }
}

echo date('[Y-m-d H:i:s]') . " Poll complete. Processed " . count($jobs) . " job(s).\n";
exit(0);
