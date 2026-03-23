<?php
require_once __DIR__ . '/../src/bootstrap.php';

$pageTitle  = 'Market Data';
$activePage = 'Market Data';

$locations       = MarketData::getAvailableLocations();
$selectedLocation = $_GET['location'] ?? $locations[0] ?? 'KLCC';

// Sanitize
if (!in_array($selectedLocation, $locations)) $selectedLocation = $locations[0];

$data  = Database::getMarketDataRange($selectedLocation, 37);
$today = date('Y-m-d');

// Separate historical and future
$sorted    = array_reverse($data); // oldest first
$todayData = null;
foreach ($data as $row) {
    if ($row['date'] === $today) { $todayData = $row; break; }
}

// Prepare chart arrays
$chartLabels   = array_column($sorted, 'date');
$chartAvg      = array_column($sorted, 'avg_price');
$chartMin      = array_column($sorted, 'min_price');
$chartMax      = array_column($sorted, 'max_price');
$chartOcc      = array_map(fn($r) => round($r['occupancy_rate'] * 100, 1), $sorted);

require_once __DIR__ . '/partials/header.php';
?>

<div class="flex items-center justify-between mb-8">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Market Data</h2>
        <p class="text-sm text-gray-500 mt-1">Competitor prices, occupancy, and demand signals</p>
    </div>
    <div class="flex items-center gap-3">
        <form method="get" class="flex gap-2">
            <select name="location" onchange="this.form.submit()"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                <?php foreach ($locations as $loc): ?>
                <option value="<?= htmlspecialchars($loc) ?>"
                    <?= $loc === $selectedLocation ? 'selected' : '' ?>>
                    <?= htmlspecialchars($loc) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
        <button onclick="runCronJob('fetch_market_data', this)"
                class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
            🔄 Refresh Data
        </button>
    </div>
</div>

<!-- KPI Row -->
<div class="grid grid-cols-4 gap-6 mb-8">
    <?php
    $occ    = $todayData ? round($todayData['occupancy_rate'] * 100) : 0;
    $demand = $todayData ? PricingEngine::demandLabel(
        (float)$todayData['occupancy_rate'], (bool)$todayData['event_flag']
    ) : '—';
    $kpis = [
        ['label' => 'Avg Price Today',    'value' => $todayData ? 'RM ' . number_format($todayData['avg_price'], 0) : '—'],
        ['label' => 'Occupancy Rate',     'value' => $todayData ? $occ . '%' : '—'],
        ['label' => 'Active Listings',    'value' => $todayData ? $todayData['listing_count'] : '—'],
        ['label' => 'Demand Level',       'value' => $demand],
    ];
    foreach ($kpis as $k): ?>
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <p class="text-sm text-gray-500"><?= $k['label'] ?></p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $k['value'] ?></p>
    </div>
    <?php endforeach; ?>
</div>

<?php if (empty($data)): ?>
<div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 text-center text-yellow-800">
    No market data for <?= htmlspecialchars($selectedLocation) ?>.
    Click <strong>Refresh Data</strong> to fetch.
</div>
<?php else: ?>

<!-- Tabs -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
    <div class="border-b border-gray-200 flex">
        <button onclick="showTab('tab-price', this)"
                class="tab-btn active px-6 py-3 text-sm font-medium border-b-2 border-indigo-600 text-indigo-600">
            💰 Price Trend
        </button>
        <button onclick="showTab('tab-occ', this)"
                class="tab-btn px-6 py-3 text-sm font-medium text-gray-500 hover:text-gray-700">
            📈 Occupancy
        </button>
        <button onclick="showTab('tab-table', this)"
                class="tab-btn px-6 py-3 text-sm font-medium text-gray-500 hover:text-gray-700">
            📋 Raw Data
        </button>
    </div>

    <div id="tab-price" class="tab-panel p-6">
        <canvas id="priceChart" height="80"></canvas>
    </div>

    <div id="tab-occ" class="tab-panel hidden p-6">
        <canvas id="occChart" height="80"></canvas>
    </div>

    <div id="tab-table" class="tab-panel hidden overflow-x-auto">
        <table class="w-full data-table text-sm">
            <thead>
                <tr>
                    <th class="text-left">Date</th>
                    <th class="text-right">Avg (RM)</th>
                    <th class="text-right">Min (RM)</th>
                    <th class="text-right">Max (RM)</th>
                    <th class="text-right">Listings</th>
                    <th class="text-right">Occupancy</th>
                    <th>Demand</th>
                    <th>Event</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_reverse($sorted) as $row):
                    $rowDemand = PricingEngine::demandLabel((float)$row['occupancy_rate'], (bool)$row['event_flag']);
                    $isToday   = $row['date'] === $today;
                ?>
                <tr class="<?= $isToday ? 'bg-indigo-50' : '' ?>">
                    <td class="font-medium <?= $isToday ? 'text-indigo-700' : '' ?>">
                        <?= $row['date'] ?><?= $isToday ? ' <span class="text-xs text-indigo-500">(today)</span>' : '' ?>
                    </td>
                    <td class="text-right">RM <?= number_format($row['avg_price'], 0) ?></td>
                    <td class="text-right text-green-600">RM <?= number_format($row['min_price'], 0) ?></td>
                    <td class="text-right text-red-500">RM <?= number_format($row['max_price'], 0) ?></td>
                    <td class="text-right"><?= $row['listing_count'] ?></td>
                    <td class="text-right"><?= round($row['occupancy_rate'] * 100) ?>%</td>
                    <td><?= htmlspecialchars($rowDemand) ?></td>
                    <td><?= $row['event_flag'] ? ('🎉 ' . htmlspecialchars($row['event_name'] ?? '')) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- CSV Import -->
<details class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <summary class="cursor-pointer font-medium text-gray-700">📥 Import Market Data via CSV</summary>
    <div class="mt-4 space-y-4">
        <a href="/api/data.php?action=csv_template" download="market_data_template.csv"
           class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
            ⬇️ Download CSV Template
        </a>
        <form method="post" action="/api/data.php?action=import_csv" enctype="multipart/form-data" class="flex gap-3 items-center">
            <input type="file" name="csv_file" accept=".csv"
                   class="text-sm border border-gray-300 rounded-lg px-3 py-1.5">
            <button type="submit"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                Upload & Import
            </button>
        </form>
    </div>
</details>

<?php endif; ?>

<script>
// Tab switcher
function showTab(id, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('border-b-2', 'border-indigo-600', 'text-indigo-600');
        b.classList.add('text-gray-500');
    });
    document.getElementById(id).classList.remove('hidden');
    btn.classList.add('border-b-2', 'border-indigo-600', 'text-indigo-600');
    btn.classList.remove('text-gray-500');
}

const labels = <?= json_encode($chartLabels) ?>;
const avg    = <?= json_encode($chartAvg) ?>;
const min    = <?= json_encode($chartMin) ?>;
const max    = <?= json_encode($chartMax) ?>;
const occ    = <?= json_encode($chartOcc) ?>;

makePriceChart('priceChart', labels, avg, min, max);
makeOccChart('occChart', labels, occ);
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
