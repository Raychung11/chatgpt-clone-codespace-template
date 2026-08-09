<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
$pageTitle = 'Privacy Policy';
$pageDesc  = 'How AiServe collects, uses, and protects your personal data.';
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
</style>

<!-- Hero -->
<div class="legal-hero">
    <div class="container">
        <div class="legal-body">
            <span class="legal-badge mb-3 d-inline-block">Legal</span>
            <h1 class="h2 fw-bold text-white mb-2">Privacy Policy</h1>
            <p class="text-muted mb-0">Last updated: <?= $updated ?> &nbsp;·&nbsp; Effective immediately</p>
        </div>
    </div>
</div>

<div class="container py-5">
<div class="row g-5">
<div class="col-lg-3 d-none d-lg-block">
    <div class="legal-toc">
        <div class="text-white fw-semibold small mb-3">Contents</div>
        <a href="#overview">1. Overview</a>
        <a href="#data-collect">2. Data We Collect</a>
        <a href="#data-use">3. How We Use Data</a>
        <a href="#data-share">4. Sharing &amp; Disclosure</a>
        <a href="#cookies">5. Cookies</a>
        <a href="#retention">6. Data Retention</a>
        <a href="#rights">7. Your Rights</a>
        <a href="#security">8. Security</a>
        <a href="#ai-data">9. AI &amp; Processing</a>
        <a href="#children">10. Children</a>
        <a href="#changes">11. Changes</a>
        <a href="#contact-us">12. Contact</a>
    </div>
</div>
<div class="col-lg-9">
<div class="legal-body" style="max-width:100%">

<div class="info-box mb-5">
    <p><strong style="color:#a5b4fc">Your privacy matters to us.</strong> This policy explains how SLV Group Sdn Bhd ("AiServe", "we", "us") collects, uses, and protects your personal data when you use the AiServe Business Operating System and its AI Capsules.</p>
</div>

<div class="legal-section" id="overview">
    <h2>1. Overview</h2>
    <p>AiServe operates a Software-as-a-Service (SaaS) platform at <strong style="color:#e5e7eb">bizai.my</strong> (the "Platform"). By accessing or using the Platform, you agree to the collection and use of information in accordance with this policy. This policy applies to all users, including free trial users, paid subscribers, and visitors.</p>
    <p>We are committed to compliance with the <strong style="color:#e5e7eb">Personal Data Protection Act 2010 (PDPA)</strong> of Malaysia, and where applicable, the General Data Protection Regulation (GDPR).</p>
</div>

<div class="legal-section" id="data-collect">
    <h2>2. Data We Collect</h2>
    <h3>2.1 Information You Provide</h3>
    <ul>
        <li><strong style="color:#e5e7eb">Account data:</strong> name, email address, phone number, company name, job title</li>
        <li><strong style="color:#e5e7eb">Billing data:</strong> payment method details (processed securely via Stripe; we do not store card numbers)</li>
        <li><strong style="color:#e5e7eb">Business data:</strong> content you enter into AI Capsules — documents, prompts, conversation history, reports</li>
        <li><strong style="color:#e5e7eb">Communications:</strong> messages sent to our support team</li>
    </ul>
    <h3>2.2 Data Collected Automatically</h3>
    <ul>
        <li>IP address and approximate location</li>
        <li>Browser type, operating system, device identifiers</li>
        <li>Pages visited, features used, time spent, click patterns</li>
        <li>Session data and access logs</li>
    </ul>
    <h3>2.3 Data from Third Parties</h3>
    <ul>
        <li>Payment status from Stripe</li>
        <li>Analytics data from aggregate usage metrics</li>
    </ul>
</div>

<div class="legal-section" id="data-use">
    <h2>3. How We Use Your Data</h2>
    <p>We use the information we collect to:</p>
    <ul>
        <li>Provide, operate, and maintain the Platform and AI Capsules</li>
        <li>Process payments and manage subscriptions</li>
        <li>Send transactional emails (receipts, alerts, renewal notices)</li>
        <li>Improve the Platform through usage analytics and feedback</li>
        <li>Send product updates and marketing communications (you may opt out at any time)</li>
        <li>Comply with legal obligations and enforce our Terms of Service</li>
        <li>Detect and prevent fraud, abuse, or security incidents</li>
        <li>Respond to support requests</li>
    </ul>
    <p>We process your data on the legal bases of <em>contract performance</em>, <em>legitimate interests</em>, and <em>consent</em> where required.</p>
</div>

<div class="legal-section" id="data-share">
    <h2>4. Sharing &amp; Disclosure</h2>
    <p>We do <strong style="color:#e5e7eb">not sell</strong> your personal data. We may share data with:</p>
    <ul>
        <li><strong style="color:#e5e7eb">Service providers:</strong> Stripe (payments), cloud hosting providers, email delivery services — bound by confidentiality obligations</li>
        <li><strong style="color:#e5e7eb">AI providers:</strong> prompts and content submitted to AI Capsules may be processed by third-party large language model APIs; this data is used only to generate your response and is not used to train models</li>
        <li><strong style="color:#e5e7eb">Legal authorities:</strong> when required by law, court order, or to protect our legal rights</li>
        <li><strong style="color:#e5e7eb">Business transfers:</strong> in the event of a merger, acquisition, or asset sale, your data may transfer to the successor entity with prior notice</li>
    </ul>
</div>

<div class="legal-section" id="cookies">
    <h2>5. Cookies</h2>
    <p>We use cookies and similar tracking technologies to operate the Platform. See our <a href="/cookie-policy.php" style="color:#a5b4fc">Cookie Policy</a> for full details. You can control cookies through your browser settings.</p>
</div>

<div class="legal-section" id="retention">
    <h2>6. Data Retention</h2>
    <ul>
        <li><strong style="color:#e5e7eb">Account data:</strong> retained for the duration of your account plus 2 years after closure</li>
        <li><strong style="color:#e5e7eb">Billing records:</strong> retained for 7 years to comply with financial regulations</li>
        <li><strong style="color:#e5e7eb">AI-generated content:</strong> retained while your account is active; deleted within 30 days of account deletion</li>
        <li><strong style="color:#e5e7eb">Access logs:</strong> retained for 90 days</li>
    </ul>
    <p>You may request deletion of your data at any time (see Section 7).</p>
</div>

<div class="legal-section" id="rights">
    <h2>7. Your Rights</h2>
    <p>Subject to applicable law, you have the right to:</p>
    <ul>
        <li><strong style="color:#e5e7eb">Access</strong> the personal data we hold about you</li>
        <li><strong style="color:#e5e7eb">Correct</strong> inaccurate or incomplete data</li>
        <li><strong style="color:#e5e7eb">Delete</strong> your data ("right to be forgotten")</li>
        <li><strong style="color:#e5e7eb">Port</strong> your data to another service in a structured format</li>
        <li><strong style="color:#e5e7eb">Object</strong> to processing for marketing purposes</li>
        <li><strong style="color:#e5e7eb">Withdraw consent</strong> at any time where processing is based on consent</li>
    </ul>
    <p>To exercise your rights, email us at <a href="mailto:privacy@aiserve.ai" style="color:#a5b4fc">privacy@aiserve.ai</a>. We will respond within 14 business days.</p>
</div>

<div class="legal-section" id="security">
    <h2>8. Security</h2>
    <p>We implement industry-standard safeguards including TLS encryption in transit, encrypted storage, access controls, and regular security audits. However, no method of transmission over the Internet is 100% secure. We encourage you to use a strong password and keep your credentials confidential.</p>
</div>

<div class="legal-section" id="ai-data">
    <h2>9. AI Processing &amp; Data</h2>
    <p>AiServe Capsules use AI to generate content based on your inputs. Please be aware:</p>
    <ul>
        <li>Do not submit sensitive personal data (e.g., IC numbers, passwords) into AI prompts</li>
        <li>AI-generated content is not legal, financial, or medical advice</li>
        <li>Prompts may be logged for quality assurance; they are never shared with other customers</li>
        <li>Model providers (e.g., Anthropic) process your prompt in real-time; their own privacy policies apply</li>
    </ul>
</div>

<div class="legal-section" id="children">
    <h2>10. Children's Privacy</h2>
    <p>The Platform is intended for business users aged 18 and above. We do not knowingly collect personal data from minors. If you believe a minor has provided us with data, please contact us for immediate removal.</p>
</div>

<div class="legal-section" id="changes">
    <h2>11. Changes to This Policy</h2>
    <p>We may update this Privacy Policy from time to time. We will notify registered users by email at least 14 days before material changes take effect. Continued use of the Platform after the effective date constitutes acceptance of the updated policy.</p>
</div>

<div class="legal-section" id="contact-us">
    <h2>12. Contact Us</h2>
    <p>For privacy-related questions or requests:</p>
    <div class="info-box">
        <p><strong style="color:#a5b4fc">SLV Group Sdn Bhd</strong><br>
        Data Protection Officer<br>
        Email: <a href="mailto:privacy@aiserve.ai" style="color:#a5b4fc">privacy@aiserve.ai</a><br>
        Website: <a href="https://bizai.my" style="color:#a5b4fc">bizai.my</a><br>
        Malaysia</p>
    </div>
</div>

</div>
</div>
</div>
</div>

<script>
// Highlight active TOC link on scroll
const sections = document.querySelectorAll('.legal-section[id]');
const links = document.querySelectorAll('.legal-toc a');
window.addEventListener('scroll', () => {
    let current = '';
    sections.forEach(s => { if (window.scrollY >= s.offsetTop - 120) current = s.id; });
    links.forEach(a => { a.classList.toggle('active', a.getAttribute('href') === '#' + current); });
});
</script>

<?php require_once 'includes/footer.php'; ?>
