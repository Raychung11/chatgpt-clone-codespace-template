<?php
declare(strict_types=1);

/**
 * inc/vision_auth.php
 * Two signing schemes for BytePlus / Volcengine Visual AI services.
 *
 * ① BytePlus HMAC256  — for visual.ap-southeast-1.byteplus.com
 *     Authorization: HMAC256 <AK>:<Signature>
 *
 * ② Volcengine V4    — for visual.volcengineapi.com
 *     Authorization: HMAC-SHA256 Credential=<AK>/date/region/service/request, ...
 *     URL format: POST https://visual.volcengineapi.com?Action=XXX&Version=YYYY
 *
 * The helper vision_post() auto-detects which scheme to use based on the URL host.
 */

// ── ① BytePlus HMAC256 signing ────────────────────────────────────────────────

function vision_signed_headers(
    string $method,
    string $path,
    string $body,
    string $ak,
    string $sk,
    array  $extra = []
): array {
    $date        = gmdate('D, d M Y H:i:s') . ' GMT';
    $contentType = 'application/json';
    $contentMd5  = base64_encode(md5($body, true));

    $toSign = [];
    foreach ($extra as $k => $v) {
        $k = strtolower(trim($k));
        if (str_starts_with($k, 'x-byteplus-')) {
            $toSign[$k] = trim((string)$v);
        }
    }
    ksort($toSign);
    $canonHeaders = '';
    foreach ($toSign as $k => $v) {
        $canonHeaders .= "$k:$v\n";
    }

    $canonResource = '/' . ltrim(parse_url($path, PHP_URL_PATH) ?? $path, '/');
    $stringToSign  = implode("\n", [
        strtoupper($method),
        $contentMd5,
        $contentType,
        $date,
        $canonHeaders . $canonResource,
    ]);

    $signature = base64_encode(hash_hmac('sha256', $stringToSign, $sk, true));

    $headers = [
        'Date'          => $date,
        'Content-MD5'   => $contentMd5,
        'Content-Type'  => $contentType,
        'Authorization' => "HMAC256 $ak:$signature",
    ];
    foreach ($extra as $k => $v) {
        $headers[$k] = (string)$v;
    }
    return $headers;
}

// ── ② Volcengine V4 signing ───────────────────────────────────────────────────

/**
 * Build signed headers for Volcengine API (visual.volcengineapi.com).
 *
 * @param string $queryString  e.g. "Action=CVSubmitTask&Version=2022-08-31"
 * @param string $region       e.g. "ap-southeast-1"
 * @param string $service      e.g. "visual"
 */
function volcengine_v4_headers(
    string $method,
    string $host,
    string $path,
    string $queryString,
    string $body,
    string $ak,
    string $sk,
    string $region  = 'ap-southeast-1',
    string $service = 'visual'
): array {
    $xDate     = gmdate('Ymd\THis\Z');
    $shortDate = substr($xDate, 0, 8);
    $bodyHash  = hash('sha256', $body);

    $signedHeaders = 'content-type;host;x-content-sha256;x-date';

    // Canonical request
    $canonRequest = implode("\n", [
        strtoupper($method),
        '/' . ltrim($path, '/'),
        $queryString,
        'content-type:application/json',
        "host:$host",
        "x-content-sha256:$bodyHash",
        "x-date:$xDate",
        '',
        $signedHeaders,
        $bodyHash,
    ]);

    // String to sign
    $credScope   = "$shortDate/$region/$service/request";
    $stringToSign = implode("\n", [
        'HMAC-SHA256',
        $xDate,
        $credScope,
        hash('sha256', $canonRequest),
    ]);

    // Signing key chain
    $kDate    = hash_hmac('sha256', $shortDate, $sk, true);
    $kRegion  = hash_hmac('sha256', $region,    $kDate,    true);
    $kService = hash_hmac('sha256', $service,   $kRegion,  true);
    $kSign    = hash_hmac('sha256', 'request',  $kService, true);
    $sig      = hash_hmac('sha256', $stringToSign, $kSign);

    return [
        'Content-Type'     => 'application/json',
        'Host'             => $host,
        'X-Date'           => $xDate,
        'X-Content-Sha256' => $bodyHash,
        'Authorization'    => "HMAC-SHA256 Credential=$ak/$credScope, SignedHeaders=$signedHeaders, Signature=$sig",
    ];
}

// ── Auto-dispatch: picks signing scheme from URL host ────────────────────────

function _is_volcengine_host(string $url): bool
{
    $host = parse_url($url, PHP_URL_HOST) ?? '';
    return str_contains($host, 'volcengineapi.com');
}

/**
 * Execute a signed POST. Auto-selects BytePlus or Volcengine signing.
 *
 * For Volcengine URLs, $url must already contain ?Action=XXX&Version=YYY.
 *
 * @return array{ok:bool, raw:array, error?:string}
 */
function vision_post(string $url, array $payload, string $ak, string $sk): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (_is_volcengine_host($url)) {
        $host   = parse_url($url, PHP_URL_HOST);
        $path   = parse_url($url, PHP_URL_PATH) ?? '/';
        $query  = parse_url($url, PHP_URL_QUERY) ?? '';
        $region = setting('vision_ai_region', 'ap-southeast-1') ?: 'ap-southeast-1';
        $hdrs   = volcengine_v4_headers('POST', $host, $path, $query, $body, $ak, $sk, $region);
    } else {
        $path = parse_url($url, PHP_URL_PATH) ?? '/';
        $hdrs = vision_signed_headers('POST', $path, $body, $ak, $sk);
    }

    $headerLines = array_map(fn($k, $v) => "$k: $v", array_keys($hdrs), array_values($hdrs));

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headerLines,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    $resp    = curl_exec($ch);
    $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log('[VisionAI] cURL error: ' . $curlErr);
        return ['ok' => false, 'error' => 'Network error: ' . $curlErr, 'raw' => []];
    }

    $decoded = json_decode($resp ?: '', true) ?? [];

    // Volcengine wraps errors in ResponseMetadata.Error
    if (isset($decoded['ResponseMetadata']['Error'])) {
        $err = $decoded['ResponseMetadata']['Error'];
        $msg = $err['Message'] ?? $err['Code'] ?? 'Volcengine API error';
        error_log("[VisionAI] Volcengine error $code: $resp");
        return ['ok' => false, 'error' => $msg, 'raw' => $decoded];
    }

    if ($code < 200 || $code >= 300) {
        $msg = $decoded['message'] ?? $decoded['error_detail'] ?? $decoded['error'] ?? "HTTP $code";
        error_log("[VisionAI] API error $code: $resp");
        return ['ok' => false, 'error' => $msg, 'raw' => $decoded];
    }

    // BytePlus wraps errors in code != 10000
    $apiCode = $decoded['code'] ?? $decoded['status_code'] ?? 10000;
    if ((int)$apiCode !== 10000 && (int)$apiCode !== 0) {
        $msg = $decoded['message'] ?? $decoded['error_detail'] ?? "API code $apiCode";
        return ['ok' => false, 'error' => $msg, 'raw' => $decoded];
    }

    return ['ok' => true, 'raw' => $decoded];
}

