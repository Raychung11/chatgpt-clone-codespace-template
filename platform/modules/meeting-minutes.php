<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireLogin();
$pageTitle = 'Meeting Minutes';
require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/modules/" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-journal-text me-2" style="color:#8b5cf6"></i>Meeting Minutes</h3>
            <p class="text-muted small mb-0">Transform rough notes into professional meeting minutes instantly</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">Meeting Details</h6>
                <form id="moduleForm">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Meeting Title</label>
                        <input type="text" class="form-control" name="meeting_title" placeholder="e.g. Q2 Sales Review">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Date</label>
                            <input type="date" class="form-control" name="meeting_date" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Language</label>
                            <select class="form-select" name="language">
                                <option value="English">English</option>
                                <option value="Bahasa Malaysia">Bahasa Malaysia</option>
                                <option value="Chinese Simplified">中文 (简体)</option>
                                <option value="Arabic">العربية</option>
                                <option value="French">Français</option>
                                <option value="Spanish">Español</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Attendees</label>
                        <input type="text" class="form-control" name="attendees" placeholder="e.g. John (CEO), Sarah (Sales), Mike (Finance)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Agenda Items</label>
                        <input type="text" class="form-control" name="agenda" placeholder="e.g. Q2 review, budget approval, new hires">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Raw Notes / Key Points <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="raw_notes" rows="6" placeholder="Paste your rough notes, bullet points, or voice-to-text here…" required></textarea>
                    </div>
                    <button type="submit" class="btn w-100" id="generateBtn" style="background:#8b5cf6;border-color:#8b5cf6;color:#fff">
                        <i class="bi bi-magic me-2"></i>Generate Minutes
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="glass-card rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Meeting Minutes</h6>
                    <div id="outputActions" style="display:none">
                        <button class="btn btn-sm btn-outline-secondary" id="copyBtn" onclick="copyResult()"><i class="bi bi-clipboard me-1"></i>Copy</button>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="generate()"><i class="bi bi-arrow-clockwise me-1"></i>Regenerate</button>
                    </div>
                </div>
                <div id="placeholder" class="text-center py-5">
                    <i class="bi bi-journal-text fs-1 d-block mb-3" style="opacity:0.2;color:#8b5cf6"></i>
                    <p class="text-muted small">Paste your meeting notes and click <strong>Generate Minutes</strong></p>
                </div>
                <div id="loading" class="text-center py-5 d-none">
                    <div class="spinner-border mb-3" style="color:#8b5cf6" role="status"></div>
                    <p class="text-muted small">Structuring your meeting minutes…</p>
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
window.AI_MODULE_KEY = 'meeting_minutes';
const form = document.getElementById('moduleForm');
const btn  = document.getElementById('generateBtn');
form.addEventListener('submit', e => { e.preventDefault(); generate(); });
async function generate() {
    setState('loading');
    const data = new FormData(form);
    data.append('module', 'meeting_minutes');
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
        : '<i class="bi bi-magic me-2"></i>Generate Minutes';
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
