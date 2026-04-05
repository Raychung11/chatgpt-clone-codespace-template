<?php
declare(strict_types=1);

/**
 * client/generate.php
 * Video generation page with prompt builder, template picker,
 * credit cost preview, and job submission.
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

// ── Load pricing rules ────────────────────────────────────────────────────────
$rules = $pdo->query(
    'SELECT * FROM `generation_pricing_rules` WHERE `is_active` = 1 ORDER BY `credit_cost`'
)->fetchAll();

// ── Load prompt templates ─────────────────────────────────────────────────────
$templates = $pdo->query(
    'SELECT `id`,`name`,`category`,`template`
     FROM `prompt_templates` WHERE `is_active` = 1 ORDER BY `category`,`name`'
)->fetchAll();

// Group templates by category
$templatesByCategory = [];
foreach ($templates as $t) {
    $templatesByCategory[$t['category'] ?: 'General'][] = $t;
}

$balance = wallet_balance($uid);
$errors  = [];

// ── Handle POST submission ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $prompt  = trim($_POST['prompt']   ?? '');
    $rule_id = (int)($_POST['rule_id'] ?? 0);

    // Validate
    if (mb_strlen($prompt) < 10) {
        $errors['prompt'] = 'Prompt must be at least 10 characters.';
    }
    if (mb_strlen($prompt) > 2000) {
        $errors['prompt'] = 'Prompt cannot exceed 2000 characters.';
    }

    $rule = null;
    foreach ($rules as $r) {
        if ((int)$r['id'] === $rule_id) { $rule = $r; break; }
    }
    if (!$rule) {
        $errors['rule'] = 'Please select a valid video quality option.';
    }

    if (empty($errors)) {
        $creditCost = (float)$rule['credit_cost'];

        // Pre-flight balance check (wallet_deduct also checks, but catch it early for UX)
        if ($balance < $creditCost) {
            $errors['balance'] = sprintf(
                'Insufficient credits. You need %.2f but only have %.2f. <a href="%s">Buy more credits →</a>',
                $creditCost, $balance,
                BASE_URL . '/client/buy-credits.php'
            );
        }
    }

    if (empty($errors)) {
        $creditCost = (float)$rule['credit_cost'];

        // Create job row first (status = queued)
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO `video_jobs`
                 (`user_id`,`pricing_rule_id`,`prompt`,`resolution`,`duration`,`credit_cost`,`status`)
                 VALUES (?,?,?,?,?,?,"queued")'
            );
            $stmt->execute([
                $uid,
                $rule['id'],
                $prompt,
                $rule['resolution'],
                (int)$rule['duration'],
                $creditCost,
            ]);
            $jobId = (int)$pdo->lastInsertId();

            // Deduct credits atomically
            $deduct = wallet_deduct(
                $uid, $creditCost, 'deduction',
                'video_job', $jobId,
                'Video generation job #' . $jobId
            );

            if (!$deduct['ok']) {
                $pdo->rollBack();
                $errors['balance'] = $deduct['error'];
            } else {
                // Submit to BytePlus API
                $apiResult = byteplus_create_task(
                    $prompt,
                    $rule['resolution'] ?? '720p',
                    (int)($rule['duration'] ?? 5)
                );

                if ($apiResult['ok']) {
                    $pdo->prepare(
                        'UPDATE `video_jobs`
                         SET `status`="processing", `api_task_id`=?, `api_response`=?, `started_at`=NOW()
                         WHERE `id`=?'
                    )->execute([
                        $apiResult['task_id'],
                        json_encode($apiResult['raw']),
                        $jobId,
                    ]);
                } else {
                    // API failed — mark job failed and refund
                    $pdo->prepare(
                        'UPDATE `video_jobs`
                         SET `status`="failed", `error_message`=?
                         WHERE `id`=?'
                    )->execute([$apiResult['error'], $jobId]);

                    wallet_refund($uid, $creditCost, 'video_job', $jobId,
                        'Refund: API submission failed for job #' . $jobId);

                    $pdo->prepare(
                        'UPDATE `video_jobs` SET `status`="refunded", `refunded_at`=NOW() WHERE `id`=?'
                    )->execute([$jobId]);

                    $pdo->commit();
                    flash_error('Video generation failed: ' . $apiResult['error'] . ' Your credits have been refunded.');
                    redirect(BASE_URL . '/client/generate.php');
                }

                $pdo->commit();
                log_activity('user', $uid, 'video_job_created', 'Job #' . $jobId . ' submitted');

                flash_success('Video generation started! We\'ll process it shortly. You can track progress in your history.');
                redirect(BASE_URL . '/client/history.php');
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('[generate] ' . $e->getMessage());
            $errors['general'] = 'Something went wrong. Please try again.';
        }
    }
}

// Refresh balance for display
$balance = wallet_balance($uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Video — <?= e(setting('site_name','VideoSaaS')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/main.css">
    <style>
        .rule-card {
            cursor: pointer;
            transition: border-color .2s, box-shadow .2s;
        }
        .rule-card.selected {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(108,71,255,.3);
        }
        .tpl-btn {
            background: var(--color-surface2);
            border: 1px solid var(--color-border);
            border-radius: var(--radius);
            padding: 6px 14px;
            font-size: .82rem;
            color: var(--color-muted);
            cursor: pointer;
            transition: all .15s;
        }
        .tpl-btn:hover { border-color: var(--color-primary); color: var(--color-text); }
        #charCount { font-size: .8rem; color: var(--color-muted); text-align: right; margin-top: 4px; }
    </style>
</head>
<body>
<?php render_client_navbar($user, 'generate'); ?>

<div class="container main-content">
    <?= render_flash() ?>

    <div class="page-header">
        <div>
            <h1 class="page-title">Generate Video</h1>
            <p class="page-sub">Turn your marketing brief into an AI video</p>
        </div>
        <span class="navbar-wallet">⚡ <?= e(format_credits($balance)) ?> credits</span>
    </div>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert--error"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors['balance'])): ?>
        <div class="alert alert--error"><?= $errors['balance'] /* intentional: contains safe HTML link */ ?></div>
    <?php endif; ?>

    <form method="POST" id="genForm">
        <?= csrf_field() ?>

        <!-- ── STEP 1: Quality ─────────────────────────────────────────── -->
        <div class="card mb-4">
            <div class="card-header">
                <span class="card-title">Step 1 — Choose Video Quality</span>
            </div>

            <?php if (!empty($errors['rule'])): ?>
                <div class="alert alert--error"><?= e($errors['rule']) ?></div>
            <?php endif; ?>

            <?php if (empty($rules)): ?>
                <div class="alert alert--warning">No pricing rules configured. Please contact the admin.</div>
            <?php else: ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px">
                    <?php foreach ($rules as $i => $r): ?>
                        <label style="cursor:pointer">
                            <input type="radio" name="rule_id" value="<?= (int)$r['id'] ?>"
                                   style="display:none" class="rule-radio"
                                   <?= ($i === 0) ? 'checked' : '' ?>
                                   data-cost="<?= e($r['credit_cost']) ?>">
                            <div class="card rule-card <?= ($i === 0) ? 'selected' : '' ?>">
                                <div style="font-weight:700"><?= e($r['name']) ?></div>
                                <div class="text-muted text-sm mt-1">
                                    <?= e($r['resolution']) ?> · <?= (int)$r['duration'] ?>s
                                </div>
                                <div style="font-size:1.3rem;font-weight:900;color:var(--color-accent);margin-top:8px">
                                    <?= e(format_credits((float)$r['credit_cost'])) ?>
                                    <span style="font-size:.75rem;color:var(--color-muted);font-weight:400">credits</span>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── STEP 2: Prompt ──────────────────────────────────────────── -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-between align-center" style="flex-wrap:wrap;gap:10px">
                <span class="card-title">Step 2 — Write Your Prompt</span>
                <?php if (!empty($templates)): ?>
                    <button type="button" onclick="toggleTemplates()"
                            class="btn btn-ghost btn-sm" id="tplToggle">
                        📋 Use Template
                    </button>
                <?php endif; ?>
            </div>

            <!-- Template picker (hidden by default) -->
            <?php if (!empty($templatesByCategory)): ?>
                <div id="tplPanel" style="display:none;margin-bottom:16px;padding:16px;background:var(--color-surface2);border-radius:var(--radius)">
                    <?php foreach ($templatesByCategory as $cat => $catTpls): ?>
                        <div style="margin-bottom:10px">
                            <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;color:var(--color-muted);font-weight:700;margin-bottom:6px">
                                <?= e($cat) ?>
                            </div>
                            <div style="display:flex;flex-wrap:wrap;gap:8px">
                                <?php foreach ($catTpls as $tpl): ?>
                                    <button type="button" class="tpl-btn"
                                            onclick="applyTemplate(<?= htmlspecialchars(json_encode($tpl['template']), ENT_QUOTES) ?>)">
                                        <?= e($tpl['name']) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors['prompt'])): ?>
                <div class="alert alert--error"><?= e($errors['prompt']) ?></div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label" for="prompt">Video Prompt</label>
                <textarea id="prompt" name="prompt" class="form-control"
                          rows="6" maxlength="2000" required
                          placeholder="Describe your marketing video in detail.
Example: A vibrant product launch video for a new energy drink. Show the can against a neon-lit city background, with fast cuts and upbeat music. Target audience: 18-30 year olds. Tone: energetic, bold."
                          oninput="updateCharCount(this)"><?= e($_POST['prompt'] ?? '') ?></textarea>
                <div id="charCount">0 / 2000 characters</div>
            </div>

            <!-- Prompt writing tips -->
            <details style="margin-top:8px">
                <summary style="cursor:pointer;font-size:.85rem;color:var(--color-muted)">
                    💡 Tips for better prompts
                </summary>
                <ul style="margin:10px 0 0 20px;font-size:.85rem;color:var(--color-muted);line-height:1.8">
                    <li>Describe the <strong style="color:var(--color-text)">scene, product, and mood</strong> clearly</li>
                    <li>Mention your <strong style="color:var(--color-text)">target audience</strong></li>
                    <li>Specify <strong style="color:var(--color-text)">visual style</strong> (cinematic, minimalist, bold, etc.)</li>
                    <li>Include <strong style="color:var(--color-text)">brand colours or key messages</strong> if relevant</li>
                    <li>Be specific — more detail = better results</li>
                </ul>
            </details>
        </div>

        <!-- ── STEP 3: Review & Submit ─────────────────────────────────── -->
        <div class="card">
            <div class="card-header"><span class="card-title">Step 3 — Review & Generate</span></div>

            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
                <div>
                    <div class="text-muted text-sm">Cost</div>
                    <div id="costDisplay" style="font-size:1.6rem;font-weight:900;color:var(--color-accent)">
                        <?= e(format_credits((float)($rules[0]['credit_cost'] ?? 0))) ?>
                        <span style="font-size:.85rem;color:var(--color-muted);font-weight:400">credits</span>
                    </div>
                    <div style="font-size:.8rem;color:var(--color-muted)">
                        Balance after: <span id="balanceAfter">—</span>
                    </div>
                </div>
                <div>
                    <button type="submit" id="submitBtn" class="btn btn-primary btn-lg"
                            <?= $balance <= 0 ? 'disabled' : '' ?>>
                        ⚡ Generate Video
                    </button>
                    <?php if ($balance <= 0): ?>
                        <div class="text-sm text-muted mt-1">
                            <a href="<?= BASE_URL ?>/client/buy-credits.php">Buy credits first →</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </form>
</div>

<script>
const userBalance = <?= json_encode($balance) ?>;

// Track selected rule cost
let selectedCost = <?= json_encode((float)($rules[0]['credit_cost'] ?? 0)) ?>;

document.querySelectorAll('.rule-radio').forEach(radio => {
    radio.addEventListener('change', () => {
        // Deselect all cards
        document.querySelectorAll('.rule-card').forEach(c => c.classList.remove('selected'));
        // Select this card
        radio.nextElementSibling.classList.add('selected');

        selectedCost = parseFloat(radio.dataset.cost);
        updateCostDisplay();
    });
    // Init on first rule
    if (radio.checked) {
        selectedCost = parseFloat(radio.dataset.cost);
        updateCostDisplay();
    }
});

function updateCostDisplay() {
    const costEl    = document.getElementById('costDisplay');
    const afterEl   = document.getElementById('balanceAfter');
    const submitBtn = document.getElementById('submitBtn');

    costEl.innerHTML = selectedCost.toFixed(2) +
        ' <span style="font-size:.85rem;color:var(--color-muted);font-weight:400">credits</span>';

    const after = userBalance - selectedCost;
    afterEl.textContent = after.toFixed(2) + ' credits';
    afterEl.style.color = after < 0 ? 'var(--color-danger)' : 'var(--color-muted)';

    submitBtn.disabled = (after < 0);
}

function updateCharCount(el) {
    document.getElementById('charCount').textContent = el.value.length + ' / 2000 characters';
}

// Init char count
updateCharCount(document.getElementById('prompt'));
updateCostDisplay();

// Template panel toggle
function toggleTemplates() {
    const panel = document.getElementById('tplPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

// Apply template to prompt textarea
function applyTemplate(template) {
    const ta = document.getElementById('prompt');
    ta.value = template;
    updateCharCount(ta);
    // Close template panel
    const panel = document.getElementById('tplPanel');
    if (panel) panel.style.display = 'none';
    ta.focus();
}

// Prevent double submission
document.getElementById('genForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = 'Submitting…';
});
</script>
</body>
</html>
