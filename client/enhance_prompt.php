<?php
declare(strict_types=1);

/**
 * client/enhance_prompt.php
 * AJAX endpoint — takes a rough prompt, returns LLM-enhanced version.
 * Called by the generate page "Enhance" button.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/prompt_enhancer.php';

boot_session();
$user = require_auth('/public/login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    json_response(['error' => 'Method not allowed'], 405);
}

csrf_verify();

$prompt = trim($_POST['prompt'] ?? '');
if (mb_strlen($prompt) < 5) {
    json_response(['ok' => false, 'error' => 'Prompt too short.']);
}

$result = enhance_video_prompt($prompt);
json_response($result);
