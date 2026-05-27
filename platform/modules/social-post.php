<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireLogin();
$pageTitle = 'Social Post Generator';
require_once '../includes/header.php';
?>

<div class="container py-5">

    <!-- Header -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/modules/" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-share me-2 text-success"></i>Social Post Generator</h3>
            <p class="text-muted small mb-0">Generate platform-ready social media posts from a single brief</p>
        </div>
    </div>

    <div class="row g-4">

        <!-- Input Panel -->
        <div class="col-lg-5">
            <div class="glass-card rounded-4 p-4">
                <h6 class="text-white fw-semibold mb-3">Post Details</h6>
                <form id="socialForm">

                    <div class="mb-3">
                        <label class="form-label text-muted small">Business / Brand Name</label>
                        <input type="text" class="form-control" name="business" placeholder="e.g. Acme Retail">
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small">Topic / Product / Promotion <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="topic" rows="3" placeholder="e.g. Launching our new summer collection, 20% off this weekend only" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small">Platforms</label>
                        <div class="d-flex flex-wrap gap-2 mt-1">
                            <?php foreach (['Facebook','Instagram','LinkedIn','Twitter/X'] as $p): ?>
                            <div class="form-check form-check-inline m-0">
                                <input class="form-check-input platform-check" type="checkbox" name="platforms[]" id="p<?= $p ?>" value="<?= $p ?>" checked>
                                <label class="form-check-label text-muted small" for="p<?= $p ?>"><?= $p ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Tone</label>
                            <select class="form-select" name="tone">
                                <option value="engaging and friendly">Engaging</option>
                                <option value="professional">Professional</option>
                                <option value="exciting and energetic">Exciting</option>
                                <option value="educational">Educational</option>
                                <option value="humorous">Humorous</option>
                                <option value="inspirational">Inspirational</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Call to Action</label>
                            <input type="text" class="form-control" name="cta" placeholder="e.g. Shop now, Book today">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success w-100" id="generateBtn">
                        <i class="bi bi-magic me-2"></i>Generate Posts
                    </button>
                </form>
            </div>
        </div>

        <!-- Output Panel -->
        <div class="col-lg-7">
            <div class="glass-card rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Generated Posts</h6>
                    <div id="outputActions" style="display:none">
                        <button class="btn btn-sm btn-outline-secondary" id="copyAllBtn" onclick="copyAll()">
                            <i class="bi bi-clipboard me-1"></i>Copy All
                        </button>
                    </div>
                </div>

                <div id="outputPlaceholder" class="text-center py-5">
                    <i class="bi bi-share fs-1 text-muted d-block mb-3" style="opacity:0.3"></i>
                    <p class="text-muted small">Fill in the details and click <strong>Generate Posts</strong></p>
                </div>

                <div id="outputLoading" class="text-center py-5 d-none">
                    <div class="spinner-border text-success mb-3" role="status"></div>
                    <p class="text-muted small">Creating your posts…</p>
                </div>

                <div id="outputError" class="alert alert-danger d-none"></div>
                <div id="outputResult" class="d-none"></div>
            </div>
        </div>
    </div>
</div>

<script>
window.AI_MODULE_KEY = 'social';
const platformIcons = { 'Facebook': 'bi-facebook', 'Instagram': 'bi-instagram', 'LinkedIn': 'bi-linkedin', 'Twitter/X': 'bi-twitter-x' };
const platformColors = { 'Facebook': '#1877f2', 'Instagram': '#e1306c', 'LinkedIn': '#0a66c2', 'Twitter/X': '#000' };

const form = document.getElementById('socialForm');
const generateBtn = document.getElementById('generateBtn');
let lastRawText = '';

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const checked = [...document.querySelectorAll('.platform-check:checked')].map(el => el.value);
    if (!checked.length) { alert('Please select at least one platform.'); return; }

    showLoading();
    const data = new FormData(form);
    data.set('platforms', checked.join(','));
    data.append('module', 'social');

    try {
        const res = await fetch('/api/ai-generate.php', { method: 'POST', body: data });
        const json = await res.json();
        if (json.ok) { lastRawText = json.text; showResult(json.text); if (typeof refreshMemoryWidget === 'function') refreshMemoryWidget();
                    if (typeof refreshMemoryWidget === 'function') refreshMemoryWidget(); }
        else showError(json.error || 'Generation failed.');
    } catch (err) {
        showError('Network error. Please check your connection.');
    }
});

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
    document.getElementById('outputActions').style.removeProperty('display');
    const container = document.getElementById('outputResult');
    container.classList.remove('d-none');
    container.innerHTML = '';

    // Split by --- separator
    const sections = text.split(/\n---+\n?/).filter(s => s.trim());
    sections.forEach(section => {
        const lines = section.trim().split('\n');
        let platformName = '';
        let contentLines = [];

        for (let i = 0; i < lines.length; i++) {
            const stripped = lines[i].replace(/^📱\s*/, '').trim();
            if (i === 0 && (stripped.includes('FACEBOOK') || stripped.includes('INSTAGRAM') || stripped.includes('LINKEDIN') || stripped.includes('TWITTER') || stripped.includes('X'))) {
                platformName = stripped.replace(/^[*#]+|[*#]+$/g, '').trim();
            } else {
                contentLines.push(lines[i]);
            }
        }
        const content = contentLines.join('\n').trim();

        const pKey = Object.keys(platformColors).find(k => platformName.toUpperCase().includes(k.toUpperCase())) || '';
        const color = pKey ? platformColors[pKey] : '#6366f1';
        const icon  = pKey ? platformIcons[pKey] : 'bi-globe';

        const card = document.createElement('div');
        card.className = 'mb-3 p-3 rounded-3';
        card.style.cssText = `background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07)`;
        card.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi ${icon}" style="color:${color};font-size:16px"></i>
                    <span class="fw-semibold text-white small">${platformName || 'Post'}</span>
                </div>
                <button class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:11px" onclick="copySection(this)">
                    <i class="bi bi-clipboard me-1"></i>Copy
                </button>
            </div>
            <pre class="post-content mb-0" style="white-space:pre-wrap;font-family:inherit;color:#d1d5db;font-size:13px;line-height:1.6">${escHtml(content)}</pre>`;
        container.appendChild(card);
    });

    if (!sections.length) {
        container.innerHTML = `<pre style="white-space:pre-wrap;font-family:inherit;color:#d1d5db;font-size:14px">${escHtml(text)}</pre>`;
    }

    generateBtn.disabled = false;
    generateBtn.innerHTML = '<i class="bi bi-magic me-2"></i>Generate Posts';
}

function showError(msg) {
    document.getElementById('outputLoading').classList.add('d-none');
    document.getElementById('outputPlaceholder').classList.add('d-none');
    const el = document.getElementById('outputError');
    el.classList.remove('d-none');
    el.textContent = msg;
    generateBtn.disabled = false;
    generateBtn.innerHTML = '<i class="bi bi-magic me-2"></i>Generate Posts';
}

function copySection(btn) {
    const text = btn.closest('.rounded-3').querySelector('.post-content').textContent;
    navigator.clipboard.writeText(text).then(() => {
        btn.innerHTML = '<i class="bi bi-check me-1"></i>Copied!';
        setTimeout(() => { btn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy'; }, 2000);
    });
}

function copyAll() {
    navigator.clipboard.writeText(lastRawText).then(() => {
        const btn = document.getElementById('copyAllBtn');
        btn.innerHTML = '<i class="bi bi-check me-1"></i>Copied!';
        setTimeout(() => { btn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy All'; }, 2000);
    });
}

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>

<?php require_once '../includes/footer.php'; ?>
