<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
$page_title       = 'Contact Us — SilverDeals MY';
$page_description = 'Get in touch with the SilverDeals MY team. We\'re here to help with membership, deals, merchant partnerships, and more.';

$success = '';
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    rate_limit('contact_' . ($_SERVER['REMOTE_ADDR'] ?? 'x'), 3, 300);

    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$name)                          $errors[] = 'Your name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
    if (!$subject)                       $errors[] = 'Please select a subject.';
    if (strlen($message) < 20)           $errors[] = 'Message must be at least 20 characters.';

    if (!$errors) {
        // Log to DB audit_logs as a contact enquiry
        try {
            db()->prepare("INSERT INTO audit_logs(user_id,action,target_type,target_id,ip_address) VALUES(NULL,'public.contact_form',?,NULL,?)")
                ->execute([$email, $_SERVER['REMOTE_ADDR'] ?? null]);
        } catch (PDOException) {}
        $success = 'Thank you, ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '! Your message has been received. We\'ll reply within 1–2 business days.';
    }
}

include __DIR__ . '/../inc/public_header.php';
?>

<!-- Hero -->
<section style="background:linear-gradient(135deg,var(--orange-primary),var(--orange-secondary));padding:var(--space-2xl) 0;text-align:center;">
  <div class="container">
    <div style="font-size:48px;margin-bottom:var(--space-md);">💬</div>
    <h1 style="color:#fff;font-size:clamp(26px,4vw,44px);margin-bottom:var(--space-sm);">We're Here to Help</h1>
    <p style="color:rgba(255,255,255,.9);font-size:17px;">Reach out to us anytime — we love hearing from our members and partners.</p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="grid grid-2" style="gap:var(--space-2xl);align-items:start;max-width:900px;margin:0 auto;">

      <!-- Contact info -->
      <div>
        <h2 style="margin-bottom:var(--space-xl);">Get in Touch</h2>

        <div style="display:flex;flex-direction:column;gap:var(--space-lg);margin-bottom:var(--space-2xl);">
          <div style="display:flex;gap:var(--space-md);align-items:flex-start;">
            <div style="width:44px;height:44px;background:var(--orange-bg);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">💬</div>
            <div>
              <div style="font-weight:700;margin-bottom:4px;">WhatsApp (Fastest)</div>
              <p style="color:var(--text-muted);font-size:14px;margin-bottom:var(--space-sm);">For the quickest response, chat with us directly on WhatsApp.</p>
              <a href="<?= whatsapp_url('Hi SilverDeals MY, I need help with my account.') ?>" target="_blank" class="btn btn--primary btn--sm" style="background:#25D366;border-color:#25D366;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:6px;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                Chat on WhatsApp
              </a>
            </div>
          </div>

          <div style="display:flex;gap:var(--space-md);align-items:flex-start;">
            <div style="width:44px;height:44px;background:var(--orange-bg);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">✉️</div>
            <div>
              <div style="font-weight:700;margin-bottom:4px;">Email</div>
              <p style="color:var(--text-muted);font-size:14px;margin-bottom:4px;">For detailed enquiries and official correspondence.</p>
              <a href="mailto:hello@silverdeals.my" style="color:var(--orange-primary);font-weight:600;">hello@silverdeals.my</a>
            </div>
          </div>

          <div style="display:flex;gap:var(--space-md);align-items:flex-start;">
            <div style="width:44px;height:44px;background:var(--orange-bg);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">⏰</div>
            <div>
              <div style="font-weight:700;margin-bottom:4px;">Support Hours</div>
              <p style="color:var(--text-muted);font-size:14px;line-height:1.7;">Monday – Friday: 9am – 6pm<br>Saturday: 9am – 1pm<br>Closed on Sundays &amp; Public Holidays</p>
            </div>
          </div>
        </div>

        <!-- Topic cards -->
        <div style="background:var(--bg-light);border-radius:var(--radius-lg);padding:var(--space-lg);">
          <div style="font-weight:700;margin-bottom:var(--space-md);">Common Topics</div>
          <?php $topics = [['👤','Membership & Verification'],['🎁','Deals & Vouchers'],['🪙','SilverPoints'],['🏪','Merchant Partnership'],['🔒','Account & Security'],['💳','Payments & Payouts']]; ?>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-sm);">
            <?php foreach ($topics as $t): ?>
              <div style="font-size:13px;padding:var(--space-xs) 0;"><?= $t[0] ?> <?= $t[1] ?></div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Contact form -->
      <div class="card" style="padding:var(--space-xl);">
        <?php if ($success): ?>
          <div style="text-align:center;padding:var(--space-2xl) 0;">
            <div style="font-size:56px;margin-bottom:var(--space-lg);">✅</div>
            <h3 style="color:var(--success);margin-bottom:var(--space-md);">Message Sent!</h3>
            <p style="color:var(--text-muted);"><?= $success ?></p>
            <a href="/public/contact.php" class="btn btn--muted btn--sm" style="margin-top:var(--space-lg);">Send Another</a>
          </div>
        <?php else: ?>
          <h3 style="margin-bottom:var(--space-xl);">Send Us a Message</h3>

          <?php if ($errors): ?>
            <div class="alert alert--error" style="margin-bottom:var(--space-lg);">
              <span class="alert__icon">✕</span>
              <div><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e, ENT_QUOTES) ?></div><?php endforeach; ?></div>
            </div>
          <?php endif; ?>

          <form method="POST">
            <?= csrf_field() ?>
            <div class="form-group">
              <label class="form-label" for="name">Your Name <span style="color:var(--error);">*</span></label>
              <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="email">Email Address <span style="color:var(--error);">*</span></label>
              <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="subject">Subject <span style="color:var(--error);">*</span></label>
              <select id="subject" name="subject" class="form-control" required>
                <option value="">— Select a topic —</option>
                <?php foreach (['Membership & Verification','Deals & Vouchers','SilverPoints','Merchant Partnership','Account & Security','Payments & Payouts','General Enquiry','Other'] as $opt): ?>
                  <option value="<?= $opt ?>" <?= ($_POST['subject'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="message">Message <span style="color:var(--error);">*</span></label>
              <textarea id="message" name="message" class="form-control" rows="5" required placeholder="Please describe your enquiry in detail so we can help you faster…"><?= htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES) ?></textarea>
            </div>
            <button type="submit" class="btn btn--primary btn--full btn--lg">Send Message</button>
            <p style="font-size:12px;color:var(--text-muted);text-align:center;margin-top:var(--space-md);">We typically respond within 1–2 business days.</p>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/../inc/public_footer.php'; ?>
