<?php
declare(strict_types=1);

/**
 * client/video_proxy.php
 * Streams a BytePlus CDN video through the server as same-origin bytes.
 * Used by the editor so captureStream() works (cross-origin videos block it).
 *
 * GET params:
 *   job_id  — numeric video_jobs.id (must belong to the logged-in user)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';

boot_session();
$user = require_auth('/public/login.php');
$uid  = (int)$user['id'];
$pdo  = db();

$jobId = (int)($_GET['job_id'] ?? 0);
if (!$jobId) { http_response_code(400); exit('Missing job_id'); }

// Verify ownership and fetch URL
$stmt = $pdo->prepare(
    'SELECT vo.cdn_url
     FROM video_jobs vj
     JOIN video_outputs vo ON vo.job_id = vj.id
     WHERE vj.id = ? AND vj.user_id = ?
     LIMIT 1'
);
$stmt->execute([$jobId, $uid]);
$row = $stmt->fetch();

if (!$row || empty($row['cdn_url'])) {
    http_response_code(404); exit('Video not found');
}

$cdnUrl = $row['cdn_url'];

// Build curl headers — forward Range if browser sent one (supports seeking)
$curlHeaders = ['User-Agent: Mozilla/5.0'];
if (!empty($_SERVER['HTTP_RANGE'])) {
    $curlHeaders[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
}

// Stream directly to output — no buffering entire file in memory
$responseCode   = 200;
$contentType    = 'video/mp4';
$contentLength  = null;
$contentRange   = null;
$headersStarted = false;

$ch = curl_init($cdnUrl);
curl_setopt_array($ch, [
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER     => $curlHeaders,
    CURLOPT_RETURNTRANSFER => false,

    // Intercept upstream response headers
    CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$responseCode, &$contentType, &$contentLength, &$contentRange) {
        $h = trim($header);
        if (preg_match('#^HTTP/[\d.]+ (\d+)#i', $h, $m)) {
            $responseCode = (int)$m[1];
        } elseif (stripos($h, 'Content-Type:') === 0) {
            $contentType = trim(explode(':', $h, 2)[1]);
            $contentType = explode(';', $contentType)[0]; // strip charset
        } elseif (stripos($h, 'Content-Length:') === 0) {
            $contentLength = (int)trim(explode(':', $h, 2)[1]);
        } elseif (stripos($h, 'Content-Range:') === 0) {
            $contentRange = trim(explode(':', $h, 2)[1]);
        }
        return strlen($header);
    },

    // Stream body to browser as it arrives
    CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$headersStarted, &$responseCode, &$contentType, &$contentLength, &$contentRange) {
        if (!$headersStarted) {
            $headersStarted = true;
            http_response_code($responseCode === 206 ? 206 : 200);
            header('Content-Type: '  . ($contentType ?: 'video/mp4'));
            header('Accept-Ranges: bytes');
            header('Cache-Control: private, max-age=3600');
            if ($contentLength !== null) header('Content-Length: ' . $contentLength);
            if ($contentRange !== null)  header('Content-Range: '  . $contentRange);
        }
        echo $data;
        // flush() — let output buffer/PHP handle it
        return strlen($data);
    },
]);

curl_exec($ch);
curl_close($ch);
