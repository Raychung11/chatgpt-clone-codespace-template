<?php
declare(strict_types=1);

/**
 * inc/clone_avatar_api.php
 * BytePlus Clone Avatar — Generate Clone Avatar Video (web integration).
 *
 * Reuses the same AK/SK and Volcengine V4 signing as OmniHuman (vision_auth.php).
 *   req_key  : realman_avatar_creation_task
 *   submit   : POST cv.byteplusapi.com/?Action=CVSubmitTask&Version=2024-06-06
 *   query    : POST cv.byteplusapi.com/?Action=CVGetResult&Version=2024-06-06
 *
 * Docs: https://docs.byteplus.com/en/docs/byteplus-vision/generate-clone-avatar-video
 */

require_once __DIR__ . '/vision_auth.php';

define('CLONE_AVATAR_REQ_KEY', 'realman_avatar_creation_task');

// ── Submit a generation task ──────────────────────────────────────────────────

/**
 * @return array{ok:bool, task_id?:string, raw:array, error?:string}
 */
function clone_avatar_create_task(string $resourceId, string $audioUrl): array
{
    [$ak, $sk, $apiBase] = _clone_avatar_creds();

    if (!$ak || !$sk) {
        return ['ok' => false, 'error' => 'Vision AI AK/SK not configured.', 'raw' => []];
    }

    $url     = _clone_avatar_url($apiBase, 'submit');
    $payload = [
        'req_key'     => CLONE_AVATAR_REQ_KEY,
        'resource_id' => $resourceId,
        'audio_url'   => $audioUrl,
    ];

    error_log('[CloneAvatar] submit url=' . $url . ' resource_id=' . $resourceId);
    $result = vision_post($url, $payload, $ak, $sk);

    if (!$result['ok']) {
        error_log('[CloneAvatar] submit http/sign failed: ' . json_encode($result['raw']));
        return $result;
    }

    $raw = $result['raw'];
    if (($raw['code'] ?? null) !== 10000) {
        $code = $raw['code'] ?? '?';
        $msg  = $raw['message'] ?? 'Submit failed';
        error_log('[CloneAvatar] non-10000 submit: ' . json_encode($raw));
        return ['ok' => false, 'error' => "(code $code) $msg", 'raw' => $raw];
    }

    $taskId = $raw['data']['task_id'] ?? null;
    if (!$taskId) {
        error_log('[CloneAvatar] no task_id in submit response: ' . json_encode($raw));
        return ['ok' => false, 'error' => 'No task_id in response', 'raw' => $raw];
    }

    error_log('[CloneAvatar] submitted ok task_id=' . $taskId);
    return ['ok' => true, 'task_id' => (string)$taskId, 'raw' => $raw];
}

// ── Query task status ─────────────────────────────────────────────────────────

/**
 * @return array{ok:bool, status:string, video_url?:string, error?:string, raw:array}
 *   status: 'queued' | 'processing' | 'completed' | 'failed'
 */
function clone_avatar_query_task(string $taskId): array
{
    [$ak, $sk, $apiBase] = _clone_avatar_creds();

    if (!$ak || !$sk) {
        return ['ok' => false, 'status' => 'failed', 'error' => 'AK/SK not configured.', 'raw' => []];
    }

    $url    = _clone_avatar_url($apiBase, 'query');
    $result = vision_post($url, [
        'req_key' => CLONE_AVATAR_REQ_KEY,
        'task_id' => $taskId,
    ], $ak, $sk);

    if (!$result['ok']) {
        return array_merge(['status' => 'failed'], $result);
    }

    $raw = $result['raw'];
    if (($raw['code'] ?? null) !== 10000) {
        return [
            'ok'     => false,
            'status' => 'failed',
            'error'  => $raw['message'] ?? 'Query failed',
            'raw'    => $raw,
        ];
    }

    $data           = $raw['data'] ?? [];
    $providerStatus = strtolower($data['status'] ?? 'unknown');
    $status = match (true) {
        $providerStatus === 'done'                                                       => 'completed',
        in_array($providerStatus, ['not_found', 'expired', 'failed', 'error'], true)    => 'failed',
        $providerStatus === 'generating'                                                 => 'processing',
        default                                                                          => 'queued',
    };

    // Per BytePlus v2024-06-06: video_url sits at data.video_url (sibling of resp_data)
    $videoUrl = $data['video_url'] ?? null;

    // Also decode resp_data (serialised JSON string) as fallback
    $respData = [];
    $rawRd    = $data['resp_data'] ?? null;
    if (is_string($rawRd) && $rawRd !== '') {
        $respData = json_decode($rawRd, true) ?? [];
    }
    if (!$videoUrl) {
        $videoUrl = $respData['video_url'] ?? null;
    }

    $errorMsg = $respData['msg'] ?? $data['error_detail'] ?? $data['message'] ?? '';

    return [
        'ok'        => true,
        'status'    => $status,
        'video_url' => $videoUrl,
        'error'     => $errorMsg,
        'raw'       => $raw,
    ];
}

// ── Internal helpers ──────────────────────────────────────────────────────────

/** @return array{string,string,string} [ak, sk, apiBase] */
function _clone_avatar_creds(): array
{
    $ak      = (setting('vision_ai_ak',  VISION_AI_AK)  ?: VISION_AI_AK)  ?: '';
    $sk      = (setting('vision_ai_sk',  VISION_AI_SK)  ?: VISION_AI_SK)  ?: '';
    $apiBase = (setting('vision_ai_url', VISION_AI_URL) ?: VISION_AI_URL) ?: VISION_AI_URL;
    return [$ak, $sk, $apiBase];
}

function _clone_avatar_url(string $apiBase, string $type): string
{
    $base    = rtrim($apiBase, '/');
    $action  = $type === 'submit' ? 'CVSubmitTask' : 'CVGetResult';
    $host    = parse_url($apiBase, PHP_URL_HOST) ?? '';
    $version = str_contains($host, 'byteplusapi.com') ? '2024-06-06' : '2022-08-31';
    return $base . '/?Action=' . $action . '&Version=' . $version;
}
