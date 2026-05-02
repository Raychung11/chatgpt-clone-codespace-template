<?php
// ============================================================
//  KOPONIX – Edit Listing (members only, own listings)
// ============================================================
require_once __DIR__ . '/../layout.php';
require_login();

$member = current_member();
$kop_id = $member['koperasi_id'];
$id     = trim($_GET['id'] ?? '');
$errors = [];

if (!$id) { flash('No listing specified.', 'error'); redirect(PORTAL_URL . '/?tab=listings'); }

$seller = get_seller_by_id($id);
if (!$seller || $seller['koperasi_id'] !== $kop_id) {
    flash('Listing not found or access denied.', 'error');
    redirect(PORTAL_URL . '/?tab=listings');
}

// ── Handle form submit ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $category      = trim($_POST['category']      ?? '');
    $service_title = trim($_POST['service_title'] ?? '');
    $area          = trim($_POST['area']          ?? '');
    $price_range   = trim($_POST['price_range']   ?? '');
    $availability  = trim($_POST['availability']  ?? '');
    $experience    = trim($_POST['experience']    ?? '');
    $description   = trim($_POST['description']   ?? '');
    $contact       = trim($_POST['contact']       ?? 'WhatsApp available upon request');

    if (!$category)      $errors[] = 'Category is required.';
    if (!$service_title) $errors[] = 'Service title is required.';
    if (!$area)          $errors[] = 'Area is required.';
    if (!$price_range)   $errors[] = 'Price range is required.';
    if (!$description)   $errors[] = 'Description is required.';

    if (empty($errors)) {
        $update = compact('category','service_title','area','price_range','availability','experience','description','contact');
        $update['name']   = $seller['name']; // keep original name
        $update['status'] = $seller['status'];

        // Handle image uploads (new upload wins; else clear if "remove" checked)
        foreach ([['image','delete_image'],['gallery1','delete_gallery1'],['gallery2','delete_gallery2']] as [$col,$del]) {
            $new = handle_image_upload($col, 'sellers');
            if ($new) {
                $update[$col] = $new;
            } elseif (!empty($_POST[$del])) {
                $update[$col] = '';
            }
        }

        update_seller($id, $update);
        flash('Listing updated successfully!');
        redirect(PORTAL_URL . '/?tab=listings');
    }
    // re-merge for display
    $seller = array_merge($seller, compact('category','service_title','area','price_range','availability','experience','description','contact'));
}

html_head('Edit Listing');
html_body_open();
?>

<div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= PORTAL_URL ?>/?tab=listings" class="btn btn-sm btn-outline-secondary">← Back</a>
    <div class="page-title mb-0">✏️ Edit Listing</div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="row g-4">
<div class="col-lg-8">
<div class="card p-4">
<form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label fw-semibold">Service Category *</label>
            <select name="category" class="form-select" required>
                <?php foreach (categories() as $c): ?>
                    <option value="<?= e($c) ?>" <?= $seller['category']===$c?'selected':'' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Service Title *</label>
            <input type="text" name="service_title" class="form-control"
                value="<?= e($seller['service_title']) ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Service Area *</label>
            <select name="area" class="form-select" required>
                <?php foreach (locations() as $l): ?>
                    <option value="<?= e($l) ?>" <?= $seller['area']===$l?'selected':'' ?>><?= e($l) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Price Range *</label>
            <input type="text" name="price_range" class="form-control"
                value="<?= e($seller['price_range']) ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Availability</label>
            <input type="text" name="availability" class="form-control"
                value="<?= e($seller['availability']) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Experience</label>
            <select name="experience" class="form-select">
                <?php foreach (exp_options() as $opt): ?>
                    <option value="<?= e($opt) ?>" <?= $seller['experience']===$opt?'selected':'' ?>><?= e($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label fw-semibold">Service Description *</label>
            <textarea name="description" class="form-control" rows="4" required><?= e($seller['description']) ?></textarea>
        </div>
        <div class="col-12">
            <label class="form-label fw-semibold">Contact Info</label>
            <input type="text" name="contact" class="form-control" value="<?= e($seller['contact']) ?>">
        </div>

        <!-- ── Photo uploads ── -->
        <div class="col-12"><hr><h6 class="text-muted">📷 Photos</h6></div>

        <!-- Main photo -->
        <div class="col-md-4">
            <label class="form-label fw-semibold">Main Listing Photo</label>
            <?php if ($seller['image']): ?>
                <div class="mb-2">
                    <img src="<?= e(img_url($seller['image'])) ?>" class="img-thumbnail" style="max-height:120px;object-fit:cover;width:100%">
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" name="delete_image" id="del_img" value="1">
                        <label class="form-check-label small text-danger" for="del_img">Remove this photo</label>
                    </div>
                </div>
            <?php endif; ?>
            <input type="file" name="image" class="form-control form-control-sm" accept="image/*"
                onchange="previewImg(this,'prev_main')">
            <img id="prev_main" src="" class="img-thumbnail mt-1 d-none" style="max-height:100px;width:100%;object-fit:cover">
            <div class="small text-muted mt-1">JPG/PNG/WebP, max 2 MB</div>
        </div>

        <!-- Gallery 1 -->
        <div class="col-md-4">
            <label class="form-label fw-semibold">Gallery Photo 1</label>
            <?php if ($seller['gallery1']): ?>
                <div class="mb-2">
                    <img src="<?= e(img_url($seller['gallery1'])) ?>" class="img-thumbnail" style="max-height:120px;object-fit:cover;width:100%">
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" name="delete_gallery1" id="del_g1" value="1">
                        <label class="form-check-label small text-danger" for="del_g1">Remove this photo</label>
                    </div>
                </div>
            <?php endif; ?>
            <input type="file" name="gallery1" class="form-control form-control-sm" accept="image/*"
                onchange="previewImg(this,'prev_g1')">
            <img id="prev_g1" src="" class="img-thumbnail mt-1 d-none" style="max-height:100px;width:100%;object-fit:cover">
            <div class="small text-muted mt-1">JPG/PNG/WebP, max 2 MB</div>
        </div>

        <!-- Gallery 2 -->
        <div class="col-md-4">
            <label class="form-label fw-semibold">Gallery Photo 2</label>
            <?php if ($seller['gallery2']): ?>
                <div class="mb-2">
                    <img src="<?= e(img_url($seller['gallery2'])) ?>" class="img-thumbnail" style="max-height:120px;object-fit:cover;width:100%">
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" name="delete_gallery2" id="del_g2" value="1">
                        <label class="form-check-label small text-danger" for="del_g2">Remove this photo</label>
                    </div>
                </div>
            <?php endif; ?>
            <input type="file" name="gallery2" class="form-control form-control-sm" accept="image/*"
                onchange="previewImg(this,'prev_g2')">
            <img id="prev_g2" src="" class="img-thumbnail mt-1 d-none" style="max-height:100px;width:100%;object-fit:cover">
            <div class="small text-muted mt-1">JPG/PNG/WebP, max 2 MB</div>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">💾 Save Changes</button>
            <a href="<?= PORTAL_URL ?>/?tab=listings" class="btn btn-outline-secondary ms-2">Cancel</a>
        </div>
    </div>
</form>
</div>
</div>

<!-- Preview panel -->
<div class="col-lg-4">
    <div class="card p-3">
        <h6 class="mb-3">👁️ Listing Preview</h6>
        <div class="seller-card" style="box-shadow:none;border:1px solid #eee">
            <?= cat_badge($seller['category']) ?>
            <?php if ($seller['image']): ?>
                <img src="<?= e(img_url($seller['image'])) ?>"
                    style="width:100%;height:140px;object-fit:cover;border-radius:8px;margin:.5rem 0">
            <?php endif; ?>
            <h5><?= e($seller['service_title']) ?></h5>
            <div class="meta">👤 <strong><?= e($seller['name']) ?></strong></div>
            <div class="meta">📍 <?= e($seller['area']) ?> | 💰 <?= e($seller['price_range']) ?></div>
            <div class="desc"><?= e(substr($seller['description'],0,120)) ?>…</div>
        </div>
    </div>
</div>
</div>

<script>
function previewImg(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.classList.remove('d-none');
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
<?php html_footer(); ?>
