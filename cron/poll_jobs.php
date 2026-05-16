<?php
declare(strict_types=1);

/**
 * cron/poll_jobs.php
 * Polls BytePlus ModelArk for pending/processing video jobs and updates status.
 *
 * ── Setup on Hostinger (two options) ────────────────────────────────────────
 *
 * Option A — cPanel Cron Job (recommended):
 *   Command: /usr/local/bin/php /home/YOUR_USER/public_html/cron/poll_jobs.php
 *   Schedule: every 2 minutes  →  *\/2 * * * *
 *
 * Option B — URL-based cron (if CLI not available):
 *   Set CRON_SECRET in config/config.php:
 *     define('CRON_SECRET', 'your-random-secret-here');
 *   Then hit:
 *     https://yourdomain.com/cron/poll_jobs.php?secret=your-random-secret-here
 *   Schedule via Hostinger cPanel → Cron Jobs → use a URL cron service, or
 *   any external cron (e.g. cron-job.org — free).
 */

// ── Access control ────────────────────────────────────────────────────────────
$isCli = php_sapi_name() === 'cli';
$isWeb = !$isCli;

if ($isWeb) {
    // Allow web access only with the correct secret token
    require_once __DIR__ . '/../config/config.php';
    $secret = defined('CRON_SECRET') ? CRON_SECRET : '';
    if (!$secret || ($_GET['secret'] ?? '') !== $secret) {
        http_response_code(403);
        exit('Forbidden');
    }
    header('Content-Type: text/plain');
}

if (!$isCli) {
    require_once __DIR__ . '/../config/config.php';
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/wallet.php';
require_once __DIR__ . '/../inc/byteplus.php';
require_once __DIR__ . '/../inc/vision_auth.php';
require_once __DIR__ . '/../inc/omnihuman.php';
require_once __DIR__ . '/../inc/long_video.php';
require_once __DIR__ . '/../inc/clone_avatar_api.php';

$pdo = db();

function clog(string $msg): void {
    echo date('[Y-m-d H:i:s]') . ' ' . $msg . "\n";
    flush();
}

clog('Polling started.');

// ── Load jobs that need polling ───────────────────────────────────────────────
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
    clog('No jobs to poll. Done.');
    exit(0);
}

clog('Found ' . count($jobs) . ' job(s) to poll.');

foreach ($jobs as $job) {
    $jobId  = (int)$job['id'];
    $taskId = $job['api_task_id'];
    $userId = (int)$job['user_id'];

    clog("Checking job #$jobId (task_id: $taskId)");

    // Use the shared byteplus_query_task() — ModelArk endpoint
    $result = byteplus_query_task($taskId);

    if (!$result['ok']) {
        clog("  ERROR: " . $result['error']);
        continue;
    }

    $status      = $result['status'];      // queued|processing|completed|failed
    $videoUrl    = $result['video_url'];
    $thumbUrl    = $result['thumbnail_url'];
    $errorMsg    = $result['error_message'];
    $raw         = $result['raw'];

    // Extract token/cost usage from BytePlus response if available
    $tokensUsed  = $raw['usage']['total_tokens']      ?? $raw['usage']['completion_tokens'] ?? null;
    $inputTokens = $raw['usage']['prompt_tokens']     ?? null;
    $costUsd     = $raw['usage']['total_cost']        ?? null;

    clog("  Status: $status" . ($tokensUsed ? " | tokens: $tokensUsed" : ''));

    if ($status === 'queued' || $status === 'processing') {
        // Keep alive — update started_at if not set
        $pdo->prepare(
            'UPDATE `video_jobs`
             SET `status` = ?, `started_at` = COALESCE(`started_at`, NOW())
             WHERE `id` = ?'
        )->execute([$status, $jobId]);
        continue;
    }

    // ── Terminal state ────────────────────────────────────────────────────────
    $pdo->beginTransaction();
    try {
        if ($status === 'completed' && $videoUrl) {

            // Update job with cost data
            $pdo->prepare(
                'UPDATE `video_jobs`
                 SET `status` = "completed",
                     `completed_at` = NOW(),
                     `api_response` = ?,
                     `tokens_used`  = ?,
                     `api_cost_usd` = ?
                 WHERE `id` = ?'
            )->execute([json_encode($raw), $tokensUsed, $costUsd, $jobId]);

            // Insert video output
            $pdo->prepare(
                'INSERT INTO `video_outputs` (`job_id`, `user_id`, `cdn_url`, `thumbnail`)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE `cdn_url` = VALUES(`cdn_url`), `thumbnail` = VALUES(`thumbnail`)'
            )->execute([$jobId, $userId, $videoUrl, $thumbUrl]);

            $pdo->commit();
            clog("  ✓ COMPLETED — video: $videoUrl");

            // Send email notification (non-blocking)
            if (function_exists('mail_video_completed')) {
                $usr = $pdo->prepare('SELECT name, email FROM `users` WHERE id = ? LIMIT 1');
                $usr->execute([$userId]);
                if ($row = $usr->fetch()) {
                    mail_video_completed($row['email'], $row['name'], $jobId, $videoUrl);
                }
            }

        } elseif ($status === 'failed') {

            $pdo->prepare(
                'UPDATE `video_jobs`
                 SET `status` = "failed",
                     `error_message` = ?,
                     `api_response`  = ?,
                     `tokens_used`   = ?,
                     `api_cost_usd`  = ?
                 WHERE `id` = ?'
            )->execute([$errorMsg, json_encode($raw), $tokensUsed, $costUsd, $jobId]);

            // Refund credits — wallet_refund checks inTransaction()
            wallet_refund($userId, (float)$job['credit_cost'], 'video_job', $jobId,
                'Auto-refund: job #' . $jobId . ' failed at API');

            $pdo->prepare(
                'UPDATE `video_jobs` SET `status` = "refunded", `refunded_at` = NOW() WHERE `id` = ?'
            )->execute([$jobId]);

            $pdo->commit();
            clog("  ✗ FAILED — refunded {$job['credit_cost']} credits to user #$userId");
        }

    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        clog("  DB ERROR: " . $e->getMessage());
    }
}

// ── Poll avatar_jobs (OmniHuman) ──────────────────────────────────────────────
$avatarStmt = $pdo->prepare(
    'SELECT * FROM `avatar_jobs`
     WHERE `status` IN ("queued","processing")
       AND `api_task_id` IS NOT NULL
     ORDER BY `created_at` ASC
     LIMIT 20'
);
$avatarStmt->execute();
$avatarJobs = $avatarStmt->fetchAll();

// Auto-timeout avatar jobs stuck processing for > 30 minutes
// OmniHuman 10-second videos can take 15–30 min in a busy queue.
$timeoutStmt = $pdo->prepare(
    'SELECT id, user_id, credit_cost FROM `avatar_jobs`
     WHERE `status` IN ("queued","processing")
       AND `created_at` < DATE_SUB(NOW(), INTERVAL 30 MINUTE)'
);
$timeoutStmt->execute();
foreach ($timeoutStmt->fetchAll() as $stuckJob) {
    clog("Avatar job #{$stuckJob['id']} timed out after 15 min — refunding.");
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE avatar_jobs SET status="refunded", error_message="Timed out after 15 minutes", refunded_at=NOW() WHERE id=?'
        )->execute([$stuckJob['id']]);
        wallet_refund((int)$stuckJob['user_id'], (float)$stuckJob['credit_cost'], 'avatar_job', (int)$stuckJob['id'],
            'Auto-refund: avatar job #' . $stuckJob['id'] . ' timed out');
        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        clog("  Timeout refund DB error: " . $e->getMessage());
    }
}

if (!empty($avatarJobs)) {
    clog('Found ' . count($avatarJobs) . ' avatar job(s) to poll.');

    foreach ($avatarJobs as $job) {
        $jobId  = (int)$job['id'];
        $taskId = $job['api_task_id'];
        $userId = (int)$job['user_id'];

        clog("Checking avatar job #$jobId (task_id: $taskId)");

        $result = omnihuman_query_task($taskId);

        if (!$result['ok']) {
            $apiErrCode = (int)($result['raw']['code'] ?? $result['raw']['status'] ?? 0);
            clog("  ERROR (code $apiErrCode): " . ($result['error'] ?? 'unknown'));
            // Give code 50215 a 5-minute grace period: BytePlus can return this
            // transiently while the task is still being validated in their queue.
            $jobAgeSeconds  = time() - (int)strtotime($job['started_at'] ?? $job['created_at'] ?? 'now');
            $pastGrace      = ($jobAgeSeconds > 300);
            $permanentCodes = [50204, 50200];
            $delayedCodes   = [50215];
            if (in_array($apiErrCode, $permanentCodes, true)
                || (in_array($apiErrCode, $delayedCodes, true) && $pastGrace)) {
                $errMsg = 'BytePlus rejected task (code ' . $apiErrCode . '): '
                        . ($result['error'] ?? 'permanent API error');
                $pdo->beginTransaction();
                try {
                    $pdo->prepare(
                        'UPDATE `avatar_jobs`
                         SET `status` = "failed", `error_message` = ?, `api_response` = ?
                         WHERE `id` = ?'
                    )->execute([$errMsg, json_encode($result['raw']), $jobId]);
                    wallet_refund($userId, (float)$job['credit_cost'], 'avatar_job', $jobId,
                        'Auto-refund: avatar job #' . $jobId . ' — code ' . $apiErrCode);
                    $pdo->prepare(
                        'UPDATE `avatar_jobs` SET `status` = "refunded", `refunded_at` = NOW() WHERE `id` = ?'
                    )->execute([$jobId]);
                    $pdo->commit();
                    clog("  ✗ PERMANENT ERROR — refunded {$job['credit_cost']} credits to user #$userId");
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    clog("  DB ERROR during refund: " . $e->getMessage());
                }
            }
            // else: transient error (network, auth timeout) — leave status, retry next run
            continue;
        }

        $status   = $result['status'];    // queued|processing|completed|failed
        $videoUrl = $result['video_url'];
        $errorMsg = $result['error'];

        clog("  Status: $status");

        if ($status === 'queued' || $status === 'processing') {
            $pdo->prepare(
                'UPDATE `avatar_jobs`
                 SET `status` = ?, `started_at` = COALESCE(`started_at`, NOW())
                 WHERE `id` = ?'
            )->execute([$status, $jobId]);
            continue;
        }

        $pdo->beginTransaction();
        try {
            if ($status === 'completed' && $videoUrl) {

                $pdo->prepare(
                    'UPDATE `avatar_jobs`
                     SET `status` = "completed",
                         `completed_at` = NOW(),
                         `video_url`    = ?,
                         `api_response` = ?
                     WHERE `id` = ?'
                )->execute([$videoUrl, json_encode($result['raw']), $jobId]);

                $pdo->commit();
                clog("  ✓ COMPLETED — video: $videoUrl");

            } elseif ($status === 'failed') {

                $pdo->prepare(
                    'UPDATE `avatar_jobs`
                     SET `status` = "failed",
                         `error_message` = ?,
                         `api_response`  = ?
                     WHERE `id` = ?'
                )->execute([$errorMsg, json_encode($result['raw']), $jobId]);

                wallet_refund($userId, (float)$job['credit_cost'], 'avatar_job', $jobId,
                    'Auto-refund: avatar job #' . $jobId . ' failed at API');

                $pdo->prepare(
                    'UPDATE `avatar_jobs` SET `status` = "refunded", `refunded_at` = NOW() WHERE `id` = ?'
                )->execute([$jobId]);

                $pdo->commit();
                clog("  ✗ FAILED — refunded {$job['credit_cost']} credits to user #$userId");
            }

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            clog("  DB ERROR: " . $e->getMessage());
        }
    }
} else {
    clog('No avatar jobs to poll.');
}

// ── Poll clone_avatar_jobs ────────────────────────────────────────────────────
$caTableExists = false;
try {
    $pdo->query('SELECT 1 FROM `clone_avatar_jobs` LIMIT 1');
    $caTableExists = true;
} catch (\Throwable $e) { /* table not yet created — run sql/migrate_clone_avatar.sql */ }

if ($caTableExists) {
    // Auto-timeout jobs stuck > 30 minutes (generation should finish in ~60 s)
    $caTimeout = $pdo->prepare(
        'SELECT id, user_id, credit_cost FROM `clone_avatar_jobs`
         WHERE `status` IN ("queued","processing")
           AND `created_at` < DATE_SUB(NOW(), INTERVAL 30 MINUTE)'
    );
    $caTimeout->execute();
    foreach ($caTimeout->fetchAll() as $stuck) {
        clog("Clone Avatar job #{$stuck['id']} timed out — refunding.");
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE clone_avatar_jobs SET status="refunded", error_message="Timed out after 30 minutes", refunded_at=NOW() WHERE id=?'
            )->execute([$stuck['id']]);
            wallet_refund((int)$stuck['user_id'], (float)$stuck['credit_cost'],
                'clone_avatar_job', (int)$stuck['id'],
                'Auto-refund: clone avatar job #' . $stuck['id'] . ' timed out');
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            clog("  Timeout refund DB error: " . $e->getMessage());
        }
    }

    $caStmt = $pdo->prepare(
        'SELECT * FROM `clone_avatar_jobs`
         WHERE `status` IN ("queued","processing")
           AND `api_task_id` IS NOT NULL
         ORDER BY `created_at` ASC
         LIMIT 20'
    );
    $caStmt->execute();
    $caJobs = $caStmt->fetchAll();

    if (!empty($caJobs)) {
        clog('Found ' . count($caJobs) . ' clone avatar job(s) to poll.');
        foreach ($caJobs as $job) {
            $jobId  = (int)$job['id'];
            $taskId = $job['api_task_id'];
            $userId = (int)$job['user_id'];
            clog("Checking clone avatar job #$jobId (task_id: $taskId)");

            $result = clone_avatar_query_task($taskId);

            if (!$result['ok']) {
                $apiErrCode = (int)($result['raw']['code'] ?? 0);
                clog("  ERROR (code $apiErrCode): " . ($result['error'] ?? 'unknown'));
                $jobAge     = time() - (int)strtotime($job['started_at'] ?? $job['created_at'] ?? 'now');
                $pastGrace  = ($jobAge > 300);
                if (in_array($apiErrCode, [50204, 50200], true)
                    || ($apiErrCode === 50215 && $pastGrace)) {
                    $errMsg = 'BytePlus rejected task (code ' . $apiErrCode . ')';
                    $pdo->beginTransaction();
                    try {
                        $pdo->prepare(
                            'UPDATE clone_avatar_jobs SET status="failed", error_message=?, api_response=? WHERE id=?'
                        )->execute([$errMsg, json_encode($result['raw']), $jobId]);
                        wallet_refund($userId, (float)$job['credit_cost'], 'clone_avatar_job', $jobId,
                            'Auto-refund: clone avatar job #' . $jobId . ' — code ' . $apiErrCode);
                        $pdo->prepare('UPDATE clone_avatar_jobs SET status="refunded", refunded_at=NOW() WHERE id=?')->execute([$jobId]);
                        $pdo->commit();
                        clog("  ✗ PERMANENT ERROR — refunded to user #$userId");
                    } catch (\Throwable $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        clog("  DB ERROR: " . $e->getMessage());
                    }
                }
                continue;
            }

            $status   = $result['status'];
            $videoUrl = $result['video_url'];
            $errorMsg = $result['error'];
            clog("  Status: $status");

            if ($status === 'queued' || $status === 'processing') {
                $pdo->prepare(
                    'UPDATE clone_avatar_jobs SET status=?, started_at=COALESCE(started_at,NOW()) WHERE id=?'
                )->execute([$status, $jobId]);
                continue;
            }

            $pdo->beginTransaction();
            try {
                if ($status === 'completed' && $videoUrl) {
                    $pdo->prepare(
                        'UPDATE clone_avatar_jobs
                         SET status="completed", completed_at=NOW(), video_url=?, api_response=?
                         WHERE id=?'
                    )->execute([$videoUrl, json_encode($result['raw']), $jobId]);
                    $pdo->commit();
                    clog("  ✓ COMPLETED — video: $videoUrl");
                } elseif ($status === 'failed') {
                    $pdo->prepare(
                        'UPDATE clone_avatar_jobs SET status="failed", error_message=?, api_response=? WHERE id=?'
                    )->execute([$errorMsg, json_encode($result['raw']), $jobId]);
                    wallet_refund($userId, (float)$job['credit_cost'], 'clone_avatar_job', $jobId,
                        'Auto-refund: clone avatar job #' . $jobId . ' failed at API');
                    $pdo->prepare('UPDATE clone_avatar_jobs SET status="refunded", refunded_at=NOW() WHERE id=?')->execute([$jobId]);
                    $pdo->commit();
                    clog("  ✗ FAILED — refunded to user #$userId");
                }
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                clog("  DB ERROR: " . $e->getMessage());
            }
        }
    } else {
        clog('No clone avatar jobs to poll.');
    }
}

// ── Poll long_video_jobs (30-Second Ad) ──────────────────────────────────────
$lvTableExists = false;
try {
    $pdo->query('SELECT 1 FROM `long_video_jobs` LIMIT 1');
    $lvTableExists = true;
} catch (\Throwable $e) { /* table not yet created */ }

if ($lvTableExists) {
    $lvStmt = $pdo->prepare(
        'SELECT * FROM `long_video_jobs`
         WHERE `status` IN ("queued","clip1","clip2","clip3","stitching")
         ORDER BY `created_at` ASC
         LIMIT 10'
    );
    $lvStmt->execute();
    $lvJobs = $lvStmt->fetchAll();

    // Auto-timeout jobs stuck for > 90 minutes (3 clips × ~25 min worst-case)
    $lvTimeout = $pdo->prepare(
        'SELECT id, user_id, credit_cost FROM `long_video_jobs`
         WHERE `status` IN ("queued","clip1","clip2","clip3","stitching")
           AND `created_at` < DATE_SUB(NOW(), INTERVAL 90 MINUTE)'
    );
    $lvTimeout->execute();
    foreach ($lvTimeout->fetchAll() as $stuck) {
        clog("30s Ad job #{$stuck['id']} timed out — refunding.");
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE `long_video_jobs`
                 SET `status`="failed", `error_message`="Timed out after 90 minutes"
                 WHERE `id`=?'
            )->execute([$stuck['id']]);
            wallet_refund((int)$stuck['user_id'], (float)$stuck['credit_cost'],
                'long_video_job', (int)$stuck['id'],
                'Auto-refund: 30s Ad #' . $stuck['id'] . ' timed out');
            $pdo->prepare(
                'UPDATE `long_video_jobs` SET `status`="refunded", `refunded_at`=NOW() WHERE `id`=?'
            )->execute([$stuck['id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            clog("  Timeout refund DB error: " . $e->getMessage());
        }
    }

    if (!empty($lvJobs)) {
        clog('Found ' . count($lvJobs) . ' 30s Ad job(s) to advance.');
        foreach ($lvJobs as $lvJob) {
            clog("Advancing 30s Ad job #{$lvJob['id']} (status: {$lvJob['status']})");
            try {
                lv_advance_job($lvJob, $pdo);
            } catch (\Throwable $e) {
                clog("  ERROR advancing job #{$lvJob['id']}: " . $e->getMessage());
            }
        }
    } else {
        clog('No 30s Ad jobs to advance.');
    }
}

// ── Usage summary for this run ────────────────────────────────────────────────
$usageRow = $pdo->query(
    'SELECT
        COUNT(*) AS total_jobs,
        COALESCE(SUM(tokens_used), 0) AS total_tokens,
        COALESCE(SUM(api_cost_usd), 0) AS total_cost_usd
     FROM video_jobs
     WHERE status IN ("completed","failed","refunded")
       AND completed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
)->fetch();

clog("24h summary — jobs: {$usageRow['total_jobs']}, tokens: {$usageRow['total_tokens']}, est. cost: \${$usageRow['total_cost_usd']}");
clog('Poll complete.');
exit(0);
