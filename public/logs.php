<?php
require_once __DIR__ . '/../src/bootstrap.php';

$pageTitle  = 'System Logs';
$activePage = 'System Logs';

$logs         = Database::getCronLogs(100);
$filterStatus = $_GET['status'] ?? 'all';
if ($filterStatus !== 'all') {
    $logs = array_filter($logs, fn($l) => $l['status'] === $filterStatus);
}

require_once __DIR__ . '/partials/header.php';
?>

<div class="flex items-center justify-between mb-8">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">System & Cron Jobs</h2>
        <p class="text-sm text-gray-500 mt-1">Monitor automated jobs and run them manually</p>
    </div>
</div>

<!-- Manual job triggers -->
<h3 class="text-base font-semibold text-gray-700 mb-4">▶️ Run Jobs Manually</h3>
<div class="grid grid-cols-3 gap-4 mb-8">
    <?php
    $jobs = [
        ['key' => 'fetch_market_data',        'icon' => '🔄', 'label' => 'Fetch Market Data',
         'desc' => 'Updates competitor prices & occupancy for all 8 locations.'],
        ['key' => 'generate_recommendations', 'icon' => '💰', 'label' => 'Generate Recommendations',
         'desc' => 'Runs pricing engine for all properties, next 7 days.'],
        ['key' => 'daily_summary',            'icon' => '📋', 'label' => 'Daily Summary',
         'desc' => 'Generates the nightly summary report for all owners.'],
    ];
    foreach ($jobs as $job): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-start gap-3 mb-3">
            <span class="text-2xl"><?= $job['icon'] ?></span>
            <div>
                <p class="font-semibold text-gray-900"><?= $job['label'] ?></p>
                <p class="text-xs text-gray-500 mt-0.5"><?= $job['desc'] ?></p>
            </div>
        </div>
        <button onclick="runCronJob('<?= $job['key'] ?>', this)"
                class="w-full px-4 py-2 bg-gray-100 hover:bg-indigo-600 hover:text-white text-gray-700 rounded-lg text-sm font-medium transition">
            Run <?= $job['label'] ?>
        </button>
    </div>
    <?php endforeach; ?>
</div>

<!-- Cron schedule reference -->
<details class="bg-gray-900 text-green-400 rounded-xl p-6 mb-8 font-mono text-sm">
    <summary class="cursor-pointer font-semibold text-gray-300 mb-2">📅 Hostinger Cron Schedule (click to expand)</summary>
    <pre class="mt-4"># Edit with: crontab -e

# Fetch market data every 4 hours
0 */4 * * * php <?= dirname($_SERVER['DOCUMENT_ROOT']) ?>/cron/fetch_market_data.php >> /var/log/strate_fetch.log 2>&1

# Generate recommendations daily at 6AM MYT
0 6 * * * php <?= dirname($_SERVER['DOCUMENT_ROOT']) ?>/cron/generate_recommendations.php >> /var/log/strate_recs.log 2>&1

# Daily summary at 9PM MYT
0 21 * * * php <?= dirname($_SERVER['DOCUMENT_ROOT']) ?>/cron/daily_summary.php >> /var/log/strate_summary.log 2>&1</pre>
</details>

<!-- Log table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-semibold text-gray-900">Job Logs</h3>
        <div class="flex gap-2">
            <?php foreach (['all' => 'All', 'success' => '✅ Success', 'error' => '❌ Error'] as $k => $l): ?>
            <a href="?status=<?= $k ?>"
               class="px-3 py-1 text-xs rounded-full border <?= $filterStatus === $k ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 text-gray-600 hover:bg-gray-50' ?>">
                <?= $l ?>
            </a>
            <?php endforeach; ?>
            <a href="?" class="px-3 py-1 text-xs border border-gray-300 rounded-full text-gray-600 hover:bg-gray-50">🔄 Refresh</a>
        </div>
    </div>

    <?php if (empty($logs)): ?>
    <div class="p-12 text-center text-gray-400 text-sm">No logs yet. Run a job above.</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full data-table text-sm">
            <thead>
                <tr>
                    <th class="text-left">Timestamp</th>
                    <th class="text-left">Job</th>
                    <th>Status</th>
                    <th class="text-left">Message</th>
                    <th class="text-right">Duration</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td class="text-gray-500 text-xs whitespace-nowrap"><?= $log['created_at'] ?></td>
                <td class="font-mono text-xs text-gray-700"><?= htmlspecialchars($log['job_name']) ?></td>
                <td class="text-center">
                    <span class="badge-<?= htmlspecialchars($log['status']) ?>">
                        <?= $log['status'] === 'success' ? '✅ success' : '❌ error' ?>
                    </span>
                </td>
                <td class="text-xs text-gray-600 max-w-xs truncate"><?= htmlspecialchars($log['message']) ?></td>
                <td class="text-right text-xs text-gray-400"><?= $log['duration_ms'] ?>ms</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- System info -->
<details class="mt-6 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <summary class="cursor-pointer font-medium text-gray-700">ℹ️ System Info</summary>
    <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
        <?php
        $sysinfo = [
            'PHP Version'   => phpversion(),
            'Server'        => php_uname('s') . ' ' . php_uname('r'),
            'App Version'   => APP_VERSION,
            'Environment'   => APP_ENV,
            'Timezone'      => APP_TIMEZONE,
            'DB Host'       => DB_HOST,
            'DB Name'       => DB_NAME,
            'OpenAI'        => OPENAI_API_KEY ? '✅ Configured' : '⚠️ Not set',
            'AiServe'       => AISENSY_API_KEY ? '✅ Configured' : '⚠️ Not set',
        ];
        foreach ($sysinfo as $k => $v): ?>
        <div class="flex justify-between border-b border-gray-50 py-1">
            <span class="text-gray-500"><?= $k ?></span>
            <span class="font-mono text-gray-800"><?= htmlspecialchars($v) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</details>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
