<?php
declare(strict_types=1);

/**
 * inc/prompt_enhancer.php
 * LLM-powered video prompt enhancer — powered by BytePlus ModelArk Seed 2.0.
 *
 * Takes a rough user description and rewrites it into a detailed,
 * cinematic prompt optimised for Seedance 1.5 Pro / 2.0 text-to-video.
 *
 * Provider : BytePlus ModelArk /chat/completions  (same API key + base URL as video)
 * Default model: seed-2-0-lite-260228  (no custom endpoint needed — direct model ID)
 * Alternative  : seed-2-0-260228 (Pro, slower/better) or a Doubao endpoint ep-XXXXX-*
 *
 * Admin settings:
 *   llm_endpoint_id  — model ID or endpoint (default: seed-2-0-lite-260228)
 *   llm_enabled      — '1' to enable, '0' to skip enhancement
 */

// ── System prompt ─────────────────────────────────────────────────────────────
const PROMPT_ENHANCER_SYSTEM = <<<SYSPROMPT
You are an expert AI video director and prompt engineer specialising in Seedance 1.5 Pro / 2.0 text-to-video generation.

Seedance 1.5 Pro supports NATIVE audio-video generation — it can generate dialogue, voiceover, background music, and sound effects directly from the prompt. Use this when appropriate.

Your task: transform the user's rough idea into ONE richly detailed, production-ready video generation prompt.

Follow this formula:
Subject + Movement + Environment + Camera movement + Aesthetic style + Sound (if applicable)

Your output MUST include all relevant elements from the list below:

VISUALS:
- Subject & action: who/what is in the shot, what they are doing (be specific about expressions, clothing, attributes)
- Environment: location, background, lighting conditions, time of day
- Camera angle: high-angle / eye-level / low-angle / over-the-shoulder / bird's-eye
- Shot size: wide shot / medium shot / close-up / extreme close-up / bust / full-length
- Camera movement: dolly-in / dolly-out / pan / tilt / orbit / rise / Hitchcock zoom / handheld follow / static
- Aesthetic style: cinematic, luxury, Miyazaki anime, Disney 2D, Pixar 3D, documentary, dark fantasy, solarpunk, etc.
- Lighting & mood: golden hour, neon city, soft studio, dramatic side-light, Tyndall effect, etc.
- Colour palette & contrast

AUDIO (include when the scene has people speaking or when atmosphere benefits from sound):
- Dialogue: specify each character's gender/age/appearance, the exact words they say, language, emotional tone, and speech pace
  Format: [Character description]: "[exact dialogue]"
- Voiceover: describe voice type (deep/clear/warm), emotion, pace, and script
- Background music: genre, tempo, instrument, mood (e.g. "gentle nostalgic piano solo, warm and slightly melancholic")
- Sound effects: describe ambient sounds tied to the scene action

SHOT TRANSITIONS (for multi-shot prompts):
- Describe each shot as Shot 1 / Shot 2 / etc. with explicit cut instructions
- Specify reverse-angle cuts, close-up inserts, and pull-back reveals

RULES:
- Output ONLY the enhanced prompt — no headings, no bullet points, no explanations
- Write in vivid present-tense descriptive language
- Keep length between 100–250 words
- Do NOT mention text overlays, captions, or on-screen words
- Be visually specific — replace vague words like "beautiful" with precise descriptions
- For marketing/product videos: keep the brand and product prominent
- Preserve the user's original intent — do not change the subject matter

SYSPROMPT;

// ── Main function ─────────────────────────────────────────────────────────────

/**
 * Enhance a user's rough prompt into a detailed Seedance video generation prompt.
 *
 * @param  string $userPrompt  The raw prompt from the user
 * @return array{ok:bool, enhanced:string, original:string, error?:string, skipped?:bool}
 */
function enhance_video_prompt(string $userPrompt): array
{
    $apiKey   = setting('byteplus_api_key', BYTEPLUS_API_KEY) ?: BYTEPLUS_API_KEY;
    $apiBase  = rtrim(setting('byteplus_api_url', BYTEPLUS_API_URL) ?: BYTEPLUS_API_URL, '/');
    $llmModel = trim(setting('llm_endpoint_id', LLM_ENDPOINT_ID) ?: LLM_ENDPOINT_ID);
    $enabled  = setting('llm_enabled', '1');

    if ($enabled === '0') {
        return ['ok' => true, 'enhanced' => $userPrompt, 'original' => $userPrompt, 'skipped' => true];
    }

    if (!$apiKey) {
        return ['ok' => false, 'enhanced' => $userPrompt, 'original' => $userPrompt,
                'error' => 'BytePlus API key not configured.'];
    }
    if (!$llmModel) {
        return ['ok' => false, 'enhanced' => $userPrompt, 'original' => $userPrompt,
                'error' => 'LLM model not configured. Set llm_endpoint_id in admin settings (e.g. seed-2-0-lite-260228).'];
    }

    $payload = [
        'model'    => $llmModel,
        'messages' => [
            ['role' => 'system', 'content' => PROMPT_ENHANCER_SYSTEM],
            ['role' => 'user',   'content' => $userPrompt],
        ],
        'max_tokens'  => 700,
        'temperature' => 0.75,
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
        error_log("[LLM] API error $code: " . substr($body, 0, 500));
        return ['ok' => false, 'enhanced' => $userPrompt, 'original' => $userPrompt,
                'error' => 'LLM error: ' . $msg];
    }

    $enhanced = trim(
        $decoded['choices'][0]['message']['content']
        ?? $decoded['choices'][0]['text']
        ?? ''
    );

    if (empty($enhanced)) {
        return ['ok' => false, 'enhanced' => $userPrompt, 'original' => $userPrompt,
                'error' => 'LLM returned empty response. Check model ID in admin settings.'];
    }

    // Strip any markdown the model may have added
    $enhanced = preg_replace('/^#+\s*/m',      '',   $enhanced); // ## headings
    $enhanced = preg_replace('/^\*+\s*/m',     '',   $enhanced); // * bullets
    $enhanced = preg_replace('/\*\*(.*?)\*\*/s', '$1', $enhanced); // **bold**
    $enhanced = trim($enhanced);

    return [
        'ok'       => true,
        'enhanced' => $enhanced,
        'original' => $userPrompt,
    ];
}
