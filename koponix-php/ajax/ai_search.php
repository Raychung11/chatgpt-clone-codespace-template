<?php
// AJAX: AI Smart Search — extract categories/locations from query
require_once dirname(__DIR__) . '/functions.php';
header('Content-Type: application/json');
verify_csrf_ajax();

$input = json_decode(file_get_contents('php://input'), true);
$query = trim($input['query'] ?? '');

if (!$query) { echo json_encode(['url' => null]); exit; }

$cat_list = implode(', ', categories());
$loc_list = implode(', ', locations());

$prompt = "A user is searching for a service on Koponix with this description:\n\"{$query}\"\n\n"
    . "Available categories: {$cat_list}\n"
    . "Available locations: {$loc_list}\n\n"
    . "Extract the user's intent and return a JSON object with exactly these keys:\n"
    . "{\"categories\": [...], \"locations\": [...], \"keywords\": \"...\"}\n"
    . "- categories: list of matching category names from the available list (0–3 items)\n"
    . "- locations: list of matching location names from the available list (0–2 items)\n"
    . "- keywords: short keyword string for text search (max 4 words, empty string if none)\n"
    . "Return ONLY the raw JSON object — no explanation, no markdown fences.";

if (!ai_rate_limit('search')) {
    echo json_encode(['url' => null]);
    exit;
}
$raw = claude_api([['role'=>'user','content'=>$prompt]], KOPONIX_SYSTEM_PROMPT, 200, CLAUDE_MODEL);

$url = null;
try {
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    $params = [];
    if (!empty($data['categories'][0])) $params['category'] = $data['categories'][0];
    if (!empty($data['locations'][0]))  $params['location']  = $data['locations'][0];
    if (!empty($data['keywords']))      $params['q']         = $data['keywords'];
    $url = 'find_services.php' . ($params ? '?' . http_build_query($params) : '');
} catch (Throwable $e) {
    // ignore parse error
}

echo json_encode(['url' => $url, 'raw' => $raw]);
