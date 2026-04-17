<?php
declare(strict_types=1);

/**
 * client/long-video.php
 * 30-Second Ad — storyboard form and status tracker.
 * Chains three 10-second Seedance clips with first-frame visual continuity.
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

// ── DB table guard ────────────────────────────────────────────────────────────
$tableExists = false;
try {
    $pdo->query('SELECT 1 FROM `long_video_jobs` LIMIT 1');
    $tableExists = true;
} catch (\Throwable $e) {}

// ── Credit cost ───────────────────────────────────────────────────────────────
$creditCost = (float)(function_exists('setting')
    ? (setting('long_video_credit_cost', '105') ?: '105')
    : '105');

// ── One-time submit token (prevent double-submit) ─────────────────────────────
const LV_TOKEN_KEY = 'lv_submit_token';
$errors = [];

// ── Handle form POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    $submittedToken = $_POST['_lv_token'] ?? '';
    $sessionToken   = $_SESSION[LV_TOKEN_KEY] ?? '';
    if (!$submittedToken || $submittedToken !== $sessionToken) {
        redirect(BASE_URL . '/client/long-video.php');
    }
    unset($_SESSION[LV_TOKEN_KEY]);

    $prompt1    = trim($_POST['prompt1']    ?? '');
    $prompt2    = trim($_POST['prompt2']    ?? '');
    $prompt3    = trim($_POST['prompt3']    ?? '');
    $resolution = in_array($_POST['resolution'] ?? '', ['720p','1080p'], true)
                  ? $_POST['resolution'] : '1080p';

    if (!$prompt1) $errors['prompt1'] = 'Shot 1 prompt is required.';
    if (!$prompt2) $errors['prompt2'] = 'Shot 2 prompt is required.';
    if (!$prompt3) $errors['prompt3'] = 'Shot 3 prompt is required.';

    $balance = wallet_balance($uid);
    if (empty($errors) && $balance < $creditCost) {
        $errors['balance'] = sprintf(
            'Insufficient credits. Need %.0f, you have %.0f. <a href="%s">Top up →</a>',
            $creditCost, $balance, BASE_URL . '/client/buy-credits.php'
        );
    }

    // Save optional hero frame
    $heroFramePath = null;
    if (empty($errors)) {
        $heroFile = $_FILES['hero_frame'] ?? null;
        if ($heroFile && $heroFile['error'] === UPLOAD_ERR_OK
            && in_array($heroFile['type'], ['image/jpeg','image/png','image/webp'], true)) {
            $hExt  = pathinfo($heroFile['name'], PATHINFO_EXTENSION) ?: 'jpg';
            $hName = 'hero_' . $uid . '_' . bin2hex(random_bytes(6)) . '.' . $hExt;
            $hDir  = BASE_PATH . '/uploads/long_video/frames';
            if (!is_dir($hDir)) @mkdir($hDir, 0755, true);
            $heroFramePath = $hDir . '/' . $hName;
            move_uploaded_file($heroFile['tmp_name'], $heroFramePath);
        }
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO `long_video_jobs`
                 (user_id, prompt1, prompt2, prompt3, resolution, hero_frame_path, status, credit_cost)
                 VALUES (?,?,?,?,?,?,"queued",?)'
            );
            $ins->execute([$uid, $prompt1, $prompt2, $prompt3, $resolution, $heroFramePath, $creditCost]);
            $lvJobId = (int)$pdo->lastInsertId();

            $deduct = wallet_deduct($uid, $creditCost, 'deduction', 'long_video_job', $lvJobId,
                '30s Ad job #' . $lvJobId);

            if (!$deduct['ok']) {
                $pdo->rollBack();
                if ($heroFramePath) @unlink($heroFramePath);
                $errors['balance'] = $deduct['error'];
            } else {
                $pdo->commit();
                flash_success('30-Second Ad submitted! Clips generate sequentially — check back in 30–60 minutes.');
                redirect(BASE_URL . '/client/long-video.php');
            }
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[long-video] ' . $e->getMessage());
            $errors['general'] = 'Something went wrong. Please try again.';
        }
    }
}

// Regenerate submit token
$_SESSION[LV_TOKEN_KEY] = bin2hex(random_bytes(16));
$lvToken = $_SESSION[LV_TOKEN_KEY];

// ── Load past jobs ────────────────────────────────────────────────────────────
$pastJobs = [];
if ($tableExists) {
    $js = $pdo->prepare(
        'SELECT id, status, resolution, prompt1, prompt2, prompt3,
                clip1_url, clip2_url, clip3_url, final_video_url,
                error_message, credit_cost, created_at, completed_at
         FROM `long_video_jobs`
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 20'
    );
    $js->execute([$uid]);
    $pastJobs = $js->fetchAll();
}

$balance = wallet_balance($uid);
$hasActiveJobs = (bool)array_filter($pastJobs,
    fn($j) => in_array($j['status'], ['queued','clip1','clip2','clip3','stitching'], true));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>30-Second Ad — <?= e(setting('site_name','VideoSaaS')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
    <style>
        .shot-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }
        @media (max-width: 768px) { .shot-grid { grid-template-columns: 1fr; } }

        .shot-card {
            background: var(--color-surface2);
            border: 1px solid var(--color-border);
            border-radius: var(--radius);
            padding: 16px;
        }
        .shot-label {
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--color-primary);
            margin-bottom: 8px;
        }
        .shot-sub {
            font-size: .72rem;
            color: var(--color-muted);
            margin-top: 6px;
        }

        .cost-strip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--color-surface2);
            border: 1px solid var(--color-border);
            border-radius: var(--radius);
            padding: 12px 16px;
            margin-bottom: 20px;
        }
        .cost-strip .cost-label { font-size: .82rem; color: var(--color-muted); }
        .cost-strip .cost-val { font-size: 1.25rem; font-weight: 700; color: var(--color-primary); }

        .lv-job { margin-bottom: 14px; }
        .lv-job .job-prompts { margin: 8px 0; }
        .lv-job .job-prompts .shot-pill {
            display: inline-block;
            background: var(--color-surface2);
            border-radius: 4px;
            padding: 2px 8px;
            font-size: .75rem;
            color: var(--color-muted);
            margin: 2px 4px 2px 0;
        }
        .clip-links { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
        .clip-links a {
            font-size: .78rem;
            padding: 4px 12px;
            background: var(--color-surface2);
            border: 1px solid var(--color-border);
            border-radius: var(--radius);
            color: var(--color-primary);
            text-decoration: none;
            transition: border-color .15s;
        }
        .clip-links a:hover { border-color: var(--color-primary); }

        .status-badge { display: inline-block; padding: 2px 10px; border-radius: 99px; font-size: .72rem; font-weight: 700; text-transform: uppercase; }
        .status-badge.queued     { background: rgba(255,193,7,.15); color: #ffc107; }
        .status-badge.processing { background: rgba(108,71,255,.15); color: var(--color-primary); }
        .status-badge.completed  { background: rgba(34,197,94,.15);  color: #22c55e; }
        .status-badge.failed,
        .status-badge.refunded   { background: rgba(239,68,68,.15);  color: #ef4444; }
    </style>
</head>
<body>
<?php render_client_navbar($user, 'long_video'); ?>

<div class="container main-content">
    <?= render_flash() ?>

    <?php if (!$tableExists): ?>
    <div class="alert alert--error">
        ⚠ The <code>long_video_jobs</code> table does not exist yet.
        Please run <strong>sql/migrate_long_video.sql</strong> in phpMyAdmin first.
    </div>
    <?php else: ?>

    <div class="page-header">
        <div>
            <h1 class="page-title">30-Second Ad</h1>
            <p class="page-sub">Chain three 10-second Seedance 1.5 clips into one seamless 30-second video</p>
        </div>
        <span class="navbar-wallet">⚡ <?= e(format_credits($balance)) ?> credits</span>
    </div>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert--error"><?= e($errors['general']) ?></div>
    <?php endif ?>
    <?php if (!empty($errors['balance'])): ?>
        <div class="alert alert--error"><?= $errors['balance'] ?></div>
    <?php endif ?>

    <!-- ── How it works ── -->
    <div class="card" style="margin-bottom:20px">
        <div class="card-body" style="display:flex;gap:16px;flex-wrap:wrap">
            <?php foreach ([
                ['①', 'Write 3 shots', 'Describe each 10-second scene. Shot 1 is text-to-video; shots 2 & 3 continue from the previous clip\'s last frame.'],
                ['②', 'Clips generate', 'Each clip takes ~10–20 min. The cron job polls automatically and chains them in sequence.'],
                ['③', 'FFmpeg stitches', 'All three clips are downloaded and concatenated into a single 30-second MP4.'],
            ] as [$num, $title, $desc]): ?>
            <div style="flex:1;min-width:180px">
                <div style="font-size:1.4rem;margin-bottom:4px"><?= $num ?></div>
                <div style="font-weight:600;margin-bottom:4px"><?= $title ?></div>
                <div class="text-muted text-sm"><?= $desc ?></div>
            </div>
            <?php endforeach ?>
        </div>
    </div>

    <!-- ── Submit form ── -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Create 30-Second Ad</span>
            <span class="text-muted text-sm" style="margin-left:auto"><?= number_format($creditCost, 0) ?> credits</span>
        </div>

        <form method="post" enctype="multipart/form-data" id="lvForm">
            <input type="hidden" name="_lv_token" value="<?= e($lvToken) ?>">

            <!-- Resolution -->
            <div class="form-group">
                <label class="form-label">Resolution</label>
                <div style="display:flex;gap:20px">
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
                        <input type="radio" name="resolution" value="1080p"
                               <?= (($_POST['resolution'] ?? '1080p') === '1080p') ? 'checked' : '' ?>>
                        1080p (HD)
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
                        <input type="radio" name="resolution" value="720p"
                               <?= (($_POST['resolution'] ?? '') === '720p') ? 'checked' : '' ?>>
                        720p
                    </label>
                </div>
            </div>

            <!-- 3 shot prompts -->
            <div class="form-group">
                <label class="form-label">Storyboard — 3 Shots</label>
                <div class="shot-grid">
                    <div class="shot-card">
                        <div class="shot-label">Shot 1 — Opening</div>
                        <?php if (!empty($errors['prompt1'])): ?>
                            <div class="alert alert--error" style="margin-bottom:8px;font-size:.82rem"><?= e($errors['prompt1']) ?></div>
                        <?php endif ?>
                        <textarea name="prompt1" rows="5" style="width:100%;resize:vertical"
                                  placeholder="Establish your scene. E.g. A sleek serum bottle on white marble, golden hour light, slow push in…"><?= e($_POST['prompt1'] ?? '') ?></textarea>
                        <div class="shot-sub">Clip 1 — text-to-video</div>
                    </div>
                    <div class="shot-card">
                        <div class="shot-label">Shot 2 — Middle</div>
                        <?php if (!empty($errors['prompt2'])): ?>
                            <div class="alert alert--error" style="margin-bottom:8px;font-size:.82rem"><?= e($errors['prompt2']) ?></div>
                        <?php endif ?>
                        <textarea name="prompt2" rows="5" style="width:100%;resize:vertical"
                                  placeholder="Continue the story. E.g. A model's hands pick up the bottle, applying a drop to glowing skin…"><?= e($_POST['prompt2'] ?? '') ?></textarea>
                        <div class="shot-sub">Clip 2 — continues from Clip 1's last frame</div>
                    </div>
                    <div class="shot-card">
                        <div class="shot-label">Shot 3 — Closing</div>
                        <?php if (!empty($errors['prompt3'])): ?>
                            <div class="alert alert--error" style="margin-bottom:8px;font-size:.82rem"><?= e($errors['prompt3']) ?></div>
                        <?php endif ?>
                        <textarea name="prompt3" rows="5" style="width:100%;resize:vertical"
                                  placeholder="Close strong. E.g. Product on shelf with tagline, camera pulls back…"><?= e($_POST['prompt3'] ?? '') ?></textarea>
                        <div class="shot-sub">Clip 3 — continues from Clip 2's last frame</div>
                    </div>
                </div>
            </div>

            <!-- Hero frame (optional) -->
            <div class="form-group">
                <label class="form-label">
                    Hero / Ending Frame
                    <span class="text-muted" style="font-weight:400"> — optional</span>
                </label>
                <input type="file" name="hero_frame" accept="image/jpeg,image/png,image/webp">
                <p class="text-muted text-sm" style="margin-top:4px">
                    A target ending image (e.g. product packshot or brand logo). Clip 3 will try to end on this frame.
                </p>
            </div>

            <!-- Cost strip -->
            <div class="cost-strip">
                <div>
                    <div class="cost-label">Your balance</div>
                    <div style="font-weight:600"><?= e(format_credits($balance)) ?> credits</div>
                </div>
                <div style="text-align:center">
                    <div class="cost-label">Cost</div>
                    <div class="cost-val">–<?= number_format($creditCost, 0) ?></div>
                </div>
                <div style="text-align:right">
                    <div class="cost-label">After submission</div>
                    <div style="font-weight:600;color:<?= ($balance >= $creditCost) ? '#22c55e' : '#ef4444' ?>">
                        <?= e(format_credits($balance - $creditCost)) ?> credits
                    </div>
                </div>
            </div>

            <button type="submit" id="lvSubmitBtn"
                    class="btn btn-primary"
                    style="width:100%"
                    <?= ($balance < $creditCost) ? 'disabled' : '' ?>
                    onclick="if(!this.disabled){this.disabled=true;this.textContent='Submitting…';this.form.submit()}">
                Generate 30-Second Ad — <?= number_format($creditCost, 0) ?> credits
            </button>
            <?php if ($balance < $creditCost): ?>
            <p class="text-muted text-sm" style="text-align:center;margin-top:8px">
                <a href="<?= BASE_URL ?>/client/buy-credits.php">Top up credits →</a>
            </p>
            <?php endif ?>
        </form>
    </div>

    <!-- ── Past jobs ── -->
    <?php if (!empty($pastJobs)): ?>
    <h2 style="margin:32px 0 16px;font-size:1.15rem;font-weight:700">Your 30s Ad Jobs</h2>

    <?php foreach ($pastJobs as $j):
        $st = match($j['status']) {
            'completed'                    => ['label' => 'Completed',  'cls' => 'completed'],
            'failed', 'refunded'           => ['label' => ucfirst($j['status']), 'cls' => 'failed'],
            'clip1','clip2','clip3',
            'stitching'                    => ['label' => match($j['status']) {
                                                  'clip1' => 'Clip 1/3', 'clip2' => 'Clip 2/3',
                                                  'clip3' => 'Clip 3/3', default => 'Stitching…'
                                              }, 'cls' => 'processing'],
            default                        => ['label' => 'Queued', 'cls' => 'queued'],
        };
        $isActive = in_array($j['status'], ['queued','clip1','clip2','clip3','stitching'], true);
        $hasThreeClipFallback = $j['final_video_url'] && str_contains($j['final_video_url'], '|');
        $clipUrls = $hasThreeClipFallback ? explode('|', $j['final_video_url']) : [];
    ?>
    <div class="card lv-job" id="lv-job-<?= $j['id'] ?>">
        <div class="card-body">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px">
                <span style="font-weight:700">#<?= $j['id'] ?></span>
                <span class="status-badge <?= $st['cls'] ?>"><?= $st['label'] ?></span>
                <span class="text-muted text-sm"><?= $j['resolution'] ?></span>
                <span class="text-muted text-sm"><?= date('d M Y H:i', strtotime($j['created_at'])) ?></span>
                <span class="text-muted text-sm"><?= number_format((float)$j['credit_cost'], 0) ?> credits</span>
            </div>

            <div class="job-prompts">
                <span class="shot-pill" title="Shot 1">① <?= e(mb_substr($j['prompt1'], 0, 55)) . (mb_strlen($j['prompt1']) > 55 ? '…' : '') ?></span>
                <span class="shot-pill" title="Shot 2">② <?= e(mb_substr($j['prompt2'], 0, 55)) . (mb_strlen($j['prompt2']) > 55 ? '…' : '') ?></span>
                <span class="shot-pill" title="Shot 3">③ <?= e(mb_substr($j['prompt3'], 0, 55)) . (mb_strlen($j['prompt3']) > 55 ? '…' : '') ?></span>
            </div>

            <?php if ($j['status'] === 'completed' && $j['final_video_url'] && !$hasThreeClipFallback): ?>
                <div style="margin-top:12px">
                    <video src="<?= e($j['final_video_url']) ?>" controls playsinline preload="none"
                           style="width:100%;max-width:640px;border-radius:var(--radius)"></video>
                    <div style="margin-top:8px">
                        <a href="<?= e($j['final_video_url']) ?>" download class="btn btn-ghost btn-sm">⬇ Download MP4</a>
                    </div>
                </div>

            <?php elseif ($hasThreeClipFallback): ?>
                <p style="color:#f59e0b;font-size:.85rem;margin-top:8px">
                    ⚠ FFmpeg unavailable — individual clips only (no stitched file):
                </p>
                <div class="clip-links">
                    <?php foreach ($clipUrls as $i => $cu): ?>
                        <a href="<?= e(trim($cu)) ?>" target="_blank">▶ Clip <?= $i + 1 ?></a>
                    <?php endforeach ?>
                </div>

            <?php elseif (in_array($j['status'], ['clip1','clip2','clip3','stitching'], true)): ?>
                <p style="font-size:.85rem;color:var(--color-primary);margin-top:8px">
                    ⏳ Generating… the cron job advances each clip automatically.
                </p>
                <?php if ($j['clip1_url'] || $j['clip2_url'] || $j['clip3_url']): ?>
                <div class="clip-links">
                    <?php if ($j['clip1_url']): ?><a href="<?= e($j['clip1_url']) ?>" target="_blank">▶ Clip 1 ready</a><?php endif ?>
                    <?php if ($j['clip2_url']): ?><a href="<?= e($j['clip2_url']) ?>" target="_blank">▶ Clip 2 ready</a><?php endif ?>
                    <?php if ($j['clip3_url']): ?><a href="<?= e($j['clip3_url']) ?>" target="_blank">▶ Clip 3 ready</a><?php endif ?>
                </div>
                <?php endif ?>

            <?php elseif ($j['status'] === 'queued'): ?>
                <p class="text-muted text-sm" style="margin-top:8px">⏳ Waiting in queue — Clip 1 will start shortly.</p>

            <?php elseif (in_array($j['status'], ['failed','refunded'], true)): ?>
                <p style="color:#ef4444;font-size:.85rem;margin-top:8px">
                    ✗ <?= e($j['error_message'] ?: 'Generation failed. Credits were refunded.') ?>
                </p>
            <?php endif ?>

            <?php if ($isActive): ?>
            <div style="margin-top:10px">
                <button class="btn btn-ghost btn-sm" onclick="refreshLvJob(<?= $j['id'] ?>, this)">
                    ↻ Refresh status
                </button>
            </div>
            <?php endif ?>
        </div>
    </div>
    <?php endforeach ?>

    <?php endif ?>

    <?php endif // tableExists ?>
</div>

<script>
function refreshLvJob(jobId, btn) {
    btn.disabled = true;
    btn.textContent = 'Refreshing…';
    fetch('<?= BASE_URL ?>/client/check_job_status.php?lv=' + jobId + '&_=' + Date.now())
        .then(r => r.json())
        .then(d => {
            if (d.status && ['completed','failed','refunded'].includes(d.status)) {
                location.reload();
            } else {
                btn.disabled = false;
                btn.textContent = '↻ ' + (d.status_label || d.status || 'Refresh status');
            }
        })
        .catch(() => { btn.disabled = false; btn.textContent = '↻ Refresh status'; });
}
<?php if ($hasActiveJobs): ?>
setTimeout(() => location.reload(), 30000);
<?php endif ?>
</script>
</body>
</html>
