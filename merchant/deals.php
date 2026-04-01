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
} catch (PDOException $e) { error_log('[Merchant deals] '.$e->getMessage()); }

if (!$merchant) { auth_set_flash('error','Merchant profile not found.'); redirect('/merchant/profile.php'); }

$mid    = $merchant['id'];
$action = $_GET['action'] ?? 'list';
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

// ─── Load categories ───────────────────────────────────────────────────────
$categories = [];
try { $categories = db()->query("SELECT id,name,icon FROM deal_categories WHERE is_active=1 ORDER BY sort_order")->fetchAll(); } catch (PDOException) {}

// ─── POST: save deal ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['form_action']??'', ['create_deal','update_deal'])) {
    csrf_abort();
    $isUpdate   = ($_POST['form_action'] === 'update_deal');
    $dealId     = $isUpdate ? (int)($_POST['deal_id'] ?? 0) : 0;
    $errors     = [];

    $title      = trim($_POST['title']        ?? '');
    $shortDesc  = trim($_POST['short_desc']   ?? '');
    $desc       = trim($_POST['description']  ?? '');
    $catId      = (int)($_POST['category_id'] ?? 0);
    $dealType   = in_array($_POST['deal_type']??'', ['discount','voucher','freebie','event','offer']) ? $_POST['deal_type'] : 'discount';
    $origPrice  = strlen($_POST['original_price']??'') ? (float)$_POST['original_price'] : null;
    $dealPrice  = strlen($_POST['deal_price']??'')   ? (float)$_POST['deal_price']   : null;
    $discPct    = strlen($_POST['discount_pct']??'') ? (float)$_POST['discount_pct'] : null;
    $maxRedeem  = (int)($_POST['max_redemptions'] ?? -1);
    $ptReq      = (int)($_POST['points_required'] ?? 0);
    $memberOnly = isset($_POST['is_members_only']) ? 1 : 0;
    $featured   = ($merchant['status']==='active') ? (isset($_POST['is_featured'])?1:0) : 0;
    $terms      = trim($_POST['terms_conditions'] ?? '');
    $validFrom  = $_POST['valid_from']  ?: null;
    $validUntil = $_POST['valid_until'] ?: null;
    $statusReq  = ($merchant['status']==='active') ? 'pending' : 'draft';
    if ($isUpdate) $statusReq = $_POST['status'] ?? 'pending';

    if (!$title) $errors['title'] = 'Deal title is required.';
    if ($validUntil && $validFrom && $validUntil < $validFrom) $errors['valid_until'] = 'Expiry must be after start date.';

    if (empty($errors)) {
        try {
            $pdo = db();
            if ($isUpdate) {
                // verify ownership
                $stmt = $pdo->prepare("SELECT id FROM deals WHERE id=? AND merchant_id=?");
                $stmt->execute([$dealId, $mid]);
                if (!$stmt->fetch()) { auth_set_flash('error','Deal not found.'); redirect('/merchant/deals.php'); }

                $pdo->prepare("
                    UPDATE deals SET title=?,short_desc=?,description=?,category_id=?,deal_type=?,
                        original_price=?,deal_price=?,discount_pct=?,max_redemptions=?,points_required=?,
                        is_members_only=?,is_featured=?,terms_conditions=?,valid_from=?,valid_until=?,
                        status=?,updated_at=NOW()
                    WHERE id=? AND merchant_id=?
                ")->execute([$title,$shortDesc,$desc,$catId?:null,$dealType,$origPrice,$dealPrice,$discPct,
                              $maxRedeem,$ptReq,$memberOnly,$featured,$terms,$validFrom,$validUntil,
                              $statusReq,$dealId,$mid]);
                auth_set_flash('success','Deal updated successfully!');
            } else {
                $slug = slugify($title).'-'.time();
                $pdo->prepare("
                    INSERT INTO deals (merchant_id,category_id,title,slug,short_desc,description,
                        deal_type,original_price,deal_price,discount_pct,max_redemptions,points_required,
                        is_members_only,is_featured,terms_conditions,valid_from,valid_until,status)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ")->execute([$mid,$catId?:null,$title,$slug,$shortDesc,$desc,$dealType,$origPrice,$dealPrice,
                              $discPct,$maxRedeem,$ptReq,$memberOnly,$featured,$terms,$validFrom,$validUntil,$statusReq]);
                $dealId = (int)$pdo->lastInsertId();

                // Handle primary image upload
                if (!empty($_FILES['image']['name']) && $_FILES['image']['error']===UPLOAD_ERR_OK) {
                    $file = $_FILES['image'];
                    if ($file['size'] <= MAX_UPLOAD_BYTES && in_array(mime_content_type($file['tmp_name']), ALLOWED_IMG_TYPES)) {
                        $dir = UPLOAD_DIR.'deals/'; if(!is_dir($dir)) mkdir($dir,0755,true);
                        $ext = pathinfo($file['name'],PATHINFO_EXTENSION);
                        $fn  = 'deal_'.$dealId.'_'.time().'.'.$ext;
                        if (move_uploaded_file($file['tmp_name'],$dir.$fn)) {
                            $pdo->prepare("INSERT INTO deal_images(deal_id,image_path,is_primary) VALUES(?,?,1)")
                                ->execute([$dealId,'/assets/img/uploads/deals/'.$fn]);
                        }
                    }
                }

                $pdo->prepare("INSERT INTO audit_logs(user_id,action,target_type,target_id,ip_address) VALUES(?,'merchant.create_deal','deal',?,?)")
                    ->execute([$user['id'],$dealId,$_SERVER['REMOTE_ADDR']??null]);

                auth_set_flash('success',$statusReq==='draft' ? 'Deal saved as draft.' : 'Deal submitted for review!');
            }
            redirect('/merchant/deals.php');
        } catch (PDOException $e) {
            error_log('[Merchant save deal] '.$e->getMessage());
            $errors['general'] = 'Save failed. Please try again.';
        }
    }
    $action = $isUpdate ? 'edit' : 'create';
}

// ─── POST: delete/toggle ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $act  = $_POST['action'] ?? '';
    $dId  = (int)($_POST['deal_id'] ?? 0);
    if ($dId) {
        try {
            $stmt = db()->prepare("SELECT id,status FROM deals WHERE id=? AND merchant_id=?");
            $stmt->execute([$dId,$mid]);
            $row = $stmt->fetch();
            if ($row) {
                if ($act==='delete') {
                    db()->prepare("UPDATE deals SET deleted_at=NOW(),status='expired' WHERE id=?")->execute([$dId]);
                    auth_set_flash('success','Deal removed.');
                } elseif ($act==='toggle_pause') {
                    $ns = $row['status']==='active' ? 'paused' : 'pending';
                    db()->prepare("UPDATE deals SET status=? WHERE id=?")->execute([$ns,$dId]);
                    auth_set_flash('success','Deal status updated.');
                }
            }
        } catch (PDOException $e) { error_log('[Deal toggle] '.$e->getMessage()); }
    }
    redirect('/merchant/deals.php');
}

// ─── Load deal for editing ─────────────────────────────────────────────────
$editDeal = null;
if ($action==='edit' && $editId) {
    try {
        $stmt = db()->prepare("SELECT d.*,di.image_path AS primary_image FROM deals d LEFT JOIN deal_images di ON di.deal_id=d.id AND di.is_primary=1 WHERE d.id=? AND d.merchant_id=?");
        $stmt->execute([$editId,$mid]);
        $editDeal = $stmt->fetch();
    } catch (PDOException) {}
    if (!$editDeal) { auth_set_flash('error','Deal not found.'); redirect('/merchant/deals.php'); }
}

// ─── Deal list ─────────────────────────────────────────────────────────────
$deals      = [];
$deal_total = 0;
if ($action==='list') {
    $filter   = in_array($_GET['status']??'',['all','active','pending','draft','paused','expired']) ? $_GET['status'] : 'all';
    $per_page = 15; $page_num = max(1,(int)($_GET['page']??1)); $offset = ($page_num-1)*$per_page;
    try {
        $pdo = db();
        $where = ["d.merchant_id=?","d.deleted_at IS NULL"];
        $params = [$mid];
        if ($filter!=='all') { $where[]="d.status=?"; $params[]=$filter; }
        $ws = 'WHERE '.implode(' AND ',$where);

        $deal_total = (int)$pdo->prepare("SELECT COUNT(*) FROM deals d {$ws}")->execute($params) ?
            (int)$pdo->query("SELECT COUNT(*) FROM deals d {$ws} -- count")->fetchColumn() : 0;
        $st = $pdo->prepare("SELECT COUNT(*) FROM deals d {$ws}"); $st->execute($params); $deal_total=(int)$st->fetchColumn();

        $st = $pdo->prepare("SELECT d.*,di.image_path AS primary_image FROM deals d LEFT JOIN deal_images di ON di.deal_id=d.id AND di.is_primary=1 {$ws} ORDER BY d.created_at DESC LIMIT ? OFFSET ?");
        $st->execute(array_merge($params,[$per_page,$offset]));
        $deals = $st->fetchAll();
    } catch (PDOException $e) { error_log('[Merchant deals list] '.$e->getMessage()); }
}

$page_title = match($action){ 'create'=>'Create New Deal', 'edit'=>'Edit Deal', default=>'My Deals' };
include __DIR__ . '/../inc/merchant_layout.php';
?>

<?php if ($action==='create' || $action==='edit'): ?>
<?php $d = $editDeal ?? []; $errors = $errors ?? []; ?>

<div style="margin-bottom:var(--space-lg);"><a href="/merchant/deals.php" style="color:var(--text-muted);font-size:15px;">← Back to Deals</a></div>

<?php if (!empty($errors['general'])): ?><div class="alert alert--error"><span class="alert__icon">✕</span><span><?= e($errors['general']) ?></span></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="form_action" value="<?= $action==='edit'?'update_deal':'create_deal' ?>">
  <?php if ($action==='edit'): ?><input type="hidden" name="deal_id" value="<?= $d['id'] ?>"><?php endif; ?>

  <div class="grid grid-2" style="align-items:start;gap:var(--space-xl);">

    <!-- Left: Core info -->
    <div style="display:flex;flex-direction:column;gap:var(--space-lg);">
      <div class="card" style="padding:var(--space-xl);">
        <h4 style="margin-bottom:var(--space-lg);padding-bottom:var(--space-md);border-bottom:1px solid var(--border-light);">📝 Deal Details</h4>

        <div class="form-group">
          <label class="form-label" for="title">Deal Title <span class="required">*</span></label>
          <input type="text" id="title" name="title" class="form-control <?= !empty($errors['title'])?'form-control--error':'' ?>"
                 value="<?= e($_POST['title'] ?? $d['title'] ?? '') ?>" placeholder="e.g. 20% Off Wellness Package" required>
          <?php if (!empty($errors['title'])): ?><div class="form-error"><?= e($errors['title']) ?></div><?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="short_desc">Short Description</label>
          <input type="text" id="short_desc" name="short_desc" class="form-control"
                 value="<?= e($_POST['short_desc'] ?? $d['short_desc'] ?? '') ?>"
                 placeholder="One-line summary shown on deal cards (max 120 chars)" maxlength="120">
        </div>

        <div class="form-group">
          <label class="form-label" for="description">Full Description</label>
          <textarea id="description" name="description" class="form-control" rows="5"
                    placeholder="Describe what the deal includes, how to use it, any restrictions…"><?= e($_POST['description'] ?? $d['description'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="deal_type">Deal Type</label>
            <select id="deal_type" name="deal_type" class="form-control">
              <?php foreach (['discount'=>'💸 Discount','voucher'=>'🎫 Voucher','freebie'=>'🎁 Freebie','event'=>'📅 Event','offer'=>'🏷 Offer'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= (($_POST['deal_type']??$d['deal_type']??'')===$v)?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="category_id">Category</label>
            <select id="category_id" name="category_id" class="form-control">
              <option value="">— Select —</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= (($_POST['category_id']??$d['category_id']??'')==$cat['id'])?'selected':'' ?>><?= e($cat['icon'].' '.$cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="terms_conditions">Terms & Conditions</label>
          <textarea id="terms_conditions" name="terms_conditions" class="form-control" rows="3"
                    placeholder="Any conditions, exclusions, or redemption instructions…"><?= e($_POST['terms_conditions'] ?? $d['terms_conditions'] ?? '') ?></textarea>
        </div>
      </div>

      <!-- Image upload -->
      <div class="card" style="padding:var(--space-xl);">
        <h4 style="margin-bottom:var(--space-lg);">🖼 Deal Image</h4>
        <?php if (!empty($d['primary_image'])): ?>
          <img src="<?= e($d['primary_image']) ?>" alt="Current image" style="width:100%;max-height:200px;object-fit:cover;border-radius:var(--radius-md);margin-bottom:var(--space-md);">
        <?php endif; ?>
        <div style="border:2px dashed var(--border-light);border-radius:var(--radius-md);padding:var(--space-xl);text-align:center;cursor:pointer;" onclick="document.getElementById('image').click()">
          <div style="font-size:36px;margin-bottom:var(--space-sm);">🖼</div>
          <div style="font-weight:600;margin-bottom:4px;"><?= $d ? 'Replace image' : 'Upload deal image' ?></div>
          <div style="font-size:13px;color:var(--text-muted);">JPG, PNG, WebP — max 5 MB. Recommended: 1200×630px</div>
          <img id="img_preview" src="" alt="" style="display:none;max-height:120px;margin:var(--space-md) auto 0;border-radius:var(--radius-sm);">
        </div>
        <input type="file" id="image" name="image" accept="image/*" style="display:none;" onchange="const r=new FileReader();r.onload=e=>{const p=document.getElementById('img_preview');p.src=e.target.result;p.style.display='block'};r.readAsDataURL(this.files[0])">
      </div>
    </div>

    <!-- Right: Pricing + Rules -->
    <div style="display:flex;flex-direction:column;gap:var(--space-lg);">
      <div class="card" style="padding:var(--space-xl);">
        <h4 style="margin-bottom:var(--space-lg);padding-bottom:var(--space-md);border-bottom:1px solid var(--border-light);">💰 Pricing</h4>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="original_price">Original Price (RM)</label>
            <input type="number" id="original_price" name="original_price" class="form-control"
                   value="<?= e($_POST['original_price'] ?? $d['original_price'] ?? '') ?>"
                   placeholder="e.g. 100.00" step="0.01" min="0">
          </div>
          <div class="form-group">
            <label class="form-label" for="deal_price">Deal Price (RM)</label>
            <input type="number" id="deal_price" name="deal_price" class="form-control"
                   value="<?= e($_POST['deal_price'] ?? $d['deal_price'] ?? '') ?>"
                   placeholder="e.g. 80.00" step="0.01" min="0">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="discount_pct">Discount % (alternative to price)</label>
          <input type="number" id="discount_pct" name="discount_pct" class="form-control"
                 value="<?= e($_POST['discount_pct'] ?? $d['discount_pct'] ?? '') ?>"
                 placeholder="e.g. 20 (for 20% off)" step="0.01" min="0" max="100">
          <div class="form-hint">Set either a deal price, a discount %, or both.</div>
        </div>

        <div class="form-group">
          <label class="form-label" for="points_required">SilverPoints Required to Redeem</label>
          <input type="number" id="points_required" name="points_required" class="form-control"
                 value="<?= e($_POST['points_required'] ?? $d['points_required'] ?? 0) ?>"
                 placeholder="0 = free to redeem, no points needed" min="0">
        </div>
      </div>

      <div class="card" style="padding:var(--space-xl);">
        <h4 style="margin-bottom:var(--space-lg);padding-bottom:var(--space-md);border-bottom:1px solid var(--border-light);">⚙️ Rules & Availability</h4>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="valid_from">Valid From</label>
            <input type="date" id="valid_from" name="valid_from" class="form-control"
                   value="<?= e($_POST['valid_from'] ?? $d['valid_from'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label" for="valid_until">Valid Until <span class="required">*</span></label>
            <input type="date" id="valid_until" name="valid_until" class="form-control <?= !empty($errors['valid_until'])?'form-control--error':'' ?>"
                   value="<?= e($_POST['valid_until'] ?? $d['valid_until'] ?? '') ?>">
            <?php if (!empty($errors['valid_until'])): ?><div class="form-error"><?= e($errors['valid_until']) ?></div><?php endif; ?>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="max_redemptions">Max Redemptions</label>
          <input type="number" id="max_redemptions" name="max_redemptions" class="form-control"
                 value="<?= e($_POST['max_redemptions'] ?? $d['max_redemptions'] ?? -1) ?>"
                 placeholder="-1 = unlimited">
          <div class="form-hint">-1 means unlimited redemptions.</div>
        </div>

        <div class="form-group" style="display:flex;flex-direction:column;gap:var(--space-sm);">
          <label class="form-check">
            <input type="checkbox" name="is_members_only" <?= (($_POST['is_members_only']??$d['is_members_only']??0)?'checked':'') ?>>
            <span>Members-only deal (requires active SilverDeals membership)</span>
          </label>
          <?php if ($merchant['status']==='active'): ?>
          <label class="form-check">
            <input type="checkbox" name="is_featured" <?= (($_POST['is_featured']??$d['is_featured']??0)?'checked':'') ?>>
            <span>Request featured placement (subject to admin approval)</span>
          </label>
          <?php endif; ?>
        </div>

        <?php if ($action==='edit'): ?>
        <div class="form-group">
          <label class="form-label" for="status">Deal Status</label>
          <select id="status" name="status" class="form-control">
            <?php foreach (['draft'=>'Draft','pending'=>'Submit for Review','active'=>'Active','paused'=>'Paused'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= (($_POST['status']??$d['status']??'')===$v)?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
      </div>

      <button type="submit" class="btn btn--primary btn--full btn--lg">
        <?= $action==='edit' ? '💾 Update Deal' : '🚀 Submit Deal' ?>
      </button>
      <?php if ($action==='create'): ?>
        <p style="font-size:13px;color:var(--text-muted);text-align:center;">Deal will be submitted to admin for review before going live.</p>
      <?php endif; ?>
    </div>
  </div>
</form>

<?php else: /* DEAL LIST */ ?>

<div class="flex-between" style="margin-bottom:var(--space-lg);flex-wrap:wrap;gap:var(--space-md);">
  <div class="pill-list">
    <?php foreach (['all'=>'All','active'=>'✅ Active','pending'=>'⏳ Review','draft'=>'📝 Draft','paused'=>'⏸ Paused','expired'=>'Expired'] as $k=>$l): ?>
      <a href="?status=<?= $k ?>" class="pill <?= ($filter??'all')===$k?'active':'' ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>
  <a href="/merchant/deals.php?action=create" class="btn btn--primary btn--sm">+ Create New Deal</a>
</div>

<?php if (!empty($deals)): ?>
  <div class="grid grid-3">
    <?php foreach ($deals as $d): ?>
      <div class="card deal-card">
        <?php if ($d['primary_image']): ?>
          <img src="<?= e($d['primary_image']) ?>" alt="<?= e($d['title']) ?>" class="card__image">
        <?php else: ?>
          <div class="card__image" style="display:flex;align-items:center;justify-content:center;background:var(--orange-bg);font-size:48px;">🎁</div>
        <?php endif; ?>
        <span class="badge badge--<?= match($d['status']){'active'=>'success','pending'=>'warning','draft'=>'muted','paused'=>'warning',default=>'error'} ?>" style="position:absolute;top:12px;left:12px;">
          <?= ucfirst($d['status']) ?>
        </span>
        <div class="card__body">
          <h5 class="card__title" style="font-size:16px;"><?= e($d['title']) ?></h5>
          <div style="font-size:14px;color:var(--text-muted);margin-bottom:var(--space-sm);">
            <?= $d['deal_price'] ? format_myr((float)$d['deal_price']) : ($d['discount_pct'] ? $d['discount_pct'].'% OFF' : 'Members deal') ?>
            <?php if ($d['valid_until']): ?> · Expires <?= date('d M',strtotime($d['valid_until'])) ?><?php endif; ?>
          </div>
          <div style="font-size:14px;font-weight:700;color:var(--orange-primary);margin-bottom:var(--space-md);">
            <?= $d['redemption_count'] ?> redemptions
          </div>
          <div style="display:flex;gap:var(--space-sm);">
            <a href="?action=edit&edit=<?= $d['id'] ?>" class="btn btn--secondary btn--sm" style="flex:1;text-align:center;">Edit</a>
            <form method="POST" style="flex:0;">
              <?= csrf_field() ?>
              <input type="hidden" name="deal_id" value="<?= $d['id'] ?>">
              <input type="hidden" name="action"  value="toggle_pause">
              <button class="btn btn--muted btn--sm"><?= $d['status']==='active' ? '⏸' : '▶' ?></button>
            </form>
            <form method="POST" style="flex:0;" onsubmit="return confirm('Delete this deal?')">
              <?= csrf_field() ?>
              <input type="hidden" name="deal_id" value="<?= $d['id'] ?>">
              <input type="hidden" name="action"  value="delete">
              <button class="btn btn--sm" style="background:var(--error-bg);color:var(--error);border-color:var(--error);">🗑</button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="empty-state">
    <div class="empty-state__icon">🎁</div>
    <h3 class="empty-state__title">No deals yet</h3>
    <p class="empty-state__text">Create your first deal and start reaching senior customers.</p>
    <a href="?action=create" class="btn btn--primary">+ Create First Deal</a>
  </div>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../inc/merchant_layout_end.php'; ?>
