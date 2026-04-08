<?php
/**
 * PlotGold Malaysia — Navigation Bar
 */
defined('PLOTGOLD') or die('Direct access not permitted.');

$whatsapp_msg = urlencode('Hi PlotGold! I need assistance.');
$whatsapp_url = 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . $whatsapp_msg;
?>
<!-- Urgent Banner -->
<div class="urgent-banner d-none d-md-block">
    <div class="container d-flex justify-content-between align-items-center">
        <span><i class="fas fa-phone-alt me-2"></i>Need urgent funeral assistance? <strong>We're here for you.</strong></span>
        <a href="<?= $whatsapp_url ?>" target="_blank" rel="noopener" class="btn btn-sm btn-whatsapp">
            <i class="fab fa-whatsapp me-1"></i>WhatsApp Now
        </a>
    </div>
</div>

<nav class="navbar navbar-expand-lg navbar-light pg-navbar sticky-top">
    <div class="container">
        <!-- Brand -->
        <a class="navbar-brand" href="<?= pg_url() ?>">
            <span class="brand-pg">Plot</span><span class="brand-gold">Gold</span>
            <small class="brand-my">Malaysia</small>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navBrowse" role="button" data-bs-toggle="dropdown">
                        Browse Plots
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navBrowse">
                        <li><a class="dropdown-item" href="<?= pg_url('browse_listings.php') ?>">All Listings</a></li>
                        <li><a class="dropdown-item" href="<?= pg_url('browse_listings.php?type=burial-plot') ?>">Burial Plots</a></li>
                        <li><a class="dropdown-item" href="<?= pg_url('columbarium-niches') ?>">Columbarium Niches</a></li>
                        <li><a class="dropdown-item" href="<?= pg_url('browse_listings.php?type=family-lot') ?>">Family Lots</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= pg_url('compare-burial-plots') ?>"><i class="fas fa-balance-scale me-2 text-muted"></i>Compare Listings</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="<?= pg_url('sell_plot.php') ?>">Sell My Plot</a>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navPlan" role="button" data-bs-toggle="dropdown">
                        Plan Ahead
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navPlan">
                        <li><a class="dropdown-item" href="<?= pg_url('funeral-planner') ?>"><i class="fas fa-list-alt me-2 text-muted"></i>DIY Funeral Planner</a></li>
                        <li><a class="dropdown-item" href="<?= pg_url('providers.php') ?>"><i class="fas fa-briefcase me-2 text-muted"></i>Service Providers</a></li>
                        <li><a class="dropdown-item" href="<?= pg_url('request_quote.php') ?>"><i class="fas fa-file-invoice me-2 text-muted"></i>Request a Quote</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="<?= pg_url('urgent-funeral-help') ?>">
                        <i class="fas fa-hands-helping text-danger me-1"></i>Urgent Help
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav align-items-center gap-2">
                <?php if (auth_check()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="navUser" role="button" data-bs-toggle="dropdown">
                            <div class="nav-avatar"><i class="fas fa-user"></i></div>
                            <span class="d-none d-lg-inline"><?= h(auth_user_name()) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navUser">
                            <?php if (auth_has_role(ROLE_SELLER)): ?>
                                <li><a class="dropdown-item" href="<?= pg_url('seller/dashboard.php') ?>"><i class="fas fa-th-large me-2 text-muted"></i>Seller Dashboard</a></li>
                            <?php endif; ?>
                            <?php if (auth_has_role(ROLE_BUYER)): ?>
                                <li><a class="dropdown-item" href="<?= pg_url('buyer/dashboard.php') ?>"><i class="fas fa-home me-2 text-muted"></i>Buyer Dashboard</a></li>
                            <?php endif; ?>
                            <?php if (auth_has_role(ROLE_PROVIDER)): ?>
                                <li><a class="dropdown-item" href="<?= pg_url('provider/dashboard.php') ?>"><i class="fas fa-briefcase me-2 text-muted"></i>Provider Dashboard</a></li>
                            <?php endif; ?>
                            <?php if (auth_is_admin()): ?>
                                <li><a class="dropdown-item" href="<?= pg_url('admin/') ?>"><i class="fas fa-cog me-2 text-muted"></i>Admin Panel</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= pg_url('logout.php') ?>"><i class="fas fa-sign-out-alt me-2 text-muted"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= pg_url('login.php') ?>">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-gold btn-sm" href="<?= pg_url('register.php') ?>">List Your Plot</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
