#!/usr/bin/env php
<?php
/**
 * STRate AI — Cron: Daily Summary
 * Run nightly at 9PM:
 *   0 21 * * * php /home/user/strate_ai/cron/daily_summary.php >> /var/log/strate_summary.log 2>&1
 *
 * Generates a per-owner summary and (optionally) sends via WhatsApp AiServe.
 */
require_once __DIR__ . '/../src/bootstrap.php';

$job   = 'daily_summary';
$today = date('Y-m-d');

echo str_repeat('─', 40) . "\n";
echo "  STRate AI — Daily Summary\n";
echo "  {$today}\n";
echo str_repeat('─', 40) . "\n\n";

$properties = Database::getAllProperties();

if (empty($properties)) {
    Database::logCron($job, 'success', 'No properties.', 0);
    exit(0);
}

foreach ($properties as $prop) {
    $rec = Database::getLatestRecommendation((int) $prop['id'], $today);

    echo "🏠 {$prop['name']} ({$prop['location']})\n";

    if ($rec) {
        $base    = (float) $rec['base_price'];
        $suggest = (float) $rec['suggested_price'];
        $chg     = $base > 0 ? round((($suggest - $base) / $base) * 100, 1) : 0;
        $arrow   = $chg >= 0 ? '▲' : '▼';
        $conf    = round((float)$rec['confidence_score'] * 100);

        echo "   Price:      RM{$suggest} {$arrow} ({$chg:+.1f}%)\n";
        echo "   Confidence: {$conf}%\n";
        echo "   Reason:     {$rec['reason']}\n";

        // WhatsApp via AiServe (uncomment and configure in production)
        // sendWhatsApp($prop['owner_phone'], AiExplainer::whatsappReply($prop['name'], $rec));
    } else {
        echo "   No recommendation available today.\n";
    }
    echo "\n";
}

echo str_repeat('─', 40) . "\n";
Database::logCron($job, 'success', 'Summary generated for ' . count($properties) . ' properties.', 0);

/**
 * Send via AiServe WhatsApp API.
 * Docs: https://aisensy.com/docs
 */
function sendWhatsApp(string $phone, string $message): void
{
    if (!AISENSY_API_KEY) return;

    $ch = curl_init('https://backend.aisensy.com/campaign/t1/api/v2');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'apiKey'            => AISENSY_API_KEY,
            'campaignName'      => AISENSY_TEMPLATE,
            'destination'       => $phone,
            'userName'          => APP_NAME,
            'templateParams'    => [$message],
            'source'            => 'STRate AI Cron',
            'media'             => [],
            'buttons'           => [],
            'carouselCards'     => [],
            'location'          => [],
        ]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 10,
    ]);
    curl_exec($ch);
    curl_close($ch);
}
