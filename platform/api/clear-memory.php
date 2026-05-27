<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai-memory.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$module = trim($_POST['module'] ?? '');
AIMemory::clear(Auth::id(), $module);
echo json_encode(['ok' => true]);
