<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
$page_title       = 'About SilverDeals MY — Senior Rewards & Deals for Malaysians 50+';
$page_description = 'Learn about SilverDeals MY, Malaysia\'s premier senior membership platform offering exclusive deals, rewards, and a vibrant community for those aged 50 and above.';
include __DIR__ . '/../inc/public_header.php';
?>

<!-- Hero -->
<section style="background:linear-gradient(135deg,var(--orange-primary),var(--orange-secondary));padding:var(--space-2xl) 0;text-align:center;">
  <div class="container" style="max-width:640px;">
    <div style="font-size:56px;margin-bottom:var(--space-md);">🌟</div>
    <h1 style="color:#fff;font-size:clamp(28px,4vw,48px);margin-bottom:var(--space-md);">About SilverDeals MY</h1>
    <p style="color:rgba(255,255,255,.9);font-size:18px;line-height:1.7;">Malaysia's premier rewards and deals platform, built with love for Malaysians aged 50 and above.</p>
  </div>
</section>

<!-- Mission -->
<section class="section">
  <div class="container" style="max-width:780px;">
    <div class="grid grid-2" style="align-items:center;gap:var(--space-2xl);">
      <div>
        <div style="font-size:13px;font-weight:700;letter-spacing:.1em;color:var(--orange-primary);text-transform:uppercase;margin-bottom:var(--space-sm);">OUR MISSION</div>
        <h2 style="margin-bottom:var(--space-lg);">Helping Seniors Live Richer, Save More</h2>
        <p style="color:var(--text-muted);font-size:16px;line-height:1.8;margin-bottom:var(--space-md);">SilverDeals MY was founded with a simple belief: Malaysians who have spent decades building this nation deserve to enjoy their golden years with more savings, more joy, and more connection.</p>
        <p style="color:var(--text-muted);font-size:16px;line-height:1.8;">We partner with trusted merchants across Malaysia to bring exclusive discounts, promotions, and privileges that are specifically tailored for the 50+ community.</p>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-md);">
        <div class="card" style="padding:var(--space-lg);text-align:center;">
          <div style="font-size:36px;margin-bottom:var(--space-sm);">🎯</div>
          <div style="font-weight:700;font-size:14px;">Purpose-Built</div>
          <div style="font-size:13px;color:var(--text-muted);margin-top:4px;">Designed for seniors, not retrofitted</div>
        </div>
        <div class="card" style="padding:var(--space-lg);text-align:center;">
          <div style="font-size:36px;margin-bottom:var(--space-sm);">🔒</div>
          <div style="font-weight:700;font-size:14px;">Trustworthy</div>
          <div style="font-size:13px;color:var(--text-muted);margin-top:4px;">Verified members &amp; vetted partners</div>
        </div>
        <div class="card" style="padding:var(--space-lg);text-align:center;">
          <div style="font-size:36px;margin-bottom:var(--space-sm);">🤝</div>
          <div style="font-weight:700;font-size:14px;">Community</div>
          <div style="font-size:13px;color:var(--text-muted);margin-top:4px;">Connecting seniors across Malaysia</div>
        </div>
        <div class="card" style="padding:var(--space-lg);text-align:center;">
          <div style="font-size:36px;margin-bottom:var(--space-sm);">💎</div>
          <div style="font-weight:700;font-size:14px;">Rewarding</div>
          <div style="font-size:13px;color:var(--text-muted);margin-top:4px;">SilverPoints on every interaction</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- How it works -->
<section class="section" style="background:var(--bg-light);">
  <div class="container" style="max-width:800px;text-align:center;">
    <div style="font-size:13px;font-weight:700;letter-spacing:.1em;color:var(--orange-primary);text-transform:uppercase;margin-bottom:var(--space-sm);">HOW IT WORKS</div>
    <h2 style="margin-bottom:var(--space-2xl);">Simple, Senior-Friendly &amp; Free</h2>
    <div class="grid grid-4">
      <?php $steps = [['🆓','Register Free','Sign up in under 2 minutes with your email or phone number.'],['✅','Get Verified','Upload your IC or passport to unlock full membership benefits.'],['🎁','Browse Deals','Explore hundreds of exclusive deals from trusted Malaysian merchants.'],['🪙','Earn Rewards','Collect SilverPoints on every activity and redeem for more savings.']]; ?>
      <?php foreach ($steps as $i => $s): ?>
        <div style="text-align:center;">
          <div style="width:52px;height:52px;background:var(--orange-primary);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;margin:0 auto var(--space-md);"><?= $i+1 ?></div>
          <div style="font-size:28px;margin-bottom:var(--space-sm);"><?= $s[0] ?></div>
          <div style="font-weight:700;margin-bottom:var(--space-xs);"><?= $s[1] ?></div>
          <div style="font-size:14px;color:var(--text-muted);"><?= $s[2] ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- About SLV Group -->
<section class="section">
  <div class="container" style="max-width:700px;">
    <div style="text-align:center;margin-bottom:var(--space-2xl);">
      <div style="font-size:13px;font-weight:700;letter-spacing:.1em;color:var(--orange-primary);text-transform:uppercase;margin-bottom:var(--space-sm);">OPERATED BY</div>
      <h2>SLV Lifestyle Sdn Bhd</h2>
      <p style="color:var(--text-muted);font-size:16px;max-width:560px;margin:var(--space-md) auto 0;line-height:1.8;">SilverDeals MY is operated by <strong>SLV Lifestyle Sdn Bhd</strong> and powered by <strong>SLV Group Sdn Bhd</strong>, a Malaysian company dedicated to improving the quality of life for senior citizens through technology, community, and commerce.</p>
    </div>

    <div class="grid grid-3" style="gap:var(--space-md);text-align:center;">
      <div class="card" style="padding:var(--space-lg);">
        <div style="font-size:32px;margin-bottom:var(--space-sm);">🇲🇾</div>
        <div style="font-weight:700;">100% Malaysian</div>
        <div style="font-size:13px;color:var(--text-muted);margin-top:4px;">Built and operated in Malaysia, for Malaysians</div>
      </div>
      <div class="card" style="padding:var(--space-lg);">
        <div style="font-size:32px;margin-bottom:var(--space-sm);">🛡️</div>
        <div style="font-weight:700;">Data Protected</div>
        <div style="font-size:13px;color:var(--text-muted);margin-top:4px;">PDPA compliant, your data is safe with us</div>
      </div>
      <div class="card" style="padding:var(--space-lg);">
        <div style="font-size:32px;margin-bottom:var(--space-sm);">📱</div>
        <div style="font-weight:700;">Mobile-First</div>
        <div style="font-size:13px;color:var(--text-muted);margin-top:4px;">Designed to work beautifully on any device</div>
      </div>
    </div>
  </div>
</section>

<!-- Values -->
<section class="section" style="background:var(--bg-light);">
  <div class="container" style="max-width:740px;">
    <div style="text-align:center;margin-bottom:var(--space-2xl);">
      <h2>Our Values</h2>
    </div>
    <div style="display:flex;flex-direction:column;gap:var(--space-lg);">
      <?php $values = [['💛','Dignity & Respect','We believe every senior deserves to be treated with dignity. Our platform is built with large fonts, simple navigation, and clear language.'],['🌱','Accessibility','Financial savings should not require a smartphone degree. SilverDeals MY is designed to be easy for everyone, including first-time smartphone users.'],['🤝','Community First','Beyond deals, we are building a community where Malaysian seniors can share experiences, referrals, and support each other.'],['🔐','Security & Trust','We verify all merchants and members. Your personal information is protected under Malaysia\'s PDPA and never sold to third parties.']]; ?>
      <?php foreach ($values as $v): ?>
        <div style="display:flex;gap:var(--space-lg);align-items:flex-start;">
          <div style="font-size:32px;flex-shrink:0;"><?= $v[0] ?></div>
          <div>
            <div style="font-weight:700;font-size:17px;margin-bottom:var(--space-xs);"><?= $v[1] ?></div>
            <div style="color:var(--text-muted);font-size:15px;line-height:1.7;"><?= $v[2] ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Final CTA -->
<section class="section" style="text-align:center;">
  <div class="container" style="max-width:520px;">
    <h2 style="margin-bottom:var(--space-md);">Ready to Start Saving?</h2>
    <p style="color:var(--text-muted);font-size:16px;margin-bottom:var(--space-xl);">Join thousands of Malaysian seniors enjoying exclusive deals every day.</p>
    <div style="display:flex;justify-content:center;gap:var(--space-md);flex-wrap:wrap;">
      <a href="/public/register.php" class="btn btn--primary btn--lg">Join Free Today</a>
      <a href="/public/deals.php" class="btn btn--muted btn--lg">Browse Deals</a>
    </div>
  </div>
</section>

<?php include __DIR__ . '/../inc/public_footer.php'; ?>
