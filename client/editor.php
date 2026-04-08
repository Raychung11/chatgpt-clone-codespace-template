<?php
declare(strict_types=1);

/**
 * client/editor.php
 * Browser-side video caption editor + merger.
 * Uses HTML5 Canvas + MediaRecorder — no server-side FFmpeg needed.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/layout.php';

boot_session();
$user = require_auth('/public/login.php');
$uid  = (int)$user['id'];
$pdo  = db();

// Load user's completed videos
$videos = $pdo->prepare(
    'SELECT vj.id, vj.prompt, vj.resolution, vj.duration, vj.created_at,
            vo.cdn_url, vo.thumbnail
     FROM video_jobs vj
     JOIN video_outputs vo ON vo.job_id = vj.id
     WHERE vj.user_id = ? AND vj.status = "completed"
       AND vo.cdn_url IS NOT NULL AND vo.cdn_url != ""
     ORDER BY vj.created_at DESC
     LIMIT 50'
);
$videos->execute([$uid]);
$videos = $videos->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Editor — <?= e(setting('site_name','VideoSaaS')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
    <style>
        /* ── Layout ── */
        .editor-wrap {
            display: grid;
            grid-template-columns: 260px 1fr;
            grid-template-rows: auto;
            gap: 0;
            height: calc(100vh - 64px);
            overflow: hidden;
        }
        /* ── Sidebar ── */
        .editor-sidebar {
            background: var(--color-surface2);
            border-right: 1px solid var(--color-border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .sidebar-header {
            padding: 14px 16px;
            border-bottom: 1px solid var(--color-border);
            font-weight: 700;
            font-size: .9rem;
        }
        .sidebar-list {
            overflow-y: auto;
            flex: 1;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .vid-thumb-card {
            background: var(--color-surface);
            border: 2px solid var(--color-border);
            border-radius: var(--radius);
            padding: 8px;
            cursor: pointer;
            transition: border-color .2s;
        }
        .vid-thumb-card:hover { border-color: var(--color-primary); }
        .vid-thumb-card.in-seq { border-color: var(--color-accent); opacity: .6; }
        .vid-thumb-preview {
            width: 100%;
            aspect-ratio: 16/9;
            background: #000;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }
        .vid-thumb-preview video {
            width: 100%; height: 100%; object-fit: cover; display: block;
        }
        .vid-thumb-info {
            margin-top: 6px;
            font-size: .75rem;
            color: var(--color-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        /* ── Main editor ── */
        .editor-main {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #0d0d0f;
        }
        /* ── Canvas preview ── */
        .preview-area {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0a0a0c;
            padding: 16px;
            position: relative;
            min-height: 0;
        }
        #previewCanvas {
            max-width: 100%;
            max-height: 100%;
            border-radius: 6px;
            box-shadow: 0 4px 32px rgba(0,0,0,.6);
        }
        .preview-empty {
            color: var(--color-muted);
            text-align: center;
        }
        /* ── Playback bar ── */
        .playback-bar {
            background: var(--color-surface2);
            border-top: 1px solid var(--color-border);
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-shrink: 0;
        }
        .playback-bar button {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 6px;
            color: var(--color-text);
            padding: 6px 14px;
            cursor: pointer;
            font-size: .85rem;
            transition: background .15s;
        }
        .playback-bar button:hover { background: var(--color-primary); }
        .time-display { font-size: .85rem; color: var(--color-muted); min-width: 100px; }
        .progress-track {
            flex: 1;
            height: 6px;
            background: var(--color-border);
            border-radius: 3px;
            position: relative;
            cursor: pointer;
        }
        .progress-fill {
            height: 100%;
            background: var(--color-primary);
            border-radius: 3px;
            width: 0%;
            pointer-events: none;
        }
        /* ── Bottom panels (sequence + captions) ── */
        .bottom-panels {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-top: 1px solid var(--color-border);
            max-height: 280px;
            flex-shrink: 0;
        }
        .panel {
            overflow-y: auto;
            padding: 12px 14px;
            border-right: 1px solid var(--color-border);
        }
        .panel:last-child { border-right: none; }
        .panel-title {
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--color-muted);
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        /* Sequence */
        .sequence-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .seq-item {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 6px;
            padding: 8px 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: .82rem;
        }
        .seq-item .seq-label { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .seq-item button {
            background: transparent;
            border: none;
            color: var(--color-muted);
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: .8rem;
        }
        .seq-item button:hover { background: var(--color-danger); color: #fff; }
        .seq-empty {
            color: var(--color-muted);
            font-size: .82rem;
            text-align: center;
            padding: 16px 0;
        }
        /* Caption list */
        .cap-list { display: flex; flex-direction: column; gap: 6px; }
        .cap-item {
            background: var(--color-surface);
            border-left: 3px solid var(--color-primary);
            border-radius: 0 6px 6px 0;
            padding: 6px 10px;
            font-size: .82rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .cap-item .cap-text { flex: 1; font-weight: 600; }
        .cap-item .cap-time { color: var(--color-muted); font-size: .75rem; white-space: nowrap; }
        .cap-item button {
            background: transparent;
            border: none;
            color: var(--color-muted);
            cursor: pointer;
            padding: 2px 5px;
            border-radius: 3px;
        }
        .cap-item button:hover { background: var(--color-danger); color: #fff; }
        /* Caption add form */
        .cap-add-form {
            background: var(--color-surface2);
            border: 1px solid var(--color-border);
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 10px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .cap-add-form input, .cap-add-form select {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 4px;
            color: var(--color-text);
            padding: 4px 8px;
            font-size: .82rem;
            width: 100%;
        }
        .cap-row { display: flex; gap: 6px; }
        .cap-row > * { flex: 1; }
        /* Export panel */
        .export-bar {
            background: var(--color-surface2);
            border-top: 1px solid var(--color-border);
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }
        .export-bar .btn { font-size: .83rem; padding: 7px 18px; }
        #recStatus { font-size: .8rem; color: var(--color-muted); }
        #recDot {
            width: 10px; height: 10px; border-radius: 50%;
            background: #e55; display: none;
            animation: blink 1s infinite;
        }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0} }
        /* Responsive */
        @media (max-width: 700px) {
            .editor-wrap { grid-template-columns: 1fr; grid-template-rows: auto 1fr; height: auto; }
            .editor-sidebar { height: 180px; border-right: none; border-bottom: 1px solid var(--color-border); }
            .sidebar-list { flex-direction: row; overflow-x: auto; overflow-y: hidden; padding: 8px; }
            .vid-thumb-card { min-width: 120px; }
            .bottom-panels { grid-template-columns: 1fr; max-height: 400px; }
        }
    </style>
</head>
<body>
<?php render_client_navbar($user, 'history'); ?>

<div class="editor-wrap">

    <!-- ── Sidebar: video library ─────────────────────────────────────────── -->
    <div class="editor-sidebar">
        <div class="sidebar-header">📁 Your Videos</div>
        <div class="sidebar-list" id="sidebarList">
            <?php if (empty($videos)): ?>
                <div style="padding:16px;color:var(--color-muted);font-size:.83rem;text-align:center">
                    No completed videos yet.<br>
                    <a href="<?= BASE_URL ?>/client/generate.php" style="color:var(--color-primary)">Generate one →</a>
                </div>
            <?php else: ?>
                <?php foreach ($videos as $v): ?>
                    <div class="vid-thumb-card"
                         id="card_<?= (int)$v['id'] ?>"
                         data-id="<?= (int)$v['id'] ?>"
                         data-url="<?= e($v['cdn_url']) ?>"
                         data-label="<?= e(mb_strimwidth($v['prompt'], 0, 40, '…')) ?>"
                         data-thumb="<?= e($v['thumbnail'] ?? '') ?>"
                         onclick="addToSequence(this)">
                        <div class="vid-thumb-preview">
                            <video src="<?= e($v['cdn_url']) ?>#t=0.5"
                                   preload="metadata" muted playsinline
                                   style="pointer-events:none"></video>
                        </div>
                        <div class="vid-thumb-info" title="<?= e($v['prompt']) ?>">
                            <?= e(mb_strimwidth($v['prompt'], 0, 45, '…')) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Main editor area ───────────────────────────────────────────────── -->
    <div class="editor-main">

        <!-- Canvas preview -->
        <div class="preview-area" id="previewArea">
            <div class="preview-empty" id="previewEmpty">
                <div style="font-size:3rem;margin-bottom:8px">🎬</div>
                <div style="font-size:1rem;font-weight:600">Click a video to add it to the sequence</div>
                <div style="font-size:.85rem;margin-top:4px">Then add captions and export</div>
            </div>
            <canvas id="previewCanvas" width="1280" height="720" style="display:none"></canvas>
        </div>

        <!-- Playback bar -->
        <div class="playback-bar">
            <button onclick="togglePlay()" id="playBtn">▶ Play</button>
            <button onclick="restartPreview()">⏮ Restart</button>
            <div class="time-display" id="timeDisplay">0.0 / 0.0 s</div>
            <div class="progress-track" id="progressTrack" onclick="seekTo(event)">
                <div class="progress-fill" id="progressFill"></div>
            </div>
        </div>

        <!-- Bottom: sequence + captions -->
        <div class="bottom-panels">

            <!-- Sequence (merger) -->
            <div class="panel">
                <div class="panel-title">
                    <span>Sequence (Merger)</span>
                    <button onclick="clearSequence()"
                            style="background:none;border:none;color:var(--color-muted);cursor:pointer;font-size:.75rem">
                        Clear
                    </button>
                </div>
                <div class="sequence-list" id="sequenceList">
                    <div class="seq-empty" id="seqEmpty">Add videos from the left panel</div>
                </div>
            </div>

            <!-- Caption editor -->
            <div class="panel">
                <div class="panel-title">
                    <span>Captions</span>
                    <span id="capTarget" style="font-size:.72rem;color:var(--color-primary)"></span>
                </div>

                <!-- Add caption form -->
                <div class="cap-add-form" id="capAddForm" style="display:none">
                    <input type="text" id="capText" placeholder="Caption text…" maxlength="120">
                    <div class="cap-row">
                        <div>
                            <label style="font-size:.72rem;color:var(--color-muted)">Start (s)</label>
                            <input type="number" id="capStart" value="0" min="0" step="0.5">
                        </div>
                        <div>
                            <label style="font-size:.72rem;color:var(--color-muted)">End (s)</label>
                            <input type="number" id="capEnd" value="3" min="0" step="0.5">
                        </div>
                    </div>
                    <div class="cap-row">
                        <div>
                            <label style="font-size:.72rem;color:var(--color-muted)">Position</label>
                            <select id="capPos">
                                <option value="bottom">Bottom</option>
                                <option value="center">Center</option>
                                <option value="top">Top</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:.72rem;color:var(--color-muted)">Size</label>
                            <select id="capSize">
                                <option value="36">Small</option>
                                <option value="52" selected>Medium</option>
                                <option value="72">Large</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:.72rem;color:var(--color-muted)">Color</label>
                            <input type="color" id="capColor" value="#ffffff" style="height:31px;padding:2px">
                        </div>
                    </div>
                    <button onclick="addCaption()"
                            style="background:var(--color-primary);border:none;color:#fff;border-radius:4px;padding:6px;cursor:pointer;font-size:.83rem">
                        + Add Caption
                    </button>
                </div>

                <div class="cap-list" id="capList"></div>
                <div id="capEmpty" style="color:var(--color-muted);font-size:.82rem;text-align:center;padding:16px 0">
                    Select a video in the sequence to edit its captions
                </div>
            </div>
        </div>

        <!-- Export bar -->
        <div class="export-bar">
            <span id="recDot"></span>
            <span id="recStatus">Ready to export</span>
            <button class="btn btn-primary" onclick="startExport()" id="exportBtn">
                ⬇ Export with Captions
            </button>
            <button class="btn btn-ghost" onclick="downloadSRT()" id="srtBtn">
                📄 Download SRT
            </button>
            <button class="btn btn-ghost" onclick="stopExport()" id="stopBtn" style="display:none">
                ⏹ Stop Recording
            </button>
            <div style="flex:1"></div>
            <div style="font-size:.75rem;color:var(--color-muted)">
                Export produces a WebM file (plays in Chrome, Edge, Firefox)
            </div>
        </div>

    </div><!-- /editor-main -->
</div><!-- /editor-wrap -->

<!-- Hidden video elements for canvas rendering (cross-origin anonymous) -->
<div id="hiddenVideos" style="position:fixed;left:-9999px;width:1px;height:1px;overflow:hidden"></div>

<script>
// ── State ──────────────────────────────────────────────────────────────────────
const sequence    = [];   // [{id, url, label, captions:[{text,start,end,pos,size,color}]}]
let   activeSegIdx = -1;  // which segment's captions we're editing
let   playing     = false;
let   currentSegIdx = 0;
let   rafId       = null;
let   mediaRecorder = null;
let   recChunks   = [];

const canvas  = document.getElementById('previewCanvas');
const ctx     = canvas.getContext('2d');
const W = 1280, H = 720;

// ── Sidebar: add video to sequence ────────────────────────────────────────────
function addToSequence(card) {
    const id    = card.dataset.id;
    const url   = card.dataset.url;
    const label = card.dataset.label;

    // Allow same video multiple times in sequence (for looping effect)
    const seg = { id: id + '_' + Date.now(), vidId: id, url, label, captions: [] };
    sequence.push(seg);

    ensureVideoElement(seg);
    renderSequenceList();
    selectSegment(sequence.length - 1);
    showCanvas();
}

function ensureVideoElement(seg) {
    if (document.getElementById('hv_' + seg.id)) return;
    const v = document.createElement('video');
    v.id        = 'hv_' + seg.id;
    v.src       = seg.url;
    v.crossOrigin = 'anonymous';
    v.preload   = 'auto';
    v.muted     = false;
    v.playsInline = true;
    v.style.cssText = 'width:1px;height:1px';
    document.getElementById('hiddenVideos').appendChild(v);
}

function getVideo(seg) {
    return document.getElementById('hv_' + seg.id);
}

// ── Sequence list UI ──────────────────────────────────────────────────────────
function renderSequenceList() {
    const list  = document.getElementById('sequenceList');
    const empty = document.getElementById('seqEmpty');
    if (sequence.length === 0) {
        empty.style.display = 'block';
        list.innerHTML = '';
        list.appendChild(empty);
        return;
    }
    empty.style.display = 'none';
    list.innerHTML = '';
    sequence.forEach((seg, i) => {
        const div = document.createElement('div');
        div.className = 'seq-item' + (i === activeSegIdx ? ' seq-active' : '');
        div.style.borderColor = i === activeSegIdx ? 'var(--color-primary)' : '';
        div.innerHTML = `
            <span class="seq-label" onclick="selectSegment(${i})" style="cursor:pointer">
                ${i+1}. ${escHtml(seg.label)}
                <span style="color:var(--color-muted);font-size:.72rem"> · ${seg.captions.length} cap</span>
            </span>
            <button onclick="moveSeq(${i},-1)" title="Move up">↑</button>
            <button onclick="moveSeq(${i},1)"  title="Move down">↓</button>
            <button onclick="removeSeq(${i})"  title="Remove">✕</button>
        `;
        list.appendChild(div);
    });
}

function moveSeq(i, dir) {
    const j = i + dir;
    if (j < 0 || j >= sequence.length) return;
    [sequence[i], sequence[j]] = [sequence[j], sequence[i]];
    if (activeSegIdx === i) activeSegIdx = j;
    else if (activeSegIdx === j) activeSegIdx = i;
    renderSequenceList();
    renderCaptionList();
}

function removeSeq(i) {
    const seg = sequence[i];
    const v = getVideo(seg);
    if (v) { v.pause(); v.remove(); }
    sequence.splice(i, 1);
    if (activeSegIdx >= sequence.length) activeSegIdx = sequence.length - 1;
    renderSequenceList();
    renderCaptionList();
    if (sequence.length === 0) hideCanvas();
}

function clearSequence() {
    sequence.forEach(seg => { const v = getVideo(seg); if(v){v.pause();v.remove();} });
    sequence.length = 0;
    activeSegIdx = -1;
    renderSequenceList();
    renderCaptionList();
    hideCanvas();
}

// ── Segment selection (for caption editing) ───────────────────────────────────
function selectSegment(i) {
    activeSegIdx = i;
    renderSequenceList();
    renderCaptionList();
    // Show the form and preview that segment
    document.getElementById('capAddForm').style.display = 'flex';
    document.getElementById('capAddForm').style.flexDirection = 'column';
    document.getElementById('capEmpty').style.display = 'none';
    document.getElementById('capTarget').textContent = 'Editing: ' + sequence[i].label;
    // Draw first frame
    pauseAll();
    const v = getVideo(sequence[i]);
    if (v) {
        v.currentTime = 0;
        v.onseeked = () => { ctx.drawImage(v, 0, 0, W, H); drawCaptions(sequence[i], 0); };
    }
}

// ── Caption list UI ────────────────────────────────────────────────────────────
function renderCaptionList() {
    const list = document.getElementById('capList');
    list.innerHTML = '';
    if (activeSegIdx < 0 || !sequence[activeSegIdx]) return;
    const caps = sequence[activeSegIdx].captions;
    caps.forEach((cap, i) => {
        const div = document.createElement('div');
        div.className = 'cap-item';
        div.style.borderLeftColor = cap.color;
        div.innerHTML = `
            <span class="cap-text">${escHtml(cap.text)}</span>
            <span class="cap-time">${cap.start}s – ${cap.end}s · ${cap.pos}</span>
            <button onclick="removeCaption(${i})" title="Delete">✕</button>
        `;
        list.appendChild(div);
    });
}

function addCaption() {
    if (activeSegIdx < 0) return;
    const text  = document.getElementById('capText').value.trim();
    const start = parseFloat(document.getElementById('capStart').value) || 0;
    const end   = parseFloat(document.getElementById('capEnd').value)   || 3;
    const pos   = document.getElementById('capPos').value;
    const size  = parseInt(document.getElementById('capSize').value, 10);
    const color = document.getElementById('capColor').value;

    if (!text) { document.getElementById('capText').focus(); return; }
    if (end <= start) { alert('End time must be after start time.'); return; }

    sequence[activeSegIdx].captions.push({ text, start, end, pos, size, color });
    document.getElementById('capText').value = '';
    renderCaptionList();
    renderSequenceList(); // update cap count
    // Redraw preview at current time with new caption
    const v = getVideo(sequence[activeSegIdx]);
    if (v) { ctx.drawImage(v, 0, 0, W, H); drawCaptions(sequence[activeSegIdx], v.currentTime); }
}

function removeCaption(i) {
    if (activeSegIdx < 0) return;
    sequence[activeSegIdx].captions.splice(i, 1);
    renderCaptionList();
    renderSequenceList();
}

// ── Canvas rendering ──────────────────────────────────────────────────────────
function showCanvas() {
    document.getElementById('previewEmpty').style.display = 'none';
    canvas.style.display = 'block';
}
function hideCanvas() {
    document.getElementById('previewEmpty').style.display = 'block';
    canvas.style.display = 'none';
}

function drawCaptions(seg, t) {
    seg.captions.forEach(cap => {
        if (t < cap.start || t > cap.end) return;
        const fs   = cap.size || 52;
        const line = Math.ceil(fs * 1.2);
        ctx.font = `bold ${fs}px "Arial", sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'alphabetic';

        // Word-wrap at 80% canvas width
        const maxW  = W * 0.85;
        const words = cap.text.split(' ');
        const lines = [];
        let cur = '';
        words.forEach(w => {
            const test = cur ? cur + ' ' + w : w;
            if (ctx.measureText(test).width > maxW && cur) { lines.push(cur); cur = w; }
            else cur = test;
        });
        if (cur) lines.push(cur);

        const totalH = lines.length * line;
        let   baseY;
        if (cap.pos === 'top')    baseY = fs + 24;
        else if (cap.pos === 'center') baseY = (H - totalH) / 2 + fs;
        else                      baseY = H - 24 - (lines.length - 1) * line; // bottom

        lines.forEach((ln, li) => {
            const y  = baseY + li * line;
            const tw = ctx.measureText(ln).width;
            // Shadow/background for readability
            ctx.fillStyle = 'rgba(0,0,0,0.55)';
            ctx.beginPath();
            ctx.roundRect(W/2 - tw/2 - 12, y - fs - 4, tw + 24, fs + 14, 6);
            ctx.fill();
            // Text
            ctx.fillStyle = cap.color || '#ffffff';
            ctx.fillText(ln, W/2, y);
        });
    });
}

// ── Playback ──────────────────────────────────────────────────────────────────
function togglePlay() {
    if (sequence.length === 0) return;
    if (playing) pausePreview();
    else         startPreview();
}

function startPreview() {
    playing      = true;
    currentSegIdx = Math.max(0, activeSegIdx === -1 ? 0 : activeSegIdx);
    document.getElementById('playBtn').textContent = '⏸ Pause';
    playSegment(currentSegIdx);
}

function pausePreview() {
    playing = false;
    document.getElementById('playBtn').textContent = '▶ Play';
    pauseAll();
    cancelAnimationFrame(rafId);
}

function restartPreview() {
    pausePreview();
    currentSegIdx = 0;
    sequence.forEach(seg => { const v = getVideo(seg); if(v) v.currentTime = 0; });
    updateProgress(0, 0);
    if (sequence.length > 0) { selectSegment(0); }
}

function pauseAll() {
    sequence.forEach(seg => { const v = getVideo(seg); if(v) v.pause(); });
    cancelAnimationFrame(rafId);
}

function playSegment(i) {
    if (i >= sequence.length) {
        // Sequence finished
        playing = false;
        document.getElementById('playBtn').textContent = '▶ Play';
        if (mediaRecorder && mediaRecorder.state === 'recording') mediaRecorder.stop();
        return;
    }
    currentSegIdx = i;
    const seg = sequence[i];
    const v   = getVideo(seg);
    if (!v) { playSegment(i + 1); return; }

    v.currentTime = 0;
    v.onended = () => { cancelAnimationFrame(rafId); playSegment(i + 1); };
    v.play().catch(() => {});
    renderLoop(seg, v);
}

function renderLoop(seg, v) {
    function frame() {
        if (!playing && !mediaRecorder) return;
        ctx.drawImage(v, 0, 0, W, H);
        drawCaptions(seg, v.currentTime);

        // Update progress bar
        const total = getTotalDuration();
        const sofar = getElapsedBefore(currentSegIdx) + v.currentTime;
        updateProgress(sofar, total);
        rafId = requestAnimationFrame(frame);
    }
    rafId = requestAnimationFrame(frame);
}

function getTotalDuration() {
    return sequence.reduce((acc, seg) => {
        const v = getVideo(seg);
        return acc + (v ? (v.duration || 5) : 5);
    }, 0);
}

function getElapsedBefore(idx) {
    let t = 0;
    for (let i = 0; i < idx; i++) {
        const v = getVideo(sequence[i]);
        t += v ? (v.duration || 5) : 5;
    }
    return t;
}

function updateProgress(current, total) {
    const pct = total > 0 ? Math.min(100, (current / total) * 100) : 0;
    document.getElementById('progressFill').style.width = pct + '%';
    document.getElementById('timeDisplay').textContent =
        current.toFixed(1) + ' / ' + (total || 0).toFixed(1) + ' s';
}

function seekTo(e) {
    if (sequence.length === 0) return;
    const track = document.getElementById('progressTrack');
    const rect  = track.getBoundingClientRect();
    const pct   = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
    const total = getTotalDuration();
    const target = pct * total;

    let acc = 0, idx = 0;
    for (let i = 0; i < sequence.length; i++) {
        const v = getVideo(sequence[i]);
        const d = v ? (v.duration || 5) : 5;
        if (acc + d >= target) { idx = i; break; }
        acc += d;
    }
    const v = getVideo(sequence[idx]);
    if (v) v.currentTime = target - acc;
    currentSegIdx = idx;
}

// ── Export (Canvas → MediaRecorder → WebM) ───────────────────────────────────
function startExport() {
    if (sequence.length === 0) { alert('Add at least one video to the sequence first.'); return; }

    const mimeTypes = [
        'video/webm;codecs=vp9',
        'video/webm;codecs=vp8',
        'video/webm',
    ];
    let mimeType = mimeTypes.find(m => MediaRecorder.isTypeSupported(m)) || 'video/webm';

    const stream = canvas.captureStream(30);
    recChunks    = [];
    mediaRecorder = new MediaRecorder(stream, { mimeType, videoBitsPerSecond: 5_000_000 });

    mediaRecorder.ondataavailable = e => { if (e.data.size > 0) recChunks.push(e.data); };
    mediaRecorder.onstop = () => {
        const blob = new Blob(recChunks, { type: mimeType });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = 'video_with_captions_' + Date.now() + '.webm';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
        setRecordingUI(false);
    };

    mediaRecorder.start(1000); // collect every 1s
    setRecordingUI(true);

    // Auto-play sequence for recording
    playing       = true;
    currentSegIdx = 0;
    document.getElementById('playBtn').textContent = '⏸ Pause';
    playSegment(0);
}

function stopExport() {
    pausePreview();
    if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
    else setRecordingUI(false);
}

function setRecordingUI(active) {
    document.getElementById('exportBtn').style.display = active ? 'none' : 'inline-block';
    document.getElementById('stopBtn').style.display   = active ? 'inline-block' : 'none';
    document.getElementById('recDot').style.display    = active ? 'inline-block' : 'none';
    document.getElementById('recStatus').textContent   = active
        ? 'Recording… play through your video then click Stop'
        : 'Ready to export';
    if (!active) mediaRecorder = null;
}

// ── SRT export ────────────────────────────────────────────────────────────────
function downloadSRT() {
    let srt = '';
    let cueIdx = 1;
    let timeOffset = 0;

    sequence.forEach(seg => {
        const v = getVideo(seg);
        const dur = v ? (v.duration || 5) : 5;
        seg.captions.forEach(cap => {
            srt += cueIdx++ + '\n';
            srt += toSRTTime(timeOffset + cap.start) + ' --> ' + toSRTTime(timeOffset + cap.end) + '\n';
            srt += cap.text + '\n\n';
        });
        timeOffset += dur;
    });

    if (!srt.trim()) { alert('No captions to export.'); return; }
    const blob = new Blob([srt], { type: 'text/plain' });
    const a    = document.createElement('a');
    a.href     = URL.createObjectURL(blob);
    a.download = 'captions.srt';
    a.click();
}

function toSRTTime(s) {
    const h   = Math.floor(s / 3600);
    const m   = Math.floor((s % 3600) / 60);
    const sec = Math.floor(s % 60);
    const ms  = Math.round((s % 1) * 1000);
    return [h, m, sec].map(n => String(n).padStart(2,'0')).join(':') + ',' + String(ms).padStart(3,'0');
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Init ──────────────────────────────────────────────────────────────────────
renderSequenceList();
renderCaptionList();
</script>
</body>
</html>
