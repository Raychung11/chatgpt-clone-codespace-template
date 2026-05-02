<?php
// ============================================================
//  KOPONIX – My Bookings (buyer + seller views)
// ============================================================
require_once __DIR__ . '/../layout.php';
require_login();

$member = current_member();
$kop_id = $member['koperasi_id'];
$role   = view_role();
$tab    = $_GET['tab'] ?? 'mine';

// ── Handle status update (seller action) ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'])) {
    verify_csrf();
    $bid    = trim($_POST['booking_id'] ?? '');
    $action = trim($_POST['action']     ?? '');
    $b      = get_booking($bid);

    if ($b && $b['seller_kop_id'] === $kop_id && in_array($action, ['confirmed','cancelled'], true)) {
        update_booking_status($bid, $action);
        $b['status'] = $action;
        if ($action === 'confirmed') notify_booking_confirmed($b);
        if ($action === 'cancelled') notify_booking_cancelled($b);
        flash('Booking marked as ' . $action . '.', 'success');
    }
    redirect(PORTAL_URL . '/bookings.php?tab=incoming');
}

$my_bookings  = get_bookings_for_buyer($kop_id);
$inc_bookings = ($role === 'provider' || $role === 'admin') ? get_bookings_for_seller($kop_id) : [];

$status_styles = [
    'pending'   => ['bg' => '#fff3cd', 'color' => '#856404', 'label' => '⏳ Pending'],
    'confirmed' => ['bg' => '#d1e7dd', 'color' => '#0a3622', 'label' => '✅ Confirmed'],
    'completed' => ['bg' => '#cff4fc', 'color' => '#055160', 'label' => '🏁 Completed'],
    'cancelled' => ['bg' => '#f8d7da', 'color' => '#842029', 'label' => '❌ Cancelled'],
];

function status_badge(string $s, array $map): string {
    $st = $map[$s] ?? ['bg' => '#eee', 'color' => '#555', 'label' => ucfirst($s)];
    return "<span style='background:{$st['bg']};color:{$st['color']};padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:700'>{$st['label']}</span>";
}

html_head('My Bookings');
html_body_open();
?>

<div class="page-title">📋 My Bookings</div>

<!-- ── Tabs ────────────────────────────────────────────────── -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'mine' ? 'active' : '' ?>"
           href="<?= PORTAL_URL ?>/bookings.php?tab=mine">
            🛒 My Bookings
            <?php if (count($my_bookings)): ?>
                <span class="badge bg-secondary ms-1"><?= count($my_bookings) ?></span>
            <?php endif; ?>
        </a>
    </li>
    <?php if ($inc_bookings !== []): ?>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'incoming' ? 'active' : '' ?>"
           href="<?= PORTAL_URL ?>/bookings.php?tab=incoming">
            📥 Incoming Bookings
            <?php $pending = array_filter($inc_bookings, fn($b) => $b['status'] === 'pending'); ?>
            <?php if ($pending): ?>
                <span class="badge bg-danger ms-1"><?= count($pending) ?></span>
            <?php endif; ?>
        </a>
    </li>
    <?php endif; ?>
</ul>

<?php if ($tab === 'mine'): ?>
<!-- ════════════════════════════════════════════════════
     MY BOOKINGS (as buyer)
════════════════════════════════════════════════════ -->
<?php if (!$my_bookings): ?>
    <div class="card p-4 text-center text-muted">
        <div style="font-size:2.5rem">📭</div>
        <p class="mt-2">You haven't made any bookings yet.</p>
        <a href="<?= MARKET_URL ?>/" class="btn btn-primary btn-sm mt-1">Browse Services →</a>
    </div>
<?php else: ?>
<div class="row g-3">
<?php foreach ($my_bookings as $b): ?>
    <div class="col-12">
        <div class="card p-3">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <div class="fw-bold" style="color:#1a3a52;font-size:1rem"><?= e($b['service_title']) ?></div>
                    <div class="small text-muted">
                        Provider: <strong><?= e($b['seller_name']) ?></strong>
                        &nbsp;·&nbsp; <?= e($b['category']) ?>
                    </div>
                    <?php if ($b['booking_date']): ?>
                        <div class="small text-muted">📅 <?= date('d M Y', strtotime($b['booking_date'])) ?>
                            <?= $b['booking_time'] ? ' &nbsp;🕐 ' . e($b['booking_time']) : '' ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($b['notes']): ?>
                        <div class="small text-muted mt-1">📝 <?= e(substr($b['notes'], 0, 120)) ?></div>
                    <?php endif; ?>
                    <div class="small text-muted mt-1">
                        Submitted: <?= date('d M Y, g:ia', strtotime($b['created_at'])) ?>
                    </div>
                </div>
                <div class="text-end">
                    <?= status_badge($b['status'], $status_styles) ?>
                    <?php if ($b['status'] === 'confirmed'): ?>
                        <div class="mt-2">
                            <a href="<?= PORTAL_URL ?>/messages.php?start=1&seller_kop=<?= urlencode($b['seller_kop_id']) ?>&seller_name=<?= urlencode($b['seller_name']) ?>&subject=<?= urlencode('Booking: ' . $b['service_title']) ?>&seller_id=<?= urlencode($b['seller_id']) ?>"
                               class="btn btn-sm btn-outline-primary">💬 Message Provider</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif ($tab === 'incoming' && $inc_bookings !== []): ?>
<!-- ════════════════════════════════════════════════════
     INCOMING BOOKINGS (as seller/provider)
════════════════════════════════════════════════════ -->
<?php if (!$inc_bookings): ?>
    <div class="card p-4 text-center text-muted">
        <div style="font-size:2.5rem">📭</div>
        <p class="mt-2">No incoming bookings yet.</p>
    </div>
<?php else: ?>
<div class="row g-3">
<?php foreach ($inc_bookings as $b): ?>
    <div class="col-12">
        <div class="card p-3" style="border-left:4px solid <?= $b['status']==='pending'?'#f6c90e':($b['status']==='confirmed'?'#27ae60':'#ccc') ?>">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div class="flex-grow-1">
                    <div class="fw-bold" style="color:#1a3a52"><?= e($b['service_title']) ?></div>
                    <div class="small mt-1">
                        👤 <strong><?= e($b['buyer_name']) ?></strong>
                        &nbsp;·&nbsp; 📞 <strong><?= e($b['buyer_contact']) ?></strong>
                    </div>
                    <?php if ($b['booking_date']): ?>
                        <div class="small text-muted">📅 <?= date('d M Y', strtotime($b['booking_date'])) ?>
                            <?= $b['booking_time'] ? ' &nbsp;🕐 ' . e($b['booking_time']) : '' ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($b['notes']): ?>
                        <div class="small text-muted mt-1 p-2 rounded" style="background:#f8f9fa">
                            📝 <?= e($b['notes']) ?>
                        </div>
                    <?php endif; ?>
                    <div class="small text-muted mt-1">
                        Received: <?= date('d M Y, g:ia', strtotime($b['created_at'])) ?>
                    </div>
                </div>
                <div class="text-end d-flex flex-column gap-2 align-items-end">
                    <?= status_badge($b['status'], $status_styles) ?>

                    <?php if ($b['status'] === 'pending'): ?>
                    <div class="d-flex gap-1 mt-1">
                        <form method="post" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="booking_id" value="<?= e($b['id']) ?>">
                            <input type="hidden" name="action"     value="confirmed">
                            <button type="submit" class="btn btn-sm btn-success"
                                onclick="return confirm('Confirm this booking?')">✅ Confirm</button>
                        </form>
                        <form method="post" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="booking_id" value="<?= e($b['id']) ?>">
                            <input type="hidden" name="action"     value="cancelled">
                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                onclick="return confirm('Decline this booking?')">✖ Decline</button>
                        </form>
                    </div>
                    <?php endif; ?>

                    <?php if ($b['buyer_kop_id'] && $b['status'] === 'confirmed'): ?>
                        <a href="<?= PORTAL_URL ?>/messages.php?start=1&seller_kop=<?= urlencode($b['buyer_kop_id']) ?>&seller_name=<?= urlencode($b['buyer_name']) ?>&subject=<?= urlencode('Booking: ' . $b['service_title']) ?>&seller_id=<?= urlencode($b['seller_id']) ?>"
                           class="btn btn-sm btn-outline-primary">💬 Message Buyer</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php html_footer(); ?>
