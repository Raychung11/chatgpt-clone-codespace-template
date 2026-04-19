<?php
declare(strict_types=1);

/**
 * inc/byteplus.php
 * BytePlus ModelArk — Text-to-Video generation provider layer.
 *
 * API: https://ark.ap-southeast.bytepluses.com/api/v3  (bytepluses.com with 's'!)
 * Docs: https://www.byteplus.com/en/docs/modelark
 *
 * Setup:
 *   1. BytePlus Console → ModelArk → Model activation → activate a video model
 *      (e.g. seedance-1-5-lite-t2v-250428 or seedance-1-0-pro-t2v)
 *   2. Online inference → Create endpoint → copy the Endpoint ID (ep-xxxxxxxx)
 *   3. API keys → Create API Key
 *   4. Set BYTEPLUS_API_KEY and BYTEPLUS_ENDPOINT_ID in config/config.php
 *      or the admin settings table.
 *
 * All public methods return a normalised result:
 *   ['ok' => true,  'task_id' => '...', 'raw' => [...]]
 *   ['ok' => false, 'error'   => '...', 'raw' => [...]]
 */

/**
 * Submit a text-to-video generation task to ModelArk.
 *
 * @param string $prompt      The video prompt / script
 * @param string $resolution  '720p' | '1080p' | '480p'
 * @param int    $duration    Duration in seconds (5 or 10)
 * @param array  $extra       Optional extra params merged into 'parameters'
 */
function byteplus_create_task(
    string $prompt,
    string $resolution = '720p',
    int    $duration   = 5,
    array  $extra      = []
): array {
    $apiKey     = setting('byteplus_api_key',     BYTEPLUS_API_KEY)     ?: BYTEPLUS_API_KEY;
    $apiBase    = rtrim(setting('byteplus_api_url', BYTEPLUS_API_URL)   ?: BYTEPLUS_API_URL, '/');

    // Endpoint priority:
    //   Seedance 2.0 specific (if configured) → Seedance 1.5 (10s/5s) → default
    // For 10s jobs, a Pro model endpoint is required — Lite silently caps at 5s.
    if ($duration >= 10) {
        $endpointId = setting('byteplus_endpoint_id_s2_10s', '') ?: '';
        if (!$endpointId) $endpointId = setting('byteplus_endpoint_id_10s', BYTEPLUS_ENDPOINT_ID_10S) ?: BYTEPLUS_ENDPOINT_ID_10S;
        if (!$endpointId) $endpointId = setting('byteplus_endpoint_id', BYTEPLUS_ENDPOINT_ID) ?: BYTEPLUS_ENDPOINT_ID;
    } else {
        $endpointId = setting('byteplus_endpoint_id_s2_5s', '') ?: '';
        if (!$endpointId) $endpointId = setting('byteplus_endpoint_id', BYTEPLUS_ENDPOINT_ID) ?: BYTEPLUS_ENDPOINT_ID;
    }

    if (!$apiKey) {
        return ['ok' => false, 'error' => 'BytePlus API key is not configured.', 'raw' => []];
    }
    if (!$endpointId) {
        return ['ok' => false, 'error' => 'BytePlus Endpoint ID is not configured.', 'raw' => []];
    }

    // ModelArk content-generation payload.
    // Per docs, parameters are embedded as --flags in the prompt text, not a separate key.
    // Format: "<prompt> --ratio 16:9 --resolution 720p --duration 5"
    $ratio = $extra['ratio'] ?? '16:9';
    $promptWithFlags = rtrim($prompt) . " --ratio {$ratio} --resolution {$resolution} --duration {$duration}";

    $payload = [
        'model'   => $endpointId,
        'content' => [
            ['type' => 'text', 'text' => $promptWithFlags],
        ],
    ];

    $result = byteplus_post($apiBase . '/contents/generations/tasks', $payload, $apiKey);

    if (!$result['ok']) {
        return $result;
    }

    // Normalise: ModelArk returns { "id": "...", "status": "queued", ... }
    $raw    = $result['raw'];
    $taskId = $raw['id'] ?? $raw['task_id'] ?? $raw['data']['id'] ?? $raw['data']['task_id'] ?? null;

    if (!$taskId) {
        return [
            'ok'    => false,
            'error' => 'API returned success but no task ID found.',
            'raw'   => $raw,
        ];
    }

    return ['ok' => true, 'task_id' => (string)$taskId, 'raw' => $raw];
}

/**
 * Query the status of an existing ModelArk video task.
 *
 * Returns:
 *   status: 'queued' | 'processing' | 'completed' | 'failed'
 *   video_url, thumbnail_url, error_message (populated when relevant)
 */
function byteplus_query_task(string $task_id): array
{
    $apiKey  = setting('byteplus_api_key', BYTEPLUS_API_KEY) ?: BYTEPLUS_API_KEY;
    $apiBase = rtrim(setting('byteplus_api_url', BYTEPLUS_API_URL) ?: BYTEPLUS_API_URL, '/');

    if (!$apiKey) {
        return ['ok' => false, 'error' => 'BytePlus API key not configured.', 'raw' => []];
    }

    // GET /contents/generations/tasks/{task_id}
    $url    = $apiBase . '/contents/generations/tasks/' . urlencode($task_id);
    $result = byteplus_get($url, $apiKey);

    if (!$result['ok']) {
        return $result;
    }

    $raw = $result['raw'];

    // ModelArk status values: queued | running | succeeded | failed
    $providerStatus = strtolower($raw['status'] ?? 'unknown');
    $status = match (true) {
        in_array($providerStatus, ['succeeded', 'success', 'completed', 'done'], true) => 'completed',
        in_array($providerStatus, ['failed', 'error', 'cancelled'], true)              => 'failed',
        in_array($providerStatus, ['running', 'processing', 'in_progress'], true)      => 'processing',
        default                                                                         => 'queued',
    };

    // ModelArk returns content as object {"video_url":"..."} OR array [{type:"video",...}]
    $videoUrl     = null;
    $thumbnailUrl = null;
    $content      = $raw['content'] ?? null;

    if (is_array($content)) {
        if (isset($content['video_url'])) {
            // Object shape: "content": {"video_url": "..."}
            $videoUrl     = $content['video_url'] ?? null;
            $thumbnailUrl = $content['thumbnail_url'] ?? $content['cover_image_url'] ?? null;
        } else {
            // Array shape: "content": [{"type":"video","video_url":"..."}]
            foreach ($content as $item) {
                if (($item['type'] ?? '') === 'video' && !$videoUrl) {
                    $videoUrl     = $item['video_url'] ?? $item['url'] ?? null;
                    $thumbnailUrl = $item['cover_image_url'] ?? $item['thumbnail_url'] ?? null;
                }
            }
        }
    }
    // Flat fallbacks
    $videoUrl     = $videoUrl     ?? $raw['video_url']     ?? $raw['output_url']    ?? null;
    $thumbnailUrl = $thumbnailUrl ?? $raw['thumbnail_url'] ?? $raw['cover_url']     ?? null;
    $errorMsg     = $raw['error']['message'] ?? $raw['error_message'] ?? $raw['message'] ?? '';

    return [
        'ok'            => true,
        'status'        => $status,
        'video_url'     => $videoUrl,
        'thumbnail_url' => $thumbnailUrl,
        'error_message' => $errorMsg,
        'raw'           => $raw,
    ];
}

/**
 * Submit an image-to-video (i2v) task to ModelArk.
 * Used for clips 2 & 3 of the 30-second ad feature: the last frame of the
 * previous clip is passed as first_frame to maintain visual continuity.
 *
 * @param string      $prompt        Shot prompt (with --dur / --rs flags appended here)
 * @param string      $firstFrameUrl Public URL of the first-frame image
 * @param string      $resolution    '720p' | '1080p'
 * @param int         $duration      Seconds (10 for 30s ad clips)
 * @param string|null $lastFrameUrl  Optional public URL of a hero ending frame
 */
function byteplus_create_i2v_task(
    string  $prompt,
    string  $firstFrameUrl,
    string  $resolution  = '1080p',
    int     $duration    = 10,
    ?string $lastFrameUrl = null
): array {
    $apiKey     = setting('byteplus_api_key',     BYTEPLUS_API_KEY)     ?: BYTEPLUS_API_KEY;
    $apiBase    = rtrim(setting('byteplus_api_url', BYTEPLUS_API_URL)   ?: BYTEPLUS_API_URL, '/');

    // Endpoint priority: Seedance 2.0 i2v → Seedance 1.5 i2v → 10s → default
    $endpointId = setting('byteplus_endpoint_id_s2_i2v', '') ?: '';
    if (!$endpointId) $endpointId = setting('byteplus_endpoint_id_i2v', BYTEPLUS_ENDPOINT_ID_I2V) ?: BYTEPLUS_ENDPOINT_ID_I2V;
    if (!$endpointId) $endpointId = setting('byteplus_endpoint_id_10s', BYTEPLUS_ENDPOINT_ID_10S) ?: BYTEPLUS_ENDPOINT_ID_10S;
    if (!$endpointId) $endpointId = setting('byteplus_endpoint_id', BYTEPLUS_ENDPOINT_ID) ?: BYTEPLUS_ENDPOINT_ID;

    if (!$apiKey)     return ['ok' => false, 'error' => 'BytePlus API key is not configured.',     'raw' => []];
    if (!$endpointId) return ['ok' => false, 'error' => 'BytePlus Endpoint ID is not configured.', 'raw' => []];

    $promptWithFlags = rtrim($prompt) . " --ratio 16:9 --resolution {$resolution} --duration {$duration}";

    $content = [];
    $content[] = [
        'type'      => 'image_url',
        'image_url' => ['url' => $firstFrameUrl, 'role' => 'first_frame'],
    ];
    if ($lastFrameUrl) {
        $content[] = [
            'type'      => 'image_url',
            'image_url' => ['url' => $lastFrameUrl, 'role' => 'last_frame'],
        ];
    }
    $content[] = ['type' => 'text', 'text' => $promptWithFlags];

    $payload = ['model' => $endpointId, 'content' => $content];

    $result = byteplus_post($apiBase . '/contents/generations/tasks', $payload, $apiKey);
    if (!$result['ok']) return $result;

    $raw    = $result['raw'];
    $taskId = $raw['id'] ?? $raw['task_id'] ?? $raw['data']['id'] ?? $raw['data']['task_id'] ?? null;
    if (!$taskId) return ['ok' => false, 'error' => 'API returned success but no task ID.', 'raw' => $raw];

    return ['ok' => true, 'task_id' => (string)$taskId, 'raw' => $raw];
}



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
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => false,
    ];
    // DNS override: set byteplus_dns_override in Admin→Settings if ark.byteplusapi.com
    // doesn't resolve on the server but you know its IP (from DoH lookup).
    $dnsOverride = function_exists('setting') ? (setting('byteplus_dns_override', '') ?: '') : '';
    if ($dnsOverride) {
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        if ($host) $opts[CURLOPT_RESOLVE] = ["$host:443:$dnsOverride"];
    }
    curl_setopt_array($ch, $opts);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    $body     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log('[BytePlus] cURL error: ' . $curlErr);
        return ['ok' => false, 'error' => 'Network error: ' . $curlErr, 'raw' => []];
    }

    $decoded = json_decode($body, true, 512, JSON_BIGINT_AS_STRING);

    if ($httpCode === 401) {
        return ['ok' => false, 'error' => 'Invalid API key.', 'raw' => $decoded ?? []];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $msg = $decoded['error']['message'] ?? $decoded['message'] ?? $decoded['error'] ?? "HTTP $httpCode";
        error_log("[BytePlus] API error $httpCode: $body");
        return ['ok' => false, 'error' => $msg, 'raw' => $decoded ?? []];
    }

    return ['ok' => true, 'raw' => $decoded ?? []];
}

/**
 * Check if a BytePlus signed CDN URL is expired (or expiring within 30 min).
 */
function byteplus_url_is_expired(string $url): bool
{
    parse_str((string)parse_url($url, PHP_URL_QUERY), $p);
    $dateStr = $p['X-Tos-Date'] ?? '';
    $expires = (int)($p['X-Tos-Expires'] ?? 0);
    if (!$dateStr || !$expires) return false;

    $dt = \DateTime::createFromFormat('Ymd\THis\Z', $dateStr, new \DateTimeZone('UTC'));
    if (!$dt) return false;

    // Treat as expired if less than 30 minutes remaining
    return time() > ($dt->getTimestamp() + $expires - 1800);
}

/**
 * Return the exact UTC DateTime when a BytePlus signed URL expires,
 * or null if the URL has no expiry params.
 */
function byteplus_url_expires_at(string $url): ?\DateTime
{
    parse_str((string)parse_url($url, PHP_URL_QUERY), $p);
    $dateStr = $p['X-Tos-Date'] ?? '';
    $expires = (int)($p['X-Tos-Expires'] ?? 0);
    if (!$dateStr || !$expires) return null;

    $dt = \DateTime::createFromFormat('Ymd\THis\Z', $dateStr, new \DateTimeZone('UTC'));
    if (!$dt) return null;

    $dt->modify('+' . $expires . ' seconds');
    return $dt;
}

/**
 * Return a human-readable string for time remaining until expiry,
 * e.g. "22h 14m" or "Expired".
 */
function byteplus_url_time_remaining(string $url): string
{
    $expiresAt = byteplus_url_expires_at($url);
    if (!$expiresAt) return '';

    $diff = $expiresAt->getTimestamp() - time();
    if ($diff <= 0) return 'Expired';

    $h = (int)floor($diff / 3600);
    $m = (int)floor(($diff % 3600) / 60);

    if ($h >= 24) return round($h / 24) . 'd remaining';
    if ($h > 0)   return $h . 'h ' . $m . 'm remaining';
    return $m . 'm remaining';
}

/**
 * If the stored CDN URL is expired, re-query BytePlus and update video_outputs.
 * Returns the fresh URL (or the original if still valid / refresh failed).
 */
function byteplus_ensure_fresh_url(int $jobId, \PDO $pdo): string
{
    $row = $pdo->prepare(
        'SELECT vj.api_task_id, vo.cdn_url
         FROM video_jobs vj
         LEFT JOIN video_outputs vo ON vo.job_id = vj.id
         WHERE vj.id = ? LIMIT 1'
    );
    $row->execute([$jobId]);
    $data = $row->fetch();

    if (!$data || !$data['api_task_id']) return $data['cdn_url'] ?? '';

    // URL still valid — return as-is
    if ($data['cdn_url'] && !byteplus_url_is_expired($data['cdn_url'])) {
        return $data['cdn_url'];
    }

    // Re-query BytePlus for a fresh URL
    $result = byteplus_query_task($data['api_task_id']);
    if ($result['ok'] && $result['status'] === 'completed' && $result['video_url']) {
        $freshUrl = $result['video_url'];
        $pdo->prepare(
            'UPDATE video_outputs SET cdn_url=?, thumbnail=COALESCE(?,thumbnail) WHERE job_id=?'
        )->execute([$freshUrl, $result['thumbnail_url'], $jobId]);
        return $freshUrl;
    }

    return $data['cdn_url'] ?? '';
}
