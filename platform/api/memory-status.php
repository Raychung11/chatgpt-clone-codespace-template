<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai-memory.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['ok' => false, 'count' => 0]);
    exit;
}

$module = trim($_GET['module'] ?? '');
$count  = $module ? AIMemory::count(Auth::id(), $module) : 0;
echo json_encode(['ok' => true, 'count' => $count]);
