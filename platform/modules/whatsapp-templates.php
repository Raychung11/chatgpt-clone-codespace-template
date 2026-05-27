<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireModuleAccess('whatsapp_templates');
$pageTitle = 'WhatsApp Template Builder';
require_once '../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/modules/" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-whatsapp me-2" style="color:#25d366"></i>WhatsApp Template Builder</h3>
            <p class="text-muted small mb-0">Generate broadcast messages, auto-replies, and chatbot scripts for WhatsApp</p>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">Template Details</h6>
                <form id="moduleForm">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Template Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="template_type">
                            <option value="broadcast promotion">Broadcast — Promotion / Offer</option>
                            <option value="broadcast announcement">Broadcast — Announcement</option>
                            <option value="welcome message">Welcome Message (new contact)</option>
                            <option value="auto-reply FAQ">Auto-Reply — FAQ Response</option>
                            <option value="appointment reminder">Appointment Reminder</option>
                            <option value="order update">Order / Delivery Update</option>
                            <option value="follow-up">Follow-Up (after enquiry)</option>
                            <option value="re-engagement">Re-engagement (inactive customer)</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Business Name</label>
                            <input type="text" class="form-control" name="business_name" placeholder="e.g. Luxe Clinic KL">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Language</label>
                            <select class="form-select" name="language">
                                <option value="English">English</option>
                                <option value="Bahasa Malaysia">Bahasa Malaysia</option>
                                <option value="English and Bahasa Malaysia">Both (EN + BM)</option>
                                <option value="Chinese Simplified">中文 (简体)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Key Message / Offer <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="key_message" rows="3" placeholder="e.g. 30% off facial treatments this weekend only, limited slots" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Call to Action</label>
                        <input type="text" class="form-control" name="cta" placeholder="e.g. Reply YES to book, Click link to order">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Tone</label>
                            <select class="form-select" name="tone">
                                <option value="friendly">Friendly</option>
                                <option value="professional">Professional</option>
                                <option value="urgent">Urgent</option>
                                <option value="casual">Casual</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Variations</label>
                            <select class="form-select" name="variations">
                                <option value="3">3 variations</option>
                                <option value="2">2 variations</option>
                                <option value="5">5 variations</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="include_emoji" id="emojiCheck" checked>
                            <label class="form-check-label text-muted small" for="emojiCheck">Include relevant emojis</label>
                        </div>
                    </div>
                    <button type="submit" class="btn w-100" id="generateBtn" style="background:#25d366;border-color:#25d366;color:#fff">
                        <i class="bi bi-magic me-2"></i>Generate Templates
                    </button>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="glass-card rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">WhatsApp Templates</h6>
                    <div id="outputActions" style="display:none">
                        <button class="btn btn-sm btn-outline-secondary" id="copyBtn" onclick="copyResult()"><i class="bi bi-clipboard me-1"></i>Copy All</button>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="generate()"><i class="bi bi-arrow-clockwise me-1"></i>Regenerate</button>
                    </div>
                </div>
                <div id="placeholder" class="text-center py-5">
                    <i class="bi bi-whatsapp fs-1 d-block mb-3" style="opacity:0.2;color:#25d366"></i>
                    <p class="text-muted small">Fill in the details and click <strong>Generate Templates</strong></p>
                </div>
                <div id="loading" class="text-center py-5 d-none">
                    <div class="spinner-border mb-3" style="color:#25d366" role="status"></div>
                    <p class="text-muted small">Writing your WhatsApp templates…</p>
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
window.AI_MODULE_KEY = 'whatsapp_templates';
const form = document.getElementById('moduleForm');
const btn  = document.getElementById('generateBtn');
form.addEventListener('submit', e => { e.preventDefault(); generate(); });
async function generate() {
    setState('loading');
    const data = new FormData(form);
    if (!form.querySelector('#emojiCheck').checked) data.set('include_emoji', '');
    data.append('module', 'whatsapp_templates');
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
    btn.innerHTML = s === 'loading' ? '<span class="spinner-border spinner-border-sm me-2"></span>Generating…' : '<i class="bi bi-magic me-2"></i>Generate Templates';
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
