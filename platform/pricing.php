<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
$pageTitle = 'Pricing — BOS Plans';
$pageDesc  = 'AiServe BOS pricing. Choose the plan that fits your business — Starter, Growth, or Enterprise. Includes BOS Core + AI Capsules.';
require_once 'includes/header.php';
?>

<!-- Hero -->
<section class="py-5 bg-section">
  <div class="container py-4 text-center">
    <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill mb-3">
      <i class="bi bi-tag-fill me-1"></i>Pricing
    </span>
    <h1 class="display-4 fw-bold mb-3">
      One System. <span class="text-gradient">Every Department.</span>
    </h1>
    <p class="text-muted mb-2" style="max-width:560px;margin:0 auto">
      Every plan includes the <strong class="text-white">BOS Core</strong> — your central operating system —
      plus the AI Capsules that automate the departments you choose.
    </p>
    <p class="text-muted small mt-2">Prices in Malaysian Ringgit (RM) · Monthly subscription · 14-day free trial</p>
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
            <p class="text-muted small">Solve your #1 pain first. BOS Core + Customer Service Capsule to replace manual customer handling.</p>
          </div>
          <div class="mb-4">
            <div class="d-flex align-items-end gap-1">
              <span class="display-5 fw-bold">RM3,500</span>
              <span class="text-muted mb-2">/mo</span>
            </div>
            <div class="text-muted small">BOS Core + 1 Capsule</div>
          </div>
          <ul class="list-unstyled flex-grow-1 mb-4">
            <?php
            $starter_features = [
              ['text'=>'BOS Core Platform',             'ok'=>true],
              ['text'=>'Customer Service Capsule',       'ok'=>true],
              ['text'=>'WhatsApp AI Inbox (5 users)',    'ok'=>true],
              ['text'=>'CRM — up to 2,000 contacts',    'ok'=>true],
              ['text'=>'FAQ automation',                 'ok'=>true],
              ['text'=>'Multi-language support',         'ok'=>true],
              ['text'=>'Basic analytics dashboard',      'ok'=>true],
              ['text'=>'Sales Conversion Capsule',       'ok'=>false],
              ['text'=>'Marketing Automation Capsule',   'ok'=>false],
              ['text'=>'AI Decision Layer',              'ok'=>false],
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
            <p class="text-muted small">BOS Core + 3 Capsules. Customer service, sales conversion, and marketing automation — all running automatically.</p>
          </div>
          <div class="mb-4">
            <div class="d-flex align-items-end gap-1">
              <span class="display-5 fw-bold text-gradient">RM10,000</span>
              <span class="text-muted mb-2">/mo</span>
            </div>
            <div class="text-muted small">BOS Core + 3 Capsules · Save RM4,500 vs individual</div>
          </div>
          <ul class="list-unstyled flex-grow-1 mb-4">
            <?php
            $growth_features = [
              ['text'=>'BOS Core Platform',              'ok'=>true],
              ['text'=>'Customer Service Capsule',        'ok'=>true],
              ['text'=>'Sales Conversion Capsule',        'ok'=>true],
              ['text'=>'Marketing Automation Capsule',    'ok'=>true],
              ['text'=>'WhatsApp AI Inbox (20 users)',    'ok'=>true],
              ['text'=>'CRM — unlimited contacts',        'ok'=>true],
              ['text'=>'Auto follow-up + quote gen',      'ok'=>true],
              ['text'=>'Broadcast automation',            'ok'=>true],
              ['text'=>'Advanced analytics + reports',    'ok'=>true],
              ['text'=>'AI Decision Layer',               'ok'=>false],
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
            <p class="text-muted small">All 6 Capsules + AI Decision Layer. The complete Business Operating System — fully automated.</p>
          </div>
          <div class="mb-4">
            <div class="d-flex align-items-end gap-1">
              <span class="display-5 fw-bold">RM22,500</span>
              <span class="text-muted mb-2">/mo</span>
            </div>
            <div class="text-muted small">All Capsules · Custom workflows · AI Decision</div>
          </div>
          <ul class="list-unstyled flex-grow-1 mb-4">
            <?php
            $enterprise_features = [
              ['text'=>'BOS Core Platform',              'ok'=>true],
              ['text'=>'All 6 Capsules included',        'ok'=>true],
              ['text'=>'AI Decision Layer',               'ok'=>true],
              ['text'=>'WhatsApp AI Inbox (unlimited)',   'ok'=>true],
              ['text'=>'Custom workflow automation',      'ok'=>true],
              ['text'=>'Sales forecasting + churn AI',   'ok'=>true],
              ['text'=>'Business insights dashboard',     'ok'=>true],
              ['text'=>'Dedicated implementation team',   'ok'=>true],
              ['text'=>'White-label + custom domain',     'ok'=>true],
              ['text'=>'24/7 dedicated support',          'ok'=>true],
            ];
            foreach ($enterprise_features as $f):
            ?>
            <li class="d-flex align-items-center gap-2 mb-2 small">
              <i class="bi bi-check-circle-fill text-success"></i>
              <?= htmlspecialchars($f['text']) ?>
            </li>
            <?php endforeach; ?>
          </ul>
          <a href="/contact.php" class="btn btn-outline-light w-100 py-2 fw-semibold">Talk to Sales</a>
        </div>
      </div>

    </div>
    <p class="text-center text-muted small mt-4">
      <i class="bi bi-shield-check me-1 text-success"></i>
      All plans include a <strong>14-day free trial</strong>. No credit card required. Cancel any time.
    </p>
  </div>
</section>

<!-- Add-on Capsules -->
<section class="py-6 bg-section">
  <div class="container">
    <div class="text-center mb-5">
      <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill mb-3">
        <i class="bi bi-puzzle-fill me-1"></i>Add-on Capsules
      </span>
      <h2 class="fw-bold display-6">Expand Your BOS Anytime</h2>
      <p class="text-muted">Already on a plan? Add individual Capsules as your business grows.</p>
    </div>
    <div class="row g-3">
      <?php
      $capsules = [
        ['bi-headset',         '#6366f1', 'Customer Service',     'RM1,500 – RM3,000',  'FAQ automation · Multi-language · Lead classification'],
        ['bi-graph-up-arrow',  '#10b981', 'Sales Conversion',     'RM3,000 – RM8,000',  'Auto follow-up · Quote generation · Closing scripts'],
        ['bi-bar-chart-line',  '#f59e0b', 'Daily Reporting',      'RM1,500 – RM4,000',  'Sales reports · Inventory · Cash flow · AI anomaly detection'],
        ['bi-people',          '#ef4444', 'HR & Admin',           'RM1,500 – RM3,500',  'Leave application · Payroll query · Staff FAQ'],
        ['bi-megaphone',       '#8b5cf6', 'Marketing Automation', 'RM2,000 – RM6,000',  'Broadcast automation · Segmentation · Campaigns'],
        ['bi-lightbulb',       '#06b6d4', 'AI Decision',         'RM5,000 – RM15,000', 'Sales forecasting · Churn prediction · Business insights'],
      ];
      foreach ($capsules as [$icon, $color, $name, $price, $features]):
      ?>
      <div class="col-md-6 col-lg-4">
        <div class="glass-card rounded-4 p-4 h-100">
          <div class="d-flex align-items-center gap-3 mb-3">
            <div style="width:44px;height:44px;border-radius:12px;background:<?= $color ?>22;color:<?= $color ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <i class="bi <?= $icon ?> fs-5"></i>
            </div>
            <div>
              <div class="text-white fw-semibold small"><?= $name ?> Capsule</div>
              <div class="text-primary small fw-semibold"><?= $price ?>/mo</div>
            </div>
          </div>
          <p class="text-muted small mb-0"><?= $features ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Comparison Table -->
<section class="py-6">
  <div class="container">
    <div class="text-center mb-5">
      <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill mb-3">
        <i class="bi bi-table me-1"></i>Compare Plans
      </span>
      <h2 class="fw-bold display-6">Full Plan Comparison</h2>
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
              ['feature'=>'BOS Core Platform',          'starter'=>true,          'growth'=>true,            'enterprise'=>true],
              ['feature'=>'WhatsApp AI Inbox users',    'starter'=>'5',           'growth'=>'20',            'enterprise'=>'Unlimited'],
              ['feature'=>'CRM Contacts',               'starter'=>'2,000',       'growth'=>'Unlimited',     'enterprise'=>'Unlimited'],
              ['feature'=>'Customer Service Capsule',   'starter'=>true,          'growth'=>true,            'enterprise'=>true],
              ['feature'=>'Sales Conversion Capsule',   'starter'=>false,         'growth'=>true,            'enterprise'=>true],
              ['feature'=>'Marketing Automation',        'starter'=>false,         'growth'=>true,            'enterprise'=>true],
              ['feature'=>'HR & Admin Capsule',          'starter'=>false,         'growth'=>'Add-on',        'enterprise'=>true],
              ['feature'=>'Daily Reporting Capsule',    'starter'=>false,         'growth'=>'Add-on',        'enterprise'=>true],
              ['feature'=>'AI Decision Layer',           'starter'=>false,         'growth'=>false,           'enterprise'=>true],
              ['feature'=>'Custom Workflows',            'starter'=>false,         'growth'=>false,           'enterprise'=>true],
              ['feature'=>'Analytics',                   'starter'=>'Basic',       'growth'=>'Advanced',      'enterprise'=>'Custom + AI'],
              ['feature'=>'Support',                     'starter'=>'Email',       'growth'=>'Priority',      'enterprise'=>'24/7 Dedicated'],
              ['feature'=>'Onboarding',                  'starter'=>'Self-serve',  'growth'=>'Guided call',   'enterprise'=>'Full concierge'],
              ['feature'=>'White-label',                 'starter'=>false,         'growth'=>false,           'enterprise'=>true],
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

<!-- FAQ -->
<section class="py-6 bg-section">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="fw-bold display-6">Common Questions</h2>
    </div>
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="accordion" id="pricingFAQ">
          <?php
          $pfaqs = [
            ['q'=>'What is the BOS Core?',
             'a'=>'BOS Core is the central platform included in every plan — it\'s the WhatsApp AI Inbox, CRM, automation engine, and dashboard. Think of it as the "machine" that runs your business. Capsules are the modules you plug into it.'],
            ['q'=>'What is a Capsule?',
             'a'=>'A Capsule is a plug-and-play AI module built for a specific business function — Customer Service, Sales, HR, Marketing, etc. Each Capsule is pre-built, continuously improved, and activates within your BOS Core instantly.'],
            ['q'=>'Can I add Capsules later?',
             'a'=>'Yes. You can add individual Capsules at any time from your dashboard. Each add-on Capsule is billed on top of your base plan at the published monthly rate.'],
            ['q'=>'Do I need a credit card to start the trial?',
             'a'=>'No credit card required. Your 14-day free trial starts the moment you create an account. We only collect payment details when you choose to continue.'],
            ['q'=>'What happens to my data if I cancel?',
             'a'=>'Your data remains accessible in read-only mode for 30 days after cancellation, giving you time to export everything. After 30 days, data is permanently deleted per our privacy policy.'],
            ['q'=>'Is there a contract or lock-in period?',
             'a'=>'All plans are monthly with no long-term contract required. Enterprise clients typically sign annual agreements for better pricing — contact our sales team to discuss.'],
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
<section class="py-6">
  <div class="container text-center">
    <div class="glass-card rounded-4 p-5" style="background:linear-gradient(135deg,rgba(99,102,241,0.12),rgba(139,92,246,0.06))!important;border-color:rgba(99,102,241,0.25)!important;">
      <h2 class="fw-bold display-6 mb-3">Ready to Run Your Business on Autopilot?</h2>
      <p class="text-muted mb-4" style="max-width:500px;margin:0 auto">
        Start with the Starter plan and scale as you grow. Most clients expand to Growth within 90 days.
      </p>
      <div class="d-flex flex-wrap justify-content-center gap-3">
        <a href="/register.php" class="btn btn-primary btn-lg px-5">
          <i class="bi bi-rocket-takeoff me-2"></i>Start Free Trial
        </a>
        <a href="/contact.php" class="btn btn-outline-light btn-lg px-5">Talk to Sales</a>
      </div>
    </div>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
