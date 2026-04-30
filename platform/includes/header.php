<?php
require_once __DIR__ . '/auth.php';
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$isLoggedIn  = Auth::check();
$isAdmin     = Auth::isAdmin();
/* Load active theme from settings */
$activeTheme = 'dark';
try {
    $themeSetting = DB::fetch("SELECT value FROM settings WHERE `key`='active_theme'");
    if ($themeSetting && $themeSetting['value']) {
        $allowed = ['dark','bright','modern','zen','punk'];
        $activeTheme = in_array($themeSetting['value'], $allowed) ? $themeSetting['value'] : 'dark';
    }
} catch (Exception $e) { /* fallback to dark */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' . SITE_NAME : SITE_NAME ?></title>
    <meta name="description" content="<?= isset($pageDesc) ? htmlspecialchars($pageDesc) : 'AiServe — the Business Operating System for SMEs. Deploy AI Capsules to automate customer service, sales, HR, finance and more.' ?>">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts (preconnect for speed) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="/assets/css/style.css" rel="stylesheet">
    <!-- Theme System -->
    <link href="/assets/css/themes.css" rel="stylesheet">
    <?= isset($extraHead) ? $extraHead : '' ?>
</head>
<body class="theme-<?= htmlspecialchars($activeTheme) ?>">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top" id="mainNav">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/">
            <i class="bi bi-cpu-fill me-2 text-primary"></i><?= SITE_NAME ?>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav mx-auto gap-1">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>" href="/">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'marketplace' ? 'active' : '' ?>" href="/marketplace.php">Capsule Store</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'pricing' ? 'active' : '' ?>" href="/pricing.php">Pricing</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'about' ? 'active' : '' ?>" href="/about.php">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'contact' ? 'active' : '' ?>" href="/contact.php">Contact</a>
                </li>
                <?php if ($isLoggedIn): ?>
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($_SERVER['PHP_SELF'], '/modules/') ? 'active' : '' ?>" href="/modules/">
                        <i class="bi bi-magic me-1"></i>AI Tools
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex gap-2 align-items-center">
                <?php if ($isLoggedIn): ?>
                    <?php if ($isAdmin): ?>
                        <a href="/admin/" class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-shield-lock me-1"></i>Admin
                        </a>
                    <?php endif; ?>
                    <a href="/dashboard.php" class="btn btn-sm btn-outline-light">
                        <i class="bi bi-grid me-1"></i>Dashboard
                    </a>
                    <a href="/cart.php" class="btn btn-sm btn-outline-light position-relative" title="Cart">
                        <i class="bi bi-cart3"></i>
                        <?php
                        $cartCount = !empty($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
                        if ($cartCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" style="font-size:9px;"><?= $cartCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="/logout.php" class="btn btn-sm btn-danger">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                <?php else: ?>
                    <a href="/cart.php" class="btn btn-sm btn-outline-light position-relative" title="Cart">
                        <i class="bi bi-cart3"></i>
                    </a>
                    <a href="/login.php" class="btn btn-sm btn-outline-light">Login</a>
                    <a href="/register.php" class="btn btn-sm btn-primary">Start Free Trial</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
