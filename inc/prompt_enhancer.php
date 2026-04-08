<?php
declare(strict_types=1);

/**
 * inc/prompt_enhancer.php
 * LLM-powered video prompt enhancer.
 *
 * Takes a rough user description and rewrites it into a detailed,
 * cinematic prompt optimised for BytePlus Seedance text-to-video.
 *
 * Provider: BytePlus ModelArk /chat/completions  (same API key as video)
 *           Needs a TEXT model endpoint ID configured separately.
 *           e.g. doubao-1-5-pro-32k  or  ep-XXXXX-doubao
 *
 * Admin settings:
 *   llm_endpoint_id  — text model endpoint (e.g. doubao-1-5-pro-32k)
 *   llm_enabled      — '1' to enable, '0' to skip enhancement
 */

// ── System prompt ─────────────────────────────────────────────────────────────
const PROMPT_ENHANCER_SYSTEM = <<<SYSPROMPT
You are an expert AI video director and prompt engineer specialising in text-to-video generation.

Your task: transform a user's rough marketing video idea into a single, richly detailed video generation prompt optimised for the Seedance AI video model.

Your output must include:
- **Scene & setting**: location, environment, background details
- **Subject & product**: what/who is in the video, their actions
- **Camera work**: movement (slow pan, dolly in, orbit, static), angle (eye-level, low angle, aerial), lens feel (wide, close-up, macro)
- **Lighting**: quality, direction, colour temperature (golden hour, studio softbox, neon, etc.)
- **Visual style**: cinematic, minimalist, luxury, energetic, documentary, etc.
- **Mood & atmosphere**: emotional tone that fits the brand
- **Colour palette**: dominant colours, contrast level
- **Motion**: speed of movement, any specific actions or transitions

Rules:
- Output ONLY the enhanced prompt text — no headings, no explanations, no bullet points
- Write in vivid present-tense descriptive language
- Keep length between 80–200 words
- Do NOT mention text overlays, captions, watermarks, or on-screen words
- Make it visually specific — avoid vague words like "beautiful" or "nice"
- Tailor the style to fit a professional marketing video

SYSPROMPT;

// ── Main function ─────────────────────────────────────────────────────────────

/**
 * Enhance a user's rough prompt into a detailed video generation prompt.
 *
 * @param  string $userPrompt  The raw prompt from the user
 * @return array{ok:bool, enhanced:string, original:string, error?:string}
 */
function enhance_video_prompt(string $userPrompt): array
{
    $apiKey     = setting('byteplus_api_key', BYTEPLUS_API_KEY) ?: BYTEPLUS_API_KEY;
    $apiBase    = rtrim(setting('byteplus_api_url', BYTEPLUS_API_URL) ?: BYTEPLUS_API_URL, '/');
    $llmModel   = setting('llm_endpoint_id', LLM_ENDPOINT_ID) ?: LLM_ENDPOINT_ID;
    $enabled    = setting('llm_enabled', '1');

    // Enhancement disabled
    if ($enabled === '0') {
        return ['ok' => true, 'enhanced' => $userPrompt, 'original' => $userPrompt, 'skipped' => true];
    }

    if (!$apiKey) {
        return ['ok' => false, 'enhanced' => $userPrompt, 'original' => $userPrompt,
                'error' => 'BytePlus API key not configured.'];
    }
    if (!$llmModel) {
        return ['ok' => false, 'enhanced' => $userPrompt, 'original' => $userPrompt,
                'error' => 'LLM model endpoint not configured. Set llm_endpoint_id in admin settings.'];
    }

    $payload = [
        'model'    => $llmModel,
        'messages' => [
            ['role' => 'system', 'content' => PROMPT_ENHANCER_SYSTEM],
            ['role' => 'user',   'content' => $userPrompt],
        ],
        'max_tokens'  => 600,
        'temperature' => 0.72,
    ];

    $ch = curl_init($apiBase . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $body    = curl_exec($ch);
    $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log('[LLM] cURL error: ' . $curlErr);
        return ['ok' => false, 'enhanced' => $userPrompt, 'original' => $userPrompt,
                'error' => 'Network error: ' . $curlErr];
    }

    $decoded = json_decode($body ?: '', true) ?? [];

    if ($code < 200 || $code >= 300) {
        $msg = $decoded['error']['message'] ?? $decoded['message'] ?? "HTTP $code";
        error_log("[LLM] API error $code: $body");
        return ['ok' => false, 'enhanced' => $userPrompt, 'original' => $userPrompt,
                'error' => 'LLM error: ' . $msg];
    }

    // OpenAI-compatible response: choices[0].message.content
    $enhanced = trim(
        $decoded['choices'][0]['message']['content']
        ?? $decoded['choices'][0]['text']
        ?? ''
    );

    if (empty($enhanced)) {
        return ['ok' => false, 'enhanced' => $userPrompt, 'original' => $userPrompt,
                'error' => 'LLM returned empty response.'];
    }

    // Sanitise: strip any markdown the LLM sneaked in
    $enhanced = preg_replace('/^#+\s*/m', '', $enhanced);          // ## headings
    $enhanced = preg_replace('/^\*+\s*/m', '', $enhanced);         // * bullets
    $enhanced = preg_replace('/\*\*(.*?)\*\*/s', '$1', $enhanced); // **bold**
    $enhanced = trim($enhanced);

    return [
        'ok'       => true,
        'enhanced' => $enhanced,
        'original' => $userPrompt,
    ];
}
