<?php
/**
 * PlotGold Malaysia — Search API (AJAX typeahead)
 * GET: ?q=keyword
 */
require_once __DIR__ . '/../inc/bootstrap.php';

header('Content-Type: application/json');

$q = clean($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$results = [];

// Listings
$listings = Database::fetchAll(
    "SELECT l.id, l.title, l.slug, l.asking_price, l.city, l.state, lt.label_en AS type_label
     FROM listings l
     LEFT JOIN listing_types lt ON lt.id = l.listing_type_id
     WHERE l.status = 'active' AND (l.title LIKE ? OR l.city LIKE ?)
     LIMIT 5",
    ["%$q%", "%$q%"]
);
foreach ($listings as $l) {
    $results[] = [
        'type'    => 'listing',
        'label'   => $l['title'],
        'sub'     => $l['city'] . ', ' . $l['state'] . ' — ' . ($l['type_label'] ?? ''),
        'price'   => $l['asking_price'] ? 'RM ' . number_format($l['asking_price']) : 'POQ',
        'url'     => pg_url('listing/' . $l['slug']),
    ];
}

// Parks
$parks = Database::fetchAll(
    "SELECT id, name, city, state, slug FROM memorial_parks WHERE is_active = 1 AND (name LIKE ? OR city LIKE ?) LIMIT 3",
    ["%$q%", "%$q%"]
);
foreach ($parks as $p) {
    $results[] = [
        'type'  => 'park',
        'label' => $p['name'],
        'sub'   => $p['city'] . ', ' . $p['state'],
        'url'   => pg_url('parks/' . $p['slug']),
    ];
}

echo json_encode($results);
