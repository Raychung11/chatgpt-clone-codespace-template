<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/claude.php';
require_once __DIR__ . '/../includes/projectos.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['ok' => false, 'error' => 'Login required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$projectId = (int)($_POST['project_id'] ?? 0);
$question  = trim($_POST['question'] ?? '');

if ($projectId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'project_id is required']);
    exit;
}

if ($question === '') {
    echo json_encode(['ok' => false, 'error' => 'question is required']);
    exit;
}

$context = ProjectOS::getProjectContext($projectId);

$systemPrompt = "You are ProjectOS AI Assistant, an expert project manager helping analyse and manage this project. Answer questions clearly and concisely. When asked for reports or summaries, use professional formatting.";

$userPrompt = $context . "\n\n---\n\nUser Question:\n" . $question;

$result = Claude::generate($systemPrompt, $userPrompt, 1500);

if (!$result['ok']) {
    echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'AI assistant failed']);
    exit;
}

echo json_encode(['ok' => true, 'result' => $result['result']]);
