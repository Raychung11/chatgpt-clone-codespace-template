<?php
/**
 * PlotGold Malaysia — HTML Head + Opening Body
 * Usage: include with $page_title, $meta_description, $body_class set
 */
defined('PLOTGOLD') or die('Direct access not permitted.');

$page_title       = $page_title       ?? get_setting('site_name', 'PlotGold Malaysia');
$meta_description = $meta_description ?? 'Malaysia\'s trusted marketplace for verified resale burial plots, columbarium niches and dignified funeral planning.';
$body_class       = $body_class       ?? '';
$site_name        = get_setting('site_name', 'PlotGold Malaysia');
?>
<!doctype html>
<html lang="<?= html_lang() ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= h($meta_description) ?>">
    <meta property="og:title" content="<?= h($page_title) ?>">
    <meta property="og:description" content="<?= h($meta_description) ?>">
    <meta property="og:type" content="website">
    <meta property="og:image" content="<?= asset_url('img/og-default.jpg') ?>">
    <link rel="canonical" href="<?= h((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '')) ?>">
    <title><?= h($page_title) ?> — <?= h($site_name) ?></title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;600&display=swap" rel="stylesheet">
    <!-- PlotGold CSS -->
    <link rel="stylesheet" href="<?= asset_url('css/app.css') ?>">
    <?= $extra_head ?? '' ?>
</head>
<body class="<?= h($body_class) ?>">
