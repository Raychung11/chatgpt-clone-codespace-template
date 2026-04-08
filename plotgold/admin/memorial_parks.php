<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_admin();

$action = clean($_GET['action'] ?? 'list');
$parkId = clean_int($_GET['id'] ?? 0);
$error  = '';

// Save new/edit park
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();
    $name    = clean($_POST['name']        ?? '');
    $nameZh  = clean($_POST['name_zh']     ?? '');
    $city    = clean($_POST['city']        ?? '');
    $state   = clean($_POST['state']       ?? '');
    $address = clean($_POST['address_line1'] ?? '');
    $phone   = clean($_POST['phone']       ?? '');
    $email   = clean_email($_POST['email'] ?? '');
    $desc    = clean($_POST['description'] ?? '');
    $religions = clean($_POST['supported_religions'] ?? '');
    $isActive  = (int)!empty($_POST['is_active']);
    $isFeat    = (int)!empty($_POST['is_featured']);

    if (!$name || !$city || !$state) {
        $error = 'Name, city and state are required.';
    } else {
        $slugBase = slug($name);
        if ($parkId) {
            Database::query(
                'UPDATE memorial_parks SET name=?,name_zh=?,city=?,state=?,address_line1=?,phone=?,email=?,description=?,supported_religions=?,is_active=?,is_featured=? WHERE id=?',
                [$name,$nameZh,$city,$state,$address,$phone,$email,$desc,$religions,$isActive,$isFeat,$parkId]
            );
            flash_set(FLASH_SUCCESS, 'Memorial park updated.');
        } else {
            // Ensure slug is unique
            $slug  = $slugBase;
            $count = 0;
            while (Database::fetchOne('SELECT id FROM memorial_parks WHERE slug = ?', [$slug])) {
                $slug = $slugBase . '-' . (++$count);
            }
            Database::insert(
                'INSERT INTO memorial_parks (slug,name,name_zh,city,state,address_line1,phone,email,description,supported_religions,is_active,is_featured) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [$slug,$name,$nameZh,$city,$state,$address,$phone,$email,$desc,$religions,$isActive,$isFeat]
            );
            flash_set(FLASH_SUCCESS, 'Memorial park added.');
        }
        activity_log(auth_user_id(), 'park_saved', 'memorial_parks', $parkId, $name);
        redirect('admin/memorial_parks.php');
    }
}

// Get park for edit
$park = null;
if ($parkId) {
    $park = Database::fetchOne('SELECT * FROM memorial_parks WHERE id = ?', [$parkId]);
    if (!$park) redirect('admin/memorial_parks.php');
}

// List
$parks = Database::fetchAll('SELECT *, (SELECT COUNT(*) FROM listings WHERE park_id = memorial_parks.id AND status = "active") AS listing_count FROM memorial_parks ORDER BY is_featured DESC, name ASC');

$page_title = 'Memorial Parks';
$body_class = 'admin-layout';
include INC_PATH . '/header.php';
?>
<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="admin-main">
    <?= render_flash() ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-700 text-navy mb-0">Memorial Parks</h4>
        <a href="?action=new" class="btn btn-gold btn-sm"><i class="fas fa-plus me-1"></i>Add Park</a>
    </div>

    <?php if ($action === 'new' || ($action === 'edit' && $park)): ?>
    <!-- Add/Edit Form -->
    <div class="pg-card p-4 mb-4">
        <h6 class="fw-600 mb-3"><?= $park ? 'Edit: ' . h($park['name']) : 'Add New Memorial Park' ?></h6>
        <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
        <form method="POST">
            <?= csrf_field() ?>
            <?php if ($park): ?><input type="hidden" name="_park_id" value="<?= $park['id'] ?>">
                <?php $parkId = $park['id']; /* keep for re-edit */ ?>
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Name (English) <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required value="<?= h($park['name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Name (Chinese)</label>
                    <input type="text" name="name_zh" class="form-control" value="<?= h($park['name_zh'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">City <span class="text-danger">*</span></label>
                    <input type="text" name="city" class="form-control" required value="<?= h($park['city'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">State <span class="text-danger">*</span></label>
                    <select name="state" class="form-select" required>
                        <option value="">— Select —</option>
                        <?php foreach (MY_STATES as $s): ?>
                            <option value="<?= h($s) ?>" <?= ($park['state'] ?? '') === $s ? 'selected' : '' ?>><?= h($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <input type="text" name="address_line1" class="form-control" value="<?= h($park['address_line1'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= h($park['phone'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= h($park['email'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Supported Religions (csv)</label>
                    <input type="text" name="supported_religions" class="form-control" placeholder="buddhist,taoist,christian" value="<?= h($park['supported_religions'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= h($park['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" id="parkActive" class="form-check-input" <?= ($park['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label for="parkActive" class="form-check-label small">Active (visible on site)</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input type="checkbox" name="is_featured" value="1" id="parkFeat" class="form-check-input" <?= !empty($park['is_featured']) ? 'checked' : '' ?>>
                        <label for="parkFeat" class="form-check-label small">Featured on Homepage</label>
                    </div>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-gold">Save Park</button>
                    <a href="<?= pg_url('admin/memorial_parks.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Parks Table -->
    <div class="pg-card">
        <div class="table-responsive">
            <table class="table admin-table mb-0">
                <thead><tr><th>Park Name</th><th>Location</th><th>Religions</th><th>Listings</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($parks as $p): ?>
                <tr>
                    <td>
                        <div class="fw-500 small"><?= h($p['name']) ?></div>
                        <?php if ($p['name_zh']): ?><div class="text-muted" style="font-size:.75rem"><?= h($p['name_zh']) ?></div><?php endif; ?>
                        <?php if ($p['is_featured']): ?><span class="badge bg-warning text-dark" style="font-size:.65rem">Featured</span><?php endif; ?>
                    </td>
                    <td class="small"><?= h($p['city']) ?>, <?= h($p['state']) ?></td>
                    <td class="small text-muted"><?= h($p['supported_religions'] ?? '—') ?></td>
                    <td class="small"><?= $p['listing_count'] ?></td>
                    <td>
                        <span class="status-pill <?= $p['is_active'] ? 'active' : 'expired' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span>
                    </td>
                    <td>
                        <a href="?action=edit&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-gold"><i class="fas fa-edit"></i></a>
                        <a href="<?= park_url($p['slug']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
<?php include INC_PATH . '/footer.php'; ?>
