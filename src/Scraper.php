<?php
/**
 * STRate AI — Scraper
 *
 * Fetches real STR market data from Airbnb via Apify.
 * Falls back to MarketData::simulate() automatically on any failure.
 *
 * Flow:
 *   1. POST to Apify → start actor run → get runId
 *   2. Poll run status until done / timeout
 *   3. GET dataset items → parse prices
 *   4. Calculate avg/min/max/occupancy_estimate
 *   5. Store into market_data + scraper_runs tables
 *   6. On any error → log + fall back to simulation
 */
class Scraper
{
    // ─── Location → Airbnb search query mapping ───────────────────────────
    private const LOCATION_QUERIES = [
        'KLCC'          => 'KLCC, Kuala Lumpur, Malaysia',
        'Bukit Bintang' => 'Bukit Bintang, Kuala Lumpur, Malaysia',
        'Mont Kiara'    => 'Mont Kiara, Kuala Lumpur, Malaysia',
        'Sunway'        => 'Sunway, Petaling Jaya, Selangor, Malaysia',
        'Cyberjaya'     => 'Cyberjaya, Selangor, Malaysia',
        'Bangsar'       => 'Bangsar, Kuala Lumpur, Malaysia',
        'Cheras'        => 'Cheras, Kuala Lumpur, Malaysia',
        'Petaling Jaya' => 'Petaling Jaya, Selangor, Malaysia',
    ];

    // ─── Public API ───────────────────────────────────────────────────────

    /**
     * Scrape one location for one date.
     * Returns market_data-format array (same shape as MarketData::simulate()).
     * Falls back to simulation if Apify fails or token is not set.
     */
    public static function scrapeLocation(string $location, string $date): array
    {
        if (!APIFY_TOKEN) {
            return self::fallback($location, $date, 'No Apify token configured.');
        }

        if (!isset(self::LOCATION_QUERIES[$location])) {
            return self::fallback($location, $date, "No query mapping for location: {$location}");
        }

        // Check cache — don't re-scrape within TTL
        $cached = self::getCached($location, $date);
        if ($cached) return $cached;

        $start = microtime(true);
        $runId = null;

        try {
            // Step 1: Start the actor run
            $input = self::buildAirbnbInput($location, $date);
            $runId = self::startActorRun(APIFY_AIRBNB_ACTOR, $input);

            // Step 2: Poll until done
            $run = self::waitForRun($runId);

            if ($run['status'] !== 'SUCCEEDED') {
                throw new RuntimeException("Apify run {$runId} finished with status: {$run['status']}");
            }

            // Step 3: Fetch dataset items
            $items = self::getDatasetItems($run['defaultDatasetId']);

            if (empty($items)) {
                throw new RuntimeException("Apify returned 0 listings for {$location} on {$date}");
            }

            // Step 4: Parse + aggregate
            $marketData = self::parseAirbnbItems($items, $location, $date);

            // Step 5: Store results
            $ms = (int)((microtime(true) - $start) * 1000);
            self::logRun($location, $date, 'airbnb', $runId, 'done', $marketData, null, $ms, $items);
            Database::upsertMarketData($marketData);

            return $marketData;

        } catch (Throwable $e) {
            $ms = (int)((microtime(true) - $start) * 1000);
            self::logRun($location, $date, 'airbnb', $runId, 'failed', null, $e->getMessage(), $ms, []);
            return self::fallback($location, $date, $e->getMessage());
        }
    }

    /**
     * Scrape all known locations for a range of dates.
     * Returns ['location' => ['date' => marketData, ...], ...]
     */
    public static function scrapeAll(int $daysAhead = 7, int $daysBack = 0): array
    {
        $results = [];
        $today   = new DateTime();

        foreach (array_keys(self::LOCATION_QUERIES) as $location) {
            $results[$location] = [];

            // Only scrape forward-looking dates for real data (past = use simulation)
            for ($delta = -$daysBack; $delta <= $daysAhead; $delta++) {
                $date = (clone $today)->modify("{$delta} days")->format('Y-m-d');

                if ($delta < 0) {
                    // Historical — always simulate (can't scrape the past)
                    $data = MarketData::simulate($location, $date);
                    Database::upsertMarketData($data);
                } else {
                    // Present / future — use real scraper with fallback
                    $data = self::scrapeLocation($location, $date);
                }

                $results[$location][$date] = $data;

                // Small delay between requests to be polite to Apify
                if (APIFY_TOKEN && $delta >= 0) usleep(500_000); // 0.5s
            }
        }

        return $results;
    }

    /**
     * Get status of recent scraper runs for the dashboard.
     */
    public static function getRecentRuns(int $limit = 50): array
    {
        return Database::query(
            'SELECT * FROM scraper_runs ORDER BY created_at DESC LIMIT ?',
            [$limit]
        );
    }

    /**
     * Get scraper run stats for the dashboard KPI row.
     */
    public static function getStats(): array
    {
        $row = Database::queryOne(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'done')   AS success,
                SUM(status = 'failed') AS failed,
                AVG(duration_ms)       AS avg_ms,
                MAX(created_at)        AS last_run
             FROM scraper_runs
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        return $row ?? ['total' => 0, 'success' => 0, 'failed' => 0, 'avg_ms' => 0, 'last_run' => null];
    }

    // ─── Apify HTTP helpers ───────────────────────────────────────────────

    /**
     * Build the Apify Airbnb scraper input payload.
     * Docs: https://apify.com/dtrungtin/airbnb-scraper/input-schema
     */
    private static function buildAirbnbInput(string $location, string $date): array
    {
        $checkIn  = $date;
        $checkOut = (new DateTime($date))->modify('+1 day')->format('Y-m-d');

        return [
            'locationQueries' => [self::LOCATION_QUERIES[$location]],
            'checkIn'         => $checkIn,
            'checkOut'        => $checkOut,
            'maxResults'      => APIFY_MAX_RESULTS,
            'currency'        => 'MYR',
            'locale'          => 'en',
            'minPrice'        => 30,
            'maxPrice'        => 2000,
        ];
    }

    /**
     * POST to Apify to start an actor run.
     * Returns runId string.
     */
    private static function startActorRun(string $actorId, array $input): string
    {
        $url      = "https://api.apify.com/v2/acts/{$actorId}/runs";
        $response = self::apifyPost($url, $input);

        $runId = $response['data']['id'] ?? null;
        if (!$runId) {
            throw new RuntimeException('Apify did not return a run ID. Response: ' . json_encode($response));
        }
        return $runId;
    }

    /**
     * Poll the run status until SUCCEEDED / FAILED / timeout.
     */
    private static function waitForRun(string $runId): array
    {
        $deadline = time() + APIFY_TIMEOUT_SEC;
        $pollSec  = 3;

        while (time() < $deadline) {
            sleep($pollSec);

            $response = self::apifyGet("https://api.apify.com/v2/actor-runs/{$runId}");
            $status   = $response['data']['status'] ?? 'UNKNOWN';

            if (in_array($status, ['SUCCEEDED', 'FAILED', 'ABORTED', 'TIMED-OUT'], true)) {
                return $response['data'];
            }

            // Back off slightly as run progresses
            $pollSec = min($pollSec + 2, 10);
        }

        throw new RuntimeException("Apify run {$runId} did not finish within " . APIFY_TIMEOUT_SEC . "s.");
    }

    /**
     * Fetch all items from an Apify dataset.
     */
    private static function getDatasetItems(string $datasetId): array
    {
        $url      = "https://api.apify.com/v2/datasets/{$datasetId}/items?format=json&clean=true";
        $response = self::apifyGet($url, expectArray: true);
        return is_array($response) ? $response : [];
    }

    // ─── Parser ───────────────────────────────────────────────────────────

    /**
     * Parse raw Apify Airbnb items → market_data format.
     *
     * The dtrungtin/airbnb-scraper returns items shaped like:
     * {
     *   "pricing": { "rate": { "amount": 285, "currency": "MYR" } },
     *   "name": "...",
     *   "roomType": "ENTIRE_HOME",
     *   "starRating": 4.8,
     *   "reviewsCount": 42,
     *   ...
     * }
     */
    private static function parseAirbnbItems(array $items, string $location, string $date): array
    {
        $prices = [];

        foreach ($items as $item) {
            $price = self::extractPrice($item);
            if ($price !== null && $price >= 30 && $price <= 5000) {
                $prices[] = $price;
            }
        }

        if (empty($prices)) {
            throw new RuntimeException("No valid prices found in " . count($items) . " listings for {$location}");
        }

        sort($prices);
        $count  = count($prices);
        $avg    = array_sum($prices) / $count;

        // Remove top/bottom 5% to reduce outlier effect on min/max
        $trimN  = max(1, (int)($count * 0.05));
        $trimmed = array_slice($prices, $trimN, $count - 2 * $trimN);
        $minP   = $trimmed[0]         ?? $prices[0];
        $maxP   = end($trimmed)       ?? end($prices);

        // Occupancy estimate:
        // We use the ratio of "expensive" listings (>120% of avg) as a proxy.
        // High-demand areas show more premium pricing.
        $premiumCount = count(array_filter($prices, fn($p) => $p > $avg * 1.15));
        $occEstimate  = self::estimateOccupancy($avg, $count, $premiumCount, $date);

        // Event detection: look for known events
        $eventFlag = 0;
        $eventName = null;
        $events    = MarketData::getEventsForDate($date);
        if ($events) { $eventFlag = 1; $eventName = $events; }

        return [
            'location'      => $location,
            'date'          => $date,
            'avg_price'     => round($avg, 2),
            'min_price'     => round($minP, 2),
            'max_price'     => round($maxP, 2),
            'listing_count' => $count,
            'occupancy_rate'=> $occEstimate,
            'event_flag'    => $eventFlag,
            'event_name'    => $eventName,
            'source'        => 'airbnb_apify',
        ];
    }

    /**
     * Extract price-per-night from a single Apify item.
     * Handles multiple response shapes from different actor versions.
     */
    private static function extractPrice(array $item): ?float
    {
        // dtrungtin/airbnb-scraper shape
        if (isset($item['pricing']['rate']['amount'])) {
            return (float) $item['pricing']['rate']['amount'];
        }
        // maxcopell/airbnb-scraper shape
        if (isset($item['price'])) {
            return (float) $item['price'];
        }
        // Generic fallback
        if (isset($item['pricePerNight'])) {
            return (float) $item['pricePerNight'];
        }
        if (isset($item['price_per_night'])) {
            return (float) $item['price_per_night'];
        }
        return null;
    }

    /**
     * Estimate occupancy rate from price data signals.
     *
     * Logic:
     * - Baseline occupancy is 0.65 (KL average)
     * - If avg price is above the location's known baseline → demand is high
     * - If many premium-priced listings exist → demand signal is strong
     * - Weekend and event bonuses applied via MarketData
     */
    private static function estimateOccupancy(
        float $avgPrice,
        int $listingCount,
        int $premiumCount,
        string $date
    ): float {
        $baselines = [
            'KLCC' => 265, 'Bukit Bintang' => 195, 'Mont Kiara' => 230,
            'Sunway' => 148, 'Cyberjaya' => 112, 'Bangsar' => 210,
            'Cheras' => 130, 'Petaling Jaya' => 155,
        ];

        // Start at base occupancy
        $occ = 0.65;

        // Price premium signal
        // (we don't know the location here, use overall avg as proxy)
        $overallBaseline = array_sum($baselines) / count($baselines); // ~181
        $pricePremium    = ($avgPrice - $overallBaseline) / $overallBaseline;
        $occ += $pricePremium * 0.25; // 25% weight

        // Premium listing ratio signal
        if ($listingCount > 0) {
            $premiumRatio = $premiumCount / $listingCount;
            $occ += $premiumRatio * 0.10;
        }

        // Weekend boost
        $dow = (int)(new DateTime($date))->format('N');
        if ($dow >= 5) $occ += 0.07;

        return round(min(max($occ, 0.30), 0.97), 3);
    }

    // ─── Cache check ─────────────────────────────────────────────────────

    /**
     * Return existing market data if it was scraped within TTL.
     * Prevents re-scraping the same location+date too frequently.
     */
    private static function getCached(string $location, string $date): ?array
    {
        $run = Database::queryOne(
            "SELECT sr.*, md.*
             FROM scraper_runs sr
             JOIN market_data md ON md.location = sr.location AND md.date = sr.date_scraped
             WHERE sr.location = ? AND sr.date_scraped = ? AND sr.status = 'done'
               AND sr.created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
             ORDER BY sr.created_at DESC LIMIT 1",
            [$location, $date, SCRAPER_CACHE_TTL]
        );

        if (!$run) return null;

        return [
            'location'      => $run['location'],
            'date'          => $run['date'],
            'avg_price'     => $run['avg_price'],
            'min_price'     => $run['min_price'],
            'max_price'     => $run['max_price'],
            'listing_count' => $run['listing_count'],
            'occupancy_rate'=> $run['occupancy_rate'],
            'event_flag'    => $run['event_flag'],
            'event_name'    => $run['event_name'],
            'source'        => $run['source'],
        ];
    }

    // ─── Logging ─────────────────────────────────────────────────────────

    private static function logRun(
        string  $location,
        string  $date,
        string  $source,
        ?string $runId,
        string  $status,
        ?array  $marketData,
        ?string $error,
        int     $ms,
        array   $rawItems
    ): void {
        $sample = array_slice($rawItems, 0, 3); // keep first 3 for audit

        Database::execute(
            "INSERT INTO scraper_runs
                (location, date_scraped, source, apify_run_id, status,
                 listings_found, avg_price, min_price, max_price,
                 occupancy_est, raw_sample, error_message, duration_ms)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                apify_run_id  = VALUES(apify_run_id),
                status        = VALUES(status),
                listings_found= VALUES(listings_found),
                avg_price     = VALUES(avg_price),
                min_price     = VALUES(min_price),
                max_price     = VALUES(max_price),
                occupancy_est = VALUES(occupancy_est),
                raw_sample    = VALUES(raw_sample),
                error_message = VALUES(error_message),
                duration_ms   = VALUES(duration_ms)",
            [
                $location, $date, $source, $runId, $status,
                $marketData ? $marketData['listing_count'] : 0,
                $marketData ? $marketData['avg_price']     : null,
                $marketData ? $marketData['min_price']     : null,
                $marketData ? $marketData['max_price']     : null,
                $marketData ? $marketData['occupancy_rate']: null,
                json_encode($sample),
                $error,
                $ms,
            ]
        );
    }

    // ─── Fallback ────────────────────────────────────────────────────────

    /**
     * Log the failure and return simulated data so the pipeline never breaks.
     */
    private static function fallback(string $location, string $date, string $reason): array
    {
        error_log("[STRate Scraper] Fallback for {$location}/{$date}: {$reason}");

        // Log a failed run so the dashboard shows the error
        self::logRun($location, $date, 'airbnb', null, 'failed', null, $reason, 0, []);

        $data = MarketData::simulate($location, $date);
        $data['source'] = 'simulated_fallback';
        Database::upsertMarketData($data);
        return $data;
    }

    // ─── HTTP wrappers ────────────────────────────────────────────────────

    private static function apifyPost(string $url, array $payload): array
    {
        return self::curlRequest($url, 'POST', json_encode($payload));
    }

    private static function apifyGet(string $url, bool $expectArray = false): array
    {
        return self::curlRequest($url, 'GET', null, $expectArray);
    }

    private static function curlRequest(
        string  $url,
        string  $method,
        ?string $body,
        bool    $expectArray = false
    ): array {
        $sep      = str_contains($url, '?') ? '&' : '?';
        $fullUrl  = $url . $sep . 'token=' . urlencode(APIFY_TOKEN);

        $headers = ['Content-Type: application/json'];

        $ch = curl_init($fullUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new RuntimeException("cURL error: {$curlErr}");
        }

        if ($httpCode >= 400) {
            throw new RuntimeException("Apify HTTP {$httpCode}: " . substr($raw, 0, 300));
        }

        $decoded = json_decode($raw, true);
        if ($decoded === null) {
            throw new RuntimeException("Apify returned non-JSON: " . substr($raw, 0, 300));
        }

        return $expectArray ? (array)$decoded : (array)$decoded;
    }
}
