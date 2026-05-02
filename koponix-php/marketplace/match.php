<?php
require_once __DIR__ . '/../layout.php';

$cat_filter = $_GET['category'] ?? '';
$loc_filter = $_GET['location'] ?? '';
$matches    = [];
$request    = null;

if ($cat_filter && $loc_filter) {
    $request = ['category' => $cat_filter, 'location' => $loc_filter];
    $matches = get_top_matches($request, 5);
}

html_head('Match Engine');
html_body_open();
?>

<div class="page-title">🎯 Match Engine</div>
<p class="text-muted small mb-3">Find the best-fit service providers for your requirements using AI scoring.</p>

<div class="card p-4 mb-4" style="max-width:600px">
    <form method="get" class="row g-3">
        <div class="col-md-6">
            <label class="form-label fw-semibold">Service Category</label>
            <select name="category" class="form-select" required>
                <option value="">Select…</option>
                <?php foreach (categories() as $c): ?>
                    <option value="<?= e($c) ?>" <?= $cat_filter===$c?'selected':'' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Your Location</label>
            <select name="location" class="form-select" required>
                <option value="">Select…</option>
                <?php foreach (locations() as $l): ?>
                    <option value="<?= e($l) ?>" <?= $loc_filter===$l?'selected':'' ?>><?= e($l) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">🎯 Find Matches</button>
        </div>
    </form>
</div>

<?php if ($request && !$matches): ?>
    <div class="alert alert-warning">No active providers found for <strong><?= e($cat_filter) ?></strong> in <strong><?= e($loc_filter) ?></strong>. Try a different category or location.</div>
<?php elseif ($matches): ?>
<div class="section-head">Top <?= count($matches) ?> Matches for <?= e($cat_filter) ?> in <?= e($loc_filter) ?></div>
<?php foreach ($matches as $i => $s): ?>
    <div class="seller-card d-flex gap-3 align-items-start">
        <div style="text-align:center">
            <?php if (!empty($s['image'])): ?>
                <img src="<?= e(img_url($s['image'])) ?>"
                     style="width:72px;height:72px;object-fit:cover;border-radius:10px;display:block;margin-bottom:4px">
            <?php endif; ?>
            <div style="min-width:54px;font-size:1.4rem;font-weight:800;color:var(--primary)">#<?= $i+1 ?></div>
            <div style="font-size:.75rem;color:#555">Score</div>
            <div style="font-size:1.1rem;font-weight:700;color:<?= $s['_score']>=80?'#27ae60':($s['_score']>=60?'#e67e22':'#e74c3c') ?>"><?= $s['_score'] ?></div>
        </div>
        <div class="flex-grow-1">
            <?= cat_badge($s['category']) ?>
            <h5><?= e($s['service_title']) ?></h5>
            <div class="meta">👤 <strong><?= e($s['name']) ?></strong> &nbsp;|&nbsp; 🏅 <?= e($s['experience']) ?> experience</div>
            <div class="meta">📍 <?= e($s['area']) ?> &nbsp;|&nbsp; 💰 <?= e($s['price_range']) ?></div>
            <div class="desc"><?= e($s['description']) ?></div>
            <div class="d-flex gap-2 mt-2">
                <a href="<?= MARKET_URL ?>/request.php?category=<?= urlencode($s['category']) ?>&location=<?= urlencode($s['area']) ?>" class="btn btn-sm btn-primary">Submit Request</a>
                <?php if (is_logged_in()): ?>
                <a href="<?= PORTAL_URL ?>/messages.php?start=1&seller_kop=<?= urlencode($s['koperasi_id']) ?>&seller_name=<?= urlencode($s['name']) ?>&subject=<?= urlencode('Enquiry: '.$s['service_title']) ?>&seller_id=<?= urlencode($s['id']) ?>"
                   class="btn btn-sm btn-outline-primary">💬 Message</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php endif; ?>

<?php html_footer(); ?>
