<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireModuleAccess('contract_drafter');
$pageTitle = 'Contract & Agreement Drafter';
require_once '../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/modules/" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-file-earmark-lock me-2" style="color:#14b8a6"></i>Contract & Agreement Drafter</h3>
            <p class="text-muted small mb-0">Draft professional legal agreements in minutes — no lawyer required</p>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">Agreement Details</h6>
                <form id="moduleForm">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Agreement Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="contract_type">
                            <option value="Non-Disclosure Agreement (NDA)">Non-Disclosure Agreement (NDA)</option>
                            <option value="Service Agreement">Service Agreement</option>
                            <option value="Employment Contract">Employment Contract</option>
                            <option value="Freelance / Contractor Agreement">Freelance / Contractor Agreement</option>
                            <option value="Partnership Agreement">Partnership Agreement</option>
                            <option value="Vendor / Supplier Agreement">Vendor / Supplier Agreement</option>
                            <option value="Sales & Purchase Agreement">Sales & Purchase Agreement</option>
                            <option value="Letter of Intent (LOI)">Letter of Intent (LOI)</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Party A (Your Company) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="party_a" placeholder="e.g. XYZ Sdn Bhd" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Party B (Other Party) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="party_b" placeholder="e.g. ABC Trading Co" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Key Terms & Scope <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="key_terms" rows="4" placeholder="e.g. Party A provides monthly social media management services. Fee: RM3,500/month. Deliverables: 20 posts, 4 stories, monthly report." required></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Duration</label>
                            <input type="text" class="form-control" name="duration" placeholder="e.g. 12 months, 1 year">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Governing Law</label>
                            <select class="form-select" name="governing_law">
                                <option value="Malaysia">Malaysia</option>
                                <option value="Singapore">Singapore</option>
                                <option value="General / International">General</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Special Clauses (optional)</label>
                        <textarea class="form-control" name="special_clauses" rows="2" placeholder="e.g. 30-day termination notice, IP ownership stays with Party A"></textarea>
                    </div>
                    <button type="submit" class="btn w-100" id="generateBtn" style="background:#14b8a6;border-color:#14b8a6;color:#fff">
                        <i class="bi bi-magic me-2"></i>Draft Agreement
                    </button>
                </form>
                <p class="text-muted mt-3" style="font-size:11px"><i class="bi bi-info-circle me-1"></i>For reference only. Have a qualified lawyer review before signing.</p>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="glass-card rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Agreement Draft</h6>
                    <div id="outputActions" style="display:none">
                        <button class="btn btn-sm btn-outline-secondary" id="copyBtn" onclick="copyResult()"><i class="bi bi-clipboard me-1"></i>Copy</button>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="generate()"><i class="bi bi-arrow-clockwise me-1"></i>Regenerate</button>
                    </div>
                </div>
                <div id="placeholder" class="text-center py-5">
                    <i class="bi bi-file-earmark-lock fs-1 d-block mb-3" style="opacity:0.2;color:#14b8a6"></i>
                    <p class="text-muted small">Fill in the parties and terms, then click <strong>Draft Agreement</strong></p>
                </div>
                <div id="loading" class="text-center py-5 d-none">
                    <div class="spinner-border mb-3" style="color:#14b8a6" role="status"></div>
                    <p class="text-muted small">Drafting your agreement…</p>
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
window.AI_MODULE_KEY = 'contract_drafter';
const form = document.getElementById('moduleForm');
const btn  = document.getElementById('generateBtn');
form.addEventListener('submit', e => { e.preventDefault(); generate(); });
async function generate() {
    setState('loading');
    const data = new FormData(form);
    data.append('module', 'contract_drafter');
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
    btn.innerHTML = s === 'loading' ? '<span class="spinner-border spinner-border-sm me-2"></span>Generating…' : '<i class="bi bi-magic me-2"></i>Draft Agreement';
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
