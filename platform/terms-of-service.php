<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
$pageTitle = 'Terms of Service';
$pageDesc  = 'Terms and conditions governing the use of the AiServe platform.';
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
.legal-section ul, .legal-section ol { padding-left:20px; }
.legal-section li { margin-bottom:6px; }
.legal-badge { display:inline-block; background:rgba(99,102,241,0.15); color:#a5b4fc; border:1px solid rgba(99,102,241,0.25); border-radius:6px; padding:3px 10px; font-size:12px; font-weight:600; }
.info-box { background:rgba(99,102,241,0.08); border:1px solid rgba(99,102,241,0.2); border-radius:12px; padding:16px 20px; margin:16px 0; }
.info-box p { margin:0; color:#c4c6fd; font-size:13px; }
.warn-box { background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.2); border-radius:12px; padding:16px 20px; margin:16px 0; }
.warn-box p { margin:0; color:#fcd34d; font-size:13px; }
</style>

<div class="legal-hero">
    <div class="container">
        <div class="legal-body">
            <span class="legal-badge mb-3 d-inline-block">Legal</span>
            <h1 class="h2 fw-bold text-white mb-2">Terms of Service</h1>
            <p class="text-muted mb-0">Last updated: <?= $updated ?> &nbsp;·&nbsp; Please read carefully before using the Platform</p>
        </div>
    </div>
</div>

<div class="container py-5">
<div class="row g-5">
<div class="col-lg-3 d-none d-lg-block">
    <div class="legal-toc">
        <div class="text-white fw-semibold small mb-3">Contents</div>
        <a href="#acceptance">1. Acceptance</a>
        <a href="#services">2. Services</a>
        <a href="#accounts">3. Accounts</a>
        <a href="#subscriptions">4. Subscriptions &amp; Billing</a>
        <a href="#trial">5. Free Trial</a>
        <a href="#acceptable-use">6. Acceptable Use</a>
        <a href="#ip">7. Intellectual Property</a>
        <a href="#content">8. Your Content</a>
        <a href="#ai">9. AI Services</a>
        <a href="#warranties">10. Disclaimers</a>
        <a href="#liability">11. Limitation of Liability</a>
        <a href="#termination">12. Termination</a>
        <a href="#governing">13. Governing Law</a>
        <a href="#contact-us">14. Contact</a>
    </div>
</div>
<div class="col-lg-9">
<div class="legal-body" style="max-width:100%">

<div class="info-box mb-5">
    <p><strong style="color:#a5b4fc">Agreement to Terms.</strong> By accessing or using the AiServe Platform, you agree to be bound by these Terms of Service and our Privacy Policy. If you are using the Platform on behalf of a company, you represent that you have authority to bind that company to these terms.</p>
</div>

<div class="legal-section" id="acceptance">
    <h2>1. Acceptance of Terms</h2>
    <p>These Terms of Service ("Terms") form a binding legal agreement between you ("User", "Customer") and <strong style="color:#e5e7eb">SLV Group Sdn Bhd</strong> (Company No: [SSM Registration]), operating as <strong style="color:#e5e7eb">AiServe</strong> ("Company", "we", "us"), regarding your use of the AiServe Business Operating System and AI Capsules accessible at <strong style="color:#e5e7eb">bizai.my</strong> (the "Platform").</p>
    <p>If you do not agree to these Terms, you may not access or use the Platform.</p>
</div>

<div class="legal-section" id="services">
    <h2>2. Description of Services</h2>
    <p>AiServe provides a cloud-based Business Operating System ("BOS") for SMEs, consisting of:</p>
    <ul>
        <li><strong style="color:#e5e7eb">BOS Core:</strong> the foundational platform including CRM, automation engine, and dashboard</li>
        <li><strong style="color:#e5e7eb">AI Capsules:</strong> modular add-on tools for customer service, sales, HR, finance, operations, and more</li>
        <li><strong style="color:#e5e7eb">AI Tools:</strong> standalone productivity tools for content generation, proposals, invoices, etc.</li>
    </ul>
    <p>We reserve the right to modify, suspend, or discontinue any feature at any time with reasonable notice.</p>
</div>

<div class="legal-section" id="accounts">
    <h2>3. Accounts &amp; Registration</h2>
    <ul>
        <li>You must provide accurate, complete, and current information during registration</li>
        <li>You are responsible for maintaining the confidentiality of your password</li>
        <li>You are responsible for all activity that occurs under your account</li>
        <li>You must notify us immediately at <a href="mailto:hello@aiserve.ai" style="color:#a5b4fc">hello@aiserve.ai</a> of any unauthorised use</li>
        <li>One person may not maintain more than one free account</li>
        <li>Accounts are non-transferable without prior written consent</li>
    </ul>
</div>

<div class="legal-section" id="subscriptions">
    <h2>4. Subscriptions &amp; Billing</h2>
    <h3>4.1 Subscription Plans</h3>
    <p>Subscriptions are offered on monthly or annual billing cycles. Annual plans are billed upfront and offer a discount. Current pricing is displayed on our <a href="/pricing.php" style="color:#a5b4fc">Pricing page</a>.</p>
    <h3>4.2 Payment</h3>
    <p>Payments are processed via Stripe. By subscribing, you authorise us to charge your payment method on a recurring basis. All prices are in Malaysian Ringgit (RM) and exclusive of applicable taxes unless stated otherwise.</p>
    <h3>4.3 Auto-Renewal</h3>
    <p>Subscriptions automatically renew at the end of each billing period. You may cancel at any time before the renewal date to avoid being charged for the next period.</p>
    <h3>4.4 Price Changes</h3>
    <p>We will provide at least 30 days' notice before increasing subscription prices. Continued use after the effective date constitutes acceptance.</p>
    <h3>4.5 Taxes</h3>
    <p>You are responsible for all applicable taxes. Where required by law (e.g., SST in Malaysia), taxes will be added to your invoice.</p>
</div>

<div class="legal-section" id="trial">
    <h2>5. Free Trial</h2>
    <p>New users receive a <strong style="color:#e5e7eb"><?= TRIAL_DAYS ?>-day free trial</strong> upon registration. No credit card is required to start a trial. At the end of the trial:</p>
    <ul>
        <li>Access to paid Capsules will be restricted</li>
        <li>Your data will be retained for 30 days to allow you to subscribe</li>
        <li>No charges are incurred if you do not subscribe</li>
    </ul>
    <p>One free trial per person or organisation. We reserve the right to modify or terminate the trial offer at any time.</p>
</div>

<div class="legal-section" id="acceptable-use">
    <h2>6. Acceptable Use Policy</h2>
    <p>You agree not to use the Platform to:</p>
    <ul>
        <li>Violate any applicable laws or regulations</li>
        <li>Transmit spam, malware, or harmful code</li>
        <li>Attempt to gain unauthorised access to the Platform or other users' accounts</li>
        <li>Scrape, reverse-engineer, or decompile any part of the Platform</li>
        <li>Generate content that is illegal, defamatory, hateful, or infringes third-party rights</li>
        <li>Resell or sub-license the Platform without express written permission</li>
        <li>Use the Platform for any purpose competitive with AiServe without our consent</li>
        <li>Circumvent usage limits or access restrictions</li>
    </ul>
    <p>Violation of this policy may result in immediate suspension or termination of your account.</p>
</div>

<div class="legal-section" id="ip">
    <h2>7. Intellectual Property</h2>
    <p>The Platform, including its software, design, trademarks, logos, and documentation, is the exclusive property of SLV Group Sdn Bhd and is protected by Malaysian and international intellectual property laws.</p>
    <p>We grant you a limited, non-exclusive, non-transferable licence to access and use the Platform solely for your internal business purposes during your subscription period.</p>
    <p>You may not copy, modify, distribute, sell, or lease any part of the Platform without our prior written consent.</p>
</div>

<div class="legal-section" id="content">
    <h2>8. Your Content</h2>
    <p>You retain ownership of all data and content you submit to the Platform ("Customer Content"). By submitting content, you grant us a limited licence to process it solely to provide the services.</p>
    <p>You represent and warrant that you have all necessary rights to submit such content and that it does not violate any third-party rights or applicable laws.</p>
</div>

<div class="legal-section" id="ai">
    <h2>9. AI-Generated Content</h2>
    <div class="warn-box mb-3">
        <p><strong>Important:</strong> AI-generated outputs are provided for informational and productivity purposes only. They do not constitute legal, financial, medical, or professional advice.</p>
    </div>
    <ul>
        <li>You are solely responsible for reviewing, validating, and using AI-generated content</li>
        <li>AI outputs may be inaccurate, incomplete, or outdated — always verify before acting on them</li>
        <li>We do not warrant the accuracy of AI-generated content</li>
        <li>We are not liable for decisions made based on AI outputs</li>
    </ul>
</div>

<div class="legal-section" id="warranties">
    <h2>10. Disclaimers</h2>
    <p>The Platform is provided on an <strong style="color:#e5e7eb">"as is" and "as available"</strong> basis without warranties of any kind, express or implied, including but not limited to warranties of merchantability, fitness for a particular purpose, and non-infringement.</p>
    <p>We do not warrant that the Platform will be uninterrupted, error-free, or free of viruses or other harmful components.</p>
</div>

<div class="legal-section" id="liability">
    <h2>11. Limitation of Liability</h2>
    <p>To the fullest extent permitted by law, SLV Group Sdn Bhd shall not be liable for any:</p>
    <ul>
        <li>Indirect, incidental, special, consequential, or punitive damages</li>
        <li>Loss of profits, revenue, data, business, or goodwill</li>
        <li>Damages arising from use or inability to use the Platform</li>
    </ul>
    <p>Our total aggregate liability to you in connection with these Terms shall not exceed the total amount paid by you to us in the <strong style="color:#e5e7eb">3 months preceding the claim</strong>.</p>
</div>

<div class="legal-section" id="termination">
    <h2>12. Termination</h2>
    <p>You may cancel your account at any time via your dashboard or by contacting us. We may suspend or terminate your account immediately if:</p>
    <ul>
        <li>You breach these Terms</li>
        <li>Payment fails and is not resolved within 7 days</li>
        <li>We are required to do so by law</li>
    </ul>
    <p>Upon termination, your right to use the Platform ceases immediately. We will retain your data for 30 days post-termination to allow export, after which it will be permanently deleted.</p>
</div>

<div class="legal-section" id="governing">
    <h2>13. Governing Law &amp; Disputes</h2>
    <p>These Terms are governed by the laws of <strong style="color:#e5e7eb">Malaysia</strong>. Any disputes shall first be attempted to be resolved through good-faith negotiation. If unresolved within 30 days, disputes shall be subject to the exclusive jurisdiction of the courts of Malaysia.</p>
</div>

<div class="legal-section" id="contact-us">
    <h2>14. Contact Us</h2>
    <div class="info-box">
        <p><strong style="color:#a5b4fc">SLV Group Sdn Bhd</strong><br>
        Email: <a href="mailto:legal@aiserve.ai" style="color:#a5b4fc">legal@aiserve.ai</a><br>
        General: <a href="mailto:hello@aiserve.ai" style="color:#a5b4fc">hello@aiserve.ai</a><br>
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
