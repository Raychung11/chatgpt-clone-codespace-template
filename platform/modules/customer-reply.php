<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
Auth::requireLogin();
$pageTitle = 'Customer Reply Writer';
require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/modules/" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-chat-dots me-2" style="color:#14b8a6"></i>Customer Reply Writer</h3>
            <p class="text-muted small mb-0">Reply to complaints, enquiries, and reviews with confidence</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">Customer Message</h6>
                <form id="moduleForm">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Paste Customer Message <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="customer_message" rows="5" placeholder="Paste the customer's complaint, enquiry, or review here…" required></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Message Type</label>
                            <select class="form-select" name="message_type">
                                <option value="complaint">Complaint</option>
                                <option value="enquiry">Enquiry</option>
                                <option value="review response">Review Response</option>
                                <option value="refund request">Refund Request</option>
                                <option value="compliment">Compliment</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Reply Tone</label>
                            <select class="form-select" name="tone">
                                <option value="professional">Professional</option>
                                <option value="empathetic">Empathetic</option>
                                <option value="formal">Formal</option>
                                <option value="friendly">Friendly</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Resolution / Action Being Taken</label>
                        <textarea class="form-control" name="resolution" rows="2" placeholder="e.g. Offering a replacement, full refund, escalating to manager…"></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Your Name</label>
                            <input type="text" class="form-control" name="your_name" placeholder="e.g. Sarah">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Company Name</label>
                            <input type="text" class="form-control" name="company_name" placeholder="e.g. ABC Store">
                        </div>
                    </div>
                    <button type="submit" class="btn w-100" id="generateBtn" style="background:#14b8a6;border-color:#14b8a6;color:#fff">
                        <i class="bi bi-magic me-2"></i>Generate Reply
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="glass-card rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Customer Reply</h6>
                    <div id="outputActions" style="display:none">
                        <button class="btn btn-sm btn-outline-secondary" id="copyBtn" onclick="copyResult()"><i class="bi bi-clipboard me-1"></i>Copy</button>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="generate()"><i class="bi bi-arrow-clockwise me-1"></i>Regenerate</button>
                    </div>
                </div>
                <div id="placeholder" class="text-center py-5">
                    <i class="bi bi-chat-dots fs-1 d-block mb-3" style="opacity:0.2;color:#14b8a6"></i>
                    <p class="text-muted small">Paste the customer message and click <strong>Generate Reply</strong></p>
                </div>
                <div id="loading" class="text-center py-5 d-none">
                    <div class="spinner-border mb-3" style="color:#14b8a6" role="status"></div>
                    <p class="text-muted small">Writing your customer reply…</p>
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
window.AI_MODULE_KEY = 'customer_reply';
const form = document.getElementById('moduleForm');
const btn  = document.getElementById('generateBtn');
form.addEventListener('submit', e => { e.preventDefault(); generate(); });
async function generate() {
    setState('loading');
    const data = new FormData(form);
    data.append('module', 'customer_reply');
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
        : '<i class="bi bi-magic me-2"></i>Generate Reply';
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
