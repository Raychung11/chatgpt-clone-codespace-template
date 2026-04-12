<?php
declare(strict_types=1);

/**
 * inc/omnihuman.php
 * BytePlus Vision AI — OmniHuman 1.5 / 1.0 API client.
 *
 * OmniHuman generates a talking-head video from:
 *   - A portrait image (JPG/PNG of a person)
 *   - An audio clip (MP3/WAV) OR a text string (TTS)
 *
 * Console: https://console.byteplus.com/ai/overview
 *   Vision AI → Model Plaza → OmniHuman → Activate service
 *
 * Auth: AK/SK (NOT ModelArk Bearer key) — see inc/vision_auth.php
 */

require_once __DIR__ . '/vision_auth.php';

// ── Submit a new OmniHuman generation task ─────────────────────────────────────

/**
 * @param string      $imageBase64  Base64-encoded portrait image (no data: prefix)
 * @param string|null $audioBase64  Base64-encoded audio file (MP3/WAV), or null
 * @param string|null $ttsText      Text for TTS voice (used when no audioBase64)
 * @param array       $extra        Extra params e.g. ['resolution'=>'720p','duration'=>10]
 * @return array{ok:bool, task_id?:string, raw:array, error?:string}
 */
function omnihuman_create_task(
    string  $imageBase64,
    ?string $audioBase64 = null,
    ?string $ttsText     = null,
    array   $extra       = []
): array {
    [$ak, $sk, $apiBase, $reqKey] = _omnihuman_creds();

    if (!$ak || !$sk) {
        return ['ok' => false, 'error' => 'Vision AI AK/SK not configured.', 'raw' => []];
    }

    $payload = array_merge([
        'req_key'      => $reqKey,
        'image_base64' => $imageBase64,
    ], $extra);

    if ($audioBase64) {
        $payload['audio_base64'] = $audioBase64;
    } elseif ($ttsText) {
        $payload['text'] = $ttsText;
    } else {
        return ['ok' => false, 'error' => 'Provide either an audio file or TTS text.', 'raw' => []];
    }

    $url    = _omnihuman_url($apiBase, 'generate');
    $result = vision_post($url, $payload, $ak, $sk);

    if (!$result['ok']) {
        return $result;
    }

    $raw    = $result['raw'];
    // BytePlus:    {"code":10000,"data":{"task_id":"xxx"}}
    // Volcengine:  {"Result":{"task_id":"xxx"}} or {"data":{"task_id":"xxx"}}
    $taskId = $raw['Result']['task_id']
           ?? $raw['data']['task_id']
           ?? $raw['task_id']
           ?? $raw['Result']['id']
           ?? $raw['data']['id']
           ?? null;

    if (!$taskId) {
        return ['ok' => false, 'error' => 'No task_id in response.', 'raw' => $raw];
    }

    return ['ok' => true, 'task_id' => (string)$taskId, 'raw' => $raw];
}

// ── Query task status ─────────────────────────────────────────────────────────

/**
 * @return array{ok:bool, status:string, video_url?:string, error?:string, raw:array}
 *   status: 'queued' | 'processing' | 'completed' | 'failed'
 */
function omnihuman_query_task(string $taskId): array
{
    [$ak, $sk, $apiBase, $reqKey] = _omnihuman_creds();

    if (!$ak || !$sk) {
        return ['ok' => false, 'status' => 'failed', 'error' => 'AK/SK not configured.', 'raw' => []];
    }

    $url    = _omnihuman_url($apiBase, 'query');
    $result = vision_post($url, ['req_key' => $reqKey, 'task_id' => $taskId], $ak, $sk);

    if (!$result['ok']) {
        return array_merge(['status' => 'failed'], $result);
    }

    $raw  = $result['raw'];
    // Volcengine wraps in Result, BytePlus in data
    $data = $raw['Result'] ?? $raw['data'] ?? $raw;

    // Normalise status
    $providerStatus = strtolower($data['status'] ?? $data['Status'] ?? 'unknown');
    $status = match (true) {
        in_array($providerStatus, ['done', 'succeed', 'succeeded', 'success', 'completed'], true) => 'completed',
        in_array($providerStatus, ['failed', 'error', 'cancelled', 'fail'],                 true) => 'failed',
        in_array($providerStatus, ['running', 'processing', 'in_progress', 'generating'],   true) => 'processing',
        default                                                                                    => 'queued',
    };

    // Extract video URL — covers both BytePlus and Volcengine response shapes
    $videoUrl = $data['video_url']
             ?? $data['VideoUrl']
             ?? $data['url']
             ?? $data['result']['video_url']
             ?? $data['output']['video_url']
             ?? null;

    $errorMsg = $data['error_detail'] ?? $data['error'] ?? $data['message'] ?? $data['ErrorMessage'] ?? '';

    return [
        'ok'        => true,
        'status'    => $status,
        'video_url' => $videoUrl,
        'error'     => $errorMsg,
        'raw'       => $raw,
    ];
}

// ── Internal helpers ──────────────────────────────────────────────────────────

/** @return array{string,string,string,string} [ak, sk, apiBase, reqKey] */
function _omnihuman_creds(): array
{
    $ak      = (setting('vision_ai_ak',  VISION_AI_AK)  ?: VISION_AI_AK)  ?: '';
    $sk      = (setting('vision_ai_sk',  VISION_AI_SK)  ?: VISION_AI_SK)  ?: '';
    $apiBase = (setting('vision_ai_url', VISION_AI_URL) ?: VISION_AI_URL) ?: VISION_AI_URL;
    $reqKey  = (setting('omnihuman_req_key', OMNIHUMAN_REQ_KEY) ?: OMNIHUMAN_REQ_KEY) ?: OMNIHUMAN_REQ_KEY;
    return [$ak, $sk, $apiBase, $reqKey];
}

/**
 * Build the correct request URL for generate or query.
 *
 * BytePlus REST:   https://visual.ap-southeast-1.byteplus.com/api/v1/ai_video_{generate|query}
 * Volcengine V4:   https://visual.volcengineapi.com?Action=CVSubmitTask&Version=2022-08-31
 *                  https://visual.volcengineapi.com?Action=CVGetResult&Version=2022-08-31
 *
 * Action names are configurable via settings (omnihuman_action_generate / omnihuman_action_query)
 * so the user can correct them if Volcengine changes the API.
 */
function _omnihuman_url(string $apiBase, string $type): string
{
    $base = rtrim($apiBase, '/');

    if (_is_volcengine_host($apiBase)) {
        // cv.byteplusapi.com uses Version=2024-06-06; visual.volcengineapi.com uses 2022-08-31
        $host    = parse_url($apiBase, PHP_URL_HOST) ?? '';
        $version = str_contains($host, 'byteplusapi.com') ? '2024-06-06' : '2022-08-31';

        if ($type === 'generate') {
            $action = setting('omnihuman_action_generate', 'CVSubmitTask') ?: 'CVSubmitTask';
        } else {
            $action = setting('omnihuman_action_query', 'CVGetResult') ?: 'CVGetResult';
        }
        // Docs show: https://cv.byteplusapi.com/?Action=CVSubmitTask&Version=2024-06-06
        return $base . '/?Action=' . $action . '&Version=' . $version;
    }

    // BytePlus legacy REST paths
    return $type === 'generate'
        ? $base . '/api/v1/ai_video_generate'
        : $base . '/api/v1/ai_video_query';
}

/**
 * Read a file and return its base64-encoded content.
 * Returns null on failure.
 */
function omnihuman_file_to_base64(string $filePath): ?string
{
    if (!is_file($filePath) || !is_readable($filePath)) return null;
    $bytes = file_get_contents($filePath);
    return $bytes !== false ? base64_encode($bytes) : null;
}
