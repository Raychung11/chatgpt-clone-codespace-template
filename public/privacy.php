<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
$page_title       = 'Privacy Policy — SilverDeals MY';
$page_description = 'SilverDeals MY privacy policy. Learn how we collect, use, and protect your personal data in compliance with Malaysia\'s Personal Data Protection Act 2010 (PDPA).';
$updated = 'April 2025';
include __DIR__ . '/../inc/public_header.php';
?>

<section style="background:var(--bg-light);padding:var(--space-xl) 0;border-bottom:1px solid var(--border-color);">
  <div class="container">
    <div style="font-size:13px;color:var(--text-muted);margin-bottom:var(--space-sm);">Last updated: <?= $updated ?></div>
    <h1>Privacy Policy</h1>
    <p style="color:var(--text-muted);font-size:16px;">SilverDeals MY is committed to protecting your personal data under Malaysia's Personal Data Protection Act 2010 (PDPA).</p>
  </div>
</section>

<section class="section">
  <div class="container" style="max-width:760px;">

    <?php
    $sections = [
        ['Who We Are', 'SilverDeals MY is operated by <strong>SLV Lifestyle Sdn Bhd</strong> (Company Registration No. [XXXXXXXXX]), powered by <strong>SLV Group Sdn Bhd</strong>. We operate the website silverdeals.my and related services. References to "we", "us", "our" in this policy refer to SLV Lifestyle Sdn Bhd.'],

        ['Personal Data We Collect', '<p>We collect the following categories of personal data:</p><ul>
<li><strong>Identity Data:</strong> Full name, date of birth, gender, membership number</li>
<li><strong>Contact Data:</strong> Email address, phone number, home address</li>
<li><strong>Identity Verification:</strong> National Identity Card (MyKad) or passport number, document images, selfie photographs</li>
<li><strong>Transaction Data:</strong> Deals claimed, vouchers redeemed, points earned and spent</li>
<li><strong>Technical Data:</strong> IP address, browser type, device information, session data</li>
<li><strong>Profile Data:</strong> Avatar, preferences, referral code</li>
<li><strong>Communications:</strong> Messages sent through contact forms or WhatsApp support</li>
</ul>'],

        ['How We Use Your Data', '<p>We use your personal data to:</p><ul>
<li>Create and manage your SilverDeals MY membership account</li>
<li>Verify your age eligibility (50 years and above) for senior membership</li>
<li>Process deal claims, voucher redemptions, and rewards</li>
<li>Send important account notifications and security alerts</li>
<li>Facilitate the referral programme</li>
<li>Improve our platform, products, and services</li>
<li>Comply with legal obligations</li>
<li>Detect and prevent fraud, abuse, or security incidents</li>
</ul>'],

        ['Legal Basis for Processing', 'We process your personal data under the following lawful bases: <strong>consent</strong> (when you register and accept this policy), <strong>contract performance</strong> (to provide our membership services), <strong>legitimate interests</strong> (fraud prevention, platform security, analytics), and <strong>legal obligation</strong> (compliance with Malaysian law).'],

        ['Sharing Your Data', '<p>We do not sell your personal data. We may share your data with:</p><ul>
<li><strong>Merchants:</strong> Only the information required to process a deal redemption (name, voucher code). We do not share your IC number or home address with merchants.</li>
<li><strong>Service Providers:</strong> Trusted third-party vendors who help operate our platform (hosting, email delivery, analytics), bound by confidentiality agreements.</li>
<li><strong>Legal Authorities:</strong> When required by Malaysian law, court order, or regulatory authority.</li>
</ul>'],

        ['Data Retention', 'We retain your personal data for as long as your account is active. If you close your account, we retain data for 7 years to comply with Malaysian financial and legal record-keeping requirements, after which it is securely deleted. Identity verification documents are retained for 2 years after account closure.'],

        ['Your Rights Under PDPA', '<p>As a data subject under Malaysia\'s Personal Data Protection Act 2010, you have the right to:</p><ul>
<li><strong>Access:</strong> Request a copy of the personal data we hold about you</li>
<li><strong>Correction:</strong> Request correction of inaccurate or outdated data</li>
<li><strong>Withdrawal of Consent:</strong> Withdraw consent to data processing (this may affect your ability to use our services)</li>
<li><strong>Prevent Processing:</strong> Object to certain uses of your data for direct marketing purposes</li>
</ul><p>To exercise these rights, email us at <a href="mailto:privacy@silverdeals.my" style="color:var(--orange-primary);">privacy@silverdeals.my</a> with your name, membership number, and the request details.</p>'],

        ['Cookies', 'We use only essential session cookies necessary to operate the platform (e.g., login sessions, CSRF protection tokens). We do not use tracking or advertising cookies. Session cookies are deleted when you close your browser.'],

        ['Children\'s Privacy', 'SilverDeals MY is a senior membership platform intended for persons aged 50 and above. We do not knowingly collect personal data from persons under 18. If you believe a minor has registered, please contact us immediately.'],

        ['Data Security', 'We implement industry-standard security measures including encrypted passwords (bcrypt), HTTPS enforcement, CSRF protection, rate limiting, and regular security reviews. However, no method of transmission over the internet is 100% secure. We will notify you of any significant data breach as required by law.'],

        ['Changes to This Policy', 'We may update this Privacy Policy from time to time. We will notify registered members of material changes via email or in-app notification. Your continued use of SilverDeals MY after such notification constitutes acceptance of the updated policy.'],

        ['Contact & Complaints', 'For privacy-related enquiries or complaints, contact our Data Protection Officer at <a href="mailto:privacy@silverdeals.my" style="color:var(--orange-primary);">privacy@silverdeals.my</a>. If you are unsatisfied with our response, you may lodge a complaint with the Department of Personal Data Protection Malaysia (JPDP) at <a href="https://www.pdp.gov.my" target="_blank" rel="noopener" style="color:var(--orange-primary);">www.pdp.gov.my</a>.'],
    ];
    ?>

    <?php foreach ($sections as $i => [$title, $body]): ?>
      <div style="margin-bottom:var(--space-2xl);">
        <h3 style="font-size:19px;margin-bottom:var(--space-md);display:flex;align-items:center;gap:var(--space-sm);">
          <span style="width:28px;height:28px;background:var(--orange-primary);color:#fff;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;flex-shrink:0;"><?= $i+1 ?></span>
          <?= $title ?>
        </h3>
        <div style="color:var(--text-muted);font-size:15px;line-height:1.9;">
          <?php if (str_contains($body, '<')): ?>
            <?= $body ?>
          <?php else: ?>
            <p><?= $body ?></p>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <div style="background:var(--orange-bg);border-radius:var(--radius-lg);padding:var(--space-xl);text-align:center;margin-top:var(--space-2xl);">
      <div style="font-size:32px;margin-bottom:var(--space-sm);">🔒</div>
      <div style="font-weight:700;margin-bottom:var(--space-sm);">Questions about your data?</div>
      <p style="color:var(--text-muted);font-size:14px;margin-bottom:var(--space-md);">We take data privacy seriously. Contact us anytime.</p>
      <a href="mailto:privacy@silverdeals.my" class="btn btn--primary btn--sm">Email Privacy Team</a>
    </div>
  </div>
</section>

<?php include __DIR__ . '/../inc/public_footer.php'; ?>
