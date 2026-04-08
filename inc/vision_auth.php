<?php
declare(strict_types=1);

/**
 * inc/vision_auth.php
 * BytePlus Vision AI — AK/SK HMAC-SHA256 request signing.
 *
 * Used for: OmniHuman, Dreamina, and other Vision AI services.
 * NOT used for ModelArk (which uses Bearer API key).
 *
 * Signing scheme (BytePlus Vision AI):
 *   Authorization: HMAC256 <AccessKeyId>:<Signature>
 *
 *   StringToSign = Method            + "\n"
 *                + base64(MD5(body)) + "\n"
 *                + ContentType       + "\n"
 *                + Date(RFC1123)     + "\n"
 *                + CanonicalHeaders  (sorted x-byteplus-* headers, each on own line)
 *                + CanonicalResource (path only, no query)
 *
 *   Signature = base64( HMAC-SHA256(SecretKey, StringToSign) )
 *
 * Reference: https://www.byteplus.com/en/docs/visual/authentication
 */

/**
 * Build the signed headers array for a Vision AI request.
 *
 * @param  string $method       HTTP method (POST / GET)
 * @param  string $path         Request path, e.g. /api/v1/ai_video_generate
 * @param  string $body         Raw JSON request body
 * @param  string $ak           Access Key ID
 * @param  string $sk           Secret Access Key
 * @param  array  $extra        Any extra x-byteplus-* headers to include in signature
 * @return array<string,string> Headers to send with the request
 */
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

    // Collect & sort x-byteplus-* headers for signing
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

    // Only path (no query string) in canonical resource
    $canonResource = '/' . ltrim(parse_url($path, PHP_URL_PATH) ?? $path, '/');

    $stringToSign = implode("\n", [
        strtoupper($method),
        $contentMd5,
        $contentType,
        $date,
        $canonHeaders . $canonResource,   // headers already end with \n if present
    ]);

    $signature = base64_encode(hash_hmac('sha256', $stringToSign, $sk, true));

    $headers = [
        'Date'          => $date,
        'Content-MD5'   => $contentMd5,
        'Content-Type'  => $contentType,
        'Authorization' => "HMAC256 $ak:$signature",
    ];

    // Merge any extra headers (non-signing ones too)
    foreach ($extra as $k => $v) {
        $headers[$k] = (string)$v;
    }

    return $headers;
}

/**
 * Execute a signed POST to BytePlus Vision AI.
 *
 * @return array{ok:bool, raw:array, error?:string}
 */
function vision_post(string $url, array $payload, string $ak, string $sk): array
{
    $path = parse_url($url, PHP_URL_PATH) ?? '/';
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $headers = vision_signed_headers('POST', $path, $body, $ak, $sk);
    $headerLines = array_map(
        fn($k, $v) => "$k: $v",
        array_keys($headers),
        array_values($headers)
    );

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

    if ($code < 200 || $code >= 300) {
        $msg = $decoded['message'] ?? $decoded['error_detail'] ?? $decoded['error'] ?? "HTTP $code";
        error_log("[VisionAI] API error $code: $resp");
        return ['ok' => false, 'error' => $msg, 'raw' => $decoded];
    }

    // BytePlus Vision AI wraps errors in code != 10000
    $apiCode = $decoded['code'] ?? $decoded['status_code'] ?? 10000;
    if ((int)$apiCode !== 10000 && (int)$apiCode !== 0) {
        $msg = $decoded['message'] ?? $decoded['error_detail'] ?? "API code $apiCode";
        return ['ok' => false, 'error' => $msg, 'raw' => $decoded];
    }

    return ['ok' => true, 'raw' => $decoded];
}
