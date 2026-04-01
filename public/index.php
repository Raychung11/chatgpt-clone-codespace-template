<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';

$page_title = 'SilverDeals MY — Senior Membership &amp; Rewards for Malaysians 50+';
$meta_desc  = 'Join SilverDeals MY — Malaysia\'s premier senior membership, exclusive deals, referral rewards, and community marketplace for Malaysians aged 50+. Free to join!';

// Fetch featured deals (top 6)
$featured_deals = [];
try {
    $stmt = db()->prepare("
        SELECT d.id, d.title, d.slug, d.short_desc, d.original_price, d.deal_price, d.discount_pct,
               m.business_name AS merchant_name, m.slug AS merchant_slug,
               dc.name AS category, dc.icon AS category_icon,
               di.image_path AS image
        FROM deals d
        JOIN merchants m  ON m.id = d.merchant_id
        LEFT JOIN deal_categories dc ON dc.id = d.category_id
        LEFT JOIN deal_images di     ON di.deal_id = d.id AND di.is_primary = 1
        WHERE d.status = 'active' AND d.is_featured = 1
          AND (d.valid_until IS NULL OR d.valid_until >= CURDATE())
        ORDER BY d.updated_at DESC
        LIMIT 6
    ");
    $stmt->execute();
    $featured_deals = $stmt->fetchAll();
} catch (PDOException) { /* silent on fresh DB */ }

// Fetch categories
$categories = [];
try {
    $stmt = db()->query("SELECT id, name, slug, icon FROM deal_categories WHERE is_active=1 ORDER BY sort_order");
    $categories = $stmt->fetchAll();
} catch (PDOException) {}

// Fetch testimonials
$testimonials = [];
try {
    $stmt = db()->query("SELECT * FROM testimonials WHERE is_active=1 ORDER BY sort_order LIMIT 3");
    $testimonials = $stmt->fetchAll();
} catch (PDOException) {}

include __DIR__ . '/../inc/public_header.php';
?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     HERO
════════════════════════════════════════════════════════════════════════════ -->
<section class="hero">
  <div class="container">
    <div style="display:grid;grid-template-columns:1fr auto;gap:var(--space-2xl);align-items:center;">
      <div class="hero__content">
        <div class="hero__tag">🏅 Malaysia's #1 Senior Lifestyle Platform</div>
        <h1 class="hero__title">Enjoy Life More.<br>Save More. Earn More.</h1>
        <p class="hero__subtitle">
          Exclusive deals, premium membership, and real rewards — designed for Malaysians aged 50+.
          Join thousands of seniors living better every day with SilverDeals MY.
        </p>
        <div class="hero__actions">
          <a href="/public/register.php" class="btn btn--ghost btn--lg">
            🎉 Join Free Today
          </a>
          <a href="/public/deals.php" class="btn btn--ghost" style="background:rgba(255,255,255,.15);">
            Browse Deals →
          </a>
        </div>
        <div style="margin-top:var(--space-xl);display:flex;gap:var(--space-xl);flex-wrap:wrap;">
          <div>
            <div style="font-size:28px;font-weight:800;color:#fff;">10,000+</div>
            <div style="font-size:14px;opacity:.8;">Senior Members</div>
          </div>
          <div>
            <div style="font-size:28px;font-weight:800;color:#fff;">500+</div>
            <div style="font-size:14px;opacity:.8;">Merchant Partners</div>
          </div>
          <div>
            <div style="font-size:28px;font-weight:800;color:#fff;">50+</div>
            <div style="font-size:14px;opacity:.8;">Community Partners</div>
          </div>
        </div>
      </div>

      <!-- Hero illustration card (CSS-only on mobile hidden) -->
      <div style="display:none;" class="hero-card-preview">
        <!-- Shown via JS on desktop -->
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     TRUST BAR
════════════════════════════════════════════════════════════════════════════ -->
<section style="background:var(--white);border-bottom:1px solid var(--border-light);padding:var(--space-lg) 0;">
  <div class="container">
    <div style="display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:var(--space-2xl);opacity:.7;font-size:15px;font-weight:600;color:var(--text-muted);">
      <span>🏢 SSM Registered</span>
      <span>🔒 PDPA Compliant</span>
      <span>✅ Senior Verified Deals</span>
      <span>🇲🇾 Made for Malaysians</span>
      <span>📱 Mobile-Friendly</span>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     HOW IT WORKS
════════════════════════════════════════════════════════════════════════════ -->
<section class="section section--bg">
  <div class="container">
    <div class="section-header">
      <div class="section-header__tag">Simple &amp; Easy</div>
      <h2 class="section-header__title">How SilverDeals MY Works</h2>
      <p class="section-header__subtitle">Get started in minutes. Save on every visit.</p>
    </div>

    <div class="grid grid-4">
      <?php
      $steps = [
        ['🖊️', '1', 'Register Free',         'Sign up with your phone number or email. It takes less than 2 minutes.'],
        ['✅', '2', 'Verify Your Profile',    'Complete your senior verification to unlock exclusive deals and your digital membership card.'],
        ['🃏', '3', 'Get Your Digital Card',  'Receive your QR membership card instantly. Use it at any partner merchant.'],
        ['🎁', '4', 'Enjoy Deals & Earn Points','Redeem deals, refer friends, and earn SilverPoints redeemable for even more rewards.'],
      ];
      foreach ($steps as [$icon, $num, $title, $desc]):
      ?>
        <div class="card step-card">
          <div class="step-number"><?= $num ?></div>
          <div style="font-size:36px;margin-bottom:var(--space-md);"><?= $icon ?></div>
          <h4><?= e($title) ?></h4>
          <p class="text-muted" style="font-size:16px;margin-top:var(--space-sm);"><?= e($desc) ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="text-center" style="margin-top:var(--space-2xl);">
      <a href="/public/how-it-works.php" class="btn btn--secondary">Learn More →</a>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     DEAL CATEGORIES
════════════════════════════════════════════════════════════════════════════ -->
<?php if (!empty($categories)): ?>
<section class="section">
  <div class="container">
    <div class="section-header">
      <div class="section-header__tag">Curated For Seniors</div>
      <h2 class="section-header__title">Deals By Category</h2>
    </div>
    <div class="pill-list" style="justify-content:center;">
      <a href="/public/deals.php" class="pill active">All Deals</a>
      <?php foreach ($categories as $cat): ?>
        <a href="/public/deals.php?cat=<?= urlencode($cat['slug']) ?>" class="pill">
          <?= e($cat['icon'] ?? '') ?> <?= e($cat['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     FEATURED DEALS
════════════════════════════════════════════════════════════════════════════ -->
<section class="section section--grey" style="<?= empty($categories) ? 'padding-top:var(--space-3xl);' : '' ?>">
  <div class="container">
    <div class="section-header">
      <div class="section-header__tag">Hand-Picked For You</div>
      <h2 class="section-header__title">Featured Deals</h2>
      <p class="section-header__subtitle">Trusted merchants. Real savings. Verified for seniors.</p>
    </div>

    <?php if (!empty($featured_deals)): ?>
      <div class="grid grid-3">
        <?php foreach ($featured_deals as $deal): ?>
          <div class="card deal-card">
            <?php if ($deal['image']): ?>
              <img src="<?= e($deal['image']) ?>" alt="<?= e($deal['title']) ?>" class="card__image">
            <?php else: ?>
              <div class="card__image" style="display:flex;align-items:center;justify-content:center;background:var(--orange-bg);font-size:48px;">
                <?= e($deal['category_icon'] ?? '🎁') ?>
              </div>
            <?php endif; ?>
            <div class="badge badge--orange" style="position:absolute;top:12px;left:12px;">
              <?php if ($deal['discount_pct']): ?>
                <?= (int)$deal['discount_pct'] ?>% OFF
              <?php else: ?>
                Featured
              <?php endif; ?>
            </div>
            <div class="card__body">
              <span class="badge badge--muted" style="margin-bottom:var(--space-sm);">
                <?= e($deal['category_icon'] ?? '') ?> <?= e($deal['category'] ?? 'Deal') ?>
              </span>
              <h4 class="card__title"><?= e($deal['title']) ?></h4>
              <p class="card__text"><?= e($deal['short_desc'] ?? '') ?></p>
              <div class="deal-card__price">
                <?php if ($deal['deal_price']): ?>
                  <span class="price-new"><?= format_myr((float)$deal['deal_price']) ?></span>
                  <?php if ($deal['original_price']): ?>
                    <span class="price-old"><?= format_myr((float)$deal['original_price']) ?></span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="price-new text-orange">Free / Members Only</span>
                <?php endif; ?>
              </div>
              <div style="display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:14px;color:var(--text-muted);">by <?= e($deal['merchant_name']) ?></span>
                <a href="/public/deal.php?slug=<?= urlencode($deal['slug']) ?>" class="btn btn--primary btn--sm">Get Deal</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-state__icon">🎁</div>
        <h3 class="empty-state__title">Deals Coming Soon</h3>
        <p class="empty-state__text">We're onboarding amazing merchants. Check back soon!</p>
        <a href="/public/register.php" class="btn btn--primary">Join Free — Be First to Know</a>
      </div>
    <?php endif; ?>

    <div class="text-center" style="margin-top:var(--space-2xl);">
      <a href="/public/deals.php" class="btn btn--primary btn--lg">View All Deals →</a>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     MEMBERSHIP TIERS
════════════════════════════════════════════════════════════════════════════ -->
<section class="section">
  <div class="container">
    <div class="section-header">
      <div class="section-header__tag">Choose Your Plan</div>
      <h2 class="section-header__title">Membership Tiers</h2>
      <p class="section-header__subtitle">Start free. Upgrade for more exclusive benefits.</p>
    </div>

    <div class="grid grid-3">
      <?php
      $plans = [
        [
          'name'    => 'Free',
          'price'   => 'Free Forever',
          'color'   => 'var(--text-muted)',
          'badge'   => '',
          'icon'    => '🆓',
          'perks'   => ['Digital membership card','Browse all deals','Refer friends & earn points','Basic member profile','Community access'],
          'cta'     => 'Join Free',
          'url'     => '/public/register.php',
          'style'   => '',
        ],
        [
          'name'    => 'Silver',
          'price'   => 'RM 49 / year',
          'color'   => 'var(--orange-primary)',
          'badge'   => 'Most Popular',
          'icon'    => '🥈',
          'perks'   => ['Everything in Free','500 bonus SilverPoints on upgrade','Priority deal access','Exclusive Silver-only deals','Monthly rewards voucher','Member hotline support'],
          'cta'     => 'Get Silver',
          'url'     => '/public/register.php?plan=silver',
          'style'   => 'border:2px solid var(--orange-primary);transform:scale(1.03);',
        ],
        [
          'name'    => 'Gold',
          'price'   => 'RM 99 / year',
          'color'   => '#B45309',
          'badge'   => 'Best Value',
          'icon'    => '🥇',
          'perks'   => ['Everything in Silver','1,500 bonus SilverPoints on upgrade','VIP deal access','Dedicated relationship manager','Exclusive Gold events','Family add-on slots'],
          'cta'     => 'Get Gold',
          'url'     => '/public/register.php?plan=gold',
          'style'   => '',
        ],
      ];
      foreach ($plans as $plan):
      ?>
        <div class="card" style="padding:var(--space-xl);text-align:center;<?= $plan['style'] ?>">
          <?php if ($plan['badge']): ?>
            <div class="badge badge--premium" style="margin-bottom:var(--space-md);"><?= e($plan['badge']) ?></div>
          <?php endif; ?>
          <div style="font-size:44px;margin-bottom:var(--space-sm);"><?= $plan['icon'] ?></div>
          <h3 style="color:<?= $plan['color'] ?>;margin-bottom:var(--space-sm);"><?= e($plan['name']) ?></h3>
          <div style="font-size:26px;font-weight:800;margin-bottom:var(--space-lg);"><?= e($plan['price']) ?></div>
          <ul style="text-align:left;margin-bottom:var(--space-xl);display:flex;flex-direction:column;gap:10px;">
            <?php foreach ($plan['perks'] as $perk): ?>
              <li style="display:flex;align-items:flex-start;gap:8px;font-size:15px;">
                <span style="color:var(--success);flex-shrink:0;margin-top:2px;">✓</span>
                <?= e($perk) ?>
              </li>
            <?php endforeach; ?>
          </ul>
          <a href="<?= $plan['url'] ?>" class="btn btn--<?= $plan['badge'] ? 'primary' : 'secondary' ?> btn--full">
            <?= e($plan['cta']) ?>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     FOR WHO (3 audiences)
════════════════════════════════════════════════════════════════════════════ -->
<section class="section section--bg">
  <div class="container">
    <div class="section-header">
      <div class="section-header__tag">The SilverDeals Ecosystem</div>
      <h2 class="section-header__title">Built for Everyone in the Community</h2>
    </div>

    <div class="grid grid-3">
      <?php
      $audiences = [
        ['👴', 'Senior Members',       '#FF6B00', 'Malaysians aged 50+',        'Enjoy curated deals on health, dining, travel and lifestyle. Earn points, get your digital membership card, and live life fully.', '/public/register.php', 'Join as Member'],
        ['🏪', 'Merchants & Businesses','#1D4ED8', 'Serving the silver economy', 'Reach thousands of senior customers. List your deals, manage redemptions, and grow your business with a community that values loyalty.', '/public/join-merchant.php', 'Partner with Us'],
        ['🏢', 'Community Partners',    '#065F46', 'JMBs, Condos, Koperasi',     'Bring SilverDeals to your residents. Track local engagement, earn community commissions, and provide real value to your community.', '/public/join-community.php', 'Become a Partner'],
      ];
      foreach ($audiences as [$icon, $title, $color, $subtitle, $desc, $url, $cta]):
      ?>
        <div class="card" style="padding:var(--space-xl);">
          <div style="font-size:48px;margin-bottom:var(--space-md);"><?= $icon ?></div>
          <span style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:<?= $color ?>;"><?= e($subtitle) ?></span>
          <h3 style="margin:var(--space-sm) 0 var(--space-md);"><?= e($title) ?></h3>
          <p style="color:var(--text-muted);font-size:16px;margin-bottom:var(--space-lg);"><?= e($desc) ?></p>
          <a href="<?= $url ?>" class="btn btn--secondary btn--sm"><?= e($cta) ?></a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     TESTIMONIALS
════════════════════════════════════════════════════════════════════════════ -->
<?php if (!empty($testimonials)): ?>
<section class="section">
  <div class="container">
    <div class="section-header">
      <div class="section-header__tag">Member Stories</div>
      <h2 class="section-header__title">What Our Members Say</h2>
    </div>
    <div class="grid grid-3">
      <?php foreach ($testimonials as $t): ?>
        <div class="card" style="padding:var(--space-xl);">
          <div style="font-size:22px;color:var(--orange-primary);margin-bottom:var(--space-md);">
            <?= str_repeat('★', (int)($t['rating'] ?? 5)) ?>
          </div>
          <p style="font-size:17px;line-height:1.7;color:var(--text-dark);margin-bottom:var(--space-lg);">
            "<?= e($t['quote']) ?>"
          </p>
          <div style="display:flex;align-items:center;gap:var(--space-md);">
            <div style="width:46px;height:46px;border-radius:50%;background:var(--orange-bg);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:var(--orange-primary);flex-shrink:0;">
              <?= mb_strtoupper(mb_substr($t['name'], 0, 1)) ?>
            </div>
            <div>
              <div style="font-weight:700;font-size:16px;"><?= e($t['name']) ?></div>
              <div style="font-size:14px;color:var(--text-muted);"><?= e($t['location'] ?? '') ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     REFERRAL CTA BANNER
════════════════════════════════════════════════════════════════════════════ -->
<section style="background:linear-gradient(135deg,#1F2937 0%,#374151 100%);color:#fff;padding:var(--space-3xl) 0;">
  <div class="container text-center">
    <div style="font-size:48px;margin-bottom:var(--space-md);">🎯</div>
    <h2 style="color:#fff;margin-bottom:var(--space-md);">Refer Friends. Earn Together.</h2>
    <p style="font-size:19px;opacity:.85;max-width:560px;margin:0 auto var(--space-xl);">
      Get <strong style="color:var(--orange-secondary);">200 SilverPoints</strong> for every friend you refer who joins and verifies their account.
      Plus your friend gets <strong style="color:var(--orange-secondary);">100 welcome points</strong> too!
    </p>
    <a href="/public/register.php" class="btn btn--primary btn--lg">Start Earning Now →</a>
  </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     FAQ SNIPPET
════════════════════════════════════════════════════════════════════════════ -->
<section class="section section--bg">
  <div class="container container--narrow">
    <div class="section-header">
      <div class="section-header__tag">Got Questions?</div>
      <h2 class="section-header__title">Frequently Asked Questions</h2>
    </div>

    <?php
    $faqs_snap = [];
    try {
        $stmt = db()->query("SELECT question, answer FROM faqs WHERE is_active=1 ORDER BY sort_order LIMIT 5");
        $faqs_snap = $stmt->fetchAll();
    } catch (PDOException) {}
    ?>

    <?php if (!empty($faqs_snap)): ?>
      <div style="display:flex;flex-direction:column;gap:var(--space-md);" x-data="{open:null}">
        <?php foreach ($faqs_snap as $i => $faq): ?>
          <div class="card" style="padding:0;overflow:hidden;">
            <button
              style="width:100%;text-align:left;padding:var(--space-lg);background:none;border:none;cursor:pointer;font-size:17px;font-weight:600;display:flex;justify-content:space-between;align-items:center;gap:var(--space-md);"
              onclick="this.nextElementSibling.classList.toggle('hidden'); this.querySelector('.faq-icon').textContent = this.nextElementSibling.classList.contains('hidden') ? '+' : '−';">
              <span><?= e($faq['question']) ?></span>
              <span class="faq-icon" style="color:var(--orange-primary);flex-shrink:0;font-size:22px;font-weight:400;">+</span>
            </button>
            <div class="hidden" style="padding:0 var(--space-lg) var(--space-lg);color:var(--text-muted);font-size:16px;line-height:1.7;border-top:1px solid var(--border-light);">
              <div style="padding-top:var(--space-md);"><?= e($faq['answer']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <!-- Static fallback if DB is empty -->
      <div class="card" style="padding:var(--space-lg);">
        <p style="text-align:center;color:var(--text-muted);">Visit our <a href="/public/faq.php">FAQ page</a> for detailed answers.</p>
      </div>
    <?php endif; ?>

    <div class="text-center" style="margin-top:var(--space-xl);">
      <a href="/public/faq.php" class="btn btn--secondary">View All FAQs</a>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     FINAL CTA
════════════════════════════════════════════════════════════════════════════ -->
<section style="background:linear-gradient(135deg,var(--orange-primary) 0%,var(--orange-secondary) 100%);padding:var(--space-3xl) 0;color:#fff;text-align:center;">
  <div class="container">
    <h2 style="color:#fff;margin-bottom:var(--space-md);font-size:clamp(26px,4vw,42px);">
      Ready to Start Enjoying More?
    </h2>
    <p style="font-size:19px;opacity:.92;max-width:520px;margin:0 auto var(--space-xl);">
      Membership is free. Deals are real. Your community is waiting.
    </p>
    <div style="display:flex;justify-content:center;flex-wrap:wrap;gap:var(--space-md);">
      <a href="/public/register.php"   class="btn btn--ghost btn--lg">🎉 Join Free Now</a>
      <a href="<?= whatsapp_url() ?>" class="btn btn--ghost" target="_blank" rel="noopener">💬 WhatsApp Us</a>
    </div>
  </div>
</section>

<?php include __DIR__ . '/../inc/public_footer.php'; ?>
