<?php
require_once __DIR__ . '/../inc/bootstrap.php';
require_admin();

$listingId = clean_int($_GET['id'] ?? 0);
if (!$listingId) redirect('admin/listings.php');

$listing = Database::fetchOne(
    'SELECT l.*, lt.label_en AS type_label, mp.name AS park_name, mp.slug AS park_slug,
            rc.label_en AS religion_label, up.full_name AS seller_name, u.email AS seller_email, u.phone AS seller_phone
     FROM listings l
     LEFT JOIN listing_types lt ON lt.id = l.listing_type_id
     LEFT JOIN memorial_parks mp ON mp.id = l.park_id
     LEFT JOIN religion_categories rc ON rc.id = l.religion_id
     LEFT JOIN sellers s ON s.id = l.seller_id
     LEFT JOIN users u ON u.id = s.user_id
     LEFT JOIN user_profiles up ON up.user_id = s.user_id
     WHERE l.id = ?',
    [$listingId]
);
if (!$listing) redirect('admin/listings.php');

$checks   = Database::fetchAll('SELECT * FROM listing_verification_checks WHERE listing_id = ? ORDER BY weight DESC', [$listingId]);
$media    = Database::fetchAll('SELECT * FROM listing_media WHERE listing_id = ? ORDER BY is_primary DESC, sort_order', [$listingId]);
$docs     = Database::fetchAll('SELECT * FROM listing_documents WHERE listing_id = ?', [$listingId]);
$notes    = Database::fetchAll('SELECT n.*, up.full_name AS author_name FROM listing_notes n LEFT JOIN user_profiles up ON up.user_id = n.author_id WHERE n.listing_id = ? ORDER BY n.created_at DESC', [$listingId]);
$statusLog = Database::fetchAll('SELECT ls.*, up.full_name AS changer_name FROM listing_status_logs ls LEFT JOIN user_profiles up ON up.user_id = ls.changed_by WHERE ls.listing_id = ? ORDER BY ls.created_at DESC LIMIT 10', [$listingId]);

// POST: update check, add note, change status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();
    $action = clean($_POST['action'] ?? '');

    if ($action === 'update_check') {
        $checkKey = clean($_POST['check_key'] ?? '');
        $status   = in_array($_POST['check_status'] ?? '', ['passed','failed','na','skipped']) ? $_POST['check_status'] : 'pending';
        $notes_   = clean($_POST['check_notes'] ?? '');
        Database::query(
            'UPDATE listing_verification_checks SET status = ?, checked_by = ?, checked_at = NOW(), notes = ? WHERE listing_id = ? AND check_key = ?',
            [$status, auth_user_id(), $notes_, $listingId, $checkKey]
        );

        // Recalculate score
        $allChecks = Database::fetchAll('SELECT check_key, weight, status FROM listing_verification_checks WHERE listing_id = ?', [$listingId]);
        $checksMap = array_column($allChecks, null, 'check_key');
        $score     = calc_verification_score($checksMap);
        $badge     = verification_badge_from_score($score);
        Database::query(
            'UPDATE listings SET verification_score = ?, badge_status = ? WHERE id = ?',
            [$score, $badge, $listingId]
        );
        activity_log(auth_user_id(), 'check_updated', 'listings', $listingId, "Check '$checkKey' set to $status");
        flash_set(FLASH_SUCCESS, 'Verification check updated.');
        redirect('admin/listing_review.php?id=' . $listingId);
    }

    if ($action === 'add_note') {
        $content = clean($_POST['note_content'] ?? '');
        if ($content) {
            Database::query(
                'INSERT INTO listing_notes (listing_id, author_id, note_type, content, is_internal) VALUES (?, ?, ?, ?, 1)',
                [$listingId, auth_user_id(), 'admin', $content]
            );
            flash_set(FLASH_SUCCESS, 'Note added.');
        }
        redirect('admin/listing_review.php?id=' . $listingId);
    }

    if ($action === 'change_status') {
        $newStatus = clean($_POST['new_status'] ?? '');
        $reason    = clean($_POST['status_reason'] ?? '');
        $validStatuses = ['active', 'pending_review', 'rejected', 'withdrawn', 'sold', 'expired'];
        if (in_array($newStatus, $validStatuses)) {
            $oldStatus = $listing['status'];
            Database::query('UPDATE listings SET status = ?, listed_at = IF(listed_at IS NULL AND ? = ?, NOW(), listed_at) WHERE id = ?',
                [$newStatus, $newStatus, 'active', $listingId]);
            Database::query(
                'INSERT INTO listing_status_logs (listing_id, from_status, to_status, changed_by, reason) VALUES (?,?,?,?,?)',
                [$listingId, $oldStatus, $newStatus, auth_user_id(), $reason]
            );
            activity_log(auth_user_id(), 'listing_status_changed', 'listings', $listingId, "$oldStatus → $newStatus");
            flash_set(FLASH_SUCCESS, 'Listing status updated to ' . ucwords(str_replace('_', ' ', $newStatus)) . '.');
            redirect('admin/listing_review.php?id=' . $listingId);
        }
    }
}

// Reload checks after update
$checks    = Database::fetchAll('SELECT * FROM listing_verification_checks WHERE listing_id = ? ORDER BY weight DESC', [$listingId]);
$checksMap = array_column($checks, null, 'check_key');
$score     = calc_verification_score($checksMap);

$page_title = 'Review: ' . $listing['listing_code'];
$body_class = 'admin-layout';
include INC_PATH . '/header.php';
?>
<div class="d-flex">
<?php include __DIR__ . '/inc/sidebar.php'; ?>
<div class="admin-main">
    <?= render_flash() ?>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="<?= pg_url('admin/listings.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i></a>
            <div>
                <h4 class="fw-700 text-navy mb-0"><?= h($listing['title']) ?></h4>
                <p class="text-muted small mb-0"><?= h($listing['listing_code']) ?> &middot; <?= h($listing['type_label']) ?></p>
            </div>
        </div>
        <span class="status-pill <?= $listing['status'] ?>"><?= ucwords(str_replace('_',' ',$listing['status'])) ?></span>
    </div>

    <div class="row g-4">
        <!-- LEFT: Verification Checklist -->
        <div class="col-lg-5">
            <div class="pg-card mb-4">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-600 mb-0"><i class="fas fa-shield-alt me-2 text-gold"></i>Verification Checklist</h6>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:80px">
                            <div class="verification-bar">
                                <div class="bar-fill <?= $score >= 90 ? 'verified' : ($score >= 60 ? 'partial' : 'pending') ?>"
                                     style="width:<?= $score ?>%"></div>
                            </div>
                        </div>
                        <span class="small fw-600"><?= $score ?>%</span>
                    </div>
                </div>
                <div class="p-3">
                    <?php foreach ($checks as $c): ?>
                    <div class="border rounded-2 p-3 mb-2">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="small fw-600"><?= h($c['check_label'] ?? $c['check_key']) ?></div>
                                <div class="text-muted" style="font-size:.72rem">Weight: <?= $c['weight'] ?>%</div>
                            </div>
                            <?php
                            $statusColor = ['passed' => 'success', 'failed' => 'danger', 'na' => 'secondary', 'skipped' => 'secondary', 'pending' => 'warning'];
                            ?>
                            <span class="badge bg-<?= $statusColor[$c['status']] ?? 'secondary' ?>"><?= ucfirst($c['status']) ?></span>
                        </div>
                        <form method="POST" class="row g-1 align-items-center">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_check">
                            <input type="hidden" name="check_key" value="<?= h($c['check_key']) ?>">
                            <div class="col-5">
                                <select name="check_status" class="form-select form-select-sm">
                                    <?php foreach (['pending','passed','failed','na','skipped'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $c['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-5">
                                <input type="text" name="check_notes" class="form-control form-control-sm" placeholder="Notes…" value="<?= h($c['notes'] ?? '') ?>">
                            </div>
                            <div class="col-2">
                                <button type="submit" class="btn btn-sm btn-outline-gold w-100">✓</button>
                            </div>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Change Status -->
            <div class="pg-card p-3 mb-4">
                <h6 class="fw-600 mb-3">Change Status</h6>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_status">
                    <div class="mb-2">
                        <select name="new_status" class="form-select form-select-sm">
                            <?php foreach (['active','pending_review','rejected','withdrawn','sold','expired'] as $s): ?>
                                <option value="<?= $s ?>" <?= $listing['status'] === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="status_reason" class="form-control form-control-sm" placeholder="Reason (optional)">
                    </div>
                    <button type="submit" class="btn btn-gold btn-sm w-100">Update Status</button>
                </form>
            </div>

            <!-- Add Note -->
            <div class="pg-card p-3 mb-4">
                <h6 class="fw-600 mb-3">Internal Notes</h6>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_note">
                    <textarea name="note_content" class="form-control form-control-sm mb-2" rows="3" placeholder="Add an internal note…"></textarea>
                    <button type="submit" class="btn btn-outline-secondary btn-sm w-100">Add Note</button>
                </form>
                <?php foreach ($notes as $n): ?>
                <div class="border-top pt-2 mt-2">
                    <div class="small fw-500"><?= h($n['author_name'] ?? 'Admin') ?></div>
                    <div class="small text-muted"><?= h($n['content']) ?></div>
                    <div class="text-muted" style="font-size:.72rem"><?= time_ago($n['created_at']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- RIGHT: Listing Details -->
        <div class="col-lg-7">
            <!-- Listing info summary -->
            <div class="pg-card p-4 mb-4">
                <h6 class="fw-600 mb-3">Listing Information</h6>
                <div class="row">
                    <div class="col-md-6">
                        <table class="table listing-spec-table table-sm">
                            <tr><td>Code</td><td class="fw-500"><?= h($listing['listing_code']) ?></td></tr>
                            <tr><td>Type</td><td><?= h($listing['type_label'] ?? '—') ?></td></tr>
                            <tr><td>Religion</td><td><?= h($listing['religion_label'] ?? '—') ?></td></tr>
                            <tr><td>Park</td><td><?= h($listing['park_name'] ?? '—') ?></td></tr>
                            <tr><td>Block/Row/Lot</td><td><?= implode('/', array_filter([h($listing['block_no']), h($listing['row_no']), h($listing['lot_no'])])) ?: '—' ?></td></tr>
                            <tr><td>Location</td><td><?= h($listing['city']) ?>, <?= h($listing['state']) ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table listing-spec-table table-sm">
                            <tr><td>Price</td><td class="fw-600"><?= format_currency($listing['asking_price']) ?></td></tr>
                            <tr><td>Transfer Fee</td><td><?= format_currency($listing['transfer_fee']) ?></td></tr>
                            <tr><td>Maintenance</td><td><?= ucfirst($listing['maintenance_status'] ?? '—') ?></td></tr>
                            <tr><td>Ownership</td><td><?= ucfirst(str_replace('_',' ',$listing['ownership_type'] ?? '—')) ?></td></tr>
                            <tr><td>Transferable</td><td><?= $listing['is_transferable'] ? '<span class="text-success">Yes</span>' : '<span class="text-warning">Check Required</span>' ?></td></tr>
                            <tr><td>Intent</td><td><?= ucwords(str_replace('_',' ',$listing['seller_intent'] ?? '—')) ?></td></tr>
                        </table>
                    </div>
                </div>
                <?php if ($listing['public_description']): ?>
                <div class="border-top pt-3 mt-1">
                    <div class="small text-muted fw-600 mb-1">Description</div>
                    <p class="small text-muted mb-0"><?= h(substr($listing['public_description'], 0, 300)) ?>…</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Seller info -->
            <div class="pg-card p-3 mb-4">
                <h6 class="fw-600 mb-2">Seller</h6>
                <div class="d-flex gap-2 align-items-center">
                    <div class="nav-avatar"><i class="fas fa-user"></i></div>
                    <div>
                        <div class="small fw-500"><?= h($listing['seller_name'] ?? '—') ?></div>
                        <div class="text-muted" style="font-size:.78rem"><?= h($listing['seller_email'] ?? '') ?> &middot; <?= h($listing['seller_phone'] ?? '') ?></div>
                    </div>
                </div>
            </div>

            <!-- Media -->
            <?php if ($media): ?>
            <div class="pg-card p-3 mb-4">
                <h6 class="fw-600 mb-2">Media (<?= count($media) ?>)</h6>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($media as $m): ?>
                        <a href="<?= BASE_URL . '/uploads/listings/' . h($m['file_path']) ?>" target="_blank">
                            <img src="<?= BASE_URL . '/uploads/listings/' . h($m['file_path']) ?>" class="rounded-2" style="width:80px;height:60px;object-fit:cover" alt="">
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Documents -->
            <?php if ($docs): ?>
            <div class="pg-card p-3 mb-4">
                <h6 class="fw-600 mb-2">Documents</h6>
                <?php foreach ($docs as $d): ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <div class="small fw-500"><?= ucwords(str_replace('_', ' ', $d['doc_type'])) ?></div>
                        <div class="text-muted" style="font-size:.72rem"><?= h($d['original_name']) ?></div>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="status-pill <?= $d['status'] ?>"><?= ucfirst($d['status']) ?></span>
                        <a href="<?= BASE_URL . '/uploads/documents/' . h($d['file_path']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="fas fa-eye"></i></a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Status history -->
            <div class="pg-card p-3">
                <h6 class="fw-600 mb-2">Status History</h6>
                <?php foreach ($statusLog as $log): ?>
                <div class="d-flex gap-2 py-2 border-bottom align-items-start">
                    <div class="small text-muted" style="white-space:nowrap"><?= format_date($log['created_at'], 'd M H:i') ?></div>
                    <div>
                        <span class="status-pill <?= $log['from_status'] ?>"><?= ucwords(str_replace('_',' ',$log['from_status'] ?? 'new')) ?></span>
                        <i class="fas fa-arrow-right mx-2 text-muted" style="font-size:.7rem"></i>
                        <span class="status-pill <?= $log['to_status'] ?>"><?= ucwords(str_replace('_',' ',$log['to_status'])) ?></span>
                        <div class="text-muted" style="font-size:.72rem"><?= h($log['reason'] ?? '') ?> <?= $log['changer_name'] ? '— ' . h($log['changer_name']) : '' ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
</div>
<?php include INC_PATH . '/footer.php'; ?>
