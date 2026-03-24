<?php
// AJAX: AI Insight tagline for a seller card
require_once dirname(__DIR__) . '/functions.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$title    = $input['title']    ?? '';
$category = $input['category'] ?? '';
$area     = $input['area']     ?? '';
$price    = $input['price']    ?? '';
$desc     = substr($input['description'] ?? '', 0, 200);

$prompt = "In one sentence (max 18 words), write a 'Best for:' tagline for this service provider:\n"
    . "Service: {$title}\nCategory: {$category}\nArea: {$area}\nPrice: {$price}\nDescription: {$desc}\n\n"
    . "Start with 'Best for' and be specific. No quotes.";

$insight = claude_api([['role'=>'user','content'=>$prompt]], KOPONIX_SYSTEM_PROMPT, 60, CLAUDE_MODEL);
echo json_encode(['insight' => trim($insight)]);
