<?php
require_once __DIR__ . '/../layout.php';
html_head('Monthly Report');

// Gather stats
$all_sellers  = get_sellers();
$all_requests = get_requests();
$all_members  = get_all_members();
$month        = date('F Y');

// Category breakdown
$cat_counts = [];
foreach ($all_sellers as $s) {
    $cat_counts[$s['category']] = ($cat_counts[$s['category']] ?? 0) + 1;
}
arsort($cat_counts);

$req_statuses = [];
foreach ($all_requests as $r) {
    $req_statuses[$r['status']] = ($req_statuses[$r['status']] ?? 0) + 1;
}

$area_counts = [];
foreach ($all_sellers as $s) {
    $area_counts[$s['area']] = ($area_counts[$s['area']] ?? 0) + 1;
}
arsort($area_counts);

html_body_open();
?>

<div class="page-title">📊 Monthly Report — <?= e($month) ?></div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6"><div class="stat-box"><div class="val"><?= count($all_sellers) ?></div><div class="lbl">Total Listings</div></div></div>
    <div class="col-md-3 col-6"><div class="stat-box"><div class="val"><?= count(array_filter($all_sellers,fn($s)=>$s['status']==='active')) ?></div><div class="lbl">Active Sellers</div></div></div>
    <div class="col-md-3 col-6"><div class="stat-box"><div class="val"><?= count($all_requests) ?></div><div class="lbl">Buyer Requests</div></div></div>
    <div class="col-md-3 col-6"><div class="stat-box"><div class="val"><?= count($all_members) ?></div><div class="lbl">Members</div></div></div>
</div>

<div class="row g-4">
    <!-- Category breakdown -->
    <div class="col-md-6">
        <div class="card p-3 h-100">
            <div class="section-head">Services by Category</div>
            <?php foreach ($cat_counts as $cat => $cnt):
                $pct = count($all_sellers) > 0 ? round($cnt / count($all_sellers) * 100) : 0;
                $color = cat_colors()[$cat] ?? '#607d8b';
            ?>
            <div class="mb-2">
                <div class="d-flex justify-content-between small mb-1">
                    <span><?= cat_icons()[$cat] ?? '⭐' ?> <?= e($cat) ?></span>
                    <span class="text-muted"><?= $cnt ?> (<?= $pct ?>%)</span>
                </div>
                <div class="progress" style="height:8px">
                    <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Request statuses -->
    <div class="col-md-3">
        <div class="card p-3 h-100">
            <div class="section-head">Request Status</div>
            <?php if (!$all_requests): ?>
                <p class="text-muted small">No requests yet.</p>
            <?php else: ?>
            <?php foreach (['open','in progress','matched','closed'] as $st):
                $n = $req_statuses[$st] ?? 0;
                $pill = ['open'=>'pill-open','in progress'=>'badge bg-warning text-dark','matched'=>'pill-matched','closed'=>'pill-closed'][$st] ?? 'badge bg-secondary';
            ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="<?= $pill ?>"><?= $st ?></span>
                    <strong><?= $n ?></strong>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top areas -->
    <div class="col-md-3">
        <div class="card p-3 h-100">
            <div class="section-head">Top Areas</div>
            <?php $i=0; foreach ($area_counts as $area => $cnt): if($i++>=6) break; ?>
                <div class="d-flex justify-content-between small mb-1">
                    <span>📍 <?= e($area) ?></span>
                    <strong><?= $cnt ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- AI Narrative -->
<div class="card p-4 mt-4">
    <div class="section-head">🤖 AI Platform Pulse</div>
    <p class="text-muted small">Get an AI-written narrative summary of this month's activity.</p>
    <button class="btn btn-primary btn-sm" id="pulseBtn">Generate AI Summary</button>
    <div id="pulseResult" class="mt-3"></div>
</div>

<script>
document.getElementById('pulseBtn').addEventListener('click', async () => {
    const btn = document.getElementById('pulseBtn');
    const res = document.getElementById('pulseResult');
    btn.disabled = true; btn.textContent = 'Generating…';
    res.innerHTML = '<div class="spinner-border spinner-border-sm"></div> AI is writing…';
    const stats = {
        total_sellers: <?= count($all_sellers) ?>,
        active_sellers: <?= count(array_filter($all_sellers,fn($s)=>$s['status']==='active')) ?>,
        total_requests: <?= count($all_requests) ?>,
        total_members: <?= count($all_members) ?>,
        top_category: <?= json_encode(array_key_first($cat_counts) ?? 'N/A') ?>,
        month: <?= json_encode($month) ?>
    };
    try {
        const r    = await csrfFetch('ajax/ai_pulse.php', {
            method: 'POST',
            body: JSON.stringify(stats)
        });
        const data = await r.json();
        const box  = document.createElement('div'); box.className = 'alert alert-info';
        box.innerHTML = escHtml(data.summary || '').replace(/\n/g,'<br>');
        res.replaceChildren(box);
    } catch(e) {
        res.textContent = 'Error generating summary.';
    }
    btn.disabled = false; btn.textContent = '🔄 Refresh Summary';
});
</script>
<?php html_footer(); ?>
