-- ─── SilverDeals MY — Phase 3 Database Additions ─────────────────────────────
-- Run after 001_schema.sql and 002_phase2.sql

-- Payout requests from merchants
CREATE TABLE IF NOT EXISTS payout_requests (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    merchant_id   INT UNSIGNED NOT NULL,
    amount        DECIMAL(10,2) NOT NULL,
    bank_name     VARCHAR(100)  NOT NULL,
    account_no    VARCHAR(50)   NOT NULL,
    account_name  VARCHAR(150)  NOT NULL,
    notes         TEXT,
    status        ENUM('pending','paid','rejected') NOT NULL DEFAULT 'pending',
    processed_at  DATETIME      NULL,
    processed_by  INT UNSIGNED  NULL,        -- admin user_id
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (merchant_id) REFERENCES merchants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_payout_requests_merchant ON payout_requests (merchant_id);
CREATE INDEX idx_payout_requests_status   ON payout_requests (status);

-- Add slug column to merchant_deals if not present (idempotent guard)
ALTER TABLE deals MODIFY COLUMN slug VARCHAR(255) NOT NULL DEFAULT '';

-- Add points_required column to deals (for points-gated deals)
ALTER TABLE deals
    ADD COLUMN IF NOT EXISTS points_required INT UNSIGNED NOT NULL DEFAULT 0 AFTER discount_pct;

-- Ensure merchant_branches table exists
CREATE TABLE IF NOT EXISTS merchant_branches (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    merchant_id INT UNSIGNED NOT NULL,
    name        VARCHAR(150) NOT NULL,
    address     TEXT,
    phone       VARCHAR(30),
    state       VARCHAR(60),
    is_primary  TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (merchant_id) REFERENCES merchants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
