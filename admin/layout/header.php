<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> – F&B Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #1a1a2e;
            --sidebar-active: #e94560;
            --sidebar-text: #a8b2d8;
            --sidebar-width: 260px;
        }
        body { background: #f4f6fb; font-family: 'Segoe UI', system-ui, sans-serif; }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            top: 0; left: 0;
            z-index: 1000;
            overflow-y: auto;
        }
        .sidebar-brand {
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            color: #fff;
            font-weight: 700;
            font-size: 1.1rem;
        }
        .sidebar-brand span { color: var(--sidebar-active); }
        .sidebar .nav-link {
            color: var(--sidebar-text);
            padding: .55rem 1.5rem;
            font-size: .9rem;
            display: flex;
            align-items: center;
            gap: .6rem;
            border-radius: 0;
            transition: background .15s, color .15s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(233,69,96,.15);
            color: #fff;
            border-left: 3px solid var(--sidebar-active);
        }
        .sidebar .nav-section {
            color: #5a6886;
            font-size: .7rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 1rem 1.5rem .3rem;
        }

        /* Main */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        .topbar {
            background: #fff;
            border-bottom: 1px solid #e3e8f0;
            padding: .75rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        .page-wrapper { padding: 1.5rem; }

        /* Cards */
        .stat-card {
            border: none;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            background: #fff;
            box-shadow: 0 1px 8px rgba(0,0,0,.06);
        }
        .stat-card .stat-icon {
            width: 48px; height: 48px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform .25s; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<nav class="sidebar d-flex flex-column">
    <div class="sidebar-brand">🍽️ F&B <span>Admin</span></div>

    <div class="flex-grow-1 pt-2">
        <div class="nav-section">Main</div>
        <a href="/admin/dashboard" class="nav-link <?= (($activePage ?? '') === 'dashboard') ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="nav-section">CRM</div>
        <a href="/admin/customers" class="nav-link <?= (($activePage ?? '') === 'customers') ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Customers
        </a>
        <a href="/admin/loyalty" class="nav-link <?= (($activePage ?? '') === 'loyalty') ? 'active' : '' ?>">
            <i class="bi bi-star"></i> Loyalty Points
        </a>

        <div class="nav-section">Operations</div>
        <a href="/admin/reservations" class="nav-link <?= (($activePage ?? '') === 'reservations') ? 'active' : '' ?>">
            <i class="bi bi-calendar-check"></i> Reservations
        </a>
        <a href="/admin/orders" class="nav-link <?= (($activePage ?? '') === 'orders') ? 'active' : '' ?>">
            <i class="bi bi-receipt"></i> Orders
        </a>
        <a href="/admin/outlets" class="nav-link <?= (($activePage ?? '') === 'outlets') ? 'active' : '' ?>">
            <i class="bi bi-shop"></i> Outlets
        </a>

        <div class="nav-section">Marketing</div>
        <a href="/admin/rewards" class="nav-link <?= (($activePage ?? '') === 'rewards') ? 'active' : '' ?>">
            <i class="bi bi-gift"></i> Rewards
        </a>
        <a href="/admin/campaigns" class="nav-link <?= (($activePage ?? '') === 'campaigns') ? 'active' : '' ?>">
            <i class="bi bi-megaphone"></i> Campaigns
        </a>
        <a href="/admin/notifications" class="nav-link <?= (($activePage ?? '') === 'notifications') ? 'active' : '' ?>">
            <i class="bi bi-bell"></i> Notifications
        </a>

        <div class="nav-section">System</div>
        <a href="/admin/settings" class="nav-link <?= (($activePage ?? '') === 'settings') ? 'active' : '' ?>">
            <i class="bi bi-gear"></i> Settings
        </a>
    </div>

    <div class="px-3 py-3 border-top border-secondary">
        <div class="d-flex align-items-center gap-2 text-white small">
            <div class="rounded-circle bg-danger d-flex align-items-center justify-content-center"
                 style="width:32px;height:32px;font-size:.8rem;">
                <?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="flex-grow-1 overflow-hidden">
                <div class="fw-semibold text-truncate"><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></div>
                <div style="color:#5a6886;font-size:.75rem;"><?= ucfirst($_SESSION['user_role'] ?? '') ?></div>
            </div>
            <a href="/admin/logout" class="text-muted" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>
</nav>

<!-- Main Content Wrapper -->
<div class="main-content">
    <!-- Topbar -->
    <div class="topbar d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-light d-md-none" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
            <h6 class="mb-0 fw-semibold"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h6>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success">● Live</span>
            <span class="text-muted small"><?= date('d M Y') ?></span>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if ($msg = get_flash('success')): ?>
    <div class="alert alert-success alert-dismissible mx-3 mt-3 mb-0 py-2 small" role="alert">
        <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($msg = get_flash('error')): ?>
    <div class="alert alert-danger alert-dismissible mx-3 mt-3 mb-0 py-2 small" role="alert">
        <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="page-wrapper">
