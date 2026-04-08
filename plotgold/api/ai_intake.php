<?php
/**
 * PlotGold Malaysia — AI / WhatsApp Intake API
 * POST JSON: handle AI-driven lead intake and store conversations
 * This is the webhook/API endpoint that an AI layer (n8n, Make, or custom) can call.
 */
require_once __DIR__ . '/../inc/bootstrap.php';

header('Content-Type: application/json');

// Authenticate via API key (set in settings)
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$validKey = get_setting('ai_api_key', '');
if ($validKey && $apiKey !== $validKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST required.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload.']);
    exit;
}

$flowType   = $payload['flow_type']    ?? 'general';
$channel    = in_array($payload['channel'] ?? '', ['web','whatsapp','api']) ? $payload['channel'] : 'api';
$sessionKey = $payload['session_key']  ?? bin2hex(random_bytes(16));
$messages   = $payload['messages']     ?? [];

// Contact info
$contactName  = clean($payload['contact_name']  ?? '');
$contactPhone = clean($payload['contact_phone'] ?? '');
$contactEmail = clean_email($payload['contact_email'] ?? '');
$intentSummary= clean($payload['intent_summary'] ?? '');
$urgencyFlag  = !empty($payload['urgency_flag']) ? 1 : 0;
$aiScore      = (int)($payload['ai_score'] ?? 0);
$aiTags       = clean($payload['ai_tags'] ?? '');

$leadType = match($flowType) {
    'seller_intake' => 'seller',
    'buyer_intake'  => 'buyer',
    'urgent'        => 'urgent',
    'planning'      => 'planning',
    'provider'      => 'provider',
    default         => 'general',
};

try {
    // Find or create conversation
    $conv = Database::fetchOne('SELECT * FROM ai_conversations WHERE session_key = ?', [$sessionKey]);

    if (!$conv) {
        $convId = Database::insert(
            'INSERT INTO ai_conversations (session_key, channel, flow_type, status) VALUES (?, ?, ?, ?)',
            [$sessionKey, $channel, $flowType, 'active']
        );
    } else {
        $convId = $conv['id'];
    }

    // Store messages
    foreach ($messages as $msg) {
        $role    = in_array($msg['role'] ?? '', ['user','assistant','system']) ? $msg['role'] : 'user';
        $content = clean($msg['content'] ?? '');
        if ($content) {
            Database::query(
                'INSERT INTO ai_messages (conversation_id, role, content, metadata_json) VALUES (?, ?, ?, ?)',
                [$convId, $role, $content, isset($msg['meta']) ? json_encode($msg['meta']) : null]
            );
        }
    }

    // Update message count
    Database::query('UPDATE ai_conversations SET total_messages = total_messages + ? WHERE id = ?',
        [count($messages), $convId]);

    // Create or update lead if contact info present
    $leadId = null;
    if ($contactName || $contactPhone) {
        $existing = Database::fetchOne(
            'SELECT id FROM ai_leads WHERE contact_phone = ? OR contact_email = ? ORDER BY created_at DESC LIMIT 1',
            [$contactPhone, $contactEmail]
        );

        if ($existing) {
            $leadId = $existing['id'];
            Database::query(
                'UPDATE ai_leads SET intent_summary = ?, ai_score = ?, ai_tags = ?, urgency_flag = ?, updated_at = NOW() WHERE id = ?',
                [$intentSummary, $aiScore, $aiTags, $urgencyFlag, $leadId]
            );
        } else {
            $uuid     = pg_uuid();
            $leadCode = 'LD-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $leadId   = Database::insert(
                'INSERT INTO ai_leads (uuid, lead_code, lead_type, contact_name, contact_email, contact_phone,
                 channel, intent_summary, ai_score, ai_tags, urgency_flag, crm_status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $uuid, $leadCode, $leadType,
                    $contactName, $contactEmail, $contactPhone,
                    $channel, $intentSummary, $aiScore, $aiTags, $urgencyFlag, 'new',
                ]
            );
        }

        // Link conversation to lead
        Database::query('UPDATE ai_conversations SET lead_id = ? WHERE id = ?', [$leadId, $convId]);

        // Flag urgency for immediate attention
        if ($urgencyFlag) {
            Database::query(
                'INSERT INTO crm_status_logs (lead_id, from_status, to_status, note) VALUES (?, ?, ?, ?)',
                [$leadId, null, 'new', 'Urgency flag set by AI intake']
            );
        }
    }

    // Mark conversation complete if flagged
    if (!empty($payload['completed'])) {
        Database::query('UPDATE ai_conversations SET status = ?, ended_at = NOW() WHERE id = ?', ['completed', $convId]);
    }

    echo json_encode([
        'success'         => true,
        'conversation_id' => $convId,
        'lead_id'         => $leadId,
        'session_key'     => $sessionKey,
    ]);

} catch (Throwable $e) {
    error_log('AI Intake error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal error.']);
}
