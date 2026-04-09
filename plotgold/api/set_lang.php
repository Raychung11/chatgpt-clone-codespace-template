<?php
/**
 * PlotGold Malaysia — Language Switch API
 * POST { lang: 'en'|'zh' }
 * Sets session + cookie and returns JSON { success, lang }
 */
require_once __DIR__ . '/../inc/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$supported = ['en', 'zh'];
$lang      = clean($_POST['lang'] ?? '');

if (!in_array($lang, $supported, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unsupported language']);
    exit;
}

$_SESSION['lang'] = $lang;
setcookie('pg_lang', $lang, time() + (86400 * 365), '/', '', false, false);

echo json_encode(['success' => true, 'lang' => $lang]);
