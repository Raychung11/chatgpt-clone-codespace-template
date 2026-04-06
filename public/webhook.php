<?php
declare(strict_types=1);

/**
 * public/webhook.php
 * Payment gateway webhook receiver.
 *
 * Supports: Billplz (FPX), Stripe
 * Extend: add your gateway block below following the same pattern.
 *
 * Security: each gateway uses its own signature verification.
 *           NEVER trust the payload without verifying the signature.
 *
 * Setup:
 *   Billplz: Dashboard > API > Callback URL -> https://yourdomain.com/webhook.php?gateway=billplz
 *   Stripe:  Dashboard > Webhooks -> https://yourdomain.com/webhook.php?gateway=stripe
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/wallet.php';
require_once __DIR__ . '/../inc/mailer.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$gateway = strtolower($_GET['gateway'] ?? '');
$rawBody = file_get_contents('php://input');

// ── Route to gateway handler ──────────────────────────────────────────────────
match ($gateway) {
    'billplz' => handle_billplz($rawBody),
    'stripe'  => handle_stripe($rawBody),
    default   => (function () use ($gateway) {
        http_response_code(400);
        error_log("[webhook] Unknown gateway: $gateway");
        exit('Unknown gateway');
    })(),
};

// ============================================================================
// BILLPLZ (FPX / Malaysia)
// ============================================================================
function handle_billplz(string $raw): never
{
    parse_str($raw, $data);

    $xSignature = $data['x_signature'] ?? '';
    $secretKey  = setting('billplz_x_signature_key', '');

    // -- Verify X-Signature -------------------------------------------------
    // Billplz signs: bill_id|paid|paid_at (sorted alphabetically by key)
    // See: https://www.billplz.com/api#x-signature
    if ($secretKey) {
        $fields  = ['id','paid','paid_at','bill_id','type'];
        $payload = [];
        foreach ($fields as $f) {
            if (isset($data[$f])) $payload[$f] = $data[$f];
        }
        ksort($payload);
        $sigString = implode('|', $payload);
        $expected  = hash_hmac('sha256', $sigString, $secretKey);

        if (!hash_equals($expected, $xSignature)) {
            http_response_code(403);
            error_log('[webhook/billplz] Invalid X-Signature');
            exit('Invalid signature');
        }
    }

    $paid    = strtolower($data['paid'] ?? 'false') === 'true';
    $billId  = $data['id'] ?? '';     // Billplz bill ID
    $orderId = (int)($data['order_id'] ?? 0); // pass this as query param when creating bill

    if (!$paid || !$orderId) {
        http_response_code(200);
        exit('OK - not paid or missing order_id');
    }

    $result = process_payment_approval($orderId, 0 /* system */);

    if (!$result['ok']) {
        error_log("[webhook/billplz] Approval failed for order $orderId: " . $result['error']);
        http_response_code(200); // Always 200 to stop retries
        exit('OK');
    }

    log_activity('system', null, 'billplz_webhook', "Auto-approved order #$orderId via Billplz");
    http_response_code(200);
    exit('OK');
}

// ============================================================================
// STRIPE
// ============================================================================
function handle_stripe(string $raw): never
{
    $webhookSecret = setting('stripe_webhook_secret', '');
    $sigHeader     = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    // -- Verify Stripe signature --------------------------------------------
    if ($webhookSecret && $sigHeader) {
        $tolerance = 300; // 5 minutes
        $parts     = [];
        foreach (explode(',', $sigHeader) as $part) {
            [$k, $v]    = explode('=', $part, 2) + ['', ''];
            $parts[$k][] = $v;
        }
        $timestamp = (int)($parts['t'][0] ?? 0);
        $signatures = $parts['v1'] ?? [];

        if (abs(time() - $timestamp) > $tolerance) {
            http_response_code(400);
            error_log('[webhook/stripe] Timestamp too old');
            exit('Timestamp too old');
        }

        $signedPayload = $timestamp . '.' . $raw;
        $expected      = hash_hmac('sha256', $signedPayload, $webhookSecret);
        $valid         = false;
        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) { $valid = true; break; }
        }
        if (!$valid) {
            http_response_code(403);
            error_log('[webhook/stripe] Invalid signature');
            exit('Invalid signature');
        }
    }

    $event = json_decode($raw, true);
    if (!$event) {
        http_response_code(400);
        exit('Invalid JSON');
    }

    $type = $event['type'] ?? '';

    if ($type === 'checkout.session.completed' || $type === 'payment_intent.succeeded') {
        $metadata = $event['data']['object']['metadata'] ?? [];
        $orderId  = (int)($metadata['order_id'] ?? 0);

        if ($orderId) {
            $result = process_payment_approval($orderId, 0 /* system */);
            if (!$result['ok']) {
                error_log("[webhook/stripe] Approval failed for order $orderId: " . $result['error']);
            } else {
                log_activity('system', null, 'stripe_webhook', "Auto-approved order #$orderId via Stripe");
            }
        }
    }

    http_response_code(200);
    echo json_encode(['received' => true]);
    exit;
}
