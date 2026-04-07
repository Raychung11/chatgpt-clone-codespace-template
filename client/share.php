<?php
declare(strict_types=1);

/**
 * client/share.php
 * PUBLIC share page — no login required.
 * Anyone with the link can watch and reshare the video.
 * Open Graph + Twitter card meta for social media previews.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/social.php';
require_once __DIR__ . '/../inc/layout.php';

boot_session();

$jobId = (int)($_GET['job_id'] ?? 0);
if (!$jobId) {
    http_response_code(404);
    die('Video not found.');
}

$pdo  = db();
$stmt = $pdo->prepare(
    'SELECT vj.id, vj.prompt, vj.resolution, vj.duration, vj.created_at,
            vo.cdn_url, vo.thumbnail,
            vo.caption AS stored_caption, vo.hashtags AS stored_hashtags,
            u.name AS creator_name
     FROM `video_jobs` vj
     LEFT JOIN `video_outputs` vo ON vo.job_id = vj.id
     LEFT JOIN `users` u ON u.id = vj.user_id
     WHERE vj.id = ? AND vj.status = "completed"
     LIMIT 1'
);
$stmt->execute([$jobId]);
$job = $stmt->fetch();

if (!$job || !$job['cdn_url']) {
    http_response_code(404);
    die('Video not found or not ready yet.');
}

$siteName  = setting('site_name', 'VideoSaaS');
$shareUrl  = BASE_URL . '/client/share.php?job_id=' . $jobId;
$videoUrl  = $job['cdn_url'];
$thumbUrl  = $job['thumbnail'] ?: '';

$caption   = $job['stored_caption']  ?: social_generate_caption($job['prompt']);
$hashtags  = $job['stored_hashtags'] ?: social_generate_hashtags($job['prompt']);
$shareUrls = social_share_urls($shareUrl, $caption . "\n\n" . $hashtags);

// OG title — clean prompt, strip placeholders
$ogTitle = preg_replace('/\{\{[^}]*\}\}/', '', $job['prompt']);
$ogTitle = trim(preg_replace('/\s{2,}/', ' ', $ogTitle));
$ogTitle = mb_substr($ogTitle ?: 'AI Marketing Video', 0, 80);

// Log share view (best effort)
try {
    $pdo->prepare(
        'INSERT INTO `social_share_logs` (user_id, video_job_id, platform, caption, hashtags, share_type)
         VALUES (0, ?, "view", "", "", "view")
         ON DUPLICATE KEY UPDATE video_job_id = video_job_id'
    )->execute([$jobId]);
} catch (\Throwable $e) {}

// Is the current user the owner?
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/auth.php';
$currentUser = auth_user();
$isOwner     = $currentUser && (int)$currentUser['id'] === (int)($pdo->prepare('SELECT user_id FROM video_jobs WHERE id=? LIMIT 1')->execute([$jobId]) ? $pdo->query("SELECT user_id FROM video_jobs WHERE id=$jobId LIMIT 1")->fetchColumn() : 0);
?>
<!DOCTYPE html>
<html lang="en" prefix="og: https://ogp.me/ns#">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($ogTitle) ?> — <?= e($siteName) ?></title>

<!-- ── Open Graph (Facebook, LinkedIn, WhatsApp) ──────────────── -->
<meta property="og:type"              content="video.other">
<meta property="og:site_name"         content="<?= e($siteName) ?>">
<meta property="og:title"             content="<?= e($ogTitle) ?>">
<meta property="og:description"       content="<?= e($caption) ?>">
<meta property="og:url"               content="<?= e($shareUrl) ?>">
<meta property="og:video"             content="<?= e($videoUrl) ?>">
<meta property="og:video:secure_url"  content="<?= e($videoUrl) ?>">
<meta property="og:video:type"        content="video/mp4">
<meta property="og:video:width"       content="1280">
<meta property="og:video:height"      content="720">
<?php if ($thumbUrl): ?>
<meta property="og:image"             content="<?= e($thumbUrl) ?>">
<meta property="og:image:width"       content="1280">
<meta property="og:image:height"      content="720">
<?php endif; ?>

<!-- ── Twitter / X Card ───────────────────────────────────────── -->
<meta name="twitter:card"             content="player">
<meta name="twitter:title"            content="<?= e($ogTitle) ?>">
<meta name="twitter:description"      content="<?= e($caption) ?>">
<meta name="twitter:player"           content="<?= e($shareUrl) ?>&embed=1">
<meta name="twitter:player:width"     content="1280">
<meta name="twitter:player:height"    content="720">
<?php if ($thumbUrl): ?>
<meta name="twitter:image"            content="<?= e($thumbUrl) ?>">
<?php endif; ?>

<link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
<style>
    .video-hero {
        background: #000; border-radius: var(--radius-lg); overflow: hidden;
        position: relative; aspect-ratio: 16/9;
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 20px;
    }
    .video-hero video { width:100%; height:100%; object-fit:contain; outline:none; }
    .share-btn-lg {
        display:flex; align-items:center; justify-content:center; gap:10px;
        padding:13px 18px; border-radius:var(--radius); font-weight:700;
        font-size:.95rem; cursor:pointer; text-decoration:none;
        transition:opacity .15s, transform .1s; border:none;
    }
    .share-btn-lg:hover { opacity:.85; transform:translateY(-1px); text-decoration:none; }
    .wa  { background:#25d366; color:#fff; }
    .fb  { background:#1877f2; color:#fff; }
    .tw  { background:#000;    color:#fff; }
    .li  { background:#0a66c2; color:#fff; }
    .ig  { background:linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888); color:#fff; }
    .tt  { background:#010101; color:#fff; }
    .copy-box {
        background:var(--color-surface2); border:1px solid var(--color-border);
        border-radius:var(--radius); padding:14px; font-size:.88rem;
        line-height:1.7; white-space:pre-wrap; word-break:break-word;
        color:var(--color-text);
    }
</style>
</head>
<body>

<!-- Minimal public navbar -->
<nav class="navbar">
    <div class="navbar-inner">
        <a href="<?= BASE_URL ?>/client/dashboard.php" class="navbar-brand">
            <?= e($siteName) ?><span></span>
        </a>
        <div style="margin-left:auto;display:flex;gap:10px;align-items:center">
            <?php if ($currentUser): ?>
                <a href="<?= BASE_URL ?>/client/history.php" class="btn btn-ghost btn-sm">← My Videos</a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/public/login.php" class="btn btn-ghost btn-sm">Sign In</a>
                <a href="<?= BASE_URL ?>/public/register.php" class="btn btn-primary btn-sm">Get Started</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container main-content" style="max-width:860px">

    <!-- Creator credit -->
    <p class="text-muted text-sm" style="margin-bottom:12px">
        <?php if ($job['creator_name']): ?>
            Shared by <strong><?= e($job['creator_name']) ?></strong> ·
        <?php endif; ?>
        <?= e($job['resolution'] ?? '') ?> · <?= (int)$job['duration'] ?>s ·
        <?= e(format_datetime($job['created_at'])) ?>
    </p>

    <!-- Video player -->
    <div class="video-hero">
        <video controls playsinline autoplay muted loop
               <?= $thumbUrl ? 'poster="' . e($thumbUrl) . '"' : '' ?>
               id="mainVideo">
            <source src="<?= e($videoUrl) ?>" type="video/mp4">
        </video>
    </div>

    <!-- Title -->
    <h1 style="font-size:1.3rem;margin-bottom:6px"><?= e($ogTitle) ?></h1>
    <p class="text-muted text-sm" style="margin-bottom:24px"><?= e($caption) ?></p>

    <div style="display:grid;grid-template-columns:1fr 300px;gap:24px;align-items:start">

        <!-- Left: caption + hashtags -->
        <div>
            <div class="card mb-3">
                <div class="card-header d-flex justify-between align-center">
                    <span class="card-title">Caption</span>
                    <button class="btn btn-ghost btn-sm" onclick="copyText('captionBox','Caption copied!')">📋 Copy</button>
                </div>
                <div id="captionBox" class="copy-box"><?= e($caption) ?></div>
            </div>
            <div class="card">
                <div class="card-header d-flex justify-between align-center">
                    <span class="card-title">Hashtags</span>
                    <button class="btn btn-ghost btn-sm" onclick="copyText('hashtagBox','Hashtags copied!')">📋 Copy</button>
                </div>
                <div id="hashtagBox" class="copy-box" style="color:var(--color-primary)"><?= e($hashtags) ?></div>
            </div>
        </div>

        <!-- Right: share buttons -->
        <div>
            <div class="card mb-3">
                <div class="card-header"><span class="card-title">Share Video</span></div>
                <div style="display:flex;flex-direction:column;gap:8px">
                    <a href="<?= e($shareUrls['whatsapp']) ?>" target="_blank" rel="noopener"
                       class="share-btn-lg wa">💬 WhatsApp</a>
                    <a href="<?= e($shareUrls['facebook']) ?>" target="_blank" rel="noopener"
                       class="share-btn-lg fb">f&nbsp; Facebook</a>
                    <a href="<?= e($shareUrls['twitter']) ?>" target="_blank" rel="noopener"
                       class="share-btn-lg tw">𝕏&nbsp; Twitter / X</a>
                    <a href="<?= e($shareUrls['linkedin']) ?>" target="_blank" rel="noopener"
                       class="share-btn-lg li">in LinkedIn</a>
                    <button class="share-btn-lg ig"
                            onclick="document.getElementById('dlBtn').click();alert('Video downloading — upload it to Instagram!')">
                        📷 Instagram
                    </button>
                    <button class="share-btn-lg tt"
                            onclick="document.getElementById('dlBtn').click();alert('Video downloading — upload it to TikTok!')">
                        ♪ TikTok
                    </button>
                </div>
            </div>

            <!-- Copy link -->
            <div class="card mb-3">
                <div class="card-header"><span class="card-title">Copy Link</span></div>
                <div style="display:flex;gap:8px">
                    <input id="shareLinkInput" type="text" class="form-control"
                           style="font-size:.8rem" value="<?= e($shareUrl) ?>" readonly>
                    <button class="btn btn-ghost btn-sm"
                            onclick="copyText('shareLinkInput','Link copied!',true)">Copy</button>
                </div>
            </div>

            <!-- Download -->
            <a id="dlBtn" href="<?= e($videoUrl) ?>" download="video_<?= $jobId ?>.mp4"
               class="btn btn-accent btn-block">↓ Download MP4</a>
        </div>
    </div>

    <?php if (!$currentUser): ?>
    <div class="card" style="margin-top:28px;text-align:center;padding:28px">
        <p style="font-size:1.1rem;font-weight:700;margin-bottom:8px">Create your own AI marketing videos</p>
        <p class="text-muted text-sm" style="margin-bottom:16px">Turn any idea into a professional video in seconds.</p>
        <a href="<?= BASE_URL ?>/public/register.php" class="btn btn-primary">Get Started Free →</a>
    </div>
    <?php endif; ?>

</div>

<script>
function copyText(elId, msg, isInput = false) {
    const el   = document.getElementById(elId);
    const text = isInput ? el.value : el.textContent.trim();
    navigator.clipboard.writeText(text).then(() => toast(msg)).catch(() => {
        el.select?.();
        document.execCommand('copy');
        toast(msg);
    });
}
function toast(msg) {
    const el = document.createElement('div');
    el.textContent = msg;
    el.style.cssText = 'position:fixed;bottom:24px;right:24px;background:var(--color-primary);color:#fff;padding:10px 20px;border-radius:8px;font-weight:700;z-index:9999;';
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2200);
}
// Unmute video on first click
document.getElementById('mainVideo')?.addEventListener('click', function() {
    this.muted = false;
});
</script>
</body>
</html>
