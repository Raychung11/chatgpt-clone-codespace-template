<?php
/**
 * STRate AI — API: Trigger Scraper
 * GET /api/scrape.php?location=KLCC          — scrape one location (today)
 * GET /api/scrape.php?location=all           — scrape all locations (today + 7 days)
 * GET /api/scrape.php?location=KLCC&date=... — scrape specific date
 */
require_once __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json');

$location  = $_GET['location'] ?? 'all';
$date      = $_GET['date']     ?? date('Y-m-d');
$locations = MarketData::getAvailableLocations();
$start     = microtime(true);

// Validate location
if ($location !== 'all' && !in_array($location, $locations, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => "Unknown location: {$location}"]);
    exit;
}

$results  = [];
$okCount  = 0;
$errCount = 0;

if ($location === 'all') {
    foreach ($locations as $loc) {
        for ($delta = 0; $delta <= PRICING_DAYS_AHEAD; $delta++) {
            $d = (new DateTime())->modify("{$delta} days")->format('Y-m-d');
            try {
                $data = Scraper::scrapeLocation($loc, $d);
                $results[] = ['location' => $loc, 'date' => $d, 'source' => $data['source'], 'avg' => $data['avg_price']];
                $okCount++;
            } catch (Throwable $e) {
                $results[] = ['location' => $loc, 'date' => $d, 'error' => $e->getMessage()];
                $errCount++;
            }
        }
    }
    $msg = "Scraped {$okCount} records across " . count($locations) . " locations.";
    if ($errCount) $msg .= " ({$errCount} fallbacks used)";
} else {
    try {
        $data = Scraper::scrapeLocation($location, $date);
        $results[] = ['location' => $location, 'date' => $date, 'source' => $data['source'], 'avg' => $data['avg_price']];
        $okCount++;
        $msg = "Scraped {$location} for {$date}: avg RM" . number_format($data['avg_price'], 0)
             . ' [' . $data['source'] . ']';
    } catch (Throwable $e) {
        $errCount++;
        $msg = "Failed to scrape {$location}: " . $e->getMessage();
    }
}

$ms = (int)((microtime(true) - $start) * 1000);
Database::logCron('scrape_manual', $errCount > $okCount ? 'error' : 'success', $msg, $ms);

echo json_encode([
    'ok'      => $errCount === 0 || $okCount > 0,
    'message' => $msg,
    'results' => $results,
    'ms'      => $ms,
]);
