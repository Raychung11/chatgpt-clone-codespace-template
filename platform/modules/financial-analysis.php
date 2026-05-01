<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireLogin();
$pageTitle = 'Financial Analysis AI';
require_once '../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/modules/" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-bar-chart-line me-2" style="color:#10b981"></i>Financial Analysis AI</h3>
            <p class="text-muted small mb-0">Paste your numbers — get plain-English analysis, red flags, and recommendations</p>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">Financial Data</h6>
                <form id="moduleForm">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Business Type</label>
                            <input type="text" class="form-control" name="business_type" placeholder="e.g. F&B, Retail, SaaS">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Period</label>
                            <input type="text" class="form-control" name="period" placeholder="e.g. Q1 2025">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Revenue <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="revenue" placeholder="e.g. RM185,000" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Cost of Goods (COGS)</label>
                            <input type="text" class="form-control" name="cogs" placeholder="e.g. RM72,000">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Gross Profit</label>
                            <input type="text" class="form-control" name="gross_profit" placeholder="e.g. RM113,000">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Key Expenses (list them) <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="expenses" rows="4" placeholder="e.g.&#10;Salaries: RM55,000&#10;Rent: RM12,000&#10;Marketing: RM8,500&#10;Utilities: RM3,200&#10;Other: RM5,000" required></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Net Profit / Loss</label>
                            <input type="text" class="form-control" name="net_profit" placeholder="e.g. RM29,300">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">vs Last Period</label>
                            <input type="text" class="form-control" name="vs_last_period" placeholder="e.g. +12%, -RM5k">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">What concerns you most?</label>
                        <input type="text" class="form-control" name="concerns" placeholder="e.g. Margins shrinking, cash flow tight next month">
                    </div>
                    <button type="submit" class="btn w-100" id="generateBtn" style="background:#10b981;border-color:#10b981;color:#fff">
                        <i class="bi bi-magic me-2"></i>Analyse Financials
                    </button>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="glass-card rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Financial Analysis</h6>
                    <div id="outputActions" style="display:none">
                        <button class="btn btn-sm btn-outline-secondary" id="copyBtn" onclick="copyResult()"><i class="bi bi-clipboard me-1"></i>Copy</button>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="generate()"><i class="bi bi-arrow-clockwise me-1"></i>Regenerate</button>
                    </div>
                </div>
                <div id="placeholder" class="text-center py-5">
                    <i class="bi bi-bar-chart-line fs-1 d-block mb-3" style="opacity:0.2;color:#10b981"></i>
                    <p class="text-muted small">Enter your financial figures and click <strong>Analyse Financials</strong></p>
                </div>
                <div id="loading" class="text-center py-5 d-none">
                    <div class="spinner-border mb-3" style="color:#10b981" role="status"></div>
                    <p class="text-muted small">Analysing your financials…</p>
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
    data.append('module', 'financial_analysis');
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
    btn.innerHTML = s === 'loading' ? '<span class="spinner-border spinner-border-sm me-2"></span>Analysing…' : '<i class="bi bi-magic me-2"></i>Analyse Financials';
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
