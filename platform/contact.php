<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

$success = false;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $company = trim($_POST['company'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$name)                        $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (!$subject)                     $errors[] = 'Subject is required.';
    if (strlen($message) < 10)        $errors[] = 'Message must be at least 10 characters.';

    if (empty($errors)) {
        DB::insert('leads', [
            'name'    => htmlspecialchars($name),
            'email'   => htmlspecialchars($email),
            'company' => htmlspecialchars($company),
            'message' => htmlspecialchars("[$subject] $message"),
            'source'  => 'contact-page',
            'status'  => 'new',
        ]);
        $success = true;
    }
}

$pageTitle = 'Contact Us';
$pageDesc  = 'Get in touch with the AI101 team. We\'re here to help you automate smarter.';
require_once 'includes/header.php';
?>

<!-- Hero -->
<section class="py-5 bg-section">
  <div class="container py-4 text-center">
    <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill section-badge mb-3">
      <i class="bi bi-chat-heart me-1"></i>Get In Touch
    </span>
    <h1 class="display-4 fw-bold mb-3">
      Let's <span class="text-gradient">Talk</span>
    </h1>
    <p class="text-muted" style="max-width:500px;margin:0 auto">
      Questions, demos, partnership ideas — our team is always happy to help. We typically respond within 2 business hours.
    </p>
  </div>
</section>

<!-- Main Content -->
<section class="py-6">
  <div class="container">
    <?php if ($success): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 mb-4 rounded-3" role="alert">
      <i class="bi bi-check-circle-fill fs-5"></i>
      <div><strong>Message sent!</strong> We'll get back to you within 2 business hours.</div>
    </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger rounded-3 mb-4" role="alert">
      <i class="bi bi-exclamation-circle me-2"></i>
      <?= implode(' ', array_map('htmlspecialchars', $errors)) ?>
    </div>
    <?php endif; ?>

    <div class="row g-5">
      <!-- Left: contact info -->
      <div class="col-lg-4">
        <h5 class="fw-bold mb-4">Contact Information</h5>

        <div class="glass-card rounded-4 p-4 d-flex gap-3 align-items-start mb-3">
          <div style="width:44px;height:44px;border-radius:12px;background:rgba(99,102,241,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="bi bi-envelope-fill text-primary fs-5"></i>
          </div>
          <div>
            <div class="fw-semibold small mb-1">Email</div>
            <a href="mailto:hello@ai101platform.com" class="text-muted text-decoration-none small">hello@ai101platform.com</a>
          </div>
        </div>

        <div class="glass-card rounded-4 p-4 d-flex gap-3 align-items-start mb-3">
          <div style="width:44px;height:44px;border-radius:12px;background:rgba(99,102,241,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="bi bi-telephone-fill text-primary fs-5"></i>
          </div>
          <div>
            <div class="fw-semibold small mb-1">Phone</div>
            <a href="tel:+18005551234" class="text-muted text-decoration-none small">+1 (800) 555-1234</a>
          </div>
        </div>

        <div class="glass-card rounded-4 p-4 d-flex gap-3 align-items-start mb-3">
          <div style="width:44px;height:44px;border-radius:12px;background:rgba(99,102,241,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="bi bi-geo-alt-fill text-primary fs-5"></i>
          </div>
          <div>
            <div class="fw-semibold small mb-1">Location</div>
            <span class="text-muted small">340 Pine Street, Suite 800<br>San Francisco, CA 94104</span>
          </div>
        </div>

        <div class="glass-card rounded-4 p-4 d-flex gap-3 align-items-start mb-4">
          <div style="width:44px;height:44px;border-radius:12px;background:rgba(99,102,241,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="bi bi-clock-fill text-primary fs-5"></i>
          </div>
          <div>
            <div class="fw-semibold small mb-1">Support Hours</div>
            <span class="text-muted small">Mon–Fri: 8am – 8pm PST<br>Sat–Sun: 10am – 4pm PST</span>
          </div>
        </div>

        <!-- Social links -->
        <div class="d-flex gap-3">
          <a href="#" class="btn btn-sm btn-outline-secondary rounded-circle" style="width:38px;height:38px;display:flex;align-items:center;justify-content:center;"><i class="bi bi-twitter-x"></i></a>
          <a href="#" class="btn btn-sm btn-outline-secondary rounded-circle" style="width:38px;height:38px;display:flex;align-items:center;justify-content:center;"><i class="bi bi-linkedin"></i></a>
          <a href="#" class="btn btn-sm btn-outline-secondary rounded-circle" style="width:38px;height:38px;display:flex;align-items:center;justify-content:center;"><i class="bi bi-facebook"></i></a>
          <a href="#" class="btn btn-sm btn-outline-secondary rounded-circle" style="width:38px;height:38px;display:flex;align-items:center;justify-content:center;"><i class="bi bi-youtube"></i></a>
        </div>
      </div>

      <!-- Right: form -->
      <div class="col-lg-8">
        <div class="glass-card rounded-4 p-4 p-lg-5">
          <h5 class="fw-bold mb-4">Send Us a Message</h5>
          <form method="POST" novalidate>
            <div class="row g-3">
              <div class="col-sm-6">
                <label class="form-label small fw-semibold">Your Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" placeholder="Jane Smith"
                  value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
              </div>
              <div class="col-sm-6">
                <label class="form-label small fw-semibold">Work Email <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control" placeholder="jane@company.com"
                  value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
              </div>
              <div class="col-sm-6">
                <label class="form-label small fw-semibold">Company</label>
                <input type="text" name="company" class="form-control" placeholder="Acme Corp"
                  value="<?= htmlspecialchars($_POST['company'] ?? '') ?>">
              </div>
              <div class="col-sm-6">
                <label class="form-label small fw-semibold">Subject <span class="text-danger">*</span></label>
                <select name="subject" class="form-select" required>
                  <option value="">Select a topic…</option>
                  <?php
                  $subjects = ['General Inquiry','Sales & Pricing','Technical Support','Partnership','Demo Request','Billing','Other'];
                  $sel = $_POST['subject'] ?? '';
                  foreach ($subjects as $s):
                  ?>
                  <option value="<?= htmlspecialchars($s) ?>" <?= ($sel === $s ? 'selected' : '') ?>>
                    <?= htmlspecialchars($s) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Message <span class="text-danger">*</span></label>
                <textarea name="message" class="form-control" rows="5" placeholder="Tell us how we can help…" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary px-5 py-2">
                  <i class="bi bi-send me-2"></i>Send Message
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Map Placeholder -->
<section class="py-0 mb-5">
  <div class="container">
    <div class="rounded-4 overflow-hidden" style="height:280px;background:linear-gradient(135deg,rgba(99,102,241,0.08),rgba(139,92,246,0.05));border:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:center;flex-direction:column;gap:12px;">
      <i class="bi bi-geo-alt-fill text-primary" style="font-size:48px;opacity:0.6;"></i>
      <p class="text-muted small mb-0">San Francisco, CA — 340 Pine Street, Suite 800</p>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="py-6 bg-section">
  <div class="container">
    <div class="text-center mb-5">
      <span class="badge bg-primary-soft text-primary px-3 py-2 rounded-pill section-badge mb-3">
        <i class="bi bi-question-circle me-1"></i>FAQ
      </span>
      <h2 class="fw-bold display-6">Frequently Asked Questions</h2>
    </div>
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="accordion" id="contactFAQ">
          <?php
          $faqs = [
            ['q'=>'How quickly will you respond to my message?',
             'a'=>'We aim to respond to all enquiries within 2 business hours during business days. For urgent technical issues, Growth and Enterprise customers have access to priority support with a 1-hour SLA.'],
            ['q'=>'Can I request a personalised demo?',
             'a'=>'Absolutely! Select "Demo Request" as your subject and our sales team will arrange a 30-minute personalised walkthrough. We\'ll tailor it to your specific industry and business needs.'],
            ['q'=>'Do you offer onboarding support?',
             'a'=>'Yes. Every paid plan includes onboarding support. Growth customers get a dedicated onboarding call, and Enterprise customers get a full implementation concierge service.'],
            ['q'=>'I\'m a developer / partner interested in integrations — who do I contact?',
             'a'=>'Select "Partnership" in the subject dropdown and briefly describe your integration idea. Our partnerships team reviews all submissions and responds within 3 business days.'],
            ['q'=>'Where can I find documentation and API references?',
             'a'=>'Documentation is available inside your dashboard after signup. API references, webhook guides, and SDKs are linked from the documentation hub. Sign up for a free trial to get access instantly.'],
          ];
          foreach ($faqs as $i => $faq):
          ?>
          <div class="accordion-item glass-card border-0 mb-2 rounded-3 overflow-hidden">
            <h2 class="accordion-header">
              <button class="accordion-button <?= $i > 0 ? 'collapsed' : '' ?> bg-transparent text-white fw-semibold" type="button"
                data-bs-toggle="collapse" data-bs-target="#cfaq<?= $i ?>">
                <?= htmlspecialchars($faq['q']) ?>
              </button>
            </h2>
            <div id="cfaq<?= $i ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>" data-bs-parent="#contactFAQ">
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

<?php require_once 'includes/footer.php'; ?>
