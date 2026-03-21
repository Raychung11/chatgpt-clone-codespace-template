<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
$pageTitle = 'Pricing';
$pageDesc  = 'Simple, transparent pricing for AI automation. Starter, Growth, and Enterprise plans with a 14-day free trial.';
require_once 'includes/header.php';
?>

<!-- Hero -->
<section class="py-5 bg-section">
  <div class="container py-4 text-center">
    <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill section-badge mb-3">
      <i class="bi bi-tag-fill me-1"></i>Pricing
    </span>
    <h1 class="display-4 fw-bold mb-3">
      Simple, <span class="text-gradient">Transparent Pricing</span>
    </h1>
    <p class="text-muted mb-4" style="max-width:520px;margin:0 auto">
      No hidden fees. No long-term contracts. Cancel any time. Every plan starts with a 14-day free trial.
    </p>
    <!-- Billing Toggle -->
    <div class="d-flex align-items-center justify-content-center gap-3 mb-2">
      <span id="lbl-monthly" class="fw-semibold text-white">Monthly</span>
      <div class="form-check form-switch mb-0">
        <input class="form-check-input" type="checkbox" id="billingToggle" style="width:48px;height:24px;cursor:pointer;">
      </div>
      <span id="lbl-yearly" class="fw-semibold text-muted">
        Yearly <span class="badge bg-success ms-1">Save 20%</span>
      </span>
    </div>
  </div>
</section>

<!-- Pricing Cards -->
<section class="py-6">
  <div class="container">
    <div class="row g-4 align-items-stretch">

      <!-- Starter -->
      <div class="col-lg-4">
        <div class="pricing-card glass-card rounded-4 p-4 h-100 d-flex flex-column">
          <div class="mb-3">
            <div class="d-flex align-items-center gap-2 mb-2">
              <div style="width:40px;height:40px;border-radius:10px;background:rgba(99,102,241,0.12);display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-lightning-fill text-primary"></i>
              </div>
              <h5 class="fw-bold mb-0">Starter</h5>
            </div>
            <p class="text-muted small">Perfect for solopreneurs and small teams getting started with AI automation.</p>
          </div>
          <div class="mb-4">
            <div class="d-flex align-items-end gap-1">
              <span class="display-5 fw-bold" id="starter-price">$49</span>
              <span class="text-muted mb-2">/mo</span>
            </div>
            <div class="text-muted small" id="starter-yearly-note" style="display:none;">Billed as $470/year — save $118</div>
          </div>
          <ul class="list-unstyled flex-grow-1 mb-4">
            <?php
            $starter_features = [
              ['text'=>'Up to 5 AI agents active',       'ok'=>true],
              ['text'=>'1,000 agent runs/month',          'ok'=>true],
              ['text'=>'Email + chat support',            'ok'=>true],
              ['text'=>'Basic analytics dashboard',       'ok'=>true],
              ['text'=>'API access (read-only)',           'ok'=>true],
              ['text'=>'Custom integrations',             'ok'=>false],
              ['text'=>'Priority support',                'ok'=>false],
              ['text'=>'Dedicated onboarding',            'ok'=>false],
              ['text'=>'White-label options',             'ok'=>false],
              ['text'=>'SSO / SAML',                     'ok'=>false],
            ];
            foreach ($starter_features as $f):
            ?>
            <li class="d-flex align-items-center gap-2 mb-2 small <?= $f['ok'] ? '' : 'text-muted' ?>" style="opacity:<?= $f['ok'] ? '1' : '0.4' ?>">
              <i class="bi <?= $f['ok'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?>"></i>
              <?= htmlspecialchars($f['text']) ?>
            </li>
            <?php endforeach; ?>
          </ul>
          <a href="/register.php" class="btn btn-outline-primary w-100 py-2 fw-semibold">Start Free Trial</a>
        </div>
      </div>

      <!-- Growth (featured) -->
      <div class="col-lg-4">
        <div class="pricing-card pricing-card-featured glass-card rounded-4 p-4 h-100 d-flex flex-column position-relative"
             style="border-color:rgba(99,102,241,0.5)!important;background:linear-gradient(135deg,rgba(99,102,241,0.10),rgba(139,92,246,0.05))!important;">
          <div class="position-absolute top-0 start-50 translate-middle">
            <span class="badge bg-primary px-3 py-2 rounded-pill">Most Popular</span>
          </div>
          <div class="mb-3 mt-3">
            <div class="d-flex align-items-center gap-2 mb-2">
              <div style="width:40px;height:40px;border-radius:10px;background:rgba(99,102,241,0.20);display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-graph-up-arrow text-primary"></i>
              </div>
              <h5 class="fw-bold mb-0">Growth</h5>
            </div>
            <p class="text-muted small">Built for growing teams who want serious automation across multiple departments.</p>
          </div>
          <div class="mb-4">
            <div class="d-flex align-items-end gap-1">
              <span class="display-5 fw-bold text-gradient" id="growth-price">$149</span>
              <span class="text-muted mb-2">/mo</span>
            </div>
            <div class="text-muted small" id="growth-yearly-note" style="display:none;">Billed as $1,430/year — save $358</div>
          </div>
          <ul class="list-unstyled flex-grow-1 mb-4">
            <?php
            $growth_features = [
              ['text'=>'Up to 25 AI agents active',       'ok'=>true],
              ['text'=>'10,000 agent runs/month',         'ok'=>true],
              ['text'=>'Priority email + phone support',  'ok'=>true],
              ['text'=>'Advanced analytics + reports',    'ok'=>true],
              ['text'=>'Full API access + webhooks',      'ok'=>true],
              ['text'=>'50+ pre-built integrations',      'ok'=>true],
              ['text'=>'Dedicated onboarding call',       'ok'=>true],
              ['text'=>'Team management (10 seats)',       'ok'=>true],
              ['text'=>'White-label options',             'ok'=>false],
              ['text'=>'SSO / SAML',                     'ok'=>false],
            ];
            foreach ($growth_features as $f):
            ?>
            <li class="d-flex align-items-center gap-2 mb-2 small <?= $f['ok'] ? '' : 'text-muted' ?>" style="opacity:<?= $f['ok'] ? '1' : '0.4' ?>">
              <i class="bi <?= $f['ok'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?>"></i>
              <?= htmlspecialchars($f['text']) ?>
            </li>
            <?php endforeach; ?>
          </ul>
          <a href="/register.php" class="btn btn-primary w-100 py-2 fw-semibold">Start Free Trial</a>
        </div>
      </div>

      <!-- Enterprise -->
      <div class="col-lg-4">
        <div class="pricing-card glass-card rounded-4 p-4 h-100 d-flex flex-column">
          <div class="mb-3">
            <div class="d-flex align-items-center gap-2 mb-2">
              <div style="width:40px;height:40px;border-radius:10px;background:rgba(99,102,241,0.12);display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-building text-primary"></i>
              </div>
              <h5 class="fw-bold mb-0">Enterprise</h5>
            </div>
            <p class="text-muted small">Unlimited scale, dedicated infrastructure, and white-glove service for larger organizations.</p>
          </div>
          <div class="mb-4">
            <div class="d-flex align-items-end gap-1">
              <span class="display-5 fw-bold" id="enterprise-price">$399</span>
              <span class="text-muted mb-2">/mo</span>
            </div>
            <div class="text-muted small" id="enterprise-yearly-note" style="display:none;">Billed as $3,830/year — save $958</div>
          </div>
          <ul class="list-unstyled flex-grow-1 mb-4">
            <?php
            $enterprise_features = [
              ['text'=>'Unlimited AI agents',            'ok'=>true],
              ['text'=>'Unlimited agent runs',           'ok'=>true],
              ['text'=>'24/7 dedicated support',         'ok'=>true],
              ['text'=>'Custom analytics + BI export',   'ok'=>true],
              ['text'=>'Full API + private webhooks',    'ok'=>true],
              ['text'=>'Custom integrations + dev hours','ok'=>true],
              ['text'=>'Full implementation concierge',  'ok'=>true],
              ['text'=>'Unlimited seats',                'ok'=>true],
              ['text'=>'White-label & custom domain',    'ok'=>true],
              ['text'=>'SSO / SAML + audit logs',        'ok'=>true],
            ];
            foreach ($enterprise_features as $f):
            ?>
            <li class="d-flex align-items-center gap-2 mb-2 small">
              <i class="bi bi-check-circle-fill text-success"></i>
              <?= htmlspecialchars($f['text']) ?>
            </li>
            <?php endforeach; ?>
          </ul>
          <a href="/contact.php" class="btn btn-outline-light w-100 py-2 fw-semibold">Contact Sales</a>
        </div>
      </div>

    </div>
    <p class="text-center text-muted small mt-4">
      <i class="bi bi-shield-check me-1 text-success"></i>
      All plans include a <strong>14-day free trial</strong>. No credit card required. Cancel any time.
    </p>
  </div>
</section>

<!-- Feature Comparison Table -->
<section class="py-6 bg-section">
  <div class="container">
    <div class="text-center mb-5">
      <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill section-badge mb-3">
        <i class="bi bi-table me-1"></i>Compare Plans
      </span>
      <h2 class="fw-bold display-6">Full Feature Comparison</h2>
    </div>
    <div class="glass-card rounded-4 overflow-hidden">
      <div class="table-responsive">
        <table class="table mb-0" style="color:inherit;">
          <thead>
            <tr style="background:rgba(255,255,255,0.04);">
              <th class="py-3 px-4 fw-semibold border-0" style="width:40%">Feature</th>
              <th class="py-3 px-4 fw-semibold border-0 text-center">Starter</th>
              <th class="py-3 px-4 fw-semibold border-0 text-center" style="background:rgba(99,102,241,0.08);">Growth</th>
              <th class="py-3 px-4 fw-semibold border-0 text-center">Enterprise</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $compare = [
              ['feature'=>'Active AI Agents',     'starter'=>'5',            'growth'=>'25',           'enterprise'=>'Unlimited'],
              ['feature'=>'Agent Runs / Month',   'starter'=>'1,000',        'growth'=>'10,000',        'enterprise'=>'Unlimited'],
              ['feature'=>'Team Seats',           'starter'=>'3',            'growth'=>'10',            'enterprise'=>'Unlimited'],
              ['feature'=>'Analytics Dashboard',  'starter'=>'Basic',        'growth'=>'Advanced',      'enterprise'=>'Custom + BI'],
              ['feature'=>'API Access',           'starter'=>'Read-only',    'growth'=>'Full',          'enterprise'=>'Full + Private'],
              ['feature'=>'Integrations',         'starter'=>'5',            'growth'=>'50+',           'enterprise'=>'Custom'],
              ['feature'=>'Onboarding Support',   'starter'=>false,          'growth'=>'Call',          'enterprise'=>'Concierge'],
              ['feature'=>'Support Channel',      'starter'=>'Email',        'growth'=>'Email + Phone', 'enterprise'=>'24/7 Dedicated'],
              ['feature'=>'SLA',                  'starter'=>'Best effort',  'growth'=>'4-hour',        'enterprise'=>'1-hour'],
              ['feature'=>'White-label',          'starter'=>false,          'growth'=>false,           'enterprise'=>true],
              ['feature'=>'SSO / SAML',           'starter'=>false,          'growth'=>false,           'enterprise'=>true],
              ['feature'=>'Audit Logs',           'starter'=>false,          'growth'=>'30 days',       'enterprise'=>'Unlimited'],
              ['feature'=>'Data Export',          'starter'=>'CSV',          'growth'=>'CSV + API',     'enterprise'=>'All formats'],
              ['feature'=>'Uptime SLA',           'starter'=>'99.5%',       'growth'=>'99.9%',         'enterprise'=>'99.99%'],
            ];
            foreach ($compare as $i => $row):
              $bg = $i % 2 === 0 ? '' : 'background:rgba(255,255,255,0.02);';
              function renderCell($val) {
                if ($val === true)  return '<i class="bi bi-check-circle-fill text-success"></i>';
                if ($val === false) return '<i class="bi bi-x-circle-fill" style="opacity:0.25;"></i>';
                return '<span class="small">' . htmlspecialchars($val) . '</span>';
              }
            ?>
            <tr style="<?= $bg ?>border-color:rgba(255,255,255,0.05);">
              <td class="py-3 px-4 fw-medium small border-0"><?= htmlspecialchars($row['feature']) ?></td>
              <td class="py-3 px-4 text-center border-0"><?= renderCell($row['starter']) ?></td>
              <td class="py-3 px-4 text-center border-0" style="background:rgba(99,102,241,0.05);"><?= renderCell($row['growth']) ?></td>
              <td class="py-3 px-4 text-center border-0"><?= renderCell($row['enterprise']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<!-- Pricing FAQ -->
<section class="py-6">
  <div class="container">
    <div class="text-center mb-5">
      <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill section-badge mb-3">
        <i class="bi bi-question-circle me-1"></i>FAQ
      </span>
      <h2 class="fw-bold display-6">Pricing Questions</h2>
    </div>
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="accordion" id="pricingFAQ">
          <?php
          $pfaqs = [
            ['q'=>'Do I need a credit card to start the free trial?',
             'a'=>'No credit card required. Simply create an account and your 14-day trial starts immediately. We only ask for payment details when you decide to continue after the trial.'],
            ['q'=>'Can I switch plans at any time?',
             'a'=>'Yes. You can upgrade or downgrade your plan at any time from your dashboard. Upgrades take effect immediately; downgrades apply at the next billing cycle. Unused credit is prorated.'],
            ['q'=>'What happens when my trial ends?',
             'a'=>'Your account moves to a read-only state. No data is deleted. You can continue by adding a payment method and choosing a plan — or export all your data for free.'],
            ['q'=>'Is there a discount for annual billing?',
             'a'=>'Yes! Choosing yearly billing saves you 20% compared to monthly billing. The discount is applied automatically when you select annual in the billing toggle above.'],
            ['q'=>'Can I get a custom quote for a large team?',
             'a'=>'Absolutely. Enterprise pricing can be tailored to your exact needs — including custom agent limits, SLA, data residency, and volume discounts. Contact our sales team.'],
            ['q'=>'What payment methods do you accept?',
             'a'=>'We accept all major credit/debit cards (Visa, Mastercard, AmEx, Discover) via Stripe. Enterprise customers can also pay by invoice (bank transfer / ACH).'],
          ];
          foreach ($pfaqs as $i => $faq):
          ?>
          <div class="accordion-item glass-card border-0 mb-2 rounded-3 overflow-hidden">
            <h2 class="accordion-header">
              <button class="accordion-button <?= $i > 0 ? 'collapsed' : '' ?> bg-transparent text-white fw-semibold" type="button"
                data-bs-toggle="collapse" data-bs-target="#pfaq<?= $i ?>">
                <?= htmlspecialchars($faq['q']) ?>
              </button>
            </h2>
            <div id="pfaq<?= $i ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>" data-bs-parent="#pricingFAQ">
              <div class="accordion-body text-muted small">
                <?= htmlspecialchars($faq['a']) ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="py-6 bg-section">
  <div class="container text-center">
    <div class="glass-card rounded-4 p-5" style="background:linear-gradient(135deg,rgba(99,102,241,0.12),rgba(139,92,246,0.06))!important;border-color:rgba(99,102,241,0.25)!important;">
      <h2 class="fw-bold display-6 mb-3">
        Start your <span class="text-gradient">free 14-day trial</span> today
      </h2>
      <p class="text-muted mb-4">No credit card. No commitment. Full access to all features.</p>
      <div class="d-flex flex-wrap gap-3 justify-content-center">
        <a href="/register.php" class="btn btn-primary btn-lg px-5">
          <i class="bi bi-rocket-takeoff me-2"></i>Start Free Trial
        </a>
        <a href="/contact.php" class="btn btn-outline-light btn-lg px-5">
          <i class="bi bi-chat-dots me-2"></i>Talk to Sales
        </a>
      </div>
    </div>
  </div>
</section>

<script>
(function () {
  var monthly  = { starter: '$49', growth: '$149', enterprise: '$399' };
  var yearly   = { starter: '$39', growth: '$119', enterprise: '$319' };
  var toggle   = document.getElementById('billingToggle');
  var lblMonthly = document.getElementById('lbl-monthly');
  var lblYearly  = document.getElementById('lbl-yearly');

  function applyBilling(isYearly) {
    var prices = isYearly ? yearly : monthly;
    document.getElementById('starter-price').textContent    = prices.starter;
    document.getElementById('growth-price').textContent     = prices.growth;
    document.getElementById('enterprise-price').textContent = prices.enterprise;

    document.getElementById('starter-yearly-note').style.display    = isYearly ? '' : 'none';
    document.getElementById('growth-yearly-note').style.display     = isYearly ? '' : 'none';
    document.getElementById('enterprise-yearly-note').style.display = isYearly ? '' : 'none';

    lblMonthly.classList.toggle('text-white', !isYearly);
    lblMonthly.classList.toggle('text-muted',  isYearly);
    lblYearly.classList.toggle('text-white',  isYearly);
    lblYearly.classList.toggle('text-muted',  !isYearly);
  }

  toggle.addEventListener('change', function () {
    applyBilling(toggle.checked);
  });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
