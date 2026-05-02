<?php
require_once __DIR__ . '/../layout.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name          = trim($_POST['name']          ?? '');
    $kop_id        = trim($_POST['koperasi_id']   ?? '');
    $category      = trim($_POST['category']      ?? '');
    $service_title = trim($_POST['service_title'] ?? '');
    $area          = trim($_POST['area']          ?? '');
    $price_range   = trim($_POST['price_range']   ?? '');
    $availability  = trim($_POST['availability']  ?? '');
    $experience    = trim($_POST['experience']    ?? '');
    $description   = trim($_POST['description']   ?? '');
    $contact       = trim($_POST['contact']       ?? 'WhatsApp available upon request');

    if (!$name)          $errors[] = 'Name is required.';
    if (!$kop_id)        $errors[] = 'Koperasi Member ID is required.';
    elseif (!str_starts_with(strtoupper($kop_id), MEMBER_ID_PREFIX))
        $errors[] = 'Member ID must start with ' . MEMBER_ID_PREFIX . ' (e.g. ' . MEMBER_ID_PREFIX . '00123).';
    if (!$category)      $errors[] = 'Category is required.';
    if (!$service_title) $errors[] = 'Service title is required.';
    if (!$area)          $errors[] = 'Service area is required.';
    if (!$price_range)   $errors[] = 'Price range is required.';
    if (!$description)   $errors[] = 'Description is required.';

    if (empty($errors)) {
        $data = compact('name','category','service_title','area','price_range','availability','experience','description','contact') + ['koperasi_id' => strtoupper($kop_id)];
        $data['image']   = handle_image_upload('image',   'sellers') ?? '';
        $data['gallery1']= handle_image_upload('gallery1','sellers') ?? '';
        $data['gallery2']= handle_image_upload('gallery2','sellers') ?? '';
        $data['status']  = 'pending'; // requires admin approval
        save_seller($data);
        flash('Listing submitted! It will be reviewed and activated by the koperasi admin within 1–2 working days.', 'info');
        redirect(PORTAL_URL . '/');
    }
}

$member = current_member();
html_head('Register Service');
html_body_open();
?>

<div class="page-title">💼 Register Your Service</div>
<p class="text-muted small mb-3">List your skill or service for other koperasi members to find and hire you.</p>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="card p-4" style="max-width:740px">
<form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label fw-semibold">Full Name *</label>
            <input type="text" name="name" class="form-control"
                value="<?= e($_POST['name'] ?? $member['name'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Koperasi Member ID *</label>
            <input type="text" name="koperasi_id" class="form-control"
                value="<?= e($_POST['koperasi_id'] ?? $member['koperasi_id'] ?? '') ?>"
                placeholder="e.g. KKBR-00123" required>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Service Category *</label>
            <select name="category" class="form-select" required>
                <option value="">Select category…</option>
                <?php foreach (categories() as $c): ?>
                    <option value="<?= e($c) ?>" <?= ($_POST['category'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Service Title *</label>
            <input type="text" name="service_title" class="form-control"
                value="<?= e($_POST['service_title'] ?? '') ?>"
                placeholder="e.g. Professional Home Cleaning" required>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Service Area *</label>
            <select name="area" class="form-select" required>
                <option value="">Select area…</option>
                <?php foreach (locations() as $l): ?>
                    <option value="<?= e($l) ?>" <?= ($_POST['area'] ?? '') === $l ? 'selected' : '' ?>><?= e($l) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Price Range *</label>
            <input type="text" name="price_range" class="form-control"
                value="<?= e($_POST['price_range'] ?? '') ?>"
                placeholder="e.g. RM 80 – RM 120 per session" required>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Availability</label>
            <input type="text" name="availability" class="form-control"
                value="<?= e($_POST['availability'] ?? '') ?>"
                placeholder="e.g. Weekends, Mon–Fri evenings">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Experience</label>
            <select name="experience" class="form-select">
                <option value="">Select…</option>
                <?php foreach (exp_options() as $opt): ?>
                    <option value="<?= e($opt) ?>" <?= ($_POST['experience'] ?? '') === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label fw-semibold">Service Description *</label>
            <textarea name="description" class="form-control" rows="4"
                placeholder="Describe what you offer, what's included/excluded, minimum booking, etc." required><?= e($_POST['description'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
            <label class="form-label fw-semibold">Contact Info</label>
            <input type="text" name="contact" class="form-control"
                value="<?= e($_POST['contact'] ?? 'WhatsApp available upon request') ?>">
        </div>

        <!-- ── Photos ── -->
        <div class="col-12"><hr><h6 class="text-muted fw-semibold">📷 Listing Photos <small class="fw-normal">(optional, max 2MB each)</small></h6></div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Main Photo</label>
            <input type="file" name="image" class="form-control form-control-sm" accept="image/*"
                onchange="previewImg(this,'prev_main')">
            <img id="prev_main" src="" class="img-thumbnail mt-2 d-none" style="max-height:110px;width:100%;object-fit:cover">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Gallery Photo 1</label>
            <input type="file" name="gallery1" class="form-control form-control-sm" accept="image/*"
                onchange="previewImg(this,'prev_g1')">
            <img id="prev_g1" src="" class="img-thumbnail mt-2 d-none" style="max-height:110px;width:100%;object-fit:cover">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Gallery Photo 2</label>
            <input type="file" name="gallery2" class="form-control form-control-sm" accept="image/*"
                onchange="previewImg(this,'prev_g2')">
            <img id="prev_g2" src="" class="img-thumbnail mt-2 d-none" style="max-height:110px;width:100%;object-fit:cover">
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">✅ Submit Listing</button>
            <a href="<?= MARKET_URL ?>/" class="btn btn-outline-secondary ms-2">Cancel</a>
        </div>
    </div>
</form>
</div>

<script>
function previewImg(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; preview.classList.remove('d-none'); };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
<?php html_footer(); ?>
