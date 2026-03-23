<?php
require_once __DIR__ . '/../src/bootstrap.php';

$pageTitle  = 'Recommendations';
$activePage = 'Recommendations';

$properties = Database::getAllProperties();
$today      = date('Y-m-d');

// Selected property
$selectedId = (int)($_GET['property_id'] ?? ($properties[0]['id'] ?? 0));
$prop = null;
foreach ($properties as $p) {
    if ((int)$p['id'] === $selectedId) { $prop = $p; break; }
}

$recs = $prop ? Database::getRecommendations($selectedId, PRICING_DAYS_AHEAD + 1) : [];

// Chart data
$chartLabels    = array_column($recs, 'date');
$chartSuggested = array_column($recs, 'suggested_price');
$chartBase      = array_column($recs, 'base_price');

require_once __DIR__ . '/partials/header.php';
?>

<div class="flex items-center justify-between mb-8">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Price Recommendations</h2>
        <p class="text-sm text-gray-500 mt-1">AI-assisted daily pricing for your properties</p>
    </div>

    <?php if ($prop): ?>
    <button onclick="generateRecommendations(<?= $prop['id'] ?>, this)"
            class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
        ⚡ Generate for This Property
    </button>
    <?php endif; ?>
</div>

<?php if (empty($properties)): ?>
<div class="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
    <p class="text-gray-500">No properties yet. <a href="/properties.php" class="text-indigo-600 hover:underline">Add one →</a></p>
</div>
<?php else: ?>

<!-- Property selector -->
<form method="get" class="mb-6 flex gap-3 items-center">
    <label class="text-sm font-medium text-gray-700">Property:</label>
    <select name="property_id" onchange="this.form.submit()"
            class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
        <?php foreach ($properties as $p): ?>
        <option value="<?= $p['id'] ?>" <?= $p['id'] === $selectedId ? 'selected' : '' ?>>
            <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['location']) ?>)
        </option>
        <?php endforeach; ?>
    </select>
</form>

<?php if (empty($recs)): ?>
<div class="bg-blue-50 border border-blue-200 rounded-xl p-8 text-center text-blue-800">
    <p class="font-medium">No recommendations yet for this property.</p>
    <p class="text-sm mt-1">Click <strong>Generate for This Property</strong> above.</p>
</div>
<?php else: ?>

<!-- Chart -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-4">
        📈 Price Forecast — <?= htmlspecialchars($prop['name']) ?>
    </h3>
    <canvas id="recChart" height="70"></canvas>
</div>

<!-- Recommendation rows -->
<div class="space-y-4">
<?php foreach ($recs as $rec):
    $date      = $rec['date'];
    $isToday   = $date === $today;
    $dt        = new DateTime($date);
    $dateLabel = $dt->format('l, d M Y');
    $base      = (float)$rec['base_price'];
    $suggested = (float)$rec['suggested_price'];
    $changePct = $base > 0 ? round((($suggested - $base) / $base) * 100, 1) : 0;
    $conf      = (float)$rec['confidence_score'];
    $confClass = $conf >= 0.75 ? 'conf-high' : ($conf >= 0.55 ? 'conf-medium' : 'conf-low');

    $market = Database::getMarketData($prop['location'], $date);
?>
<div class="bg-white rounded-xl shadow-sm border <?= $isToday ? 'border-indigo-400 ring-2 ring-indigo-100' : 'border-gray-100' ?> overflow-hidden">
    <!-- Row header -->
    <div class="flex items-center justify-between px-6 py-4 bg-<?= $isToday ? 'indigo-50' : 'gray-50' ?> border-b border-gray-100 cursor-pointer"
         onclick="toggleRec('rec-<?= $rec['property_id'] ?>-<?= str_replace('-', '', $date) ?>')">
        <div class="flex items-center gap-3">
            <?php if ($isToday): ?><span class="text-xs bg-indigo-600 text-white px-2 py-0.5 rounded-full font-medium">TODAY</span><?php endif; ?>
            <span class="font-semibold text-gray-800"><?= $dateLabel ?></span>
            <?php if ($market && $market['event_flag'] && $market['event_name']): ?>
            <span class="text-xs text-orange-600 bg-orange-50 px-2 py-0.5 rounded-full">🎉 <?= htmlspecialchars($market['event_name']) ?></span>
            <?php endif; ?>
        </div>
        <div class="flex items-center gap-6 text-right">
            <div>
                <span class="text-xl font-bold text-gray-900">RM <?= number_format($suggested, 0) ?></span>
                <span class="text-sm ml-1 <?= $changePct >= 0 ? 'text-green-600' : 'text-red-500' ?>">
                    <?= $changePct >= 0 ? '▲' : '▼' ?> <?= abs($changePct) ?>%
                </span>
            </div>
            <span class="text-xs px-2 py-1 rounded-full <?= $confClass ?>">
                <?= round($conf * 100) ?>% conf
            </span>
            <span class="text-gray-400 text-sm">▼</span>
        </div>
    </div>

    <!-- Expandable detail -->
    <div id="rec-<?= $rec['property_id'] ?>-<?= str_replace('-', '', $date) ?>"
         class="<?= $isToday ? '' : 'hidden' ?> px-6 py-4">
        <div class="grid grid-cols-4 gap-4 mb-4">
            <div><p class="text-xs text-gray-500">Base Price</p><p class="font-semibold">RM <?= number_format($base, 0) ?></p></div>
            <?php if ($market): ?>
            <div><p class="text-xs text-gray-500">Area Occupancy</p><p class="font-semibold"><?= round($market['occupancy_rate'] * 100) ?>%</p></div>
            <div><p class="text-xs text-gray-500">Competitor Avg</p><p class="font-semibold">RM <?= number_format($market['avg_price'], 0) ?></p></div>
            <div><p class="text-xs text-gray-500">Demand</p><p class="font-semibold"><?= PricingEngine::demandLabel((float)$market['occupancy_rate'], (bool)$market['event_flag']) ?></p></div>
            <?php endif; ?>
        </div>

        <!-- Factor bars -->
        <div class="mb-4">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Price Factor Breakdown</p>
            <div class="space-y-2">
                <?php
                $factors = [
                    'Demand Factor'   => (float)$rec['demand_factor'] * 100,
                    'Event Boost'     => (float)$rec['event_boost']   * 100,
                    'Competitor Gap'  => (float)$rec['competitor_gap']* 100,
                    'Occupancy Adj'   => (float)$rec['occupancy_adj'] * 100,
                ];
                foreach ($factors as $label => $pct):
                    $barW  = min(abs($pct) * 3, 100);
                    $color = $pct >= 0 ? 'bg-indigo-500' : 'bg-red-400';
                ?>
                <div class="flex items-center gap-3 text-sm">
                    <span class="w-36 text-gray-600"><?= $label ?></span>
                    <div class="flex-1 bg-gray-100 rounded-full h-2">
                        <div class="<?= $color ?> h-2 rounded-full" style="width:<?= $barW ?>%"></div>
                    </div>
                    <span class="w-16 text-right font-mono text-xs <?= $pct >= 0 ? 'text-indigo-600' : 'text-red-500' ?>">
                        <?= sprintf('%+.1f%%', $pct) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($rec['reason']): ?>
        <div class="reason-bubble">💬 <?= htmlspecialchars($rec['reason']) ?></div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>
<?php endif; ?>

<script>
function toggleRec(id) {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('hidden');
}

const labels    = <?= json_encode($chartLabels) ?>;
const suggested = <?= json_encode(array_map('floatval', $chartSuggested)) ?>;
const base      = <?= json_encode(array_map('floatval', $chartBase)) ?>;
makeRecommendationChart('recChart', labels, suggested, base);
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
