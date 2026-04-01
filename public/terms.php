<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
$page_title       = 'Terms & Conditions — SilverDeals MY';
$page_description = 'Terms and conditions for using SilverDeals MY — the senior membership, rewards, and deals platform operated by SLV Lifestyle Sdn Bhd.';
$updated = 'April 2025';
include __DIR__ . '/../inc/public_header.php';
?>

<section style="background:var(--bg-light);padding:var(--space-xl) 0;border-bottom:1px solid var(--border-color);">
  <div class="container">
    <div style="font-size:13px;color:var(--text-muted);margin-bottom:var(--space-sm);">Last updated: <?= $updated ?></div>
    <h1>Terms &amp; Conditions</h1>
    <p style="color:var(--text-muted);font-size:16px;">Please read these terms carefully before using SilverDeals MY. By registering or using our platform, you agree to be bound by these terms.</p>
  </div>
</section>

<section class="section">
  <div class="container" style="max-width:760px;">

    <?php
    $terms = [
        ['Agreement & Acceptance', '<p>These Terms &amp; Conditions ("Terms") govern your use of the SilverDeals MY platform ("Platform"), operated by <strong>SLV Lifestyle Sdn Bhd</strong> ("Company", "we", "us"). By accessing or using the Platform, you agree to these Terms. If you do not agree, please do not use the Platform.</p>'],

        ['Eligibility', '<p>To register as a SilverDeals MY member, you must:</p><ul>
<li>Be a Malaysian citizen or permanent resident aged <strong>50 years and above</strong></li>
<li>Provide accurate and truthful personal information</li>
<li>Have a valid email address and/or phone number</li>
<li>Not be previously banned or suspended from the Platform</li>
</ul><p>Merchants must operate a legitimate business registered in Malaysia.</p>'],

        ['Member Accounts', '<ul>
<li>You are responsible for maintaining the security of your account password</li>
<li>You must not share your account credentials with any other person</li>
<li>You must notify us immediately of any unauthorised access to your account</li>
<li>We reserve the right to suspend or terminate accounts that violate these Terms</li>
<li>One account per person is permitted. Duplicate accounts will be removed</li>
</ul>'],

        ['Senior Verification', '<p>To access full membership benefits, you must complete senior verification by submitting a valid Malaysian identity document (MyKad or passport). We verify:</p><ul>
<li>That you are aged 50 or above</li>
<li>That the document matches your registration details</li>
</ul><p>Submission of fraudulent documents is a violation of these Terms and may be reported to relevant authorities.</p>'],

        ['SilverPoints Programme', '<ul>
<li>SilverPoints are non-monetary digital rewards with no cash value</li>
<li>Points are earned by completing actions on the Platform as described in the membership card page</li>
<li>Points may expire as communicated in the Platform; check your wallet for expiry dates</li>
<li>Points cannot be transferred between accounts</li>
<li>We reserve the right to adjust, expire, or cancel points at our discretion in cases of fraud or account termination</li>
<li>Points balances are approximate and subject to reconciliation</li>
</ul>'],

        ['Deals, Vouchers & Redemptions', '<ul>
<li>Deals are provided by third-party merchants and subject to merchant terms</li>
<li>Vouchers are valid only during the stated validity period</li>
<li>Vouchers are single-use and cannot be combined with other promotions unless stated</li>
<li>We make no warranty regarding merchant service quality or deal availability</li>
<li>Once a voucher is redeemed (marked as used), it cannot be refunded or re-issued</li>
<li>We reserve the right to cancel or modify deals where merchant arrangements change</li>
</ul>'],

        ['Referral Programme', '<ul>
<li>Referral rewards are subject to the referred friend completing senior verification</li>
<li>Self-referrals are strictly prohibited</li>
<li>Referral reward amounts may be changed at our discretion with notice to members</li>
<li>Fraudulent referral activity (fake accounts, bulk signups) will result in forfeiture of all earned referral points and account suspension</li>
</ul>'],

        ['Merchant Partners', '<ul>
<li>Merchant applications are subject to review and approval by SilverDeals MY</li>
<li>Merchants must honour advertised deals as published on the Platform</li>
<li>Merchants are responsible for maintaining accurate deal information</li>
<li>Commission rates are agreed at onboarding and deducted from redemption revenue</li>
<li>Payout requests are processed within 5 business days of approval</li>
<li>Merchants must comply with all Malaysian consumer protection laws</li>
</ul>'],

        ['Prohibited Conduct', '<p>You must not use the Platform to:</p><ul>
<li>Submit false, misleading, or fraudulent information</li>
<li>Impersonate another person or entity</li>
<li>Attempt to gain unauthorised access to other accounts or our systems</li>
<li>Use automated tools, bots, or scrapers</li>
<li>Circumvent security measures or rate limits</li>
<li>Engage in any activity that is unlawful under Malaysian law</li>
<li>Harass, intimidate, or harm other members or merchants</li>
</ul>'],

        ['Intellectual Property', 'All content on the SilverDeals MY platform, including logos, design, text, and code, is the intellectual property of SLV Lifestyle Sdn Bhd or its licensors. You may not reproduce, modify, or redistribute any content without prior written consent.'],

        ['Limitation of Liability', '<p>To the maximum extent permitted by Malaysian law:</p><ul>
<li>We provide the Platform "as is" without warranties of any kind</li>
<li>We are not liable for deals or services provided by third-party merchants</li>
<li>We are not responsible for any indirect, incidental, or consequential loss</li>
<li>Our total liability to you shall not exceed RM 100 or the amount you paid to us in the preceding 12 months, whichever is greater</li>
</ul>'],

        ['Termination', 'We may suspend or terminate your access to the Platform at any time if you breach these Terms. You may close your account at any time by contacting us. Upon termination, any unused points will be forfeited.'],

        ['Changes to Terms', 'We may update these Terms from time to time. We will provide at least 14 days notice of material changes via email or in-app notification. Continued use of the Platform after notice constitutes acceptance.'],

        ['Governing Law', 'These Terms are governed by the laws of Malaysia. Any disputes shall be submitted to the exclusive jurisdiction of the Malaysian courts.'],

        ['Contact', 'For questions about these Terms, email us at <a href="mailto:legal@silverdeals.my" style="color:var(--orange-primary);">legal@silverdeals.my</a> or use our <a href="/public/contact.php" style="color:var(--orange-primary);">contact form</a>.'],
    ];
    ?>

    <?php foreach ($terms as $i => [$title, $body]): ?>
      <div style="margin-bottom:var(--space-2xl);padding-bottom:var(--space-xl);border-bottom:1px solid var(--border-color);">
        <h3 style="font-size:18px;margin-bottom:var(--space-md);display:flex;gap:var(--space-sm);align-items:center;">
          <span style="width:26px;height:26px;background:var(--orange-primary);color:#fff;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0;"><?= $i+1 ?></span>
          <?= $title ?>
        </h3>
        <div style="color:var(--text-muted);font-size:15px;line-height:1.9;">
          <?php if (str_contains($body, '<')): echo $body; else: ?><p><?= $body ?></p><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <div style="padding:var(--space-xl);background:var(--bg-light);border-radius:var(--radius-lg);text-align:center;">
      <p style="color:var(--text-muted);font-size:14px;">By using SilverDeals MY, you acknowledge that you have read, understood, and agreed to these Terms &amp; Conditions.</p>
      <div style="display:flex;justify-content:center;gap:var(--space-md);margin-top:var(--space-md);flex-wrap:wrap;">
        <a href="/public/privacy.php" class="btn btn--muted btn--sm">Privacy Policy</a>
        <a href="/public/contact.php" class="btn btn--muted btn--sm">Contact Us</a>
        <a href="/public/register.php" class="btn btn--primary btn--sm">Join Free</a>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/../inc/public_footer.php'; ?>
