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
        // Duplicate submit — silently redirect
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
                flash_success('30-Second Ad submitted! The 3 clips will generate sequentially — check back in 30–60 minutes.');
                redirect(BASE_URL . '/client/long-video.php');
            }
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[long-video] ' . $e->getMessage());
            $errors['general'] = 'Something went wrong. Please try again.';
        }
    }
}

// Regenerate submit token for next GET or failed POST
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

$statusLabels = [
    'queued'     => ['text' => 'Queued',       'cls' => 'badge-queued'],
    'clip1'      => ['text' => 'Clip 1/3',     'cls' => 'badge-processing'],
    'clip2'      => ['text' => 'Clip 2/3',     'cls' => 'badge-processing'],
    'clip3'      => ['text' => 'Clip 3/3',     'cls' => 'badge-processing'],
    'stitching'  => ['text' => 'Stitching…',   'cls' => 'badge-processing'],
    'completed'  => ['text' => 'Completed',    'cls' => 'badge-completed'],
    'failed'     => ['text' => 'Failed',       'cls' => 'badge-failed'],
    'refunded'   => ['text' => 'Refunded',     'cls' => 'badge-failed'],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>30-Second Ad | VideoSaaS</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<style>
.lv-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem}
@media(max-width:768px){.lv-grid{grid-template-columns:1fr}}
.shot-card{background:var(--card-bg,#1e2130);border:1px solid var(--border,#2a2f45);border-radius:10px;padding:1.25rem}
.shot-card h4{margin:0 0 .75rem;font-size:.9rem;color:var(--accent,#7c6dfa);text-transform:uppercase;letter-spacing:.05em}
.shot-card textarea{width:100%;min-height:130px;resize:vertical}
.lv-cost{background:var(--card-bg,#1e2130);border:1px solid var(--border,#2a2f45);border-radius:10px;padding:1rem 1.25rem;display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem}
.lv-cost .cost-num{font-size:1.4rem;font-weight:700;color:var(--accent,#7c6dfa)}
.job-row{background:var(--card-bg,#1e2130);border:1px solid var(--border,#2a2f45);border-radius:10px;padding:1.25rem;margin-bottom:1rem}
.job-meta{display:flex;gap:1rem;align-items:center;flex-wrap:wrap;margin-bottom:.75rem}
.job-prompts{font-size:.8rem;color:#9ca3af;margin-bottom:.75rem}
.job-prompts span{display:inline-block;background:#12141f;border-radius:4px;padding:2px 8px;margin:2px}
.clip-urls{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.5rem}
.clip-urls a{font-size:.8rem;padding:4px 10px;background:#12141f;border-radius:4px;color:var(--accent,#7c6dfa);text-decoration:none}
.clip-urls a:hover{text-decoration:underline}
.badge-queued{background:#374151;color:#d1d5db}
.badge-processing{background:#1e3a5f;color:#60a5fa}
.badge-completed{background:#14532d;color:#4ade80}
.badge-failed{background:#450a0a;color:#f87171}
.badge{display:inline-block;padding:2px 10px;border-radius:12px;font-size:.75rem;font-weight:600}
.final-video{margin-top:.75rem}
.final-video video{width:100%;max-width:640px;border-radius:8px}
.info-box{background:#1e2130;border:1px solid #2a2f45;border-radius:8px;padding:.875rem 1rem;margin-bottom:1.5rem;font-size:.88rem;color:#9ca3af;line-height:1.6}
.info-box strong{color:#e5e7eb}
.res-select{display:flex;gap:.75rem;margin-bottom:1rem}
.res-select label{display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.9rem}
</style>
</head>
<body>
<?php render_client_navbar($user, 'long_video'); ?>

<div class="container" style="max-width:960px;margin:0 auto;padding:2rem 1rem">

  <h1 style="margin-bottom:.5rem">30-Second Ad</h1>
  <p style="color:#9ca3af;margin-bottom:1.5rem">
    Generate a seamless 30-second video by chaining three 10-second Seedance 1.5 clips.
    Each clip picks up visually from where the last one ended.
  </p>

  <?php if (!$tableExists): ?>
  <div class="alert alert-warning">
    ⚠ The <code>long_video_jobs</code> table has not been created yet.
    Please run <code>sql/migrate_long_video.sql</code> in phpMyAdmin first.
  </div>
  <?php else: ?>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-error">
    <?php foreach ($errors as $e): ?><p><?= $e ?></p><?php endforeach ?>
  </div>
  <?php endif ?>

  <?php if ($flash = get_flash()): ?>
  <div class="alert alert-<?= $flash['type'] ?>"><?= $flash['message'] ?></div>
  <?php endif ?>

  <!-- ── Info box ── -->
  <div class="info-box">
    <strong>How it works:</strong>
    Write a short description for each of the 3 shots (10 seconds each).
    The system automatically extracts the last frame of Clip 1 and feeds it as the opening
    frame of Clip 2 for visual continuity — same for Clip 2→3. All three clips are stitched
    into a single 30-second MP4 by FFmpeg.
    <br><strong>Cost:</strong> <?= number_format($creditCost, 0) ?> credits (3 × 10s HD clips).
    <strong>Wait time:</strong> ~30–60 minutes (clips generate sequentially).
  </div>

  <!-- ── Form ── -->
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="_lv_token" value="<?= e($lvToken) ?>">

    <!-- Resolution -->
    <div style="margin-bottom:1.25rem">
      <label style="display:block;margin-bottom:.5rem;font-weight:600">Resolution</label>
      <div class="res-select">
        <label><input type="radio" name="resolution" value="1080p" checked> 1080p (HD)</label>
        <label><input type="radio" name="resolution" value="720p"> 720p</label>
      </div>
    </div>

    <!-- 3 shot prompts -->
    <div class="lv-grid" style="margin-bottom:1.25rem">
      <div class="shot-card">
        <h4>Shot 1 — Opening</h4>
        <textarea name="prompt1" placeholder="Describe your opening scene. E.g. A sleek bottle of serum on white marble, golden hour light, slow push in…"><?= e($_POST['prompt1'] ?? '') ?></textarea>
        <?php if (!empty($errors['prompt1'])): ?><p class="field-error"><?= $errors['prompt1'] ?></p><?php endif ?>
        <p style="font-size:.75rem;color:#6b7280;margin-top:.4rem">Clip 1 of 3 — t2v (text-to-video)</p>
      </div>
      <div class="shot-card">
        <h4>Shot 2 — Middle</h4>
        <textarea name="prompt2" placeholder="Continue the story. E.g. A model's hands pick up the bottle, applying a drop to glowing skin…"><?= e($_POST['prompt2'] ?? '') ?></textarea>
        <?php if (!empty($errors['prompt2'])): ?><p class="field-error"><?= $errors['prompt2'] ?></p><?php endif ?>
        <p style="font-size:.75rem;color:#6b7280;margin-top:.4rem">Clip 2 of 3 — i2v (continues from Clip 1)</p>
      </div>
      <div class="shot-card">
        <h4>Shot 3 — Closing</h4>
        <textarea name="prompt3" placeholder="Close strong. E.g. Product on shelf with tagline 'Glow differently', camera pulls back…"><?= e($_POST['prompt3'] ?? '') ?></textarea>
        <?php if (!empty($errors['prompt3'])): ?><p class="field-error"><?= $errors['prompt3'] ?></p><?php endif ?>
        <p style="font-size:.75rem;color:#6b7280;margin-top:.4rem">Clip 3 of 3 — i2v (continues from Clip 2)</p>
      </div>
    </div>

    <!-- Hero frame (optional) -->
    <div style="margin-bottom:1.25rem">
      <label style="display:block;margin-bottom:.5rem;font-weight:600">
        Hero / Ending Frame <span style="font-weight:400;color:#6b7280">(optional)</span>
      </label>
      <input type="file" name="hero_frame" accept="image/jpeg,image/png,image/webp">
      <p style="font-size:.8rem;color:#6b7280;margin-top:.25rem">
        Upload a target ending image (e.g. product packshot or brand logo).
        Clip 3 will try to end on this frame.
      </p>
    </div>

    <!-- Cost bar -->
    <div class="lv-cost">
      <div>
        <div style="font-size:.85rem;color:#9ca3af">Your balance</div>
        <div style="font-weight:600"><?= number_format($balance, 2) ?> credits</div>
      </div>
      <div style="text-align:center">
        <div style="font-size:.85rem;color:#9ca3af">Cost</div>
        <div class="cost-num">–<?= number_format($creditCost, 0) ?></div>
      </div>
      <div style="text-align:right">
        <div style="font-size:.85rem;color:#9ca3af">After</div>
        <div style="font-weight:600;color:<?= ($balance >= $creditCost) ? '#4ade80' : '#f87171' ?>">
          <?= number_format($balance - $creditCost, 2) ?> credits
        </div>
      </div>
    </div>

    <button type="submit"
            class="btn btn-primary btn-lg"
            style="width:100%"
            <?= ($balance < $creditCost) ? 'disabled' : '' ?>
            onclick="if(!this.disabled){this.disabled=true;this.innerHTML='<span style=\'opacity:.7\'>Submitting…</span>';this.form.submit()}">
      Generate 30-Second Ad — <?= number_format($creditCost, 0) ?> credits
    </button>
  </form>

  <!-- ── Past jobs ── -->
  <?php if (!empty($pastJobs)): ?>
  <h2 style="margin:2.5rem 0 1rem">Your 30s Ad Jobs</h2>

  <?php foreach ($pastJobs as $j):
      $st  = $statusLabels[$j['status']] ?? ['text' => ucfirst($j['status']), 'cls' => 'badge-queued'];
      $isActive = in_array($j['status'], ['queued','clip1','clip2','clip3','stitching'], true);
      $hasThreeClipFallback = $j['final_video_url'] && str_contains($j['final_video_url'], '|');
      $clipUrls = $hasThreeClipFallback ? explode('|', $j['final_video_url']) : [];
  ?>
  <div class="job-row" id="lv-job-<?= $j['id'] ?>">
    <div class="job-meta">
      <strong>#<?= $j['id'] ?></strong>
      <span class="badge <?= $st['cls'] ?>"><?= $st['text'] ?></span>
      <span style="color:#6b7280;font-size:.82rem"><?= $j['resolution'] ?></span>
      <span style="color:#6b7280;font-size:.82rem"><?= date('d M Y H:i', strtotime($j['created_at'])) ?></span>
      <span style="color:#6b7280;font-size:.82rem"><?= number_format((float)$j['credit_cost'], 0) ?> credits</span>
    </div>
    <div class="job-prompts">
      <span title="Shot 1">① <?= e(mb_substr($j['prompt1'], 0, 60)) . (mb_strlen($j['prompt1']) > 60 ? '…' : '') ?></span>
      <span title="Shot 2">② <?= e(mb_substr($j['prompt2'], 0, 60)) . (mb_strlen($j['prompt2']) > 60 ? '…' : '') ?></span>
      <span title="Shot 3">③ <?= e(mb_substr($j['prompt3'], 0, 60)) . (mb_strlen($j['prompt3']) > 60 ? '…' : '') ?></span>
    </div>

    <?php if ($j['status'] === 'completed' && $j['final_video_url'] && !$hasThreeClipFallback): ?>
    <div class="final-video">
      <video src="<?= e($j['final_video_url']) ?>" controls playsinline preload="none"></video>
      <div style="margin-top:.5rem">
        <a href="<?= e($j['final_video_url']) ?>" download class="btn btn-sm" style="font-size:.8rem">
          ⬇ Download MP4
        </a>
      </div>
    </div>

    <?php elseif ($hasThreeClipFallback): ?>
    <p style="color:#f59e0b;font-size:.85rem">⚠ FFmpeg not available — individual clips only:</p>
    <div class="clip-urls">
      <?php foreach ($clipUrls as $i => $cu): ?>
      <a href="<?= e(trim($cu)) ?>" target="_blank">▶ Clip <?= $i + 1 ?></a>
      <?php endforeach ?>
    </div>

    <?php elseif (in_array($j['status'], ['clip1','clip2','clip3','stitching'], true)): ?>
    <div style="font-size:.85rem;color:#60a5fa">
      ⏳ Generating… clips complete automatically every few minutes.
      <?php if ($j['clip1_url']): ?>
      <div class="clip-urls" style="margin-top:.4rem">
        <a href="<?= e($j['clip1_url']) ?>" target="_blank">▶ Clip 1 ready</a>
        <?php if ($j['clip2_url']): ?><a href="<?= e($j['clip2_url']) ?>" target="_blank">▶ Clip 2 ready</a><?php endif ?>
        <?php if ($j['clip3_url']): ?><a href="<?= e($j['clip3_url']) ?>" target="_blank">▶ Clip 3 ready</a><?php endif ?>
      </div>
      <?php endif ?>
    </div>

    <?php elseif ($j['status'] === 'queued'): ?>
    <p style="color:#6b7280;font-size:.85rem">⏳ Waiting in queue — will start shortly.</p>

    <?php elseif (in_array($j['status'], ['failed','refunded'], true)): ?>
    <p style="color:#f87171;font-size:.85rem">✗ <?= e($j['error_message'] ?: 'Generation failed.') ?></p>
    <?php endif ?>

    <?php if ($isActive): ?>
    <div style="margin-top:.5rem">
      <button class="btn btn-sm" style="font-size:.75rem"
              onclick="refreshLvJob(<?= $j['id'] ?>, this)">↻ Refresh status</button>
    </div>
    <?php endif ?>
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

// Auto-refresh every 30s if any active jobs exist
<?php if (array_filter($pastJobs, fn($j) => in_array($j['status'], ['queued','clip1','clip2','clip3','stitching'], true))): ?>
setTimeout(() => location.reload(), 30000);
<?php endif ?>
</script>

</body>
</html>
