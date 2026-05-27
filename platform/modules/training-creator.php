<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireModuleAccess('training_creator');
$pageTitle = 'Training Material Creator';
require_once '../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/modules/" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-mortarboard me-2" style="color:#8b5cf6"></i>Training Material Creator</h3>
            <p class="text-muted small mb-0">Generate complete training modules, quizzes, and onboarding guides with AI</p>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">Training Details</h6>
                <form id="moduleForm">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Course / Training Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="course_title" placeholder="e.g. New Staff Onboarding — Customer Service" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Target Audience <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="target_audience" placeholder="e.g. New frontline staff with no prior experience" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Learning Objectives</label>
                        <textarea class="form-control" name="learning_objectives" rows="2" placeholder="e.g. Staff can handle customer complaints, process refunds, and escalate issues correctly"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Topics to Cover <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="topics" rows="4" placeholder="e.g.&#10;1. Company values and service standards&#10;2. Handling complaints step-by-step&#10;3. Refund and exchange policy&#10;4. Escalation procedure&#10;5. Common customer scenarios" required></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Duration</label>
                            <select class="form-select" name="duration">
                                <option value="30 minutes">30 minutes</option>
                                <option value="1 hour" selected>1 hour</option>
                                <option value="half day">Half day</option>
                                <option value="full day">Full day</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Output Format</label>
                            <select class="form-select" name="format">
                                <option value="slide deck outline">Slide Deck Outline</option>
                                <option value="written training guide" selected>Written Training Guide</option>
                                <option value="video script">Video Script</option>
                                <option value="workshop facilitation plan">Workshop Plan</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="include_quiz" id="quizCheck" checked>
                            <label class="form-check-label text-muted small" for="quizCheck">Include quiz questions (10 questions)</label>
                        </div>
                    </div>
                    <button type="submit" class="btn w-100" id="generateBtn" style="background:#8b5cf6;border-color:#8b5cf6;color:#fff">
                        <i class="bi bi-magic me-2"></i>Create Training Material
                    </button>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="glass-card rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Training Material</h6>
                    <div id="outputActions" style="display:none">
                        <button class="btn btn-sm btn-outline-secondary" id="copyBtn" onclick="copyResult()"><i class="bi bi-clipboard me-1"></i>Copy</button>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="generate()"><i class="bi bi-arrow-clockwise me-1"></i>Regenerate</button>
                    </div>
                </div>
                <div id="placeholder" class="text-center py-5">
                    <i class="bi bi-mortarboard fs-1 d-block mb-3" style="opacity:0.2;color:#8b5cf6"></i>
                    <p class="text-muted small">Fill in the training details and click <strong>Create Training Material</strong></p>
                </div>
                <div id="loading" class="text-center py-5 d-none">
                    <div class="spinner-border mb-3" style="color:#8b5cf6" role="status"></div>
                    <p class="text-muted small">Building your training material…</p>
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
window.AI_MODULE_KEY = 'training_creator';
const form = document.getElementById('moduleForm');
const btn  = document.getElementById('generateBtn');
form.addEventListener('submit', e => { e.preventDefault(); generate(); });
async function generate() {
    setState('loading');
    const data = new FormData(form);
    data.append('module', 'training_creator');
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
    btn.innerHTML = s === 'loading' ? '<span class="spinner-border spinner-border-sm me-2"></span>Generating…' : '<i class="bi bi-magic me-2"></i>Create Training Material';
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
