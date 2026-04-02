<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';

$page_title       = 'How It Works — SilverDeals MY';
$page_description = 'Learn how SilverDeals MY works — register free, verify your senior status, get your digital membership card, and enjoy exclusive deals with SilverPoints rewards.';

include __DIR__ . '/../inc/public_header.php';
?>

<!-- ─── Hero ──────────────────────────────────────────────────────────────── -->
<section style="background:linear-gradient(135deg,var(--orange-primary),var(--orange-secondary));padding:var(--space-2xl) 0;text-align:center;">
  <div class="container" style="max-width:640px;">
    <div style="font-size:13px;font-weight:700;letter-spacing:.12em;color:rgba(255,255,255,.85);text-transform:uppercase;margin-bottom:var(--space-sm);">Simple &amp; Easy</div>
    <h1 style="color:#fff;font-size:clamp(28px,4vw,50px);margin-bottom:var(--space-md);">How SilverDeals MY Works</h1>
    <p style="color:rgba(255,255,255,.9);font-size:18px;line-height:1.7;margin-bottom:var(--space-xl);">Get started in minutes. Save on every visit. Earn rewards for simply living well.</p>
    <a href="/public/register.php" class="btn btn--lg" style="background:#fff;color:var(--orange-primary);font-weight:700;border:none;">Join Free Today →</a>
  </div>
</section>

<!-- ─── 4 Steps overview ──────────────────────────────────────────────────── -->
<section class="section">
  <div class="container" style="max-width:860px;">
    <div style="text-align:center;margin-bottom:var(--space-2xl);">
      <h2>Four Simple Steps</h2>
      <p style="color:var(--text-muted);font-size:16px;">No complicated setup. No hidden fees. Just savings.</p>
    </div>

    <?php
    $steps = [
      [
        'num'   => '1',
        'icon'  => '🖊️',
        'title' => 'Register Free',
        'color' => 'var(--orange-primary)',
        'short' => 'Create your account in under 2 minutes.',
        'details' => [
          'Sign up with your email address or Malaysian phone number',
          'Choose a secure password — we use strong bcrypt encryption',
          'No credit card or payment required — membership is completely free',
          'Receive a welcome email with your account details',
          'Earn <strong>'.format_points(POINTS_WELCOME_BONUS).' SilverPoints</strong> just for joining',
        ],
      ],
      [
        'num'   => '2',
        'icon'  => '✅',
        'title' => 'Verify Your Senior Status',
        'color' => '#10B981',
        'short' => 'A quick one-time verification to unlock full benefits.',
        'details' => [
          'Upload a photo of your MyKad (front) or passport',
          'Optionally add a selfie for faster approval',
          'Our team reviews submissions within 1–2 business days',
          'Verification confirms you are aged 50 or above — required for member-exclusive deals',
          'Earn <strong>'.format_points(POINTS_VERIFICATION_BONUS).' SilverPoints</strong> once verified',
          'Your identity document is stored securely and never shared with merchants',
        ],
      ],
      [
        'num'   => '3',
        'icon'  => '🃏',
        'title' => 'Get Your Digital Membership Card',
        'color' => '#3B82F6',
        'short' => 'Your personal QR card is ready the moment you\'re verified.',
        'details' => [
          'Your digital membership card is generated automatically on approval',
          'Features a unique HMAC-signed QR code that merchants can scan',
          'Displays your membership tier (Free / Silver / Gold), points balance, and member number',
          'Always available on your phone — no plastic card needed',
          'Print or download your card from the Member Card page',
          'Upgrade your tier at any time for even more exclusive benefits',
        ],
      ],
      [
        'num'   => '4',
        'icon'  => '🎁',
        'title' => 'Enjoy Deals &amp; Earn Rewards',
        'color' => '#8B5CF6',
        'short' => 'Browse hundreds of deals and earn points on everything.',
        'details' => [
          'Browse deals across dining, healthcare, travel, retail, and more',
          'Claim a voucher code with one tap — show it to the merchant',
          'Earn <strong>SilverPoints</strong> on referrals, first redemptions, and special promotions',
          'Refer a friend and earn <strong>'.format_points(POINTS_REFERRAL_BONUS).' SilverPoints</strong> when they verify',
          'Use points to unlock points-gated exclusive deals',
          'Your vouchers are stored safely in My Vouchers — never lose a code',
        ],
      ],
    ];
    ?>

    <div style="display:flex;flex-direction:column;gap:var(--space-2xl);">
      <?php foreach ($steps as $i => $s): ?>
        <div style="display:flex;gap:var(--space-2xl);align-items:flex-start;<?= $i % 2 === 1 ? 'flex-direction:row-reverse;' : '' ?>flex-wrap:wrap;">

          <!-- Step number + icon block -->
          <div style="flex-shrink:0;text-align:center;width:160px;">
            <div style="width:80px;height:80px;background:<?= $s['color'] ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:36px;margin:0 auto var(--space-md);box-shadow:0 8px 24px <?= $s['color'] ?>40;">
              <?= $s['icon'] ?>
            </div>
            <div style="font-size:48px;font-weight:900;color:<?= $s['color'] ?>;opacity:.15;line-height:1;margin-top:-8px;"><?= $s['num'] ?></div>
          </div>

          <!-- Content -->
          <div style="flex:1;min-width:240px;">
            <div style="display:inline-block;background:<?= $s['color'] ?>18;color:<?= $s['color'] ?>;font-size:12px;font-weight:700;padding:3px 12px;border-radius:20px;letter-spacing:.06em;text-transform:uppercase;margin-bottom:var(--space-sm);">Step <?= $s['num'] ?></div>
            <h3 style="font-size:clamp(20px,3vw,26px);margin-bottom:var(--space-sm);"><?= $s['title'] ?></h3>
            <p style="color:var(--text-muted);font-size:16px;margin-bottom:var(--space-lg);"><?= $s['short'] ?></p>
            <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:var(--space-sm);">
              <?php foreach ($s['details'] as $detail): ?>
                <li style="display:flex;align-items:flex-start;gap:var(--space-sm);font-size:15px;color:var(--text-dark);line-height:1.6;">
                  <span style="color:<?= $s['color'] ?>;font-size:18px;flex-shrink:0;line-height:1.4;">✓</span>
                  <span><?= $detail ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>

        <?php if ($i < count($steps) - 1): ?>
          <div style="text-align:center;font-size:28px;color:var(--border-color);">↓</div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ─── SilverPoints explained ────────────────────────────────────────────── -->
<section class="section" style="background:var(--bg-light);">
  <div class="container" style="max-width:820px;">
    <div style="text-align:center;margin-bottom:var(--space-2xl);">
      <div style="font-size:13px;font-weight:700;letter-spacing:.12em;color:var(--orange-primary);text-transform:uppercase;margin-bottom:var(--space-sm);">REWARDS</div>
      <h2>Understanding SilverPoints</h2>
      <p style="color:var(--text-muted);font-size:16px;">Every action you take earns you points you can use for even better deals.</p>
    </div>

    <div class="grid grid-3" style="margin-bottom:var(--space-xl);">
      <?php
      $earning = [
        ['🎉', 'Welcome Bonus',       '+'.format_points(POINTS_WELCOME_BONUS),  'Awarded on registration'],
        ['✅', 'Verification Bonus',   '+'.format_points(POINTS_VERIFICATION_BONUS), 'Complete your senior verification'],
        ['🤝', 'Refer a Friend',       '+'.format_points(POINTS_REFERRAL_BONUS), 'Per verified referral'],
        ['🎁', 'Friend Joins Bonus',   '+'.format_points(POINTS_REFERRAL_BONUS / 2), 'Your friend also gets rewarded'],
        ['🛍️', 'First Redemption',    '+'.format_points(POINTS_FIRST_REDEMPTION_BONUS), 'Claim your first deal voucher'],
        ['⭐', 'Special Promotions',   'Varies',                                 'Look out for bonus point events'],
      ];
      foreach ($earning as [$icon, $title, $pts, $note]):
      ?>
        <div class="card" style="padding:var(--space-lg);text-align:center;">
          <div style="font-size:32px;margin-bottom:var(--space-sm);"><?= $icon ?></div>
          <div style="font-weight:700;margin-bottom:4px;font-size:15px;"><?= $title ?></div>
          <div style="font-size:22px;font-weight:800;color:var(--orange-primary);margin-bottom:4px;"><?= $pts ?></div>
          <div style="font-size:13px;color:var(--text-muted);"><?= $note ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="card" style="padding:var(--space-xl);background:linear-gradient(135deg,var(--orange-primary),var(--orange-secondary));border:none;text-align:center;">
      <div style="font-size:32px;margin-bottom:var(--space-md);">🪙</div>
      <h3 style="color:#fff;margin-bottom:var(--space-sm);">How to Use Your Points</h3>
      <p style="color:rgba(255,255,255,.9);font-size:15px;max-width:480px;margin:0 auto;">SilverPoints unlock special <strong>points-gated deals</strong> — exclusive offers only available to members with enough points in their wallet. The more active you are, the better deals you access.</p>
    </div>
  </div>
</section>

<!-- ─── Membership tiers ──────────────────────────────────────────────────── -->
<section class="section">
  <div class="container" style="max-width:820px;">
    <div style="text-align:center;margin-bottom:var(--space-2xl);">
      <div style="font-size:13px;font-weight:700;letter-spacing:.12em;color:var(--orange-primary);text-transform:uppercase;margin-bottom:var(--space-sm);">MEMBERSHIP TIERS</div>
      <h2>Choose Your Membership</h2>
      <p style="color:var(--text-muted);font-size:16px;">Start free. Upgrade anytime for premium access.</p>
    </div>

    <div class="grid grid-3" style="align-items:start;">
      <?php
      $tiers = [
        ['🆓', 'Free',   '#6B7280', false, ['Access to public deals','SilverPoints wallet','Referral programme','Digital profile']],
        ['🥈', 'Silver', 'var(--orange-primary)', true, ['Everything in Free','Senior-verified badge','Digital QR membership card','Members-only exclusive deals','Priority customer support']],
        ['🥇', 'Gold',   '#D97706', false, ['Everything in Silver','Gold badge on profile','Early access to new deals','Higher points multipliers','Dedicated account manager']],
      ];
      foreach ($tiers as [$icon, $name, $color, $featured, $perks]):
      ?>
        <div class="card" style="padding:var(--space-xl);<?= $featured ? 'border:2px solid var(--orange-primary);position:relative;' : '' ?>">
          <?php if ($featured): ?>
            <div style="position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:var(--orange-primary);color:#fff;font-size:11px;font-weight:700;padding:3px 14px;border-radius:20px;letter-spacing:.06em;white-space:nowrap;">MOST POPULAR</div>
          <?php endif; ?>
          <div style="text-align:center;margin-bottom:var(--space-lg);">
            <div style="font-size:40px;margin-bottom:var(--space-sm);"><?= $icon ?></div>
            <div style="font-size:20px;font-weight:800;color:<?= $color ?>;"><?= $name ?></div>
          </div>
          <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:var(--space-sm);">
            <?php foreach ($perks as $perk): ?>
              <li style="display:flex;gap:var(--space-sm);font-size:14px;line-height:1.5;">
                <span style="color:<?= $color ?>;flex-shrink:0;">✓</span>
                <span><?= $perk ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ─── FAQ ───────────────────────────────────────────────────────────────── -->
<section class="section" style="background:var(--bg-light);">
  <div class="container" style="max-width:680px;">
    <div style="text-align:center;margin-bottom:var(--space-2xl);">
      <div style="font-size:13px;font-weight:700;letter-spacing:.12em;color:var(--orange-primary);text-transform:uppercase;margin-bottom:var(--space-sm);">FAQ</div>
      <h2>Common Questions</h2>
    </div>

    <?php
    $faqs = [
      ['Is SilverDeals MY really free?',
       'Yes — the Free tier is permanently free. You can browse public deals, earn SilverPoints, and participate in our referral programme at no cost. Silver membership is unlocked by completing senior verification, also free.'],
      ['Who is eligible to join?',
       'SilverDeals MY is for Malaysian citizens and permanent residents aged 50 and above. You will need a valid MyKad or passport to complete verification.'],
      ['How long does verification take?',
       'Our team typically reviews verification submissions within 1–2 business days. You will receive a notification once your account is approved.'],
      ['Are SilverPoints cash?',
       'No. SilverPoints are non-monetary digital rewards with no cash value. They are used to unlock special points-gated deals on the platform.'],
      ['How do I use a voucher at a merchant?',
       'After claiming a deal, go to My Vouchers and open your voucher. Show the QR code or the voucher code to the merchant staff. They will scan or enter the code to redeem it.'],
      ['Can I refer someone younger than 50?',
       'You can share your referral link with anyone, but referral points are only awarded once your friend completes senior verification (confirming they are 50+).'],
      ['Is my MyKad information safe?',
       'Absolutely. Your identity documents are encrypted, stored securely, and never shared with merchants or third parties. We comply with Malaysia\'s Personal Data Protection Act 2010 (PDPA).'],
      ['How do I become a merchant partner?',
       'Visit our <a href="/public/join-merchant.php" style="color:var(--orange-primary);">Become a Partner</a> page to submit your merchant application. Our team will review and get back to you within 2 business days.'],
    ];
    ?>

    <div style="display:flex;flex-direction:column;gap:var(--space-sm);">
      <?php foreach ($faqs as $i => [$q, $a]): ?>
        <details class="faq-item" style="background:#fff;border:1px solid var(--border-color);border-radius:var(--radius-md);overflow:hidden;">
          <summary style="padding:var(--space-lg);font-weight:700;font-size:16px;cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center;gap:var(--space-md);">
            <span><?= e($q) ?></span>
            <span class="faq-chevron" style="font-size:20px;color:var(--orange-primary);flex-shrink:0;transition:transform .2s;">+</span>
          </summary>
          <div style="padding:0 var(--space-lg) var(--space-lg);color:var(--text-muted);font-size:15px;line-height:1.8;"><?= $a ?></div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ─── Final CTA ─────────────────────────────────────────────────────────── -->
<section class="section" style="text-align:center;">
  <div class="container" style="max-width:560px;">
    <div style="font-size:56px;margin-bottom:var(--space-md);">🌟</div>
    <h2 style="margin-bottom:var(--space-md);">Ready to Start Saving?</h2>
    <p style="color:var(--text-muted);font-size:17px;margin-bottom:var(--space-xl);">Join thousands of Malaysian seniors enjoying exclusive deals, earning rewards, and living better every day.</p>
    <div style="display:flex;justify-content:center;gap:var(--space-md);flex-wrap:wrap;">
      <a href="/public/register.php" class="btn btn--primary btn--lg">Join Free — Takes 2 Minutes</a>
      <a href="/public/deals.php" class="btn btn--muted btn--lg">Browse Deals First</a>
    </div>
    <p style="font-size:13px;color:var(--text-muted);margin-top:var(--space-lg);">
      Already a member? <a href="/public/login.php" style="color:var(--orange-primary);font-weight:600;">Log in here</a>
    </p>
  </div>
</section>

<script>
// Toggle + / − on FAQ chevrons
document.querySelectorAll('.faq-item').forEach(el => {
    el.addEventListener('toggle', () => {
        const chevron = el.querySelector('.faq-chevron');
        if (chevron) chevron.textContent = el.open ? '−' : '+';
    });
});
</script>

<?php include __DIR__ . '/../inc/public_footer.php'; ?>
