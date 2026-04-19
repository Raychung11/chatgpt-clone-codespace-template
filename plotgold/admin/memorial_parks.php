<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_admin();

$action = clean($_GET['action'] ?? 'list');
$parkId = clean_int($_GET['id'] ?? 0);
$error  = '';

$parksUploadDir = UPLOAD_PATH . '/parks';

// ── POST: Quick actions (toggle active / delete) ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action'])) {
    csrf_enforce();
    $quickId = clean_int($_POST['_park_id'] ?? 0);
    $qAction = clean($_POST['_action']);

    if ($qAction === 'toggle_active' && $quickId) {
        Database::query('UPDATE memorial_parks SET is_active = 1 - is_active WHERE id = ?', [$quickId]);
        flash_set(FLASH_SUCCESS, 'Park status updated.');
    }

    if ($qAction === 'delete' && $quickId) {
        $activeCount = (int)(Database::fetchOne(
            "SELECT COUNT(*) c FROM listings WHERE park_id = ? AND status = 'active'", [$quickId]
        )['c'] ?? 0);
        if ($activeCount > 0) {
            flash_set(FLASH_ERROR, "Cannot delete: {$activeCount} active listing(s) exist at this park. Archive it instead.");
        } else {
            $row = Database::fetchOne('SELECT logo_path, banner_path FROM memorial_parks WHERE id = ?', [$quickId]);
            if (!empty($row['logo_path'])   && file_exists($parksUploadDir . '/' . $row['logo_path']))   @unlink($parksUploadDir . '/' . $row['logo_path']);
            if (!empty($row['banner_path']) && file_exists($parksUploadDir . '/' . $row['banner_path'])) @unlink($parksUploadDir . '/' . $row['banner_path']);
            Database::query('DELETE FROM memorial_parks WHERE id = ?', [$quickId]);
            flash_set(FLASH_SUCCESS, 'Park deleted.');
        }
    }

    redirect('admin/memorial_parks.php');
}

// ── POST: Save park ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();

    $name      = clean($_POST['name']               ?? '');
    $nameZh    = clean($_POST['name_zh']            ?? '');
    $city      = clean($_POST['city']               ?? '');
    $state     = clean($_POST['state']              ?? '');
    $address   = clean($_POST['address_line1']      ?? '');
    $phone     = clean($_POST['phone']              ?? '');
    $email     = clean_email($_POST['email']        ?? '');
    $desc      = clean($_POST['description']        ?? '');
    $religions = clean($_POST['supported_religions'] ?? '');
    $isActive  = (int)!empty($_POST['is_active']);
    $isFeat    = (int)!empty($_POST['is_featured']);
    $editId    = clean_int($_POST['_park_id'] ?? 0);

    if (!$name || !$city || !$state) {
        $error = 'Name, city and state are required.';
    } else {

        // ── Fetch existing paths so we can delete old files if replaced/removed ──
        $existing = $editId
            ? Database::fetchOne('SELECT logo_path, banner_path FROM memorial_parks WHERE id = ?', [$editId])
            : null;

        // ── Logo upload ──────────────────────────────────────────────────────────
        $logoPath = $existing['logo_path'] ?? null;

        if (!empty($_POST['remove_logo'])) {
            if ($logoPath && file_exists($parksUploadDir . '/' . $logoPath)) {
                @unlink($parksUploadDir . '/' . $logoPath);
            }
            $logoPath = null;
        } elseif (!empty($_FILES['logo']['name'])) {
            $up = upload_file($_FILES['logo'], $parksUploadDir, ALLOWED_IMAGE_TYPES);
            if (!$up['success']) {
                $error = 'Logo: ' . $up['error'];
            } else {
                if ($logoPath && file_exists($parksUploadDir . '/' . $logoPath)) {
                    @unlink($parksUploadDir . '/' . $logoPath);
                }
                $logoPath = $up['filename'];
            }
        }

        // ── Banner upload ────────────────────────────────────────────────────────
        $bannerPath = $existing['banner_path'] ?? null;

        if (!empty($_POST['remove_banner'])) {
            if ($bannerPath && file_exists($parksUploadDir . '/' . $bannerPath)) {
                @unlink($parksUploadDir . '/' . $bannerPath);
            }
            $bannerPath = null;
        } elseif (!empty($_FILES['banner']['name'])) {
            $up = upload_file($_FILES['banner'], $parksUploadDir, ALLOWED_IMAGE_TYPES);
            if (!$up['success']) {
                $error = 'Banner: ' . $up['error'];
            } else {
                if ($bannerPath && file_exists($parksUploadDir . '/' . $bannerPath)) {
                    @unlink($parksUploadDir . '/' . $bannerPath);
                }
                $bannerPath = $up['filename'];
            }
        }

        if (!$error) {
            if ($editId) {
                Database::query(
                    'UPDATE memorial_parks
                     SET name=?,name_zh=?,city=?,state=?,address_line1=?,phone=?,email=?,
                         description=?,supported_religions=?,is_active=?,is_featured=?,
                         logo_path=?,banner_path=?
                     WHERE id=?',
                    [$name,$nameZh,$city,$state,$address,$phone,$email,
                     $desc,$religions,$isActive,$isFeat,
                     $logoPath,$bannerPath,$editId]
                );
                flash_set(FLASH_SUCCESS, 'Memorial park updated.');
            } else {
                $slugBase = slug($name);
                $slug     = $slugBase;
                $count    = 0;
                while (Database::fetchOne('SELECT id FROM memorial_parks WHERE slug = ?', [$slug])) {
                    $slug = $slugBase . '-' . (++$count);
                }
                Database::insert(
                    'INSERT INTO memorial_parks
                     (slug,name,name_zh,city,state,address_line1,phone,email,description,
                      supported_religions,is_active,is_featured,logo_path,banner_path)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$slug,$name,$nameZh,$city,$state,$address,$phone,$email,$desc,
                     $religions,$isActive,$isFeat,$logoPath,$bannerPath]
                );
                flash_set(FLASH_SUCCESS, 'Memorial park added.');
            }
            activity_log(auth_user_id(), 'park_saved', 'memorial_parks', $editId ?: 0, $name);
            redirect('admin/memorial_parks.php');
        }
    }
}

// ── Load park for edit ─────────────────────────────────────────────────────────
$park = null;
if ($parkId) {
    $park = Database::fetchOne('SELECT * FROM memorial_parks WHERE id = ?', [$parkId]);
    if (!$park) redirect('admin/memorial_parks.php');
}

// ── Parks list ─────────────────────────────────────────────────────────────────
$parks = Database::fetchAll(
    'SELECT *, (SELECT COUNT(*) FROM listings WHERE park_id = memorial_parks.id AND status = "active") AS listing_count
     FROM memorial_parks ORDER BY is_featured DESC, name ASC'
);

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
    <!-- ── Add / Edit Form ──────────────────────────────────────────────────── -->
    <div class="pg-card p-4 mb-4">
        <h6 class="fw-600 mb-4"><?= $park ? 'Edit: ' . h($park['name']) : 'Add New Memorial Park' ?></h6>
        <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <?php if ($park): ?>
                <input type="hidden" name="_park_id" value="<?= $park['id'] ?>">
            <?php endif; ?>

            <div class="row g-3">

                <!-- Basic info -->
                <div class="col-md-6">
                    <label class="form-label fw-600 small">Name (English) <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required value="<?= h($park['name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-600 small">Name (Chinese)</label>
                    <input type="text" name="name_zh" class="form-control" value="<?= h($park['name_zh'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-600 small">City <span class="text-danger">*</span></label>
                    <input type="text" name="city" class="form-control" required value="<?= h($park['city'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-600 small">State <span class="text-danger">*</span></label>
                    <select name="state" class="form-select" required>
                        <option value="">— Select —</option>
                        <?php foreach (MY_STATES as $s): ?>
                            <option value="<?= h($s) ?>" <?= ($park['state'] ?? '') === $s ? 'selected' : '' ?>><?= h($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-600 small">Address</label>
                    <input type="text" name="address_line1" class="form-control" value="<?= h($park['address_line1'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-600 small">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= h($park['phone'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-600 small">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= h($park['email'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-600 small">Supported Religions <span class="text-muted fw-400">(comma separated)</span></label>
                    <input type="text" name="supported_religions" class="form-control" placeholder="buddhist,taoist,christian" value="<?= h($park['supported_religions'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label fw-600 small">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= h($park['description'] ?? '') ?></textarea>
                </div>

                <!-- ── Logo ──────────────────────────────────────────────────── -->
                <div class="col-md-6">
                    <label class="form-label fw-600 small">
                        <i class="fas fa-image me-1" style="color:var(--pg-gold);"></i>
                        Park Logo
                        <span class="text-muted fw-400">(JPG/PNG/WebP, max 5 MB)</span>
                    </label>

                    <?php if (!empty($park['logo_path'])): ?>
                    <div class="mb-2 d-flex align-items-center gap-3 p-3 rounded" style="background:#f8f9fa;border:1px solid #e9ecef;">
                        <img src="<?= pg_url('uploads/parks/' . h($park['logo_path'])) ?>"
                             alt="Current logo"
                             style="width:80px;height:80px;object-fit:contain;border-radius:8px;background:#fff;border:1px solid #e9ecef;">
                        <div>
                            <div class="small fw-600 text-navy mb-1">Current Logo</div>
                            <div class="form-check">
                                <input type="checkbox" name="remove_logo" value="1" id="removeLogo" class="form-check-input">
                                <label for="removeLogo" class="form-check-label small text-danger">Remove logo</label>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <input type="file" name="logo" id="logoInput" class="form-control" accept="image/jpeg,image/png,image/webp">
                    <div class="mt-2" id="logoPreviewWrap" style="display:none;">
                        <img id="logoPreview" src="" alt="Preview" style="max-width:120px;max-height:120px;object-fit:contain;border-radius:8px;border:1px solid #e9ecef;">
                    </div>
                </div>

                <!-- ── Banner / Cover Photo ───────────────────────────────────── -->
                <div class="col-md-6">
                    <label class="form-label fw-600 small">
                        <i class="fas fa-panorama me-1" style="color:var(--pg-gold);"></i>
                        Banner / Cover Photo
                        <span class="text-muted fw-400">(JPG/PNG/WebP, max 5 MB)</span>
                    </label>

                    <?php if (!empty($park['banner_path'])): ?>
                    <div class="mb-2 p-3 rounded" style="background:#f8f9fa;border:1px solid #e9ecef;">
                        <img src="<?= pg_url('uploads/parks/' . h($park['banner_path'])) ?>"
                             alt="Current banner"
                             style="width:100%;height:100px;object-fit:cover;border-radius:8px;">
                        <div class="form-check mt-2">
                            <input type="checkbox" name="remove_banner" value="1" id="removeBanner" class="form-check-input">
                            <label for="removeBanner" class="form-check-label small text-danger">Remove banner</label>
                        </div>
                    </div>
                    <?php endif; ?>

                    <input type="file" name="banner" id="bannerInput" class="form-control" accept="image/jpeg,image/png,image/webp">
                    <div class="mt-2" id="bannerPreviewWrap" style="display:none;">
                        <img id="bannerPreview" src="" alt="Preview" style="width:100%;max-height:120px;object-fit:cover;border-radius:8px;border:1px solid #e9ecef;">
                    </div>
                </div>

                <!-- Flags -->
                <div class="col-md-6">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" id="parkActive" class="form-check-input"
                               <?= ($park['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label for="parkActive" class="form-check-label small">Active (visible on site)</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input type="checkbox" name="is_featured" value="1" id="parkFeat" class="form-check-input"
                               <?= !empty($park['is_featured']) ? 'checked' : '' ?>>
                        <label for="parkFeat" class="form-check-label small">Featured on Homepage</label>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-gold">
                        <i class="fas fa-save me-1"></i>Save Park
                    </button>
                    <a href="<?= pg_url('admin/memorial_parks.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- ── Parks Table ──────────────────────────────────────────────────────── -->
    <div class="pg-card">
        <div class="table-responsive">
            <table class="table admin-table mb-0">
                <thead>
                    <tr>
                        <th style="width:56px;"></th>
                        <th>Park Name</th>
                        <th>Location</th>
                        <th>Religions</th>
                        <th>Listings</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($parks as $p): ?>
                <tr>
                    <!-- Logo thumbnail -->
                    <td>
                        <?php if (!empty($p['logo_path'])): ?>
                            <img src="<?= pg_url('uploads/parks/' . h($p['logo_path'])) ?>"
                                 alt="<?= h($p['name']) ?>"
                                 style="width:44px;height:44px;object-fit:contain;border-radius:8px;background:#f8f9fa;border:1px solid #e9ecef;padding:3px;">
                        <?php else: ?>
                            <div style="width:44px;height:44px;border-radius:8px;background:#f1f5f9;border:1px solid #e9ecef;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-tree text-muted" style="font-size:.75rem;"></i>
                            </div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <div class="fw-500 small"><?= h($p['name']) ?></div>
                        <?php if ($p['name_zh']): ?>
                            <div class="text-muted" style="font-size:.75rem"><?= h($p['name_zh']) ?></div>
                        <?php endif; ?>
                        <?php if ($p['is_featured']): ?>
                            <span class="badge bg-warning text-dark" style="font-size:.65rem">Featured</span>
                        <?php endif; ?>
                        <?php if (!empty($p['banner_path'])): ?>
                            <span class="badge bg-light text-muted border" style="font-size:.65rem;">
                                <i class="fas fa-panorama me-1"></i>Banner
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= h($p['city']) ?>, <?= h($p['state']) ?></td>
                    <td class="small text-muted"><?= h($p['supported_religions'] ?? '—') ?></td>
                    <td class="small"><?= $p['listing_count'] ?></td>
                    <td>
                        <span class="status-pill <?= $p['is_active'] ? 'active' : 'expired' ?>">
                            <?= $p['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end flex-wrap">
                        <a href="?action=edit&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-gold" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="<?= park_url($p['slug']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank" title="View public page">
                            <i class="fas fa-eye"></i>
                        </a>
                        <!-- Archive / Restore -->
                        <form method="POST" class="d-inline m-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="toggle_active">
                            <input type="hidden" name="_park_id" value="<?= $p['id'] ?>">
                            <button type="submit"
                                    class="btn btn-sm <?= $p['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                                    title="<?= $p['is_active'] ? 'Archive (hide from site)' : 'Restore (show on site)' ?>">
                                <i class="fas fa-<?= $p['is_active'] ? 'archive' : 'undo-alt' ?>"></i>
                            </button>
                        </form>
                        <!-- Delete -->
                        <form method="POST" class="d-inline m-0"
                              onsubmit="return confirm('Delete \'<?= h(addslashes($p['name'])) ?>\'?\n\nThis permanently removes the park and its photos.\nThis cannot be undone.')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="delete">
                            <input type="hidden" name="_park_id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete permanently">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<?php
$extra_scripts = <<<'JS'
<script>
// Live image preview before upload
function previewImage(inputId, previewId, wrapId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.addEventListener('change', function () {
        const file = this.files[0];
        const wrap = document.getElementById(wrapId);
        const img  = document.getElementById(previewId);
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = e => {
                img.src = e.target.result;
                wrap.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            wrap.style.display = 'none';
        }
    });
}
previewImage('logoInput',   'logoPreview',   'logoPreviewWrap');
previewImage('bannerInput', 'bannerPreview', 'bannerPreviewWrap');
</script>
JS;
include INC_PATH . '/footer.php';
?>
