<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';

// Redirect logged-in users
if (auth_check()) {
    redirect('/member/dashboard.php');
}

$page_title = 'Join Free — SilverDeals MY';
$meta_desc  = 'Create your free SilverDeals MY membership. Exclusive senior deals, rewards, and your digital membership card.';

$errors  = [];
$values  = ['name'=>'','email'=>'','phone'=>'','plan'=>'free'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();

    if (!rate_limit('register', 5, 300)) {
        $errors['general'] = 'Too many registration attempts. Please wait a few minutes.';
    } else {
        // Collect & sanitise
        $name     = trim($_POST['name']    ?? '');
        $email    = trim(strtolower($_POST['email'] ?? ''));
        $phone    = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        $password = $_POST['password']     ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $plan     = in_array($_POST['plan'] ?? '', ['free','silver','gold']) ? $_POST['plan'] : 'free';
        $ref_code = trim($_POST['ref'] ?? '');
        $agree    = isset($_POST['agree']);

        $values = compact('name','email','phone','plan');

        // Validation
        if (empty($name))               $errors['name']     = 'Full name is required.';
        elseif (mb_strlen($name) < 2)   $errors['name']     = 'Please enter your full name.';

        if (empty($email))              $errors['email']    = 'Email address is required.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Please enter a valid email address.';

        if (strlen($phone) < 9)         $errors['phone']    = 'Please enter a valid Malaysian phone number.';

        if (strlen($password) < 8)      $errors['password'] = 'Password must be at least 8 characters.';
        elseif ($password !== $confirm)  $errors['password'] = 'Passwords do not match.';

        if (!$agree)                    $errors['agree']    = 'Please agree to the Terms of Service and Privacy Policy.';

        if (empty($errors)) {
            try {
                $pdo = db();

                // Check duplicate email
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors['email'] = 'This email is already registered. Please login instead.';
                }

                // Check duplicate phone
                if (empty($errors)) {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
                    $stmt->execute([$phone]);
                    if ($stmt->fetch()) {
                        $errors['phone'] = 'This phone number is already registered.';
                    }
                }

                if (empty($errors)) {
                    $pdo->beginTransaction();

                    // Create user
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $stmt = $pdo->prepare("
                        INSERT INTO users (name, email, phone, password_hash, role, status)
                        VALUES (?, ?, ?, ?, 'member', 'pending')
                    ");
                    $stmt->execute([$name, $email, $phone, $hash]);
                    $userId = (int)$pdo->lastInsertId();

                    // Referral lookup
                    $referrerId = null;
                    if (!empty($ref_code)) {
                        $stmt = $pdo->prepare("SELECT user_id FROM member_profiles WHERE referral_code = ? LIMIT 1");
                        $stmt->execute([$ref_code]);
                        $referrer = $stmt->fetch();
                        if ($referrer && $referrer['user_id'] !== $userId) {
                            $referrerId = (int)$referrer['user_id'];
                        }
                    }

                    // Create member profile + referral code
                    $memberNumber = generate_member_number();
                    $referralCode = generate_referral_code($userId);
                    $stmt = $pdo->prepare("
                        INSERT INTO member_profiles
                            (user_id, member_number, referral_code, referred_by)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$userId, $memberNumber, $referralCode, $referrerId]);

                    // Create points wallet
                    $pdo->prepare("INSERT INTO points_wallets (user_id, balance) VALUES (?, 0)")
                        ->execute([$userId]);

                    // Record referral
                    if ($referrerId) {
                        $pdo->prepare("
                            INSERT INTO referrals (referrer_id, referred_id, referral_code, status)
                            VALUES (?, ?, ?, 'pending')
                        ")->execute([$referrerId, $userId, $ref_code]);
                    }

                    // Audit log
                    $pdo->prepare("
                        INSERT INTO audit_logs (user_id, action, target_type, target_id, ip_address)
                        VALUES (?, 'member.register', 'user', ?, ?)
                    ")->execute([$userId, $userId, $_SERVER['REMOTE_ADDR'] ?? null]);

                    $pdo->commit();

                    auth_set_flash('success', 'Welcome to SilverDeals MY! Your account is under review. You can login and complete your profile while you wait.');
                    redirect('/login.php');
                }
            } catch (PDOException $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                error_log('[SilverDeals Register] ' . $e->getMessage());
                $errors['general'] = 'Registration failed due to a server error. Please try again.';
            }
        }
    }
}

$ref_code_prefill = e($_GET['ref']  ?? '');
$plan_prefill     = in_array($_GET['plan'] ?? '', ['free','silver','gold']) ? $_GET['plan'] : 'free';
include __DIR__ . '/../inc/public_header.php';
?>

<div class="auth-page">
  <div class="auth-card" style="max-width:520px;">
    <div class="auth-card__logo">
      <div class="auth-card__logo-text">🟠 SilverDeals MY</div>
      <div style="font-size:13px;color:var(--text-muted);margin-top:4px;">Senior Membership &amp; Rewards</div>
    </div>

    <h2 class="auth-card__title">Create Your Free Account</h2>
    <p class="auth-card__subtitle">Join thousands of Malaysians aged 50+ enjoying better deals every day.</p>

    <?php if (!empty($errors['general'])): ?>
      <div class="alert alert--error"><span class="alert__icon">✕</span><span><?= e($errors['general']) ?></span></div>
    <?php endif; ?>

    <form method="POST" action="/register.php" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="ref" value="<?= $ref_code_prefill ?>">

      <!-- Full Name -->
      <div class="form-group">
        <label class="form-label" for="name">Full Name <span class="required">*</span></label>
        <input
          type="text" id="name" name="name"
          class="form-control <?= !empty($errors['name']) ? 'form-control--error' : '' ?>"
          value="<?= e($values['name']) ?>"
          placeholder="As per MyKad"
          autocomplete="name" required>
        <?php if (!empty($errors['name'])): ?><div class="form-error"><?= e($errors['name']) ?></div><?php endif; ?>
      </div>

      <!-- Email -->
      <div class="form-group">
        <label class="form-label" for="email">Email Address <span class="required">*</span></label>
        <input
          type="email" id="email" name="email"
          class="form-control <?= !empty($errors['email']) ? 'form-control--error' : '' ?>"
          value="<?= e($values['email']) ?>"
          placeholder="yourname@email.com"
          autocomplete="email" required>
        <?php if (!empty($errors['email'])): ?><div class="form-error"><?= e($errors['email']) ?></div><?php endif; ?>
      </div>

      <!-- Phone -->
      <div class="form-group">
        <label class="form-label" for="phone">Mobile Number <span class="required">*</span></label>
        <input
          type="tel" id="phone" name="phone"
          class="form-control <?= !empty($errors['phone']) ? 'form-control--error' : '' ?>"
          value="<?= e($values['phone']) ?>"
          placeholder="e.g. 60123456789"
          autocomplete="tel" required>
        <div class="form-hint">Malaysian mobile number (including country code 60)</div>
        <?php if (!empty($errors['phone'])): ?><div class="form-error"><?= e($errors['phone']) ?></div><?php endif; ?>
      </div>

      <!-- Password -->
      <div class="form-group">
        <label class="form-label" for="password">Password <span class="required">*</span></label>
        <div style="position:relative;">
          <input
            type="password" id="password" name="password"
            class="form-control <?= !empty($errors['password']) ? 'form-control--error' : '' ?>"
            placeholder="Minimum 8 characters"
            autocomplete="new-password" required>
          <button type="button" data-toggle-password="password"
            style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:18px;">👁</button>
        </div>
        <?php if (!empty($errors['password'])): ?><div class="form-error"><?= e($errors['password']) ?></div><?php endif; ?>
      </div>

      <!-- Confirm Password -->
      <div class="form-group">
        <label class="form-label" for="confirm_password">Confirm Password <span class="required">*</span></label>
        <input
          type="password" id="confirm_password" name="confirm_password"
          class="form-control"
          placeholder="Re-enter your password"
          autocomplete="new-password" required>
      </div>

      <!-- Referral Code -->
      <div class="form-group">
        <label class="form-label" for="ref_code_display">Referral Code <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
        <input
          type="text" id="ref_code_display" name="ref"
          class="form-control"
          value="<?= $ref_code_prefill ?>"
          placeholder="Enter a referral code if you have one">
        <div class="form-hint">Have a referral code? Enter it to earn 100 bonus points on approval.</div>
      </div>

      <!-- Membership Plan -->
      <div class="form-group">
        <label class="form-label">Membership Plan</label>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:var(--space-sm);">
          <?php foreach (['free'=>['Free','🆓','Free Forever'],'silver'=>['Silver','🥈','RM 49/yr'],'gold'=>['Gold','🥇','RM 99/yr']] as $key=>[$label,$icon,$price]): ?>
            <label style="cursor:pointer;">
              <input type="radio" name="plan" value="<?= $key ?>" <?= ($values['plan']===$key || $plan_prefill===$key) ? 'checked' : '' ?> style="display:none;" class="plan-radio">
              <div class="card plan-option" style="padding:var(--space-md);text-align:center;cursor:pointer;border:2px solid var(--border-light);transition:all .2s;"
                   onclick="document.querySelectorAll('.plan-option').forEach(e=>e.style.borderColor='var(--border-light)');this.style.borderColor='var(--orange-primary)';">
                <div style="font-size:24px;"><?= $icon ?></div>
                <div style="font-weight:700;font-size:15px;"><?= $label ?></div>
                <div style="font-size:13px;color:var(--text-muted);"><?= $price ?></div>
              </div>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="form-hint">You can upgrade anytime after joining.</div>
      </div>

      <!-- Terms -->
      <div class="form-group">
        <label class="form-check">
          <input type="checkbox" name="agree" <?= isset($_POST['agree']) ? 'checked' : '' ?>>
          <span>
            I agree to the
            <a href="/terms.php" target="_blank">Terms of Service</a> and
            <a href="/privacy.php" target="_blank">Privacy Policy</a>.
            I confirm I am at least 50 years old or registering on behalf of a senior family member.
            <span class="required">*</span>
          </span>
        </label>
        <?php if (!empty($errors['agree'])): ?><div class="form-error"><?= e($errors['agree']) ?></div><?php endif; ?>
      </div>

      <button type="submit" class="btn btn--primary btn--full btn--lg">
        🎉 Create My Free Account
      </button>
    </form>

    <div class="auth-divider">or</div>

    <p class="text-center" style="font-size:16px;">
      Already have an account? <a href="/login.php" style="font-weight:600;">Login here</a>
    </p>

    <p class="text-center text-muted" style="font-size:14px;margin-top:var(--space-lg);">
      Need help? <a href="<?= whatsapp_url('Hello, I need help registering on SilverDeals MY.') ?>" target="_blank">Chat with us on WhatsApp</a>
    </p>
  </div>
</div>

<script>
// Highlight selected plan on load
document.querySelectorAll('.plan-radio').forEach(radio => {
  if (radio.checked) {
    radio.closest('label').querySelector('.plan-option').style.borderColor = 'var(--orange-primary)';
  }
  radio.addEventListener('change', () => {
    document.querySelectorAll('.plan-option').forEach(e => e.style.borderColor = 'var(--border-light)');
    if (radio.checked) radio.closest('label').querySelector('.plan-option').style.borderColor = 'var(--orange-primary)';
  });
});
</script>

<?php include __DIR__ . '/../inc/public_footer.php'; ?>
