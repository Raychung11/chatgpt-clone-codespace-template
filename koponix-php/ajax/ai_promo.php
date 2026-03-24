<?php
// AJAX: Promo Generator
require_once dirname(__DIR__) . '/functions.php';
header('Content-Type: application/json');

$input    = json_decode(file_get_contents('php://input'), true);
$title    = $input['title']    ?? '';
$category = $input['category'] ?? '';
$area     = $input['area']     ?? '';
$price    = $input['price']    ?? '';
$desc     = $input['desc']     ?? '';
$type     = $input['type']     ?? 'Facebook/Instagram post caption';

$prompt = "Write a {$type} for the following Koponix member service:\n"
    . "Service Title: {$title}\nCategory: {$category}\nArea: {$area}\n"
    . "Price: {$price}\nDescription: {$desc}\n\n"
    . "Rules: Keep it professional and authentic. No exaggerated claims. "
    . "Include a call-to-action. Write in Malaysian business English. "
    . "Do not add hashtags unless it's a social media post.";

$promo = claude_api([['role'=>'user','content'=>$prompt]], KOPONIX_SYSTEM_PROMPT, 400, CLAUDE_MODEL);
echo json_encode(['promo' => trim($promo)]);
