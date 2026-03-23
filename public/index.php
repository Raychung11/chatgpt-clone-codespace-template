<?php
require_once __DIR__ . '/../src/bootstrap.php';

$pageTitle  = 'Dashboard';
$activePage = 'Dashboard';

$properties = Database::getAllProperties();
$today      = date('Y-m-d');

// KPI aggregation
$totalProps  = count($properties);
$recsToday   = [];
foreach ($properties as $p) {
    $r = Database::getLatestRecommendation((int)$p['id'], $today);
    if ($r) $recsToday[] = $r;
}

$avgSuggested  = $recsToday ? array_sum(array_column($recsToday,'suggested_price')) / count($recsToday) : 0;
$avgBase       = $recsToday ? array_sum(array_column($recsToday,'base_price'))      / count($recsToday) : 0;
$avgConf       = $recsToday ? array_sum(array_column($recsToday,'confidence_score'))/ count($recsToday) : 0;
$revenueSignal = $avgBase > 0 ? round((($avgSuggested - $avgBase) / $avgBase) * 100, 1) : 0;

require_once __DIR__ . '/partials/header.php';
?>

<!-- Page header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Dashboard</h2>
        <p class="text-sm text-gray-500 mt-1"><?= date('l, d F Y') ?></p>
    </div>
    <div class="flex gap-3">
        <button onclick="runCronJob('fetch_market_data', this)"
                class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
            🔄 Refresh Market Data
        </button>
        <button onclick="runCronJob('generate_recommendations', this)"
                class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
            ⚡ Generate Recommendations
        </button>
    </div>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-4 gap-6 mb-8">
    <?php
    $kpis = [
        ['label' => 'Active Properties',  'value' => $totalProps,
         'sub'   => 'Klang Valley',       'icon'  => '🏠'],
        ['label' => 'Avg Price Today',
         'value' => $avgSuggested ? 'RM ' . number_format($avgSuggested, 0) : '—',
         'sub'   => ($revenueSignal >= 0 ? '▲ ' : '▼ ') . abs($revenueSignal) . '% vs base',
         'icon'  => '💰', 'sub_color' => $revenueSignal >= 0 ? 'text-green-600' : 'text-red-500'],
        ['label' => 'Recs Ready',
         'value' => count($recsToday) . ' / ' . $totalProps,
         'sub'   => 'for today',          'icon'  => '✅'],
        ['label' => 'Avg Confidence',
         'value' => $avgConf ? round($avgConf * 100) . '%' : '—',
         'sub'   => 'pricing accuracy',   'icon'  => '🎯'],
    ];
    foreach ($kpis as $k): ?>
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-sm text-gray-500"><?= $k['label'] ?></p>
                <p class="text-2xl font-bold text-gray-900 mt-1"><?= $k['value'] ?></p>
                <p class="text-xs mt-1 <?= $k['sub_color'] ?? 'text-gray-400' ?>"><?= $k['sub'] ?></p>
            </div>
            <span class="text-3xl"><?= $k['icon'] ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Property Cards -->
<h3 class="text-lg font-semibold text-gray-900 mb-4">Today's Recommendations</h3>

<?php if (empty($properties)): ?>
<div class="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
    <p class="text-gray-500">No properties yet.</p>
    <a href="/properties.php" class="mt-3 inline-block text-indigo-600 font-medium text-sm hover:underline">
        + Add your first property →
    </a>
</div>
<?php else: ?>
<div class="space-y-4">
<?php foreach ($properties as $prop):
    $rec    = Database::getLatestRecommendation((int)$prop['id'], $today);
    $market = Database::getMarketData($prop['location'], $today);
    $changePct = 0;
    if ($rec) {
        $changePct = $rec['base_price'] > 0
            ? round((($rec['suggested_price'] - $rec['base_price']) / $rec['base_price']) * 100, 1)
            : 0;
    }
    $demandLabel = $market ? PricingEngine::demandLabel(
        (float)$market['occupancy_rate'], (bool)$market['event_flag']
    ) : '—';
    $conf = $rec ? (float)$rec['confidence_score'] : 0;
    $confClass = $conf >= 0.75 ? 'conf-high' : ($conf >= 0.55 ? 'conf-medium' : 'conf-low');
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <div class="flex items-start gap-6">
        <!-- Property info -->
        <div class="flex-1">
            <h4 class="text-base font-semibold text-gray-900"><?= htmlspecialchars($prop['name']) ?></h4>
            <p class="text-sm text-gray-500 mt-0.5">
                📍 <?= htmlspecialchars($prop['location']) ?>
                · <?= str_replace('_', ' ', ucwords($prop['room_type'], '_')) ?>
                · <?= $prop['bedrooms'] ?> BR
            </p>
            <?php if ($market): ?>
            <p class="text-sm mt-2"><span class="font-medium">Demand:</span> <?= htmlspecialchars($demandLabel) ?></p>
            <?php if ($market['event_flag'] && $market['event_name']): ?>
            <p class="text-sm text-orange-600 mt-1">🎉 Event: <?= htmlspecialchars($market['event_name']) ?></p>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Price -->
        <div class="text-right min-w-[140px]">
            <?php if ($rec): ?>
            <p class="text-sm text-gray-500">Suggested Price</p>
            <p class="text-3xl font-bold text-gray-900">RM <?= number_format($rec['suggested_price'], 0) ?></p>
            <p class="text-sm mt-1 <?= $changePct >= 0 ? 'text-green-600' : 'text-red-500' ?>">
                <?= $changePct >= 0 ? '▲' : '▼' ?>
                <?= abs($changePct) ?>% from base RM<?= number_format($rec['base_price'], 0) ?>
            </p>
            <?php else: ?>
            <p class="text-sm text-gray-400 italic">No recommendation yet</p>
            <button onclick="generateRecommendations(<?= $prop['id'] ?>, this)"
                    class="mt-2 text-xs text-indigo-600 hover:underline font-medium">
                Generate now →
            </button>
            <?php endif; ?>
        </div>

        <!-- Confidence -->
        <?php if ($rec): ?>
        <div class="text-center min-w-[100px]">
            <p class="text-sm text-gray-500">Confidence</p>
            <p class="text-2xl font-bold text-gray-900 mt-1"><?= round($conf * 100) ?>%</p>
            <span class="text-xs px-2 py-0.5 rounded-full <?= $confClass ?>">
                <?= $conf >= 0.75 ? 'High' : ($conf >= 0.55 ? 'Medium' : 'Low') ?>
            </span>
            <?php if ($market): ?>
            <p class="text-xs text-gray-400 mt-1">Occ: <?= round((float)$market['occupancy_rate'] * 100) ?>%</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($rec && $rec['reason']): ?>
    <div class="reason-bubble mt-4">💬 <?= htmlspecialchars($rec['reason']) ?></div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
