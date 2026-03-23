#!/usr/bin/env php
<?php
/**
 * STRate AI — Cron: Fetch Market Data
 * Run every 4 hours:
 *   0 */4 * * * php /home/user/strate_ai/cron/fetch_market_data.php >> /var/log/strate_fetch.log 2>&1
 *
 * Strategy:
 *   - Future dates (today → +7 days): scrape Airbnb via Apify (real data)
 *   - Past dates  (−30 days → yesterday): simulate (can't scrape the past)
 *   - On any Apify failure: auto-fallback to simulation, log the error
 */
require_once __DIR__ . '/../src/bootstrap.php';

$job   = 'fetch_market_data';
$start = microtime(true);

echo "[{$job}] Starting at " . date('Y-m-d H:i:s') . "\n";
echo "[{$job}] Apify token: " . (APIFY_TOKEN ? '✓ configured' : '⚠ NOT SET — will use simulation') . "\n\n";

$today     = new DateTime();
$locations = MarketData::getAvailableLocations();
$totalOk   = 0;
$totalFail = 0;

// ─── Step 1: Backfill historical dates via simulation ─────────────────────
echo "── Historical backfill (simulation) ──\n";
foreach ($locations as $location) {
    for ($delta = -30; $delta <= -1; $delta++) {
        $date = (clone $today)->modify("{$delta} days")->format('Y-m-d');
        try {
            $data = MarketData::simulate($location, $date);
            Database::upsertMarketData($data);
            $totalOk++;
        } catch (Throwable $e) {
            $totalFail++;
            echo "  ✗ {$location}/{$date}: " . $e->getMessage() . "\n";
        }
    }
}
echo "  Done — " . count($locations) * 30 . " historical records upserted.\n\n";

// ─── Step 2: Scrape present + future dates via Apify ─────────────────────
echo "── Live scrape (Apify → fallback to simulation) ──\n";
foreach ($locations as $location) {
    for ($delta = 0; $delta <= PRICING_DAYS_AHEAD; $delta++) {
        $date = (clone $today)->modify("{$delta} days")->format('Y-m-d');
        try {
            $data   = Scraper::scrapeLocation($location, $date);
            $source = $data['source'];
            echo sprintf(
                "  %s %s / %s → avg RM%d, occ %.0f%% [%s]\n",
                str_contains($source, 'fallback') ? '⚠' : '✓',
                $location, $date,
                $data['avg_price'],
                $data['occupancy_rate'] * 100,
                $source
            );
            $totalOk++;
        } catch (Throwable $e) {
            $totalFail++;
            echo "  ✗ {$location}/{$date}: " . $e->getMessage() . "\n";
        }
    }
}

$ms  = (int)((microtime(true) - $start) * 1000);
$msg = sprintf(
    'OK: %d | Failed: %d | Locations: %d | Apify: %s',
    $totalOk, $totalFail, count($locations),
    APIFY_TOKEN ? 'enabled' : 'disabled (simulation only)'
);

echo "\n[{$job}] Done — {$msg} ({$ms}ms)\n";
Database::logCron($job, $totalFail > $totalOk ? 'error' : 'success', $msg, $ms);
exit($totalFail > $totalOk ? 1 : 0);
