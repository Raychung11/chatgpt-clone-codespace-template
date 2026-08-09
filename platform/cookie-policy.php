<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
$pageTitle = 'Cookie Policy';
$pageDesc  = 'How AiServe uses cookies and similar tracking technologies.';
require_once 'includes/header.php';
$updated = '31 May 2026';
?>

<style>
.legal-hero { background:linear-gradient(135deg,rgba(99,102,241,0.07),rgba(139,92,246,0.04)); border-bottom:1px solid rgba(99,102,241,0.12); padding:48px 0 36px; }
.legal-body { max-width:780px; margin:0 auto; }
.legal-toc { background:#111118; border:1px solid rgba(255,255,255,0.06); border-radius:16px; padding:24px; position:sticky; top:80px; }
.legal-toc a { display:block; padding:5px 0; font-size:13px; color:#9ca3af; text-decoration:none; border-left:2px solid transparent; padding-left:12px; transition:all .15s; }
.legal-toc a:hover, .legal-toc a.active { color:#a5b4fc; border-left-color:#6366f1; }
.legal-section { margin-bottom:40px; }
.legal-section h2 { font-size:18px; font-weight:700; color:#fff; margin-bottom:12px; padding-top:16px; }
.legal-section h3 { font-size:15px; font-weight:600; color:#e5e7eb; margin-bottom:8px; margin-top:20px; }
.legal-section p, .legal-section li { color:#9ca3af; font-size:14px; line-height:1.8; }
.legal-section ul { padding-left:20px; }
.legal-section li { margin-bottom:6px; }
.legal-badge { display:inline-block; background:rgba(99,102,241,0.15); color:#a5b4fc; border:1px solid rgba(99,102,241,0.25); border-radius:6px; padding:3px 10px; font-size:12px; font-weight:600; }
.info-box { background:rgba(99,102,241,0.08); border:1px solid rgba(99,102,241,0.2); border-radius:12px; padding:16px 20px; margin:16px 0; }
.info-box p { margin:0; color:#c4c6fd; font-size:13px; }
.cookie-table { width:100%; border-collapse:collapse; font-size:13px; }
.cookie-table th { background:rgba(99,102,241,0.1); color:#a5b4fc; padding:10px 14px; text-align:left; border-bottom:1px solid rgba(255,255,255,0.08); }
.cookie-table td { padding:10px 14px; color:#9ca3af; border-bottom:1px solid rgba(255,255,255,0.05); vertical-align:top; }
.cookie-table tr:last-child td { border-bottom:none; }
.cookie-type { display:inline-block; font-size:10px; font-weight:700; padding:2px 7px; border-radius:4px; }
.ct-essential { background:rgba(16,185,129,0.15); color:#6ee7b7; }
.ct-functional { background:rgba(99,102,241,0.15); color:#a5b4fc; }
.ct-analytics  { background:rgba(245,158,11,0.15); color:#fcd34d; }
</style>

<div class="legal-hero">
    <div class="container">
        <div class="legal-body">
            <span class="legal-badge mb-3 d-inline-block">Legal</span>
            <h1 class="h2 fw-bold text-white mb-2">Cookie Policy</h1>
            <p class="text-muted mb-0">Last updated: <?= $updated ?> &nbsp;·&nbsp; How we use cookies on bizai.my</p>
        </div>
    </div>
</div>

<div class="container py-5">
<div class="row g-5">
<div class="col-lg-3 d-none d-lg-block">
    <div class="legal-toc">
        <div class="text-white fw-semibold small mb-3">Contents</div>
        <a href="#what">1. What Are Cookies</a>
        <a href="#how">2. How We Use Cookies</a>
        <a href="#types">3. Types We Use</a>
        <a href="#list">4. Cookie List</a>
        <a href="#third-party">5. Third-Party Cookies</a>
        <a href="#control">6. Managing Cookies</a>
        <a href="#changes">7. Changes</a>
        <a href="#contact-us">8. Contact</a>
    </div>
</div>
<div class="col-lg-9">
<div class="legal-body" style="max-width:100%">

<div class="info-box mb-5">
    <p><strong style="color:#a5b4fc">In plain English:</strong> We use essential cookies to keep you logged in and make the Platform work. We use minimal analytics cookies to understand how features are used. We do not sell your data or use invasive advertising cookies.</p>
</div>

<div class="legal-section" id="what">
    <h2>1. What Are Cookies?</h2>
    <p>Cookies are small text files placed on your device by a website you visit. They are widely used to make websites work, remember your preferences, and provide analytics to site owners.</p>
    <p>Similar technologies include <strong style="color:#e5e7eb">local storage</strong> and <strong style="color:#e5e7eb">session storage</strong>, which store data in your browser rather than a file. We may also use these for session state and UI preferences.</p>
</div>

<div class="legal-section" id="how">
    <h2>2. How We Use Cookies</h2>
    <p>AiServe uses cookies to:</p>
    <ul>
        <li>Keep you securely logged in during your session</li>
        <li>Remember your preferences (theme, language, dismissed banners)</li>
        <li>Maintain your shopping cart and checkout state</li>
        <li>Understand which features are used most to guide product improvements</li>
        <li>Detect and prevent fraudulent or abusive activity</li>
        <li>Measure performance and load times</li>
    </ul>
</div>

<div class="legal-section" id="types">
    <h2>3. Types of Cookies We Use</h2>
    <h3>Essential Cookies <span class="cookie-type ct-essential ms-2">Required</span></h3>
    <p>These cookies are strictly necessary for the Platform to function. They cannot be disabled. They enable core features such as authentication, security, and session management. Without them, the Platform will not work correctly.</p>

    <h3>Functional Cookies <span class="cookie-type ct-functional ms-2">Optional</span></h3>
    <p>These cookies remember your preferences and personalisation choices — such as your selected UI theme or dismissed notifications. They improve your experience but are not required for the Platform to operate.</p>

    <h3>Analytics Cookies <span class="cookie-type ct-analytics ms-2">Optional</span></h3>
    <p>These cookies help us understand how the Platform is used — which pages are visited, which features are most popular, and where users encounter difficulties. This data is aggregated and anonymised. We use it solely to improve the product.</p>
</div>

<div class="legal-section" id="list">
    <h2>4. Cookie List</h2>
    <div style="background:#111118;border:1px solid rgba(255,255,255,0.06);border-radius:16px;overflow:hidden">
        <div style="overflow-x:auto">
        <table class="cookie-table">
            <thead>
                <tr>
                    <th>Cookie Name</th>
                    <th>Type</th>
                    <th>Purpose</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code style="color:#a5b4fc">PHPSESSID</code></td>
                    <td><span class="cookie-type ct-essential">Essential</span></td>
                    <td>Maintains your login session</td>
                    <td>Session</td>
                </tr>
                <tr>
                    <td><code style="color:#a5b4fc">remember_token</code></td>
                    <td><span class="cookie-type ct-essential">Essential</span></td>
                    <td>"Remember me" persistent login</td>
                    <td>30 days</td>
                </tr>
                <tr>
                    <td><code style="color:#a5b4fc">csrf_token</code></td>
                    <td><span class="cookie-type ct-essential">Essential</span></td>
                    <td>Cross-site request forgery protection</td>
                    <td>Session</td>
                </tr>
                <tr>
                    <td><code style="color:#a5b4fc">dismissed_capsules</code></td>
                    <td><span class="cookie-type ct-functional">Functional</span></td>
                    <td>Remembers dashboard cards you've hidden</td>
                    <td>30 days</td>
                </tr>
                <tr>
                    <td><code style="color:#a5b4fc">ui_theme</code></td>
                    <td><span class="cookie-type ct-functional">Functional</span></td>
                    <td>Stores your selected UI theme preference</td>
                    <td>1 year</td>
                </tr>
                <tr>
                    <td><code style="color:#a5b4fc">cart</code></td>
                    <td><span class="cookie-type ct-functional">Functional</span></td>
                    <td>Stores your shopping cart contents</td>
                    <td>7 days</td>
                </tr>
                <tr>
                    <td><code style="color:#a5b4fc">_stripe_*</code></td>
                    <td><span class="cookie-type ct-essential">Essential</span></td>
                    <td>Payment fraud prevention (set by Stripe)</td>
                    <td>Varies</td>
                </tr>
            </tbody>
        </table>
        </div>
    </div>
</div>

<div class="legal-section" id="third-party">
    <h2>5. Third-Party Cookies</h2>
    <p>Some cookies are set by third-party services we use:</p>
    <ul>
        <li><strong style="color:#e5e7eb">Stripe</strong> — sets cookies for payment processing and fraud prevention. See <a href="https://stripe.com/privacy" target="_blank" style="color:#a5b4fc">Stripe's Privacy Policy</a></li>
        <li><strong style="color:#e5e7eb">Bootstrap CDN / Google Fonts</strong> — these CDN requests may result in cookies being set by those providers for caching purposes</li>
    </ul>
    <p>We do not use Google Analytics, Facebook Pixel, or other advertising/tracking platforms. We do not serve third-party advertising cookies.</p>
</div>

<div class="legal-section" id="control">
    <h2>6. Managing &amp; Disabling Cookies</h2>
    <h3>Browser Settings</h3>
    <p>You can control cookies through your browser settings. Here are links to instructions for common browsers:</p>
    <ul>
        <li><a href="https://support.google.com/chrome/answer/95647" target="_blank" style="color:#a5b4fc">Google Chrome</a></li>
        <li><a href="https://support.mozilla.org/en-US/kb/cookies-information-websites-store-on-your-computer" target="_blank" style="color:#a5b4fc">Mozilla Firefox</a></li>
        <li><a href="https://support.apple.com/guide/safari/manage-cookies-sfri11471/mac" target="_blank" style="color:#a5b4fc">Apple Safari</a></li>
        <li><a href="https://support.microsoft.com/en-us/microsoft-edge/delete-cookies-in-microsoft-edge-63947406-40ac-c3b8-57b9-2a946a29ae09" target="_blank" style="color:#a5b4fc">Microsoft Edge</a></li>
    </ul>
    <div class="info-box mt-3">
        <p><strong>Please note:</strong> Disabling essential cookies will prevent you from logging in and using the Platform. Functional and analytics cookies can be disabled without affecting core Platform functionality.</p>
    </div>
    <h3>Do Not Track</h3>
    <p>We respect the Do Not Track (DNT) signal. If your browser has DNT enabled, we will not set analytics cookies for your session.</p>
</div>

<div class="legal-section" id="changes">
    <h2>7. Changes to This Policy</h2>
    <p>We may update this Cookie Policy as our use of cookies changes or as required by law. We will notify you of material changes via a banner on the Platform or by email. The "Last updated" date at the top of this page reflects the most recent revision.</p>
</div>

<div class="legal-section" id="contact-us">
    <h2>8. Contact Us</h2>
    <p>For questions about our use of cookies or to exercise your rights:</p>
    <div class="info-box">
        <p><strong style="color:#a5b4fc">SLV Group Sdn Bhd</strong><br>
        Email: <a href="mailto:privacy@aiserve.ai" style="color:#a5b4fc">privacy@aiserve.ai</a><br>
        Website: <a href="https://bizai.my" style="color:#a5b4fc">bizai.my</a></p>
    </div>
</div>

</div>
</div>
</div>
</div>

<script>
const sections = document.querySelectorAll('.legal-section[id]');
const links = document.querySelectorAll('.legal-toc a');
window.addEventListener('scroll', () => {
    let current = '';
    sections.forEach(s => { if (window.scrollY >= s.offsetTop - 120) current = s.id; });
    links.forEach(a => { a.classList.toggle('active', a.getAttribute('href') === '#' + current); });
});
</script>

<?php require_once 'includes/footer.php'; ?>
