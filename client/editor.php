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
require_once __DIR__ . '/../inc/byteplus.php';
require_once __DIR__ . '/../inc/layout.php';

boot_session();
$user = require_auth('/public/login.php');
$uid  = (int)$user['id'];
$pdo  = db();

// Load user's completed videos and refresh any expired signed URLs
$stmt = $pdo->prepare(
    'SELECT vj.id, vj.prompt, vj.resolution, vj.duration, vj.created_at,
            vo.cdn_url, vo.thumbnail
     FROM video_jobs vj
     JOIN video_outputs vo ON vo.job_id = vj.id
     WHERE vj.user_id = ? AND vj.status = "completed"
       AND vo.cdn_url IS NOT NULL AND vo.cdn_url != ""
     ORDER BY vj.created_at DESC
     LIMIT 50'
);
$stmt->execute([$uid]);
$videos = $stmt->fetchAll();

// Auto-refresh expired BytePlus signed URLs (they expire after 24h)
foreach ($videos as &$v) {
    if (byteplus_url_is_expired($v['cdn_url'])) {
        $fresh = byteplus_ensure_fresh_url((int)$v['id'], $pdo);
        if ($fresh) $v['cdn_url'] = $fresh;
    }
}
unset($v);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Editor — <?= e(setting('site_name','Motions')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
    <style>
        /* Expiry badge */
        .expiry-badge { display:inline-flex;align-items:center;gap:3px;font-size:.65rem;font-weight:600;padding:1px 5px;border-radius:8px;white-space:nowrap; }
        .expiry-ok      { background:rgba(34,197,94,.12);  color:#22c55e; }
        .expiry-warn    { background:rgba(234,179,8,.15);  color:#eab308; }
        .expiry-danger  { background:rgba(239,68,68,.15);  color:#ef4444; }
        .expiry-expired { background:rgba(239,68,68,.2);   color:#ef4444; }
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
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #0a0a0c;
            min-height: 0;
            overflow: hidden;
            position: relative;
        }
        .preview-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
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
        /* Main video — sits directly inside .preview-area */
        #mainVideo {
            display: none;
            max-width: 100%;
            max-height: calc(100vh - 340px);
            min-height: 200px;
            width: auto;
            height: auto;
            border-radius: 6px;
            box-shadow: 0 4px 32px rgba(0,0,0,.6);
            background: #000;
            z-index: 1;
            position: relative;
        }
        /* Caption overlay — absolutely positioned over the whole preview-area */
        #captionOverlay {
            position: absolute;
            inset: 0;
            pointer-events: none;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: center;
            padding-bottom: 3%;
            overflow: hidden;
            z-index: 2;
        }
        #captionOverlay.pos-top    { justify-content: flex-start; padding-top: 3%; padding-bottom: 0; }
        #captionOverlay.pos-center { justify-content: center; padding-bottom: 0; }
        .cap-overlay-text {
            background: rgba(0,0,0,.62);
            color: #fff;
            padding: 5px 16px;
            border-radius: 5px;
            font-weight: 700;
            line-height: 1.4;
            text-align: center;
            max-width: 90%;
            margin: 2px 0;
            font-size: clamp(14px, 2vw, 28px);
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
        <div style="padding:8px 12px;background:rgba(234,179,8,.08);border-bottom:1px solid rgba(234,179,8,.2);font-size:.72rem;color:#eab308;display:flex;align-items:center;gap:6px">
            <span>⚠️</span>
            <span>Videos expire in <strong>24h</strong> — <a href="<?= BASE_URL ?>/client/history.php" style="color:#eab308;text-decoration:underline">download from History</a></span>
        </div>

        <!-- Upload local video -->
        <div style="padding:10px;border-bottom:1px solid var(--color-border)">
            <div style="font-size:.72rem;color:var(--color-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">⬆ Upload from device</div>
            <label id="uploadZone" style="
                display:flex;align-items:center;justify-content:center;gap:8px;
                border:2px dashed var(--color-border);border-radius:6px;
                padding:10px;cursor:pointer;transition:border-color .2s;
                font-size:.8rem;color:var(--color-muted)">
                <span style="font-size:1.2rem">🎬</span>
                <span id="uploadLabel">Click or drag a video here</span>
                <input type="file" id="uploadInput" accept="video/*" style="display:none" onchange="handleUploadFile(this.files[0])">
            </label>
        </div>

        <!-- Paste any URL -->
        <div style="padding:10px;border-bottom:1px solid var(--color-border)">
            <div style="font-size:.72rem;color:var(--color-muted);margin-bottom:4px;text-transform:uppercase;letter-spacing:.05em">Or paste a video URL</div>
            <div style="display:flex;gap:6px">
                <input type="url" id="pasteUrl"
                       placeholder="https://…mp4"
                       style="flex:1;background:var(--color-surface);border:1px solid var(--color-border);border-radius:4px;color:var(--color-text);padding:5px 8px;font-size:.78rem;min-width:0">
                <button onclick="addUrlToSequence()"
                        style="background:var(--color-primary);border:none;color:#fff;border-radius:4px;padding:5px 10px;cursor:pointer;font-size:.8rem;white-space:nowrap">
                    + Add
                </button>
            </div>
        </div>

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
                         data-prompt="<?= e($v['prompt']) ?>"
                         onclick="addToSequence(this)">
                        <div class="vid-thumb-preview">
                            <video src="<?= e($v['cdn_url']) ?>#t=0.5"
                                   preload="metadata" muted playsinline
                                   style="pointer-events:none"></video>
                        </div>
                        <div class="vid-thumb-info" title="<?= e($v['prompt']) ?>">
                            <?= e(mb_strimwidth($v['prompt'], 0, 45, '…')) ?>
                        </div>
                        <?php $timeLeft = byteplus_url_time_remaining($v['cdn_url'] ?? ''); ?>
                        <?php if ($timeLeft): ?>
                            <?php
                                $expiresAt = byteplus_url_expires_at($v['cdn_url']);
                                $secsLeft  = $expiresAt ? ($expiresAt->getTimestamp() - time()) : PHP_INT_MAX;
                                $exClass   = $secsLeft < 7200 ? 'expiry-danger' : ($secsLeft < 43200 ? 'expiry-warn' : 'expiry-ok');
                            ?>
                            <div style="font-size:.65rem;margin-top:3px;opacity:.8"
                                 class="expiry-badge <?= $exClass ?>"
                                 style="font-size:.65rem;padding:1px 5px">
                                ⏱ <?= e($timeLeft) ?>
                            </div>
                        <?php endif; ?>
                        <!-- Card actions: stop propagation so clicks don't add to sequence -->
                        <div style="display:flex;gap:4px;margin-top:6px" onclick="event.stopPropagation()">
                            <a href="<?= BASE_URL ?>/client/generate.php?prompt=<?= urlencode($v['prompt']) ?>"
                               style="flex:1;text-align:center;font-size:.7rem;padding:3px 0;background:var(--color-surface2);
                                      border:1px solid var(--color-border);border-radius:4px;color:var(--color-muted);
                                      text-decoration:none;cursor:pointer"
                               title="Generate a new video with this prompt">
                                🔄
                            </a>
                            <button onclick="deleteSidebarJob(<?= (int)$v['id'] ?>, this)"
                                    style="flex:1;font-size:.7rem;padding:3px 0;background:transparent;
                                           border:1px solid var(--color-border);border-radius:4px;
                                           color:var(--color-muted);cursor:pointer"
                                    title="Delete this video">
                                🗑
                            </button>
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
            <video id="mainVideo" controls playsinline preload="metadata"></video>
            <div id="captionOverlay"></div>
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
                <div class="seq-empty" id="seqEmpty">Add videos from the left panel</div>
                <div class="sequence-list" id="sequenceList"></div>
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

        <!-- Merge debug log -->
        <div id="mergeDebugWrap" style="background:#0d0d12;border-top:1px solid #ff6b35;padding:8px 14px;font-family:monospace;font-size:.72rem;display:none">
            <div style="color:#ff6b35;font-weight:700;margin-bottom:4px">🔧 Merge Debug Log <button onclick="document.getElementById('mergeLog').innerHTML=''" style="background:#333;border:none;color:#ccc;padding:1px 8px;border-radius:3px;cursor:pointer;font-size:.7rem;margin-left:8px">Clear</button></div>
            <div id="mergeLog" style="max-height:120px;overflow-y:auto;line-height:1.7"></div>
        </div>

        <!-- Export bar -->
        <div class="export-bar">
            <span id="recDot"></span>
            <span id="recStatus">Ready</span>
            <button class="btn btn-primary" onclick="startMerge()" id="mergeBtn">
                🔗 Merge &amp; Download
            </button>
            <button class="btn btn-ghost" onclick="downloadSRT()" id="srtBtn">
                📄 Download SRT
            </button>
            <button class="btn btn-ghost" onclick="stopExport()" id="stopBtn" style="display:none">
                ⏹ Stop
            </button>
            <button class="btn btn-ghost" onclick="toggleMergeDebug()" id="dbgToggleBtn" style="font-size:.75rem;padding:5px 10px">
                🔧 Debug
            </button>
            <div style="flex:1"></div>
            <div style="font-size:.75rem;color:var(--color-muted)">
                Export produces a WebM file (plays in Chrome, Edge, Firefox)
            </div>
        </div>

    </div><!-- /editor-main -->
</div><!-- /editor-wrap -->

<script>
const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
const BASE_URL   = <?= json_encode(BASE_URL) ?>;

// ── Delete sidebar video ──────────────────────────────────────────────────────
async function deleteSidebarJob(jobId, btn) {
    if (!confirm('Delete this video? This cannot be undone.')) return;

    btn.disabled = true;
    btn.textContent = '…';

    const fd = new FormData();
    fd.append('<?= CSRF_TOKEN_NAME ?>', CSRF_TOKEN);
    fd.append('job_id', jobId);

    try {
        const res  = await fetch(BASE_URL + '/client/delete_job.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.ok) {
            // Remove from sequence if it was added
            for (let i = sequence.length - 1; i >= 0; i--) {
                if (sequence[i].vidId == jobId) sequence.splice(i, 1);
            }
            renderSequenceList();
            if (!sequence.length) hidePlayer();
            // Remove sidebar card
            const card = document.getElementById('card_' + jobId);
            if (card) { card.style.opacity = '0'; card.style.transition = 'opacity .3s'; setTimeout(() => card.remove(), 300); }
        } else {
            btn.disabled = false; btn.textContent = '🗑';
            alert(data.error || 'Delete failed.');
        }
    } catch(e) {
        btn.disabled = false; btn.textContent = '🗑';
        alert('Network error. Please try again.');
    }
}

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

// ── Upload local video file ───────────────────────────────────────────────────
function handleUploadFile(file) {
    if (!file) return;
    if (!file.type.startsWith('video/')) {
        alert('Please select a video file (MP4, MOV, WebM, etc.)');
        return;
    }
    const blobUrl = URL.createObjectURL(file);
    const label   = file.name.replace(/\.[^.]+$/, ''); // filename without extension
    const seg = {
        id:       'upload_' + Date.now(),
        vidId:    'upload_' + Date.now(),
        url:      blobUrl,
        label:    label,
        captions: [],
        _isUpload: true,
    };
    sequence.push(seg);
    renderSequenceList();
    selectSegment(sequence.length - 1);
    showPlayer();

    // Show filename in the upload zone label
    const lbl = document.getElementById('uploadLabel');
    if (lbl) lbl.textContent = '✓ ' + file.name + ' (' + (file.size / 1048576).toFixed(1) + ' MB)';
    // Reset input so same file can be re-selected
    document.getElementById('uploadInput').value = '';
}

// Drag-and-drop on the upload zone
document.addEventListener('DOMContentLoaded', () => {
    const zone = document.getElementById('uploadZone');
    if (!zone) return;
    zone.addEventListener('dragover', e => {
        e.preventDefault();
        zone.style.borderColor = 'var(--color-primary)';
        zone.style.background  = 'rgba(99,102,241,.08)';
    });
    zone.addEventListener('dragleave', () => {
        zone.style.borderColor = '';
        zone.style.background  = '';
    });
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.style.borderColor = '';
        zone.style.background  = '';
        const file = e.dataTransfer.files[0];
        if (file) handleUploadFile(file);
    });
});

// Add any video URL directly (for testing or external videos)
function addUrlToSequence() {
    const input = document.getElementById('pasteUrl');
    const url   = input.value.trim();
    if (!url) { input.focus(); return; }
    if (!url.startsWith('http')) { alert('Please enter a full URL starting with http(s)://'); return; }
    const seg = {
        id:       'url_' + Date.now(),
        vidId:    'url_' + Date.now(),
        url:      url,
        label:    url.split('/').pop().split('?')[0] || 'Video URL',
        captions: [],
    };
    sequence.push(seg);
    input.value = '';
    renderSequenceList();
    selectSegment(sequence.length - 1);
    showPlayer();
}

// ── Sequence list UI ─────────────────────────────────────────────────────────
function renderSequenceList() {
    const list  = document.getElementById('sequenceList');
    const empty = document.getElementById('seqEmpty');
    // seqEmpty is a sibling of sequenceList (not inside it), so list.innerHTML=''
    // never destroys it and getElementById always returns it safely.
    list.innerHTML = '';
    if (!sequence.length) {
        if (empty) empty.style.display = 'block';
        return;
    }
    if (empty) empty.style.display = 'none';
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
    currentSegIdx = i;
    mainVideo.src = sequence[i].url;
    mainVideo.load();
    // Show first frame once enough data is available
    mainVideo.addEventListener('loadedmetadata', function onMeta() {
        mainVideo.currentTime = 0.1;
        mainVideo.removeEventListener('loadedmetadata', onMeta);
    }, { once: true });
    updateCaptionOverlay(sequence[i], 0);
    updateProgress(0, 0);
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
    document.getElementById('previewEmpty').style.display = 'none';
    mainVideo.style.display = 'block';
}
function hidePlayer() {
    document.getElementById('previewEmpty').style.display = 'flex';
    mainVideo.style.display = 'none';
    captionOverlay.innerHTML = '';
}

// Keep custom play button in sync with native controls
mainVideo.addEventListener('play',  () => { document.getElementById('playBtn').textContent = '⏸ Pause'; startCaptionLoop(); });
mainVideo.addEventListener('pause', () => { document.getElementById('playBtn').textContent = '▶ Play'; });

// ── Playback ─────────────────────────────────────────────────────────────────
function togglePlay() {
    if (!sequence.length) return;
    if (mainVideo.paused) {
        mainVideo.play().catch(err => console.warn('play() blocked:', err));
    } else {
        mainVideo.pause();
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

// ── Merge debug log ───────────────────────────────────────────────────────────
function mlog(msg, color) {
    const el = document.getElementById('mergeLog');
    if (!el) return;
    const ts = new Date().toTimeString().slice(0,8);
    el.innerHTML += `<div style="color:${color||'#e2e8f0'}">[${ts}] ${msg}</div>`;
    el.scrollTop = el.scrollHeight;
}
function toggleMergeDebug() {
    const w = document.getElementById('mergeDebugWrap');
    w.style.display = w.style.display === 'none' ? 'block' : 'none';
}

// ── Merge & Download ──────────────────────────────────────────────────────────
let _activeRecorder = null;

function startMerge() {
    if (!sequence.length) { alert('Add at least one video to the sequence first.'); return; }

    // Show debug panel automatically
    document.getElementById('mergeDebugWrap').style.display = 'block';
    document.getElementById('mergeLog').innerHTML = '';
    mlog(`startMerge() — ${sequence.length} clip(s) in sequence`, '#facc15');

    setRecordingUI(true, null, 'Preparing…');

    // ── Route: server-side FFmpeg (MP4) vs browser canvas (WebM) ─────────────
    // Server merge is used when every clip is a generated BytePlus job.
    // Browser merge is the fallback for locally-uploaded clips.
    const allJobs = sequence.every(seg => {
        const n = parseInt(seg.vidId, 10);
        return !isNaN(n) && n > 0 && !seg.url.startsWith('blob:');
    });

    if (allJobs) {
        _serverMerge();
    } else {
        mlog('Sequence has uploaded clips — using browser merge (WebM)…', '#f59e0b');
        _browserMerge();
    }
}

// ── Server-side merge via FFmpeg → MP4 ───────────────────────────────────────
async function _serverMerge() {
    const jobIds = sequence.map(seg => parseInt(seg.vidId, 10));
    mlog(`Server merge: jobs [${jobIds.join(', ')}]`, '#facc15');

    try {
        setRecordingUI(true, null, 'Merging on server…');
        mlog('Sending to server — FFmpeg will concat + produce MP4…', '#94a3b8');

        const resp = await fetch('<?= BASE_URL ?>/client/merge_jobs.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ job_ids: jobIds }),
        });

        if (!resp.ok) {
            let errMsg = `HTTP ${resp.status}`;
            try { const j = await resp.json(); errMsg = j.error || errMsg; } catch(_) {}
            throw new Error(errMsg);
        }

        mlog('Server merge complete — downloading MP4…', '#a8e063');
        const blob = await resp.blob();
        const sizeMB = (blob.size / 1048576).toFixed(2);
        mlog(`File size: ${sizeMB} MB`, '#a8e063');

        const a = Object.assign(document.createElement('a'), {
            href: URL.createObjectURL(blob),
            download: 'merged_' + Date.now() + '.mp4',
        });
        document.body.appendChild(a); a.click(); a.remove();
        mlog('✅ MP4 downloaded', '#a8e063');

    } catch(e) {
        mlog(`Server merge failed: ${e.message}`, '#ef4444');
        alert('Merge failed: ' + e.message);
    } finally {
        setRecordingUI(false);
    }
}

// ── Browser-side merge via canvas + MediaRecorder → WebM ─────────────────────
// Used when the sequence contains locally-uploaded clips (blob: URLs).
function _browserMerge() {
    async function toSameOriginUrl(seg) {
        if (seg.url.startsWith('blob:')) {
            mlog(`  clip "${seg.label}": local upload ✓`, '#a8e063');
            return seg.url;
        }
        const numId = parseInt(seg.vidId, 10);
        if (!isNaN(numId) && numId > 0) {
            mlog(`  clip "${seg.label}": fetching via proxy (job #${numId})…`, '#94a3b8');
            try {
                const resp = await fetch(`<?= BASE_URL ?>/client/video_proxy.php?job_id=${numId}`);
                if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                const blob = await resp.blob();
                mlog(`  proxy OK — ${(blob.size/1048576).toFixed(1)} MB`, '#a8e063');
                return URL.createObjectURL(blob);
            } catch(e) {
                mlog(`  proxy FAILED: ${e.message}`, '#ef4444');
                return seg.url;
            }
        }
        mlog(`  clip "${seg.label}": external URL — trying CORS fetch…`, '#94a3b8');
        try {
            const resp = await fetch(seg.url, { mode: 'cors' });
            if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
            const blob = await resp.blob();
            mlog(`  CORS OK — ${(blob.size/1048576).toFixed(1)} MB`, '#a8e063');
            return URL.createObjectURL(blob);
        } catch(e) {
            mlog(`  CORS failed: ${e.message}`, '#f59e0b');
            return seg.url;
        }
    }

    Promise.all(sequence.map(seg => toSameOriginUrl(seg))).then(resolvedUrls => {
        mlog('All URLs resolved — starting canvas recorder', '#facc15');
        _doMerge(resolvedUrls);
    }).catch(err => {
        mlog(`URL resolution error: ${err}`, '#ef4444');
        setRecordingUI(false);
    });
}

function _doMerge(resolvedUrls) {
    // Off-screen video element — never tainted by cross-origin URLs
    const mergeVid = document.createElement('video');
    mergeVid.setAttribute('playsinline', '');
    mergeVid.crossOrigin = 'anonymous'; // needed for AudioContext
    mergeVid.style.cssText = 'position:fixed;left:-9999px;top:0;width:1280px;height:720px;';
    document.body.appendChild(mergeVid);

    // Canvas to draw frames into — its stream tracks never change, so
    // MediaRecorder never gets InvalidModificationError when video src changes.
    const canvas = document.createElement('canvas');
    canvas.width  = 1280;
    canvas.height = 720;
    const ctx = canvas.getContext('2d');

    let animId    = null;
    let audioCtx  = null;
    let audioSrc  = null;

    const cleanup = () => {
        if (animId) { cancelAnimationFrame(animId); animId = null; }
        mergeVid.pause();
        mergeVid.src = '';
        mergeVid.remove();
        if (audioSrc) { try { audioSrc.disconnect(); } catch(_){} }
        if (audioCtx) { try { audioCtx.close();      } catch(_){} }
        resolvedUrls.forEach(u => { try { URL.revokeObjectURL(u); } catch(_) {} });
    };

    function startDrawing() {
        function frame() {
            if (mergeVid.readyState >= 2 && !mergeVid.paused && !mergeVid.ended) {
                ctx.drawImage(mergeVid, 0, 0, canvas.width, canvas.height);
            }
            animId = requestAnimationFrame(frame);
        }
        frame();
    }

    // Play a clip; resolves when ended fires
    function playClip(url) {
        return new Promise((resolve, reject) => {
            mergeVid.src = url;
            mergeVid.load();
            mergeVid.addEventListener('error', () =>
                reject(new Error(`code=${mergeVid.error?.code}: ${mergeVid.error?.message}`))
            , { once: true });
            mergeVid.addEventListener('canplay', () => {
                mergeVid.currentTime = 0;
                mergeVid.play()
                    .then(() => mlog('  playing ▶', '#a8e063'))
                    .catch(reject);
            }, { once: true });
            mergeVid.addEventListener('ended', resolve, { once: true });
        });
    }

    function waitMeta() {
        return new Promise((resolve, reject) => {
            if (mergeVid.readyState >= 1) { resolve(); return; }
            mergeVid.addEventListener('loadedmetadata', resolve, { once: true });
            mergeVid.addEventListener('error', () =>
                reject(new Error(`meta load error: ${mergeVid.error?.code}`))
            , { once: true });
        });
    }

    (async () => {
        // ── Step 1: load first clip to get dimensions ─────────────────────────
        mlog('Loading first clip for dimensions…', '#94a3b8');
        mergeVid.src = resolvedUrls[0];
        mergeVid.load();
        try {
            await waitMeta();
            if (mergeVid.videoWidth && mergeVid.videoHeight) {
                canvas.width  = mergeVid.videoWidth;
                canvas.height = mergeVid.videoHeight;
            }
            mlog(`canvas ${canvas.width}×${canvas.height}  dur=${mergeVid.duration?.toFixed(1)}s`, '#94a3b8');
        } catch(e) {
            mlog(`metadata load failed: ${e.message}`, '#ef4444');
            cleanup(); setRecordingUI(false); return;
        }

        // ── Step 2: canvas stream (stable tracks — no InvalidModificationError) ─
        const canvasStream = canvas.captureStream(30);
        mlog(`canvas stream tracks: ${canvasStream.getTracks().length}`, '#94a3b8');

        // ── Step 3: audio via AudioContext (follows video src changes) ────────
        let audioStream = null;
        try {
            audioCtx = new AudioContext();
            audioSrc = audioCtx.createMediaElementSource(mergeVid);
            const dest = audioCtx.createMediaStreamDestination();
            audioSrc.connect(dest);
            audioSrc.connect(audioCtx.destination); // monitor audio
            audioStream = dest.stream;
            audioStream.getAudioTracks().forEach(t => canvasStream.addTrack(t));
            mlog(`audio tracks added: ${audioStream.getAudioTracks().length}`, '#94a3b8');
        } catch(e) {
            mlog(`AudioContext unavailable (${e.message}) — video-only`, '#f59e0b');
        }

        mlog(`total stream tracks: ${canvasStream.getTracks().length}`, '#a8e063');

        // ── Step 4: MediaRecorder ─────────────────────────────────────────────
        const mimeType = [
            'video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus',
            'video/webm;codecs=vp9',      'video/webm;codecs=vp8',
            'video/webm',
        ].find(m => MediaRecorder.isTypeSupported(m)) || 'video/webm';
        mlog(`mimeType: ${mimeType}`, '#94a3b8');

        const chunks = [];
        let mr;
        try {
            mr = new MediaRecorder(canvasStream, { mimeType, videoBitsPerSecond: 8_000_000 });
            mlog(`MediaRecorder created  state=${mr.state}`, '#a8e063');
        } catch(e) {
            mlog(`MediaRecorder() threw: ${e}`, '#ef4444');
            cleanup(); setRecordingUI(false);
            alert('MediaRecorder error: ' + e); return;
        }
        mr.ondataavailable = e => {
            if (e.data.size > 0) {
                chunks.push(e.data);
                mlog(`chunk: ${(e.data.size/1024).toFixed(1)} KB  total: ${chunks.length}`, '#94a3b8');
            }
        };
        mr.onerror = e => mlog(`MediaRecorder ERROR: ${e.error || e}`, '#ef4444');

        _activeRecorder = mr;
        try {
            mr.start(200);
            mlog(`mr.start(200) OK  state=${mr.state}`, '#a8e063');
        } catch(e) {
            mlog(`mr.start() threw: ${e}`, '#ef4444');
            cleanup(); setRecordingUI(false);
            alert('Cannot start recording: ' + e); return;
        }

        // Start rendering video frames to canvas
        startDrawing();
        setRecordingUI(true);
        showPlayer();
        startCaptionLoop();
        mlog('Recording started (canvas capture)…', '#facc15');

        // ── Step 5: play all clips ─────────────────────────────────────────────
        for (let i = 0; i < resolvedUrls.length; i++) {
            const seg = sequence[i];
            mlog(`▶ Clip ${i+1}/${resolvedUrls.length}: "${seg.label}"`, '#64b5f6');
            setRecordingUI(true, null, `Merging clip ${i+1} / ${resolvedUrls.length}…`);
            updateCaptionOverlay(seg, 0);
            try {
                await playClip(resolvedUrls[i]);
                mlog(`  clip ${i+1} finished`, '#94a3b8');
            } catch(e) {
                mlog(`  clip ${i+1} error: ${e.message}`, '#ef4444');
            }
            if (i < resolvedUrls.length - 1) {
                await new Promise(r => setTimeout(r, 300));
            }
        }

        // ── Step 6: stop recorder and save ────────────────────────────────────
        mlog(`All ${resolvedUrls.length} clip(s) done → stopping recorder`, '#facc15');
        if (animId) { cancelAnimationFrame(animId); animId = null; }

        await new Promise(resolve => {
            mr.addEventListener('stop', resolve, { once: true });
            if (mr.state !== 'inactive') mr.stop();
            else resolve();
        });

        const totalMB = chunks.reduce((s,c) => s + c.size, 0) / 1048576;
        mlog(`${chunks.length} chunks  ${totalMB.toFixed(2)} MB`, '#a8e063');

        if (!chunks.length) {
            mlog('WARNING: no data recorded', '#ef4444');
            alert('Merge produced an empty file. Check debug log.');
        } else {
            const blob = new Blob(chunks, { type: mimeType });
            const a = Object.assign(document.createElement('a'), {
                href: URL.createObjectURL(blob),
                download: 'merged_' + Date.now() + '.webm',
            });
            document.body.appendChild(a); a.click(); a.remove();
            mlog('✅ Download triggered', '#a8e063');
        }

        cleanup();
        setRecordingUI(false);
        if (sequence.length) selectSegment(0);
    })();
}

function stopExport() {
    mainVideo.pause();
    document.getElementById('playBtn').textContent = '▶ Play';
    if (_activeRecorder && _activeRecorder.state !== 'inactive') _activeRecorder.stop();
    else setRecordingUI(false);
}

function setRecordingUI(active, mr, statusText) {
    if (mr) _activeRecorder = mr;
    document.getElementById('mergeBtn').style.display  = active ? 'none'         : 'inline-block';
    document.getElementById('stopBtn').style.display   = active ? 'inline-block' : 'none';
    document.getElementById('srtBtn').style.display    = active ? 'none'         : 'inline-block';
    document.getElementById('recDot').style.display    = active ? 'inline-block' : 'none';
    document.getElementById('recStatus').textContent   = statusText
        || (active ? 'Recording…' : 'Ready');
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
