<?php
/**
 * STRate AI — API: Run Cron Job Manually
 * GET /api/run_cron.php?job=fetch_market_data
 */
require_once __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json');

$allowed = ['fetch_market_data', 'generate_recommendations', 'daily_summary'];
$job     = $_GET['job'] ?? '';

if (!in_array($job, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid job name.']);
    exit;
}

$start  = microtime(true);
$output = '';
$ok     = false;

try {
    switch ($job) {
        case 'fetch_market_data':
            $results = MarketData::fetchAll(daysAhead: 7, daysBack: 3);
            $total   = array_sum($results);
            $output  = "Fetched {$total} records across " . count($results) . " locations.";
            Database::logCron($job, 'success', $output, (int)((microtime(true)-$start)*1000));
            $ok = true;
            break;

        case 'generate_recommendations':
            $properties = Database::getAllProperties();
            $today      = new DateTime();
            $count      = 0;

            for ($delta = 0; $delta <= PRICING_DAYS_AHEAD; $delta++) {
                $dateStr   = (clone $today)->modify("{$delta} days")->format('Y-m-d');
                $dt        = new DateTime($dateStr);
                $isWeekend = (int)$dt->format('N') >= 5;

                foreach ($properties as $prop) {
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
            }
            $output = "Generated {$count} recommendations for " . count($properties) . " properties.";
            Database::logCron($job, 'success', $output, (int)((microtime(true)-$start)*1000));
            $ok = true;
            break;

        case 'daily_summary':
            $properties = Database::getAllProperties();
            $today      = date('Y-m-d');
            $lines      = [];
            foreach ($properties as $prop) {
                $rec = Database::getLatestRecommendation((int)$prop['id'], $today);
                if ($rec) {
                    $chg = round((((float)$rec['suggested_price'] - (float)$rec['base_price']) / $rec['base_price']) * 100, 1);
                    $lines[] = "{$prop['name']}: RM{$rec['suggested_price']} ({$chg:+}%)";
                }
            }
            $output = 'Summary: ' . implode(' | ', $lines);
            Database::logCron($job, 'success', $output, (int)((microtime(true)-$start)*1000));
            $ok = true;
            break;
    }
} catch (Throwable $e) {
    $output = 'Error: ' . $e->getMessage();
    Database::logCron($job, 'error', $output, (int)((microtime(true)-$start)*1000));
}

echo json_encode(['ok' => $ok, 'message' => $output]);
