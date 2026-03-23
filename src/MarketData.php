<?php
/**
 * STRate AI — Market Data
 * Simulates and stores market data for Klang Valley locations.
 * In production, replace simulate() with Apify / BrightData scraper output.
 */
class MarketData
{
    /** Known locations with baseline pricing parameters. */
    private const LOCATIONS = [
        'KLCC'          => ['avg' => 265, 'min_m' => 0.72, 'max_m' => 1.40, 'listings' => [55, 110]],
        'Bukit Bintang' => ['avg' => 195, 'min_m' => 0.70, 'max_m' => 1.35, 'listings' => [40, 90]],
        'Mont Kiara'    => ['avg' => 230, 'min_m' => 0.75, 'max_m' => 1.30, 'listings' => [30, 70]],
        'Sunway'        => ['avg' => 148, 'min_m' => 0.68, 'max_m' => 1.38, 'listings' => [35, 80]],
        'Cyberjaya'     => ['avg' => 112, 'min_m' => 0.65, 'max_m' => 1.25, 'listings' => [20, 55]],
        'Bangsar'       => ['avg' => 210, 'min_m' => 0.72, 'max_m' => 1.32, 'listings' => [25, 60]],
        'Cheras'        => ['avg' => 130, 'min_m' => 0.65, 'max_m' => 1.28, 'listings' => [20, 50]],
        'Petaling Jaya' => ['avg' => 155, 'min_m' => 0.70, 'max_m' => 1.30, 'listings' => [30, 65]],
    ];

    /** Malaysian public holidays — expandable. */
    private const EVENTS = [
        '2026-01-01' => 'New Year',
        '2026-01-28' => 'Chinese New Year',
        '2026-01-29' => 'Chinese New Year Holiday',
        '2026-02-01' => 'Federal Territory Day',
        '2026-04-10' => 'Hari Raya Aidilfitri',
        '2026-04-11' => 'Hari Raya Aidilfitri Holiday',
        '2026-05-01' => 'Labour Day',
        '2026-05-09' => 'Wesak Day',
        '2026-06-01' => "Agong's Birthday",
        '2026-06-17' => 'Hari Raya Haji',
        '2026-08-31' => 'National Day',
        '2026-09-16' => 'Malaysia Day',
        '2026-10-31' => 'Deepavali',
        '2026-12-25' => 'Christmas',
    ];

    public static function getAvailableLocations(): array
    {
        return array_keys(self::LOCATIONS);
    }

    /**
     * Generate realistic simulated market data for a location + date.
     * Seeds randomness on location+date for reproducibility.
     */
    public static function simulate(string $location, string $date): array
    {
        if (!isset(self::LOCATIONS[$location])) {
            throw new InvalidArgumentException("Unknown location: {$location}");
        }

        $cfg       = self::LOCATIONS[$location];
        $dt        = new DateTime($date);
        $dow       = (int) $dt->format('N'); // 1=Mon … 7=Sun
        $isWeekend = $dow >= 5;
        $isHoliday = isset(self::EVENTS[$date]);
        $eventName = self::EVENTS[$date] ?? null;

        // Seed for reproducibility (same location+date always gives same result)
        $seed = abs(crc32($location . $date));
        mt_srand($seed);

        $noise = self::randFloat(0.88, 1.12);
        $base  = $cfg['avg'] * $noise;

        // Occupancy model
        $occ = self::randFloat(0.50, 0.75);
        if ($isWeekend) $occ += self::randFloat(0.05, 0.15);
        if ($isHoliday) $occ += self::randFloat(0.08, 0.18);

        // Price responds to occupancy
        if ($occ > 0.80) $base *= self::randFloat(1.10, 1.25);
        elseif ($occ < 0.45) $base *= self::randFloat(0.85, 0.95);

        $occ = min(round($occ, 3), 0.97);

        mt_srand(); // Reset seed

        return [
            'location'      => $location,
            'date'          => $date,
            'avg_price'     => round($base, 2),
            'min_price'     => round($base * $cfg['min_m'], 2),
            'max_price'     => round($base * $cfg['max_m'], 2),
            'listing_count' => rand($cfg['listings'][0], $cfg['listings'][1]),
            'occupancy_rate'=> $occ,
            'event_flag'    => $isHoliday ? 1 : 0,
            'event_name'    => $eventName,
            'source'        => 'simulated',
        ];
    }

    /**
     * Return event name for a date, or null if no event.
     * Public so Scraper can use the same event calendar.
     */
    public static function getEventsForDate(string $date): ?string
    {
        return self::EVENTS[$date] ?? null;
    }

    /**
     * Simulate + store data for one location across a date range.
     */
    public static function fetchAndStore(
        string $location,
        int $daysAhead = 7,
        int $daysBack = 30
    ): int {
        $count = 0;
        $today = new DateTime();

        for ($i = -$daysBack; $i <= $daysAhead; $i++) {
            $d = (clone $today)->modify("{$i} days")->format('Y-m-d');
            $data = self::simulate($location, $d);
            Database::upsertMarketData($data);
            $count++;
        }
        return $count;
    }

    /**
     * Refresh all locations.
     */
    public static function fetchAll(int $daysAhead = 7, int $daysBack = 30): array
    {
        $results = [];
        foreach (self::getAvailableLocations() as $loc) {
            $results[$loc] = self::fetchAndStore($loc, $daysAhead, $daysBack);
        }
        return $results;
    }

    /**
     * CSV import: ingest market_data from CSV content string.
     * Expected columns: location,date,avg_price,min_price,max_price,
     *                   listing_count,occupancy_rate,event_flag,event_name
     */
    public static function importCsv(string $csvContent): array
    {
        $lines  = explode("\n", trim($csvContent));
        $header = str_getcsv(array_shift($lines));
        $ok     = [];
        $errors = [];

        foreach ($lines as $i => $line) {
            if (!trim($line)) continue;
            $row = array_combine($header, str_getcsv($line));
            if (!$row) { $errors[] = "Row " . ($i + 2) . ": parse error"; continue; }

            try {
                Database::upsertMarketData([
                    'location'      => trim($row['location']),
                    'date'          => trim($row['date']),
                    'avg_price'     => (float) $row['avg_price'],
                    'min_price'     => (float) $row['min_price'],
                    'max_price'     => (float) $row['max_price'],
                    'listing_count' => (int)   $row['listing_count'],
                    'occupancy_rate'=> (float) $row['occupancy_rate'],
                    'event_flag'    => (int)   ($row['event_flag'] ?? 0),
                    'event_name'    => trim($row['event_name'] ?? '') ?: null,
                    'source'        => 'csv_import',
                ]);
                $ok[] = $row;
            } catch (Throwable $e) {
                $errors[] = "Row " . ($i + 2) . ": " . $e->getMessage();
            }
        }
        return ['ok' => $ok, 'errors' => $errors];
    }

    public static function csvTemplate(): string
    {
        return "location,date,avg_price,min_price,max_price,listing_count,occupancy_rate,event_flag,event_name\n"
             . "KLCC,2026-04-10,320.00,230.00,450.00,85,0.92,1,Hari Raya Aidilfitri\n"
             . "Sunway,2026-04-10,185.00,140.00,260.00,62,0.88,1,Hari Raya Aidilfitri\n";
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private static function randFloat(float $min, float $max): float
    {
        return $min + mt_rand() / mt_getrandmax() * ($max - $min);
    }
}
