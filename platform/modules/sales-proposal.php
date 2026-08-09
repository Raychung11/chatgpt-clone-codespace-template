<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireModuleAccess('sales_proposal');
$pageTitle = 'Sales Proposal Generator';
require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/modules/" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-file-earmark-text me-2" style="color:#f97316"></i>Sales Proposal Generator</h3>
            <p class="text-muted small mb-0">Draft a professional proposal that wins the deal</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">Proposal Details</h6>
                <form id="moduleForm">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Client Name</label>
                            <input type="text" class="form-control" name="client_name" placeholder="e.g. Mr. Ahmad">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Client Company <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="client_company" placeholder="e.g. ABC Sdn Bhd" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Your Company</label>
                        <input type="text" class="form-control" name="our_company" placeholder="e.g. XYZ Solutions">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Client's Challenges / Pain Points <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="pain_points" rows="3" placeholder="What problems is the client facing?" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Your Proposed Solution <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="solution" rows="3" placeholder="How will you solve their problems?" required></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Pricing</label>
                            <input type="text" class="form-control" name="pricing" placeholder="e.g. RM8,000/month">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Timeline</label>
                            <input type="text" class="form-control" name="timeline" placeholder="e.g. 4 weeks">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Tone</label>
                        <select class="form-select" name="tone">
                            <option value="consultative">Consultative</option>
                            <option value="formal">Formal</option>
                            <option value="persuasive">Persuasive</option>
                        </select>
                    </div>
                    <button type="submit" class="btn w-100" id="generateBtn" style="background:#f97316;border-color:#f97316;color:#fff">
                        <i class="bi bi-magic me-2"></i>Generate Proposal
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="glass-card rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Sales Proposal</h6>
                    <div id="outputActions" style="display:none">
                        <button class="btn btn-sm btn-outline-secondary" id="copyBtn" onclick="copyResult()"><i class="bi bi-clipboard me-1"></i>Copy</button>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="generate()"><i class="bi bi-arrow-clockwise me-1"></i>Regenerate</button>
                    </div>
                </div>
                <div id="placeholder" class="text-center py-5">
                    <i class="bi bi-file-earmark-text fs-1 d-block mb-3" style="opacity:0.2;color:#f97316"></i>
                    <p class="text-muted small">Fill in the deal details and click <strong>Generate Proposal</strong></p>
                </div>
                <div id="loading" class="text-center py-5 d-none">
                    <div class="spinner-border mb-3" style="color:#f97316" role="status"></div>
                    <p class="text-muted small">Drafting your proposal…</p>
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
window.AI_MODULE_KEY = 'sales_proposal';
const form = document.getElementById('moduleForm');
const btn  = document.getElementById('generateBtn');
form.addEventListener('submit', e => { e.preventDefault(); generate(); });
async function generate() {
    setState('loading');
    const data = new FormData(form);
    data.append('module', 'sales_proposal');
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
        : '<i class="bi bi-magic me-2"></i>Generate Proposal';
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
