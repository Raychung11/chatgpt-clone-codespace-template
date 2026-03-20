<?php
require_once __DIR__ . '/auth.php';
Auth::requireAdmin();
$adminPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | Admin' : 'Admin Panel' ?> — <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <link href="/assets/css/admin.css" rel="stylesheet">
    <?= isset($extraHead) ? $extraHead : '' ?>
</head>
<body class="admin-body">

<!-- Sidebar -->
<div class="admin-sidebar d-flex flex-column" id="adminSidebar">
    <div class="admin-logo px-4 py-3">
        <a href="/" class="text-white text-decoration-none fw-bold fs-5">
            <i class="bi bi-cpu-fill me-2 text-primary"></i><?= SITE_NAME ?>
        </a>
        <div class="text-muted small mt-1">Admin Panel</div>
    </div>

    <nav class="admin-nav flex-grow-1 px-3 py-2">
        <div class="nav-section-label">Overview</div>
        <a href="/admin/" class="admin-nav-link <?= $adminPage === 'index' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2"></i> Dashboard
        </a>

        <div class="nav-section-label mt-3">Catalogue</div>
        <a href="/admin/products.php" class="admin-nav-link <?= $adminPage === 'products' ? 'active' : '' ?>">
            <i class="bi bi-cpu"></i> Products
        </a>
        <a href="/admin/categories.php" class="admin-nav-link <?= $adminPage === 'categories' ? 'active' : '' ?>">
            <i class="bi bi-tags"></i> Categories
        </a>

        <div class="nav-section-label mt-3">Customers</div>
        <a href="/admin/clients.php" class="admin-nav-link <?= $adminPage === 'clients' ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Customers
        </a>
        <a href="/admin/subscriptions.php" class="admin-nav-link <?= $adminPage === 'subscriptions' ? 'active' : '' ?>">
            <i class="bi bi-repeat"></i> Subscriptions
        </a>
        <a href="/admin/leads.php" class="admin-nav-link <?= $adminPage === 'leads' ? 'active' : '' ?>">
            <i class="bi bi-funnel"></i> Leads
            <?php
            $newLeads = DB::fetch('SELECT COUNT(*) as n FROM leads WHERE status="new"')['n'];
            if ($newLeads > 0) echo "<span class='badge bg-warning text-dark ms-auto'>$newLeads</span>";
            ?>
        </a>

        <div class="nav-section-label mt-3">Finance</div>
        <a href="/admin/revenue.php" class="admin-nav-link <?= $adminPage === 'revenue' ? 'active' : '' ?>">
            <i class="bi bi-graph-up"></i> Revenue
        </a>

        <div class="nav-section-label mt-3">Settings</div>
        <a href="/admin/settings.php" class="admin-nav-link <?= $adminPage === 'settings' ? 'active' : '' ?>">
            <i class="bi bi-gear"></i> Settings
        </a>
    </nav>

    <div class="px-3 py-3 border-top border-secondary border-opacity-25">
        <div class="d-flex align-items-center gap-2 mb-2">
            <div class="avatar-initials sm"><?= strtoupper(substr($_SESSION['user_name'],0,2)) ?></div>
            <div class="flex-grow-1 min-width-0">
                <div class="text-white small fw-semibold text-truncate"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                <div class="text-muted text-truncate" style="font-size:11px">Administrator</div>
            </div>
        </div>
        <a href="/" class="btn btn-outline-secondary btn-sm w-100 mb-1"><i class="bi bi-globe me-1"></i>View Site</a>
        <a href="/logout.php" class="btn btn-outline-danger btn-sm w-100"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
    </div>
</div>

<!-- Main Content Wrapper -->
<div class="admin-main">
    <!-- Top Bar -->
    <div class="admin-topbar d-flex align-items-center justify-content-between px-4 py-2">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
        <div class="ms-auto d-flex align-items-center gap-3">
            <a href="/marketplace.php" class="text-muted small text-decoration-none"><i class="bi bi-globe me-1"></i>Live Site</a>
            <span class="text-muted small"><?= date('D, d M Y') ?></span>
        </div>
    </div>
