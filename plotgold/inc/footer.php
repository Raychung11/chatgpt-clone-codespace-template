<?php
/**
 * PlotGold Malaysia — Footer + Closing Scripts
 */
defined('PLOTGOLD') or die('Direct access not permitted.');
$year = date('Y');
?>
<footer class="pg-footer mt-auto">
    <div class="container">
        <div class="row g-4 py-5">
            <!-- Brand col -->
            <div class="col-lg-3 col-md-6">
                <div class="mb-3">
                    <span class="brand-pg fs-4">Plot</span><span class="brand-gold fs-4">Gold</span>
                    <span class="brand-my">Malaysia</span>
                </div>
                <p class="text-muted small"><?= _e('footer.about_text') ?></p>
                <a href="<?= whatsapp_link(__('footer.contact_text')) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm">
                    <i class="fab fa-whatsapp me-1"></i><?= _e('footer.emergency') ?>
                </a>
            </div>

            <!-- Marketplace -->
            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="footer-heading"><?= _e('footer.quick_links') ?></h6>
                <ul class="list-unstyled footer-links">
                    <li><a href="<?= pg_url('browse_listings.php') ?>"><?= _e('footer.browse') ?></a></li>
                    <li><a href="<?= pg_url('browse_listings.php?type=columbarium') ?>">Columbarium</a></li>
                    <li><a href="<?= pg_url('browse_listings.php?type=family-lot') ?>">Family Lots</a></li>
                    <li><a href="<?= pg_url('sell_plot.php') ?>"><?= _e('footer.sell') ?></a></li>
                    <li><a href="<?= pg_url('buyer/compare.php') ?>"><?= _e('nav.compare') ?></a></li>
                </ul>
            </div>

            <!-- Planning -->
            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="footer-heading"><?= _e('footer.resources') ?></h6>
                <ul class="list-unstyled footer-links">
                    <li><a href="<?= pg_url('diy_funeral_planner.php') ?>"><?= _e('footer.planner') ?></a></li>
                    <li><a href="<?= pg_url('providers.php') ?>"><?= _e('footer.providers') ?></a></li>
                    <li><a href="<?= pg_url('request_quote.php') ?>"><?= _e('btn.get_quote') ?></a></li>
                    <li><a href="<?= pg_url('faq.php') ?>"><?= _e('footer.faq') ?></a></li>
                    <li><a href="<?= pg_url('request_quote.php?mode=urgent') ?>"><?= _e('nav.urgent') ?></a></li>
                </ul>
            </div>

            <!-- Company -->
            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="footer-heading"><?= _e('footer.about') ?></h6>
                <ul class="list-unstyled footer-links">
                    <li><a href="<?= pg_url('about.php') ?>"><?= _e('footer.about') ?></a></li>
                    <li><a href="<?= pg_url('how_it_works.php') ?>"><?= _e('home.how_title') ?></a></li>
                    <li><a href="<?= pg_url('contact.php') ?>"><?= _e('footer.contact') ?></a></li>
                    <li><a href="<?= pg_url('privacy.php') ?>"><?= _e('footer.privacy') ?></a></li>
                    <li><a href="<?= pg_url('terms.php') ?>"><?= _e('footer.terms') ?></a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div class="col-lg-3 col-md-6">
                <h6 class="footer-heading"><?= _e('footer.contact') ?></h6>
                <ul class="list-unstyled footer-links">
                    <li><i class="fas fa-envelope me-2 text-muted"></i><a href="mailto:<?= h(get_setting('site_email', 'hello@plotgold.my')) ?>"><?= h(get_setting('site_email', 'hello@plotgold.my')) ?></a></li>
                    <li><i class="fab fa-whatsapp me-2 text-muted"></i><a href="<?= whatsapp_link(__('nav.urgent')) ?>" target="_blank" rel="noopener"><?= h(get_setting('site_phone', '+60 11-XXXX XXXX')) ?></a></li>
                    <li class="mt-3 small text-muted">Serving Klang Valley &amp; Selangor</li>
                </ul>

                <!-- Language Switcher in Footer -->
                <div class="mt-3 d-flex align-items-center gap-2">
                    <span class="small text-muted">Language:</span>
                    <?php $cl = current_lang(); ?>
                    <a href="<?= lang_switch_url('en') ?>" onclick="return pgSetLang('en')"
                       class="lang-pill <?= $cl === 'en' ? 'lang-active' : '' ?>">EN</a>
                    <span class="lang-sep text-muted">|</span>
                    <a href="<?= lang_switch_url('zh') ?>" onclick="return pgSetLang('zh')"
                       class="lang-pill <?= $cl === 'zh' ? 'lang-active' : '' ?>">中文</a>
                </div>
            </div>
        </div>

        <hr class="footer-divider">

        <div class="row align-items-center py-3">
            <div class="col-md-6 text-center text-md-start">
                <p class="small text-muted mb-0"><?= _e('footer.copyright', ['year' => $year]) ?></p>
            </div>
            <div class="col-md-6 text-center text-md-end mt-2 mt-md-0">
                <a href="<?= pg_url('privacy.php') ?>" class="small text-muted me-3"><?= _e('footer.privacy') ?></a>
                <a href="<?= pg_url('terms.php') ?>" class="small text-muted me-3"><?= _e('footer.terms') ?></a>
                <a href="<?= pg_url('sitemap.xml') ?>" class="small text-muted">Sitemap</a>
            </div>
        </div>

        <div class="row pb-3">
            <div class="col-12 text-center">
                <p class="small text-muted mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    <?= _e('footer.disclaimer_text') ?>
                </p>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<!-- PlotGold JS -->
<script src="<?= asset_url('js/app.js') ?>"></script>
<?= $extra_scripts ?? '' ?>
</body>
</html>
