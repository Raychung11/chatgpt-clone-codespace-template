<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireLogin();
$pageTitle = 'AI Email Writer';
require_once '../includes/header.php';
?>

<div class="container py-5">

    <!-- Header -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/modules/" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-envelope-paper me-2 text-primary"></i>AI Email Writer</h3>
            <p class="text-muted small mb-0">Describe what you need and get a professional email instantly</p>
        </div>
    </div>

    <div class="row g-4">

        <!-- Input Panel -->
        <div class="col-lg-5">
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">Email Details</h6>

                <form id="emailForm">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Email Type</label>
                        <select class="form-select" name="email_type" id="emailType">
                            <option value="sales outreach">Sales Outreach</option>
                            <option value="follow-up">Follow-Up</option>
                            <option value="proposal">Proposal</option>
                            <option value="thank you">Thank You</option>
                            <option value="complaint response">Complaint Response</option>
                            <option value="introduction">Introduction</option>
                            <option value="meeting request">Meeting Request</option>
                            <option value="invoice reminder">Invoice Reminder</option>
                            <option value="apology">Apology</option>
                            <option value="announcement">Announcement</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small">Recipient (name/role/company)</label>
                        <input type="text" class="form-control" name="recipient" placeholder="e.g. Sarah from ABC Corp, HR Manager">
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small">What is this email about? <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="context" rows="4" placeholder="Describe the key points, purpose, or any details you want included…" required></textarea>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Tone</label>
                            <select class="form-select" name="tone">
                                <option value="professional">Professional</option>
                                <option value="friendly">Friendly</option>
                                <option value="formal">Formal</option>
                                <option value="persuasive">Persuasive</option>
                                <option value="empathetic">Empathetic</option>
                                <option value="assertive">Assertive</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Length</label>
                            <select class="form-select" name="length">
                                <option value="short">Short</option>
                                <option value="medium" selected>Medium</option>
                                <option value="long">Long</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" id="generateBtn">
                        <i class="bi bi-magic me-2"></i>Generate Email
                    </button>
                </form>
            </div>
        </div>

        <!-- Output Panel -->
        <div class="col-lg-7">
            <div class="glass-card rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Generated Email</h6>
                    <div class="d-flex gap-2" id="outputActions" style="display:none!important">
                        <button class="btn btn-sm btn-outline-secondary" id="copyBtn" onclick="copyOutput()">
                            <i class="bi bi-clipboard me-1"></i>Copy
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="regenerate()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Regenerate
                        </button>
                    </div>
                </div>

                <!-- Placeholder -->
                <div id="outputPlaceholder" class="text-center py-5">
                    <i class="bi bi-envelope-paper fs-1 text-muted d-block mb-3" style="opacity:0.3"></i>
                    <p class="text-muted small">Fill in the details on the left and click <strong>Generate Email</strong></p>
                </div>

                <!-- Loading -->
                <div id="outputLoading" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <p class="text-muted small">Writing your email…</p>
                </div>

                <!-- Error -->
                <div id="outputError" class="alert alert-danger d-none"></div>

                <!-- Result -->
                <div id="outputResult" class="d-none">
                    <div id="outputSubject" class="mb-3 p-3 rounded-3" style="background:rgba(99,102,241,0.10);border:1px solid rgba(99,102,241,0.2)">
                        <div class="text-muted small mb-1">Subject</div>
                        <div class="text-white fw-semibold" id="subjectText"></div>
                    </div>
                    <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);min-height:200px">
                        <pre id="emailBody" style="white-space:pre-wrap;font-family:inherit;color:#e5e7eb;margin:0;font-size:14px;line-height:1.7"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.AI_MODULE_KEY = 'email';
const form = document.getElementById('emailForm');
const generateBtn = document.getElementById('generateBtn');

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    await runGenerate();
});

async function runGenerate() {
    showLoading();
    const data = new FormData(form);
    data.append('module', 'email');

    try {
        const res = await fetch('/api/ai-generate.php', { method: 'POST', body: data });
        const json = await res.json();
        if (json.ok) {
            showResult(json.text);
                    if (typeof refreshMemoryWidget === 'function') refreshMemoryWidget();
        } else {
            showError(json.error || 'Generation failed. Please try again.');
        }
    } catch (err) {
        showError('Network error. Please check your connection.');
    }
}

function regenerate() { runGenerate(); }

function showLoading() {
    document.getElementById('outputPlaceholder').classList.add('d-none');
    document.getElementById('outputLoading').classList.remove('d-none');
    document.getElementById('outputError').classList.add('d-none');
    document.getElementById('outputResult').classList.add('d-none');
    document.getElementById('outputActions').style.display = 'none';
    generateBtn.disabled = true;
    generateBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating…';
}

function showResult(text) {
    document.getElementById('outputLoading').classList.add('d-none');
    document.getElementById('outputResult').classList.remove('d-none');
    document.getElementById('outputActions').style.removeProperty('display');

    // Split subject from body
    const lines = text.trim().split('\n');
    let subject = '';
    let bodyStart = 0;
    for (let i = 0; i < lines.length; i++) {
        if (lines[i].toLowerCase().startsWith('subject:')) {
            subject = lines[i].replace(/^subject:\s*/i, '').trim();
            bodyStart = i + 1;
            break;
        }
    }
    // Skip blank lines after subject
    while (bodyStart < lines.length && lines[bodyStart].trim() === '') bodyStart++;

    document.getElementById('subjectText').textContent = subject || '(No subject line)';
    document.getElementById('emailBody').textContent = lines.slice(bodyStart).join('\n').trim();

    generateBtn.disabled = false;
    generateBtn.innerHTML = '<i class="bi bi-magic me-2"></i>Generate Email';
}

function showError(msg) {
    document.getElementById('outputLoading').classList.add('d-none');
    document.getElementById('outputPlaceholder').classList.add('d-none');
    const el = document.getElementById('outputError');
    el.classList.remove('d-none');
    el.textContent = msg;
    generateBtn.disabled = false;
    generateBtn.innerHTML = '<i class="bi bi-magic me-2"></i>Generate Email';
}

function copyOutput() {
    const subject = document.getElementById('subjectText').textContent;
    const body = document.getElementById('emailBody').textContent;
    const full = `Subject: ${subject}\n\n${body}`;
    navigator.clipboard.writeText(full).then(() => {
        const btn = document.getElementById('copyBtn');
        btn.innerHTML = '<i class="bi bi-check me-1"></i>Copied!';
        setTimeout(() => { btn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy'; }, 2000);
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
