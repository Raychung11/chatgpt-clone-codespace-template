<?php
require_once __DIR__ . '/layout.php';
html_head('Find Services');

// ── Filters ───────────────────────────────────────────────────
$cat_filter  = $_GET['category']  ?? '';
$loc_filter  = $_GET['location']  ?? '';
$kw_filter   = trim($_GET['q']    ?? '');

$all_sellers  = get_sellers(['status' => 'active']);

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
$icons    = cat_icons();
$colors   = cat_colors();

html_body_open();
?>

<!-- ── Hero Banner ─────────────────────────────────────────────── -->
<div style="background:linear-gradient(135deg,#0d3b5e 0%,#1a5276 45%,#2e86c1 100%);
            border-radius:16px;padding:2.5rem 2rem;margin-bottom:1.8rem;color:#fff;position:relative;overflow:hidden">
    <!-- Decorative circles -->
    <div style="position:absolute;top:-40px;right:-40px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.05)"></div>
    <div style="position:absolute;bottom:-60px;right:80px;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,.04)"></div>

    <div class="row align-items-center">
        <div class="col-lg-7">
            <div style="font-size:.8rem;opacity:.8;font-weight:600;letter-spacing:1px;text-transform:uppercase;margin-bottom:.4rem">
                🤝 Koponix Marketplace
            </div>
            <h2 style="font-size:1.9rem;font-weight:800;margin-bottom:.6rem;line-height:1.25">
                Find Trusted Services<br>from Koperasi Members
            </h2>
            <p style="opacity:.85;font-size:.92rem;margin-bottom:1.2rem">
                <?= count($all_sellers) ?> active services available across Malaysia.
                Browse by category or describe what you need.
            </p>

            <!-- AI Search -->
            <div class="d-flex gap-2">
                <input type="text" id="aiSearchInput" class="form-control"
                    placeholder='e.g. "Math tutor in PJ" or "logo design online"'
                    style="max-width:380px;border:none;box-shadow:0 2px 8px rgba(0,0,0,.15)">
                <button class="btn btn-warning fw-bold" id="aiSearchBtn" type="button"
                    style="white-space:nowrap">✨ AI Search</button>
            </div>
            <div id="aiSearchResult" class="mt-2" style="font-size:.82rem;opacity:.9"></div>
        </div>

        <!-- Category chips -->
        <div class="col-lg-5 mt-3 mt-lg-0">
            <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                <?php foreach (categories() as $c):
                    $active = ($cat_filter === $c);
                ?>
                    <a href="find_services.php?category=<?= urlencode($c) ?>"
                       style="background:<?= $active ? '#fff' : 'rgba(255,255,255,.15)' ?>;
                              color:<?= $active ? ($colors[$c] ?? '#1a5276') : '#fff' ?>;
                              border-radius:20px;padding:4px 12px;font-size:.75rem;font-weight:600;
                              text-decoration:none;white-space:nowrap;
                              border:1px solid <?= $active ? 'transparent' : 'rgba(255,255,255,.3)' ?>">
                        <?= $icons[$c] ?? '⭐' ?> <?= e($c) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Manual filters row ─────────────────────────────────────── -->
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
    <div class="col-md-1 d-flex gap-1">
        <button class="btn btn-secondary btn-sm flex-fill" type="submit">Go</button>
    </div>
</form>
<?php if ($cat_filter || $loc_filter || $kw_filter): ?>
<p class="small"><a href="find_services.php">✕ Clear all filters</a></p>
<?php endif; ?>

<p class="small text-muted mb-3"><?= count($filtered) ?> service<?= count($filtered) !== 1 ? 's' : '' ?> found
    <?= count($filtered) < count($all_sellers) ? ' · filtered from ' . count($all_sellers) . ' total' : '' ?></p>

<?php if (!$filtered): ?>
    <div class="alert alert-info">No services match your search. Try different filters or <a href="find_services.php">clear all</a>.</div>
<?php else: ?>
<div class="row g-3">
<?php foreach ($filtered as $s):
    $color = $colors[$s['category']] ?? '#607d8b';
    $icon  = $icons[$s['category']]  ?? '⭐';
    $has_img = !empty($s['image']);
    $gallery  = array_filter([$s['gallery1'] ?? '', $s['gallery2'] ?? '']);
?>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100" style="border-radius:14px;overflow:hidden;box-shadow:0 3px 12px rgba(0,0,0,.09);border:none">

            <!-- Card image / gradient header -->
            <?php if ($has_img): ?>
                <div style="height:180px;overflow:hidden;position:relative">
                    <img src="<?= e(img_url($s['image'])) ?>"
                        style="width:100%;height:100%;object-fit:cover">
                    <div style="position:absolute;bottom:0;left:0;right:0;height:60px;
                                background:linear-gradient(transparent,rgba(0,0,0,.55))"></div>
                    <span style="position:absolute;bottom:10px;left:12px">
                        <?= cat_badge($s['category']) ?>
                    </span>
                    <?php if ($gallery): ?>
                    <span style="position:absolute;top:10px;right:10px;background:rgba(0,0,0,.45);
                                 color:#fff;border-radius:12px;padding:2px 8px;font-size:.7rem">
                        📷 <?= count($gallery)+1 ?> photos
                    </span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="height:80px;background:linear-gradient(135deg,<?= $color ?>,<?= $color ?>aa);
                            display:flex;align-items:center;padding:0 1rem;gap:.75rem">
                    <span style="font-size:2.2rem"><?= $icon ?></span>
                    <span><?= cat_badge($s['category']) ?></span>
                </div>
            <?php endif; ?>

            <div class="card-body p-3">
                <h6 style="font-weight:700;color:#1a3a52;margin-bottom:.3rem"><?= e($s['service_title']) ?></h6>
                <div class="meta">👤 <strong><?= e($s['name']) ?></strong>
                    <?php if ($s['experience']): ?>
                        &nbsp;|&nbsp; 🏅 <?= e($s['experience']) ?>
                    <?php endif; ?>
                </div>
                <div class="meta">📍 <?= e($s['area']) ?> &nbsp;|&nbsp; 💰 <?= e($s['price_range']) ?></div>
                <?php if ($s['availability']): ?>
                    <div class="meta">🕐 <?= e($s['availability']) ?></div>
                <?php endif; ?>
                <div class="desc mt-1"><?= e(substr($s['description'],0,100)) ?><?= strlen($s['description'])>100?'…':'' ?></div>

                <!-- AI Insight area -->
                <div class="ai-insight-box mt-2" id="insight-<?= e($s['id']) ?>"></div>
            </div>

            <!-- Gallery strip (if photos) -->
            <?php if ($gallery): ?>
            <div class="d-flex gap-1 px-3 pb-2">
                <?php foreach ($gallery as $gp): ?>
                    <img src="<?= e(img_url($gp)) ?>"
                        style="width:56px;height:44px;object-fit:cover;border-radius:6px;border:1px solid #eee">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="card-footer bg-white border-top-0 pb-3 px-3 pt-0">
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="collapse" data-bs-target="#contact-<?= e($s['id']) ?>">
                        📩 Contact
                    </button>
                    <?php if (is_logged_in()): ?>
                    <a href="messages.php?start=1&seller_kop=<?= urlencode($s['koperasi_id']) ?>&seller_name=<?= urlencode($s['name']) ?>&subject=<?= urlencode('Enquiry: '.$s['service_title']) ?>&seller_id=<?= urlencode($s['id']) ?>"
                       class="btn btn-sm btn-primary">💬 Message</a>
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
                        ✨ AI
                    </button>
                </div>
                <div class="collapse mt-2" id="contact-<?= e($s['id']) ?>">
                    <div class="card card-body p-2 small">
                        <strong><?= e($s['name']) ?></strong><br>
                        📍 <?= e($s['area']) ?> | 💰 <?= e($s['price_range']) ?><br>
                        <?= e($s['contact']) ?><br>
                        <a href="request_service.php" class="btn btn-sm btn-primary mt-2">Submit Request →</a>
                    </div>
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
        <div class="card p-3 border-0" style="background:#eaf4fb">
            <strong>Don't see what you need?</strong><br>
            <span class="small text-muted">Submit a buyer request and let Koponix AI match you.</span>
            <br><a href="request_service.php" class="btn btn-sm btn-primary mt-2">🛒 Submit a Request</a>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card p-3 border-0" style="background:#f0fdf4">
            <strong>Are you a koperasi member with skills?</strong><br>
            <span class="small text-muted">List your service and start earning.</span>
            <br><a href="register_service.php" class="btn btn-sm btn-outline-primary mt-2">💼 Register Service</a>
        </div>
    </div>
</div>

<script>
// AI Smart Search
document.getElementById('aiSearchBtn').addEventListener('click', async () => {
    const q   = document.getElementById('aiSearchInput').value.trim();
    if (!q) return;
    const btn = document.getElementById('aiSearchBtn');
    const res = document.getElementById('aiSearchResult');
    btn.disabled = true; btn.textContent = 'Searching…';
    res.textContent = '⏳ AI is interpreting your search…';
    try {
        const r    = await csrfFetch('ajax/ai_search.php', {method:'POST', body: JSON.stringify({query:q})});
        const data = await r.json();
        if (data.url) {
            res.textContent = '🎯 Filter applied — redirecting…';
            setTimeout(() => { window.location.href = data.url; }, 500);
        } else {
            res.textContent = 'Could not interpret. Use manual filters below.';
        }
    } catch(e) { res.textContent = 'Error. Please try again.'; }
    btn.disabled = false; btn.textContent = '✨ AI Search';
});

// AI Insight buttons
document.querySelectorAll('.ai-insight-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const id  = btn.dataset.sellerId;
        const box = document.getElementById('insight-' + id);
        if (box.dataset.loaded) return;
        btn.disabled = true; btn.textContent = '…';
        try {
            const r    = await csrfFetch('ajax/ai_insight.php', {method:'POST',
                body: JSON.stringify({title:btn.dataset.title, category:btn.dataset.category, area:btn.dataset.area, price:btn.dataset.price, description:btn.dataset.desc})
            });
            const data = await r.json();
            const ins  = document.createElement('div');
            ins.style.cssText = 'background:#eaf4fb;border-left:3px solid #2e86c1;border-radius:0 6px 6px 0;padding:4px 8px;font-size:.76rem;color:#1a5276;margin-top:.25rem';
            const em = document.createElement('em'); em.textContent = '✨ ' + (data.insight || '');
            ins.appendChild(em); box.appendChild(ins);
            box.dataset.loaded = '1';
        } catch(e) {}
        btn.disabled = false; btn.textContent = '✨ AI';
    });
});
</script>
<?php html_footer(); ?>
