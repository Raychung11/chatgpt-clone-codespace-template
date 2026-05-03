<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireLogin();
$pageTitle = 'Pitch Deck Generator';
require_once '../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/modules/" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-easel me-2" style="color:#f59e0b"></i>Pitch Deck Generator</h3>
            <p class="text-muted small mb-0">Generate slide-by-slide pitch deck content that wins investors and clients</p>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">Business Details</h6>
                <form id="moduleForm">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Company Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="company_name" placeholder="e.g. BizAI" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Industry</label>
                            <input type="text" class="form-control" name="industry" placeholder="e.g. B2B SaaS, F&B Tech">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">The Problem You Solve <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="problem" rows="3" placeholder="e.g. SMEs in Malaysia spend 40% of their time on manual operations — answering WhatsApp, chasing payments, managing HR — instead of growing their business." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Your Solution <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="solution" rows="3" placeholder="e.g. BizAI is the Business Operating System for SMEs — a single platform of AI Capsules that automates customer service, sales, HR, and finance." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Target Market</label>
                        <input type="text" class="form-control" name="target_market" placeholder="e.g. 900,000 SMEs in Malaysia, F&B and retail sector">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Traction / Proof</label>
                        <textarea class="form-control" name="traction" rows="2" placeholder="e.g. 120 paying customers, RM45k MRR, 3x growth in 6 months"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Business Model</label>
                        <input type="text" class="form-control" name="business_model" placeholder="e.g. Monthly SaaS subscription, RM3,500–RM22,500/month">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Funding Ask</label>
                            <input type="text" class="form-control" name="funding_ask" placeholder="e.g. RM2M Seed">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Use of Funds</label>
                            <input type="text" class="form-control" name="use_of_funds" placeholder="e.g. 50% product, 30% sales, 20% ops">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Key Team Members</label>
                        <input type="text" class="form-control" name="team" placeholder="e.g. Ray (CEO, 10yr F&B ops), Lim (CTO, ex-Grab)">
                    </div>
                    <button type="submit" class="btn w-100" id="generateBtn" style="background:#f59e0b;border-color:#f59e0b;color:#1a1a1a">
                        <i class="bi bi-magic me-2"></i>Generate Pitch Deck
                    </button>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="glass-card rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Pitch Deck Content</h6>
                    <div id="outputActions" style="display:none">
                        <button class="btn btn-sm btn-outline-secondary" id="copyBtn" onclick="copyResult()"><i class="bi bi-clipboard me-1"></i>Copy All</button>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="generate()"><i class="bi bi-arrow-clockwise me-1"></i>Regenerate</button>
                    </div>
                </div>
                <div id="placeholder" class="text-center py-5">
                    <i class="bi bi-easel fs-1 d-block mb-3" style="opacity:0.2;color:#f59e0b"></i>
                    <p class="text-muted small">Fill in your business details and click <strong>Generate Pitch Deck</strong></p>
                </div>
                <div id="loading" class="text-center py-5 d-none">
                    <div class="spinner-border mb-3" style="color:#f59e0b" role="status"></div>
                    <p class="text-muted small">Writing your pitch deck…</p>
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
    data.append('module', 'pitch_deck');
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
    btn.innerHTML = s === 'loading' ? '<span class="spinner-border spinner-border-sm me-2"></span>Generating…' : '<i class="bi bi-magic me-2"></i>Generate Pitch Deck';
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
