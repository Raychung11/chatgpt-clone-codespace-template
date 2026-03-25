<?php
/**
 * Admin – Reward Create/Edit Form
 * /admin/pages/rewards_form.php
 */

$id     = sanitize_int($_GET['id'] ?? 0);
$reward = $id ? Database::fetchOne('SELECT * FROM rewards WHERE id = ?', [$id]) : null;
$isEdit = (bool) $reward;

$pageTitle  = $isEdit ? 'Edit Reward' : 'Create Reward';
$activePage = 'rewards';
$errors     = [];

$outlets = Database::fetchAll("SELECT id, name FROM outlets WHERE status = 'active' ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $name       = sanitize_string($_POST['name']           ?? '');
        $desc       = sanitize_string($_POST['description']    ?? '', 500);
        $points     = sanitize_int($_POST['points_required']   ?? 0);
        $type       = in_array($_POST['reward_type'], ['voucher','free_item','discount','experience']) ? $_POST['reward_type'] : 'voucher';
        $discVal    = floatval($_POST['discount_value']        ?? 0);
        $discType   = in_array($_POST['discount_type'], ['fixed','percent']) ? $_POST['discount_type'] : null;
        $stock      = $_POST['stock'] !== '' ? sanitize_int($_POST['stock']) : null;
        $validFrom  = sanitize_string($_POST['valid_from']     ?? '');
        $validUntil = sanitize_string($_POST['valid_until']    ?? '');
        $outletId   = sanitize_int($_POST['outlet_id']         ?? 0) ?: null;
        $status     = in_array($_POST['status'], ['active','inactive']) ? $_POST['status'] : 'active';

        if (!$name)    $errors[] = 'Reward name is required.';
        if ($points < 1) $errors[] = 'Points required must be at least 1.';

        if (empty($errors)) {
            $params = [$name,$desc,$points,$type,$discVal?:null,$discType,$stock,$validFrom?:null,$validUntil?:null,$outletId,$status];

            if ($isEdit) {
                Database::execute(
                    'UPDATE rewards SET name=?,description=?,points_required=?,reward_type=?,discount_value=?,discount_type=?,stock=?,valid_from=?,valid_until=?,outlet_id=?,status=? WHERE id=?',
                    array_merge($params, [$id])
                );
                flash('success', 'Reward updated.');
            } else {
                Database::insert(
                    'INSERT INTO rewards (name,description,points_required,reward_type,discount_value,discount_type,stock,valid_from,valid_until,outlet_id,status) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                    $params
                );
                flash('success', 'Reward created.');
            }
            admin_log($isEdit?'update_reward':'create_reward', 'rewards', $id);
            header('Location: /admin/rewards');
            exit;
        }
    }
}

$v = $reward ?? [];
require __DIR__ . '/../layout/header.php';
?>

<div class="mb-3">
    <a href="/admin/rewards" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Rewards</a>
</div>

<div class="card border-0 shadow-sm" style="max-width:640px;">
    <div class="card-header bg-white border-0 py-3"><h6 class="mb-0 fw-semibold"><?= $pageTitle ?></h6></div>
    <div class="card-body">
        <?php if ($errors): ?>
        <div class="alert alert-danger py-2 small"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="_csrf_token" value="<?= Auth::generateCsrfToken() ?>">

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold small">Reward Name *</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($v['name'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold small">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($v['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Reward Type</label>
                    <select name="reward_type" class="form-select" id="rewardType">
                        <option value="voucher"    <?= ($v['reward_type']??'')==='voucher'    ?'selected':'' ?>>Voucher</option>
                        <option value="free_item"  <?= ($v['reward_type']??'')==='free_item'  ?'selected':'' ?>>Free Item</option>
                        <option value="discount"   <?= ($v['reward_type']??'')==='discount'   ?'selected':'' ?>>Discount</option>
                        <option value="experience" <?= ($v['reward_type']??'')==='experience' ?'selected':'' ?>>Experience</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Points Required *</label>
                    <input type="number" name="points_required" class="form-control" min="1" value="<?= $v['points_required'] ?? 100 ?>" required>
                </div>

                <!-- Discount fields -->
                <div class="col-md-6 discount-fields">
                    <label class="form-label fw-semibold small">Discount Value</label>
                    <input type="number" step="0.01" name="discount_value" class="form-control" value="<?= $v['discount_value'] ?? '' ?>">
                </div>
                <div class="col-md-6 discount-fields">
                    <label class="form-label fw-semibold small">Discount Type</label>
                    <select name="discount_type" class="form-select">
                        <option value="">— Select —</option>
                        <option value="fixed"   <?= ($v['discount_type']??'')==='fixed'   ?'selected':'' ?>>Fixed Amount (MYR)</option>
                        <option value="percent" <?= ($v['discount_type']??'')==='percent' ?'selected':'' ?>>Percentage (%)</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Stock (leave blank = unlimited)</label>
                    <input type="number" name="stock" class="form-control" min="0" value="<?= $v['stock'] ?? '' ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Applicable Outlet</label>
                    <select name="outlet_id" class="form-select">
                        <option value="">All Outlets</option>
                        <?php foreach ($outlets as $o): ?>
                        <option value="<?= $o['id'] ?>" <?= ($v['outlet_id']??0)==$o['id']?'selected':'' ?>><?= htmlspecialchars($o['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Valid From</label>
                    <input type="date" name="valid_from" class="form-control" value="<?= $v['valid_from'] ?? '' ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Valid Until</label>
                    <input type="date" name="valid_until" class="form-control" value="<?= $v['valid_until'] ?? '' ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Status</label>
                    <select name="status" class="form-select">
                        <option value="active"   <?= ($v['status']??'')==='active'   ?'selected':'' ?>>Active</option>
                        <option value="inactive" <?= ($v['status']??'')==='inactive' ?'selected':'' ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <hr class="my-3">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Update' : 'Create Reward' ?></button>
                <a href="/admin/rewards" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('rewardType').addEventListener('change', function() {
    const show = this.value === 'discount';
    document.querySelectorAll('.discount-fields').forEach(el => el.style.display = show ? '' : 'none');
});
document.getElementById('rewardType').dispatchEvent(new Event('change'));
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
