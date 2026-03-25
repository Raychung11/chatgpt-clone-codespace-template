<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#e94560">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= htmlspecialchars($appTitle ?? 'F&B Loyalty') ?></title>
    <link rel="manifest" href="/public/manifest.json">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/public/css/app.css" rel="stylesheet">
    <?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body>

<!-- App Header -->
<?php if (isset($showHeader) && $showHeader): ?>
<div class="app-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <?php if (isset($headerBack)): ?>
            <a href="<?= $headerBack ?>" class="text-white me-2"><i class="bi bi-arrow-left"></i></a>
            <?php endif; ?>
            <span class="header-title"><?= htmlspecialchars($pageHeader ?? '') ?></span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if (isset($headerRight)) echo $headerRight; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Main Content -->
<div class="container-fluid px-3 pt-3">
