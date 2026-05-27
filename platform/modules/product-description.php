<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireModuleAccess('product_description');
$pageTitle = 'Product Description Writer';
require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/modules/" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-bag me-2" style="color:#6366f1"></i>Product Description Writer</h3>
            <p class="text-muted small mb-0">SEO-ready product copy for your website, Shopee, Lazada, or Amazon</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">Product Details</h6>
                <form id="moduleForm">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="product_name" placeholder="e.g. Ergonomic Office Chair Pro" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Category</label>
                            <input type="text" class="form-control" name="product_category" placeholder="e.g. Furniture, Electronics">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Platform</label>
                            <select class="form-select" name="platform">
                                <option value="website">Website</option>
                                <option value="Shopee / Lazada">Shopee / Lazada</option>
                                <option value="Amazon">Amazon</option>
                                <option value="social media">Social Media</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Key Features / Benefits <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="key_features" rows="4" placeholder="List the main features and benefits, one per line…" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Target Customer</label>
                        <input type="text" class="form-control" name="target_customer" placeholder="e.g. Remote workers, home office setups">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Tone</label>
                            <select class="form-select" name="tone">
                                <option value="professional">Professional</option>
                                <option value="casual">Casual</option>
                                <option value="luxury">Luxury</option>
                                <option value="technical">Technical</option>
                                <option value="playful">Playful</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Length</label>
                            <select class="form-select" name="length">
                                <option value="short">Short (tagline + 2 lines)</option>
                                <option value="medium" selected>Medium (listing ready)</option>
                                <option value="long">Long (full page)</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" id="generateBtn">
                        <i class="bi bi-magic me-2"></i>Generate Description
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="glass-card rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Product Description</h6>
                    <div id="outputActions" style="display:none">
                        <button class="btn btn-sm btn-outline-secondary" id="copyBtn" onclick="copyResult()"><i class="bi bi-clipboard me-1"></i>Copy</button>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="generate()"><i class="bi bi-arrow-clockwise me-1"></i>Regenerate</button>
                    </div>
                </div>
                <div id="placeholder" class="text-center py-5">
                    <i class="bi bi-bag fs-1 d-block mb-3" style="opacity:0.2;color:#6366f1"></i>
                    <p class="text-muted small">Fill in the product details and click <strong>Generate Description</strong></p>
                </div>
                <div id="loading" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <p class="text-muted small">Writing your product description…</p>
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
window.AI_MODULE_KEY = 'product_description';
const form = document.getElementById('moduleForm');
const btn  = document.getElementById('generateBtn');
form.addEventListener('submit', e => { e.preventDefault(); generate(); });
async function generate() {
    setState('loading');
    const data = new FormData(form);
    data.append('module', 'product_description');
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
        : '<i class="bi bi-magic me-2"></i>Generate Description';
}
function copyResult() {
    navigator.clipboard.writeText(document.getElementById('resultText').textContent).then(() => {
        const b = document.getElementById('copyBtn');
        b.innerHTML = '<i class="bi bi-check me-1"></i>Copied!';
        setTimeout(() => { b.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy'; }, 2000);
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
