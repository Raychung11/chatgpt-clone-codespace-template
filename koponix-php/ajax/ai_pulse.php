<?php
// AJAX: Monthly Report — AI Platform Pulse narrative
require_once dirname(__DIR__) . '/functions.php';
header('Content-Type: application/json');
verify_csrf_ajax();

$input = json_decode(file_get_contents('php://input'), true);
$stats = [
    'total_sellers'  => (int)($input['total_sellers']  ?? 0),
    'active_sellers' => (int)($input['active_sellers'] ?? 0),
    'total_requests' => (int)($input['total_requests'] ?? 0),
    'total_members'  => (int)($input['total_members']  ?? 0),
    'top_category'   => $input['top_category']         ?? 'N/A',
    'month'          => $input['month']                ?? date('F Y'),
];

$prompt = "Write a 3-sentence platform activity summary for a koperasi digital marketplace called Koponix for {$stats['month']}:\n"
    . "- Total listings: {$stats['total_sellers']} ({$stats['active_sellers']} active)\n"
    . "- Buyer requests: {$stats['total_requests']}\n"
    . "- Registered members: {$stats['total_members']}\n"
    . "- Top service category: {$stats['top_category']}\n\n"
    . "Write in a professional but warm tone suitable for a koperasi report. "
    . "Highlight growth areas and suggest one improvement action. "
    . "Use Malaysian business English.";

if (!ai_rate_limit('pulse', 10)) {
    echo json_encode(['summary' => 'Please wait before requesting another summary.']);
    exit;
}
$summary = claude_api([['role'=>'user','content'=>$prompt]], KOPONIX_SYSTEM_PROMPT, 300, CLAUDE_MODEL_OPUS);
echo json_encode(['summary' => trim($summary)]);
