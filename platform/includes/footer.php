<!-- Footer -->
<footer class="footer-dark pt-5 pb-4 mt-5">
    <div class="container">
        <div class="row g-4 mb-4">
            <div class="col-lg-4">
                <a href="/" class="navbar-brand fw-bold fs-5 text-white mb-3 d-inline-block">
                    <i class="bi bi-cpu-fill me-2 text-primary"></i><?= SITE_NAME ?>
                </a>
                <p class="text-muted small">The Business Operating System for SMEs. Deploy AI Capsules to automate operations, grow revenue, and run your business on autopilot.</p>
                <div class="d-flex gap-3 mt-3">
                    <a href="#" class="text-muted fs-5"><i class="bi bi-twitter-x"></i></a>
                    <a href="#" class="text-muted fs-5"><i class="bi bi-linkedin"></i></a>
                    <a href="#" class="text-muted fs-5"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="text-muted fs-5"><i class="bi bi-youtube"></i></a>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <h6 class="text-white fw-semibold mb-3">Platform</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="/marketplace.php" class="text-muted text-decoration-none">Capsule Store</a></li>
                    <li class="mb-2"><a href="#pricing" class="text-muted text-decoration-none">Pricing</a></li>
                    <li class="mb-2"><a href="/register.php" class="text-muted text-decoration-none">Free Trial</a></li>
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Documentation</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h6 class="text-white fw-semibold mb-3">Categories</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="/marketplace.php?cat=customer-service" class="text-muted text-decoration-none">Customer Service</a></li>
                    <li class="mb-2"><a href="/marketplace.php?cat=sales-marketing" class="text-muted text-decoration-none">Sales & Marketing</a></li>
                    <li class="mb-2"><a href="/marketplace.php?cat=hr-recruitment" class="text-muted text-decoration-none">HR & Recruitment</a></li>
                    <li class="mb-2"><a href="/marketplace.php?cat=finance-accounting" class="text-muted text-decoration-none">Finance</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h6 class="text-white fw-semibold mb-3">Company</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none">About Us</a></li>
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Blog</a></li>
                    <li class="mb-2"><a href="#contact" class="text-muted text-decoration-none">Contact</a></li>
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Affiliates</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h6 class="text-white fw-semibold mb-3">Legal</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Privacy Policy</a></li>
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Terms of Service</a></li>
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Refund Policy</a></li>
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Cookie Policy</a></li>
                </ul>
            </div>
        </div>
        <hr class="border-secondary">
        <div class="d-flex flex-wrap justify-content-between align-items-center small text-muted">
            <span>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</span>
            <span>Built with <i class="bi bi-heart-fill text-danger"></i> for SMEs worldwide</span>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="/assets/js/main.js"></script>
<!-- AI Chat Widget -->
<script src="/assets/js/chat-widget.js"></script>
<?= isset($extraScripts) ? $extraScripts : '' ?>
</body>
</html>
