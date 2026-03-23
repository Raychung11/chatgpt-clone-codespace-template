<?php
/**
 * Shared header partial.
 * $pageTitle and $activePage must be set before including this.
 */
$pageTitle  = $pageTitle  ?? 'STRate AI';
$activePage = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — STRate AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Sidebar -->
<div class="flex">
<aside class="w-64 min-h-screen bg-gray-900 text-white flex flex-col fixed top-0 left-0 z-10">
    <div class="p-6 border-b border-gray-700">
        <div class="flex items-center gap-3">
            <span class="text-3xl">🏠</span>
            <div>
                <h1 class="text-xl font-bold">STRate AI</h1>
                <p class="text-xs text-gray-400">Revenue Decision Engine</p>
            </div>
        </div>
    </div>

    <nav class="flex-1 p-4 space-y-1">
        <?php
        $nav = [
            ['href' => '/index.php',           'icon' => '📊', 'label' => 'Dashboard'],
            ['href' => '/market.php',           'icon' => '📈', 'label' => 'Market Data'],
            ['href' => '/recommendations.php',  'icon' => '💰', 'label' => 'Recommendations'],
            ['href' => '/properties.php',       'icon' => '🏢', 'label' => 'Properties'],
            ['href' => '/scraper.php',          'icon' => '🕷️',  'label' => 'Scraper'],
            ['href' => '/logs.php',             'icon' => '⚙️',  'label' => 'System Logs'],
        ];
        foreach ($nav as $item):
            $active = ($activePage === $item['label']) ? 'bg-indigo-600' : 'hover:bg-gray-700';
        ?>
        <a href="<?= $item['href'] ?>"
           class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition <?= $active ?>">
            <span><?= $item['icon'] ?></span>
            <?= $item['label'] ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="p-4 border-t border-gray-700 text-xs text-gray-500">
        v<?= APP_VERSION ?> · Klang Valley MVP
    </div>
</aside>

<!-- Main content -->
<main class="ml-64 flex-1 p-8">
    <div class="max-w-7xl mx-auto">
