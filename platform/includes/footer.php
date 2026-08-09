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
                    <li class="mb-2"><a href="/affiliate.php" class="text-muted text-decoration-none">Affiliates</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h6 class="text-white fw-semibold mb-3">Legal</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="/privacy-policy.php" class="text-muted text-decoration-none">Privacy Policy</a></li>
                    <li class="mb-2"><a href="/terms-of-service.php" class="text-muted text-decoration-none">Terms of Service</a></li>
                    <li class="mb-2"><a href="/refund-policy.php" class="text-muted text-decoration-none">Refund Policy</a></li>
                    <li class="mb-2"><a href="/cookie-policy.php" class="text-muted text-decoration-none">Cookie Policy</a></li>
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

<!-- AI Memory Widget (auto-shows on module pages that set window.AI_MODULE_KEY) -->
<div id="memoryWidget" style="display:none;position:fixed;bottom:24px;left:24px;z-index:1050">
    <div style="background:#1a1a2e;border:1px solid rgba(99,102,241,0.35);border-radius:50px;padding:7px 14px 7px 10px;display:flex;align-items:center;gap:8px;box-shadow:0 4px 20px rgba(0,0,0,0.5)">
        <i class="bi bi-brain" style="color:#6366f1;font-size:15px"></i>
        <span id="memoryCount" style="color:#a5b4fc;font-size:12px;font-weight:500">0 exchanges</span>
        <button id="memoryClearBtn" title="Clear conversation memory"
            style="background:none;border:none;padding:0 2px;cursor:pointer;color:#6b7280;line-height:1"
            onclick="clearMemory()">
            <i class="bi bi-trash3" style="font-size:12px"></i>
        </button>
    </div>
</div>

<script>
(function () {
    if (typeof window.AI_MODULE_KEY === 'undefined') return;

    const moduleKey = window.AI_MODULE_KEY;
    const widget    = document.getElementById('memoryWidget');
    const countEl   = document.getElementById('memoryCount');

    function loadCount() {
        fetch('/api/memory-status.php?module=' + encodeURIComponent(moduleKey))
            .then(r => r.json())
            .then(d => {
                if (d.ok) {
                    const n = d.count;
                    countEl.textContent = n === 1 ? '1 exchange' : n + ' exchanges';
                    widget.style.display = n > 0 ? 'block' : 'none';
                }
            })
            .catch(() => {});
    }

    window.clearMemory = function () {
        if (!confirm('Clear AI memory for this tool? The AI will forget your previous context.')) return;
        const fd = new FormData();
        fd.append('module', moduleKey);
        fetch('/api/clear-memory.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.ok) {
                    countEl.textContent = '0 exchanges';
                    widget.style.display = 'none';
                }
            })
            .catch(() => {});
    };

    // Expose refresh so modules can call it after each generation
    window.refreshMemoryWidget = loadCount;

    loadCount();
})();

// Global fetch interceptor: redirect to upgrade page if API returns upgrade:true
(function () {
    const _fetch = window.fetch;
    window.fetch = async function (...args) {
        const res = await _fetch(...args);
        const clone = res.clone();
        try {
            const url = typeof args[0] === 'string' ? args[0] : (args[0]?.url ?? '');
            if (url.includes('ai-generate.php')) {
                const data = await clone.json();
                if (data && data.upgrade === true) {
                    window.location.href = '/upgrade.php';
                    return res;
                }
            }
        } catch (e) {}
        return res;
    };
})();
</script>
</body>
</html>
