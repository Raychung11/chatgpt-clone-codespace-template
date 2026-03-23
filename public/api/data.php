<?php
/**
 * STRate AI — API: Data Endpoints
 * GET  /api/data.php?action=csv_template
 * POST /api/data.php?action=import_csv   (multipart form with csv_file)
 * GET  /api/data.php?action=whatsapp&property_id=1
 */
require_once __DIR__ . '/../../src/bootstrap.php';

$action = $_GET['action'] ?? '';

switch ($action) {

    // ─── CSV Template Download ───────────────────────────────────────────
    case 'csv_template':
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="market_data_template.csv"');
        echo MarketData::csvTemplate();
        exit;

    // ─── CSV Import ──────────────────────────────────────────────────────
    case 'import_csv':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['csv_file'])) {
            header('Location: /market.php?msg=no_file');
            exit;
        }
        $content = file_get_contents($_FILES['csv_file']['tmp_name']);
        $result  = MarketData::importCsv($content);
        $ok      = count($result['ok']);
        $err     = count($result['errors']);
        header("Location: /market.php?msg=import&ok={$ok}&err={$err}");
        exit;

    // ─── WhatsApp preview ────────────────────────────────────────────────
    case 'whatsapp':
        header('Content-Type: application/json');
        $propId = (int)($_GET['property_id'] ?? 0);
        $prop   = $propId ? Database::getProperty($propId) : null;
        if (!$prop) { echo json_encode(['ok' => false, 'message' => 'Not found']); exit; }

        $today = date('Y-m-d');
        $rec   = Database::getLatestRecommendation($propId, $today);
        if (!$rec) { echo json_encode(['ok' => false, 'message' => 'No recommendation for today.']); exit; }

        $reply = AiExplainer::whatsappReply($prop['name'], $rec);
        echo json_encode(['ok' => true, 'message' => $reply]);
        exit;

    default:
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Unknown action.']);
        exit;
}
