<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
$pageTitle = 'Refund Policy';
$pageDesc  = 'AiServe refund and cancellation policy for subscriptions and purchases.';
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
.success-box { background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.2); border-radius:12px; padding:16px 20px; margin:16px 0; }
.success-box p { margin:0; color:#6ee7b7; font-size:13px; }
.policy-table { width:100%; border-collapse:collapse; font-size:13px; }
.policy-table th { background:rgba(99,102,241,0.1); color:#a5b4fc; padding:10px 14px; text-align:left; border-bottom:1px solid rgba(255,255,255,0.08); }
.policy-table td { padding:10px 14px; color:#9ca3af; border-bottom:1px solid rgba(255,255,255,0.05); }
.policy-table tr:last-child td { border-bottom:none; }
.policy-table .yes { color:#6ee7b7; font-weight:600; }
.policy-table .no  { color:#fca5a5; font-weight:600; }
.policy-table .partial { color:#fcd34d; font-weight:600; }
</style>

<div class="legal-hero">
    <div class="container">
        <div class="legal-body">
            <span class="legal-badge mb-3 d-inline-block">Legal</span>
            <h1 class="h2 fw-bold text-white mb-2">Refund Policy</h1>
            <p class="text-muted mb-0">Last updated: <?= $updated ?> &nbsp;·&nbsp; We believe in being fair</p>
        </div>
    </div>
</div>

<div class="container py-5">
<div class="row g-5">
<div class="col-lg-3 d-none d-lg-block">
    <div class="legal-toc">
        <div class="text-white fw-semibold small mb-3">Contents</div>
        <a href="#overview">1. Overview</a>
        <a href="#trial">2. Free Trial</a>
        <a href="#monthly">3. Monthly Plans</a>
        <a href="#annual">4. Annual Plans</a>
        <a href="#eligible">5. Eligible Refunds</a>
        <a href="#not-eligible">6. Non-Refundable</a>
        <a href="#process">7. How to Request</a>
        <a href="#timeline">8. Processing Time</a>
        <a href="#cancellation">9. Cancellation</a>
        <a href="#contact-us">10. Contact</a>
    </div>
</div>
<div class="col-lg-9">
<div class="legal-body" style="max-width:100%">

<div class="success-box mb-5">
    <p><strong>Our commitment:</strong> We want you to be satisfied with AiServe. If you experience a genuine issue with our Platform, we will work with you to resolve it — including issuing a refund where appropriate.</p>
</div>

<!-- Summary Table -->
<div style="background:#111118;border:1px solid rgba(255,255,255,0.06);border-radius:16px;overflow:hidden;margin-bottom:40px">
    <div style="padding:16px 20px;border-bottom:1px solid rgba(255,255,255,0.06)">
        <span class="text-white fw-semibold" style="font-size:14px">Quick Reference</span>
    </div>
    <div style="overflow-x:auto">
    <table class="policy-table">
        <thead>
            <tr>
                <th>Scenario</th>
                <th>Refund Available</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>Free trial cancellation</td><td class="yes">No charge</td><td>Never billed during trial</td></tr>
            <tr><td>Monthly plan — cancel within 7 days</td><td class="yes">Yes</td><td>Full refund of that month</td></tr>
            <tr><td>Monthly plan — cancel after 7 days</td><td class="no">No</td><td>Access until period end</td></tr>
            <tr><td>Annual plan — cancel within 14 days</td><td class="yes">Yes</td><td>Full refund</td></tr>
            <tr><td>Annual plan — cancel after 14 days</td><td class="partial">Partial</td><td>Pro-rated unused months</td></tr>
            <tr><td>Platform outage &gt; 24 hours</td><td class="yes">Yes</td><td>Credit or refund for affected period</td></tr>
            <tr><td>Duplicate charge</td><td class="yes">Yes</td><td>Full refund of duplicate</td></tr>
            <tr><td>Change of mind (after policy window)</td><td class="no">No</td><td>—</td></tr>
        </tbody>
    </table>
    </div>
</div>

<div class="legal-section" id="overview">
    <h2>1. Overview</h2>
    <p>This Refund Policy applies to all subscriptions and purchases made on the AiServe Platform operated by SLV Group Sdn Bhd. All fees are charged in Malaysian Ringgit (RM) and processed via Stripe.</p>
    <p>Refunds are issued to the original payment method. We do not offer refunds as account credits except where the original payment method is unavailable.</p>
</div>

<div class="legal-section" id="trial">
    <h2>2. Free Trial</h2>
    <p>All new users receive a <strong style="color:#e5e7eb"><?= TRIAL_DAYS ?>-day free trial</strong> with no credit card required. You will never be charged during the trial period. Simply let the trial expire or cancel your account — no action is needed to avoid being charged.</p>
</div>

<div class="legal-section" id="monthly">
    <h2>3. Monthly Subscriptions</h2>
    <p>For monthly plans:</p>
    <ul>
        <li>You may request a full refund within <strong style="color:#e5e7eb">7 days</strong> of your initial payment or any renewal charge, provided you have not generated more than 50 AI outputs during that billing period</li>
        <li>After the 7-day window, no refund is issued for the current period — you retain access until the end of the billing cycle</li>
        <li>Cancellation takes effect at the end of the current billing period; you will not be charged for the next cycle</li>
    </ul>
</div>

<div class="legal-section" id="annual">
    <h2>4. Annual Subscriptions</h2>
    <p>For annual (yearly) plans:</p>
    <ul>
        <li>Full refund available within <strong style="color:#e5e7eb">14 days</strong> of payment, provided usage is below 100 AI outputs</li>
        <li>After the 14-day window and within 90 days: a pro-rated refund for unused complete months may be issued at our discretion</li>
        <li>After 90 days: no refund is available for the remainder of the annual term</li>
        <li>Annual plans may be cancelled at any time; no further renewals will occur</li>
    </ul>
</div>

<div class="legal-section" id="eligible">
    <h2>5. Situations Eligible for Refund</h2>
    <p>We will issue a refund or service credit in the following circumstances:</p>
    <ul>
        <li><strong style="color:#e5e7eb">Platform unavailability:</strong> if the Platform is unavailable for more than 24 consecutive hours due to our fault, we will issue a credit for the affected days</li>
        <li><strong style="color:#e5e7eb">Duplicate charges:</strong> if you were charged twice for the same billing period</li>
        <li><strong style="color:#e5e7eb">Unauthorised charge:</strong> if you can demonstrate the charge was made without your authorisation</li>
        <li><strong style="color:#e5e7eb">Technical failure:</strong> if a Capsule you paid for is completely non-functional and we are unable to resolve the issue within 14 days</li>
        <li><strong style="color:#e5e7eb">Billing error:</strong> if we charged you at the wrong rate due to our error</li>
    </ul>
</div>

<div class="legal-section" id="not-eligible">
    <h2>6. Non-Refundable Situations</h2>
    <p>Refunds will not be issued for:</p>
    <ul>
        <li>Change of mind after the applicable refund window</li>
        <li>Dissatisfaction with AI-generated content quality</li>
        <li>Failure to cancel before an auto-renewal</li>
        <li>Accounts suspended or terminated for Terms of Service violations</li>
        <li>Partial months (monthly plans) or partial use of features</li>
        <li>Third-party services or integrations not provided by AiServe</li>
    </ul>
</div>

<div class="legal-section" id="process">
    <h2>7. How to Request a Refund</h2>
    <ol>
        <li>Email <a href="mailto:billing@aiserve.ai" style="color:#a5b4fc">billing@aiserve.ai</a> with the subject line <strong style="color:#e5e7eb">"Refund Request — [Your Account Email]"</strong></li>
        <li>Include your registered email address, subscription plan, date of charge, and reason for the request</li>
        <li>Our billing team will review your request within <strong style="color:#e5e7eb">3 business days</strong></li>
        <li>If approved, the refund will be processed to your original payment method</li>
    </ol>
    <div class="info-box">
        <p>Providing more context helps us process your request faster. Screenshots of errors or billing discrepancies are especially helpful.</p>
    </div>
</div>

<div class="legal-section" id="timeline">
    <h2>8. Processing Time</h2>
    <p>Once a refund is approved:</p>
    <ul>
        <li>Stripe processes the refund within <strong style="color:#e5e7eb">5–10 business days</strong></li>
        <li>The refund will appear on your statement within 10–15 business days depending on your bank</li>
        <li>You will receive an email confirmation once the refund has been issued</li>
    </ul>
</div>

<div class="legal-section" id="cancellation">
    <h2>9. Cancellation</h2>
    <p>You may cancel your subscription at any time from <strong style="color:#e5e7eb">Dashboard → Account Settings → Subscription</strong>. Cancellation:</p>
    <ul>
        <li>Takes effect at the end of the current billing period</li>
        <li>Does not delete your account or data immediately</li>
        <li>Stops all future charges</li>
        <li>Retains your access until the period end date</li>
    </ul>
    <p>To delete your account entirely, contact <a href="mailto:hello@aiserve.ai" style="color:#a5b4fc">hello@aiserve.ai</a>.</p>
</div>

<div class="legal-section" id="contact-us">
    <h2>10. Contact Us</h2>
    <div class="info-box">
        <p><strong style="color:#a5b4fc">Billing Support</strong><br>
        Email: <a href="mailto:billing@aiserve.ai" style="color:#a5b4fc">billing@aiserve.ai</a><br>
        Response time: within 3 business days<br>
        Hours: Monday–Friday, 9am–6pm MYT</p>
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
