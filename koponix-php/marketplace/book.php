<?php
// ============================================================
//  KOPONIX – Book a Service
// ============================================================
require_once __DIR__ . '/../layout.php';

$seller_id    = trim($_GET['id'] ?? '');
$seller       = $seller_id ? get_seller_by_id($seller_id) : null;
$not_available = !$seller || $seller['status'] !== 'active';

$member = current_member();
$errors = [];
$success = false;

// ── Handle POST ───────────────────────────────────────────────
if (!$not_available && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $buyer_name    = trim($_POST['buyer_name']    ?? '');
    $buyer_contact = trim($_POST['buyer_contact'] ?? '');
    $booking_date  = trim($_POST['booking_date']  ?? '');
    $booking_time  = trim($_POST['booking_time']  ?? '');
    $notes         = trim($_POST['notes']         ?? '');

    if (!$buyer_name)    $errors[] = 'Your name is required.';
    if (!$buyer_contact) $errors[] = 'Contact number or email is required.';

    // Validate date is not in the past
    if ($booking_date && strtotime($booking_date) < strtotime('today')) {
        $errors[] = 'Booking date cannot be in the past.';
    }

    if (empty($errors)) {
        $booking_id = save_booking([
            'seller_id'     => $seller['id'],
            'seller_kop_id' => $seller['koperasi_id'],
            'seller_name'   => $seller['name'],
            'service_title' => $seller['service_title'],
            'category'      => $seller['category'],
            'buyer_kop_id'  => $member ? $member['koperasi_id'] : '',
            'buyer_name'    => $buyer_name,
            'buyer_contact' => $buyer_contact,
            'booking_date'  => $booking_date ?: null,
            'booking_time'  => $booking_time,
            'notes'         => $notes,
        ]);
        $booking = get_booking($booking_id);
        notify_new_booking($booking);
        $success = true;
    }
}

$icons  = cat_icons();
$colors = cat_colors();
$icon   = !$not_available ? ($icons[$seller['category']]  ?? '⭐')    : '⭐';
$color  = !$not_available ? ($colors[$seller['category']] ?? '#607d8b') : '#607d8b';

html_head('Book Service' . (!$not_available ? ' — ' . $seller['service_title'] : ''));
html_body_open();
?>

<div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= MARKET_URL ?>/" class="btn btn-sm btn-outline-secondary">← Browse</a>
    <div class="page-title mb-0">📅 Book a Service</div>
</div>

<?php if ($not_available): ?>
<div class="card p-4 text-center" style="max-width:500px;margin:auto">
    <div style="font-size:2.5rem">🔍</div>
    <h5 class="mt-2">Listing Not Available</h5>
    <p class="text-muted">This listing could not be found or is no longer accepting bookings.</p>
    <a href="<?= MARKET_URL ?>/" class="btn btn-primary mt-2">Browse Other Services</a>
</div>

<?php elseif ($success): ?>
<!-- ── Booking Confirmed ────────────────────────────────────── -->
<div class="card p-4 text-center" style="max-width:540px;margin:auto;border-radius:16px">
    <div style="font-size:3rem;margin-bottom:.5rem">✅</div>
    <h4 style="color:#1e8449;font-weight:800">Booking Request Sent!</h4>
    <p class="text-muted">Your booking request for <strong><?= e($seller['service_title']) ?></strong> has been submitted.</p>
    <p class="text-muted small">The provider will review and confirm your booking shortly.
    <?php if ($member): ?>A confirmation will also appear in your <a href="<?= PORTAL_URL ?>/?tab=bookings">My Bookings</a> page.<?php endif; ?>
    </p>
    <div class="d-flex gap-2 justify-content-center mt-3 flex-wrap">
        <?php if ($member): ?>
            <a href="<?= PORTAL_URL ?>/?tab=bookings" class="btn btn-primary">📋 View My Bookings</a>
        <?php endif; ?>
        <a href="<?= MARKET_URL ?>/" class="btn btn-outline-secondary">Browse More Services</a>
    </div>
</div>

<?php else: ?>
<div class="row g-4">

    <!-- ── Booking Form ────────────────────────────────────── -->
    <div class="col-lg-7">
        <div class="card p-4">
            <?php if ($errors): ?>
                <div class="alert alert-danger py-2 mb-3">
                    <ul class="mb-0 small"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <h5 class="mb-4" style="font-weight:700;color:#1a3a52">Your Booking Details</h5>

            <form method="post">
                <?= csrf_field() ?>
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Your Full Name *</label>
                        <input type="text" name="buyer_name" class="form-control"
                            value="<?= e($member ? $member['name'] : ($_POST['buyer_name'] ?? '')) ?>"
                            placeholder="e.g. Ali bin Ahmad" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Contact (Phone / WhatsApp) *</label>
                        <?php
                            $prefill_contact = $_POST['buyer_contact']
                                ?? ($member ? ($member['phone'] ?? '') : '')
                                ?: ($member ? ($member['email'] ?? '') : '');
                        ?>
                        <input type="text" name="buyer_contact" class="form-control"
                            value="<?= e($prefill_contact) ?>"
                            placeholder="e.g. 0123456789" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Preferred Date</label>
                        <input type="date" name="booking_date" class="form-control"
                            value="<?= e($_POST['booking_date'] ?? '') ?>"
                            min="<?= date('Y-m-d') ?>">
                        <div class="small text-muted mt-1">Leave blank if flexible</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Preferred Time</label>
                        <input type="text" name="booking_time" class="form-control"
                            value="<?= e($_POST['booking_time'] ?? '') ?>"
                            placeholder="e.g. 10am–12pm or Anytime">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Additional Notes</label>
                        <textarea name="notes" class="form-control" rows="4"
                            placeholder="Describe what you need, any special requirements, location details, etc."><?= e($_POST['notes'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12">
                        <div class="p-3 rounded" style="background:#f0f4f8;font-size:.85rem;color:#555">
                            💡 Your booking will be sent as a <strong>request</strong>. The provider will confirm
                            availability and contact you directly to arrange the details and payment.
                        </div>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary px-4">📅 Submit Booking Request</button>
                        <a href="<?= MARKET_URL ?>/" class="btn btn-outline-secondary ms-2">Cancel</a>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- ── Listing Summary Card ────────────────────────────── -->
    <div class="col-lg-5">
        <div class="card overflow-hidden" style="border-radius:14px">
            <?php if (!empty($seller['image'])): ?>
                <div style="height:160px;overflow:hidden">
                    <img src="<?= e(img_url($seller['image'])) ?>" style="width:100%;height:100%;object-fit:cover">
                </div>
            <?php else: ?>
                <div style="height:80px;background:linear-gradient(135deg,<?= $color ?>,<?= $color ?>bb);
                            display:flex;align-items:center;padding:0 1.2rem;gap:.75rem">
                    <span style="font-size:2rem"><?= $icon ?></span>
                    <?= cat_badge($seller['category']) ?>
                </div>
            <?php endif; ?>

            <div class="p-3">
                <?php if (!empty($seller['image'])): ?>
                    <?= cat_badge($seller['category']) ?>
                <?php endif; ?>
                <h5 class="mt-2 mb-1" style="font-weight:700;color:#1a3a52"><?= e($seller['service_title']) ?></h5>
                <div class="small mb-1">👤 <strong><?= e($seller['name']) ?></strong></div>
                <div class="small text-muted mb-1">📍 <?= e($seller['area']) ?></div>
                <div class="small text-muted mb-1">💰 <?= e($seller['price_range']) ?></div>
                <?php if ($seller['availability']): ?>
                    <div class="small text-muted mb-1">🕐 <?= e($seller['availability']) ?></div>
                <?php endif; ?>
                <?php if ($seller['experience']): ?>
                    <div class="small text-muted mb-2">🏅 <?= e($seller['experience']) ?> experience</div>
                <?php endif; ?>
                <div class="small text-muted border-top pt-2 mt-2"
                     style="line-height:1.6"><?= e(substr($seller['description'], 0, 200)) ?>…</div>

                <?php if ($seller['gallery1'] || $seller['gallery2']): ?>
                <div class="d-flex gap-1 mt-2">
                    <?php foreach (array_filter([$seller['gallery1'], $seller['gallery2']]) as $gp): ?>
                        <img src="<?= e(img_url($gp)) ?>"
                            style="width:56px;height:44px;object-fit:cover;border-radius:6px;border:1px solid #eee">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$member): ?>
        <div class="alert alert-info mt-3 small">
            <strong>Tip:</strong> <a href="<?= PORTAL_URL ?>/">Log in</a> to track your bookings and get status updates.
        </div>
        <?php endif; ?>
    </div>

</div>
<?php endif; ?>

<?php html_footer(); ?>
