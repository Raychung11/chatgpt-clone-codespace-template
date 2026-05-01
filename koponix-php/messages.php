<?php
require_once __DIR__ . '/layout.php';
require_login('member_portal.php');

$member   = current_member();
$kop_id   = $member['koperasi_id'];
$mem_name = $member['name'];

// ── Start a new conversation from "Message" button ────────────
if (!empty($_GET['start']) && !empty($_GET['seller_kop'])) {
    $seller_kop  = $_GET['seller_kop'];
    $seller_name = $_GET['seller_name'] ?? 'Seller';
    $subject     = $_GET['subject']     ?? 'Enquiry';
    $seller_id   = $_GET['seller_id']   ?? '';

    if ($seller_kop !== $kop_id) { // don't message yourself
        $conv_id = create_conversation($kop_id, $mem_name, $seller_kop, $seller_name, $subject, $seller_id);
        redirect('messages.php?conv=' . urlencode($conv_id));
    }
}

$active_conv_id = $_GET['conv'] ?? '';
$my_convs       = get_conversations_for_member($kop_id);
$active_conv    = $active_conv_id ? get_conversation($active_conv_id) : null;
$messages_list  = $active_conv ? get_messages($active_conv_id) : [];

html_head('Messages');
html_body_open();
?>

<div class="page-title">💬 Messages</div>

<?php if (!$my_convs && !$active_conv): ?>
<div class="text-center py-5 text-muted">
    <div style="font-size:3rem">💬</div>
    <h5 class="mt-2">No messages yet</h5>
    <p>Go to <a href="find_services.php">Find Services</a> and click <strong>Message</strong> on any seller.</p>
</div>
<?php html_footer(); return; ?>
<?php endif; ?>

<div class="row g-3">
<!-- Conversation list -->
<div class="col-md-4 col-lg-3">
    <div class="card">
        <div class="card-header small fw-bold">Conversations</div>
        <div class="list-group list-group-flush">
        <?php if (!$my_convs): ?>
            <div class="list-group-item text-muted small">No conversations yet.</div>
        <?php endif; ?>
        <?php foreach ($my_convs as $c):
            $other = ($c['buyer_kop_id'] === $kop_id) ? $c['seller_name'] : $c['buyer_name'];
            $is_active = ($c['id'] === $active_conv_id);
        ?>
            <a href="messages.php?conv=<?= urlencode($c['id']) ?>"
               class="list-group-item list-group-item-action <?= $is_active?'active':'' ?> small py-2">
                <div class="fw-bold"><?= e($other) ?></div>
                <div class="<?= $is_active?'text-white-50':'text-muted' ?>" style="font-size:.72rem">
                    <?= e(substr($c['subject'],0,40)) ?>
                </div>
                <div class="<?= $is_active?'text-white-50':'text-muted' ?>" style="font-size:.68rem"><?= e($c['created_date']) ?></div>
            </a>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Chat panel -->
<div class="col-md-8 col-lg-9">
<?php if (!$active_conv): ?>
    <div class="card p-5 text-center text-muted">
        <div style="font-size:2.5rem">👈</div>
        <p class="mt-2">Select a conversation to view messages</p>
    </div>
<?php else:
    $other_name = ($active_conv['buyer_kop_id'] === $kop_id) ? $active_conv['seller_name'] : $active_conv['buyer_name'];
?>
    <div class="card">
        <div class="card-header">
            <strong><?= e($active_conv['subject']) ?></strong>
            <small class="text-muted ms-2">with <?= e($other_name) ?> · <?= e($active_conv['created_date']) ?></small>
        </div>
        <div class="card-body p-2">
            <div class="chat-box" id="chatBox">
            <?php if (!$messages_list): ?>
                <div class="text-muted small text-center mt-3">No messages yet. Say hello!</div>
            <?php endif; ?>
            <?php foreach ($messages_list as $msg):
                $is_mine = ($msg['sender_kop_id'] === $kop_id);
                $is_ai   = (bool)$msg['is_ai'];
                $ts      = substr($msg['timestamp'], 0, 16);
                if ($is_ai): ?>
                    <div class="chat-msg">
                        <div class="small text-muted mb-1">🤖 Koponix AI</div>
                        <div class="bubble ai-msg-bubble" style="background:#eaf4fb;border:1px solid #b8d9f0;border-radius:14px;padding:.5rem .85rem;display:inline-block;max-width:80%;font-size:.85rem;color:#1a5276"><?= nl2br(e($msg['text'])) ?></div>
                        <div class="chat-ts"><?= e($ts) ?></div>
                    </div>
                <?php elseif ($is_mine): ?>
                    <div class="chat-msg mine">
                        <div class="small text-muted mb-1" style="text-align:right">You</div>
                        <div class="bubble"><?= nl2br(e($msg['text'])) ?></div>
                        <div class="chat-ts"><?= e($ts) ?></div>
                    </div>
                <?php else: ?>
                    <div class="chat-msg theirs">
                        <div class="small text-muted mb-1"><?= e($msg['sender_name']) ?></div>
                        <div class="bubble"><?= nl2br(e($msg['text'])) ?></div>
                        <div class="chat-ts"><?= e($ts) ?></div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            </div>
        </div>
        <div class="card-footer bg-white">
            <div class="input-group mb-2">
                <textarea id="msgInput" class="form-control" rows="2"
                    placeholder="Type a message…" style="resize:none"></textarea>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary flex-fill" id="sendMsgBtn">Send</button>
                <button class="btn btn-outline-info" id="askAiBtn" title="Ask AI bot for suggestions">🤖 Ask AI</button>
            </div>
            <div id="msgStatus" class="mt-1 small text-muted"></div>
        </div>
    </div>
<?php endif; ?>
</div>
</div>

<?php if ($active_conv): ?>
<script>
const convId   = <?= json_encode($active_conv_id) ?>;
const myKopId  = <?= json_encode($kop_id) ?>;
const myName   = <?= json_encode($mem_name) ?>;
const chatBox  = document.getElementById('chatBox');

// Scroll to bottom on load
chatBox.scrollTop = chatBox.scrollHeight;

function appendBubble(senderName, text, type) {
    const ts   = new Date().toLocaleTimeString('en-MY',{hour:'2-digit',minute:'2-digit'});
    const wrap = document.createElement('div');
    if (type === 'mine')  wrap.className = 'chat-msg mine';
    else if (type === 'ai') wrap.className = 'chat-msg';
    else wrap.className = 'chat-msg theirs';

    const lbl = document.createElement('div');
    lbl.className = 'small text-muted mb-1';
    if (type === 'mine') { lbl.style.textAlign = 'right'; lbl.textContent = 'You'; }
    else if (type === 'ai') { lbl.textContent = '🤖 Koponix AI'; }
    else { lbl.textContent = senderName; }

    const bub = document.createElement('div');
    if (type === 'ai') {
        bub.style.cssText = 'background:#eaf4fb;border:1px solid #b8d9f0;border-radius:14px;padding:.5rem .85rem;display:inline-block;max-width:80%;font-size:.85rem;color:#1a5276';
    } else {
        bub.className = 'bubble';
    }
    bub.innerHTML = escHtml(text).replace(/\n/g,'<br>');

    const tsd = document.createElement('div'); tsd.className = 'chat-ts'; tsd.textContent = ts;
    wrap.append(lbl, bub, tsd);
    chatBox.appendChild(wrap);
    chatBox.scrollTop = chatBox.scrollHeight;
}

async function sendMessage(text, withAi = false) {
    if (!text.trim()) return;
    appendBubble('You', text, 'mine');
    document.getElementById('msgInput').value = '';
    document.getElementById('msgStatus').textContent = withAi ? '🤖 AI is thinking…' : '';

    try {
        const r = await csrfFetch('ajax/send_message.php', {
            method: 'POST',
            body: JSON.stringify({
                conv_id: convId,
                text: text,
                ask_ai: withAi,
                subject: <?= json_encode($active_conv['subject']) ?>
            })
        });
        const data = await r.json();
        if (data.ai_reply) {
            appendBubble('Koponix AI', data.ai_reply, 'ai');
        }
        document.getElementById('msgStatus').textContent = '';
    } catch(e) {
        document.getElementById('msgStatus').textContent = 'Error sending message.';
    }
}

async function askAiOnly() {
    document.getElementById('msgStatus').textContent = '🤖 AI is thinking…';
    try {
        const r = await csrfFetch('ajax/send_message.php', {
            method: 'POST',
            body: JSON.stringify({
                conv_id: convId,
                text: '',
                ask_ai: true,
                ai_only: true,
                subject: <?= json_encode($active_conv['subject']) ?>
            })
        });
        const data = await r.json();
        if (data.ai_reply) appendBubble('Koponix AI', data.ai_reply, 'ai');
        document.getElementById('msgStatus').textContent = '';
    } catch(e) {
        document.getElementById('msgStatus').textContent = 'AI error.';
    }
}

document.getElementById('sendMsgBtn').addEventListener('click', () => {
    sendMessage(document.getElementById('msgInput').value.trim(), false);
});
document.getElementById('askAiBtn').addEventListener('click', () => {
    const text = document.getElementById('msgInput').value.trim();
    if (text) sendMessage(text, true);
    else askAiOnly();
});
document.getElementById('msgInput').addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(e.target.value.trim(), false); }
});
</script>
<?php endif; ?>

<?php html_footer(); ?>
