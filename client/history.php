<?php
declare(strict_types=1);

/**
 * client/history.php
 * Full video job history with output preview, download, and social sharing.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/wallet.php';
require_once __DIR__ . '/../inc/social.php';
require_once __DIR__ . '/../inc/layout.php';

boot_session();
$user = require_auth('/public/login.php');
$uid  = (int)$user['id'];
$pdo  = db();

// ── Handle AJAX status poll ───────────────────────────────────────────────────
if (($_GET['_action'] ?? '') === 'poll_status') {
    $ids = array_map('intval', explode(',', $_GET['ids'] ?? ''));
    $ids = array_filter($ids);
    if (empty($ids)) { json_response([]); }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT vj.id, vj.status, vo.cdn_url, vo.thumbnail
         FROM video_jobs vj
         LEFT JOIN video_outputs vo ON vo.job_id = vj.id
         WHERE vj.id IN ($placeholders) AND vj.user_id = ?"
    );
    $stmt->execute([...$ids, $uid]);
    $rows = [];
    foreach ($stmt->fetchAll() as $r) {
        $rows[(int)$r['id']] = ['status' => $r['status'], 'cdn_url' => $r['cdn_url'], 'thumbnail' => $r['thumbnail']];
    }
    json_response($rows);
}

// ── Handle share logging (AJAX POST) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'log_share') {
    csrf_verify();
    $jobId     = (int)($_POST['job_id']    ?? 0);
    $platform  = $_POST['platform']        ?? '';
    $shareType = $_POST['share_type']      ?? 'link';

    $allowed = ['whatsapp','facebook','twitter','linkedin','instagram','tiktok','copy_link'];
    if ($jobId && in_array($platform, $allowed, true)) {
        // Verify this job belongs to the user
        $check = $pdo->prepare('SELECT `id`,`prompt` FROM `video_jobs` WHERE `id`=? AND `user_id`=? LIMIT 1');
        $check->execute([$jobId, $uid]);
        $job = $check->fetch();

        if ($job) {
            $caption  = social_generate_caption($job['prompt'], $platform);
            $hashtags = social_generate_hashtags($job['prompt']);
            social_log_share($uid, $jobId, $platform, $caption, $hashtags, $shareType);
        }
    }
    json_response(['ok' => true]);
}

// ── Filter & pagination ───────────────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? 'all';
$page         = max(1, (int)($_GET['page'] ?? 1));

$where  = ['vj.user_id = ?'];
$params = [$uid];

$validStatuses = ['queued','processing','completed','failed','refunded'];
if (in_array($statusFilter, $validStatuses, true)) {
    $where[]  = 'vj.status = ?';
    $params[] = $statusFilter;
}

$wSQL = 'WHERE ' . implode(' AND ', $where);

$cs = $pdo->prepare("SELECT COUNT(*) FROM `video_jobs` vj $wSQL");
$cs->execute($params);
$total = (int)$cs->fetchColumn();
$pager = paginate($total, $page);

$jobs = $pdo->prepare(
    "SELECT vj.*,
            vo.cdn_url, vo.file_path AS output_path, vo.thumbnail,
            vo.caption, vo.hashtags
     FROM `video_jobs` vj
     LEFT JOIN `video_outputs` vo ON vo.job_id = vj.id
     $wSQL
     ORDER BY vj.created_at DESC
     LIMIT {$pager['per_page']} OFFSET {$pager['offset']}"
);
$jobs->execute($params);
$jobs = $jobs->fetchAll();

$statusBadge = [
    'queued'     => ['badge-muted',    'Queued'],
    'processing' => ['badge-info',     'Processing'],
    'completed'  => ['badge-success',  'Completed'],
    'failed'     => ['badge-danger',   'Failed'],
    'refunded'   => ['badge-warning',  'Refunded'],
];

// Build share page URL base
$shareBase = BASE_URL . '/client/share.php?job_id=';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video History — <?= e(setting('site_name','VideoSaaS')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
    <style>
        .video-card { margin-bottom: 16px; }
        .video-thumb {
            width: 120px; height: 68px; object-fit: cover;
            border-radius: 6px; background: var(--color-surface2);
            border: 1px solid var(--color-border); flex-shrink: 0;
        }
        .video-thumb-placeholder {
            width: 120px; height: 68px; border-radius: 6px;
            background: var(--color-surface2); border: 1px solid var(--color-border);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; flex-shrink: 0;
        }
        .share-bar { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
        .share-btn {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 12px; border-radius: 20px;
            font-size: .78rem; font-weight: 600; cursor: pointer;
            border: 1px solid var(--color-border);
            background: var(--color-surface2); color: var(--color-muted);
            transition: all .15s; text-decoration: none;
        }
        .share-btn:hover { color: var(--color-text); border-color: var(--color-primary); text-decoration: none; }
        .share-btn.wa    { border-color: #25d366; color: #25d366; }
        .share-btn.fb    { border-color: #1877f2; color: #1877f2; }
        .share-btn.tw    { border-color: #1da1f2; color: #1da1f2; }
        .share-btn.li    { border-color: #0a66c2; color: #0a66c2; }
        .share-btn.dl    { border-color: var(--color-accent); color: var(--color-accent); }
        .poll-indicator { display: inline-block; width: 8px; height: 8px;
                          background: var(--color-info); border-radius: 50%;
                          animation: pulse 1.5s infinite; margin-right: 5px; }
        @keyframes pulse { 0%,100% { opacity:1 } 50% { opacity:.3 } }

        /* Progress bar */
        .progress-wrap { margin-top: 10px; }
        .progress-bar-track {
            height: 6px; border-radius: 3px;
            background: var(--color-surface2);
            overflow: hidden; margin-bottom: 6px;
        }
        .progress-bar-fill {
            height: 100%; border-radius: 3px;
            background: linear-gradient(90deg, var(--color-primary), var(--color-accent));
            transition: width 1s ease;
            animation: shimmer 2s infinite linear;
            background-size: 200% 100%;
        }
        @keyframes shimmer {
            0%   { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        .progress-meta { display:flex; justify-content:space-between; font-size:.78rem; color:var(--color-muted); }
    </style>
</head>
<body>
<?php render_client_navbar($user, 'history'); ?>

<div class="container main-content">
    <?= render_flash() ?>

    <div class="page-header">
        <div>
            <h1 class="page-title">Video History</h1>
            <p class="page-sub"><?= $total ?> job<?= $total !== 1 ? 's' : '' ?> total</p>
        </div>
        <a href="<?= BASE_URL ?>/client/generate.php" class="btn btn-primary">+ Generate New</a>
    </div>

    <!-- Status filter tabs -->
    <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
        <?php
        $tabs = ['all' => 'All', 'processing' => 'Processing', 'completed' => 'Completed',
                 'failed' => 'Failed', 'refunded' => 'Refunded'];
        foreach ($tabs as $val => $label):
        ?>
            <a href="?status=<?= $val ?>"
               class="btn btn-sm <?= $statusFilter === $val ? 'btn-primary' : 'btn-ghost' ?>">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Job list -->
    <?php if (empty($jobs)): ?>
        <div class="card text-center" style="padding: 48px">
            <div style="font-size:2.5rem;margin-bottom:12px">🎬</div>
            <p class="text-muted">No videos yet.</p>
            <a href="<?= BASE_URL ?>/client/generate.php" class="btn btn-primary mt-3">Generate Your First Video</a>
        </div>
    <?php else: ?>
        <?php foreach ($jobs as $job):
            [$badgeClass, $badgeLabel] = $statusBadge[$job['status']] ?? ['badge-muted', $job['status']];
            $isCompleted = $job['status'] === 'completed';
            $isRunning   = in_array($job['status'], ['queued','processing'], true);

            // Generate caption/hashtags (use stored ones or auto-generate)
            $caption  = $job['caption']  ?: social_generate_caption($job['prompt']);
            $hashtags = $job['hashtags'] ?: social_generate_hashtags($job['prompt']);
            $shareUrl = $shareBase . (int)$job['id'];

            $shareUrls = $isCompleted
                ? social_share_urls($shareUrl, $caption)
                : [];
        ?>
            <div class="card video-card">
                <div style="display:flex;gap:16px;align-items:flex-start">

                    <!-- Thumbnail / Placeholder -->
                    <?php if ($isCompleted && $job['thumbnail']): ?>
                        <img src="<?= e($job['thumbnail']) ?>" class="video-thumb" alt="thumbnail"
                             onclick="openVideoPlayer(<?= htmlspecialchars(json_encode($job['cdn_url']), ENT_QUOTES) ?>)"
                             style="cursor:pointer">
                    <?php elseif ($isCompleted && $job['cdn_url']): ?>
                        <!-- Capture first frame as thumbnail via canvas -->
                        <div class="video-thumb-placeholder" style="cursor:pointer;position:relative;overflow:hidden;padding:0"
                             onclick="openVideoPlayer(<?= htmlspecialchars(json_encode($job['cdn_url']), ENT_QUOTES) ?>)">
                            <video muted playsinline preload="metadata"
                                   style="width:120px;height:68px;object-fit:cover;display:block;border-radius:6px"
                                   data-capture-thumb="1"
                                   src="<?= e($job['cdn_url']) ?>#t=0.5">
                            </video>
                            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
                                        background:rgba(0,0,0,.3);border-radius:6px;pointer-events:none">
                                <span style="font-size:1.4rem">▶</span>
                            </div>
                        </div>
                    <?php elseif ($isRunning): ?>
                        <div class="video-thumb-placeholder" style="animation:pulse 2s infinite;opacity:.6">⏳</div>
                    <?php else: ?>
                        <div class="video-thumb-placeholder">
                            <?= $job['status'] === 'failed' ? '❌' : '↩️' ?>
                        </div>
                    <?php endif; ?>

                    <!-- Job info -->
                    <div style="flex:1;min-width:0">
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px">
                            <?php if ($isRunning): ?>
                                <span class="poll-indicator"></span>
                            <?php endif; ?>
                            <span class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
                            <span class="text-muted text-sm">#<?= (int)$job['id'] ?></span>
                            <span class="text-muted text-sm"><?= e($job['resolution'] ?? '—') ?> · <?= (int)$job['duration'] ?>s</span>
                            <span class="text-muted text-sm"><?= e(format_credits((float)$job['credit_cost'])) ?> credits</span>
                            <span class="text-muted text-sm" style="margin-left:auto"><?= e(format_datetime($job['created_at'])) ?></span>
                        </div>

                        <p class="text-sm" style="color:var(--color-text);margin-bottom:6px">
                            <?= e(truncate($job['prompt'], 140)) ?>
                        </p>

                        <?php if ($job['error_message']): ?>
                            <p class="text-sm text-danger">Error: <?= e($job['error_message']) ?></p>
                        <?php endif; ?>

                        <!-- Action buttons -->
                        <?php if ($isCompleted): ?>
                            <div class="share-bar">
                                <!-- Video preview -->
                                <?php if ($job['cdn_url']): ?>
                                    <button type="button" class="share-btn dl"
                                            onclick="openVideoPlayer(<?= htmlspecialchars(json_encode($job['cdn_url']), ENT_QUOTES) ?>)">
                                        ▶ Preview
                                    </button>
                                    <a href="<?= e($job['cdn_url']) ?>" download="video_<?= (int)$job['id'] ?>.mp4"
                                       class="share-btn dl"
                                       onclick="logShare(<?= (int)$job['id'] ?>,'tiktok','download')">
                                        ↓ Download
                                    </a>
                                <?php endif; ?>

                                <!-- Share buttons -->
                                <a href="<?= e($shareUrls['whatsapp']) ?>" target="_blank" rel="noopener"
                                   class="share-btn wa"
                                   onclick="logShare(<?= (int)$job['id'] ?>,'whatsapp','link')">
                                    💬 WhatsApp
                                </a>
                                <a href="<?= e($shareUrls['facebook']) ?>" target="_blank" rel="noopener"
                                   class="share-btn fb"
                                   onclick="logShare(<?= (int)$job['id'] ?>,'facebook','link')">
                                    f Facebook
                                </a>
                                <a href="<?= e($shareUrls['twitter']) ?>" target="_blank" rel="noopener"
                                   class="share-btn tw"
                                   onclick="logShare(<?= (int)$job['id'] ?>,'twitter','link')">
                                    𝕏 Twitter
                                </a>
                                <a href="<?= e($shareUrls['linkedin']) ?>" target="_blank" rel="noopener"
                                   class="share-btn li"
                                   onclick="logShare(<?= (int)$job['id'] ?>,'linkedin','link')">
                                    in LinkedIn
                                </a>

                                <!-- Caption & hashtag tools -->
                                <button type="button" class="share-btn"
                                        onclick="copyText(<?= htmlspecialchars(json_encode($caption), ENT_QUOTES) ?>, this, <?= (int)$job['id'] ?>, 'caption_copy')">
                                    📋 Copy Caption
                                </button>
                                <button type="button" class="share-btn"
                                        onclick="copyText(<?= htmlspecialchars(json_encode($hashtags), ENT_QUOTES) ?>, this, <?= (int)$job['id'] ?>, 'hashtag_copy')">
                                    # Copy Hashtags
                                </button>

                                <!-- Full share page -->
                                <a href="<?= e($shareUrl) ?>" class="share-btn">
                                    🔗 Share Page
                                </a>
                            </div>

                        <?php elseif ($isRunning): ?>
                            <div class="progress-wrap" id="progress-<?= (int)$job['id'] ?>">
                                <div class="progress-bar-track">
                                    <div class="progress-bar-fill" style="width:<?= $job['status']==='processing' ? '60%' : '15%' ?>"></div>
                                </div>
                                <div class="progress-meta">
                                    <span id="status-label-<?= (int)$job['id'] ?>">
                                        <?= $job['status'] === 'processing' ? 'Generating video…' : 'Queued — waiting to start…' ?>
                                    </span>
                                    <span id="elapsed-<?= (int)$job['id'] ?>"
                                          data-started="<?= e($job['started_at'] ?? $job['created_at']) ?>">
                                    </span>
                                </div>
                            </div>
                            <div style="margin-top:10px">
                                <button type="button"
                                        class="btn btn-ghost btn-sm"
                                        style="color:var(--color-danger);border-color:var(--color-danger)"
                                        onclick="cancelJob(<?= (int)$job['id'] ?>, this)">
                                    ✕ Cancel &amp; Refund
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php render_pagination($pager); ?>
    <?php endif; ?>
</div>

<!-- Video player modal -->
<div id="videoModal" onclick="if(event.target===this)closeVideoPlayer()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);
            z-index:1000;align-items:center;justify-content:center;flex-direction:column;gap:14px">
    <video id="modalVideo" controls playsinline
           style="max-width:92vw;max-height:80vh;border-radius:10px;background:#000;outline:none">
        Your browser does not support video playback.
    </video>
    <button onclick="closeVideoPlayer()"
            style="background:rgba(255,255,255,.15);color:#fff;border:none;padding:8px 24px;
                   border-radius:20px;cursor:pointer;font-size:.9rem">
        ✕ Close
    </button>
</div>

<script>
const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;

// Log share to server (fire-and-forget)
function logShare(jobId, platform, shareType) {
    const fd = new FormData();
    fd.append('_action',    'log_share');
    fd.append('<?= CSRF_TOKEN_NAME ?>', CSRF_TOKEN);
    fd.append('job_id',     jobId);
    fd.append('platform',   platform);
    fd.append('share_type', shareType);
    fetch('', { method: 'POST', body: fd }).catch(() => {});
}

// Copy text to clipboard
function copyText(text, btn, jobId, shareType) {
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.textContent;
        btn.textContent = '✓ Copied!';
        logShare(jobId, shareType === 'caption_copy' ? 'copy_link' : 'copy_link', shareType);
        setTimeout(() => { btn.textContent = orig; }, 2000);
    }).catch(() => {
        // Fallback
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        btn.textContent = '✓ Copied!';
        setTimeout(() => { btn.textContent = btn.dataset.orig; }, 2000);
    });
}

// ── Live status polling ───────────────────────────────────────────────────────
<?php
$runningIds = array_values(array_map(
    fn($j) => (int)$j['id'],
    array_filter($jobs, fn($j) => in_array($j['status'], ['queued','processing']))
));
$runningStarted = [];
foreach ($jobs as $j) {
    if (in_array($j['status'], ['queued','processing'])) {
        $runningStarted[(int)$j['id']] = $j['started_at'] ?? $j['created_at'];
    }
}
?>
<?php if (!empty($runningIds)): ?>
const runningIds   = <?= json_encode($runningIds) ?>;
const pollInterval = 6000; // 6 seconds

// Elapsed time counters
function formatElapsed(seconds) {
    if (seconds < 60) return seconds + 's';
    return Math.floor(seconds / 60) + 'm ' + (seconds % 60) + 's';
}

// Update elapsed time every second
setInterval(() => {
    document.querySelectorAll('[data-started]').forEach(el => {
        const started = new Date(el.dataset.started.replace(' ', 'T') + 'Z');
        const elapsed = Math.floor((Date.now() - started.getTime()) / 1000);
        el.textContent = elapsed > 0 ? formatElapsed(Math.max(0, elapsed)) : '';
    });
}, 1000);

// Animate progress bar forward over time
function advanceProgress(jobId, currentPct) {
    const fill = document.querySelector(`#progress-${jobId} .progress-bar-fill`);
    if (!fill) return;
    // Slowly creep toward 90% — never hits 100% until actually done
    const next = Math.min(90, currentPct + (Math.random() * 3 + 1));
    fill.style.width = next + '%';
    return next;
}

let progressPcts = {};
runningIds.forEach(id => {
    const fill = document.querySelector(`#progress-${id} .progress-bar-fill`);
    progressPcts[id] = fill ? parseFloat(fill.style.width) : 20;
});

// Advance bars every 3s
setInterval(() => {
    runningIds.forEach(id => {
        progressPcts[id] = advanceProgress(id, progressPcts[id] || 20);
    });
}, 3000);

// Poll status every 6s
async function pollStatus() {
    for (const id of runningIds) {
        try {
            // Calls BytePlus directly — works without a cron job
            const res  = await fetch(`<?= BASE_URL ?>/client/check_job_status.php?job_id=${id}`);
            const data = await res.json();

            if (!data.ok) continue;

            const label = document.getElementById(`status-label-${id}`);
            const fill  = document.querySelector(`#progress-${id} .progress-bar-fill`);

            if (data.status === 'completed') {
                if (fill)  fill.style.width = '100%';
                if (label) label.textContent = '✓ Done! Reloading…';
                setTimeout(() => location.reload(), 1200);
                return; // stop polling
            } else if (data.status === 'refunded' || data.status === 'failed') {
                if (label) label.textContent = '✗ Failed — credits refunded. Reloading…';
                setTimeout(() => location.reload(), 1500);
                return;
            } else if (data.status === 'processing' && label) {
                label.textContent = 'Generating video…';
            }
        } catch(e) {}
    }
}

setInterval(pollStatus, pollInterval);
pollStatus(); // run immediately on load
<?php endif; ?>

// Load first frame of video thumbnails
document.querySelectorAll('video[data-capture-thumb]').forEach(v => {
    v.addEventListener('loadeddata', () => { v.currentTime = 0.5; });
    v.addEventListener('error', () => {
        // If video fails to load as thumb, show fallback icon
        if (v.parentElement) v.parentElement.innerHTML = '<span style="font-size:1.5rem">🎥</span>';
    });
});

// Video player modal
function openVideoPlayer(url) {
    const modal = document.getElementById('videoModal');
    const video = document.getElementById('modalVideo');
    video.src = url;
    modal.style.display = 'flex';
    video.play().catch(() => {});
}
function closeVideoPlayer() {
    const modal = document.getElementById('videoModal');
    const video = document.getElementById('modalVideo');
    video.pause();
    video.src = '';
    modal.style.display = 'none';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeVideoPlayer(); });

// Cancel job
async function cancelJob(jobId, btn) {
    if (!confirm('Cancel this job and refund your credits?')) return;

    btn.disabled = true;
    btn.textContent = 'Cancelling…';

    const fd = new FormData();
    fd.append('<?= CSRF_TOKEN_NAME ?>', CSRF_TOKEN);
    fd.append('job_id', jobId);

    try {
        const res  = await fetch('<?= BASE_URL ?>/client/cancel_job.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.ok) {
            const label = document.getElementById(`status-label-${jobId}`);
            const prog  = document.getElementById(`progress-${jobId}`);
            if (label) label.textContent = '✓ Cancelled — ' + data.message;
            if (prog)  prog.querySelector('.progress-bar-fill').style.background = 'var(--color-danger)';
            btn.style.display = 'none';
            setTimeout(() => location.reload(), 1500);
        } else {
            btn.disabled = false;
            btn.textContent = '✕ Cancel & Refund';
            alert(data.error || 'Cancel failed. Please try again.');
        }
    } catch(e) {
        btn.disabled = false;
        btn.textContent = '✕ Cancel & Refund';
        alert('Network error. Please try again.');
    }
}
</script>
</body>
</html>
