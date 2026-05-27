<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireLogin();
$pageTitle = 'Ad Copy Writer';
require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/modules/" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-megaphone me-2" style="color:#ec4899"></i>Ad Copy Writer</h3>
            <p class="text-muted small mb-0">Generate high-converting ads for Google, Facebook, TikTok & LinkedIn</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">Campaign Details</h6>
                <form id="moduleForm">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Product / Service <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="product_name" placeholder="e.g. Premium Leather Sofa" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Brief Description</label>
                        <textarea class="form-control" name="product_description" rows="2" placeholder="Key features or benefits…"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Target Audience</label>
                        <input type="text" class="form-control" name="target_audience" placeholder="e.g. Homeowners aged 30–50 in KL">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Unique Selling Point</label>
                        <input type="text" class="form-control" name="usp" placeholder="e.g. Free delivery, 10-year warranty">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Platforms <span class="text-danger">*</span></label>
                        <div class="d-flex flex-wrap gap-2 mt-1">
                            <?php foreach(['Google Search','Facebook / Instagram','TikTok','LinkedIn'] as $p): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="platforms[]" value="<?= $p ?>" id="plat_<?= md5($p) ?>" checked>
                                <label class="form-check-label text-muted small" for="plat_<?= md5($p) ?>"><?= $p ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Objective</label>
                            <select class="form-select" name="objective">
                                <option value="sales">Drive Sales</option>
                                <option value="leads">Generate Leads</option>
                                <option value="awareness">Brand Awareness</option>
                                <option value="traffic">Website Traffic</option>
                                <option value="app installs">App Installs</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Tone</label>
                            <select class="form-select" name="tone">
                                <option value="professional">Professional</option>
                                <option value="casual">Casual</option>
                                <option value="urgent">Urgent</option>
                                <option value="playful">Playful</option>
                                <option value="luxury">Luxury</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn w-100" id="generateBtn" style="background:#ec4899;border-color:#ec4899;color:#fff">
                        <i class="bi bi-magic me-2"></i>Generate Ad Copy
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="glass-card rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Ad Copy</h6>
                    <div id="outputActions" style="display:none">
                        <button class="btn btn-sm btn-outline-secondary" id="copyBtn" onclick="copyResult()"><i class="bi bi-clipboard me-1"></i>Copy All</button>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="generate()"><i class="bi bi-arrow-clockwise me-1"></i>Regenerate</button>
                    </div>
                </div>
                <div id="placeholder" class="text-center py-5">
                    <i class="bi bi-megaphone fs-1 d-block mb-3" style="opacity:0.2;color:#ec4899"></i>
                    <p class="text-muted small">Select your platforms and click <strong>Generate Ad Copy</strong></p>
                </div>
                <div id="loading" class="text-center py-5 d-none">
                    <div class="spinner-border mb-3" style="color:#ec4899" role="status"></div>
                    <p class="text-muted small">Writing your ad copy…</p>
                </div>
                <div id="error" class="alert alert-danger d-none"></div>
                <div id="result" class="d-none">
                    <pre id="resultText" style="white-space:pre-wrap;font-family:inherit;color:#e5e7eb;margin:0;font-size:14px;line-height:1.7;min-height:300px"></pre>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.AI_MODULE_KEY = 'ad_copy';
const form = document.getElementById('moduleForm');
const btn  = document.getElementById('generateBtn');
form.addEventListener('submit', e => { e.preventDefault(); generate(); });
async function generate() {
    setState('loading');
    const data = new FormData(form);
    // Join checked platforms into comma string
    const checked = [...form.querySelectorAll('input[name="platforms[]"]:checked')].map(el => el.value);
    data.delete('platforms[]');
    data.append('platforms', checked.join(','));
    data.append('module', 'ad_copy');
    try {
        const res  = await fetch('/api/ai-generate.php', { method:'POST', body:data });
        const json = await res.json();
        json.ok ? (setState('result', json.text), typeof refreshMemoryWidget === 'function' && refreshMemoryWidget()) : setState('error', json.error || 'Generation failed.');
    } catch { setState('error', 'Network error. Please check your connection.'); }
}
function setState(s, content='') {
    document.getElementById('placeholder').classList.toggle('d-none', s !== 'idle');
    document.getElementById('loading').classList.toggle('d-none', s !== 'loading');
    document.getElementById('error').classList.toggle('d-none', s !== 'error');
    document.getElementById('result').classList.toggle('d-none', s !== 'result');
    document.getElementById('outputActions').style.display = s === 'result' ? '' : 'none';
    if (s === 'error')  document.getElementById('error').textContent  = content;
    if (s === 'result') document.getElementById('resultText').textContent = content;
    btn.disabled = s === 'loading';
    btn.innerHTML = s === 'loading'
        ? '<span class="spinner-border spinner-border-sm me-2"></span>Generating…'
        : '<i class="bi bi-magic me-2"></i>Generate Ad Copy';
}
function copyResult() {
    navigator.clipboard.writeText(document.getElementById('resultText').textContent).then(() => {
        const b = document.getElementById('copyBtn');
        b.innerHTML = '<i class="bi bi-check me-1"></i>Copied!';
        setTimeout(() => { b.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy All'; }, 2000);
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
