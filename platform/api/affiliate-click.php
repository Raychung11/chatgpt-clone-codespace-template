<?php
// Record affiliate link clicks
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . (defined('SITE_URL') ? SITE_URL : '*'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$code = trim($data['code'] ?? '');
$page = trim($data['page'] ?? '');

if (!$code) {
    echo json_encode(['ok' => false]);
    exit;
}

try {
    $affiliate = DB::fetch("SELECT id FROM affiliates WHERE code = ? AND status = 'active' LIMIT 1", [$code]);
    if (!$affiliate) {
        echo json_encode(['ok' => false, 'msg' => 'unknown code']);
        exit;
    }

    // Avoid counting bots (basic check)
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (preg_match('/bot|crawler|spider|curl|wget/i', $ua)) {
        echo json_encode(['ok' => true, 'bot' => true]);
        exit;
    }

    // Deduplicate: skip if same IP clicked in last 24h for same affiliate
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $recent = DB::fetch(
        "SELECT id FROM affiliate_clicks WHERE affiliate_id = ? AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) LIMIT 1",
        [$affiliate['id'], $ip]
    );

    if (!$recent) {
        DB::insert('affiliate_clicks', [
            'affiliate_id' => $affiliate['id'],
            'ip_address'   => $ip,
            'user_agent'   => substr($ua, 0, 500),
            'landing_page' => substr($page, 0, 255),
            'referrer'     => substr($_SERVER['HTTP_REFERER'] ?? '', 0, 255),
        ]);

        DB::query(
            "UPDATE affiliates SET total_clicks = total_clicks + 1 WHERE id = ?",
            [$affiliate['id']]
        );
    }

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false]);
}
