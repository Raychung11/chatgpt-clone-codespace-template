<?php
declare(strict_types=1);

/**
 * client/share.php
 * Dedicated public-facing share page for a completed video job.
 * Provides caption, hashtags, platform share links, and download.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/social.php';
require_once __DIR__ . '/../inc/layout.php';

boot_session();
$user = require_auth('/public/login.php');
$uid  = (int)$user['id'];
$pdo  = db();

$jobId = (int)($_GET['job_id'] ?? 0);
if (!$jobId) {
    flash_error('Invalid video.');
    redirect(BASE_URL . '/client/history.php');
}

// Load job — must belong to this user
$stmt = $pdo->prepare(
    'SELECT vj.*, vo.cdn_url, vo.file_path AS output_path, vo.thumbnail,
            vo.caption AS stored_caption, vo.hashtags AS stored_hashtags
     FROM `video_jobs` vj
     LEFT JOIN `video_outputs` vo ON vo.job_id = vj.id
     WHERE vj.id = ? AND vj.user_id = ?
     LIMIT 1'
);
$stmt->execute([$jobId, $uid]);
$job = $stmt->fetch();

if (!$job) {
    flash_error('Video not found.');
    redirect(BASE_URL . '/client/history.php');
}

if ($job['status'] !== 'completed') {
    flash_info('This video is not ready yet.');
    redirect(BASE_URL . '/client/history.php');
}

// Caption & hashtags — use stored or auto-generate
$caption  = $job['stored_caption']  ?: social_generate_caption($job['prompt']);
$hashtags = $job['stored_hashtags'] ?: social_generate_hashtags($job['prompt']);

$shareUrl  = BASE_URL . '/client/share.php?job_id=' . $jobId;
$shareUrls = social_share_urls($shareUrl, $caption);

$siteName = setting('site_name', 'VideoSaaS');

// Load share stats for this job
$ss = $pdo->prepare(
    'SELECT platform, COUNT(*) AS cnt
     FROM `social_share_logs` WHERE video_job_id = ?
     GROUP BY platform'
);
$ss->execute([$jobId]);
$shareStats = [];
foreach ($ss->fetchAll() as $row) {
    $shareStats[$row['platform']] = (int)$row['cnt'];
}
$totalShares = array_sum($shareStats);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Share Video #<?= $jobId ?> — <?= e($siteName) ?></title>

    <!-- Open Graph for link previews -->
    <meta property="og:title"       content="AI Marketing Video — <?= e($siteName) ?>">
    <meta property="og:description" content="<?= e(truncate($job['prompt'], 120)) ?>">
    <?php if ($job['thumbnail']): ?>
    <meta property="og:image"       content="<?= e($job['thumbnail']) ?>">
    <?php endif; ?>
    <meta property="og:url"         content="<?= e($shareUrl) ?>">
    <meta property="og:type"        content="video.other">
    <meta name="twitter:card"       content="summary_large_image">

    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
    <style>
        .share-platform-btn {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            padding: 13px 20px; border-radius: var(--radius);
            font-size: .95rem; font-weight: 700; cursor: pointer;
            border: 1px solid var(--color-border); text-decoration: none;
            transition: opacity .15s, transform .1s;
        }
        .share-platform-btn:hover { opacity: .85; transform: translateY(-1px); text-decoration: none; }
        .share-platform-btn:active { transform: scale(.97); }
        .wa  { background: #25d366; color: #fff; border-color: #25d366; }
        .fb  { background: #1877f2; color: #fff; border-color: #1877f2; }
        .tw  { background: #000;    color: #fff; border-color: #000; }
        .li  { background: #0a66c2; color: #fff; border-color: #0a66c2; }
        .ig  { background: linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888); color:#fff; border:none; }
        .tt  { background: #010101; color: #fff; border-color: #010101; }
        .copy-field { background: var(--color-surface2); border: 1px solid var(--color-border);
                      border-radius: var(--radius); padding: 14px; font-size: .9rem;
                      line-height: 1.6; color: var(--color-text); white-space: pre-wrap;
                      word-break: break-word; }
        .video-preview-box {
            background: #000; border-radius: var(--radius-lg); overflow: hidden;
            position: relative; aspect-ratio: 16/9; max-height: 420px;
            display: flex; align-items: center; justify-content: center;
        }
        .video-preview-box video { width: 100%; height: 100%; object-fit: contain; }
        .video-preview-box img   { width: 100%; height: 100%; object-fit: cover; }
    </style>
</head>
<body>
<?php render_client_navbar($user, 'history'); ?>

<div class="container main-content">
    <?= render_flash() ?>

    <div class="page-header">
        <div>
            <h1 class="page-title">Share Your Video</h1>
            <p class="page-sub">Job #<?= $jobId ?> · <?= e($job['resolution'] ?? '') ?> · <?= (int)$job['duration'] ?>s</p>
        </div>
        <a href="<?= BASE_URL ?>/client/history.php" class="btn btn-ghost">← History</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start">

        <!-- Left column: preview + prompt -->
        <div>
            <!-- Video / thumbnail preview -->
            <div class="card mb-4" style="padding:0;overflow:hidden">
                <div class="video-preview-box">
                    <?php if ($job['cdn_url']): ?>
                        <video controls playsinline preload="metadata"
                               poster="<?= e($job['thumbnail'] ?? '') ?>">
                            <source src="<?= e($job['cdn_url']) ?>">
                            Your browser does not support video.
                        </video>
                    <?php elseif ($job['thumbnail']): ?>
                        <img src="<?= e($job['thumbnail']) ?>" alt="Video thumbnail">
                    <?php else: ?>
                        <div style="color:var(--color-muted);font-size:1.2rem;text-align:center">
                            🎬<br><span style="font-size:.85rem">No preview available</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Prompt used -->
            <div class="card mb-4">
                <div class="card-header"><span class="card-title">Prompt Used</span></div>
                <p class="text-sm" style="line-height:1.7;color:var(--color-muted)"><?= e($job['prompt']) ?></p>
            </div>

            <!-- Caption -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-between align-center">
                    <span class="card-title">Caption</span>
                    <button class="btn btn-ghost btn-sm" onclick="copyField('captionBox','caption')">📋 Copy</button>
                </div>
                <div id="captionBox" class="copy-field"><?= e($caption) ?></div>
            </div>

            <!-- Hashtags -->
            <div class="card">
                <div class="card-header d-flex justify-between align-center">
                    <span class="card-title">Hashtags</span>
                    <button class="btn btn-ghost btn-sm" onclick="copyField('hashtagBox','hashtag')">📋 Copy</button>
                </div>
                <div id="hashtagBox" class="copy-field" style="color:var(--color-primary)"><?= e($hashtags) ?></div>
            </div>
        </div>

        <!-- Right column: share buttons + stats -->
        <div>
            <!-- Download -->
            <?php if ($job['cdn_url']): ?>
            <div class="card mb-4">
                <div class="card-header"><span class="card-title">Download</span></div>
                <p class="text-muted text-sm mb-3">
                    Download for <strong>Instagram</strong> and <strong>TikTok</strong> (direct file uploads only).
                </p>
                <a href="<?= e($job['cdn_url']) ?>" download
                   class="btn btn-accent btn-block"
                   onclick="logShare('tiktok','download');logShare('instagram','download')">
                    ↓ Download Video
                </a>
            </div>
            <?php endif; ?>

            <!-- Social share buttons -->
            <div class="card mb-4">
                <div class="card-header"><span class="card-title">Share To</span></div>
                <div style="display:flex;flex-direction:column;gap:10px">
                    <a href="<?= e($shareUrls['whatsapp']) ?>" target="_blank" rel="noopener"
                       class="share-platform-btn wa"
                       onclick="logShare('whatsapp','link')">
                        💬 WhatsApp
                    </a>
                    <a href="<?= e($shareUrls['facebook']) ?>" target="_blank" rel="noopener"
                       class="share-platform-btn fb"
                       onclick="logShare('facebook','link')">
                        f&nbsp;&nbsp;Facebook
                    </a>
                    <a href="<?= e($shareUrls['twitter']) ?>" target="_blank" rel="noopener"
                       class="share-platform-btn tw"
                       onclick="logShare('twitter','link')">
                        𝕏&nbsp;&nbsp;Twitter / X
                    </a>
                    <a href="<?= e($shareUrls['linkedin']) ?>" target="_blank" rel="noopener"
                       class="share-platform-btn li"
                       onclick="logShare('linkedin','link')">
                        in LinkedIn
                    </a>
                    <button class="share-platform-btn ig" style="border:none"
                            onclick="alert('Download the video first, then upload to Instagram.')">
                        📷 Instagram (download first)
                    </button>
                    <button class="share-platform-btn tt"
                            onclick="alert('Download the video first, then upload to TikTok.')">
                        ♪ TikTok (download first)
                    </button>
                </div>
            </div>

            <!-- Copy link -->
            <div class="card mb-4">
                <div class="card-header"><span class="card-title">Copy Link</span></div>
                <div style="display:flex;gap:8px">
                    <input type="text" id="pageLinkInput" value="<?= e($shareUrl) ?>"
                           class="form-control" readonly style="font-size:.82rem">
                    <button class="btn btn-ghost btn-sm" onclick="copyLink()">Copy</button>
                </div>
            </div>

            <!-- Share stats -->
            <?php if ($totalShares > 0): ?>
            <div class="card">
                <div class="card-header"><span class="card-title">Share Stats</span></div>
                <div class="text-muted text-sm mb-2"><?= $totalShares ?> total share<?= $totalShares !== 1 ? 's' : '' ?></div>
                <?php foreach ($shareStats as $plat => $cnt): ?>
                    <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:.85rem;border-bottom:1px solid var(--color-border)">
                        <span style="text-transform:capitalize"><?= e($plat) ?></span>
                        <span class="fw-bold"><?= $cnt ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
const JOB_ID     = <?= $jobId ?>;

function logShare(platform, shareType) {
    const fd = new FormData();
    fd.append('_action',    'log_share');
    fd.append('<?= CSRF_TOKEN_NAME ?>', CSRF_TOKEN);
    fd.append('job_id',     JOB_ID);
    fd.append('platform',   platform);
    fd.append('share_type', shareType);
    fetch('history.php', { method: 'POST', body: fd }).catch(() => {});
}

function copyField(elId, type) {
    const text = document.getElementById(elId).textContent;
    navigator.clipboard.writeText(text.trim()).then(() => {
        logShare('copy_link', type === 'caption' ? 'caption_copy' : 'hashtag_copy');
        const msg = type === 'caption' ? 'Caption copied!' : 'Hashtags copied!';
        showCopyFeedback(msg);
    });
}

function copyLink() {
    const val = document.getElementById('pageLinkInput').value;
    navigator.clipboard.writeText(val).then(() => {
        logShare('copy_link', 'link');
        showCopyFeedback('Link copied!');
    });
}

function showCopyFeedback(msg) {
    const el = document.createElement('div');
    el.textContent = msg;
    el.style.cssText = 'position:fixed;bottom:24px;right:24px;background:var(--color-primary);color:#fff;padding:10px 20px;border-radius:8px;font-weight:700;z-index:9999;animation:fadeIn .2s';
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2500);
}
</script>
</body>
</html>
