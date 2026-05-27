<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireModuleAccess('performance_review');
$pageTitle = 'Performance Review Writer';
require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/modules/" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-star-half me-2" style="color:#84cc16"></i>Performance Review Writer</h3>
            <p class="text-muted small mb-0">Generate balanced, professional performance reviews for any staff member</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">Review Details</h6>
                <form id="moduleForm">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Employee Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="employee_name" placeholder="e.g. Ahmad Razif" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Role / Position <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="employee_role" placeholder="e.g. Sales Executive" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Review Period</label>
                            <input type="text" class="form-control" name="review_period" placeholder="e.g. Jan–Jun 2025">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Review Type</label>
                            <select class="form-select" name="review_type">
                                <option>Annual</option>
                                <option>Mid-Year</option>
                                <option>Quarterly</option>
                                <option>Probation</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Overall Rating</label>
                        <select class="form-select" name="overall_rating">
                            <option value="Exceptional">Exceptional</option>
                            <option value="Exceeds Expectations">Exceeds Expectations</option>
                            <option value="Meets Expectations" selected>Meets Expectations</option>
                            <option value="Needs Improvement">Needs Improvement</option>
                            <option value="Unsatisfactory">Unsatisfactory</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Key Achievements / Strengths <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="achievements" rows="3" placeholder="What did they do well? Specific wins, contributions…" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Areas for Improvement</label>
                        <textarea class="form-control" name="areas_to_improve" rows="2" placeholder="What could they do better?"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Goals for Next Period</label>
                        <textarea class="form-control" name="goals_next_period" rows="2" placeholder="Targets, skills to develop, responsibilities to take on…"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Reviewer Name</label>
                        <input type="text" class="form-control" name="reviewer_name" placeholder="Your name">
                    </div>
                    <button type="submit" class="btn w-100" id="generateBtn" style="background:#84cc16;border-color:#84cc16;color:#1a1a1a">
                        <i class="bi bi-magic me-2"></i>Generate Review
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="glass-card rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Performance Review</h6>
                    <div id="outputActions" style="display:none">
                        <button class="btn btn-sm btn-outline-secondary" id="copyBtn" onclick="copyResult()"><i class="bi bi-clipboard me-1"></i>Copy</button>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="generate()"><i class="bi bi-arrow-clockwise me-1"></i>Regenerate</button>
                    </div>
                </div>
                <div id="placeholder" class="text-center py-5">
                    <i class="bi bi-star-half fs-1 d-block mb-3" style="opacity:0.2;color:#84cc16"></i>
                    <p class="text-muted small">Fill in the employee details and click <strong>Generate Review</strong></p>
                </div>
                <div id="loading" class="text-center py-5 d-none">
                    <div class="spinner-border mb-3" style="color:#84cc16" role="status"></div>
                    <p class="text-muted small">Writing the performance review…</p>
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
window.AI_MODULE_KEY = 'performance_review';
const form = document.getElementById('moduleForm');
const btn  = document.getElementById('generateBtn');
form.addEventListener('submit', e => { e.preventDefault(); generate(); });
async function generate() {
    setState('loading');
    const data = new FormData(form);
    data.append('module', 'performance_review');
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
        : '<i class="bi bi-magic me-2"></i>Generate Review';
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
