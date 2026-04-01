<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/points.php';
auth_require(ROLE_MEMBER);

$user       = auth_user();
$page_title = 'Senior Verification';
$active_nav = 'profile';
$errors     = [];

// Load existing verification
$verif = null;
try {
    $stmt = db()->prepare("SELECT * FROM senior_verifications WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 1");
    $stmt->execute([$user['id']]);
    $verif = $stmt->fetch();
} catch (PDOException $e) { error_log('[Verif load] ' . $e->getMessage()); }

// If already approved redirect to card
if ($verif && $verif['status'] === 'approved') {
    auth_set_flash('success', 'Your account is already verified!');
    redirect('/member/membership_card.php');
}

// ─── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();

    if ($verif && $verif['status'] === 'pending') {
        $errors['general'] = 'Your verification is already under review. Please wait for admin to process it.';
    } else {
        $docType  = in_array($_POST['document_type'] ?? '', ['ic','passport','other']) ? $_POST['document_type'] : 'ic';
        $birthYear = (int)($_POST['birth_year'] ?? 0);

        if ($birthYear < 1900 || $birthYear > (int)date('Y') - 50) {
            $errors['birth_year'] = 'Please enter a valid birth year (you must be at least 50).';
        }

        // Upload handler
        $uploadedPaths = [];
        $requiredFiles = ['document_front'];
        foreach (['document_front','document_back','selfie'] as $field) {
            if (empty($_FILES[$field]['name'])) {
                if (in_array($field, $requiredFiles)) $errors[$field] = 'This document is required.';
                continue;
            }
            $file = $_FILES[$field];
            if ($file['error'] !== UPLOAD_ERR_OK) { $errors[$field] = 'Upload error. Please try again.'; continue; }
            if ($file['size'] > MAX_UPLOAD_BYTES)  { $errors[$field] = 'File too large (max 5 MB).'; continue; }
            if (!in_array(mime_content_type($file['tmp_name']), ALLOWED_IMG_TYPES, true)) {
                $errors[$field] = 'Only JPG, PNG or WebP allowed.'; continue;
            }

            $dir = UPLOAD_DIR . 'verifications/' . $user['id'] . '/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $field . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
                $uploadedPaths[$field] = '/assets/img/uploads/verifications/' . $user['id'] . '/' . $filename;
            } else {
                $errors[$field] = 'Failed to save file. Please try again.';
            }
        }

        if (empty($errors)) {
            try {
                $pdo = db();
                // Invalidate any old rejected submission
                $pdo->prepare("DELETE FROM senior_verifications WHERE user_id = ? AND status = 'rejected'")
                    ->execute([$user['id']]);

                $pdo->prepare("
                    INSERT INTO senior_verifications
                        (user_id, document_type, document_front, document_back, selfie, birth_year, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending')
                ")->execute([
                    $user['id'],
                    $docType,
                    $uploadedPaths['document_front'] ?? null,
                    $uploadedPaths['document_back']  ?? null,
                    $uploadedPaths['selfie']          ?? null,
                    $birthYear,
                ]);

                // Audit log
                db()->prepare("INSERT INTO audit_logs (user_id, action, target_type, target_id, ip_address) VALUES (?, 'member.submit_verification', 'senior_verification', LAST_INSERT_ID(), ?)")
                    ->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? null]);

                // Notify admin (notification to all admins)
                $admins = db()->query("SELECT id FROM users WHERE role IN ('admin','superadmin') AND status='active'")->fetchAll();
                foreach ($admins as $admin) {
                    notify((int)$admin['id'],
                        'New Verification Submitted',
                        'Member ' . $user['name'] . ' has submitted senior verification documents.',
                        'info', (int)$user['id'], 'user');
                }

                auth_set_flash('success', 'Verification submitted successfully! Our team will review it within 1–2 business days. We\'ll notify you once approved.');
                redirect('/member/profile.php');
            } catch (PDOException $e) {
                error_log('[Verif submit] ' . $e->getMessage());
                $errors['general'] = 'Submission failed. Please try again.';
            }
        }
    }
}

include __DIR__ . '/../inc/member_layout.php';
?>

<div style="max-width:680px;margin:0 auto;">

  <!-- Status banner -->
  <?php if ($verif && $verif['status'] === 'pending'): ?>
    <div class="alert alert--warning" style="margin-bottom:var(--space-xl);">
      <span class="alert__icon">⏳</span>
      <div>
        <strong>Verification Under Review</strong><br>
        <span style="font-size:15px;">Your documents were submitted on <?= date('d M Y', strtotime($verif['submitted_at'])) ?>. Our team will review within 1–2 business days.</span>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($errors['general'])): ?>
    <div class="alert alert--error"><span class="alert__icon">✕</span><span><?= e($errors['general']) ?></span></div>
  <?php endif; ?>

  <!-- What & Why card -->
  <div class="card" style="padding:var(--space-xl);margin-bottom:var(--space-xl);background:linear-gradient(135deg,var(--orange-bg),var(--white));">
    <h3 style="margin-bottom:var(--space-md);">🏅 Why Verify Your Senior Status?</h3>
    <div class="grid grid-2" style="gap:var(--space-md);">
      <?php
      $benefits = [
        ['🃏','Activate your digital QR membership card'],
        ['🎁','Unlock exclusive members-only deals'],
        ['💰','Receive ' . POINTS_VERIFICATION_BONUS . ' + ' . POINTS_WELCOME_BONUS . ' bonus SilverPoints'],
        ['🤝','Enable referral rewards for you and friends'],
        ['⭐','Access to premium merchant offers'],
        ['📱','Full member portal features'],
      ];
      foreach ($benefits as [$icon,$text]):
      ?>
        <div style="display:flex;align-items:flex-start;gap:var(--space-sm);font-size:15px;">
          <span><?= $icon ?></span><span><?= e($text) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Form -->
  <?php if (!$verif || $verif['status'] === 'rejected'): ?>
  <div class="card" style="padding:var(--space-xl);">
    <h3 style="margin-bottom:var(--space-md);padding-bottom:var(--space-md);border-bottom:1px solid var(--border-light);">
      📋 Submit Verification Documents
    </h3>

    <?php if ($verif && $verif['status'] === 'rejected'): ?>
      <div class="alert alert--error" style="margin-bottom:var(--space-xl);">
        <span class="alert__icon">✕</span>
        <div>
          <strong>Previous submission was rejected.</strong><br>
          <?php if ($verif['reject_reason']): ?>
            <span style="font-size:15px;">Reason: <?= e($verif['reject_reason']) ?></span>
          <?php endif; ?>
          <br><span style="font-size:14px;">Please resubmit with correct documents.</span>
        </div>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>

      <!-- Document type -->
      <div class="form-group">
        <label class="form-label">Document Type <span class="required">*</span></label>
        <div style="display:flex;gap:var(--space-lg);">
          <?php foreach (['ic'=>'Malaysian IC (MyKad)','passport'=>'Passport','other'=>'Other ID'] as $val=>$label): ?>
            <label class="form-check">
              <input type="radio" name="document_type" value="<?= $val ?>" <?= (($_POST['document_type'] ?? 'ic') === $val) ? 'checked' : '' ?>>
              <span><?= $label ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Birth year -->
      <div class="form-group">
        <label class="form-label" for="birth_year">Year of Birth <span class="required">*</span></label>
        <input type="number" id="birth_year" name="birth_year"
               class="form-control <?= !empty($errors['birth_year']) ? 'form-control--error' : '' ?>"
               value="<?= e((string)($_POST['birth_year'] ?? '')) ?>"
               placeholder="e.g. 1960"
               min="1920" max="<?= date('Y') - 50 ?>">
        <div class="form-hint">You must be at least 50 years old to qualify.</div>
        <?php if (!empty($errors['birth_year'])): ?><div class="form-error"><?= e($errors['birth_year']) ?></div><?php endif; ?>
      </div>

      <hr class="divider">

      <!-- Document Front -->
      <div class="form-group">
        <label class="form-label" for="document_front">
          IC / Passport — Front Side <span class="required">*</span>
        </label>
        <div class="upload-zone <?= !empty($errors['document_front']) ? 'upload-zone--error' : '' ?>"
             style="border:2px dashed var(--border-light);border-radius:var(--radius-md);padding:var(--space-xl);text-align:center;cursor:pointer;transition:border-color .2s;"
             onclick="document.getElementById('document_front').click()"
             ondragover="event.preventDefault();this.style.borderColor='var(--orange-primary)'"
             ondragleave="this.style.borderColor='var(--border-light)'"
             ondrop="handleDrop(event,'document_front','prev_front')">
          <div style="font-size:36px;margin-bottom:var(--space-sm);">📄</div>
          <div style="font-weight:600;margin-bottom:4px;">Click or drag to upload front of IC / Passport</div>
          <div style="font-size:13px;color:var(--text-muted);">JPG, PNG, WebP — max 5 MB</div>
          <img id="prev_front" src="" alt="" style="display:none;max-height:120px;margin:var(--space-md) auto 0;border-radius:var(--radius-sm);">
        </div>
        <input type="file" id="document_front" name="document_front" accept="image/*" style="display:none;" onchange="previewFile(this,'prev_front')">
        <?php if (!empty($errors['document_front'])): ?><div class="form-error"><?= e($errors['document_front']) ?></div><?php endif; ?>
      </div>

      <!-- Document Back -->
      <div class="form-group">
        <label class="form-label" for="document_back">
          IC / Passport — Back Side <span style="font-weight:400;color:var(--text-muted);">(optional but recommended)</span>
        </label>
        <div style="border:2px dashed var(--border-light);border-radius:var(--radius-md);padding:var(--space-xl);text-align:center;cursor:pointer;transition:border-color .2s;"
             onclick="document.getElementById('document_back').click()">
          <div style="font-size:36px;margin-bottom:var(--space-sm);">📄</div>
          <div style="font-weight:600;margin-bottom:4px;">Click to upload back of IC / Passport</div>
          <div style="font-size:13px;color:var(--text-muted);">JPG, PNG, WebP — max 5 MB</div>
          <img id="prev_back" src="" alt="" style="display:none;max-height:120px;margin:var(--space-md) auto 0;border-radius:var(--radius-sm);">
        </div>
        <input type="file" id="document_back" name="document_back" accept="image/*" style="display:none;" onchange="previewFile(this,'prev_back')">
        <?php if (!empty($errors['document_back'])): ?><div class="form-error"><?= e($errors['document_back']) ?></div><?php endif; ?>
      </div>

      <!-- Selfie -->
      <div class="form-group">
        <label class="form-label" for="selfie">
          Selfie Holding Your IC / Passport <span style="font-weight:400;color:var(--text-muted);">(optional)</span>
        </label>
        <div style="border:2px dashed var(--border-light);border-radius:var(--radius-md);padding:var(--space-xl);text-align:center;cursor:pointer;transition:border-color .2s;"
             onclick="document.getElementById('selfie').click()">
          <div style="font-size:36px;margin-bottom:var(--space-sm);">🤳</div>
          <div style="font-weight:600;margin-bottom:4px;">Take a selfie with your ID visible</div>
          <div style="font-size:13px;color:var(--text-muted);">Helps speed up approval. JPG, PNG, WebP — max 5 MB</div>
          <img id="prev_selfie" src="" alt="" style="display:none;max-height:120px;margin:var(--space-md) auto 0;border-radius:var(--radius-sm);">
        </div>
        <input type="file" id="selfie" name="selfie" accept="image/*" style="display:none;" onchange="previewFile(this,'prev_selfie')">
      </div>

      <!-- Privacy note -->
      <div style="background:var(--info-bg);border-radius:var(--radius-md);padding:var(--space-md);margin-bottom:var(--space-lg);font-size:14px;color:#1E40AF;">
        🔒 <strong>Your privacy is protected.</strong> Documents are stored securely and only reviewed by SilverDeals MY staff for verification purposes. They will not be shared with third parties.
      </div>

      <button type="submit" class="btn btn--primary btn--full btn--lg">
        📤 Submit for Verification
      </button>
    </form>
  </div>
  <?php else: ?>
    <!-- Already submitted / approved state -->
    <div class="empty-state">
      <div class="empty-state__icon">⏳</div>
      <h3 class="empty-state__title">Documents Submitted</h3>
      <p class="empty-state__text">We're reviewing your documents. You'll receive a notification once approved.</p>
      <a href="/member/dashboard.php" class="btn btn--primary">Back to Dashboard</a>
    </div>
  <?php endif; ?>
</div>

<script>
function previewFile(input, previewId) {
  const preview = document.getElementById(previewId);
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
    reader.readAsDataURL(input.files[0]);
  }
}
function handleDrop(e, inputId, previewId) {
  e.preventDefault();
  const input = document.getElementById(inputId);
  if (e.dataTransfer.files.length) {
    const dt = new DataTransfer();
    dt.items.add(e.dataTransfer.files[0]);
    input.files = dt.files;
    previewFile(input, previewId);
  }
}
</script>

<?php include __DIR__ . '/../inc/member_layout_end.php'; ?>
