<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

$isLoggedIn = Auth::check();
$userId     = $isLoggedIn ? Auth::id() : null;

// Check if user already has an affiliate account
$myAffiliate = null;
if ($isLoggedIn) {
    try {
        $myAffiliate = DB::fetch(
            "SELECT * FROM affiliates WHERE user_id = ? LIMIT 1",
            [$userId]
        );
    } catch (Throwable $e) {}
}

// PRG: handle application submission
$success = '';
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply'])) {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $desc    = trim($_POST['description'] ?? '');

    if (!$name)  $errors[] = 'Name is required.';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';

    if (!$errors) {
        // Generate unique code
        $code = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($name)), 0, 4))
              . strtoupper(substr(md5($email . time()), 0, 4));

        try {
            // Ensure table exists
            DB::query("CREATE TABLE IF NOT EXISTS affiliates (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                user_id         INT DEFAULT NULL,
                code            VARCHAR(20) NOT NULL UNIQUE,
                name            VARCHAR(150) NOT NULL,
                email           VARCHAR(191) NOT NULL,
                website         VARCHAR(255),
                description     TEXT,
                commission_rate DECIMAL(5,2) DEFAULT 20.00,
                status          ENUM('pending','active','suspended','rejected') DEFAULT 'pending',
                payout_method   VARCHAR(50) DEFAULT 'bank_transfer',
                bank_name       VARCHAR(100),
                bank_account    VARCHAR(50),
                bank_holder     VARCHAR(150),
                total_clicks    INT DEFAULT 0,
                total_referrals INT DEFAULT 0,
                total_earned    DECIMAL(10,2) DEFAULT 0.00,
                total_paid      DECIMAL(10,2) DEFAULT 0.00,
                notes           TEXT,
                approved_at     DATETIME,
                created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            DB::insert('affiliates', [
                'user_id'     => $userId,
                'code'        => $code,
                'name'        => $name,
                'email'       => $email,
                'website'     => $website ?: null,
                'description' => $desc ?: null,
                'status'      => 'pending',
            ]);

            $success = 'Application received! We will review it within 2 business days and email you at ' . htmlspecialchars($email) . '.';
            $myAffiliate = DB::fetch("SELECT * FROM affiliates WHERE email = ? LIMIT 1", [$email]);
        } catch (Throwable $e) {
            $errors[] = 'Could not submit application. Please try again.';
        }
    }
}

$pageTitle    = 'Affiliate Programme — Earn 20% Recurring Commission';
$pageDesc     = 'Join the BizAI Affiliate Programme. Earn 20% recurring monthly commission for every business you refer. No cap on earnings.';
$pageKeywords = 'affiliate programme Malaysia, referral programme, earn commission Malaysia, BizAI affiliate, AI tools affiliate';
require_once 'includes/header.php';
?>

<!-- Hero -->
<section class="py-5" style="background:linear-gradient(135deg,#0d0d1a 0%,#1a0d2e 50%,#0d1a2e 100%);border-bottom:1px solid rgba(139,92,246,0.2)">
  <div class="container py-4">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <span class="badge rounded-pill px-3 py-2 mb-3" style="background:rgba(139,92,246,0.15);color:#a78bfa;border:1px solid rgba(139,92,246,0.3)">
          <i class="bi bi-people-fill me-1"></i>Affiliate Programme
        </span>
        <h1 class="display-4 fw-bold mb-3">
          Earn <span class="text-gradient">20% Recurring</span><br>Commission
        </h1>
        <p class="text-muted fs-5 mb-4">
          Refer Malaysian businesses to BizAI and earn 20% of every monthly subscription — for as long as they remain a customer. No cap. No expiry.
        </p>
        <div class="d-flex flex-wrap gap-3 mb-4">
          <div class="d-flex align-items-center gap-2 text-white-50 small">
            <i class="bi bi-check-circle-fill text-success"></i>Free to join
          </div>
          <div class="d-flex align-items-center gap-2 text-white-50 small">
            <i class="bi bi-check-circle-fill text-success"></i>No minimum traffic
          </div>
          <div class="d-flex align-items-center gap-2 text-white-50 small">
            <i class="bi bi-check-circle-fill text-success"></i>30-day cookie
          </div>
          <div class="d-flex align-items-center gap-2 text-white-50 small">
            <i class="bi bi-check-circle-fill text-success"></i>Monthly payouts
          </div>
        </div>
        <div class="d-flex gap-3 flex-wrap">
          <a href="#apply" class="btn btn-primary btn-lg px-4">
            <i class="bi bi-person-plus me-2"></i>Apply Now — It's Free
          </a>
          <a href="#how-it-works" class="btn btn-outline-light btn-lg px-4">
            How It Works
          </a>
        </div>
      </div>
      <div class="col-lg-6">
        <!-- Earnings Calculator -->
        <div class="glass-card p-4 rounded-4" style="border:1px solid rgba(139,92,246,0.25)">
          <h5 class="text-white fw-semibold mb-4">
            <i class="bi bi-calculator me-2 text-primary"></i>Earnings Calculator
          </h5>
          <div class="mb-3">
            <label class="text-muted small mb-1">Businesses you refer per month</label>
            <input type="range" class="form-range" id="refCount" min="1" max="50" value="5" oninput="calcEarnings()">
            <div class="d-flex justify-content-between">
              <span class="text-muted small">1</span>
              <span class="fw-semibold text-primary" id="refCountVal">5 businesses</span>
              <span class="text-muted small">50</span>
            </div>
          </div>
          <div class="mb-4">
            <label class="text-muted small mb-1">Average plan value (RM/month)</label>
            <select class="form-select form-select-sm bg-transparent text-white border-secondary" id="planVal" onchange="calcEarnings()">
              <option value="199">RM 199 — Starter</option>
              <option value="499" selected>RM 499 — Growth</option>
              <option value="999">RM 999 — Enterprise</option>
            </select>
          </div>
          <div class="row g-3 text-center">
            <div class="col-4">
              <div class="rounded-3 p-3" style="background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.2)">
                <div class="fw-bold text-primary fs-5" id="monthlyEarn">RM 499</div>
                <div class="text-muted small">Month 1</div>
              </div>
            </div>
            <div class="col-4">
              <div class="rounded-3 p-3" style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2)">
                <div class="fw-bold text-success fs-5" id="year1Earn">RM 5,988</div>
                <div class="text-muted small">Year 1</div>
              </div>
            </div>
            <div class="col-4">
              <div class="rounded-3 p-3" style="background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.2)">
                <div class="fw-bold text-warning fs-5" id="year2Earn">RM 11,976</div>
                <div class="text-muted small">Year 2</div>
              </div>
            </div>
          </div>
          <p class="text-muted small mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Calculated at 20% commission. Assumes referred businesses remain active.
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Stats bar -->
<div style="background:rgba(99,102,241,0.08);border-bottom:1px solid rgba(99,102,241,0.15)">
  <div class="container py-3">
    <div class="row g-3 text-center">
      <div class="col-6 col-md-3">
        <div class="fw-bold text-white fs-4">20%</div>
        <div class="text-muted small">Recurring Commission</div>
      </div>
      <div class="col-6 col-md-3">
        <div class="fw-bold text-white fs-4">30 days</div>
        <div class="text-muted small">Cookie Duration</div>
      </div>
      <div class="col-6 col-md-3">
        <div class="fw-bold text-white fs-4">RM 100</div>
        <div class="text-muted small">Min. Payout</div>
      </div>
      <div class="col-6 col-md-3">
        <div class="fw-bold text-white fs-4">15th</div>
        <div class="text-muted small">Monthly Payout Date</div>
      </div>
    </div>
  </div>
</div>

<!-- How It Works -->
<section class="py-6 bg-section" id="how-it-works">
  <div class="container">
    <div class="text-center mb-5">
      <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill mb-3">
        <i class="bi bi-signpost-2 me-1"></i>How It Works
      </span>
      <h2 class="fw-bold display-6">Three Simple Steps to Earn</h2>
      <p class="text-muted">No technical knowledge needed. No minimum requirements.</p>
    </div>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="glass-card p-4 h-100 text-center">
          <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-4"
               style="width:72px;height:72px;background:rgba(99,102,241,0.15);border:2px solid rgba(99,102,241,0.3)">
            <i class="bi bi-person-plus text-primary" style="font-size:28px"></i>
          </div>
          <div class="text-primary fw-bold small mb-2">STEP 1</div>
          <h5 class="text-white fw-semibold mb-3">Apply & Get Approved</h5>
          <p class="text-muted small">Fill in the application below. We approve most applications within 2 business days. You'll receive your unique affiliate link and dashboard access.</p>
          <div class="mt-3 d-flex justify-content-center gap-2 flex-wrap">
            <span class="badge bg-success-soft text-success">Bloggers</span>
            <span class="badge bg-success-soft text-success">Consultants</span>
            <span class="badge bg-success-soft text-success">Trainers</span>
            <span class="badge bg-success-soft text-success">Anyone</span>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="glass-card p-4 h-100 text-center" style="border-color:rgba(139,92,246,0.3)">
          <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-4"
               style="width:72px;height:72px;background:rgba(139,92,246,0.15);border:2px solid rgba(139,92,246,0.3)">
            <i class="bi bi-share-fill" style="font-size:28px;color:#a78bfa"></i>
          </div>
          <div class="fw-bold small mb-2" style="color:#a78bfa">STEP 2</div>
          <h5 class="text-white fw-semibold mb-3">Share Your Link</h5>
          <p class="text-muted small">Share your unique referral link through your blog, social media, email newsletter, WhatsApp broadcast, or simply recommend BizAI to your network.</p>
          <div class="mt-3 d-flex justify-content-center gap-2 flex-wrap">
            <span class="badge" style="background:rgba(139,92,246,0.15);color:#a78bfa">Blog</span>
            <span class="badge" style="background:rgba(139,92,246,0.15);color:#a78bfa">Social Media</span>
            <span class="badge" style="background:rgba(139,92,246,0.15);color:#a78bfa">WhatsApp</span>
            <span class="badge" style="background:rgba(139,92,246,0.15);color:#a78bfa">Email</span>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="glass-card p-4 h-100 text-center" style="border-color:rgba(16,185,129,0.3)">
          <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-4"
               style="width:72px;height:72px;background:rgba(16,185,129,0.1);border:2px solid rgba(16,185,129,0.3)">
            <i class="bi bi-cash-coin text-success" style="font-size:28px"></i>
          </div>
          <div class="text-success fw-bold small mb-2">STEP 3</div>
          <h5 class="text-white fw-semibold mb-3">Earn Every Month</h5>
          <p class="text-muted small">When someone signs up through your link and subscribes, you earn 20% of their monthly subscription — every month they stay active. Commission is paid on the 15th.</p>
          <div class="mt-3 d-flex justify-content-center gap-2 flex-wrap">
            <span class="badge bg-success-soft text-success">No cap</span>
            <span class="badge bg-success-soft text-success">Recurring</span>
            <span class="badge bg-success-soft text-success">Bank transfer</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Commission Structure -->
<section class="py-6">
  <div class="container">
    <div class="row g-5 align-items-center">
      <div class="col-lg-5">
        <span class="badge bg-warning-soft text-warning px-3 py-2 rounded-pill mb-3">
          <i class="bi bi-cash-stack me-1"></i>Commission Structure
        </span>
        <h2 class="fw-bold mb-4">Transparent, Recurring Commissions</h2>
        <p class="text-muted mb-4">We believe in rewarding affiliates who bring long-term value. That's why our commissions are recurring — not one-time. The longer your referral stays, the more you earn.</p>
        <ul class="list-unstyled">
          <li class="d-flex align-items-start gap-3 mb-3">
            <i class="bi bi-check-circle-fill text-success mt-1"></i>
            <div>
              <div class="text-white fw-semibold">20% Monthly Recurring</div>
              <div class="text-muted small">Earn 20% of every monthly subscription your referrals pay. No time limit.</div>
            </div>
          </li>
          <li class="d-flex align-items-start gap-3 mb-3">
            <i class="bi bi-check-circle-fill text-success mt-1"></i>
            <div>
              <div class="text-white fw-semibold">30-Day Cookie Window</div>
              <div class="text-muted small">If someone clicks your link and subscribes within 30 days, you get credit for the referral.</div>
            </div>
          </li>
          <li class="d-flex align-items-start gap-3 mb-3">
            <i class="bi bi-check-circle-fill text-success mt-1"></i>
            <div>
              <div class="text-white fw-semibold">RM 100 Minimum Payout</div>
              <div class="text-muted small">Commissions are accumulated and paid once you reach RM 100. Most affiliates hit this in month 1.</div>
            </div>
          </li>
          <li class="d-flex align-items-start gap-3 mb-3">
            <i class="bi bi-check-circle-fill text-success mt-1"></i>
            <div>
              <div class="text-white fw-semibold">Paid on the 15th</div>
              <div class="text-muted small">All verified commissions from the previous month are paid via bank transfer on the 15th of each month.</div>
            </div>
          </li>
          <li class="d-flex align-items-start gap-3">
            <i class="bi bi-check-circle-fill text-success mt-1"></i>
            <div>
              <div class="text-white fw-semibold">Real-Time Dashboard</div>
              <div class="text-muted small">Track clicks, sign-ups, active referrals, and earnings in your affiliate dashboard.</div>
            </div>
          </li>
        </ul>
      </div>
      <div class="col-lg-7">
        <div class="glass-card p-4 rounded-4">
          <h5 class="text-white fw-semibold mb-4">Commission by Plan</h5>
          <div class="table-responsive">
            <table class="table table-dark table-borderless mb-0">
              <thead>
                <tr class="text-muted small border-bottom border-secondary border-opacity-25">
                  <th>Plan</th>
                  <th>Monthly Price</th>
                  <th class="text-primary">Your Cut (20%)</th>
                  <th class="text-success">Per Year</th>
                </tr>
              </thead>
              <tbody>
                <tr class="border-bottom border-secondary border-opacity-10">
                  <td>
                    <div class="fw-semibold text-white">Starter</div>
                    <div class="text-muted" style="font-size:11px">1–3 Capsules</div>
                  </td>
                  <td class="text-white">RM 199</td>
                  <td class="text-primary fw-bold">RM 39.80 / mo</td>
                  <td class="text-success fw-bold">RM 477.60</td>
                </tr>
                <tr class="border-bottom border-secondary border-opacity-10">
                  <td>
                    <div class="fw-semibold text-white">Growth</div>
                    <div class="text-muted" style="font-size:11px">4–8 Capsules</div>
                  </td>
                  <td class="text-white">RM 499</td>
                  <td class="text-primary fw-bold">RM 99.80 / mo</td>
                  <td class="text-success fw-bold">RM 1,197.60</td>
                </tr>
                <tr>
                  <td>
                    <div class="fw-semibold text-white">Enterprise</div>
                    <div class="text-muted" style="font-size:11px">Unlimited Capsules</div>
                  </td>
                  <td class="text-white">RM 999</td>
                  <td class="text-primary fw-bold">RM 199.80 / mo</td>
                  <td class="text-success fw-bold">RM 2,397.60</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="mt-4 p-3 rounded-3" style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2)">
            <div class="d-flex align-items-start gap-3">
              <i class="bi bi-lightbulb-fill text-warning mt-1"></i>
              <div>
                <div class="text-white fw-semibold small mb-1">Example: 10 Growth referrals</div>
                <div class="text-muted small">10 businesses on the Growth plan × RM 99.80 = <strong class="text-success">RM 998 per month</strong> in recurring commission. That's <strong class="text-success">RM 11,976 / year</strong> — from a single cohort of referrals.</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Who is it for -->
<section class="py-6 bg-section">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="fw-bold display-6">Who Should Join</h2>
      <p class="text-muted">Our most successful affiliates come from these backgrounds</p>
    </div>
    <div class="row g-4">
      <?php
      $personas = [
        ['icon'=>'bi-mortarboard','title'=>'Business Trainers & Coaches','desc'=>'If you run SME training programmes, workshops, or coaching — your audience is exactly who BizAI is built for. Bundle your training with our AI tools for even greater value.','color'=>'#6366f1'],
        ['icon'=>'bi-journal-richtext','title'=>'Business Bloggers & YouTubers','desc'=>'Write or talk about Malaysian SME challenges, productivity, or technology? Your readers are our target customers. Content about AI tools converts exceptionally well.','color'=>'#a78bfa'],
        ['icon'=>'bi-briefcase','title'=>'Accountants & Consultants','desc'=>'Your SME clients need better operations, HR, and finance tools. Recommend BizAI and earn recurring commission from every client who subscribes.','color'=>'#10b981'],
        ['icon'=>'bi-megaphone','title'=>'Digital Marketers','desc'=>'With a WhatsApp broadcast list, social media following, or email database of business owners — you have direct access to our ideal customers.','color'=>'#f59e0b'],
        ['icon'=>'bi-building','title'=>'Business Associations','desc'=>'Chambers of commerce, trade associations, and entrepreneur networks can offer BizAI as a member benefit while earning commission on every activation.','color'=>'#3b82f6'],
        ['icon'=>'bi-people','title'=>'Anyone with a Network','desc'=>'If you know Malaysian business owners personally, you can refer them directly. No platform or audience required — just genuine recommendations.','color'=>'#ec4899'],
      ];
      foreach ($personas as $p): ?>
      <div class="col-md-6 col-lg-4">
        <div class="glass-card p-4 h-100">
          <div class="mb-3">
            <i class="<?= $p['icon'] ?>" style="font-size:28px;color:<?= $p['color'] ?>"></i>
          </div>
          <h6 class="text-white fw-semibold mb-2"><?= $p['title'] ?></h6>
          <p class="text-muted small mb-0"><?= $p['desc'] ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Apply Section -->
<section class="py-6" id="apply">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-8">

        <?php if ($myAffiliate): ?>
        <!-- Already applied / active -->
        <div class="glass-card p-5 text-center rounded-4">
          <?php if ($myAffiliate['status'] === 'active'): ?>
            <i class="bi bi-patch-check-fill text-success" style="font-size:48px"></i>
            <h3 class="text-white fw-bold mt-3 mb-2">You're an Active Affiliate!</h3>
            <p class="text-muted mb-1">Your affiliate code: <strong class="text-primary"><?= htmlspecialchars($myAffiliate['code']) ?></strong></p>
            <p class="text-muted mb-4">Your link: <code class="text-primary"><?= SITE_URL ?>/register.php?aff=<?= htmlspecialchars($myAffiliate['code']) ?></code></p>
            <div class="row g-3 mb-4">
              <div class="col-4">
                <div class="rounded-3 p-3 bg-dark">
                  <div class="fw-bold text-white fs-4"><?= number_format($myAffiliate['total_clicks']) ?></div>
                  <div class="text-muted small">Total Clicks</div>
                </div>
              </div>
              <div class="col-4">
                <div class="rounded-3 p-3 bg-dark">
                  <div class="fw-bold text-white fs-4"><?= number_format($myAffiliate['total_referrals']) ?></div>
                  <div class="text-muted small">Referrals</div>
                </div>
              </div>
              <div class="col-4">
                <div class="rounded-3 p-3 bg-dark">
                  <div class="fw-bold text-success fs-4">RM <?= number_format($myAffiliate['total_earned'], 2) ?></div>
                  <div class="text-muted small">Total Earned</div>
                </div>
              </div>
            </div>
            <button onclick="navigator.clipboard.writeText('<?= SITE_URL ?>/register.php?aff=<?= htmlspecialchars($myAffiliate['code']) ?>').then(()=>alert('Link copied!'))"
                    class="btn btn-primary px-4">
              <i class="bi bi-clipboard me-2"></i>Copy Affiliate Link
            </button>
          <?php elseif ($myAffiliate['status'] === 'pending'): ?>
            <i class="bi bi-hourglass-split text-warning" style="font-size:48px"></i>
            <h3 class="text-white fw-bold mt-3 mb-2">Application Under Review</h3>
            <p class="text-muted">Your affiliate application is being reviewed. We will email you at <strong><?= htmlspecialchars($myAffiliate['email']) ?></strong> within 2 business days.</p>
          <?php elseif ($myAffiliate['status'] === 'rejected'): ?>
            <i class="bi bi-x-circle text-danger" style="font-size:48px"></i>
            <h3 class="text-white fw-bold mt-3 mb-2">Application Not Approved</h3>
            <p class="text-muted">Unfortunately your application was not approved at this time. Please contact us at <a href="mailto:affiliate@bizai.my">affiliate@bizai.my</a> for more information.</p>
          <?php endif; ?>
        </div>

        <?php else: ?>

        <?php if ($success): ?>
        <div class="alert alert-success d-flex align-items-center gap-3 mb-4 rounded-3" role="alert">
          <i class="bi bi-check-circle-fill fs-5"></i>
          <div><?= htmlspecialchars($success) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($errors): ?>
        <div class="alert alert-danger mb-4 rounded-3">
          <ul class="mb-0 ps-3">
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <div class="glass-card p-5 rounded-4">
          <div class="text-center mb-5">
            <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill mb-3">
              <i class="bi bi-send me-1"></i>Apply Now
            </span>
            <h2 class="fw-bold">Join the Affiliate Programme</h2>
            <p class="text-muted">Free to join. No minimum requirements. Start earning in days.</p>
          </div>

          <form method="POST" action="#apply">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label text-white-50 small">Your Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name" placeholder="Tan Wei Ming"
                       value="<?= htmlspecialchars($_POST['name'] ?? ($isLoggedIn ? Auth::user()['name'] : '')) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label text-white-50 small">Email Address <span class="text-danger">*</span></label>
                <input type="email" class="form-control" name="email" placeholder="you@example.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? ($isLoggedIn ? Auth::user()['email'] : '')) ?>" required>
              </div>
              <div class="col-12">
                <label class="form-label text-white-50 small">Website / Blog / Social Media URL <span class="text-muted">(optional)</span></label>
                <input type="url" class="form-control" name="website" placeholder="https://yourblog.com or https://instagram.com/yourhandle"
                       value="<?= htmlspecialchars($_POST['website'] ?? '') ?>">
              </div>
              <div class="col-12">
                <label class="form-label text-white-50 small">Tell us about yourself and how you plan to promote BizAI</label>
                <textarea class="form-control" name="description" rows="4"
                          placeholder="I run a business training company for SMEs in KL and Selangor with 500+ alumni. I plan to recommend BizAI to my graduates and feature it in my monthly newsletter..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                <div class="form-text text-muted small">This helps us understand your audience and approve your application faster.</div>
              </div>
              <div class="col-12">
                <div class="p-3 rounded-3 mb-3" style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2)">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="termsCheck" required>
                    <label class="form-check-label text-muted small" for="termsCheck">
                      I agree to the <a href="/terms-of-service.php" class="text-primary" target="_blank">Terms of Service</a> and the BizAI Affiliate Programme terms. I will not use spamming, misleading advertising, or paid search targeting BizAI brand terms.
                    </label>
                  </div>
                </div>
              </div>
              <div class="col-12 text-center">
                <button type="submit" name="apply" class="btn btn-primary btn-lg px-5">
                  <i class="bi bi-person-plus me-2"></i>Submit Application
                </button>
                <p class="text-muted small mt-3">We review applications within 2 business days. Questions? Email <a href="mailto:affiliate@bizai.my" class="text-primary">affiliate@bizai.my</a></p>
              </div>
            </div>
          </form>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="py-6 bg-section">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-7">
        <div class="text-center mb-5">
          <h2 class="fw-bold">Frequently Asked Questions</h2>
        </div>
        <div class="accordion" id="faqAccordion">
          <?php
          $faqs = [
            ['q'=>'How long does approval take?', 'a'=>'We review most applications within 2 business days. You will receive an email notification once approved, along with your unique affiliate link and access to your dashboard.'],
            ['q'=>'When and how do I get paid?', 'a'=>'Commissions earned in a calendar month are paid on the 15th of the following month via bank transfer (Malaysian banks only). Minimum payout is RM 100.'],
            ['q'=>'What counts as a valid referral?', 'a'=>'A referral is counted when someone clicks your unique link, creates an account within 30 days, and subscribes to a paid plan. Free trial sign-ups do not generate commission until they convert to a paid subscription.'],
            ['q'=>'Is there a limit to how much I can earn?', 'a'=>'No cap at all. The more businesses you refer and the longer they stay active, the more you earn. Your commission continues as long as the customer maintains an active subscription.'],
            ['q'=>'Can I promote BizAI on social media or paid ads?', 'a'=>'Yes! You can promote BizAI on social media, your blog, email newsletters, YouTube, and organic channels. Paid advertising is allowed but you may not bid on BizAI brand keywords (e.g. "BizAI", "bizai.my") in Google Ads or Meta Ads.'],
            ['q'=>'Do you provide marketing materials?', 'a'=>'Yes. Once approved, we provide you with banner images, product screenshots, social media copy templates, and Chinese/English/Malay content you can use in your promotions.'],
            ['q'=>'What if my referral upgrades their plan?', 'a'=>'Your commission automatically updates to 20% of their new plan price. If they upgrade from Starter (RM 199) to Growth (RM 499), your commission increases from RM 39.80 to RM 99.80 per month.'],
            ['q'=>'I\'m a business trainer or course creator — can I bundle BizAI?', 'a'=>'Absolutely. Many of our affiliates are trainers who recommend BizAI to their students and clients. We can discuss custom arrangements including co-branded materials and exclusive affiliate rates for high-volume referrers.'],
          ];
          foreach ($faqs as $i => $faq): ?>
          <div class="accordion-item bg-transparent border-0 border-bottom border-secondary border-opacity-25">
            <h2 class="accordion-header">
              <button class="accordion-button <?= $i > 0 ? 'collapsed' : '' ?> text-white fw-semibold"
                      type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>"
                      style="background:transparent;box-shadow:none">
                <?= htmlspecialchars($faq['q']) ?>
              </button>
            </h2>
            <div id="faq<?= $i ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>" data-bs-parent="#faqAccordion">
              <div class="accordion-body text-muted pt-0">
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

<!-- Final CTA -->
<section class="py-6" style="background:linear-gradient(135deg,rgba(99,102,241,0.15) 0%,rgba(139,92,246,0.1) 100%);border-top:1px solid rgba(99,102,241,0.2)">
  <div class="container text-center">
    <h2 class="fw-bold display-5 mb-3">Ready to Start Earning?</h2>
    <p class="text-muted fs-5 mb-4" style="max-width:480px;margin:0 auto">
      Join the BizAI affiliate programme today. Free to join, no minimum requirements, and your first commission could arrive within weeks.
    </p>
    <a href="#apply" class="btn btn-primary btn-lg px-5">
      <i class="bi bi-person-plus me-2"></i>Apply Now — Free
    </a>
    <p class="text-muted small mt-3">Questions? <a href="mailto:affiliate@bizai.my" class="text-primary">affiliate@bizai.my</a></p>
  </div>
</section>

<script>
function calcEarnings() {
  const refs     = parseInt(document.getElementById('refCount').value);
  const planVal  = parseInt(document.getElementById('planVal').value);
  const comm     = 0.20;
  const monthly  = refs * planVal * comm;
  const year1    = monthly * 12;
  const year2    = year1 * 2; // cumulative (same refs, 2 years)

  document.getElementById('refCountVal').textContent  = refs + (refs === 1 ? ' business' : ' businesses');
  document.getElementById('monthlyEarn').textContent  = 'RM ' + monthly.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g,',');
  document.getElementById('year1Earn').textContent    = 'RM ' + year1.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g,',');
  document.getElementById('year2Earn').textContent    = 'RM ' + year2.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g,',');
}
calcEarnings();

// Click tracking — record affiliate link clicks
(function() {
  const urlParams = new URLSearchParams(window.location.search);
  const affCode   = urlParams.get('aff');
  if (affCode) {
    fetch('/api/affiliate-click.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({code: affCode, page: window.location.pathname})
    }).catch(()=>{});
  }
})();
</script>

<?php require_once 'includes/footer.php'; ?>
