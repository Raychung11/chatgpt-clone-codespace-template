<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
auth_require();

// ─── SilverDeals MY — Secure AJAX Upload Endpoint ────────────────────────────
// POST /api/upload.php
// Fields: file (multipart), context (avatars|verifications|deals)
// Returns JSON: { success, path, error }

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);
    exit;
}

if (!csrf_verify()) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Invalid CSRF token']);
    exit;
}

if (!rate_limit('upload', 20, 300)) {
    http_response_code(429);
    echo json_encode(['success'=>false,'error'=>'Too many uploads. Please wait.']);
    exit;
}

$user    = auth_user();
$context = preg_replace('/[^a-z_]/', '', strtolower($_POST['context'] ?? 'general'));
$allowed = ['avatars','verifications','deals','banners'];

if (!in_array($context, $allowed)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Invalid upload context']);
    exit;
}

// Role gate: only admins/merchants can upload deal/banner images
if (in_array($context, ['deals','banners'])) {
    if (!in_array($user['role'], [ROLE_MERCHANT, ROLE_ADMIN, ROLE_SUPERADMIN])) {
        http_response_code(403);
        echo json_encode(['success'=>false,'error'=>'Not authorised']);
        exit;
    }
}

if (empty($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'No file received']);
    exit;
}

$file = $_FILES['file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $msg = match($file['error']) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds size limit.',
        UPLOAD_ERR_NO_FILE  => 'No file uploaded.',
        default             => 'Upload error code ' . $file['error'],
    };
    echo json_encode(['success'=>false,'error'=>$msg]);
    exit;
}

if ($file['size'] > MAX_UPLOAD_BYTES) {
    echo json_encode(['success'=>false,'error'=>'File too large. Maximum ' . (MAX_UPLOAD_BYTES / 1024 / 1024) . ' MB.']);
    exit;
}

$mimeType = mime_content_type($file['tmp_name']);
if (!in_array($mimeType, ALLOWED_IMG_TYPES, true)) {
    echo json_encode(['success'=>false,'error'=>'Only JPG, PNG or WebP images are allowed.']);
    exit;
}

// Build path
$subDir = $context === 'verifications'
    ? UPLOAD_DIR . $context . '/' . $user['id'] . '/'
    : UPLOAD_DIR . $context . '/';

if (!is_dir($subDir) && !mkdir($subDir, 0755, true)) {
    error_log('[Upload] Could not create dir: ' . $subDir);
    echo json_encode(['success'=>false,'error'=>'Server upload directory error.']);
    exit;
}

$ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$safeName = $context . '_' . $user['id'] . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$destPath = $subDir . $safeName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    error_log('[Upload] move_uploaded_file failed to: ' . $destPath);
    echo json_encode(['success'=>false,'error'=>'Failed to save file.']);
    exit;
}

$publicPath = '/assets/img/uploads/' . $context . '/'
            . ($context === 'verifications' ? $user['id'] . '/' : '')
            . $safeName;

echo json_encode(['success'=>true,'path'=>$publicPath]);
