-- ─────────────────────────────────────────────────────────────────────────────
-- SilverDeals MY — Phase 2 Migration
-- Run after 001_schema.sql
-- ─────────────────────────────────────────────────────────────────────────────

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Admin notes (for member view) ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admin_notes (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL COMMENT 'Member being noted',
    admin_id    BIGINT UNSIGNED NOT NULL COMMENT 'Admin who wrote note',
    note        TEXT            NOT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id  (user_id),
    INDEX idx_admin_id (admin_id),
    FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
-- ─── End Phase 2 Migration ───────────────────────────────────────────────────
