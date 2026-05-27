<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireLogin();
$pageTitle = 'Job Description Writer';
require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/modules/" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-person-badge me-2" style="color:#06b6d4"></i>Job Description Writer</h3>
            <p class="text-muted small mb-0">Create compelling JDs that attract the right talent</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">Role Details</h6>
                <form id="moduleForm">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Job Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="job_title" placeholder="e.g. Senior Sales Executive" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Department</label>
                            <input type="text" class="form-control" name="department" placeholder="e.g. Sales">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Employment Type</label>
                            <select class="form-select" name="employment_type">
                                <option>Full-time</option>
                                <option>Part-time</option>
                                <option>Contract</option>
                                <option>Internship</option>
                                <option>Freelance</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Location</label>
                            <input type="text" class="form-control" name="location" placeholder="e.g. KL / Remote">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Salary Range</label>
                            <input type="text" class="form-control" name="salary_range" placeholder="e.g. RM4,000–6,000">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">About the Company</label>
                        <input type="text" class="form-control" name="company_info" placeholder="e.g. Fast-growing F&B chain, 50 staff">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Key Responsibilities <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="responsibilities" rows="3" placeholder="Describe the main duties…" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Requirements</label>
                        <textarea class="form-control" name="requirements" rows="3" placeholder="Education, experience, skills needed…"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Nice to Have</label>
                        <input type="text" class="form-control" name="nice_to_have" placeholder="e.g. Bilingual, Salesforce experience">
                    </div>
                    <button type="submit" class="btn w-100" id="generateBtn" style="background:#06b6d4;border-color:#06b6d4;color:#fff">
                        <i class="bi bi-magic me-2"></i>Generate JD
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="glass-card rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Job Description</h6>
                    <div id="outputActions" style="display:none">
                        <button class="btn btn-sm btn-outline-secondary" id="copyBtn" onclick="copyResult()"><i class="bi bi-clipboard me-1"></i>Copy</button>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="generate()"><i class="bi bi-arrow-clockwise me-1"></i>Regenerate</button>
                    </div>
                </div>
                <div id="placeholder" class="text-center py-5">
                    <i class="bi bi-person-badge fs-1 d-block mb-3" style="opacity:0.2;color:#06b6d4"></i>
                    <p class="text-muted small">Fill in the role details and click <strong>Generate JD</strong></p>
                </div>
                <div id="loading" class="text-center py-5 d-none">
                    <div class="spinner-border mb-3" style="color:#06b6d4" role="status"></div>
                    <p class="text-muted small">Writing your job description…</p>
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
window.AI_MODULE_KEY = 'job_description';
const form = document.getElementById('moduleForm');
const btn  = document.getElementById('generateBtn');
form.addEventListener('submit', e => { e.preventDefault(); generate(); });
async function generate() {
    setState('loading');
    const data = new FormData(form);
    data.append('module', 'job_description');
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
        : '<i class="bi bi-magic me-2"></i>Generate JD';
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
