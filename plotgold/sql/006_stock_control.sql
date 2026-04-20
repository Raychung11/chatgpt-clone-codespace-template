-- ============================================================
-- PlotGold Malaysia — Stock Control
-- Migration: 006_stock_control.sql
-- Run once: mysql -u user -p plotgold < 006_stock_control.sql
-- ============================================================

USE plotgold;

-- Items to monitor
CREATE TABLE IF NOT EXISTS stock_items (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150)                                       NOT NULL,
    entity_type     ENUM('park_section','gold_vault','custom')         NOT NULL DEFAULT 'custom',
    entity_id       INT UNSIGNED                                       NULL COMMENT 'park_sections.id when entity_type=park_section',
    current_stock   DECIMAL(12,4)                                      NOT NULL DEFAULT 0.0000,
    unit            VARCHAR(30)                                        NOT NULL DEFAULT 'units' COMMENT 'plots / grams / units / pcs',
    min_stock       DECIMAL(12,4)                                      NOT NULL DEFAULT 0.0000 COMMENT 'Low-stock alert threshold',
    notify_admin    TINYINT(1)                                         NOT NULL DEFAULT 1,
    is_active       TINYINT(1)                                         NOT NULL DEFAULT 1,
    notes           TEXT                                               NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every change in stock level
CREATE TABLE IF NOT EXISTS stock_transactions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stock_item_id   INT UNSIGNED                                       NOT NULL,
    transaction_type ENUM('in','out','adjustment')                     NOT NULL,
    quantity        DECIMAL(12,4)                                      NOT NULL,
    stock_before    DECIMAL(12,4)                                      NOT NULL,
    stock_after     DECIMAL(12,4)                                      NOT NULL,
    reference_type  VARCHAR(50)                                        NULL COMMENT 'listing / order / purchase / manual',
    reference_id    INT UNSIGNED                                       NULL,
    notes           TEXT                                               NULL,
    created_by      INT UNSIGNED                                       NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_item (stock_item_id),
    INDEX idx_created (created_at),
    INDEX idx_type (transaction_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Triggered low-stock alerts
CREATE TABLE IF NOT EXISTS stock_alerts (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stock_item_id       INT UNSIGNED                                   NOT NULL,
    stock_at_trigger    DECIMAL(12,4)                                  NOT NULL,
    min_stock_at_trigger DECIMAL(12,4)                                 NOT NULL,
    is_acknowledged     TINYINT(1)                                     NOT NULL DEFAULT 0,
    acknowledged_by     INT UNSIGNED                                   NULL,
    acknowledged_at     TIMESTAMP                                      NULL,
    triggered_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_item (stock_item_id),
    INDEX idx_ack (is_acknowledged),
    INDEX idx_triggered (triggered_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Seed: default stock items ─────────────────────────────────────────────────

INSERT IGNORE INTO stock_items (name, entity_type, current_stock, unit, min_stock, notes) VALUES
('Gold Vault — Physical Inventory',  'gold_vault', 0.0000, 'grams', 100.0000,
 'Total grams of PAMP-certified gold held in vault and available for sale. Update after each batch procurement.'),
('Burial Plot Certificates (Blank)', 'custom',     0.0000, 'pcs',    50.0000,
 'Blank ownership certificates in stock. Reorder before running out.');
