#!/usr/bin/env php
<?php
/**
 * STRate AI — Cron: Generate Price Recommendations
 * Run daily at 6AM:
 *   0 6 * * * php /home/user/strate_ai/cron/generate_recommendations.php >> /var/log/strate_recs.log 2>&1
 *
 * For each active property:
 *   1. Load market data for target dates
 *   2. Run PricingEngine (deterministic)
 *   3. Call AiExplainer for human-readable reason
 *   4. Save to price_recommendations
 */
require_once __DIR__ . '/../src/bootstrap.php';

$job   = 'generate_recommendations';
$start = microtime(true);

echo "[{$job}] Starting at " . date('Y-m-d H:i:s') . "\n";

$properties = Database::getAllProperties();

if (empty($properties)) {
    $msg = 'No active properties found.';
    echo "[{$job}] {$msg}\n";
    Database::logCron($job, 'success', $msg, 0);
    exit(0);
}

$today    = new DateTime();
$daysOk   = 0;
$daysSkip = 0;
$daysErr  = 0;

for ($delta = 0; $delta <= PRICING_DAYS_AHEAD; $delta++) {
    $dateStr  = (clone $today)->modify("{$delta} days")->format('Y-m-d');
    $dt       = new DateTime($dateStr);
    $dow      = (int) $dt->format('N');
    $isWeekend = $dow >= 5; // Fri/Sat/Sun

    foreach ($properties as $prop) {
        try {
            $market = Database::getMarketData($prop['location'], $dateStr);

            if (!$market) {
                $daysSkip++;
                continue;
            }

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

            $daysOk++;
            echo sprintf(
                "  ✓ %s / %s → RM%d (conf %.0f%%)\n",
                $prop['name'], $dateStr,
                $result['suggested_price'],
                $result['confidence_score'] * 100
            );

        } catch (Throwable $e) {
            $daysErr++;
            echo "  ✗ {$prop['name']} / {$dateStr}: " . $e->getMessage() . "\n";
        }
    }
}

$ms  = (int) ((microtime(true) - $start) * 1000);
$msg = "Generated {$daysOk} | Skipped {$daysSkip} (no market data) | Errors {$daysErr}";
$status = ($daysErr > $daysOk) ? 'error' : 'success';

Database::logCron($job, $status, $msg, $ms);
echo "[{$job}] Done — {$msg} ({$ms}ms)\n";
exit($status === 'error' ? 1 : 0);
