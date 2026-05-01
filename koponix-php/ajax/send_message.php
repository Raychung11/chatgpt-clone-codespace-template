<?php
// AJAX: Send a chat message + optional AI reply
require_once dirname(__DIR__) . '/functions.php';
header('Content-Type: application/json');

session_start_safe();
verify_csrf_ajax();
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$member  = current_member();
$kop_id  = $member['koperasi_id'];
$mem_name= $member['name'];

$input   = json_decode(file_get_contents('php://input'), true);
$conv_id = $input['conv_id']  ?? '';
$text    = trim($input['text'] ?? '');
$ask_ai  = !empty($input['ask_ai']);
$ai_only = !empty($input['ai_only']);
$subject = $input['subject'] ?? 'Service enquiry';

// Verify conversation belongs to this member
$conv = get_conversation($conv_id);
if (!$conv || !in_array($kop_id, [$conv['buyer_kop_id'], $conv['seller_kop_id']])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Save user message (unless AI-only)
if (!$ai_only && $text) {
    add_message($conv_id, $kop_id, $mem_name, $text, false);
    // Notify the other participant
    $recipient = ($conv['buyer_kop_id'] === $kop_id) ? $conv['seller_kop_id'] : $conv['buyer_kop_id'];
    notify_new_message($recipient, $mem_name, $conv_id);
}

$ai_reply = null;
if ($ask_ai) {
    // Build context from last 10 messages
    $history = get_messages($conv_id);
    $history = array_slice($history, -10);

    $claude_msgs = [];
    foreach ($history as $m) {
        $role = ($m['is_ai'] || $m['sender_kop_id'] !== $kop_id) ? 'assistant' : 'user';
        // Anthropic doesn't allow consecutive same-role messages; merge them
        if ($claude_msgs && $claude_msgs[array_key_last($claude_msgs)]['role'] === $role) {
            $claude_msgs[array_key_last($claude_msgs)]['content'] .= "\n" . $m['text'];
        } else {
            $claude_msgs[] = ['role' => $role, 'content' => $m['text']];
        }
    }

    // If no history or ends with assistant, add a user prompt
    if (!$claude_msgs || $claude_msgs[array_key_last($claude_msgs)]['role'] !== 'user') {
        $summary = $ai_only ? "Summarise the conversation and suggest the next step." : $text;
        $claude_msgs[] = ['role' => 'user', 'content' => $summary];
    }

    // Ensure starts with user
    while ($claude_msgs && $claude_msgs[0]['role'] !== 'user') array_shift($claude_msgs);

    $system = KOPONIX_SYSTEM_PROMPT
        . "\n\nYou are assisting in a conversation between members about: '{$subject}'. "
        . "Give practical, friendly advice to help them connect and agree on the service. "
        . "Keep replies concise (2-4 sentences).";

    $ai_reply = claude_api($claude_msgs, $system, 300, CLAUDE_MODEL);
    add_message($conv_id, 'AI', 'Koponix AI', $ai_reply, true);
}

echo json_encode(['ok' => true, 'ai_reply' => $ai_reply]);
