<?php
declare(strict_types=1);
/**
 * diagnostic.php — run from SSH: php diagnostic.php
 * Tests: DB connection, table existence, API keys, BytePlus endpoints.
 * Safe to delete after use.
 */

// ── Bootstrap ─────────────────────────────────────────────────────────────────
define('IN_DIAGNOSTIC', true);
$root = __DIR__;

if (!file_exists($root . '/config/config.php')) {
    die("ERROR: config/config.php not found. Run from the project root.\n");
}

require_once $root . '/config/config.php';
require_once $root . '/config/database.php';
require_once $root . '/inc/functions.php';

// ── Helpers ───────────────────────────────────────────────────────────────────
$cli = php_sapi_name() === 'cli';

function sep(string $title = ''): void {
    global $cli;
    if ($cli) {
        echo "\n" . str_repeat('─', 60) . "\n";
        if ($title) echo "  $title\n" . str_repeat('─', 60) . "\n";
    } else {
        echo $title ? "<h3>$title</h3>\n" : "<hr>\n";
    }
}

function ok(string $msg): void  { echo ($GLOBALS['cli'] ? "  ✓ " : "<span style='color:green'>✓ ") . $msg . ($GLOBALS['cli'] ? "\n" : "</span><br>\n"); }
function err(string $msg): void { echo ($GLOBALS['cli'] ? "  ✗ " : "<span style='color:red'>✗ ") . $msg . ($GLOBALS['cli'] ? "\n" : "</span><br>\n"); }
function inf(string $msg): void { echo ($GLOBALS['cli'] ? "    " : "<span style='color:#666'>  ") . $msg . ($GLOBALS['cli'] ? "\n" : "</span><br>\n"); }
function wrn(string $msg): void { echo ($GLOBALS['cli'] ? "  ⚠ " : "<span style='color:orange'>⚠ ") . $msg . ($GLOBALS['cli'] ? "\n" : "</span><br>\n"); }

if (!$cli) {
    // Minimal HTML wrapper for browser access
    $secret = $_GET['secret'] ?? '';
    $cron   = defined('CRON_SECRET') ? CRON_SECRET : '';
    if (!$cron || $secret !== $cron) {
        http_response_code(403);
        die('Access denied. Add ?secret=YOUR_CRON_SECRET to the URL.');
    }
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Diagnostic</title>"
       . "<style>body{font-family:monospace;padding:20px;background:#0d1117;color:#c9d1d9;line-height:1.8}h3{color:#58a6ff}</style></head><body><pre>\n";
}

// ── 1. PHP info ───────────────────────────────────────────────────────────────
sep('1. PHP Environment');
inf('PHP version  : ' . PHP_VERSION);
inf('SAPI         : ' . php_sapi_name());
inf('curl enabled : ' . (function_exists('curl_init') ? 'YES' : 'NO'));
inf('GD enabled   : ' . (function_exists('imagecreatefromstring') ? 'YES' : 'NO'));

// ── 2. Config constants ───────────────────────────────────────────────────────
sep('2. Config Constants (from env / config.php)');
$ak_c  = defined('VISION_AI_AK')    ? VISION_AI_AK    : '';
$sk_c  = defined('VISION_AI_SK')    ? VISION_AI_SK    : '';
$rk_c  = defined('OMNIHUMAN_REQ_KEY') ? OMNIHUMAN_REQ_KEY : '';
$vu_c  = defined('VISION_AI_URL')   ? VISION_AI_URL   : '';
$bu_c  = defined('BYTEPLUS_API_URL')? BYTEPLUS_API_URL : '';
$bk_c  = defined('BYTEPLUS_API_KEY')? BYTEPLUS_API_KEY : '';
$ep_c  = defined('BYTEPLUS_ENDPOINT_ID') ? BYTEPLUS_ENDPOINT_ID : '';
$llm_c = defined('LLM_ENDPOINT_ID') ? LLM_ENDPOINT_ID : '';

inf('VISION_AI_URL        : ' . ($vu_c  ?: '(empty)'));
inf('VISION_AI_AK         : ' . ($ak_c  ? substr($ak_c, 0, 8) . '...' : '(NOT SET)'));
inf('VISION_AI_SK         : ' . ($sk_c  ? '*** (' . strlen($sk_c) . ' chars)' : '(NOT SET)'));
inf('OMNIHUMAN_REQ_KEY    : ' . ($rk_c  ?: '(empty)'));
inf('BYTEPLUS_API_URL     : ' . ($bu_c  ?: '(empty)'));
inf('BYTEPLUS_API_KEY     : ' . ($bk_c  ? '*** (' . strlen($bk_c) . ' chars)' : '(NOT SET)'));
inf('BYTEPLUS_ENDPOINT_ID : ' . ($ep_c  ?: '(empty)'));
inf('LLM_ENDPOINT_ID      : ' . ($llm_c ?: '(empty)'));

// ── 3. Database ───────────────────────────────────────────────────────────────
sep('3. Database');
$pdo = null;
try {
    $pdo = db();
    $pdo->query('SELECT 1');
    ok('DB connected (' . DB_HOST . ':' . DB_PORT . '/' . DB_NAME . ')');
} catch (\Throwable $e) {
    err('DB connection FAILED: ' . $e->getMessage());
    goto api_test;
}

// Tables
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$needed = ['users','settings','video_jobs','avatar_jobs','clone_avatar_jobs'];
foreach ($needed as $t) {
    if (in_array($t, $tables)) {
        ok("Table `$t` exists");
    } else {
        err("Table `$t` MISSING — run the corresponding SQL migration");
    }
}

// users.clone_avatar_resource_id column
if (in_array('users', $tables)) {
    $cols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('clone_avatar_resource_id', $cols)) {
        ok('users.clone_avatar_resource_id column exists');
    } else {
        err('users.clone_avatar_resource_id MISSING — run sql/migrate_clone_avatar_per_user.sql');
    }
}

// DB settings
sep('4. DB Settings (Admin → Settings)');
if (in_array('settings', $tables)) {
    $rows = $pdo->query("SELECT `key`, `value` FROM `settings` WHERE `key` IN (
        'vision_ai_url','vision_ai_ak','vision_ai_sk','omnihuman_req_key',
        'byteplus_api_key','byteplus_api_url','byteplus_endpoint_id',
        'byteplus_endpoint_id_10s','llm_endpoint_id'
    ) ORDER BY `key`")->fetchAll(PDO::FETCH_KEY_PAIR);

    $keys = [
        'byteplus_api_key'       => 'secret',
        'byteplus_api_url'       => 'url',
        'byteplus_endpoint_id'   => 'id',
        'byteplus_endpoint_id_10s'=> 'id',
        'llm_endpoint_id'        => 'id',
        'omnihuman_req_key'      => 'id',
        'vision_ai_ak'           => 'secret',
        'vision_ai_sk'           => 'secret',
        'vision_ai_url'          => 'url',
    ];
    foreach ($keys as $k => $type) {
        $v = $rows[$k] ?? '';
        if ($type === 'secret') {
            $display = $v ? '*** (' . strlen($v) . ' chars) — SET' : '(empty) — NOT SET in DB';
            $v ? ok(str_pad($k, 30) . ': ' . $display) : err(str_pad($k, 30) . ': ' . $display);
        } else {
            $display = $v ?: '(empty — using constant default)';
            $v ? ok(str_pad($k, 30) . ': ' . $display) : wrn(str_pad($k, 30) . ': ' . $display);
        }
    }

    // Effective values (DB overrides constant)
    $eff_ak  = ($rows['vision_ai_ak']  ?? '') ?: $ak_c;
    $eff_sk  = ($rows['vision_ai_sk']  ?? '') ?: $sk_c;
    $eff_vu  = ($rows['vision_ai_url'] ?? '') ?: $vu_c;
    $eff_rk  = ($rows['omnihuman_req_key'] ?? '') ?: $rk_c;
    $eff_bk  = ($rows['byteplus_api_key']  ?? '') ?: $bk_c;
    $eff_bu  = ($rows['byteplus_api_url']  ?? '') ?: $bu_c;
    $eff_ep  = ($rows['byteplus_endpoint_id'] ?? '') ?: $ep_c;
    $eff_llm = ($rows['llm_endpoint_id']  ?? '') ?: $llm_c;
} else {
    $eff_ak = $ak_c; $eff_sk = $sk_c; $eff_vu = $vu_c; $eff_rk = $rk_c;
    $eff_bk = $bk_c; $eff_bu = $bu_c; $eff_ep = $ep_c; $eff_llm = $llm_c;
}

sep('5. Effective Values (what the app actually uses)');
inf('OmniHuman URL  : ' . ($eff_vu  ?: '(none)'));
inf('OmniHuman AK   : ' . ($eff_ak  ? substr($eff_ak, 0, 8) . '...' : 'NOT SET ← fix this'));
inf('OmniHuman SK   : ' . ($eff_sk  ? '*** (' . strlen($eff_sk) . ' chars)' : 'NOT SET ← fix this'));
inf('OmniHuman reqK : ' . ($eff_rk  ?: '(none)'));
inf('BytePlus URL   : ' . ($eff_bu  ?: '(none)'));
inf('BytePlus key   : ' . ($eff_bk  ? '*** (' . strlen($eff_bk) . ' chars)' : 'NOT SET'));
inf('BytePlus endpt : ' . ($eff_ep  ?: 'NOT SET ← needed for video generation'));
inf('LLM model      : ' . ($eff_llm ?: '(none)'));

// ── 4. DNS check ──────────────────────────────────────────────────────────────
api_test:
sep('6. DNS Resolution');
$hosts = [
    'cv.byteplusapi.com'                       => 'OmniHuman (required)',
    'ark.ap-southeast.bytepluses.com'           => 'ModelArk video (required)',
];
foreach ($hosts as $h => $label) {
    $ip = gethostbyname($h);
    if ($ip !== $h) {
        ok("$h → $ip  ($label)");
    } else {
        err("$h — DNS FAILED  ($label) — check server DNS / firewall");
    }
}

// ── 5. OmniHuman API test ─────────────────────────────────────────────────────
sep('7. OmniHuman API Test (CVSubmitTask with tiny image + TTS)');
if (!$eff_ak || !$eff_sk) {
    err('Skipped — AK or SK not configured. Set vision_ai_ak / vision_ai_sk in Admin → Settings.');
} else {
    require_once $root . '/inc/vision_auth.php';

    // 1×1 white JPEG base64
    $tinyJpeg = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'
              . 'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAARCAABAAEDASIAAhEBAxEB/8QAFAAB'
              . 'AAAAAAAAAAAAAAAAAAAP/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFAEBAAAAAAAAAAAAAAAAAAAAAP/'
              . 'xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwABmX/9k=';

    $payload = [
        'req_key'      => $eff_rk ?: 'realman_avatar_picture_omni15_cv',
        'image_base64' => $tinyJpeg,
        'text'         => 'Hello, this is a connectivity test.',
        'duration'     => 5,
    ];

    $url    = rtrim($eff_vu ?: 'https://cv.byteplusapi.com', '/') . '/?Action=CVSubmitTask&Version=2024-06-06';
    $body   = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $host   = parse_url($url, PHP_URL_HOST);
    $query  = 'Action=CVSubmitTask&Version=2024-06-06';

    // Use the same signing logic as the app
    if (function_exists('volcengine_v4_headers')) {
        $hdrs = volcengine_v4_headers('POST', $host, '/', $query, $body, $eff_ak, $eff_sk, 'ap-singapore-1', 'cv');
    } else {
        $hdrs = vision_signed_headers('POST', '/', $body, $eff_ak, $eff_sk);
    }
    $hLines = array_map(fn($k, $v) => "$k: $v", array_keys($hdrs), array_values($hdrs));

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $hLines,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp    = curl_exec($ch);
    $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        err("CVSubmitTask cURL error: $curlErr");
    } else {
        inf("CVSubmitTask HTTP: $code");
        $dec     = json_decode($resp ?: '', true) ?? [];
        $apiCode = (int)($dec['code'] ?? $dec['status'] ?? 0);
        $msg     = $dec['message'] ?? $dec['error'] ?? '';
        $taskId  = $dec['data']['task_id'] ?? $dec['Result']['task_id'] ?? null;
        inf('api_code : ' . ($apiCode ?: '(none)'));
        inf('message  : ' . ($msg ?: '(none)'));
        inf('task_id  : ' . ($taskId ?: '(not returned)'));
        inf('raw      : ' . substr($resp ?: '', 0, 300));

        if ($apiCode === 10000 || $taskId) {
            ok('CVSubmitTask ACCEPTED — task_id: ' . $taskId);
        } elseif ($apiCode === 50200) {
            err('req_key not supported — check omnihuman_req_key setting');
        } elseif ($apiCode === 50215) {
            err('50215 Input invalid — OmniHuman service NOT ACTIVATED on this account.');
            inf('Fix: BytePlus Console → Vision AI → Model Plaza → OmniHuman → Activate service');
        } elseif ($code === 401 || $code === 403) {
            err("HTTP $code — AK/SK rejected. Check credentials.");
        } elseif (isset($dec['ResponseMetadata']['Error'])) {
            $volErr = $dec['ResponseMetadata']['Error'];
            err('Volcengine error: ' . ($volErr['Code'] ?? '') . ' — ' . ($volErr['Message'] ?? ''));
        } else {
            err("HTTP $code: $msg");
        }
    }
}

// ── 6. BytePlus ModelArk (video generation) test ─────────────────────────────
sep('8. BytePlus ModelArk API Test (models/list probe)');
if (!$eff_bk) {
    err('Skipped — byteplus_api_key not configured. Set it in Admin → Settings.');
} else {
    $probeUrl = rtrim($eff_bu ?: 'https://ark.ap-southeast.bytepluses.com/api/v3', '/') . '/models';
    $ch = curl_init($probeUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $eff_bk,
            'Content-Type: application/json',
        ],
    ]);
    $resp    = curl_exec($ch);
    $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        err("ModelArk probe cURL error: $curlErr");
    } elseif ($code === 200) {
        ok("ModelArk reachable — HTTP 200");
        if ($eff_ep) {
            ok("byteplus_endpoint_id set: $eff_ep");
        } else {
            err('byteplus_endpoint_id is EMPTY — video generation will fail. Create an endpoint in BytePlus Console → ModelArk → Online Inference and paste the ID in Admin → Settings.');
        }
    } elseif ($code === 401) {
        err('ModelArk HTTP 401 — API key invalid or expired.');
    } else {
        $dec = json_decode($resp ?: '', true) ?? [];
        $msg = $dec['error']['message'] ?? $dec['message'] ?? "HTTP $code";
        err("ModelArk probe failed: $msg");
    }
}

// ── 7. Recent failed jobs summary ─────────────────────────────────────────────
sep('9. Recent Job Errors (last 5 failed)');
if ($pdo && in_array('avatar_jobs', $tables ?? [])) {
    $failed = $pdo->query(
        "SELECT id, status, error_message, created_at
         FROM avatar_jobs WHERE status IN ('failed','refunded')
         ORDER BY id DESC LIMIT 5"
    )->fetchAll();
    if ($failed) {
        foreach ($failed as $f) {
            inf('#' . $f['id'] . ' [' . $f['status'] . '] ' . $f['created_at'] . ': ' . ($f['error_message'] ?: '(no message)'));
        }
    } else {
        inf('No failed avatar jobs found.');
    }
} else {
    inf('Skipped — no DB or avatar_jobs table missing.');
}

sep('Done');
echo $cli ? "  Run complete. Fix any ✗ items above.\n" : '';

if (!$cli) echo '</pre></body></html>';
