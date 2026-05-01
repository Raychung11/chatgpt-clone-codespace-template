<?php
require_once __DIR__ . '/layout.php';
html_head('Promo Generator');
html_body_open();
?>

<div class="page-title">📣 Promo Generator</div>
<p class="text-muted small mb-3">Generate marketing content for your service listing using AI.</p>

<div class="row g-4">
<div class="col-md-5">
    <div class="card p-4">
        <h6 class="mb-3">Service Details</h6>
        <div class="mb-3">
            <label class="form-label">Service Title</label>
            <input type="text" id="promoTitle" class="form-control" placeholder="e.g. Professional Home Cleaning">
        </div>
        <div class="mb-3">
            <label class="form-label">Category</label>
            <select id="promoCategory" class="form-select">
                <option value="">Select…</option>
                <?php foreach (categories() as $c): ?>
                    <option value="<?= e($c) ?>"><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Service Area</label>
            <select id="promoArea" class="form-select">
                <option value="">Select…</option>
                <?php foreach (locations() as $l): ?>
                    <option value="<?= e($l) ?>"><?= e($l) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Price Range</label>
            <input type="text" id="promoPrice" class="form-control" placeholder="e.g. RM 80 – RM 120">
        </div>
        <div class="mb-3">
            <label class="form-label">Short Description</label>
            <textarea id="promoDesc" class="form-control" rows="3" placeholder="What do you offer?"></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Promo Type</label>
            <select id="promoType" class="form-select">
                <option value="Facebook/Instagram post caption">Facebook/Instagram Post</option>
                <option value="WhatsApp broadcast message">WhatsApp Broadcast</option>
                <option value="Short tagline (1 sentence)">Short Tagline</option>
                <option value="Flyer headline + bullet points">Flyer Content</option>
            </select>
        </div>
        <button class="btn btn-primary w-100" id="generateBtn">✨ Generate Promo</button>
    </div>
</div>
<div class="col-md-7">
    <div class="card p-4 h-100">
        <h6 class="mb-2">Generated Content</h6>
        <div id="promoResult" class="text-muted small" style="min-height:200px">
            Fill in the details and click Generate.
        </div>
        <div id="promoCopyWrap" class="mt-3 d-none">
            <button class="btn btn-sm btn-outline-secondary" id="copyBtn">📋 Copy to Clipboard</button>
        </div>
    </div>
</div>
</div>

<script>
document.getElementById('generateBtn').addEventListener('click', async () => {
    const btn = document.getElementById('generateBtn');
    const res = document.getElementById('promoResult');
    const data = {
        title    : document.getElementById('promoTitle').value.trim(),
        category : document.getElementById('promoCategory').value,
        area     : document.getElementById('promoArea').value,
        price    : document.getElementById('promoPrice').value.trim(),
        desc     : document.getElementById('promoDesc').value.trim(),
        type     : document.getElementById('promoType').value,
    };
    if (!data.title || !data.category) {
        res.innerHTML = '<span class="text-danger">Please fill in Service Title and Category.</span>';
        return;
    }
    btn.disabled = true; btn.textContent = 'Generating…';
    res.innerHTML = '<div class="spinner-border spinner-border-sm"></div> AI is writing your promo…';
    try {
        const r    = await csrfFetch('ajax/ai_promo.php', {
            method: 'POST',
            body: JSON.stringify(data)
        });
        const json = await r.json();
        const text = json.promo || 'Could not generate promo.';
        const pre  = document.createElement('pre');
        pre.style.cssText = 'white-space:pre-wrap;font-family:inherit';
        pre.textContent = text;
        res.replaceChildren(pre);
        document.getElementById('promoCopyWrap').classList.remove('d-none');
        document.getElementById('copyBtn').onclick = () => {
            navigator.clipboard.writeText(text).then(() => {
                document.getElementById('copyBtn').textContent = '✅ Copied!';
            });
        };
    } catch(e) {
        res.textContent = 'Error generating promo. Please try again.';
    }
    btn.disabled = false; btn.textContent = '✨ Generate Promo';
});
</script>
<?php html_footer(); ?>
