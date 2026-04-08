<?php
/**
 * PlotGold Malaysia — Enquiry API Endpoint
 * POST: Submit a new enquiry or listing enquiry
 */
require_once __DIR__ . '/../inc/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

csrf_enforce();

$listingId   = clean_int($_POST['listing_id']   ?? 0);
$providerId  = clean_int($_POST['provider_id']  ?? 0);
$enquiryType = clean($_POST['enquiry_type']     ?? 'general');
$subject     = clean($_POST['subject']          ?? '');
$message     = clean($_POST['message']          ?? '');
$contactName = clean($_POST['contact_name']     ?? '');
$contactEmail= clean_email($_POST['contact_email'] ?? '');
$contactPhone= clean($_POST['contact_phone']    ?? '');
$listingType = clean($_POST['listing_type']     ?? '');
$parkName    = clean($_POST['park_name']        ?? '');
$askingPrice = clean($_POST['asking_price']     ?? '');

// Validation
if (!$contactName) {
    echo json_encode(['success' => false, 'error' => 'Please provide your name.']);
    exit;
}
if (!$message && !$listingId) {
    // Auto-generate message for listing enquiries
    if ($contactName) {
        $message = "I am interested in this listing. Please contact me.";
    } else {
        echo json_encode(['success' => false, 'error' => 'Message is required.']);
        exit;
    }
}

// Build subject if not provided
if (!$subject) {
    if ($listingId) {
        $l = Database::fetchOne('SELECT listing_code, title FROM listings WHERE id = ?', [$listingId]);
        $subject = 'Listing Enquiry: ' . ($l['listing_code'] ?? '') . ' — ' . ($l['title'] ?? '');
    } elseif ($listingType) {
        $subject = 'Sell Inquiry: ' . $listingType . ($parkName ? ' at ' . $parkName : '');
        if ($askingPrice) $subject .= ' (RM ' . number_format((float)$askingPrice) . ')';
    } else {
        $subject = 'General Enquiry';
    }
}

// Create enquiry record
$uuid    = pg_uuid();
$code    = generate_enquiry_code();
$buyerId = null;
$priority = 'normal';

if (auth_check()) {
    $buyer = Database::fetchOne('SELECT id FROM buyers WHERE user_id = ?', [auth_user_id()]);
    $buyerId = $buyer['id'] ?? null;
}

if ($enquiryType === 'urgent') $priority = 'urgent';
if ($enquiryType === 'listing' && $listingId) {
    $urgency = Database::fetchOne('SELECT urgency_level FROM listings WHERE id = ?', [$listingId])['urgency_level'] ?? '';
    if (in_array($urgency, ['urgent','immediate'])) $priority = 'urgent';
}

try {
    $enquiryId = Database::insert(
        'INSERT INTO enquiries (uuid, enquiry_code, buyer_id, listing_id, provider_id, enquiry_type,
         subject, message, contact_name, contact_email, contact_phone, status, priority, source, ip_address)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $uuid, $code,
            $buyerId,
            $listingId ?: null,
            $providerId ?: null,
            $enquiryType,
            $subject, $message,
            $contactName, $contactEmail, $contactPhone,
            'new', $priority, 'web',
            ip_address(),
        ]
    );

    // Add initial message
    Database::query(
        'INSERT INTO enquiry_messages (enquiry_id, sender_type, message) VALUES (?, ?, ?)',
        [$enquiryId, 'buyer', $message]
    );

    // Update listing inquiry count
    if ($listingId) {
        Database::query('UPDATE listings SET inquiry_count = inquiry_count + 1 WHERE id = ?', [$listingId]);
    }

    // Create AI lead record for CRM
    $leadUuid = pg_uuid();
    $leadCode = 'LD-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    $leadType = match($enquiryType) {
        'listing'  => 'buyer',
        'provider' => 'provider',
        'urgent'   => 'urgent',
        'planning' => 'planning',
        default    => 'general',
    };

    Database::insert(
        'INSERT INTO ai_leads (uuid, lead_code, lead_type, contact_name, contact_email, contact_phone,
         channel, intent_summary, urgency_flag, crm_status)
         VALUES (?,?,?,?,?,?,?,?,?,?)',
        [
            $leadUuid, $leadCode, $leadType,
            $contactName, $contactEmail, $contactPhone,
            'web_form',
            "Enquiry: $subject",
            $priority === 'urgent' ? 1 : 0,
            'new',
        ]
    );

    activity_log($buyerId ? auth_user_id() : null, 'enquiry_submitted', 'enquiries', $enquiryId);

    echo json_encode([
        'success'    => true,
        'enquiry_id' => $enquiryId,
        'code'       => $code,
        'message'    => 'Your enquiry has been submitted. We will contact you shortly.',
    ]);

} catch (Throwable $e) {
    error_log('Enquiry API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred. Please try again.']);
}
