<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';

$page_title = 'Become a Merchant Partner — SilverDeals MY';
$meta_desc  = 'Partner with SilverDeals MY to reach thousands of senior Malaysians. List your deals, grow your business.';
$errors     = [];
$values     = ['business_name'=>'','email'=>'','phone'=>'','name'=>'','category_id'=>'','state'=>''];

$categories = [];
try { $categories = db()->query("SELECT id,name,icon FROM deal_categories WHERE is_active=1 ORDER BY sort_order")->fetchAll(); } catch (PDOException) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    if (!rate_limit('merchant_reg', 3, 600)) { $errors['general'] = 'Too many attempts. Please wait.'; }
    else {
        $name     = trim($_POST['owner_name']     ?? '');
        $email    = trim(strtolower($_POST['email'] ?? ''));
        $phone    = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $biz      = trim($_POST['business_name']  ?? '');
        $catId    = (int)($_POST['category_id']   ?? 0);
        $ssm      = trim($_POST['ssm_number']     ?? '');
        $state    = trim($_POST['state']          ?? '');
        $desc     = trim($_POST['description']    ?? '');
        $agree    = isset($_POST['agree']);

        $values = ['business_name'=>$biz,'email'=>$email,'phone'=>$phone,'name'=>$name,'category_id'=>$catId,'state'=>$state];

        if (!$name)               $errors['name']     = 'Owner name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid email required.';
        if (strlen($phone) < 9)   $errors['phone']    = 'Valid phone required.';
        if (strlen($password) < 8) $errors['password'] = 'Password min 8 characters.';
        if ($password !== $confirm) $errors['password'] = 'Passwords do not match.';
        if (!$biz)                $errors['business_name'] = 'Business name is required.';
        if (!$agree)              $errors['agree']    = 'Please accept the terms.';

        if (empty($errors)) {
            try {
                $pdo = db();
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
                $stmt->execute([$email]);
                if ($stmt->fetch()) { $errors['email'] = 'Email already registered.'; }

                if (empty($errors)) {
                    $pdo->beginTransaction();

                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]);
                    $pdo->prepare("INSERT INTO users (name,email,phone,password_hash,role,status) VALUES(?,?,?,?,'merchant','active')")
                        ->execute([$name,$email,$phone,$hash]);
                    $userId = (int)$pdo->lastInsertId();

                    $slug = slugify($biz) . '-' . $userId;
                    $pdo->prepare("INSERT INTO merchants (user_id,business_name,slug,category_id,ssm_number,description,status) VALUES(?,?,?,?,?,?,'pending')")
                        ->execute([$userId,$biz,$slug,$catId ?: null,$ssm ?: null,$desc ?: null]);
                    $merchantId = (int)$pdo->lastInsertId();

                    // Primary branch placeholder
                    if ($state) {
                        $pdo->prepare("INSERT INTO merchant_branches (merchant_id,branch_name,state,is_primary) VALUES(?,?,?,1)")
                            ->execute([$merchantId, $biz . ' — Main', $state]);
                    }

                    $pdo->prepare("INSERT INTO audit_logs(user_id,action,target_type,target_id,ip_address) VALUES(?,'merchant.register','merchant',?,?)")
                        ->execute([$userId,$merchantId,$_SERVER['REMOTE_ADDR']??null]);

                    // Notify admins
                    $admins = $pdo->query("SELECT id FROM users WHERE role IN('admin','superadmin') AND status='active'")->fetchAll();
                    foreach ($admins as $adm) notify((int)$adm['id'],'New Merchant Application','Business: '.$biz.' has applied to join SilverDeals MY.','info',$merchantId,'merchant');

                    $pdo->commit();
                    auth_set_flash('success','Application received! Our team will review your business within 1-2 business days. You can log in and set up your profile in the meantime.');
                    redirect('/merchant/login.php');
                }
            } catch (PDOException $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                error_log('[Merchant Reg] '.$e->getMessage());
                $errors['general'] = 'Registration failed. Please try again.';
            }
        }
    }
}

$states_my = ['Johor','Kedah','Kelantan','Melaka','Negeri Sembilan','Pahang','Perak','Perlis','Pulau Pinang','Sabah','Sarawak','Selangor','Terengganu','Kuala Lumpur','Labuan','Putrajaya'];
include __DIR__ . '/../inc/public_header.php';
?>

<!-- Hero -->
<section style="background:linear-gradient(135deg,#0F172A 0%,#1E3A5F 100%);color:#fff;padding:var(--space-3xl) 0;">
  <div class="container">
    <div class="grid grid-2" style="align-items:center;gap:var(--space-2xl);">
      <div>
        <div style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,107,0,.2);border:1px solid rgba(255,107,0,.4);border-radius:var(--radius-pill);padding:6px 16px;font-size:14px;font-weight:600;margin-bottom:var(--space-md);color:var(--orange-secondary);">🏪 Merchant Partnership</div>
        <h1 style="color:#fff;margin-bottom:var(--space-md);">Reach 10,000+ Senior Customers</h1>
        <p style="font-size:19px;opacity:.85;margin-bottom:var(--space-xl);">List your deals on SilverDeals MY and connect with Malaysia's most loyal and growing demographic — active seniors aged 50+.</p>
        <div style="display:flex;flex-wrap:wrap;gap:var(--space-xl);">
          <?php foreach (['10,000+'=>'Senior Members','500+'=>'Merchant Partners','RM 0'=>'Setup Cost'] as $num=>$label): ?>
            <div><div style="font-size:28px;font-weight:800;color:var(--orange-secondary);"><?= $num ?></div><div style="font-size:14px;opacity:.7;"><?= $label ?></div></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:var(--radius-xl);padding:var(--space-xl);">
          <h3 style="color:#fff;margin-bottom:var(--space-lg);">Why Partner With Us?</h3>
          <?php
          $benefits = [
            ['🎯','Targeted Senior Audience','Reach verified Malaysians 50+ who are actively spending.'],
            ['💰','Pay-Per-Redemption','Only pay commission when a deal is actually redeemed.'],
            ['📊','Real-Time Dashboard','Track redemptions, commissions, and performance live.'],
            ['🤝','Dedicated Support','Our team helps you craft deals that convert.'],
          ];
          foreach ($benefits as [$icon,$title,$desc]):
          ?>
            <div style="display:flex;gap:var(--space-md);margin-bottom:var(--space-lg);">
              <div style="font-size:28px;flex-shrink:0;"><?= $icon ?></div>
              <div><div style="font-weight:700;color:#fff;margin-bottom:2px;"><?= $title ?></div><div style="font-size:14px;opacity:.7;"><?= $desc ?></div></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Registration Form -->
<section class="section section--bg">
  <div class="container container--narrow">
    <div class="section-header">
      <div class="section-header__tag">Get Started Free</div>
      <h2 class="section-header__title">Apply to Become a Merchant</h2>
      <p class="section-header__subtitle">Fill in the form below. Our team reviews all applications within 1–2 business days.</p>
    </div>

    <?php if (!empty($errors['general'])): ?><div class="alert alert--error"><span class="alert__icon">✕</span><span><?= e($errors['general']) ?></span></div><?php endif; ?>

    <div class="card" style="padding:var(--space-2xl);">
      <form method="POST" novalidate>
        <?= csrf_field() ?>

        <h4 style="margin-bottom:var(--space-lg);padding-bottom:var(--space-md);border-bottom:1px solid var(--border-light);">🏢 Business Information</h4>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="business_name">Business / Brand Name <span class="required">*</span></label>
            <input type="text" id="business_name" name="business_name" class="form-control <?= !empty($errors['business_name'])?'form-control--error':'' ?>"
                   value="<?= e($values['business_name']) ?>" placeholder="e.g. Healthy Bites KL" required>
            <?php if (!empty($errors['business_name'])): ?><div class="form-error"><?= e($errors['business_name']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label" for="category_id">Business Category</label>
            <select id="category_id" name="category_id" class="form-control">
              <option value="">— Select Category —</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $values['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= e($cat['icon'].' '.$cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="ssm_number">SSM Registration No. <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
            <input type="text" id="ssm_number" name="ssm_number" class="form-control" value="" placeholder="e.g. 202301012345">
          </div>
          <div class="form-group">
            <label class="form-label" for="state">Primary State</label>
            <select id="state" name="state" class="form-control">
              <option value="">— Select State —</option>
              <?php foreach ($states_my as $s): ?><option value="<?= e($s) ?>" <?= $values['state']===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="description">Tell us about your business <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
          <textarea id="description" name="description" class="form-control" rows="3" placeholder="What do you offer? Why are seniors a key audience for you?" data-max-chars="500"></textarea>
          <div class="form-hint"><span id="desc_counter">0/500</span> characters</div>
        </div>

        <h4 style="margin:var(--space-xl) 0 var(--space-lg);padding-bottom:var(--space-md);border-bottom:1px solid var(--border-light);">👤 Account Owner</h4>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="owner_name">Owner / PIC Full Name <span class="required">*</span></label>
            <input type="text" id="owner_name" name="owner_name" class="form-control <?= !empty($errors['name'])?'form-control--error':'' ?>"
                   value="<?= e($values['name']) ?>" placeholder="Full name" autocomplete="name" required>
            <?php if (!empty($errors['name'])): ?><div class="form-error"><?= e($errors['name']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label" for="phone">Contact Phone <span class="required">*</span></label>
            <input type="tel" id="phone" name="phone" class="form-control <?= !empty($errors['phone'])?'form-control--error':'' ?>"
                   value="<?= e($values['phone']) ?>" placeholder="60123456789" autocomplete="tel" required>
            <?php if (!empty($errors['phone'])): ?><div class="form-error"><?= e($errors['phone']) ?></div><?php endif; ?>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="email">Business Email <span class="required">*</span></label>
          <input type="email" id="email" name="email" class="form-control <?= !empty($errors['email'])?'form-control--error':'' ?>"
                 value="<?= e($values['email']) ?>" placeholder="owner@yourbusiness.com" autocomplete="email" required>
          <?php if (!empty($errors['email'])): ?><div class="form-error"><?= e($errors['email']) ?></div><?php endif; ?>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="password">Login Password <span class="required">*</span></label>
            <div style="position:relative;">
              <input type="password" id="password" name="password" class="form-control <?= !empty($errors['password'])?'form-control--error':'' ?>"
                     placeholder="Min 8 characters" autocomplete="new-password" required>
              <button type="button" data-toggle-password="password" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;">👁</button>
            </div>
            <?php if (!empty($errors['password'])): ?><div class="form-error"><?= e($errors['password']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label" for="confirm_password">Confirm Password <span class="required">*</span></label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-enter password" autocomplete="new-password" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-check">
            <input type="checkbox" name="agree" <?= isset($_POST['agree'])?'checked':'' ?>>
            <span>I agree to the <a href="/public/terms.php" target="_blank">Merchant Terms</a> and <a href="/public/privacy.php" target="_blank">Privacy Policy</a>. I authorise SilverDeals MY to list my approved deals on the platform. <span class="required">*</span></span>
          </label>
          <?php if (!empty($errors['agree'])): ?><div class="form-error"><?= e($errors['agree']) ?></div><?php endif; ?>
        </div>

        <button type="submit" class="btn btn--primary btn--full btn--lg">🏪 Submit Merchant Application</button>
      </form>

      <p class="text-center" style="margin-top:var(--space-lg);font-size:15px;color:var(--text-muted);">
        Already registered? <a href="/merchant/login.php" style="font-weight:600;">Login to Merchant Portal</a>
      </p>
    </div>
  </div>
</section>

<script>
const ta = document.getElementById('description'), ctr = document.getElementById('desc_counter');
if (ta && ctr) { ta.addEventListener('input', () => ctr.textContent = ta.value.length + '/500'); }
</script>

<?php include __DIR__ . '/../inc/public_footer.php'; ?>
