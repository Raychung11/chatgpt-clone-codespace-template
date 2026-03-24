<?php
require_once __DIR__ . '/layout.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $buyer_name          = trim($_POST['buyer_name']          ?? '');
    $buyer_contact       = trim($_POST['buyer_contact']       ?? '');
    $category            = trim($_POST['category']            ?? '');
    $location            = trim($_POST['location']            ?? '');
    $service_description = trim($_POST['service_description'] ?? '');
    $preferred_date      = trim($_POST['preferred_date']      ?? '');
    $urgency             = trim($_POST['urgency']             ?? '');
    $budget              = trim($_POST['budget']              ?? '');
    $special_notes       = trim($_POST['special_notes']       ?? '');
    $member              = current_member();
    $member_kop_id       = $member['koperasi_id'] ?? '';

    if (!$buyer_name)          $errors[] = 'Your name is required.';
    if (!$buyer_contact)       $errors[] = 'Contact number is required.';
    if (!$category)            $errors[] = 'Category is required.';
    if (!$location)            $errors[] = 'Location is required.';
    if (!$service_description) $errors[] = 'Service description is required.';

    if (empty($errors)) {
        save_request(compact('buyer_name','buyer_contact','member_kop_id','category','location','service_description','preferred_date','urgency','budget','special_notes'));
        flash('Your service request has been submitted!');
        redirect('match_engine.php?category=' . urlencode($category) . '&location=' . urlencode($location));
    }
}

$member = current_member();
html_head('Request a Service');
html_body_open();
?>

<div class="page-title">🛒 Request a Service</div>
<p class="text-muted small mb-3">Tell us what you need and we'll match you with the right koperasi member.</p>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="card p-4" style="max-width:700px">
<form method="post">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label fw-semibold">Your Name *</label>
            <input type="text" name="buyer_name" class="form-control"
                value="<?= e($_POST['buyer_name'] ?? $member['name'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Contact (WhatsApp/Phone) *</label>
            <input type="text" name="buyer_contact" class="form-control"
                value="<?= e($_POST['buyer_contact'] ?? '') ?>"
                placeholder="e.g. 012-3456789" required>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Service Category *</label>
            <select name="category" class="form-select" required>
                <option value="">Select…</option>
                <?php foreach (categories() as $c): ?>
                    <option value="<?= e($c) ?>" <?= ($_POST['category'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Location *</label>
            <select name="location" class="form-select" required>
                <option value="">Select…</option>
                <?php foreach (locations() as $l): ?>
                    <option value="<?= e($l) ?>" <?= ($_POST['location'] ?? '') === $l ? 'selected' : '' ?>><?= e($l) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label fw-semibold">Describe What You Need *</label>
            <textarea name="service_description" class="form-control" rows="3"
                placeholder="Describe the job scope, your requirements, etc." required><?= e($_POST['service_description'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Preferred Date</label>
            <input type="date" name="preferred_date" class="form-control"
                value="<?= e($_POST['preferred_date'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Urgency</label>
            <select name="urgency" class="form-select">
                <option value="">Select…</option>
                <option value="As soon as possible">As soon as possible</option>
                <option value="Within 2–3 days">Within 2–3 days</option>
                <option value="Flexible — within 1 week">Flexible — within 1 week</option>
                <option value="Flexible — within 2 weeks">Flexible — within 2 weeks</option>
                <option value="No rush — within a month">No rush — within a month</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Budget Range</label>
            <input type="text" name="budget" class="form-control"
                value="<?= e($_POST['budget'] ?? '') ?>"
                placeholder="e.g. RM 100–200">
        </div>
        <div class="col-12">
            <label class="form-label fw-semibold">Special Notes</label>
            <textarea name="special_notes" class="form-control" rows="2"
                placeholder="Any special instructions or requirements"><?= e($_POST['special_notes'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">🚀 Submit Request</button>
            <a href="find_services.php" class="btn btn-outline-secondary ms-2">Browse Services Instead</a>
        </div>
    </div>
</form>
</div>

<?php html_footer(); ?>
