<?php
/**
 * PlotGold Malaysia — Navigation Bar
 */
defined('PLOTGOLD') or die('Direct access not permitted.');

$whatsapp_msg = urlencode('Hi PlotGold! I need assistance.');
$whatsapp_url = 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . $whatsapp_msg;
$_cur_lang    = current_lang();
?>
<!-- Urgent Banner -->
<div class="urgent-banner d-none d-md-block">
    <div class="container d-flex justify-content-between align-items-center">
        <span><i class="fas fa-phone-alt me-2"></i><?= _e('home.urgent_body') ?></span>
        <a href="<?= $whatsapp_url ?>" target="_blank" rel="noopener" class="btn btn-sm btn-whatsapp">
            <i class="fab fa-whatsapp me-1"></i><?= _e('home.urgent_btn') ?>
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
                        <?= _e('nav.browse') ?>
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navBrowse">
                        <li><a class="dropdown-item" href="<?= pg_url('browse_listings.php') ?>"><?= _e('nav.browse_all') ?></a></li>
                        <li><a class="dropdown-item" href="<?= pg_url('browse_listings.php?state=Selangor') ?>"><?= _e('nav.browse_selangor') ?></a></li>
                        <li><a class="dropdown-item" href="<?= pg_url('browse_listings.php?state=Kuala+Lumpur') ?>"><?= _e('nav.browse_kl') ?></a></li>
                        <li><a class="dropdown-item" href="<?= pg_url('browse_listings.php?city=Kajang') ?>"><?= _e('nav.browse_kajang') ?></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= pg_url('buyer/compare.php') ?>"><i class="fas fa-balance-scale me-2 text-muted"></i><?= _e('nav.compare') ?></a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="<?= pg_url('sell_plot.php') ?>"><?= _e('nav.sell') ?></a>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navPlan" role="button" data-bs-toggle="dropdown">
                        <?= _e('nav.plan') ?>
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navPlan">
                        <li><a class="dropdown-item" href="<?= pg_url('diy_funeral_planner.php') ?>"><i class="fas fa-list-alt me-2 text-muted"></i><?= _e('nav.diy') ?></a></li>
                        <li><a class="dropdown-item" href="<?= pg_url('providers.php') ?>"><i class="fas fa-briefcase me-2 text-muted"></i><?= _e('nav.providers') ?></a></li>
                        <li><a class="dropdown-item" href="<?= pg_url('request_quote.php') ?>"><i class="fas fa-file-invoice me-2 text-muted"></i><?= _e('btn.get_quote') ?></a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="<?= pg_url('request_quote.php?mode=urgent') ?>">
                        <i class="fas fa-hands-helping text-danger me-1"></i><?= _e('nav.urgent') ?>
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav align-items-center gap-2">

                <!-- ── Language Switcher ── -->
                <li class="nav-item d-flex align-items-center">
                    <div class="lang-switcher d-flex gap-1 align-items-center me-1">
                        <?php if ($_cur_lang === 'en'): ?>
                            <span class="lang-pill lang-active">EN</span>
                            <span class="lang-sep">|</span>
                            <a href="<?= lang_switch_url('zh') ?>" class="lang-pill">中文</a>
                        <?php else: ?>
                            <a href="<?= lang_switch_url('en') ?>" class="lang-pill">EN</a>
                            <span class="lang-sep">|</span>
                            <span class="lang-pill lang-active">中文</span>
                        <?php endif; ?>
                    </div>
                </li>

                <?php if (auth_check()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="navUser" role="button" data-bs-toggle="dropdown">
                            <div class="nav-avatar"><i class="fas fa-user"></i></div>
                            <span class="d-none d-lg-inline"><?= h(auth_user_name()) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navUser">
                            <?php if (auth_has_role(ROLE_SELLER)): ?>
                                <li><a class="dropdown-item" href="<?= pg_url('seller/dashboard.php') ?>"><i class="fas fa-th-large me-2 text-muted"></i><?= _e('seller.dashboard') ?></a></li>
                            <?php endif; ?>
                            <?php if (auth_has_role(ROLE_BUYER)): ?>
                                <li><a class="dropdown-item" href="<?= pg_url('buyer/dashboard.php') ?>"><i class="fas fa-home me-2 text-muted"></i><?= _e('buyer.dashboard') ?></a></li>
                            <?php endif; ?>
                            <?php if (auth_has_role(ROLE_PROVIDER)): ?>
                                <li><a class="dropdown-item" href="<?= pg_url('provider/dashboard.php') ?>"><i class="fas fa-briefcase me-2 text-muted"></i><?= _e('provider.dashboard') ?></a></li>
                            <?php endif; ?>
                            <?php if (auth_is_admin()): ?>
                                <li><a class="dropdown-item" href="<?= pg_url('admin/') ?>"><i class="fas fa-cog me-2 text-muted"></i><?= _e('admin.dashboard') ?></a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= pg_url('logout.php') ?>"><i class="fas fa-sign-out-alt me-2 text-muted"></i><?= _e('nav.logout') ?></a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= pg_url('login.php') ?>"><?= _e('nav.login') ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-gold btn-sm" href="<?= pg_url('register.php') ?>"><?= _e('nav.list_plot') ?></a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

