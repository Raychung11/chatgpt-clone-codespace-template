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
require_once __DIR__ . '/../inc/wallet.php';
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
        /* Caption overlay on player */
        .player-wrap {
            position: relative;
            width: 100%; height: 100%;
            display: flex; align-items: center; justify-content: center;
        }
        #mainVideo {
            max-width: 100%; max-height: 100%;
            border-radius: 6px;
            box-shadow: 0 4px 32px rgba(0,0,0,.6);
            display: block;
            background: #000;
        }
        #captionOverlay {
            position: absolute;
            inset: 0;
            pointer-events: none;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: center;
            padding-bottom: 28px;
        }
        #captionOverlay.pos-top    { justify-content: flex-start; padding-top: 24px; padding-bottom: 0; }
        #captionOverlay.pos-center { justify-content: center; padding-bottom: 0; }
        .cap-overlay-text {
            background: rgba(0,0,0,.55);
            color: #fff;
            padding: 6px 18px;
            border-radius: 6px;
            font-weight: 700;
            line-height: 1.4;
            text-align: center;
            max-width: 85%;
            margin: 3px 0;
        }
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

        <!-- Video preview area -->
        <div class="preview-area" id="previewArea">
            <div class="preview-empty" id="previewEmpty">
                <div style="font-size:3rem;margin-bottom:8px">🎬</div>
                <div style="font-size:1rem;font-weight:600">Click a video to add it to the sequence</div>
                <div style="font-size:.85rem;margin-top:4px">Then add captions and export</div>
            </div>
            <div class="player-wrap" id="playerWrap" style="display:none">
                <video id="mainVideo" playsinline></video>
                <div id="captionOverlay"></div>
            </div>
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

<script>
// ── State ─────────────────────────────────────────────────────────────────────
const sequence     = [];  // [{id, url, label, captions:[{text,start,end,pos,size,color}]}]
let   activeSegIdx = -1;
let   playing      = false;
let   currentSegIdx = 0;
let   rafId        = null;

const mainVideo      = document.getElementById('mainVideo');
const captionOverlay = document.getElementById('captionOverlay');

// ── Sidebar: add video to sequence ───────────────────────────────────────────
function addToSequence(card) {
    const seg = {
        id:       card.dataset.id + '_' + Date.now(),
        vidId:    card.dataset.id,
        url:      card.dataset.url,
        label:    card.dataset.label,
        captions: [],
    };
    sequence.push(seg);
    renderSequenceList();
    selectSegment(sequence.length - 1);
    showPlayer();
}

// ── Sequence list UI ─────────────────────────────────────────────────────────
function renderSequenceList() {
    const list  = document.getElementById('sequenceList');
    const empty = document.getElementById('seqEmpty');
    if (!sequence.length) {
        empty.style.display = 'block';
        list.innerHTML = '';
        list.appendChild(empty);
        return;
    }
    empty.style.display = 'none';
    list.innerHTML = '';
    sequence.forEach((seg, i) => {
        const div = document.createElement('div');
        div.className = 'seq-item';
        div.style.borderColor = i === activeSegIdx ? 'var(--color-primary)' : '';
        div.innerHTML = `
            <span class="seq-label" onclick="selectSegment(${i})" style="cursor:pointer">
                ${i+1}. ${escHtml(seg.label)}
                <span style="color:var(--color-muted);font-size:.72rem"> · ${seg.captions.length} cap</span>
            </span>
            <button onclick="moveSeq(${i},-1)" title="Move up">↑</button>
            <button onclick="moveSeq(${i},1)"  title="Move down">↓</button>
            <button onclick="removeSeq(${i})"  title="Remove">✕</button>`;
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
}

function removeSeq(i) {
    sequence.splice(i, 1);
    if (activeSegIdx >= sequence.length) activeSegIdx = sequence.length - 1;
    renderSequenceList();
    renderCaptionList();
    if (!sequence.length) { hidePlayer(); return; }
    if (activeSegIdx >= 0) loadSegment(activeSegIdx);
}

function clearSequence() {
    mainVideo.pause();
    mainVideo.src = '';
    sequence.length = 0;
    activeSegIdx = -1;
    renderSequenceList();
    renderCaptionList();
    hidePlayer();
}

// ── Segment selection ────────────────────────────────────────────────────────
function selectSegment(i) {
    activeSegIdx = i;
    renderSequenceList();
    renderCaptionList();
    document.getElementById('capAddForm').style.cssText = 'display:flex;flex-direction:column';
    document.getElementById('capEmpty').style.display = 'none';
    document.getElementById('capTarget').textContent = 'Editing: ' + sequence[i].label;
    loadSegment(i);
}

function loadSegment(i) {
    // Simply set src — no CORS needed for a normal <video> element
    mainVideo.src = sequence[i].url;
    mainVideo.load();
    updateCaptionOverlay(sequence[i], 0);
}

// ── Caption overlay (CSS divs over the video, no canvas/CORS needed) ─────────
function updateCaptionOverlay(seg, t) {
    captionOverlay.innerHTML = '';
    if (!seg) return;
    seg.captions.forEach(cap => {
        if (t < cap.start || t > cap.end) return;
        const el = document.createElement('div');
        el.className = 'cap-overlay-text';
        el.textContent = cap.text;
        el.style.color     = cap.color || '#fff';
        el.style.fontSize  = (cap.size || 52) * 0.04 + 'vw'; // scale with viewport
        captionOverlay.appendChild(el);
    });
    // Adjust position class
    const pos = seg.captions.find(c => t >= c.start && t <= c.end)?.pos || 'bottom';
    captionOverlay.className = pos === 'top' ? 'pos-top' : pos === 'center' ? 'pos-center' : '';
}

// RAF loop: keep caption overlay in sync with video time
function startCaptionLoop() {
    cancelAnimationFrame(rafId);
    function tick() {
        const seg = sequence[currentSegIdx];
        if (seg && !mainVideo.paused) {
            updateCaptionOverlay(seg, mainVideo.currentTime);
            const total = sequence.reduce((a, _) => a + 5, 0); // rough total
            const sofar = currentSegIdx * 5 + mainVideo.currentTime;
            updateProgress(mainVideo.currentTime, mainVideo.duration || 1);
        }
        rafId = requestAnimationFrame(tick);
    }
    rafId = requestAnimationFrame(tick);
}

// ── Caption list UI ──────────────────────────────────────────────────────────
function renderCaptionList() {
    const list = document.getElementById('capList');
    list.innerHTML = '';
    if (activeSegIdx < 0 || !sequence[activeSegIdx]) return;
    sequence[activeSegIdx].captions.forEach((cap, i) => {
        const div = document.createElement('div');
        div.className = 'cap-item';
        div.style.borderLeftColor = cap.color;
        div.innerHTML = `
            <span class="cap-text">${escHtml(cap.text)}</span>
            <span class="cap-time">${cap.start}s–${cap.end}s · ${cap.pos}</span>
            <button onclick="removeCaption(${i})" title="Delete">✕</button>`;
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
    renderSequenceList();
    // Jump video to the start of the caption so user sees it instantly
    mainVideo.currentTime = start;
    updateCaptionOverlay(sequence[activeSegIdx], start);
}

function removeCaption(i) {
    if (activeSegIdx < 0) return;
    sequence[activeSegIdx].captions.splice(i, 1);
    renderCaptionList();
    renderSequenceList();
}

// ── Player show/hide ─────────────────────────────────────────────────────────
function showPlayer() {
    document.getElementById('previewEmpty').style.display  = 'none';
    document.getElementById('playerWrap').style.display    = 'flex';
}
function hidePlayer() {
    document.getElementById('previewEmpty').style.display  = 'block';
    document.getElementById('playerWrap').style.display    = 'none';
    captionOverlay.innerHTML = '';
}

// ── Playback ─────────────────────────────────────────────────────────────────
function togglePlay() {
    if (!sequence.length) return;
    if (mainVideo.paused) {
        mainVideo.play().catch(() => {});
        document.getElementById('playBtn').textContent = '⏸ Pause';
        startCaptionLoop();
    } else {
        mainVideo.pause();
        document.getElementById('playBtn').textContent = '▶ Play';
    }
}

function restartPreview() {
    mainVideo.pause();
    currentSegIdx = 0;
    document.getElementById('playBtn').textContent = '▶ Play';
    if (sequence.length) selectSegment(0);
}

function updateProgress(current, total) {
    const pct = total > 0 ? Math.min(100, (current / total) * 100) : 0;
    document.getElementById('progressFill').style.width = pct + '%';
    document.getElementById('timeDisplay').textContent  =
        (current || 0).toFixed(1) + ' / ' + (total || 0).toFixed(1) + ' s';
}

function seekTo(e) {
    if (!mainVideo.duration) return;
    const track = document.getElementById('progressTrack');
    const rect  = track.getBoundingClientRect();
    const pct   = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
    mainVideo.currentTime = pct * mainVideo.duration;
}

// Wire up native video events
mainVideo.addEventListener('timeupdate', () => {
    const seg = sequence[currentSegIdx];
    if (seg) updateCaptionOverlay(seg, mainVideo.currentTime);
    updateProgress(mainVideo.currentTime, mainVideo.duration);
});

mainVideo.addEventListener('ended', () => {
    // Advance to next segment in sequence
    currentSegIdx++;
    if (currentSegIdx < sequence.length) {
        loadSegment(currentSegIdx);
        mainVideo.play().catch(() => {});
    } else {
        currentSegIdx = 0;
        document.getElementById('playBtn').textContent = '▶ Play';
    }
});

// ── Export: SRT (always works) ───────────────────────────────────────────────
function downloadSRT() {
    let srt = '', cueIdx = 1, offset = 0;
    sequence.forEach(seg => {
        const dur = mainVideo.duration || 5; // best approximation
        seg.captions.forEach(cap => {
            srt += cueIdx++ + '\n'
                +  toSRTTime(offset + cap.start) + ' --> ' + toSRTTime(offset + cap.end) + '\n'
                +  cap.text + '\n\n';
        });
        offset += dur;
    });
    if (!srt.trim()) { alert('No captions added yet.'); return; }
    const a = Object.assign(document.createElement('a'), {
        href: URL.createObjectURL(new Blob([srt], { type: 'text/plain' })),
        download: 'captions.srt',
    });
    a.click();
}

// ── Export: record screen via captureStream (best-effort, no CORS needed) ────
function startExport() {
    if (!sequence.length) { alert('Add at least one video first.'); return; }

    // Use the visible <video> element's stream — no canvas, no CORS issues
    let stream;
    try {
        stream = mainVideo.captureStream ? mainVideo.captureStream(30)
               : mainVideo.mozCaptureStream ? mainVideo.mozCaptureStream(30)
               : null;
    } catch(e) { stream = null; }

    if (!stream) {
        alert('Your browser does not support video capture. Download the SRT file and use it with the original video in VLC or any editor.');
        return;
    }

    const mimeType = ['video/webm;codecs=vp9','video/webm;codecs=vp8','video/webm']
        .find(m => MediaRecorder.isTypeSupported(m)) || 'video/webm';

    const chunks = [];
    const mr = new MediaRecorder(stream, { mimeType, videoBitsPerSecond: 5_000_000 });
    mr.ondataavailable = e => { if (e.data.size > 0) chunks.push(e.data); };
    mr.onstop = () => {
        const a = Object.assign(document.createElement('a'), {
            href: URL.createObjectURL(new Blob(chunks, { type: mimeType })),
            download: 'video_with_captions_' + Date.now() + '.webm',
        });
        document.body.appendChild(a); a.click(); a.remove();
        setRecordingUI(false);
    };

    mr.start(500);
    setRecordingUI(true, mr);

    // Restart and play from beginning so the whole video is captured
    currentSegIdx = 0;
    loadSegment(0);
    mainVideo.play().catch(() => {});
    document.getElementById('playBtn').textContent = '⏸ Pause';
    startCaptionLoop();

    // Auto-stop when last segment ends (listen once)
    mainVideo.addEventListener('ended', function autoStop() {
        if (currentSegIdx >= sequence.length - 1) {
            mr.stop();
            mainVideo.removeEventListener('ended', autoStop);
        }
    });
}

let _activeRecorder = null;
function stopExport() {
    mainVideo.pause();
    document.getElementById('playBtn').textContent = '▶ Play';
    if (_activeRecorder && _activeRecorder.state !== 'inactive') _activeRecorder.stop();
    else setRecordingUI(false);
}

function setRecordingUI(active, mr) {
    if (mr) _activeRecorder = mr;
    document.getElementById('exportBtn').style.display = active ? 'none' : 'inline-block';
    document.getElementById('stopBtn').style.display   = active ? 'inline-block' : 'none';
    document.getElementById('recDot').style.display    = active ? 'inline-block' : 'none';
    document.getElementById('recStatus').textContent   = active
        ? 'Recording… video will download when done'
        : 'Ready to export';
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function toSRTTime(s) {
    const h  = Math.floor(s / 3600);
    const m  = Math.floor((s % 3600) / 60);
    const sc = Math.floor(s % 60);
    const ms = Math.round((s % 1) * 1000);
    return [h,m,sc].map(n=>String(n).padStart(2,'0')).join(':') + ',' + String(ms).padStart(3,'0');
}
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Init ─────────────────────────────────────────────────────────────────────
renderSequenceList();
renderCaptionList();
</script>
</body>
</html>
