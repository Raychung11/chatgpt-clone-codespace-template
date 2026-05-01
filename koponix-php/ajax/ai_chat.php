<?php
// AJAX: General AI chat (AI Assistant page)
require_once dirname(__DIR__) . '/functions.php';
header('Content-Type: application/json');
verify_csrf_ajax();

$input    = json_decode(file_get_contents('php://input'), true);
$messages = $input['messages'] ?? [];

if (!$messages) {
    echo json_encode(['reply' => 'No message provided.']);
    exit;
}

// Sanitise: keep only role+content, max last 20 messages
$messages = array_slice($messages, -20);
$messages = array_map(fn($m) => ['role' => $m['role'], 'content' => $m['content']], $messages);

// Ensure starts with user role
while ($messages && $messages[0]['role'] !== 'user') array_shift($messages);
if (!$messages) {
    echo json_encode(['reply' => 'Please send a message.']);
    exit;
}

if (!ai_rate_limit('chat')) {
    echo json_encode(['reply' => 'Please wait a moment before sending another message.']);
    exit;
}
$reply = claude_api($messages, KOPONIX_SYSTEM_PROMPT, 512, CLAUDE_MODEL);
echo json_encode(['reply' => $reply]);
