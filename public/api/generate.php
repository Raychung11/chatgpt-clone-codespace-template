<?php
/**
 * STRate AI — API: Generate Recommendations for One Property
 * GET /api/generate.php?property_id=1
 */
require_once __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json');

$propertyId = (int)($_GET['property_id'] ?? 0);
$prop       = $propertyId ? Database::getProperty($propertyId) : null;

if (!$prop) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Property not found.']);
    exit;
}

$start = microtime(true);
$count = 0;
$today = new DateTime();

for ($delta = 0; $delta <= PRICING_DAYS_AHEAD; $delta++) {
    $dateStr   = (clone $today)->modify("{$delta} days")->format('Y-m-d');
    $dt        = new DateTime($dateStr);
    $isWeekend = (int)$dt->format('N') >= 5;

    $market = Database::getMarketData($prop['location'], $dateStr);
    if (!$market) continue;

    $result = PricingEngine::calculate($prop, $market, $isWeekend, $delta);
    $reason = AiExplainer::explain($result, $market);

    Database::upsertRecommendation([
        'property_id'     => $prop['id'],
        'date'            => $dateStr,
        'base_price'      => $prop['base_price'],
        'suggested_price' => $result['suggested_price'],
        'confidence_score'=> $result['confidence_score'],
        'demand_factor'   => $result['demand_factor'],
        'event_boost'     => $result['event_boost'],
        'competitor_gap'  => $result['competitor_gap'],
        'occupancy_adj'   => $result['occupancy_adj'],
        'reason'          => $reason,
    ]);
    $count++;
}

$ms  = (int)((microtime(true) - $start) * 1000);
$msg = "Generated {$count} recommendations for {$prop['name']}.";
Database::logCron('generate_recommendations', 'success', $msg, $ms);

echo json_encode(['ok' => true, 'message' => $msg, 'count' => $count]);
