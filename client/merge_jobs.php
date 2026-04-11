<?php
declare(strict_types=1);

/**
 * client/merge_jobs.php
 * Server-side video merge using FFmpeg.
 *
 * POST JSON: { "job_ids": [13, 12, ...] }   (ordered — first job plays first)
 *
 * On success: streams an MP4 file directly to the browser.
 * On error:   returns JSON { "error": "..." } with a 4xx/5xx status.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';

boot_session();
$user = require_auth('/public/login.php');
$uid  = (int)$user['id'];
$pdo  = db();

set_time_limit(300); // FFmpeg + downloads may take a while

// ── Parse request ─────────────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || empty($data['job_ids']) || !is_array($data['job_ids'])) {
    json_response(['error' => 'Missing or invalid job_ids'], 400);
}

$jobIds = array_values(array_map('intval', $data['job_ids']));
if (count($jobIds) < 2 || count($jobIds) > 30) {
    json_response(['error' => 'Between 2 and 30 clips required'], 400);
}

// ── Verify ownership and fetch CDN URLs ───────────────────────────────────────
$placeholders = implode(',', array_fill(0, count($jobIds), '?'));
$stmt = $pdo->prepare(
    "SELECT vj.id, vo.cdn_url
     FROM video_jobs vj
     JOIN video_outputs vo ON vo.job_id = vj.id
     WHERE vj.id IN ($placeholders) AND vj.user_id = ?"
);
$stmt->execute([...$jobIds, $uid]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Index by job_id
$byId = [];
foreach ($rows as $row) {
    $byId[(int)$row['id']] = $row['cdn_url'];
}

// Build ordered CDN URL list, check each job was found
$cdnUrls = [];
foreach ($jobIds as $jid) {
    if (empty($byId[$jid])) {
        json_response(['error' => "Job #$jid not found or does not belong to your account"], 403);
    }
    $cdnUrls[] = $byId[$jid];
}

// ── Check FFmpeg is available ─────────────────────────────────────────────────
// Check local project bin/ first (static binary, no root needed),
// then fall back to system PATH.
$localBin = BASE_PATH . '/bin/ffmpeg';
if (is_executable($localBin)) {
    $ffmpeg = $localBin;
} else {
    $ffmpeg = trim((string)shell_exec('which ffmpeg 2>/dev/null'));
    if (!$ffmpeg) {
        $ffmpeg = trim((string)shell_exec('command -v ffmpeg 2>/dev/null'));
    }
}
if (!$ffmpeg || !file_exists($ffmpeg)) {
    json_response([
        'error' => 'FFmpeg is not installed. '
            . 'Run: sudo apt-get install ffmpeg  '
            . 'OR place a static binary at ' . BASE_PATH . '/bin/ffmpeg',
    ], 501);
}

// ── Download clips + merge ────────────────────────────────────────────────────
$tmpDir = sys_get_temp_dir() . '/merge_' . bin2hex(random_bytes(8));
if (!mkdir($tmpDir, 0700, true)) {
    json_response(['error' => 'Could not create temp directory'], 500);
}

try {
    // 1. Download each clip
    $inputFiles = [];
    foreach ($cdnUrls as $i => $url) {
        $tmpFile = $tmpDir . "/clip_{$i}.mp4";
        $fh = fopen($tmpFile, 'wb');
        if (!$fh) throw new \RuntimeException("Cannot open temp file for clip $i");

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FILE           => $fh,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_USERAGENT      => 'Mozilla/5.0',
        ]);
        $ok   = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        fclose($fh);

        if (!$ok || $code < 200 || $code >= 300) {
            throw new \RuntimeException("Failed to download clip $i: HTTP $code $err");
        }
        if (filesize($tmpFile) < 1024) {
            throw new \RuntimeException("Clip $i downloaded too small — URL may have expired");
        }
        $inputFiles[] = $tmpFile;
    }

    // 2. Build concat list file
    $concatFile = $tmpDir . '/concat.txt';
    $lines = [];
    foreach ($inputFiles as $f) {
        // FFmpeg concat demuxer requires single-quoted paths with escaped quotes
        $lines[] = "file '" . str_replace("'", "'\\''", $f) . "'";
    }
    file_put_contents($concatFile, implode("\n", $lines) . "\n");

    // 3. Run FFmpeg — concat demuxer with stream copy (no re-encode, very fast)
    $outputFile = $tmpDir . '/merged.mp4';
    $cmd = escapeshellarg($ffmpeg)
         . ' -f concat -safe 0'
         . ' -i ' . escapeshellarg($concatFile)
         . ' -c copy'
         . ' -movflags +faststart'   // moov atom at front for streaming
         . ' ' . escapeshellarg($outputFile)
         . ' 2>&1';

    $ffOut = shell_exec($cmd);

    if (!file_exists($outputFile) || filesize($outputFile) < 1024) {
        // Try again with re-encode (in case codec mismatch prevents copy)
        $outputFile2 = $tmpDir . '/merged2.mp4';
        $cmd2 = escapeshellarg($ffmpeg)
              . ' -f concat -safe 0'
              . ' -i ' . escapeshellarg($concatFile)
              . ' -c:v libx264 -preset fast -crf 23'
              . ' -c:a aac -b:a 128k'
              . ' -movflags +faststart'
              . ' ' . escapeshellarg($outputFile2)
              . ' 2>&1';
        $ffOut2 = shell_exec($cmd2);

        if (!file_exists($outputFile2) || filesize($outputFile2) < 1024) {
            $errMsg = "FFmpeg error:\n" . substr($ffOut . "\n" . ($ffOut2 ?? ''), -800);
            error_log('[merge_jobs] ' . $errMsg);
            throw new \RuntimeException('FFmpeg failed to produce output. Check server logs.');
        }
        $outputFile = $outputFile2;
    }

    // 4. Stream MP4 to browser
    $size     = filesize($outputFile);
    $filename = 'merged_' . date('Ymd_His') . '.mp4';

    header('Content-Type: video/mp4');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $size);
    header('Cache-Control: no-store');
    header('X-Merge-Size: ' . $size);

    readfile($outputFile);

} finally {
    // Clean up temp directory
    foreach (glob($tmpDir . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($tmpDir);
}
