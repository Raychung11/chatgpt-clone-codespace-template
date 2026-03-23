#!/usr/bin/env php
<?php
/**
 * STRate AI — Cron: Fetch Market Data
 * Run every 4 hours:
 *   0 */4 * * * php /home/user/strate_ai/cron/fetch_market_data.php >> /var/log/strate_fetch.log 2>&1
 */
require_once __DIR__ . '/../src/bootstrap.php';

$job   = 'fetch_market_data';
$start = microtime(true);

echo "[{$job}] Starting at " . date('Y-m-d H:i:s') . "\n";

try {
    $results = MarketData::fetchAll(daysAhead: 7, daysBack: 3);
    $total   = array_sum($results);

    $msg = "Fetched {$total} records across " . count($results) . " locations.";
    foreach ($results as $loc => $count) {
        echo "  ✓ {$loc}: {$count} records\n";
    }

    $ms = (int) ((microtime(true) - $start) * 1000);
    Database::logCron($job, 'success', $msg, $ms);
    echo "[{$job}] Done — {$msg} ({$ms}ms)\n";
    exit(0);

} catch (Throwable $e) {
    $ms  = (int) ((microtime(true) - $start) * 1000);
    $msg = "Error: " . $e->getMessage();
    Database::logCron($job, 'error', $msg, $ms);
    echo "[{$job}] FAILED — {$msg}\n";
    exit(1);
}
