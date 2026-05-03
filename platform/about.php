<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
$pageTitle = 'About Us';
$pageDesc  = 'Learn about BizAI — built by founders who know SME pain. Our mission, team, and journey.';
require_once 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section d-flex align-items-center" style="min-height:60vh;">
  <div class="container py-5">
    <div class="row align-items-center g-5">
      <div class="col-lg-7">
        <div class="mb-3">
          <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill section-badge">
            <i class="bi bi-people-fill me-1"></i>Our Story
          </span>
        </div>
        <h1 class="display-4 fw-bold mb-4">
          Built by founders who<br>
          <span class="text-gradient">know SME pain</span>
        </h1>
        <p class="lead text-muted mb-4" style="max-width:540px;">
          We've been where you are — juggling too many tools, too little time, and a team that needs to do more with less. So we built the platform we wished existed.
        </p>
        <div class="d-flex flex-wrap gap-3">
          <a href="/register.php" class="btn btn-primary btn-lg px-4">
            <i class="bi bi-rocket-takeoff me-2"></i>Start Free Trial
          </a>
          <a href="/contact.php" class="btn btn-outline-light btn-lg px-4">
            <i class="bi bi-chat-dots me-2"></i>Talk to Us
          </a>
        </div>
      </div>
      <div class="col-lg-5 d-none d-lg-block">
        <div class="glass-card rounded-4 p-4 text-center">
          <div class="display-1 mb-3">🚀</div>
          <div class="row g-3 text-center">
            <div class="col-6">
              <div class="fw-bold fs-2 text-gradient">101</div>
              <div class="text-muted small">AI Capsules</div>
            </div>
            <div class="col-6">
              <div class="fw-bold fs-2 text-gradient">500+</div>
              <div class="text-muted small">SMEs Served</div>
            </div>
            <div class="col-6">
              <div class="fw-bold fs-2 text-gradient">8</div>
              <div class="text-muted small">Categories</div>
            </div>
            <div class="col-6">
              <div class="fw-bold fs-2 text-gradient">4.8★</div>
              <div class="text-muted small">Avg. Rating</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Mission & Values -->
<section class="py-6 bg-section">
  <div class="container">
    <div class="text-center mb-5">
      <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill section-badge mb-3">
        <i class="bi bi-bullseye me-1"></i>Mission
      </span>
      <h2 class="fw-bold display-6 mb-3">Our Mission</h2>
      <p class="text-muted" style="max-width:580px;margin:0 auto">
        To put enterprise-grade AI automation within reach of every small and medium business — without the enterprise price tag.
      </p>
    </div>
    <div class="row g-4">
      <!-- Innovation -->
      <div class="col-md-4">
        <div class="glass-card rounded-4 p-4 h-100">
          <div class="cat-icon bg-primary-soft mb-3" style="width:56px;height:56px;border-radius:16px;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-lightbulb-fill text-primary fs-4"></i>
          </div>
          <h5 class="fw-bold mb-2">Innovation</h5>
          <p class="text-muted small mb-0">
            We ship new Capsules and features every week. Our engineering team monitors industry trends so your toolkit stays ahead of the curve — always.
          </p>
        </div>
      </div>
      <!-- Reliability -->
      <div class="col-md-4">
        <div class="glass-card rounded-4 p-4 h-100">
          <div class="cat-icon bg-primary-soft mb-3" style="width:56px;height:56px;border-radius:16px;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-shield-check-fill text-primary fs-4"></i>
          </div>
          <h5 class="fw-bold mb-2">Reliability</h5>
          <p class="text-muted small mb-0">
            99.9% uptime SLA, enterprise-grade security, and data residency options. Your business doesn't sleep, and neither does our infrastructure.
          </p>
        </div>
      </div>
      <!-- Simplicity -->
      <div class="col-md-4">
        <div class="glass-card rounded-4 p-4 h-100">
          <div class="cat-icon bg-primary-soft mb-3" style="width:56px;height:56px;border-radius:16px;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-magic text-primary fs-4"></i>
          </div>
          <h5 class="fw-bold mb-2">Simplicity</h5>
          <p class="text-muted small mb-0">
            No PhD required. Each Capsule is ready to deploy in minutes, with plain-language configuration and contextual help at every step.
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Team Section -->
<section class="py-6">
  <div class="container">
    <div class="text-center mb-5">
      <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill section-badge mb-3">
        <i class="bi bi-person-badge me-1"></i>The Team
      </span>
      <h2 class="fw-bold display-6 mb-3">Meet the People Behind BizAI</h2>
      <p class="text-muted">Passionate builders, designers, and operators on a mission.</p>
    </div>
    <div class="row g-4">
      <?php
      $team = [
        ['name'=>'Sarah Chen',      'title'=>'CEO & Co-Founder',       'initials'=>'SC', 'color'=>'#6366f1', 'bio'=>'Former VP Product at a Fortune 500. Quit to build the AI tools SMEs actually need.'],
        ['name'=>'Marcus Taylor',   'title'=>'CTO & Co-Founder',       'initials'=>'MT', 'color'=>'#06b6d4', 'bio'=>'Ex-Google engineer. Built AI infra at scale. Now obsessed with making it simple.'],
        ['name'=>'Priya Sharma',    'title'=>'Head of Product',         'initials'=>'PS', 'color'=>'#10b981', 'bio'=>'12 years in SaaS product. Knows exactly what SME teams need — and what they don\'t.'],
        ['name'=>'James O\'Brien',  'title'=>'Head of Engineering',     'initials'=>'JO', 'color'=>'#f59e0b', 'bio'=>'Full-stack at heart. Leads a team of 18 engineers shipping 10x faster than the competition.'],
        ['name'=>'Aisha Osei',      'title'=>'Head of Customer Success','initials'=>'AO', 'color'=>'#f000b8', 'bio'=>'Obsessed with customer outcomes. Her team maintains a 97% satisfaction score.'],
        ['name'=>'Luca Rossi',      'title'=>'Head of Design',          'initials'=>'LR', 'color'=>'#8b5cf6', 'bio'=>'Award-winning designer. Makes complex AI feel approachable and even beautiful.'],
      ];
      foreach ($team as $member):
      ?>
      <div class="col-sm-6 col-lg-4">
        <div class="glass-card rounded-4 p-4 h-100 text-center">
          <div class="avatar-initials mb-3 mx-auto" style="width:64px;height:64px;border-radius:50%;background:<?= $member['color'] ?>22;color:<?= $member['color'] ?>;font-size:20px;font-weight:700;display:flex;align-items:center;justify-content:center;border:2px solid <?= $member['color'] ?>44;">
            <?= htmlspecialchars($member['initials']) ?>
          </div>
          <h6 class="fw-bold mb-1"><?= htmlspecialchars($member['name']) ?></h6>
          <div class="text-primary small fw-semibold mb-2"><?= htmlspecialchars($member['title']) ?></div>
          <p class="text-muted small mb-0"><?= htmlspecialchars($member['bio']) ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Timeline -->
<section class="py-6 bg-section">
  <div class="container">
    <div class="text-center mb-5">
      <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill section-badge mb-3">
        <i class="bi bi-clock-history me-1"></i>Our Journey
      </span>
      <h2 class="fw-bold display-6">Company Timeline</h2>
    </div>
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <?php
        $timeline = [
          ['year'=>'2022', 'title'=>'Founded', 'desc'=>'BizAI was born in a shared coworking space. Three co-founders, one whiteboard, and a clear vision: democratize AI for SMEs.', 'icon'=>'bi-rocket-takeoff', 'color'=>'#6366f1'],
          ['year'=>'2023', 'title'=>'First 10 Capsules Launched', 'desc'=>'We launched our first 10 AI Capsules focused on customer service and sales automation. 47 SMEs signed up in month one.', 'icon'=>'bi-cpu', 'color'=>'#06b6d4'],
          ['year'=>'2024', 'title'=>'100+ Customers', 'desc'=>'We crossed 100 paying customers, raised a seed round, and expanded our team to 22 people across 4 countries.', 'icon'=>'bi-people-fill', 'color'=>'#10b981'],
          ['year'=>'2025', 'title'=>'BOS Platform Launched', 'desc'=>'We launched the full Business Operating System with 101 specialised AI Capsules across 8 business categories. 500+ SMEs now run on BizAI.', 'icon'=>'bi-trophy-fill', 'color'=>'#f59e0b'],
        ];
        foreach ($timeline as $i => $item):
        ?>
        <div class="d-flex gap-4 mb-4">
          <div class="d-flex flex-column align-items-center" style="width:48px;flex-shrink:0">
            <div style="width:48px;height:48px;border-radius:50%;background:<?= $item['color'] ?>22;border:2px solid <?= $item['color'] ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i class="<?= $item['icon'] ?>" style="color:<?= $item['color'] ?>;font-size:20px;"></i>
            </div>
            <?php if ($i < count($timeline)-1): ?>
            <div style="width:2px;flex:1;min-height:32px;background:<?= $item['color'] ?>33;margin-top:4px;"></div>
            <?php endif; ?>
          </div>
          <div class="glass-card rounded-4 p-4 flex-grow-1 mb-2">
            <div class="d-flex align-items-center gap-2 mb-1">
              <span class="fw-bold" style="color:<?= $item['color'] ?>"><?= htmlspecialchars($item['year']) ?></span>
              <span class="text-muted">·</span>
              <span class="fw-semibold"><?= htmlspecialchars($item['title']) ?></span>
            </div>
            <p class="text-muted small mb-0"><?= htmlspecialchars($item['desc']) ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- CTA Banner -->
<section class="py-6">
  <div class="container">
    <div class="glass-card rounded-4 p-5 text-center" style="background:linear-gradient(135deg,rgba(99,102,241,0.12),rgba(139,92,246,0.06))!important;border-color:rgba(99,102,241,0.25)!important;">
      <div class="display-4 mb-3">🎯</div>
      <h2 class="fw-bold display-6 mb-3">
        Join <span class="text-gradient">500+ SMEs</span> automating with AI
      </h2>
      <p class="text-muted mb-4" style="max-width:500px;margin:0 auto">
        14-day free trial. No credit card. Cancel any time. Start automating your business today.
      </p>
      <a href="/register.php" class="btn btn-primary btn-lg px-5">
        <i class="bi bi-rocket-takeoff me-2"></i>Get Started Free
      </a>
    </div>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
