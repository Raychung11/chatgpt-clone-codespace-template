<?php
/* ============================================================
   AJAX endpoint — powers all customer AI modules
   POST /api/ai-generate.php
   Body: { module: string, ...fields }
   Returns: { ok: bool, result: string, error: string }
   ============================================================ */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/claude.php';

header('Content-Type: application/json');

/* Must be logged in */
if (!Auth::check()) {
    echo json_encode(['ok' => false, 'error' => 'Please log in to use AI tools.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request method.']);
    exit;
}

$module = trim($_POST['module'] ?? '');

/* ── Helpers ── */
function req(string $key, string $default = ''): string {
    return trim($_POST[$key] ?? $default);
}
function sanitize(string $s): string {
    return htmlspecialchars(strip_tags($s), ENT_QUOTES, 'UTF-8');
}

/* ── Route by module ── */
switch ($module) {

    /* ── Email Writer ── */
    case 'email':
        $type      = sanitize(req('email_type', 'professional'));
        $recipient = sanitize(req('recipient'));
        $context   = sanitize(req('context'));
        $tone      = sanitize(req('tone', 'professional'));
        $length    = sanitize(req('length', 'medium'));

        if (!$context) { echo json_encode(['ok'=>false,'error'=>'Please describe what the email is about.']); exit; }

        $system = "You are an expert business email writer. Write clear, effective, professional emails. Output ONLY the email (subject line first, then body). Do not add commentary.";
        $user   = "Write a {$tone} {$type} email.
Recipient: {$recipient}
Key points / context: {$context}
Length: {$length} (short=3-5 sentences, medium=2-3 paragraphs, long=4+ paragraphs)

Format:
Subject: [subject line here]

[email body here]";

        $result = Claude::generate($system, $user, 800);
        echo json_encode($result);
        break;

    /* ── Social Post Generator ── */
    case 'social':
        $business  = sanitize(req('business'));
        $topic     = sanitize(req('topic'));
        $platforms = array_map('trim', explode(',', req('platforms', 'Facebook,Instagram,LinkedIn')));
        $tone      = sanitize(req('tone', 'engaging'));
        $cta       = sanitize(req('cta'));

        if (!$topic) { echo json_encode(['ok'=>false,'error'=>'Please describe your topic or product.']); exit; }

        $platformList = implode(', ', $platforms);
        $ctaLine = $cta ? "Call to action: {$cta}" : '';

        $system = "You are a social media expert creating posts for SMEs. Write engaging, platform-appropriate posts. Include relevant emojis. Respect character limits (Twitter: 280 chars, others: flexible).";
        $user   = "Business: {$business}
Topic/Product: {$topic}
Tone: {$tone}
{$ctaLine}

Write separate posts for: {$platformList}

Format each as:
📱 [PLATFORM NAME]
[post content]
---";

        $result = Claude::generate($system, $user, 1200);
        echo json_encode($result);
        break;

    /* ── Invoice Generator ── */
    case 'invoice':
        $from      = sanitize(req('from_company'));
        $to        = sanitize(req('to_company'));
        $invNumber = sanitize(req('invoice_number'));
        $invDate   = sanitize(req('invoice_date'));
        $dueDate   = sanitize(req('due_date'));
        $items     = req('items'); // JSON string of line items
        $notes     = sanitize(req('notes'));
        $currency  = sanitize(req('currency', 'USD'));

        if (!$from || !$to) { echo json_encode(['ok'=>false,'error'=>'Please fill in the From and To company details.']); exit; }

        $itemsDecoded = json_decode($items, true) ?: [];
        $itemsText = '';
        $subtotal  = 0;
        foreach ($itemsDecoded as $item) {
            $desc  = htmlspecialchars($item['desc'] ?? '');
            $qty   = (float)($item['qty'] ?? 1);
            $price = (float)($item['price'] ?? 0);
            $total = $qty * $price;
            $subtotal += $total;
            $itemsText .= "- {$desc} | Qty: {$qty} | Unit: {$price} | Total: {$total}\n";
        }

        $system = "You are a professional invoice writer. Create clean, business-appropriate invoice content. Output ONLY valid HTML for the invoice body (inside a <div>). Use inline styles for formatting. Do not use external CSS or JS.";
        $user   = "Create a professional invoice with these details:

FROM: {$from}
TO: {$to}
Invoice #: {$invNumber}
Date: {$invDate}
Due Date: {$dueDate}
Currency: {$currency}

Line Items:
{$itemsText}
Subtotal: {$subtotal} {$currency}

Notes: {$notes}

Generate a well-formatted HTML invoice. Use a white background, clean table for line items, include totals row with subtotal and total. Professional style.";

        $result = Claude::generate($system, $user, 2000);
        echo json_encode($result);
        break;

    /* ── Leave Request AI helper ── */
    case 'leave_reason':
        $leaveType = sanitize(req('leave_type', 'annual'));
        $days      = (int)req('days', 1);
        $brief     = sanitize(req('brief'));

        if (!$brief) { echo json_encode(['ok'=>false,'error'=>'Please provide a brief reason.']); exit; }

        $system = "You are an HR communication assistant. Write polite, professional leave request messages. Keep it concise and respectful.";
        $user   = "Write a professional leave request message for:
Leave type: {$leaveType}
Number of days: {$days}
Brief reason: {$brief}

Output ONLY the leave request message (2-3 sentences). No greeting needed.";

        $result = Claude::generate($system, $user, 300);
        echo json_encode($result);
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Unknown module: ' . htmlspecialchars($module)]);
}
