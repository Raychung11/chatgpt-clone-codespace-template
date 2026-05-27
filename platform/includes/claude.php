<?php
/* ============================================================
   Claude API helper — works on Hostinger shared hosting
   (uses file_get_contents, no Composer needed)
   ============================================================ */

class Claude {
    private static string $apiUrl = 'https://api.anthropic.com/v1/messages';
    private static string $model  = 'claude-sonnet-4-6';

    /**
     * Generate a response, optionally loading + saving AI memory.
     * Pass $moduleKey + $userId to enable persistent memory.
     */
    public static function generate(
        string $system,
        string $userMessage,
        int    $maxTokens = 1500,
        string $moduleKey = '',
        int    $userId    = 0
    ): array {
        if (!defined('ANTHROPIC_API_KEY') || !ANTHROPIC_API_KEY || str_contains(ANTHROPIC_API_KEY, 'YOUR_KEY')) {
            return ['ok' => false, 'text' => '', 'error' => 'Anthropic API key not configured. Add it to includes/config.php.'];
        }

        // Load conversation history if memory is enabled
        $history = [];
        if ($moduleKey && $userId > 0) {
            AIMemory::ensureTable();
            $history = AIMemory::load($userId, $moduleKey);
        }

        // Build messages: history + current user message
        $messages   = $history;
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $result = self::callApi($system, $messages, $maxTokens);

        // Save this exchange to memory
        if ($result['ok'] && $moduleKey && $userId > 0) {
            AIMemory::save($userId, $moduleKey, 'user',      $userMessage);
            AIMemory::save($userId, $moduleKey, 'assistant', $result['text']);
        }

        return $result;
    }

    /** Send a pre-built messages array (used for custom conversation flows) */
    public static function generateWithHistory(string $system, array $messages, int $maxTokens = 1500): array {
        if (!defined('ANTHROPIC_API_KEY') || !ANTHROPIC_API_KEY || str_contains(ANTHROPIC_API_KEY, 'YOUR_KEY')) {
            return ['ok' => false, 'text' => '', 'error' => 'Anthropic API key not configured.'];
        }
        return self::callApi($system, $messages, $maxTokens);
    }

    private static function callApi(string $system, array $messages, int $maxTokens): array {
        $payload = json_encode([
            'model'      => self::$model,
            'max_tokens' => $maxTokens,
            'system'     => $system,
            'messages'   => $messages,
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
