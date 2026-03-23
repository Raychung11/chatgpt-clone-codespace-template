<?php
require_once __DIR__ . '/../src/bootstrap.php';

$pageTitle  = 'Scraper';
$activePage = 'Scraper';

$stats     = Database::getScraperStats();
$runs      = Database::getScraperRuns(100);
$locations = MarketData::getAvailableLocations();
$today     = date('Y-m-d');

// Filter
$filterStatus   = $_GET['status']   ?? 'all';
$filterLocation = $_GET['location'] ?? 'all';

$filtered = $runs;
if ($filterStatus   !== 'all') $filtered = array_filter($filtered, fn($r) => $r['status']   === $filterStatus);
if ($filterLocation !== 'all') $filtered = array_filter($filtered, fn($r) => $r['location'] === $filterLocation);

require_once __DIR__ . '/partials/header.php';
?>

<div class="flex items-center justify-between mb-8">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Scraper</h2>
        <p class="text-sm text-gray-500 mt-1">Airbnb market data via Apify · auto-fallback to simulation</p>
    </div>
    <div class="flex gap-3">
        <button onclick="runScraper('all', this)"
                class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
            🕷️ Scrape All Locations
        </button>
    </div>
</div>

<!-- Apify status banner -->
<?php if (!APIFY_TOKEN): ?>
<div class="mb-6 p-4 bg-amber-50 border border-amber-300 rounded-xl flex items-start gap-3">
    <span class="text-2xl">⚠️</span>
    <div>
        <p class="font-semibold text-amber-900">Apify token not configured — running in simulation mode</p>
        <p class="text-sm text-amber-700 mt-1">
            Set <code class="bg-amber-100 px-1 rounded">APIFY_TOKEN</code> in
            <code class="bg-amber-100 px-1 rounded">config/config.local.php</code> to enable real data.
            Get your token at <a href="https://console.apify.com/account/integrations" class="underline" target="_blank">console.apify.com</a>.
        </p>
        <p class="text-sm text-amber-700 mt-1">
            Also set <code class="bg-amber-100 px-1 rounded">APIFY_AIRBNB_ACTOR</code> — default:
            <code class="bg-amber-100 px-1 rounded">dtrungtin/airbnb-scraper</code>
        </p>
    </div>
</div>
<?php else: ?>
<div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl flex items-center gap-3">
    <span class="text-2xl">✅</span>
    <div>
        <p class="font-semibold text-green-900">Apify configured — real scraping enabled</p>
        <p class="text-sm text-green-700 mt-1">
            Actor: <code class="bg-green-100 px-1 rounded"><?= htmlspecialchars(APIFY_AIRBNB_ACTOR) ?></code>
            · Max results: <?= APIFY_MAX_RESULTS ?> per location
            · Cache TTL: <?= SCRAPER_CACHE_TTL / 3600 ?>h
        </p>
    </div>
</div>
<?php endif; ?>

<!-- KPI Row -->
<div class="grid grid-cols-5 gap-4 mb-8">
    <?php
    $kpis = [
        ['label' => 'Runs (24h)',    'value' => $stats['total']    ?? 0],
        ['label' => 'Successful',    'value' => $stats['success']  ?? 0, 'color' => 'text-green-600'],
        ['label' => 'Failed',        'value' => $stats['failed']   ?? 0, 'color' => 'text-red-500'],
        ['label' => 'Real Data',     'value' => $stats['real_data'] ?? 0, 'color' => 'text-indigo-600'],
        ['label' => 'Avg Duration',  'value' => $stats['avg_ms'] ? round($stats['avg_ms'] / 1000, 1) . 's' : '—'],
    ];
    foreach ($kpis as $k): ?>
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100 text-center">
        <p class="text-sm text-gray-500"><?= $k['label'] ?></p>
        <p class="text-2xl font-bold mt-1 <?= $k['color'] ?? 'text-gray-900' ?>"><?= $k['value'] ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- Per-location scrape grid -->
<h3 class="text-base font-semibold text-gray-700 mb-4">Location Status — Today (<?= $today ?>)</h3>
<div class="grid grid-cols-4 gap-3 mb-8">
<?php foreach ($locations as $loc):
    $latestRun = null;
    foreach ($runs as $r) {
        if ($r['location'] === $loc && $r['date_scraped'] === $today) {
            $latestRun = $r;
            break;
        }
    }
    $isDone    = $latestRun && $latestRun['status'] === 'done';
    $isFailed  = $latestRun && $latestRun['status'] === 'failed';
    $isReal    = $isDone && $latestRun['source'] === 'airbnb_apify';
    $isSim     = $isDone && str_contains($latestRun['source'] ?? '', 'simulated');
    $borderCls = $isReal ? 'border-green-300 bg-green-50' : ($isFailed ? 'border-red-200 bg-red-50' : ($isSim ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-gray-50'));
?>
<div class="rounded-xl border <?= $borderCls ?> p-4">
    <div class="flex items-center justify-between mb-2">
        <span class="font-semibold text-sm text-gray-800"><?= htmlspecialchars($loc) ?></span>
        <button onclick="runScraper('<?= htmlspecialchars($loc) ?>', this)"
                class="text-xs text-indigo-600 hover:underline font-medium">
            Scrape →
        </button>
    </div>

    <?php if ($isDone): ?>
        <p class="text-xs text-gray-600">
            <?= $isReal ? '🟢 Live' : '🟡 Simulated' ?>
            · RM<?= number_format($latestRun['avg_price'], 0) ?> avg
        </p>
        <p class="text-xs text-gray-500 mt-0.5">
            <?= $latestRun['listings_found'] ?> listings
            · <?= round($latestRun['occupancy_est'] * 100) ?>% occ
        </p>
    <?php elseif ($isFailed): ?>
        <p class="text-xs text-red-600">❌ Failed</p>
        <p class="text-xs text-red-400 mt-0.5 truncate" title="<?= htmlspecialchars($latestRun['error_message'] ?? '') ?>">
            <?= htmlspecialchars(substr($latestRun['error_message'] ?? 'Unknown error', 0, 50)) ?>
        </p>
    <?php else: ?>
        <p class="text-xs text-gray-400">⚪ No data yet</p>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<!-- Run log table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
        <h3 class="font-semibold text-gray-900">Scraper Run Log</h3>
        <div class="flex flex-wrap gap-2">
            <form method="get" class="flex gap-2">
                <select name="status" onchange="this.form.submit()"
                        class="border border-gray-300 rounded-lg px-2 py-1 text-xs">
                    <option value="all"    <?= $filterStatus === 'all'    ? 'selected':'' ?>>All statuses</option>
                    <option value="done"   <?= $filterStatus === 'done'   ? 'selected':'' ?>>✅ Done</option>
                    <option value="failed" <?= $filterStatus === 'failed' ? 'selected':'' ?>>❌ Failed</option>
                </select>
                <select name="location" onchange="this.form.submit()"
                        class="border border-gray-300 rounded-lg px-2 py-1 text-xs">
                    <option value="all">All locations</option>
                    <?php foreach ($locations as $loc): ?>
                    <option value="<?= htmlspecialchars($loc) ?>" <?= $filterLocation === $loc ? 'selected':'' ?>>
                        <?= htmlspecialchars($loc) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a href="/scraper.php" class="border border-gray-300 rounded-lg px-3 py-1 text-xs hover:bg-gray-50">🔄 Refresh</a>
        </div>
    </div>

    <?php if (empty($filtered)): ?>
    <div class="p-12 text-center text-gray-400 text-sm">No runs yet. Click <strong>Scrape All Locations</strong> above.</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full data-table text-sm">
            <thead>
                <tr>
                    <th class="text-left">Time</th>
                    <th class="text-left">Location</th>
                    <th class="text-left">Date Scraped</th>
                    <th>Status</th>
                    <th>Source</th>
                    <th class="text-right">Listings</th>
                    <th class="text-right">Avg Price</th>
                    <th class="text-right">Occ Est.</th>
                    <th class="text-right">Duration</th>
                    <th class="text-left">Error</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filtered as $run):
                $statusBadge = $run['status'] === 'done'
                    ? '<span class="badge-success">done</span>'
                    : '<span class="badge-error">failed</span>';
                $sourceLabel = match($run['source']) {
                    'airbnb_apify'       => '🟢 Airbnb live',
                    'simulated_fallback' => '🟡 Sim fallback',
                    'simulated'          => '🟡 Simulated',
                    default              => htmlspecialchars($run['source'] ?? '—'),
                };
            ?>
            <tr>
                <td class="text-xs text-gray-500 whitespace-nowrap"><?= $run['created_at'] ?></td>
                <td class="font-medium"><?= htmlspecialchars($run['location']) ?></td>
                <td><?= $run['date_scraped'] ?></td>
                <td class="text-center"><?= $statusBadge ?></td>
                <td class="text-xs"><?= $sourceLabel ?></td>
                <td class="text-right"><?= $run['listings_found'] ?? '—' ?></td>
                <td class="text-right"><?= $run['avg_price'] ? 'RM' . number_format($run['avg_price'], 0) : '—' ?></td>
                <td class="text-right"><?= $run['occupancy_est'] ? round($run['occupancy_est'] * 100) . '%' : '—' ?></td>
                <td class="text-right text-xs text-gray-400"><?= $run['duration_ms'] ?>ms</td>
                <td class="text-xs text-red-500 max-w-xs truncate">
                    <?= $run['error_message'] ? htmlspecialchars(substr($run['error_message'], 0, 80)) : '' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Setup guide -->
<details class="mt-6 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <summary class="cursor-pointer font-semibold text-gray-700">📖 Apify Setup Guide</summary>
    <div class="mt-4 space-y-4 text-sm text-gray-700">
        <ol class="list-decimal list-inside space-y-3">
            <li>Create a free account at <a href="https://apify.com" class="text-indigo-600 underline" target="_blank">apify.com</a></li>
            <li>Go to <strong>Account → Integrations</strong> and copy your <strong>API Token</strong></li>
            <li>Add to <code class="bg-gray-100 px-1 rounded">config/config.local.php</code>:
                <pre class="bg-gray-900 text-green-400 rounded-lg p-4 mt-2 font-mono text-xs">&lt;?php
define('APIFY_TOKEN',        'apify_api_xxxxxxxxxxxxxxxxx');
define('APIFY_AIRBNB_ACTOR', 'dtrungtin/airbnb-scraper');
</pre>
            </li>
            <li>Click <strong>Scrape All Locations</strong> above to test</li>
            <li>Set cron to run every 4 hours:
                <pre class="bg-gray-900 text-green-400 rounded-lg p-4 mt-2 font-mono text-xs">0 */4 * * * php /path/cron/fetch_market_data.php</pre>
            </li>
        </ol>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-blue-800">
            <strong>💡 Apify free tier:</strong> $5 free credits/month ≈ ~200 runs.
            With 8 locations × 8 days × 6 runs/day = 384 scrapes/day — upgrade to $49/month plan or reduce frequency.
            All failures auto-fallback to simulation so pricing never breaks.
        </div>
    </div>
</details>

<script>
async function runScraper(location, btn) {
    btn.disabled = true;
    const orig = btn.textContent;
    btn.textContent = '⏳ Scraping...';

    try {
        const res  = await fetch(`/api/scrape.php?location=${encodeURIComponent(location)}`);
        const data = await res.json();
        showFlash(data.message, data.ok ? 'success' : 'error');
        if (data.ok) setTimeout(() => location === 'all' ? window.location.reload() : window.location.reload(), 1200);
    } catch (e) {
        showFlash('Request failed: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = orig;
    }
}
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
