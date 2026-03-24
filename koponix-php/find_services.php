<?php
require_once __DIR__ . '/layout.php';
html_head('Find Services');

// ── Filters ───────────────────────────────────────────────────
$cat_filter  = $_GET['category']  ?? '';
$loc_filter  = $_GET['location']  ?? '';
$kw_filter   = trim($_GET['q']    ?? '');

$all_sellers  = get_sellers(['status' => 'active']);

// Apply filters
$filtered = $all_sellers;
if ($cat_filter) $filtered = array_filter($filtered, fn($s) => $s['category'] === $cat_filter);
if ($loc_filter) $filtered = array_filter($filtered, fn($s) => $s['area'] === $loc_filter);
if ($kw_filter) {
    $q = strtolower($kw_filter);
    $filtered = array_filter($filtered, fn($s) =>
        str_contains(strtolower($s['service_title']), $q) ||
        str_contains(strtolower($s['description']),   $q) ||
        str_contains(strtolower($s['category']),      $q) ||
        str_contains(strtolower($s['area']),          $q)
    );
}
$filtered = array_values($filtered);

html_body_open();
?>

<div class="page-title">🔍 Find Services</div>
<p class="text-muted small mb-3">Browse services offered by verified koperasi members.</p>

<!-- AI Smart Search -->
<div class="card mb-3">
    <div class="card-header text-white" style="background:linear-gradient(135deg,#1a5276,#2e86c1)">
        <strong>✨ AI Smart Search</strong>
        <small class="opacity-75 d-block">Describe what you need in plain language</small>
    </div>
    <div class="card-body">
        <div class="input-group">
            <input type="text" id="aiSearchInput" class="form-control"
                placeholder='e.g. "I need a Math tutor in PJ" or "logo design online"'>
            <button class="btn btn-primary" id="aiSearchBtn" type="button">🔍 Search</button>
        </div>
        <div id="aiSearchResult" class="mt-2"></div>
    </div>
</div>

<!-- Manual filters -->
<form method="get" class="row g-2 mb-3 align-items-end">
    <div class="col-md-4">
        <select name="category" class="form-select form-select-sm">
            <option value="">All Categories</option>
            <?php foreach (categories() as $c): ?>
                <option value="<?= e($c) ?>" <?= $cat_filter === $c ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <select name="location" class="form-select form-select-sm">
            <option value="">All Locations</option>
            <?php foreach (locations() as $l): ?>
                <option value="<?= e($l) ?>" <?= $loc_filter === $l ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <input type="text" name="q" class="form-control form-control-sm"
            placeholder="Keyword…" value="<?= e($kw_filter) ?>">
    </div>
    <div class="col-md-1">
        <button class="btn btn-secondary btn-sm w-100" type="submit">Filter</button>
    </div>
</form>
<?php if ($cat_filter || $loc_filter || $kw_filter): ?>
<p class="small"><a href="find_services.php">✕ Clear all filters</a></p>
<?php endif; ?>

<p class="small text-muted"><?= count($filtered) ?> service<?= count($filtered) !== 1 ? 's' : '' ?> found
    <?= count($filtered) < count($all_sellers) ? ' · filtered from ' . count($all_sellers) . ' total' : '' ?></p>

<?php if (!$filtered): ?>
    <div class="alert alert-info">No services match your search. Try different filters.</div>
<?php else: ?>
<div class="row g-3">
<?php foreach ($filtered as $s): ?>
    <div class="col-md-6">
        <div class="seller-card">
            <?= cat_badge($s['category']) ?>
            <h5><?= e($s['service_title']) ?></h5>
            <div class="meta">👤 <strong><?= e($s['name']) ?></strong> &nbsp;|&nbsp; 🏅 <?= e($s['experience']) ?> experience</div>
            <div class="meta">📍 <?= e($s['area']) ?> &nbsp;|&nbsp; 💰 <?= e($s['price_range']) ?></div>
            <div class="meta">🕐 <?= e($s['availability']) ?></div>
            <div class="desc"><?= e($s['description']) ?></div>
            <!-- AI Insight placeholder -->
            <div class="ai-insight-box mt-2" id="insight-<?= e($s['id']) ?>"></div>
            <!-- Actions -->
            <div class="d-flex gap-2 mt-2 flex-wrap">
                <button class="btn btn-sm btn-outline-secondary"
                    data-bs-toggle="collapse" data-bs-target="#contact-<?= e($s['id']) ?>">
                    📩 Contact / Request
                </button>
                <?php if (is_logged_in()): ?>
                <a href="messages.php?start=1&seller_kop=<?= urlencode($s['koperasi_id']) ?>&seller_name=<?= urlencode($s['name']) ?>&subject=<?= urlencode('Enquiry: '.$s['service_title']) ?>&seller_id=<?= urlencode($s['id']) ?>"
                   class="btn btn-sm btn-outline-primary">💬 Message</a>
                <?php else: ?>
                <a href="member_portal.php?login_required=1" class="btn btn-sm btn-outline-primary">💬 Message</a>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-info ai-insight-btn"
                    data-seller-id="<?= e($s['id']) ?>"
                    data-title="<?= e($s['service_title']) ?>"
                    data-category="<?= e($s['category']) ?>"
                    data-area="<?= e($s['area']) ?>"
                    data-price="<?= e($s['price_range']) ?>"
                    data-desc="<?= e(substr($s['description'],0,200)) ?>">
                    ✨ AI Insight
                </button>
            </div>
            <div class="collapse mt-2" id="contact-<?= e($s['id']) ?>">
                <div class="card card-body p-2 small">
                    <strong><?= e($s['name']) ?></strong> — <?= e($s['service_title']) ?><br>
                    📍 <?= e($s['area']) ?> | 💰 <?= e($s['price_range']) ?><br><br>
                    To engage this provider, submit a formal service request.<br>
                    <a href="request_service.php" class="btn btn-sm btn-primary mt-2">Submit Request →</a>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<hr class="my-4">
<div class="row g-3">
    <div class="col-md-6">
        <div class="alert alert-light border">
            <strong>Don't see what you need?</strong><br>
            Submit a buyer request and let Koponix AI match you.
            <br><a href="request_service.php" class="btn btn-sm btn-primary mt-2">🛒 Submit a Request</a>
        </div>
    </div>
    <div class="col-md-6">
        <div class="alert alert-light border">
            <strong>Are you a koperasi member with skills?</strong><br>
            List your service and start earning.
            <br><a href="register_service.php" class="btn btn-sm btn-outline-primary mt-2">💼 Register Service</a>
        </div>
    </div>
</div>

<script>
// AI Smart Search
document.getElementById('aiSearchBtn').addEventListener('click', async () => {
    const q = document.getElementById('aiSearchInput').value.trim();
    if (!q) return;
    const btn = document.getElementById('aiSearchBtn');
    const res = document.getElementById('aiSearchResult');
    btn.disabled = true;
    btn.textContent = 'Searching…';
    res.innerHTML = '<div class="spinner-border spinner-border-sm"></div> AI is interpreting your search…';
    try {
        const r = await fetch('ajax/ai_search.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({query: q})
        });
        const data = await r.json();
        if (data.url) {
            res.innerHTML = `<span class="badge bg-success">🎯 AI filter applied</span>`;
            setTimeout(() => { window.location.href = data.url; }, 600);
        } else {
            res.innerHTML = `<span class="text-danger">AI could not interpret the query. Use manual filters.</span>`;
        }
    } catch(e) {
        res.innerHTML = `<span class="text-danger">Error: ${e.message}</span>`;
    }
    btn.disabled = false;
    btn.textContent = '🔍 Search';
});

// AI Insight buttons
document.querySelectorAll('.ai-insight-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const id    = btn.dataset.sellerId;
        const box   = document.getElementById('insight-' + id);
        if (box.dataset.loaded) return;
        btn.disabled = true;
        btn.textContent = 'Thinking…';
        try {
            const r = await fetch('ajax/ai_insight.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({
                    title: btn.dataset.title,
                    category: btn.dataset.category,
                    area: btn.dataset.area,
                    price: btn.dataset.price,
                    description: btn.dataset.desc
                })
            });
            const data = await r.json();
            box.innerHTML = `<div style="background:#eaf4fb;border-left:3px solid #2e86c1;border-radius:0 6px 6px 0;padding:4px 8px;font-size:.78rem;color:#1a5276;">✨ <em>${data.insight || 'No insight generated.'}</em></div>`;
            box.dataset.loaded = '1';
            btn.textContent = '✨ AI Insight';
        } catch(e) {
            btn.textContent = '✨ AI Insight';
        }
        btn.disabled = false;
    });
});
</script>
<?php html_footer(); ?>
