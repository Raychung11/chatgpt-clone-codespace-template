<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireLogin();
$pageTitle = 'Chatbot Flow Designer';
require_once '../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/modules/" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-robot me-2" style="color:#06b6d4"></i>Chatbot Flow Designer</h3>
            <p class="text-muted small mb-0">Design your WhatsApp or website chatbot conversation flow with AI</p>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">Bot Details</h6>
                <form id="moduleForm">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Business Type <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="business_type" placeholder="e.g. Dental Clinic, F&B" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Bot Name</label>
                            <input type="text" class="form-control" name="bot_name" placeholder="e.g. Ava, Max">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Channel</label>
                            <select class="form-select" name="channel">
                                <option value="WhatsApp">WhatsApp</option>
                                <option value="Website Chat">Website Chat</option>
                                <option value="WhatsApp and Website Chat">Both</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Language</label>
                            <select class="form-select" name="language">
                                <option value="English">English</option>
                                <option value="Bahasa Malaysia">Bahasa Malaysia</option>
                                <option value="English and Bahasa Malaysia">Both (EN + BM)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Bot Purpose</label>
                        <select class="form-select" name="bot_purpose">
                            <option value="customer service and FAQ">Customer Service & FAQ</option>
                            <option value="lead qualification">Lead Qualification</option>
                            <option value="appointment booking">Appointment Booking</option>
                            <option value="order taking">Order Taking</option>
                            <option value="all-in-one (service, leads, booking)">All-in-one</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Top 5 Customer Scenarios <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="common_scenarios" rows="5" placeholder="e.g.&#10;1. Ask for appointment availability&#10;2. Enquire about treatment prices&#10;3. Request directions / parking&#10;4. Ask to speak to a doctor&#10;5. Want to cancel / reschedule" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">When to Hand Off to Human</label>
                        <input type="text" class="form-control" name="escalation" placeholder="e.g. When customer is angry, or asks about insurance claims">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Business Hours</label>
                        <input type="text" class="form-control" name="business_hours" placeholder="e.g. Mon–Fri 9am–6pm, Sat 9am–1pm">
                    </div>
                    <button type="submit" class="btn w-100" id="generateBtn" style="background:#06b6d4;border-color:#06b6d4;color:#fff">
                        <i class="bi bi-magic me-2"></i>Design Chatbot Flow
                    </button>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="glass-card rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Chatbot Conversation Flow</h6>
                    <div id="outputActions" style="display:none">
                        <button class="btn btn-sm btn-outline-secondary" id="copyBtn" onclick="copyResult()"><i class="bi bi-clipboard me-1"></i>Copy</button>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="generate()"><i class="bi bi-arrow-clockwise me-1"></i>Regenerate</button>
                    </div>
                </div>
                <div id="placeholder" class="text-center py-5">
                    <i class="bi bi-robot fs-1 d-block mb-3" style="opacity:0.2;color:#06b6d4"></i>
                    <p class="text-muted small">Describe your business and top scenarios, then click <strong>Design Chatbot Flow</strong></p>
                </div>
                <div id="loading" class="text-center py-5 d-none">
                    <div class="spinner-border mb-3" style="color:#06b6d4" role="status"></div>
                    <p class="text-muted small">Designing your chatbot flow…</p>
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
    data.append('module', 'chatbot_flow');
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
    btn.innerHTML = s === 'loading' ? '<span class="spinner-border spinner-border-sm me-2"></span>Designing…' : '<i class="bi bi-magic me-2"></i>Design Chatbot Flow';
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
