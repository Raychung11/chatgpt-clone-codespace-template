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
require_once __DIR__ . '/../inc/prompt_enhancer.php';
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

        // ── Step 1: LLM prompt enhancement ───────────────────────────────────
        $enhanceResult   = enhance_video_prompt($prompt);
        $enhancedPrompt  = $enhanceResult['enhanced'];   // falls back to original on error
        $enhanceSkipped  = $enhanceResult['skipped'] ?? false;
        $enhanceFailed   = !$enhanceResult['ok'];

        // Create job row first (status = queued)
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO `video_jobs`
                 (`user_id`,`pricing_rule_id`,`prompt`,`enhanced_prompt`,`resolution`,`duration`,`credit_cost`,`status`)
                 VALUES (?,?,?,?,?,?,?,"queued")'
            );
            $stmt->execute([
                $uid,
                $rule['id'],
                $prompt,                          // original user prompt
                $enhanceFailed ? null : ($enhanceSkipped ? null : $enhancedPrompt),
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
                // ── Step 2: Submit enhanced (or original) prompt to BytePlus ─
                $apiResult = byteplus_create_task(
                    $enhancedPrompt,              // LLM-enhanced prompt sent to API
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

                $successMsg = 'Video generation started!';
                if (!$enhanceFailed && !$enhanceSkipped) {
                    $successMsg .= ' Your prompt was enhanced by AI for better results.';
                }
                flash_success($successMsg . ' Track progress in your history.');
                redirect(BASE_URL . '/client/history.php');
            }
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[generate] ' . $e->getMessage());
            $errors['general'] = 'Something went wrong. Please try again.';
        }
    }
}

// Refresh balance for display
$balance = wallet_balance($uid);

// ── Debug action: video generation connectivity diagnostics ──────────────────
// Must be before any HTML output so json_response() can set headers.
if (($_GET['_action'] ?? '') === 'debug_test') {
    csrf_verify();
    $apiKey     = setting('byteplus_api_key',  BYTEPLUS_API_KEY)  ?: BYTEPLUS_API_KEY;
    $apiBase    = rtrim(setting('byteplus_api_url', BYTEPLUS_API_URL) ?: BYTEPLUS_API_URL, '/');
    $endpointId = setting('byteplus_endpoint_id', BYTEPLUS_ENDPOINT_ID) ?: BYTEPLUS_ENDPOINT_ID;
    $results    = [];

    // 1. Config
    $dbUrl = setting('byteplus_api_url', '');
    $results['config'] = [
        'active_url'     => $apiBase ?: 'NOT SET',
        'source'         => $dbUrl ? "DB: $dbUrl" : 'constant: ' . BYTEPLUS_API_URL,
        'api_key'        => $apiKey ? ('set (' . substr($apiKey, 0, 6) . '…)') : 'NOT SET',
        'endpoint_id'    => $endpointId ?: 'NOT SET',
        'BYTEPLUS_API_URL_const' => BYTEPLUS_API_URL,
    ];

    // 2. DNS for configured host + alternatives
    $host = parse_url($apiBase, PHP_URL_HOST) ?: '';
    $ip   = $host ? gethostbyname($host) : '';
    $results['dns'] = [
        'hostname' => $host,
        'resolved' => ($ip && $ip !== $host) ? "✓ OK → $ip" : '✗ FAILED (not in server DNS)',
    ];
    $altHosts = [
        'ark.byteplusapi.com',
        'ark.ap-southeast.byteplus.com',
        'ark.ap-southeast-1.byteplus.com',
        'ark.volcengineapi.com',
    ];
    $dnsAlts = [];
    foreach ($altHosts as $h) {
        $r = gethostbyname($h);
        $dnsAlts[$h] = ($r !== $h) ? "✓ resolves → $r" : '✗ no DNS';
    }
    $results['dns_alternatives'] = $dnsAlts;

    // 3. DoH lookup for ark.byteplusapi.com (the likely correct URL)
    $dohHost = 'ark.byteplusapi.com';
    $dohIps  = [];
    foreach ([
        'Google'     => 'https://dns.google/resolve?name=' . urlencode($dohHost) . '&type=A',
        'Cloudflare' => 'https://cloudflare-dns.com/dns-query?name=' . urlencode($dohHost) . '&type=A',
    ] as $prov => $dohUrl) {
        $dohCh = curl_init($dohUrl);
        curl_setopt_array($dohCh, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>6,
            CURLOPT_HTTPHEADER=>['accept: application/dns-json'], CURLOPT_SSL_VERIFYPEER=>true]);
        $dohR = json_decode(curl_exec($dohCh) ?: '', true);
        curl_close($dohCh);
        $pIps = [];
        foreach (($dohR['Answer'] ?? []) as $rec) {
            if (($rec['type'] ?? 0) === 1) { $pIps[] = $rec['data']; $dohIps[] = $rec['data']; }
        }
        $results['doh_lookup'][$prov] = $pIps ? ('✓ ' . implode(', ', $pIps)) : '✗ no record';
    }
    $results['doh_lookup']['hostname'] = $dohHost;
    $dohIps = array_unique($dohIps);

    // 4. TCP connect test to configured host
    if ($host) {
        $fp = @fsockopen('ssl://' . $host, 443, $errno, $errstr, 8);
        $results['tcp_connect'] = $fp ? 'OK — TCP+TLS port 443 succeeded' : "FAILED (errno=$errno): $errstr";
        if ($fp) fclose($fp);
    }

    // 5. API probe — simple POST to /contents/generations/tasks
    if ($apiKey && $apiBase) {
        $probeUrl  = $apiBase . '/contents/generations/tasks';
        $probeBody = json_encode(['model' => $endpointId ?: 'test', 'content' => [['type' => 'text', 'text' => 'test']]]);
        $ch = curl_init($probeUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $probeBody,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer $apiKey", 'Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $dnsOverride = setting('byteplus_dns_override', '') ?: '';
        if ($dnsOverride && $host) {
            curl_setopt($ch, CURLOPT_RESOLVE, ["$host:443:$dnsOverride"]);
        }
        $resp    = curl_exec($ch);
        $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        $decoded = json_decode($resp ?: '', true) ?? [];
        $errMsg  = $decoded['error']['message'] ?? $decoded['message'] ?? '';
        $results['api_probe'] = [
            'url'        => $probeUrl,
            'http_code'  => $code ?: 0,
            'curl_error' => $curlErr ?: 'none',
            'response'   => $decoded ?: ($resp ? substr($resp, 0, 300) : '(empty)'),
            'diagnosis'  => match(true) {
                (bool)$curlErr && str_contains($curlErr, 'resolve') => 'DNS FAILURE — change byteplus_api_url in Admin→Settings',
                (bool)$curlErr && str_contains($curlErr, 'timed out') => 'TIMEOUT — host unreachable',
                (bool)$curlErr             => "cURL error: $curlErr",
                $code === 401              => '✓ REACHABLE — API key invalid (401)',
                $code === 400 || $code === 422 => '✓ REACHABLE — bad payload rejected (auth + connectivity OK)',
                $code === 200              => '✓ WORKING',
                $code >= 500               => "Server error $code",
                default                    => "HTTP $code: $errMsg",
            },
        ];

        // If DoH found an IP for ark.byteplusapi.com and server DNS doesn't resolve it, try via IP
        if ($dohIps && gethostbyname('ark.byteplusapi.com') === 'ark.byteplusapi.com') {
            $ipTest = $dohIps[0];
            $testUrl = 'https://ark.byteplusapi.com/api/v3/contents/generations/tasks';
            $ch2 = curl_init($testUrl);
            curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10,
                CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$probeBody,
                CURLOPT_HTTPHEADER=>["Authorization: Bearer $apiKey", 'Content-Type: application/json'],
                CURLOPT_SSL_VERIFYPEER=>true,
                CURLOPT_RESOLVE=>["ark.byteplusapi.com:443:$ipTest"]]);
            $r2 = curl_exec($ch2);
            $c2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            $e2 = curl_error($ch2);
            curl_close($ch2);
            $d2 = json_decode($r2 ?: '', true) ?? [];
            $results['ark_ip_test'] = [
                'ip'         => $ipTest,
                'http_code'  => $c2 ?: 0,
                'curl_error' => $e2 ?: 'none',
                'response'   => $d2,
                'diagnosis'  => match(true) {
                    (bool)$e2            => "FAILED: $e2",
                    $c2 === 401          => '✓ REACHABLE via IP — API key issue (set byteplus_api_url=https://ark.byteplusapi.com/api/v3)',
                    $c2 === 400 || $c2 === 422 => '✓ REACHABLE via IP — set byteplus_api_url=https://ark.byteplusapi.com/api/v3',
                    $c2 === 200          => '✓ WORKING via IP',
                    default              => "HTTP $c2",
                },
            ];
        }
    } else {
        $results['api_probe'] = 'SKIPPED — API key or URL not configured';
    }

    json_response(['ok' => true, 'debug' => $results]);
}
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
        /* AI Enhance */
        .enhance-btn {
            background: linear-gradient(135deg, #6c47ff, #a855f7);
            border: none;
            color: #fff;
            border-radius: var(--radius);
            padding: 7px 18px;
            font-size: .83rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: opacity .15s;
        }
        .enhance-btn:hover   { opacity: .88; }
        .enhance-btn:disabled { opacity: .5; cursor: not-allowed; }
        .enhance-box {
            background: linear-gradient(135deg, rgba(108,71,255,.07), rgba(168,85,247,.07));
            border: 1px solid rgba(108,71,255,.35);
            border-radius: var(--radius);
            padding: 14px 16px;
            margin-top: 12px;
            display: none;
        }
        .enhance-box .enhance-label {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--color-primary);
            font-weight: 700;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .enhance-box .enhance-text {
            font-size: .88rem;
            line-height: 1.65;
            color: var(--color-text);
            white-space: pre-wrap;
        }
        .enhance-actions { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
        .enhance-actions button {
            background: var(--color-surface2);
            border: 1px solid var(--color-border);
            border-radius: 6px;
            color: var(--color-text);
            padding: 5px 14px;
            font-size: .8rem;
            cursor: pointer;
            transition: all .15s;
        }
        .enhance-actions button:hover { border-color: var(--color-primary); }
        .enhance-spinner {
            display: inline-block;
            width: 14px; height: 14px;
            border: 2px solid rgba(255,255,255,.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            vertical-align: middle;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
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
        <div style="display:flex;gap:10px;align-items:center">
            <button onclick="toggleGenDebug()" class="btn btn-ghost btn-sm" style="font-size:.75rem;opacity:.7">🔧 Debug</button>
            <span class="navbar-wallet">⚡ <?= e(format_credits($balance)) ?> credits</span>
        </div>
    </div>

    <!-- ── Debug panel ──────────────────────────────────────────────────────── -->
    <div id="genDebugWrap" style="display:none;margin-bottom:20px">
        <div style="background:#0f172a;border:1px solid #334155;border-radius:8px;padding:14px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                <span style="color:#facc15;font-weight:700;font-size:.85rem">🔧 Video Generation Debug Console</span>
                <div style="display:flex;gap:8px">
                    <button onclick="runGenDebug()" class="btn btn-sm" style="background:#1e40af;color:#fff;font-size:.75rem">▶ Run Diagnostics</button>
                    <button onclick="document.getElementById('genDebugLog').innerHTML=''" class="btn btn-ghost btn-sm" style="font-size:.75rem">Clear</button>
                </div>
            </div>
            <div id="genDebugLog" style="font-family:monospace;font-size:.75rem;line-height:1.7;max-height:380px;overflow-y:auto;color:#94a3b8">
                Click "Run Diagnostics" to test video generation configuration and connectivity.
            </div>
        </div>
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
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <?php if (!empty($templates)): ?>
                        <button type="button" onclick="toggleTemplates()"
                                class="btn btn-ghost btn-sm" id="tplToggle">
                            📋 Use Template
                        </button>
                    <?php endif; ?>
                    <button type="button" class="enhance-btn" id="enhanceBtn" onclick="enhancePrompt()">
                        ✨ Enhance with AI
                    </button>
                </div>
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
                          oninput="updateCharCount(this)"><?= e($_POST['prompt'] ?? $_GET['prompt'] ?? '') ?></textarea>
                <div id="charCount">0 / 2000 characters</div>
            </div>

            <!-- AI-enhanced prompt preview box -->
            <div class="enhance-box" id="enhanceBox">
                <div class="enhance-label">
                    <span>✨</span> AI-Enhanced Prompt
                    <span id="enhanceBadge" style="background:rgba(34,197,94,.15);color:#22c55e;font-size:.68rem;padding:2px 8px;border-radius:99px">
                        Ready to generate
                    </span>
                </div>
                <div class="enhance-text" id="enhanceText"></div>
                <div class="enhance-actions">
                    <button type="button" onclick="useEnhanced()" id="useEnhancedBtn">
                        ✅ Use this prompt
                    </button>
                    <button type="button" onclick="editEnhanced()">
                        ✏️ Edit before using
                    </button>
                    <button type="button" onclick="dismissEnhance()" style="color:var(--color-muted)">
                        ✕ Dismiss
                    </button>
                </div>
            </div>

            <!-- Prompt writing tips -->
            <details style="margin-top:8px">
                <summary style="cursor:pointer;font-size:.85rem;color:var(--color-muted)">
                    💡 Tips for better prompts
                </summary>
                <ul style="margin:10px 0 0 20px;font-size:.85rem;color:var(--color-muted);line-height:1.8">
                    <li>Just describe your idea simply — <strong style="color:var(--color-text)">AI will enhance it</strong> into a full cinematic prompt</li>
                    <li>Mention the <strong style="color:var(--color-text)">product, brand, and target audience</strong></li>
                    <li>Specify <strong style="color:var(--color-text)">mood or style</strong> if you have one (luxury, energetic, minimal…)</li>
                    <li>Click <strong style="color:var(--color-primary)">✨ Enhance with AI</strong> to preview the improved prompt</li>
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

// Prevent double submission — show spinner while LLM enhances + submits
document.getElementById('genForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="enhance-spinner"></span> Generating…';
});

// ── AI Prompt Enhancement ────────────────────────────────────────────────────
const CSRF = <?= json_encode($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>;
let enhancedText = '';

async function enhancePrompt() {
    const ta   = document.getElementById('prompt');
    const raw  = ta.value.trim();
    const btn  = document.getElementById('enhanceBtn');
    const box  = document.getElementById('enhanceBox');

    if (raw.length < 5) {
        ta.focus();
        ta.style.borderColor = 'var(--color-danger)';
        setTimeout(() => ta.style.borderColor = '', 1500);
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="enhance-spinner"></span> Enhancing…';
    box.style.display = 'none';

    try {
        const fd = new FormData();
        fd.append('prompt', raw);
        fd.append(<?= json_encode(CSRF_TOKEN_NAME) ?>, CSRF);

        const res  = await fetch('<?= BASE_URL ?>/client/enhance_prompt.php', {
            method: 'POST', body: fd
        });
        const data = await res.json();

        if (data.ok && data.enhanced && data.enhanced !== raw) {
            enhancedText = data.enhanced;
            document.getElementById('enhanceText').textContent = data.enhanced;
            box.style.display = 'block';
            box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else if (!data.ok) {
            showEnhanceError(data.error || 'Enhancement unavailable. Configure LLM endpoint in admin settings.');
        } else {
            showEnhanceError('No changes — try a more descriptive prompt.');
        }
    } catch (e) {
        showEnhanceError('Network error. Check your connection.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '✨ Enhance with AI';
    }
}

function showEnhanceError(msg) {
    const box = document.getElementById('enhanceBox');
    document.getElementById('enhanceText').textContent = '⚠ ' + msg;
    document.getElementById('enhanceBadge').textContent = 'Not available';
    document.getElementById('enhanceBadge').style.cssText = 'background:rgba(239,68,68,.15);color:#ef4444;font-size:.68rem;padding:2px 8px;border-radius:99px';
    document.getElementById('useEnhancedBtn').style.display = 'none';
    box.style.display = 'block';
}

function useEnhanced() {
    if (!enhancedText) return;
    const ta = document.getElementById('prompt');
    ta.value = enhancedText;
    updateCharCount(ta);
    document.getElementById('enhanceBox').style.display = 'none';
    ta.style.borderColor = 'var(--color-primary)';
    setTimeout(() => ta.style.borderColor = '', 1800);
    ta.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function editEnhanced() {
    if (!enhancedText) return;
    const ta = document.getElementById('prompt');
    ta.value = enhancedText;
    updateCharCount(ta);
    document.getElementById('enhanceBox').style.display = 'none';
    ta.focus();
}

function dismissEnhance() {
    document.getElementById('enhanceBox').style.display = 'none';
    enhancedText = '';
    document.getElementById('enhanceBadge').textContent = 'Ready to generate';
    document.getElementById('enhanceBadge').style.cssText = 'background:rgba(34,197,94,.15);color:#22c55e;font-size:.68rem;padding:2px 8px;border-radius:99px';
    document.getElementById('useEnhancedBtn').style.display = '';
}

// ── Video Generation Debug Panel ──────────────────────────────────────────────
const genCsrf = <?= json_encode($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>;

function toggleGenDebug() {
    const w = document.getElementById('genDebugWrap');
    w.style.display = w.style.display === 'none' ? 'block' : 'none';
}

function glog(msg, color) {
    const log = document.getElementById('genDebugLog');
    const line = document.createElement('div');
    line.style.color = color || '#94a3b8';
    line.textContent = `[${new Date().toLocaleTimeString()}] ${msg}`;
    log.appendChild(line);
    log.scrollTop = log.scrollHeight;
}

async function runGenDebug() {
    document.getElementById('genDebugLog').innerHTML = '';
    glog('Running diagnostics…', '#facc15');
    try {
        const resp = await fetch('<?= BASE_URL ?>/client/generate.php?_action=debug_test', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': genCsrf },
            body: '<?= CSRF_TOKEN_NAME ?>=<?= csrf_token() ?>',
        });
        if (!resp.ok) { glog(`HTTP ${resp.status}: ${(await resp.text()).slice(0,200)}`, '#ef4444'); return; }
        const data = await resp.json();
        const d = data.debug;

        // Config
        glog('── Configuration ─────────────────────', '#facc15');
        Object.entries(d.config).forEach(([k,v]) => {
            const bad = String(v).includes('NOT SET');
            glog(`  ${k}: ${v}`, bad ? '#ef4444' : '#a8e063');
        });

        // DNS
        glog('── DNS Resolution ────────────────────', '#facc15');
        const dnsOk = d.dns.resolved.startsWith('✓');
        glog(`  ${d.dns.hostname}: ${d.dns.resolved}`, dnsOk ? '#a8e063' : '#ef4444');
        glog('  Alternatives:', '#64b5f6');
        Object.entries(d.dns_alternatives).forEach(([h,r]) => {
            const ok = r.startsWith('✓');
            glog(`    ${h}: ${r}`, ok ? '#a8e063' : '#475569');
            if (ok) glog(`    ↑ Set this as byteplus_api_url base in Admin→Settings`, '#fbbf24');
        });

        // DoH
        if (d.doh_lookup) {
            glog('── DoH DNS (ark.byteplusapi.com) ────', '#facc15');
            glog(`  hostname: ${d.doh_lookup.hostname}`, '#64b5f6');
            ['Google','Cloudflare'].forEach(p => {
                if (d.doh_lookup[p]) glog(`  ${p}: ${d.doh_lookup[p]}`, d.doh_lookup[p].startsWith('✓') ? '#a8e063' : '#475569');
            });
        }

        // TCP
        if (d.tcp_connect) {
            glog('── TCP+TLS Connect ───────────────────', '#facc15');
            glog('  ' + d.tcp_connect, d.tcp_connect.startsWith('OK') ? '#a8e063' : '#ef4444');
        }

        // API probe
        glog('── API Probe ─────────────────────────', '#facc15');
        if (typeof d.api_probe === 'string') {
            glog('  ' + d.api_probe, '#f59e0b');
        } else {
            glog(`  URL: ${d.api_probe.url}`, '#94a3b8');
            glog(`  HTTP: ${d.api_probe.http_code || 'N/A'}`, d.api_probe.http_code > 0 ? '#a8e063' : '#ef4444');
            const diagOk = d.api_probe.diagnosis.startsWith('✓');
            glog(`  Diagnosis: ${d.api_probe.diagnosis}`, diagOk ? '#a8e063' : '#ef4444');
            if (d.api_probe.curl_error !== 'none') glog(`  cURL: ${d.api_probe.curl_error}`, '#ef4444');
            if (d.api_probe.response && typeof d.api_probe.response === 'object') {
                glog('  Response:', '#94a3b8');
                JSON.stringify(d.api_probe.response, null, 2).split('\n').forEach(l => glog('    '+l, '#475569'));
            }
            if (d.api_probe.diagnosis.includes('DNS FAILURE')) {
                glog('  → Update byteplus_api_url in Admin→Settings to a resolvable host', '#fbbf24');
                glog('  → Likely fix: https://ark.byteplusapi.com/api/v3', '#fbbf24');
            }
        }

        // IP test via DoH
        if (d.ark_ip_test) {
            glog('── ark.byteplusapi.com via DoH IP ───', '#facc15');
            const t = d.ark_ip_test;
            const tok = t.diagnosis.startsWith('✓');
            glog(`  IP: ${t.ip}  HTTP: ${t.http_code || 'failed'}`, '#64b5f6');
            glog(`  ${t.diagnosis}`, tok ? '#a8e063' : '#ef4444');
            if (tok) {
                glog('  → Set byteplus_api_url = https://ark.byteplusapi.com/api/v3 in Admin→Settings', '#fbbf24');
                glog('  → Optionally set byteplus_dns_override = ' + t.ip + ' in Admin→Settings', '#fbbf24');
            }
            if (t.response && typeof t.response === 'object') {
                glog('  Response:', '#94a3b8');
                JSON.stringify(t.response, null, 2).split('\n').forEach(l => glog('    '+l, '#475569'));
            }
        }

        glog('── Done ──────────────────────────────', '#facc15');
    } catch(e) {
        glog('Fetch error: ' + e.message, '#ef4444');
    }
}
</script>
</body>
</html>
