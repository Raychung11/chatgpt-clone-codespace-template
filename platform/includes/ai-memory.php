<?php
/* ============================================================
   AI Memory — persists conversation context per user per module
   ============================================================ */

class AIMemory {

    const MAX_EXCHANGES  = 6;    // last 6 user↔assistant pairs
    const MAX_CONTENT    = 2000; // chars stored per message

    public static function ensureTable(): void {
        DB::query("CREATE TABLE IF NOT EXISTS ai_memory (
            id         BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id    INT NOT NULL,
            module_key VARCHAR(100) NOT NULL,
            role       ENUM('user','assistant') NOT NULL,
            content    TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_module (user_id, module_key)
        )");
    }

    /** Returns messages array ready to pass to Claude as conversation history */
    public static function load(int $userId, string $moduleKey): array {
        try {
            $rows = DB::fetchAll(
                "SELECT role, content FROM (
                     SELECT id, role, content FROM ai_memory
                     WHERE user_id = ? AND module_key = ?
                     ORDER BY id DESC
                     LIMIT " . (self::MAX_EXCHANGES * 2) . "
                 ) sub ORDER BY id ASC",
                [$userId, $moduleKey]
            );
            return $rows ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function save(int $userId, string $moduleKey, string $role, string $content): void {
        try {
            DB::insert('ai_memory', [
                'user_id'    => $userId,
                'module_key' => $moduleKey,
                'role'       => $role,
                'content'    => mb_substr($content, 0, self::MAX_CONTENT),
            ]);
            // Prune old entries — keep only last MAX_EXCHANGES*2 rows
            DB::query(
                "DELETE FROM ai_memory WHERE user_id=? AND module_key=? AND id NOT IN (
                     SELECT id FROM (
                         SELECT id FROM ai_memory WHERE user_id=? AND module_key=?
                         ORDER BY id DESC LIMIT " . (self::MAX_EXCHANGES * 2) . "
                     ) keep
                 )",
                [$userId, $moduleKey, $userId, $moduleKey]
            );
        } catch (Throwable $e) { /* silent */ }
    }

    public static function clear(int $userId, string $moduleKey = ''): void {
        try {
            if ($moduleKey) {
                DB::query('DELETE FROM ai_memory WHERE user_id=? AND module_key=?', [$userId, $moduleKey]);
            } else {
                DB::query('DELETE FROM ai_memory WHERE user_id=?', [$userId]);
            }
        } catch (Throwable $e) { /* silent */ }
    }

    /** Number of saved exchanges (pairs) for a user+module */
    public static function count(int $userId, string $moduleKey): int {
        try {
            $n = (int)(DB::fetch(
                'SELECT COUNT(*) AS n FROM ai_memory WHERE user_id=? AND module_key=?',
                [$userId, $moduleKey]
            )['n'] ?? 0);
            return (int)ceil($n / 2);
        } catch (Throwable $e) {
            return 0;
        }
    }
}
