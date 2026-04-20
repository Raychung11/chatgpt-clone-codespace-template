<?php
declare(strict_types=1);

/**
 * inc/long_video.php
 * 30-Second Ad — orchestration layer for chained Seedance clips.
 *
 * State machine (long_video_jobs.status):
 *   queued → clip1 → clip2 → clip3 → stitching → completed
 *
 * Each transition is driven by cron/poll_jobs.php calling lv_advance_job().
 * Seedance 2.0 returns last_frame_url in the API response (return_last_frame=true),
 * so FFmpeg is no longer needed for frame extraction — clips chain automatically.
 * FFmpeg is still used for stitching; if unavailable, clip URLs are returned as-is.
 */

require_once __DIR__ . '/byteplus.php';
require_once __DIR__ . '/wallet.php';

// ── Directories ───────────────────────────────────────────────────────────────

function lv_ensure_dirs(): void
{
    $dirs = [
        BASE_PATH . '/uploads/long_video',
        BASE_PATH . '/uploads/long_video/frames',
        BASE_PATH . '/uploads/long_video/clips',
        BASE_PATH . '/uploads/long_video/final',
    ];
    foreach ($dirs as $d) {
        if (!is_dir($d)) {
            @mkdir($d, 0755, true);
        }
    }
}

// ── FFmpeg helpers ────────────────────────────────────────────────────────────

function lv_ffmpeg_path(): string
{
    static $cached = null;
    if ($cached !== null) return $cached;

    $override = function_exists('setting') ? (setting('ffmpeg_path', '') ?: '') : '';
    if ($override && is_executable($override)) return $cached = $override;

    foreach (['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/opt/homebrew/bin/ffmpeg'] as $p) {
        if (is_executable($p)) return $cached = $p;
    }
    $which = trim((string)shell_exec('which ffmpeg 2>/dev/null'));
    return $cached = ($which ?: '');
}

function lv_ffmpeg_available(): bool
{
    return lv_ffmpeg_path() !== '';
}

/**
 * Extract the last frame of a local video file.
 * Returns the local file path on success, or null on failure.
 */
function lv_extract_last_frame(string $videoPath, int $jobId, int $clipNum): ?string
{
    $ffmpeg = lv_ffmpeg_path();
    if (!$ffmpeg || !is_file($videoPath)) return null;

    lv_ensure_dirs();
    $out = BASE_PATH . "/uploads/long_video/frames/lv{$jobId}_c{$clipNum}_lastframe.jpg";

    // -sseof -0.1: seek 0.1s from end; -frames:v 1: grab one frame
    $cmd = escapeshellarg($ffmpeg)
         . ' -sseof -0.1'
         . ' -i ' . escapeshellarg($videoPath)
         . ' -frames:v 1 -q:v 2 -y '
         . escapeshellarg($out)
         . ' 2>/dev/null';

    shell_exec($cmd);

    return (is_file($out) && filesize($out) > 0) ? $out : null;
}

/**
 * Download a remote URL to a local file path.
 * Returns true on success.
 */
function lv_download_file(string $url, string $destPath): bool
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 300,  // 5 min for large video files
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err || $code < 200 || $code >= 300 || !$data) {
        error_log("[LongVideo] download failed ($code, $err): $url");
        return false;
    }

    return (bool)file_put_contents($destPath, $data);
}

/**
 * Concatenate three local video files into one using FFmpeg.
 * Returns output path on success, or null on failure.
 */
function lv_stitch_clips(string $c1, string $c2, string $c3, int $jobId): ?string
{
    $ffmpeg = lv_ffmpeg_path();
    if (!$ffmpeg) return null;

    lv_ensure_dirs();
    $out      = BASE_PATH . "/uploads/long_video/final/lv{$jobId}_final.mp4";
    $listFile = BASE_PATH . "/uploads/long_video/final/lv{$jobId}_list.txt";

    // Write concat list
    $list = "file '" . addslashes($c1) . "'\n"
          . "file '" . addslashes($c2) . "'\n"
          . "file '" . addslashes($c3) . "'\n";
    file_put_contents($listFile, $list);

    // Try fast stream-copy first (no re-encode)
    $cmd = escapeshellarg($ffmpeg)
         . ' -f concat -safe 0'
         . ' -i ' . escapeshellarg($listFile)
         . ' -c copy -y '
         . escapeshellarg($out)
         . ' 2>/dev/null';
    shell_exec($cmd);

    // If stream-copy produced a valid file, we're done
    if (is_file($out) && filesize($out) > 100000) {
        @unlink($listFile);
        return $out;
    }

    // Fallback: re-encode with filter_complex concat
    @unlink($out);
    $cmd2 = escapeshellarg($ffmpeg)
          . ' -i ' . escapeshellarg($c1)
          . ' -i ' . escapeshellarg($c2)
          . ' -i ' . escapeshellarg($c3)
          . ' -filter_complex "[0:v][1:v][2:v]concat=n=3:v=1:a=0[v]"'
          . ' -map "[v]" -c:v libx264 -pix_fmt yuv420p -y '
          . escapeshellarg($out)
          . ' 2>/dev/null';
    shell_exec($cmd2);

    @unlink($listFile);
    return (is_file($out) && filesize($out) > 100000) ? $out : null;
}

// ── State machine ─────────────────────────────────────────────────────────────

/**
 * Advance a single long_video_job one step.
 * Called from cron/poll_jobs.php for every active job.
 */
function lv_advance_job(array $job, \PDO $pdo): void
{
    $id     = (int)$job['id'];
    $uid    = (int)$job['user_id'];
    $status = $job['status'];

    lv_ensure_dirs();

    switch ($status) {

        // ── Start: submit clip 1 ─────────────────────────────────────────────
        case 'queued':
            $res = byteplus_create_task(
                $job['prompt1'],
                $job['resolution'] ?: '1080p',
                10
            );
            if ($res['ok']) {
                $pdo->prepare(
                    'UPDATE `long_video_jobs`
                     SET `status`="clip1", `clip1_task_id`=?, `started_at`=COALESCE(`started_at`,NOW())
                     WHERE `id`=?'
                )->execute([$res['task_id'], $id]);
            } else {
                _lv_fail($pdo, $id, $uid, (float)$job['credit_cost'], 'Clip 1 submit: ' . $res['error']);
            }
            break;

        // ── Clip 1 polling ───────────────────────────────────────────────────
        case 'clip1':
            if (!$job['clip1_task_id']) { _lv_fail($pdo, $id, $uid, (float)$job['credit_cost'], 'No clip1 task_id'); break; }
            $q = byteplus_query_task($job['clip1_task_id']);
            if (!$q['ok']) break; // transient error, retry next cron run
            if ($q['status'] === 'queued' || $q['status'] === 'processing') break;
            if ($q['status'] === 'failed') {
                _lv_fail($pdo, $id, $uid, (float)$job['credit_cost'], 'Clip 1 failed: ' . $q['error_message']);
                break;
            }
            // Completed — get video URL and last frame URL
            $clip1Url     = $q['video_url'];
            $frame1Url    = $q['last_frame_url'] ?? null; // from return_last_frame API param

            // Fallback: download + FFmpeg extract if API didn't return last_frame_url
            $clip1Local  = null;
            $frame1Path  = null;
            if (!$frame1Url && lv_ffmpeg_available()) {
                $clip1Local = BASE_PATH . "/uploads/long_video/clips/lv{$id}_clip1.mp4";
                if (lv_download_file($clip1Url, $clip1Local)) {
                    $frame1Path = lv_extract_last_frame($clip1Local, $id, 1);
                    $frame1Url  = $frame1Path
                        ? rtrim(BASE_URL, '/') . '/uploads/long_video/frames/' . basename($frame1Path)
                        : null;
                }
            }

            // Submit clip 2 (i2v if frame available, otherwise t2v)
            $res2 = $frame1Url
                ? byteplus_create_i2v_task($job['prompt2'], $frame1Url, $job['resolution'] ?: '1080p', 10)
                : byteplus_create_task($job['prompt2'], $job['resolution'] ?: '1080p', 10);

            if ($res2['ok']) {
                $pdo->prepare(
                    'UPDATE `long_video_jobs`
                     SET `status`="clip2", `clip1_url`=?, `clip1_local`=?, `frame1_path`=?,
                         `clip2_task_id`=?
                     WHERE `id`=?'
                )->execute([$clip1Url, $clip1Local, $frame1Url ?? $frame1Path, $res2['task_id'], $id]);
            } else {
                _lv_fail($pdo, $id, $uid, (float)$job['credit_cost'], 'Clip 2 submit: ' . $res2['error']);
            }
            break;

        // ── Clip 2 polling ───────────────────────────────────────────────────
        case 'clip2':
            if (!$job['clip2_task_id']) { _lv_fail($pdo, $id, $uid, (float)$job['credit_cost'], 'No clip2 task_id'); break; }
            $q = byteplus_query_task($job['clip2_task_id']);
            if (!$q['ok']) break;
            if ($q['status'] === 'queued' || $q['status'] === 'processing') break;
            if ($q['status'] === 'failed') {
                _lv_fail($pdo, $id, $uid, (float)$job['credit_cost'], 'Clip 2 failed: ' . $q['error_message']);
                break;
            }
            $clip2Url   = $q['video_url'];
            $frame2Url  = $q['last_frame_url'] ?? null; // from return_last_frame API param

            // Fallback: download + FFmpeg if API didn't return last_frame_url
            $clip2Local = null;
            $frame2Path = null;
            if (!$frame2Url && lv_ffmpeg_available()) {
                $clip2Local = BASE_PATH . "/uploads/long_video/clips/lv{$id}_clip2.mp4";
                if (lv_download_file($clip2Url, $clip2Local)) {
                    $frame2Path = lv_extract_last_frame($clip2Local, $id, 2);
                    $frame2Url  = $frame2Path
                        ? rtrim(BASE_URL, '/') . '/uploads/long_video/frames/' . basename($frame2Path)
                        : null;
                }
            }

            // Optional hero frame for clip 3 (user-uploaded ending image)
            $heroFrameUrl = null;
            if ($job['hero_frame_path'] && is_file($job['hero_frame_path'])) {
                $heroFrameUrl = rtrim(BASE_URL, '/') . '/uploads/long_video/frames/'
                    . rawurlencode(basename($job['hero_frame_path']));
            }

            $res3 = $frame2Url
                ? byteplus_create_i2v_task($job['prompt3'], $frame2Url, $job['resolution'] ?: '1080p', 10, $heroFrameUrl)
                : byteplus_create_task($job['prompt3'], $job['resolution'] ?: '1080p', 10);

            if ($res3['ok']) {
                $pdo->prepare(
                    'UPDATE `long_video_jobs`
                     SET `status`="clip3", `clip2_url`=?, `clip2_local`=?, `frame2_path`=?,
                         `clip3_task_id`=?
                     WHERE `id`=?'
                )->execute([$clip2Url, $clip2Local, $frame2Url ?? $frame2Path, $res3['task_id'], $id]);
            } else {
                _lv_fail($pdo, $id, $uid, (float)$job['credit_cost'], 'Clip 3 submit: ' . $res3['error']);
            }
            break;

        // ── Clip 3 polling ───────────────────────────────────────────────────
        case 'clip3':
            if (!$job['clip3_task_id']) { _lv_fail($pdo, $id, $uid, (float)$job['credit_cost'], 'No clip3 task_id'); break; }
            $q = byteplus_query_task($job['clip3_task_id']);
            if (!$q['ok']) break;
            if ($q['status'] === 'queued' || $q['status'] === 'processing') break;
            if ($q['status'] === 'failed') {
                _lv_fail($pdo, $id, $uid, (float)$job['credit_cost'], 'Clip 3 failed: ' . $q['error_message']);
                break;
            }
            $clip3Url   = $q['video_url'];
            $clip3Local = BASE_PATH . "/uploads/long_video/clips/lv{$id}_clip3.mp4";
            lv_download_file($clip3Url, $clip3Local);

            $pdo->prepare(
                'UPDATE `long_video_jobs`
                 SET `status`="stitching", `clip3_url`=?, `clip3_local`=?
                 WHERE `id`=?'
            )->execute([$clip3Url, is_file($clip3Local) ? $clip3Local : null, $id]);
            break;

        // ── Stitch ───────────────────────────────────────────────────────────
        case 'stitching':
            $c1 = $job['clip1_local'] ?? '';
            $c2 = $job['clip2_local'] ?? '';
            $c3 = $job['clip3_local'] ?? '';

            if (!is_file($c1) || !is_file($c2) || !is_file($c3)) {
                // Files not present — re-download any that are missing
                foreach (['clip1' => 1, 'clip2' => 2, 'clip3' => 3] as $col => $num) {
                    $localCol = "{$col}_local";
                    $urlCol   = "{$col}_url";
                    $path     = $job[$localCol] ?? '';
                    if (!is_file($path) && !empty($job[$urlCol])) {
                        $dest = BASE_PATH . "/uploads/long_video/clips/lv{$id}_{$col}.mp4";
                        if (lv_download_file($job[$urlCol], $dest)) {
                            $pdo->prepare("UPDATE `long_video_jobs` SET `{$localCol}`=? WHERE `id`=?")
                                ->execute([$dest, $id]);
                            // Re-read for this run
                            switch ($col) { case 'clip1': $c1 = $dest; break; case 'clip2': $c2 = $dest; break; case 'clip3': $c3 = $dest; break; }
                        }
                    }
                }
            }

            if (!is_file($c1) || !is_file($c2) || !is_file($c3)) {
                error_log("[LongVideo] #$id: clip files missing for stitch, will retry");
                break; // retry next cron run
            }

            if (!lv_ffmpeg_available()) {
                // No FFmpeg — serve individual clip URLs as a fallback
                $finalUrl = $job['clip1_url'] . '|' . $job['clip2_url'] . '|' . $job['clip3_url'];
                $pdo->prepare(
                    'UPDATE `long_video_jobs`
                     SET `status`="completed", `final_video_url`=?, `completed_at`=NOW()
                     WHERE `id`=?'
                )->execute([$finalUrl, $id]);
                error_log("[LongVideo] #$id: FFmpeg not available — saved clip URLs as final");
                break;
            }

            $stitched = lv_stitch_clips($c1, $c2, $c3, $id);
            if ($stitched) {
                $finalUrl = rtrim(BASE_URL, '/') . '/uploads/long_video/final/lv' . $id . '_final.mp4';
                $pdo->prepare(
                    'UPDATE `long_video_jobs`
                     SET `status`="completed", `final_video_url`=?, `final_local`=?, `completed_at`=NOW()
                     WHERE `id`=?'
                )->execute([$finalUrl, $stitched, $id]);
                error_log("[LongVideo] #$id: completed — $finalUrl");
            } else {
                _lv_fail($pdo, $id, $uid, (float)$job['credit_cost'], 'FFmpeg stitch failed');
            }
            break;
    }
}

/**
 * Mark a long video job as failed and refund credits.
 */
function _lv_fail(\PDO $pdo, int $id, int $uid, float $cost, string $msg): void
{
    error_log("[LongVideo] #$id FAILED: $msg");
    $ownTx = !$pdo->inTransaction();
    if ($ownTx) $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE `long_video_jobs`
             SET `status`="failed", `error_message`=?
             WHERE `id`=?'
        )->execute([$msg, $id]);
        wallet_refund($uid, $cost, 'long_video_job', $id, 'Auto-refund: 30s Ad #' . $id . ' failed');
        $pdo->prepare(
            'UPDATE `long_video_jobs` SET `status`="refunded", `refunded_at`=NOW() WHERE `id`=?'
        )->execute([$id]);
        if ($ownTx) $pdo->commit();
    } catch (\Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) $pdo->rollBack();
        error_log("[LongVideo] _lv_fail DB error: " . $e->getMessage());
    }
}
