<?php
/* ============================================================
   Claude API helper — works on Hostinger shared hosting
   (uses file_get_contents, no Composer needed)
   ============================================================ */

class Claude {
    private static string $apiUrl = 'https://api.anthropic.com/v1/messages';
    private static string $model  = 'claude-sonnet-4-6';

    /**
     * Send a prompt to Claude and return ['ok' => bool, 'text' => string, 'error' => string]
     */
    public static function generate(string $system, string $userMessage, int $maxTokens = 1500): array {
        if (!defined('ANTHROPIC_API_KEY') || !ANTHROPIC_API_KEY || str_contains(ANTHROPIC_API_KEY, 'YOUR_KEY')) {
            return ['ok' => false, 'text' => '', 'error' => 'Anthropic API key not configured. Add it to includes/config.php.'];
        }

        $payload = json_encode([
            'model'      => self::$model,
            'max_tokens' => $maxTokens,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $userMessage]],
        ]);

        $headers = implode("\r\n", [
            'Content-Type: application/json',
            'x-api-key: ' . ANTHROPIC_API_KEY,
            'anthropic-version: 2023-06-01',
        ]);

        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => $headers,
            'content'       => $payload,
            'timeout'       => 45,
            'ignore_errors' => true,
        ]]);

        $raw = @file_get_contents(self::$apiUrl, false, $ctx);
        if ($raw === false) {
            return ['ok' => false, 'text' => '', 'error' => 'Could not connect to AI service. Check server outbound access.'];
        }

        $data = json_decode($raw, true);

        if (!empty($data['content'][0]['text'])) {
            return ['ok' => true, 'text' => $data['content'][0]['text'], 'error' => ''];
        }

        $errMsg = $data['error']['message'] ?? 'Unexpected response from AI service.';
        return ['ok' => false, 'text' => '', 'error' => $errMsg];
    }
}
