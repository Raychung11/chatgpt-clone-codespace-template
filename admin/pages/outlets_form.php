<?php
/**
 * Admin – Outlet Create / Edit Form
 * /admin/pages/outlets_form.php
 */

$id = sanitize_int($_GET['id'] ?? 0);
$outlet = $id ? Database::fetchOne('SELECT * FROM outlets WHERE id = ?', [$id]) : null;
$isEdit = (bool) $outlet;

$pageTitle  = $isEdit ? 'Edit Outlet' : 'Add Outlet';
$activePage = 'outlets';
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $name   = sanitize_string($_POST['name']    ?? '');
        $addr   = sanitize_string($_POST['address'] ?? '', 500);
        $city   = sanitize_string($_POST['city']    ?? '');
        $state  = sanitize_string($_POST['state']   ?? '');
        $post   = sanitize_string($_POST['postcode']?? '');
        $phone  = sanitize_string($_POST['phone']   ?? '');
        $email  = sanitize_string($_POST['email']   ?? '');
        $lat    = floatval($_POST['lat']  ?? 0);
        $lng    = floatval($_POST['lng']  ?? 0);
        $status = in_array($_POST['status'], ['active','inactive','temporarily_closed']) ? $_POST['status'] : 'active';

        // Validate
        if (!$name) $errors[] = 'Outlet name is required.';
        if (!$addr) $errors[] = 'Address is required.';
        if (!$city) $errors[] = 'City is required.';

        // Opening hours JSON
        $days   = ['mon','tue','wed','thu','fri','sat','sun'];
        $hours  = [];
        foreach ($days as $d) {
            $open  = sanitize_string($_POST["hours_{$d}_open"]  ?? '');
            $close = sanitize_string($_POST["hours_{$d}_close"] ?? '');
            if ($open && $close) $hours[$d] = "{$open}-{$close}";
        }

        if (empty($errors)) {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name)) . '-' . ($id ?: time());

            if ($isEdit) {
                Database::execute(
                    'UPDATE outlets SET name=?,slug=?,address=?,city=?,state=?,postcode=?,phone=?,email=?,lat=?,lng=?,opening_hours=?,status=? WHERE id=?',
                    [$name,$slug,$addr,$city,$state,$post,$phone,$email,$lat?:null,$lng?:null,json_encode($hours),$status,$id]
                );
                admin_log('update_outlet','outlets',$id);
                flash('success','Outlet updated successfully.');
            } else {
                $newId = Database::insert(
                    'INSERT INTO outlets (name,slug,address,city,state,postcode,phone,email,lat,lng,opening_hours,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$name,$slug,$addr,$city,$state,$post,$phone,$email,$lat?:null,$lng?:null,json_encode($hours),$status]
                );
                admin_log('create_outlet','outlets',$newId);
                flash('success','Outlet created.');
            }
            header('Location: /admin/outlets');
            exit;
        }
    }
}

// Pre-fill from existing
$v = $outlet ?? [];
$hoursData = json_decode($v['opening_hours'] ?? '{}', true) ?: [];
$days = ['mon'=>'Monday','tue'=>'Tuesday','wed'=>'Wednesday','thu'=>'Thursday','fri'=>'Friday','sat'=>'Saturday','sun'=>'Sunday'];

require __DIR__ . '/../layout/header.php';
?>

<div class="mb-3">
    <a href="/admin/outlets" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Outlets</a>
</div>

<div class="card border-0 shadow-sm" style="max-width:700px;">
    <div class="card-header bg-white border-0 py-3">
        <h6 class="mb-0 fw-semibold"><?= $pageTitle ?></h6>
    </div>
    <div class="card-body">
        <?php if ($errors): ?>
        <div class="alert alert-danger py-2 small"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="_csrf_token" value="<?= Auth::generateCsrfToken() ?>">

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold small">Outlet Name *</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($v['name'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold small">Address *</label>
                    <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($v['address'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">City *</label>
                    <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($v['city'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">State</label>
                    <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($v['state'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Postcode</label>
                    <input type="text" name="postcode" class="form-control" value="<?= htmlspecialchars($v['postcode'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($v['phone'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($v['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Latitude (Google Maps)</label>
                    <input type="number" step="0.00000001" name="lat" class="form-control" value="<?= $v['lat'] ?? '' ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Longitude (Google Maps)</label>
                    <input type="number" step="0.00000001" name="lng" class="form-control" value="<?= $v['lng'] ?? '' ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= ($v['status']??'')==='active'?'selected':'' ?>>Active</option>
                        <option value="inactive" <?= ($v['status']??'')==='inactive'?'selected':'' ?>>Inactive</option>
                        <option value="temporarily_closed" <?= ($v['status']??'')==='temporarily_closed'?'selected':'' ?>>Temporarily Closed</option>
                    </select>
                </div>
            </div>

            <!-- Opening Hours -->
            <hr class="my-3">
            <label class="form-label fw-semibold small d-block">Opening Hours</label>
            <div class="row g-2">
                <?php foreach ($days as $key => $label):
                    $hEntry = $hoursData[$key] ?? '';
                    $parts  = explode('-', $hEntry);
                    $open   = $parts[0] ?? '';
                    $close  = $parts[1] ?? '';
                ?>
                <div class="col-12">
                    <div class="row align-items-center g-1">
                        <div class="col-3 small text-end"><?= $label ?></div>
                        <div class="col-4">
                            <input type="time" name="hours_<?= $key ?>_open" class="form-control form-control-sm" value="<?= $open ?>">
                        </div>
                        <div class="col-1 text-center small text-muted">–</div>
                        <div class="col-4">
                            <input type="time" name="hours_<?= $key ?>_close" class="form-control form-control-sm" value="<?= $close ?>">
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <hr class="my-3">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Update Outlet' : 'Create Outlet' ?></button>
                <a href="/admin/outlets" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
