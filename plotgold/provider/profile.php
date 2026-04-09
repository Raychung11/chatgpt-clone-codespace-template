<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_role(ROLE_PROVIDER, '/register.php');

$provider = Database::fetchOne(
    'SELECT p.*, u.full_name, u.email, u.phone FROM providers p
     JOIN users u ON u.id = p.user_id WHERE p.user_id = ?',
    [auth_user_id()]
);
if (!$provider) redirect('/register.php');
$pid = (int)$provider['id'];

// ── Handle POST ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();
    $action = clean($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $businessName = clean($_POST['business_name'] ?? '');
        $bizReg       = clean($_POST['business_reg_no'] ?? '');
        $provType     = clean($_POST['provider_type'] ?? '');
        $description  = clean($_POST['description'] ?? '');
        $website      = clean($_POST['website'] ?? '');
        $whatsapp     = preg_replace('/[^0-9+]/', '', clean($_POST['whatsapp'] ?? ''));

        $allowed = ['funeral_home','transport','florist','memorial_park','catering','clergy','admin_support','multipurpose'];
        if (!in_array($provType, $allowed, true)) $provType = 'funeral_home';

        // Logo upload
        $logoPath = $provider['logo_path'];
        if (!empty($_FILES['logo']['name'])) {
            $uploaded = upload_file($_FILES['logo'], 'uploads/providers/', ['image/jpeg','image/png','image/webp'], 2 * 1024 * 1024);
            if ($uploaded) $logoPath = $uploaded;
        }

        Database::query(
            'UPDATE providers SET business_name=?,business_reg_no=?,provider_type=?,description=?,website=?,whatsapp=?,logo_path=?,updated_at=NOW()
             WHERE id=?',
            [$businessName, $bizReg ?: null, $provType, $description ?: null, $website ?: null, $whatsapp ?: null, $logoPath, $pid]
        );

        // Update user display name + phone
        Database::query(
            'UPDATE users SET full_name=?, phone=?, updated_at=NOW() WHERE id=?',
            [clean($_POST['full_name'] ?? $provider['full_name']), clean($_POST['phone'] ?? ''), auth_user_id()]
        );

        flash_set(FLASH_SUCCESS, 'Profile updated successfully.');
        redirect('provider/profile.php');
    }

    if ($action === 'upload_doc') {
        $docType = clean($_POST['doc_type'] ?? 'other');
        $allowed = ['business_reg','license','insurance','certification','other'];
        if (!in_array($docType, $allowed, true)) $docType = 'other';

        if (!empty($_FILES['document']['name'])) {
            $uploaded = upload_file(
                $_FILES['document'],
                'uploads/documents/',
                ['application/pdf','image/jpeg','image/png'],
                5 * 1024 * 1024
            );
            if ($uploaded) {
                Database::insert(
                    'INSERT INTO provider_documents (provider_id, doc_type, file_path, original_name) VALUES (?,?,?,?)',
                    [$pid, $docType, $uploaded, clean($_FILES['document']['name'])]
                );
                flash_set(FLASH_SUCCESS, 'Document uploaded for review.');
            } else {
                flash_set(FLASH_ERROR, 'Upload failed. Check file type and size (max 5 MB, PDF/JPG/PNG).');
            }
        }
        redirect('provider/profile.php');
    }
}

// Existing documents
$documents = Database::fetchAll(
    'SELECT * FROM provider_documents WHERE provider_id = ? ORDER BY created_at DESC',
    [$pid]
);

$providerTypes = [
    'funeral_home'   => 'Funeral Home',
    'transport'      => 'Transport / Hearse',
    'florist'        => 'Florist',
    'memorial_park'  => 'Memorial Park',
    'catering'       => 'Catering',
    'clergy'         => 'Clergy / Ritual',
    'admin_support'  => 'Admin Support',
    'multipurpose'   => 'Multipurpose / Full Service',
];

$page_title = 'Provider Profile';
$body_class = 'portal-layout';
include INC_PATH . '/header.php';
include INC_PATH . '/nav.php';
?>

<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="portal-content">
    <?= render_flash() ?>
    <h4 class="fw-700 text-navy mb-4">Business Profile</h4>

    <div class="row g-4">
        <!-- Profile Form -->
        <div class="col-lg-8">
            <div class="pg-card p-4 mb-4">
                <h6 class="fw-600 mb-4 pb-2 border-bottom">Business Information</h6>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Business Name <span class="text-danger">*</span></label>
                            <input type="text" name="business_name" class="form-control" required
                                   value="<?= h($provider['business_name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Business Registration No.</label>
                            <input type="text" name="business_reg_no" class="form-control"
                                   value="<?= h($provider['business_reg_no'] ?? '') ?>"
                                   placeholder="e.g. 1234567-X">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Business Type</label>
                            <select name="provider_type" class="form-select">
                                <?php foreach ($providerTypes as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $provider['provider_type'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">WhatsApp Business Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fab fa-whatsapp text-success"></i></span>
                                <input type="tel" name="whatsapp" class="form-control"
                                       value="<?= h($provider['whatsapp'] ?? '') ?>"
                                       placeholder="601XXXXXXXX">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Website</label>
                            <input type="url" name="website" class="form-control"
                                   value="<?= h($provider['website'] ?? '') ?>"
                                   placeholder="https://">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Business Description</label>
                            <textarea name="description" class="form-control" rows="4"
                                      placeholder="Describe your services, experience, and coverage areas…"><?= h($provider['description'] ?? '') ?></textarea>
                        </div>

                        <div class="col-12"><hr class="my-1"></div>
                        <div class="col-12"><h6 class="fw-600 text-muted small text-uppercase">Contact Person</h6></div>

                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control"
                                   value="<?= h($provider['full_name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mobile Number</label>
                            <input type="tel" name="phone" class="form-control"
                                   value="<?= h($provider['phone'] ?? '') ?>">
                        </div>

                        <div class="col-12"><hr class="my-1"></div>

                        <div class="col-12">
                            <label class="form-label">Business Logo <span class="text-muted fw-400">(optional, JPG/PNG, max 2MB)</span></label>
                            <?php if ($provider['logo_path']): ?>
                            <div class="mb-2">
                                <img src="<?= BASE_URL . '/uploads/providers/' . h($provider['logo_path']) ?>"
                                     alt="Logo" style="height:60px;border-radius:6px;border:1px solid var(--pg-border)">
                            </div>
                            <?php endif; ?>
                            <input type="file" name="logo" class="form-control" accept="image/jpeg,image/png,image/webp">
                        </div>

                        <div class="col-12 pt-2">
                            <button type="submit" class="btn btn-gold px-4">Save Profile</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Document Upload -->
            <div class="pg-card p-4">
                <h6 class="fw-600 mb-4 pb-2 border-bottom">Business Documents</h6>
                <p class="text-muted small mb-3">Upload supporting documents for provider verification. Accepted: PDF, JPG, PNG (max 5MB each).</p>

                <form method="POST" enctype="multipart/form-data" class="mb-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="upload_doc">
                    <div class="row g-3">
                        <div class="col-sm-5">
                            <label class="form-label">Document Type</label>
                            <select name="doc_type" class="form-select">
                                <option value="business_reg">Business Registration</option>
                                <option value="license">Operating License</option>
                                <option value="insurance">Insurance Certificate</option>
                                <option value="certification">Certification / Award</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label">File</label>
                            <input type="file" name="document" class="form-control"
                                   accept="application/pdf,image/jpeg,image/png" required>
                        </div>
                        <div class="col-sm-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-outline-gold w-100">Upload</button>
                        </div>
                    </div>
                </form>

                <?php if ($documents): ?>
                <div class="table-responsive">
                    <table class="table table-sm admin-table mb-0">
                        <thead><tr><th>Document</th><th>Type</th><th>Review Status</th><th>Uploaded</th></tr></thead>
                        <tbody>
                        <?php foreach ($documents as $d): ?>
                        <tr>
                            <td>
                                <a href="<?= BASE_URL . '/uploads/documents/' . h($d['file_path']) ?>"
                                   target="_blank" class="small text-gold">
                                    <i class="fas fa-paperclip me-1"></i><?= h($d['original_name'] ?? $d['file_path']) ?>
                                </a>
                            </td>
                            <td class="small text-muted"><?= ucwords(str_replace('_', ' ', $d['doc_type'])) ?></td>
                            <td>
                                <span class="status-pill <?= $d['status'] === 'approved' ? 'active' : ($d['status'] === 'rejected' ? 'rejected' : 'pending') ?>">
                                    <?= ucfirst($d['status']) ?>
                                </span>
                            </td>
                            <td class="small text-muted"><?= time_ago($d['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="small text-muted">No documents uploaded yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Approval Status -->
            <div class="pg-card p-4 mb-3">
                <h6 class="fw-600 mb-3">Approval Status</h6>
                <?php
                $statusMap = [
                    'approved'  => ['active',   'fa-check-circle',   'text-success', 'Your account is approved. You can receive quote requests.'],
                    'pending'   => ['pending',   'fa-hourglass-half', 'text-warning', 'Your account is under review. Typically 1–2 business days.'],
                    'suspended' => ['rejected',  'fa-ban',            'text-danger',  'Account suspended. Please contact support.'],
                    'rejected'  => ['rejected',  'fa-times-circle',   'text-danger',  'Application rejected. Please contact support.'],
                ];
                $s = $statusMap[$provider['approval_status']] ?? $statusMap['pending'];
                ?>
                <div class="d-flex align-items-start gap-3">
                    <i class="fas <?= $s[1] ?> fa-lg <?= $s[2] ?> mt-1"></i>
                    <div>
                        <div class="fw-600 <?= $s[2] ?>"><?= ucfirst($provider['approval_status']) ?></div>
                        <div class="small text-muted"><?= $s[3] ?></div>
                    </div>
                </div>
            </div>

            <!-- Rating -->
            <?php if ($provider['total_reviews'] > 0): ?>
            <div class="pg-card p-4 mb-3">
                <h6 class="fw-600 mb-3">Rating & Reviews</h6>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="fs-3 fw-700 text-navy"><?= number_format($provider['rating'], 1) ?></div>
                    <div>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?= $i <= round($provider['rating']) ? 'text-warning' : 'text-muted' ?>" style="font-size:.85rem"></i>
                        <?php endfor; ?>
                        <div class="text-muted small"><?= number_format($provider['total_reviews']) ?> reviews</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Tips -->
            <div class="pg-card p-4" style="border-left:3px solid var(--pg-gold)">
                <h6 class="fw-600 mb-3"><i class="fas fa-lightbulb text-gold me-2"></i>Profile Tips</h6>
                <ul class="list-unstyled small text-muted mb-0">
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Add a business logo to build trust</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Upload your business registration</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Set service areas for targeted leads</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>List all services with clear pricing</li>
                    <li><i class="fas fa-check text-success me-2"></i>Keep WhatsApp number up to date</li>
                </ul>
            </div>
        </div>
    </div>
</div>
</div>

<?php include INC_PATH . '/footer.php'; ?>
