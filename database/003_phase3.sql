-- ─── SilverDeals MY — Phase 3 Database Additions ─────────────────────────────
-- Run after 001_schema.sql and 002_phase2.sql
-- Safe to re-run (all statements use IF NOT EXISTS / IF EXISTS guards)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Payout requests from merchants ──────────────────────────────────────────
-- NOTE: merchant_id must be BIGINT UNSIGNED to match merchants.id

CREATE TABLE IF NOT EXISTS payout_requests (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    merchant_id   BIGINT UNSIGNED  NOT NULL,
    amount        DECIMAL(10,2)    NOT NULL,
    bank_name     VARCHAR(100)     NOT NULL,
    account_no    VARCHAR(50)      NOT NULL,
    account_name  VARCHAR(150)     NOT NULL,
    notes         TEXT             NULL,
    status        ENUM('pending','paid','rejected') NOT NULL DEFAULT 'pending',
    processed_at  DATETIME         NULL,
    processed_by  BIGINT UNSIGNED  NULL,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payout_merchant (merchant_id),
    INDEX idx_payout_status   (status),
    FOREIGN KEY (merchant_id) REFERENCES merchants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── Merchant branches ────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS merchant_branches (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    merchant_id BIGINT UNSIGNED NOT NULL,
    name        VARCHAR(150)    NOT NULL,
    address     TEXT            NULL,
    phone       VARCHAR(30)     NULL,
    state       VARCHAR(60)     NULL,
    is_primary  TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_branch_merchant (merchant_id),
    FOREIGN KEY (merchant_id) REFERENCES merchants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─── Add points_required column to deals (if not already present) ─────────────
-- MySQL 8.0.3+ supports ADD COLUMN IF NOT EXISTS; older versions need this guard.

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'deals'
      AND COLUMN_NAME  = 'points_required'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE deals ADD COLUMN points_required INT UNSIGNED NOT NULL DEFAULT 0 AFTER discount_pct',
    'SELECT 1 -- points_required already exists'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
