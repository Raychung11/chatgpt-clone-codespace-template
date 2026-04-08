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
                <p class="text-muted small">Malaysia's trusted marketplace for verified resale burial plots, columbarium niches, and dignified funeral planning.</p>
                <a href="<?= whatsapp_link('Hi PlotGold, I need help.') ?>" target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm">
                    <i class="fab fa-whatsapp me-1"></i>WhatsApp Us
                </a>
            </div>

            <!-- Marketplace -->
            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="footer-heading">Marketplace</h6>
                <ul class="list-unstyled footer-links">
                    <li><a href="<?= pg_url('browse_listings.php') ?>">Browse Listings</a></li>
                    <li><a href="<?= pg_url('browse_listings.php?type=columbarium') ?>">Columbarium Niches</a></li>
                    <li><a href="<?= pg_url('browse_listings.php?type=family-lot') ?>">Family Lots</a></li>
                    <li><a href="<?= pg_url('sell_plot.php') ?>">Sell My Plot</a></li>
                    <li><a href="<?= pg_url('compare-burial-plots') ?>">Compare Listings</a></li>
                </ul>
            </div>

            <!-- Planning -->
            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="footer-heading">Planning</h6>
                <ul class="list-unstyled footer-links">
                    <li><a href="<?= pg_url('funeral-planner') ?>">DIY Funeral Planner</a></li>
                    <li><a href="<?= pg_url('providers.php') ?>">Service Providers</a></li>
                    <li><a href="<?= pg_url('request_quote.php') ?>">Request a Quote</a></li>
                    <li><a href="<?= pg_url('urgent-funeral-help') ?>">Urgent Help</a></li>
                </ul>
            </div>

            <!-- Company -->
            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="footer-heading">Company</h6>
                <ul class="list-unstyled footer-links">
                    <li><a href="<?= pg_url('about.php') ?>">About Us</a></li>
                    <li><a href="<?= pg_url('how_it_works.php') ?>">How It Works</a></li>
                    <li><a href="<?= pg_url('faq.php') ?>">FAQ</a></li>
                    <li><a href="<?= pg_url('contact.php') ?>">Contact</a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div class="col-lg-3 col-md-6">
                <h6 class="footer-heading">Get In Touch</h6>
                <ul class="list-unstyled footer-links">
                    <li><i class="fas fa-envelope me-2 text-muted"></i><a href="mailto:<?= h(get_setting('site_email', 'hello@plotgold.my')) ?>"><?= h(get_setting('site_email', 'hello@plotgold.my')) ?></a></li>
                    <li><i class="fab fa-whatsapp me-2 text-muted"></i><a href="<?= whatsapp_link() ?>" target="_blank" rel="noopener"><?= h(get_setting('site_phone', '+60 11-XXXX XXXX')) ?></a></li>
                    <li class="mt-3 small text-muted">Serving Klang Valley &amp; Selangor</li>
                </ul>
            </div>
        </div>

        <hr class="footer-divider">

        <div class="row align-items-center py-3">
            <div class="col-md-6 text-center text-md-start">
                <p class="small text-muted mb-0">&copy; <?= $year ?> PlotGold Malaysia. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-center text-md-end mt-2 mt-md-0">
                <a href="<?= pg_url('privacy.php') ?>" class="small text-muted me-3">Privacy Policy</a>
                <a href="<?= pg_url('terms.php') ?>" class="small text-muted me-3">Terms of Use</a>
                <a href="<?= pg_url('sitemap.xml') ?>" class="small text-muted">Sitemap</a>
            </div>
        </div>

        <div class="row pb-3">
            <div class="col-12 text-center">
                <p class="small text-muted mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    PlotGold Malaysia is a listing and planning platform only. We do not provide legal, financial, or medical advice. All transactions are between buyers and sellers directly.
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
