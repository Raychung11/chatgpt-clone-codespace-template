<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireLogin();
$pageTitle = 'Cold Outreach Sequence';
require_once '../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/modules/" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-send me-2" style="color:#f97316"></i>Cold Outreach Sequence</h3>
            <p class="text-muted small mb-0">Build a complete multi-touch outreach campaign for email and WhatsApp</p>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">Campaign Details</h6>
                <form id="moduleForm">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Product / Service <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="product_service" placeholder="e.g. AI-powered inventory management system" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Target Persona <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="target_role" placeholder="e.g. F&B business owners with 3+ outlets in Malaysia" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Pain Point You Solve <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="pain_point" rows="3" placeholder="e.g. They lose RM5,000/month due to stock wastage and manual counting errors" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Your Unique Selling Point</label>
                        <input type="text" class="form-control" name="usp" placeholder="e.g. Only system with real-time WhatsApp alerts for low stock">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Sequence Length</label>
                            <select class="form-select" name="sequence_length">
                                <option value="3-touch">3-touch (short)</option>
                                <option value="5-touch" selected>5-touch (recommended)</option>
                                <option value="7-touch">7-touch (aggressive)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Channel</label>
                            <select class="form-select" name="channel">
                                <option value="Email">Email</option>
                                <option value="WhatsApp">WhatsApp</option>
                                <option value="Email and WhatsApp">Both (Email + WhatsApp)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Tone</label>
                        <select class="form-select" name="tone">
                            <option value="friendly and consultative">Friendly & Consultative</option>
                            <option value="professional">Professional</option>
                            <option value="direct and bold">Direct & Bold</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Your Name / Company</label>
                        <input type="text" class="form-control" name="sender" placeholder="e.g. Ahmad from XYZ Solutions">
                    </div>
                    <button type="submit" class="btn w-100" id="generateBtn" style="background:#f97316;border-color:#f97316;color:#fff">
                        <i class="bi bi-magic me-2"></i>Generate Sequence
                    </button>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="glass-card rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Outreach Sequence</h6>
                    <div id="outputActions" style="display:none">
                        <button class="btn btn-sm btn-outline-secondary" id="copyBtn" onclick="copyResult()"><i class="bi bi-clipboard me-1"></i>Copy All</button>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="generate()"><i class="bi bi-arrow-clockwise me-1"></i>Regenerate</button>
                    </div>
                </div>
                <div id="placeholder" class="text-center py-5">
                    <i class="bi bi-send fs-1 d-block mb-3" style="opacity:0.2;color:#f97316"></i>
                    <p class="text-muted small">Fill in the campaign details and click <strong>Generate Sequence</strong></p>
                </div>
                <div id="loading" class="text-center py-5 d-none">
                    <div class="spinner-border mb-3" style="color:#f97316" role="status"></div>
                    <p class="text-muted small">Building your outreach sequence…</p>
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
const form = document.getElementById('moduleForm');
const btn  = document.getElementById('generateBtn');
form.addEventListener('submit', e => { e.preventDefault(); generate(); });
async function generate() {
    setState('loading');
    const data = new FormData(form);
    data.append('module', 'cold_outreach');
    try {
        const res  = await fetch('/api/ai-generate.php', { method:'POST', body:data });
        const json = await res.json();
        json.ok ? setState('result', json.text) : setState('error', json.error || 'Generation failed.');
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
    btn.innerHTML = s === 'loading' ? '<span class="spinner-border spinner-border-sm me-2"></span>Generating…' : '<i class="bi bi-magic me-2"></i>Generate Sequence';
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
