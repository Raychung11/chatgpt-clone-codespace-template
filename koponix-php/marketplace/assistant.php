<?php
require_once __DIR__ . '/../layout.php';
html_head('AI Assistant');
html_body_open();

// Pre-filled prompt from links
$initial_prompt = trim($_GET['prompt'] ?? '');
?>

<div class="page-title">💬 AI Assistant</div>
<p class="text-muted small mb-3">Chat with Koponix AI — ask about services, pricing, how to list your skill, or anything koperasi-related.</p>

<div class="card" style="max-width:760px">
    <div class="card-header text-white" style="background:linear-gradient(135deg,#1a5276,#2e86c1)">
        <strong>🤖 Koponix AI</strong>
        <small class="opacity-75 ms-2">Powered by Claude</small>
    </div>
    <div class="card-body p-0">
        <div class="chat-box" id="chatBox"></div>
    </div>
    <div class="card-footer bg-white">
        <div class="input-group">
            <textarea id="userInput" class="form-control" rows="2"
                placeholder="Ask anything about Koponix, services, pricing…"
                style="resize:none"><?= e($initial_prompt) ?></textarea>
            <button class="btn btn-primary" id="sendBtn" type="button">Send</button>
        </div>
        <div class="d-flex gap-2 mt-2 flex-wrap">
            <button class="btn btn-sm btn-outline-secondary suggestion-btn" type="button"
                data-text="How do I register my service on Koponix?">How to register?</button>
            <button class="btn btn-sm btn-outline-secondary suggestion-btn" type="button"
                data-text="What categories of services are available?">Available categories?</button>
            <button class="btn btn-sm btn-outline-secondary suggestion-btn" type="button"
                data-text="How does the matching engine work?">How matching works?</button>
            <button class="btn btn-sm btn-outline-secondary suggestion-btn" type="button"
                data-text="I need help writing a service description for my business.">Help with description</button>
        </div>
    </div>
</div>

<script>
const chatBox       = document.getElementById('chatBox');
const userInput     = document.getElementById('userInput');
const sendBtn       = document.getElementById('sendBtn');
const systemPrompt  = <?= json_encode(KOPONIX_SYSTEM_PROMPT) ?>;
let   history       = [];

function addBubble(role, text) {
    const cls  = role === 'user' ? 'mine' : 'theirs';
    const name = role === 'user' ? 'You' : '🤖 Koponix AI';
    const ts   = new Date().toLocaleTimeString('en-MY', {hour:'2-digit', minute:'2-digit'});
    const wrap = document.createElement('div');
    wrap.className = 'chat-msg ' + cls;
    const lbl = document.createElement('div'); lbl.className = 'small text-muted mb-1'; lbl.textContent = name;
    const bub = document.createElement('div'); bub.className = 'bubble'; bub.innerHTML = escHtml(text).replace(/\n/g,'<br>');
    const tsd = document.createElement('div'); tsd.className = 'chat-ts'; tsd.textContent = ts;
    wrap.append(lbl, bub, tsd);
    chatBox.appendChild(wrap);
    chatBox.scrollTop = chatBox.scrollHeight;
}

async function sendMessage() {
    const text = userInput.value.trim();
    if (!text) return;
    addBubble('user', text);
    history.push({role:'user', content: text});
    userInput.value = '';
    sendBtn.disabled = true;
    sendBtn.textContent = '…';

    try {
        const res  = await csrfFetch('ajax/ai_chat.php', {
            method: 'POST',
            body: JSON.stringify({messages: history})
        });
        const data = await res.json();
        const reply = data.reply || 'Sorry, I could not respond right now.';
        history.push({role:'assistant', content: reply});
        addBubble('ai', reply);
    } catch(e) {
        addBubble('ai', 'Connection error. Please try again.');
    }
    sendBtn.disabled = false;
    sendBtn.textContent = 'Send';
}

sendBtn.addEventListener('click', sendMessage);
userInput.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
});
document.querySelectorAll('.suggestion-btn').forEach(b => {
    b.addEventListener('click', () => { userInput.value = b.dataset.text; userInput.focus(); });
});

// Auto-send if pre-filled
<?php if ($initial_prompt): ?>
window.addEventListener('load', () => sendMessage());
<?php endif; ?>
</script>
<?php html_footer(); ?>
