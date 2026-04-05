<?php
declare(strict_types=1);

/**
 * inc/byteplus.php
 * BytePlus / Bytedance Video Generation API provider layer.
 *
 * All API calls are isolated here so they can be swapped out without
 * touching business logic. Each method returns a normalised result array:
 *
 *   ['ok' => true,  'task_id' => '...', 'raw' => [...]]
 *   ['ok' => false, 'error'   => '...', 'raw' => [...]]
 */

/**
 * Submit a text-to-video generation task.
 *
 * @param string $prompt      The video prompt / script
 * @param string $resolution  e.g. '720p' or '1080p'
 * @param int    $duration    Duration in seconds (5 or 10)
 * @param array  $extra       Optional extra params to merge into the payload
 */
function byteplus_create_task(
    string $prompt,
    string $resolution = '720p',
    int    $duration   = 5,
    array  $extra      = []
): array {
    $apiKey  = setting('byteplus_api_key', BYTEPLUS_API_KEY);
    $apiBase = rtrim(setting('byteplus_api_url', BYTEPLUS_API_URL), '/');

    if (!$apiKey) {
        return ['ok' => false, 'error' => 'BytePlus API key is not configured.', 'raw' => []];
    }

    // Build payload — adjust keys to match the live BytePlus API spec
    $payload = array_merge([
        'prompt'     => $prompt,
        'resolution' => $resolution,
        'duration'   => $duration,
        'model'      => 'bytedance_v1.5',
    ], $extra);

    $result = byteplus_post($apiBase . '/task/submit', $payload, $apiKey);

    if (!$result['ok']) {
        return $result;
    }

    // Normalise: extract task_id from known response shapes
    $raw    = $result['raw'];
    $taskId = $raw['data']['task_id']
           ?? $raw['task_id']
           ?? $raw['data']['id']
           ?? $raw['id']
           ?? null;

    if (!$taskId) {
        return [
            'ok'    => false,
            'error' => 'API returned success but no task_id found.',
            'raw'   => $raw,
        ];
    }

    return ['ok' => true, 'task_id' => (string)$taskId, 'raw' => $raw];
}

/**
 * Query the status of an existing task.
 *
 * Returns a normalised array:
 *   status: 'queued' | 'processing' | 'completed' | 'failed'
 *   video_url, thumbnail_url, error_message (when relevant)
 */
function byteplus_query_task(string $task_id): array
{
    $apiKey  = setting('byteplus_api_key', BYTEPLUS_API_KEY);
    $apiBase = rtrim(setting('byteplus_api_url', BYTEPLUS_API_URL), '/');

    if (!$apiKey) {
        return ['ok' => false, 'error' => 'BytePlus API key not configured.', 'raw' => []];
    }

    $url = $apiBase . '/task/query?task_id=' . urlencode($task_id);
    $result = byteplus_get($url, $apiKey);

    if (!$result['ok']) {
        return $result;
    }

    $raw    = $result['raw'];
    $data   = $raw['data'] ?? $raw;

    // Map provider status → internal status
    $providerStatus = strtolower($data['status'] ?? 'unknown');
    $status = match (true) {
        in_array($providerStatus, ['succeeded','success','completed','done'], true) => 'completed',
        in_array($providerStatus, ['failed','error','cancelled'], true)             => 'failed',
        in_array($providerStatus, ['processing','running','in_progress'], true)     => 'processing',
        default                                                                     => 'queued',
    };

    return [
        'ok'            => true,
        'status'        => $status,
        'video_url'     => $data['video_url']     ?? $data['output_url'] ?? null,
        'thumbnail_url' => $data['thumbnail_url'] ?? $data['cover_url']  ?? null,
        'error_message' => $data['error_message'] ?? $data['message']    ?? '',
        'raw'           => $raw,
    ];
}

// ── Low-level HTTP helpers ────────────────────────────────────────────────────

function byteplus_post(string $url, array $payload, string $apiKey): array
{
    return byteplus_request('POST', $url, $payload, $apiKey);
}

function byteplus_get(string $url, string $apiKey): array
{
    return byteplus_request('GET', $url, [], $apiKey);
}

function byteplus_request(string $method, string $url, array $payload, string $apiKey): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $body     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log('[BytePlus] cURL error: ' . $curlErr);
        return ['ok' => false, 'error' => 'Network error: ' . $curlErr, 'raw' => []];
    }

    $decoded = json_decode($body, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        $msg = $decoded['message'] ?? $decoded['error'] ?? "HTTP $httpCode";
        error_log("[BytePlus] API error $httpCode: $body");
        return ['ok' => false, 'error' => $msg, 'raw' => $decoded ?? []];
    }

    // Some APIs return a top-level success/error flag
    if (isset($decoded['code']) && $decoded['code'] !== 0 && $decoded['code'] !== 200) {
        $msg = $decoded['message'] ?? $decoded['msg'] ?? 'API returned error code ' . $decoded['code'];
        return ['ok' => false, 'error' => $msg, 'raw' => $decoded];
    }

    return ['ok' => true, 'raw' => $decoded ?? []];
}
