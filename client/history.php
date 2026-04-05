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
                        <img src="<?= e($job['thumbnail']) ?>" class="video-thumb" alt="thumbnail">
                    <?php elseif ($isCompleted && $job['cdn_url']): ?>
                        <div class="video-thumb-placeholder">🎥</div>
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
                                    <a href="<?= e($job['cdn_url']) ?>" target="_blank" rel="noopener"
                                       class="share-btn dl">▶ Preview</a>
                                    <a href="<?= e($job['cdn_url']) ?>" download
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
                            <p class="text-muted text-sm" style="margin-top:6px">
                                Your video is being generated. This page auto-refreshes.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php render_pagination($pager); ?>
    <?php endif; ?>
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

// Auto-refresh every 15s if there are processing/queued jobs
<?php $hasRunning = !empty(array_filter($jobs, fn($j) => in_array($j['status'], ['queued','processing']))); ?>
<?php if ($hasRunning): ?>
setTimeout(() => { location.reload(); }, 15000);
<?php endif; ?>
</script>
</body>
</html>
