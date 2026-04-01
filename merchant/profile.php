<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
auth_require(ROLE_MERCHANT);

$user = auth_user();
$merchant = null;
try {
    $stmt = db()->prepare("SELECT * FROM merchants WHERE user_id=? LIMIT 1");
    $stmt->execute([$user['id']]);
    $merchant = $stmt->fetch();
} catch (PDOException $e) { error_log('[Merchant profile load] '.$e->getMessage()); }

if (!$merchant) redirect('/merchant/dashboard.php');

$page_title = 'Merchant Profile';
$active_nav = 'profile';

$errors  = [];
$success = '';

// ─── POST: Update profile ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $action = $_POST['action'] ?? 'profile';

    if ($action === 'profile') {
        $business_name = trim($_POST['business_name'] ?? '');
        $phone         = trim($_POST['phone'] ?? '');
        $website       = trim($_POST['website'] ?? '');
        $description   = trim($_POST['description'] ?? '');
        $address       = trim($_POST['address'] ?? '');
        $state         = trim($_POST['state'] ?? '');
        $postcode      = trim($_POST['postcode'] ?? '');
        $category      = trim($_POST['category'] ?? '');

        if (!$business_name) $errors[] = 'Business name is required.';
        if (strlen($description) > 1000) $errors[] = 'Description must be under 1000 characters.';

        if (!$errors) {
            // Logo upload
            $logo_path = $merchant['logo'];
            if (!empty($_FILES['logo']['tmp_name'])) {
                $mime = mime_content_type($_FILES['logo']['tmp_name']);
                if (!in_array($mime, ALLOWED_IMG_TYPES, true)) {
                    $errors[] = 'Logo must be a JPEG, PNG, or WebP image.';
                } elseif ($_FILES['logo']['size'] > MAX_UPLOAD_BYTES) {
                    $errors[] = 'Logo must be under 5 MB.';
                } else {
                    $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime];
                    $dir = UPLOAD_DIR . 'logos/';
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    $filename = 'merchant_' . $merchant['id'] . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $dir . $filename)) {
                        $logo_path = '/assets/img/uploads/logos/' . $filename;
                    }
                }
            }

            if (!$errors) {
                try {
                    db()->prepare("
                        UPDATE merchants SET
                            business_name=?,phone=?,website=?,description=?,
                            address=?,state=?,postcode=?,category=?,logo=?,
                            updated_at=NOW()
                        WHERE id=?
                    ")->execute([
                        $business_name,$phone,$website,$description,
                        $address,$state,$postcode,$category,$logo_path,
                        $merchant['id']
                    ]);
                    // Re-fetch
                    $stmt = db()->prepare("SELECT * FROM merchants WHERE id=? LIMIT 1");
                    $stmt->execute([$merchant['id']]);
                    $merchant = $stmt->fetch();

                    db()->prepare("INSERT INTO audit_logs(user_id,action,target_type,target_id,ip_address) VALUES(?,'merchant.update_profile','merchant',?,?)")
                        ->execute([$user['id'], $merchant['id'], $_SERVER['REMOTE_ADDR'] ?? null]);

                    $success = 'Profile updated successfully.';
                } catch (PDOException $e) {
                    error_log('[Merchant profile save] '.$e->getMessage());
                    $errors[] = 'Failed to update profile. Please try again.';
                }
            }
        }
    } elseif ($action === 'branch_add') {
        $branch_name    = trim($_POST['branch_name'] ?? '');
        $branch_address = trim($_POST['branch_address'] ?? '');
        $branch_phone   = trim($_POST['branch_phone'] ?? '');
        $branch_state   = trim($_POST['branch_state'] ?? '');

        if (!$branch_name) { $errors[] = 'Branch name is required.'; }
        else {
            try {
                db()->prepare("INSERT INTO merchant_branches(merchant_id,name,address,phone,state,is_primary,created_at) VALUES(?,?,?,?,?,0,NOW())")
                    ->execute([$merchant['id'], $branch_name, $branch_address, $branch_phone, $branch_state]);
                $success = 'Branch added.';
            } catch (PDOException $e) {
                error_log('[Merchant add branch] '.$e->getMessage());
                $errors[] = 'Failed to add branch.';
            }
        }
    } elseif ($action === 'branch_delete') {
        $branch_id = (int)($_POST['branch_id'] ?? 0);
        try {
            db()->prepare("DELETE FROM merchant_branches WHERE id=? AND merchant_id=? AND is_primary=0")
                ->execute([$branch_id, $merchant['id']]);
            $success = 'Branch removed.';
        } catch (PDOException $e) { $errors[] = 'Failed to remove branch.'; }
    }
}

// ─── Load branches ────────────────────────────────────────────────────────
$branches = [];
try {
    $st = db()->prepare("SELECT * FROM merchant_branches WHERE merchant_id=? ORDER BY is_primary DESC, name ASC");
    $st->execute([$merchant['id']]);
    $branches = $st->fetchAll();
} catch (PDOException) {}

$malaysian_states = ['Johor','Kedah','Kelantan','Kuala Lumpur','Labuan','Melaka','Negeri Sembilan','Pahang','Penang','Perak','Perlis','Putrajaya','Sabah','Sarawak','Selangor','Terengganu'];

include __DIR__ . '/../inc/merchant_layout.php';
?>

<div style="margin-bottom:var(--space-xl);">
  <h2 style="margin-bottom:var(--space-xs);">Merchant Profile</h2>
  <p style="color:var(--text-muted);">Update your business information, logo, and branch locations.</p>
</div>

<?php if ($success): ?>
  <div class="alert alert--success" style="margin-bottom:var(--space-lg);"><span class="alert__icon">✓</span><span><?= e($success) ?></span></div>
<?php endif; ?>
<?php if ($errors): ?>
  <div class="alert alert--error" style="margin-bottom:var(--space-lg);">
    <span class="alert__icon">✕</span>
    <div><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
  </div>
<?php endif; ?>

<div class="grid grid-2" style="align-items:start;gap:var(--space-xl);">

  <!-- Profile form -->
  <div>
    <div class="card" style="padding:var(--space-xl);">
      <h4 style="margin-bottom:var(--space-xl);">Business Information</h4>

      <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="profile">

        <!-- Logo -->
        <div class="form-group">
          <label class="form-label">Business Logo</label>
          <div style="display:flex;align-items:center;gap:var(--space-lg);margin-bottom:var(--space-sm);">
            <?php if ($merchant['logo']): ?>
              <img src="<?= e($merchant['logo']) ?>" alt="" style="width:80px;height:80px;object-fit:cover;border-radius:var(--radius-md);border:2px solid var(--orange-primary);">
            <?php else: ?>
              <div style="width:80px;height:80px;background:var(--orange-bg);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;font-size:32px;border:2px dashed var(--orange-primary);">🏪</div>
            <?php endif; ?>
            <div>
              <input type="file" name="logo" id="logo" accept="image/jpeg,image/png,image/webp" class="form-control" style="font-size:14px;">
              <div class="form-hint">JPEG, PNG, or WebP. Max 5 MB.</div>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="business_name">Business Name <span style="color:var(--error);">*</span></label>
          <input type="text" id="business_name" name="business_name" class="form-control" value="<?= e($merchant['business_name']) ?>" required>
        </div>

        <div class="grid grid-2" style="gap:var(--space-md);">
          <div class="form-group">
            <label class="form-label" for="phone">Phone Number</label>
            <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($merchant['phone'] ?? '') ?>" placeholder="+60 12-345 6789">
          </div>
          <div class="form-group">
            <label class="form-label" for="website">Website</label>
            <input type="url" id="website" name="website" class="form-control" value="<?= e($merchant['website'] ?? '') ?>" placeholder="https://…">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="category">Business Category</label>
          <input type="text" id="category" name="category" class="form-control" value="<?= e($merchant['category'] ?? '') ?>" placeholder="e.g. Healthcare, Dining, Retail">
        </div>

        <div class="form-group">
          <label class="form-label" for="description">Business Description</label>
          <textarea id="description" name="description" class="form-control" rows="4" placeholder="Tell members about your business…"><?= e($merchant['description'] ?? '') ?></textarea>
          <div class="form-hint">Max 1,000 characters.</div>
        </div>

        <div class="form-group">
          <label class="form-label" for="address">Main Address</label>
          <input type="text" id="address" name="address" class="form-control" value="<?= e($merchant['address'] ?? '') ?>">
        </div>

        <div class="grid grid-2" style="gap:var(--space-md);">
          <div class="form-group">
            <label class="form-label" for="state">State</label>
            <select id="state" name="state" class="form-control">
              <option value="">— Select state —</option>
              <?php foreach ($malaysian_states as $s): ?>
                <option value="<?= $s ?>" <?= ($merchant['state'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="postcode">Postcode</label>
            <input type="text" id="postcode" name="postcode" class="form-control" value="<?= e($merchant['postcode'] ?? '') ?>" maxlength="5" placeholder="50000">
          </div>
        </div>

        <button type="submit" class="btn btn--primary btn--full btn--lg">Save Changes</button>
      </form>
    </div>
  </div>

  <!-- Branches -->
  <div>
    <div class="card" style="padding:var(--space-xl);margin-bottom:var(--space-lg);">
      <h4 style="margin-bottom:var(--space-lg);">Branch Locations</h4>

      <?php if (!empty($branches)): ?>
        <div style="display:flex;flex-direction:column;gap:var(--space-md);margin-bottom:var(--space-xl);">
          <?php foreach ($branches as $b): ?>
            <div style="padding:var(--space-md);border:1px solid var(--border-color);border-radius:var(--radius-md);<?= $b['is_primary'] ? 'border-color:var(--orange-primary);background:var(--orange-bg);' : '' ?>">
              <div style="display:flex;justify-content:space-between;align-items:start;">
                <div>
                  <div style="font-weight:700;font-size:15px;"><?= e($b['name']) ?> <?= $b['is_primary'] ? '<span class="badge badge--orange" style="font-size:10px;">Main</span>' : '' ?></div>
                  <?php if ($b['address']): ?><div style="font-size:13px;color:var(--text-muted);margin-top:2px;"><?= e($b['address']) ?></div><?php endif; ?>
                  <?php if ($b['state']): ?><div style="font-size:12px;color:var(--text-muted);"><?= e($b['state']) ?></div><?php endif; ?>
                  <?php if ($b['phone']): ?><div style="font-size:13px;margin-top:4px;">📞 <?= e($b['phone']) ?></div><?php endif; ?>
                </div>
                <?php if (!$b['is_primary']): ?>
                  <form method="POST" style="margin:0;" onsubmit="return confirm('Remove this branch?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="branch_delete">
                    <input type="hidden" name="branch_id" value="<?= $b['id'] ?>">
                    <button class="btn btn--muted btn--sm" style="font-size:12px;">✕</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Add branch form -->
      <details <?= count($branches) === 0 ? 'open' : '' ?>>
        <summary class="btn btn--muted btn--full" style="cursor:pointer;list-style:none;margin-bottom:var(--space-lg);">+ Add Branch Location</summary>
        <form method="POST" style="margin-top:var(--space-md);">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="branch_add">
          <div class="form-group">
            <label class="form-label">Branch Name <span style="color:var(--error);">*</span></label>
            <input type="text" name="branch_name" class="form-control" placeholder="e.g. KL Central Outlet" required>
          </div>
          <div class="form-group">
            <label class="form-label">Address</label>
            <input type="text" name="branch_address" class="form-control" placeholder="Street address">
          </div>
          <div class="grid grid-2" style="gap:var(--space-md);">
            <div class="form-group">
              <label class="form-label">State</label>
              <select name="branch_state" class="form-control">
                <option value="">— Select —</option>
                <?php foreach ($malaysian_states as $s): ?>
                  <option value="<?= $s ?>"><?= $s ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Phone</label>
              <input type="tel" name="branch_phone" class="form-control" placeholder="+60…">
            </div>
          </div>
          <button type="submit" class="btn btn--primary btn--full">Add Branch</button>
        </form>
      </details>
    </div>

    <!-- Account info (read-only) -->
    <div class="card" style="padding:var(--space-lg);">
      <h5 style="margin-bottom:var(--space-md);">Account Information</h5>
      <div style="display:flex;flex-direction:column;gap:var(--space-sm);">
        <div style="display:flex;justify-content:space-between;font-size:14px;">
          <span style="color:var(--text-muted);">Email</span>
          <span><?= e($user['email']) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:14px;">
          <span style="color:var(--text-muted);">Status</span>
          <span class="badge badge--<?= $merchant['status']==='active'?'success':'warning' ?>"><?= ucfirst($merchant['status']) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:14px;">
          <span style="color:var(--text-muted);">Commission Rate</span>
          <span style="font-weight:600;"><?= (float)$merchant['commission_pct'] ?>%</span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:14px;">
          <span style="color:var(--text-muted);">Member Since</span>
          <span><?= date('d M Y', strtotime($merchant['created_at'])) ?></span>
        </div>
      </div>
      <div style="margin-top:var(--space-md);">
        <a href="/merchant/change-password.php" class="btn btn--muted btn--sm">Change Password</a>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../inc/merchant_layout_end.php'; ?>
