<?php
declare(strict_types=1);

/**
 * client/delete_job.php
 * AJAX endpoint — delete a completed/failed/refunded video job and its output.
 * POST params: job_id (int), csrf token
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';

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
    json_response(['ok' => false, 'error' => 'Invalid job ID']);
}

// Verify ownership and that job is not actively running
$stmt = $pdo->prepare(
    'SELECT id, status FROM video_jobs WHERE id = ? AND user_id = ? LIMIT 1'
);
$stmt->execute([$jobId, $uid]);
$job = $stmt->fetch();

if (!$job) {
    json_response(['ok' => false, 'error' => 'Job not found']);
}

if (in_array($job['status'], ['queued', 'processing'], true)) {
    json_response(['ok' => false, 'error' => 'Cannot delete a job that is still running. Cancel it first.']);
}

// Delete output then job (FK constraint order)
$pdo->prepare('DELETE FROM video_outputs WHERE job_id = ?')->execute([$jobId]);
$pdo->prepare('DELETE FROM video_jobs   WHERE id = ?  AND user_id = ?')->execute([$jobId, $uid]);

json_response(['ok' => true]);
