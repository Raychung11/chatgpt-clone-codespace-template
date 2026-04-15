<?php
// AJAX: AI Market Demand Insight
require_once dirname(__DIR__) . '/functions.php';
header('Content-Type: application/json');

$input      = json_decode(file_get_contents('php://input'), true);
$req_by_cat = $input['req_by_cat'] ?? [];
$sel_by_cat = $input['sel_by_cat'] ?? [];
$req_by_loc = $input['req_by_loc'] ?? [];
$total_req  = (int)($input['total_req'] ?? 0);

if (!$req_by_cat) {
    echo json_encode(['insight' => 'Not enough data yet — be the first to submit a request!']);
    exit;
}

// Build readable summary
$demand_lines = [];
foreach ($req_by_cat as $cat => $cnt) {
    $supply = $sel_by_cat[$cat] ?? 0;
    $demand_lines[] = "- {$cat}: {$cnt} requests, {$supply} providers available";
}

$loc_lines = [];
foreach ($req_by_loc as $loc => $cnt) {
    $loc_lines[] = "{$loc} ({$cnt})";
}

$prompt = "You are analysing the service request data for Koperasi Kakitangan Bank Rakyat's Koponix marketplace.\n\n"
    . "Total requests submitted: {$total_req}\n\n"
    . "Demand vs Supply by category:\n" . implode("\n", $demand_lines) . "\n\n"
    . "Most active locations: " . implode(', ', $loc_lines) . "\n\n"
    . "Write a concise market insight (4-6 sentences) that:\n"
    . "1. Identifies the top 2-3 most in-demand service categories\n"
    . "2. Highlights any categories with unmet demand (high requests, low providers) — call these 'opportunity gaps'\n"
    . "3. Mentions the busiest location(s)\n"
    . "4. Ends with one practical recommendation for koperasi members\n\n"
    . "Write in simple Malaysian business English. Be specific with numbers. No bullet points — flowing sentences only.";

$insight = claude_api(
    [['role' => 'user', 'content' => $prompt]],
    KOPONIX_SYSTEM_PROMPT,
    300,
    CLAUDE_MODEL
);

echo json_encode(['insight' => trim($insight)]);
