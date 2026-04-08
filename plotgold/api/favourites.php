<?php
/**
 * PlotGold Malaysia — Favourites API
 * POST: toggle favourite
 */
require_once __DIR__ . '/../inc/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!auth_check()) {
    echo json_encode(['success' => false, 'redirect' => pg_url('login.php')]);
    exit;
}

$data      = json_decode(file_get_contents('php://input'), true) ?? [];
$listingId = (int)($data['listing_id'] ?? $_POST['listing_id'] ?? 0);
$action    = clean($data['action'] ?? 'toggle');

if (!$listingId) {
    echo json_encode(['success' => false, 'error' => 'Invalid listing.']);
    exit;
}

// Verify buyer record exists
$buyer = Database::fetchOne('SELECT id FROM buyers WHERE user_id = ?', [auth_user_id()]);
if (!$buyer) {
    // Auto-create buyer record for multi-role users
    Database::query('INSERT IGNORE INTO buyers (user_id) VALUES (?)', [auth_user_id()]);
    $buyer = Database::fetchOne('SELECT id FROM buyers WHERE user_id = ?', [auth_user_id()]);
}

$existing = Database::fetchOne('SELECT id FROM favourites WHERE buyer_id = ? AND listing_id = ?', [$buyer['id'], $listingId]);

if ($existing) {
    Database::query('DELETE FROM favourites WHERE buyer_id = ? AND listing_id = ?', [$buyer['id'], $listingId]);
    Database::query('UPDATE listings SET favourite_count = GREATEST(0, favourite_count - 1) WHERE id = ?', [$listingId]);
    echo json_encode(['success' => true, 'state' => 'removed']);
} else {
    Database::query('INSERT IGNORE INTO favourites (buyer_id, listing_id) VALUES (?, ?)', [$buyer['id'], $listingId]);
    Database::query('UPDATE listings SET favourite_count = favourite_count + 1 WHERE id = ?', [$listingId]);
    echo json_encode(['success' => true, 'state' => 'added']);
}
