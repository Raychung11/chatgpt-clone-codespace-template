<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireModuleAccess('business_report');
$pageTitle = 'Business Report Writer';
require_once '../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/modules/" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-file-earmark-bar-graph me-2" style="color:#3b82f6"></i>Business Report Writer</h3>
            <p class="text-muted small mb-0">Turn raw numbers into a professional management report with AI analysis</p>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">Report Details</h6>
                <form id="moduleForm">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Report Type</label>
                            <select class="form-select" name="report_type">
                                <option value="Weekly">Weekly</option>
                                <option value="Monthly" selected>Monthly</option>
                                <option value="Quarterly">Quarterly</option>
                                <option value="Annual">Annual</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Period</label>
                            <input type="text" class="form-control" name="period" placeholder="e.g. May 2025">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Business Name</label>
                            <input type="text" class="form-control" name="business_name" placeholder="e.g. ABC Trading">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Industry</label>
                            <input type="text" class="form-control" name="industry" placeholder="e.g. F&B, Retail">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Key Metrics (Revenue, Sales, Targets) <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="sales_data" rows="4" placeholder="e.g.&#10;Revenue: RM85,000 (target RM80,000, +6%)&#10;Units sold: 420&#10;New customers: 38&#10;Churn: 5" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Key Wins / Highlights</label>
                        <textarea class="form-control" name="highlights" rows="2" placeholder="e.g. Launched new branch, closed 3 enterprise deals"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Challenges / Issues</label>
                        <textarea class="form-control" name="challenges" rows="2" placeholder="e.g. Supply delay, staff turnover in ops team"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Planned Actions Next Period</label>
                        <textarea class="form-control" name="next_actions" rows="2" placeholder="e.g. Launch loyalty programme, hire 2 sales staff"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Prepared By</label>
                        <input type="text" class="form-control" name="prepared_by" placeholder="Your name / department">
                    </div>
                    <button type="submit" class="btn w-100" id="generateBtn" style="background:#3b82f6;border-color:#3b82f6;color:#fff">
                        <i class="bi bi-magic me-2"></i>Generate Report
                    </button>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="glass-card rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Management Report</h6>
                    <div id="outputActions" style="display:none">
                        <button class="btn btn-sm btn-outline-secondary" id="copyBtn" onclick="copyResult()"><i class="bi bi-clipboard me-1"></i>Copy</button>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="generate()"><i class="bi bi-arrow-clockwise me-1"></i>Regenerate</button>
                    </div>
                </div>
                <div id="placeholder" class="text-center py-5">
                    <i class="bi bi-file-earmark-bar-graph fs-1 d-block mb-3" style="opacity:0.2;color:#3b82f6"></i>
                    <p class="text-muted small">Enter your metrics and click <strong>Generate Report</strong></p>
                </div>
                <div id="loading" class="text-center py-5 d-none">
                    <div class="spinner-border mb-3" style="color:#3b82f6" role="status"></div>
                    <p class="text-muted small">Writing your management report…</p>
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
window.AI_MODULE_KEY = 'business_report';
const form = document.getElementById('moduleForm');
const btn  = document.getElementById('generateBtn');
form.addEventListener('submit', e => { e.preventDefault(); generate(); });
async function generate() {
    setState('loading');
    const data = new FormData(form);
    data.append('module', 'business_report');
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
    btn.innerHTML = s === 'loading' ? '<span class="spinner-border spinner-border-sm me-2"></span>Generating…' : '<i class="bi bi-magic me-2"></i>Generate Report';
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
