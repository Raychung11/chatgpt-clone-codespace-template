<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireModuleAccess('sop');
$pageTitle = 'SOP Generator';
require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/modules/" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-list-ol me-2" style="color:#3b82f6"></i>SOP Generator</h3>
            <p class="text-muted small mb-0">Turn rough process notes into a full Standard Operating Procedure</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">Process Details</h6>
                <form id="moduleForm">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Process Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="process_name" placeholder="e.g. Monthly Inventory Count" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Department</label>
                            <input type="text" class="form-control" name="department" placeholder="e.g. Operations">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Frequency</label>
                            <select class="form-select" name="frequency">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="as needed">As Needed</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Objective <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="objective" placeholder="e.g. Ensure accurate stock levels are maintained" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Tools / Systems Required</label>
                        <input type="text" class="form-control" name="tools_needed" placeholder="e.g. Excel, barcode scanner, ERP system">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Steps Overview <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="steps" rows="5" placeholder="List the main steps in order, one per line…" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Version</label>
                        <input type="text" class="form-control" name="version" value="1.0" placeholder="1.0">
                    </div>
                    <button type="submit" class="btn w-100" id="generateBtn" style="background:#3b82f6;border-color:#3b82f6;color:#fff">
                        <i class="bi bi-magic me-2"></i>Generate SOP
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="glass-card rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Standard Operating Procedure</h6>
                    <div id="outputActions" style="display:none">
                        <button class="btn btn-sm btn-outline-secondary" id="copyBtn" onclick="copyResult()"><i class="bi bi-clipboard me-1"></i>Copy</button>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="generate()"><i class="bi bi-arrow-clockwise me-1"></i>Regenerate</button>
                    </div>
                </div>
                <div id="placeholder" class="text-center py-5">
                    <i class="bi bi-list-ol fs-1 d-block mb-3" style="opacity:0.2;color:#3b82f6"></i>
                    <p class="text-muted small">Describe your process and click <strong>Generate SOP</strong></p>
                </div>
                <div id="loading" class="text-center py-5 d-none">
                    <div class="spinner-border mb-3" style="color:#3b82f6" role="status"></div>
                    <p class="text-muted small">Writing your SOP document…</p>
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
window.AI_MODULE_KEY = 'sop';
const form = document.getElementById('moduleForm');
const btn  = document.getElementById('generateBtn');
form.addEventListener('submit', e => { e.preventDefault(); generate(); });
async function generate() {
    setState('loading');
    const data = new FormData(form);
    data.append('module', 'sop');
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
        : '<i class="bi bi-magic me-2"></i>Generate SOP';
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
