<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_role(ROLE_SELLER, '/register.php?type=seller');

$seller = Database::fetchOne('SELECT * FROM sellers WHERE user_id = ?', [auth_user_id()]);
if (!$seller) redirect('/register.php?type=seller');

$parks        = Database::fetchAll('SELECT id, name, city, state FROM memorial_parks WHERE is_active = 1 ORDER BY name');
$listingTypes = Database::fetchAll('SELECT id, label_en FROM listing_types WHERE is_active = 1 ORDER BY sort_order');
$religions    = Database::fetchAll('SELECT id, label_en FROM religion_categories WHERE is_active = 1 ORDER BY sort_order');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();

    $title       = clean($_POST['title']       ?? '');
    $parkId      = clean_int($_POST['park_id'] ?? 0);
    $typeId      = clean_int($_POST['listing_type_id'] ?? 0);
    $religionId  = clean_int($_POST['religion_id'] ?? 0);
    $city        = clean($_POST['city']        ?? '');
    $state       = clean($_POST['state']       ?? '');
    $blockNo     = clean($_POST['block_no']    ?? '');
    $rowNo       = clean($_POST['row_no']      ?? '');
    $lotNo       = clean($_POST['lot_no']      ?? '');
    $askingPrice = clean_float($_POST['asking_price']  ?? 0);
    $transferFee = clean_float($_POST['transfer_fee']  ?? 0);
    $maintFee    = clean_float($_POST['annual_maintenance_fee'] ?? 0);
    $maintStatus = clean($_POST['maintenance_status'] ?? 'unknown');
    $ownershipType = clean($_POST['ownership_type'] ?? 'unknown');
    $isTransferable = (int)!empty($_POST['is_transferable']);
    $sellerIntent   = clean($_POST['seller_intent']  ?? 'direct_sale');
    $urgency        = clean($_POST['urgency_level']  ?? 'standard');
    $description    = clean($_POST['public_description'] ?? '');
    $fsNotes        = clean($_POST['fengshui_notes'] ?? '');

    if (!$title || !$typeId || !$state) {
        $error = 'Title, listing type, and state are required.';
    } else {
        // Generate slug and code
        $baseSlug = slug($title) . '-' . strtolower(bin2hex(random_bytes(3)));
        $code     = generate_listing_code();
        $uuid     = pg_uuid();

        $listingId = Database::insert(
            'INSERT INTO listings (uuid, listing_code, seller_id, park_id, listing_type_id, religion_id,
             city, state, block_no, row_no, lot_no, title, slug, public_description, fengshui_notes,
             asking_price, transfer_fee, annual_maintenance_fee, maintenance_status,
             ownership_type, is_transferable, seller_intent, urgency_level, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $uuid, $code, $seller['id'],
                $parkId ?: null, $typeId ?: null, $religionId ?: null,
                $city, $state, $blockNo, $rowNo, $lotNo,
                $title, $baseSlug, $description, $fsNotes,
                $askingPrice ?: null, $transferFee ?: null, $maintFee ?: null, $maintStatus,
                $ownershipType, $isTransferable, $sellerIntent, $urgency,
                'pending_review',
            ]
        );

        // Insert verification checks skeleton
        foreach (VERIFICATION_CHECKS as $key => $cfg) {
            Database::query(
                'INSERT IGNORE INTO listing_verification_checks (listing_id, check_key, check_label, weight, status) VALUES (?, ?, ?, ?, ?)',
                [$listingId, $key, $cfg['label'], $cfg['weight'], 'pending']
            );
        }

        // Log status change
        Database::query(
            'INSERT INTO listing_status_logs (listing_id, from_status, to_status, changed_by, reason) VALUES (?, ?, ?, ?, ?)',
            [$listingId, null, 'pending_review', auth_user_id(), 'Initial submission']
        );

        // Handle image uploads
        if (!empty($_FILES['images']['name'][0])) {
            $destDir = UPLOAD_PATH . '/listings';
            foreach ($_FILES['images']['name'] as $i => $name) {
                if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $file = [
                    'name'     => $name,
                    'tmp_name' => $_FILES['images']['tmp_name'][$i],
                    'type'     => $_FILES['images']['type'][$i],
                    'error'    => $_FILES['images']['error'][$i],
                    'size'     => $_FILES['images']['size'][$i],
                ];
                $res = upload_file($file, $destDir, ALLOWED_IMAGE_TYPES);
                if ($res['success']) {
                    Database::query(
                        'INSERT INTO listing_media (listing_id, media_type, file_path, original_name, mime_type, file_size, is_primary) VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [$listingId, 'image', $res['filename'], $name, $res['mime_type'], $res['size'], $i === 0 ? 1 : 0]
                    );
                }
            }
        }

        // Handle document uploads
        $docTypes = ['ownership_cert', 'ic_copy', 'park_receipt', 'maintenance_receipt'];
        foreach ($docTypes as $docType) {
            if (!empty($_FILES[$docType]['name']) && $_FILES[$docType]['error'] === UPLOAD_ERR_OK) {
                $res = upload_file($_FILES[$docType], UPLOAD_PATH . '/documents', ALLOWED_DOC_TYPES);
                if ($res['success']) {
                    Database::query(
                        'INSERT INTO listing_documents (listing_id, doc_type, file_path, original_name, mime_type, file_size) VALUES (?,?,?,?,?,?)',
                        [$listingId, $docType, $res['filename'], $_FILES[$docType]['name'], $res['mime_type'], $res['size']]
                    );
                }
            }
        }

        Database::query('UPDATE sellers SET total_listings = total_listings + 1 WHERE id = ?', [$seller['id']]);
        activity_log(auth_user_id(), 'listing_submitted', 'listings', $listingId, "New listing: $title");

        flash_set(FLASH_SUCCESS, 'Listing submitted for review! Our team will verify it within 3–5 business days.');
        redirect('seller/dashboard.php');
    }
}

$page_title = 'Submit New Listing';
include INC_PATH . '/header.php';
?>
<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="portal-content">
    <?= render_flash() ?>
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="<?= pg_url('seller/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i></a>
        <h4 class="fw-700 text-navy mb-0">Submit New Listing</h4>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
        <?= csrf_field() ?>
        <div class="row g-4">

            <!-- BASIC INFO -->
            <div class="col-lg-8">
                <div class="pg-card p-4 mb-4">
                    <h6 class="fw-600 text-muted text-uppercase mb-3" style="font-size:.75rem;letter-spacing:.08em">Listing Details</h6>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Listing Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. Nirvana Semenyih — Block A Row 5 Lot 12 (Buddhist)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Listing Type <span class="text-danger">*</span></label>
                            <select name="listing_type_id" class="form-select" required>
                                <option value="">— Select Type —</option>
                                <?php foreach ($listingTypes as $t): ?>
                                    <option value="<?= (int)$t['id'] ?>"><?= h($t['label_en']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Religion / Category</label>
                            <select name="religion_id" class="form-select">
                                <option value="">— Select —</option>
                                <?php foreach ($religions as $r): ?>
                                    <option value="<?= (int)$r['id'] ?>"><?= h($r['label_en']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Memorial Park</label>
                            <select name="park_id" class="form-select">
                                <option value="">— Not listed / Unknown —</option>
                                <?php foreach ($parks as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>"><?= h($p['name']) ?> (<?= h($p['city']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Block No.</label>
                            <input type="text" name="block_no" class="form-control" placeholder="e.g. A">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Row No.</label>
                            <input type="text" name="row_no" class="form-control" placeholder="e.g. 5">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Lot No.</label>
                            <input type="text" name="lot_no" class="form-control" placeholder="e.g. 12">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" placeholder="e.g. Semenyih">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">State <span class="text-danger">*</span></label>
                            <select name="state" class="form-select" required>
                                <option value="">— Select State —</option>
                                <?php foreach (MY_STATES as $s): ?>
                                    <option value="<?= h($s) ?>"><?= h($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Public Description</label>
                            <textarea name="public_description" class="form-control" rows="4" placeholder="Describe the plot — location, orientation, surroundings, reason for selling…"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Orientation / Feng Shui Notes <span class="text-muted small">(optional)</span></label>
                            <input type="text" name="fengshui_notes" class="form-control" placeholder="e.g. South-facing, good feng shui, near water feature…">
                        </div>
                    </div>
                </div>

                <!-- Pricing -->
                <div class="pg-card p-4 mb-4">
                    <h6 class="fw-600 text-muted text-uppercase mb-3" style="font-size:.75rem;letter-spacing:.08em">Pricing & Fees</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Asking Price (RM)</label>
                            <input type="number" name="asking_price" class="form-control" min="0" step="100" placeholder="e.g. 15000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Transfer Fee (RM)</label>
                            <input type="number" name="transfer_fee" class="form-control" min="0" step="100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Annual Maintenance (RM)</label>
                            <input type="number" name="annual_maintenance_fee" class="form-control" min="0" step="10">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Maintenance Status</label>
                            <select name="maintenance_status" class="form-select">
                                <option value="unknown">Unknown</option>
                                <option value="paid">Paid / Up to date</option>
                                <option value="overdue">Overdue</option>
                                <option value="na">N/A</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ownership Type</label>
                            <select name="ownership_type" class="form-select">
                                <option value="unknown">Unknown</option>
                                <option value="freehold">Freehold</option>
                                <option value="perpetual">Perpetual</option>
                                <option value="leasehold">Leasehold</option>
                                <option value="renewable">Renewable</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Seller Intent</label>
                            <select name="seller_intent" class="form-select">
                                <option value="direct_sale">Direct Sale</option>
                                <option value="open_to_offers">Open to Offers</option>
                                <option value="inquiry_only">Inquiry Only</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Urgency</label>
                            <select name="urgency_level" class="form-select">
                                <option value="standard">Standard</option>
                                <option value="moderate">Moderate</option>
                                <option value="urgent">Urgent</option>
                                <option value="immediate">Immediate / ASAP</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="is_transferable" value="1" id="transferable" class="form-check-input">
                                <label for="transferable" class="form-check-label small">Plot is transferable (subject to park approval)</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Photos -->
                <div class="pg-card p-4 mb-4">
                    <h6 class="fw-600 text-muted text-uppercase mb-3" style="font-size:.75rem;letter-spacing:.08em">Photos</h6>
                    <p class="text-muted small mb-3">Upload clear photos of the plot, surrounding area, and any signage. Max <?= MAX_LISTING_IMAGES ?> images, up to <?= MAX_UPLOAD_SIZE / 1048576 ?>MB each.</p>
                    <input type="file" name="images[]" id="listingImages" class="form-control" multiple accept="image/jpeg,image/png,image/webp" onchange="previewImages(this, 'imgPreview')">
                    <div id="imgPreview" class="d-flex flex-wrap gap-2 mt-2"></div>
                </div>

                <!-- Documents -->
                <div class="pg-card p-4">
                    <h6 class="fw-600 text-muted text-uppercase mb-3" style="font-size:.75rem;letter-spacing:.08em">Ownership Documents <span class="text-muted fw-400">(for verification)</span></h6>
                    <p class="text-muted small mb-3">Uploading documents speeds up verification. Documents are kept private and only accessible to our verification team.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small">Ownership Certificate / Receipt</label>
                            <input type="file" name="ownership_cert" class="form-control form-control-sm" accept="image/jpeg,image/png,application/pdf">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">IC Copy (Identity)</label>
                            <input type="file" name="ic_copy" class="form-control form-control-sm" accept="image/jpeg,image/png,application/pdf">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Park Payment Receipt</label>
                            <input type="file" name="park_receipt" class="form-control form-control-sm" accept="image/jpeg,image/png,application/pdf">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Maintenance Fee Receipt</label>
                            <input type="file" name="maintenance_receipt" class="form-control form-control-sm" accept="image/jpeg,image/png,application/pdf">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar info -->
            <div class="col-lg-4">
                <div class="pg-card p-4 mb-3" style="background:var(--pg-gold-pale)">
                    <h6 class="fw-600 mb-2"><i class="fas fa-info-circle me-2 text-gold"></i>What Happens Next?</h6>
                    <ol class="small text-muted ps-3">
                        <li class="mb-1">Your listing is submitted for review.</li>
                        <li class="mb-1">Our team verifies your documents (3–5 business days).</li>
                        <li class="mb-1">Listing goes live with a verification badge.</li>
                        <li class="mb-1">Enquiries arrive in your dashboard.</li>
                    </ol>
                </div>
                <div class="pg-card p-4">
                    <button type="submit" class="btn btn-gold w-100 mb-2">
                        <i class="fas fa-paper-plane me-2"></i>Submit for Review
                    </button>
                    <a href="<?= pg_url('seller/dashboard.php') ?>" class="btn btn-outline-secondary w-100 btn-sm">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>
</div>
<?php include INC_PATH . '/footer.php'; ?>
