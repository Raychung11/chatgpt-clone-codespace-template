-- STRate AI — MySQL Schema
-- Run once on Hostinger: mysql -u strate_user -p strate_ai < schema.sql

CREATE DATABASE IF NOT EXISTS strate_ai
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE strate_ai;

-- ─── Users ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL UNIQUE,
    phone      VARCHAR(30),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Properties ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS properties (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    name        VARCHAR(150) NOT NULL,
    location    VARCHAR(100) NOT NULL,
    base_price  DECIMAL(10,2) NOT NULL,
    min_price   DECIMAL(10,2) NOT NULL,
    max_price   DECIMAL(10,2) NOT NULL,
    room_type   ENUM('entire_unit','private_room','shared_room') DEFAULT 'entire_unit',
    bedrooms    TINYINT UNSIGNED DEFAULT 1,
    active      TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_location (location),
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Market Data ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS market_data (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location       VARCHAR(100) NOT NULL,
    date           DATE NOT NULL,
    avg_price      DECIMAL(10,2),
    min_price      DECIMAL(10,2),
    max_price      DECIMAL(10,2),
    listing_count  SMALLINT UNSIGNED,
    occupancy_rate DECIMAL(5,4),
    event_flag     TINYINT(1) DEFAULT 0,
    event_name     VARCHAR(150),
    source         VARCHAR(50) DEFAULT 'simulated',
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_location_date (location, date),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Price Recommendations ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS price_recommendations (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    property_id      INT UNSIGNED NOT NULL,
    date             DATE NOT NULL,
    base_price       DECIMAL(10,2) NOT NULL,
    suggested_price  DECIMAL(10,2) NOT NULL,
    confidence_score DECIMAL(4,3) NOT NULL,
    demand_factor    DECIMAL(6,4) DEFAULT 0,
    event_boost      DECIMAL(6,4) DEFAULT 0,
    competitor_gap   DECIMAL(6,4) DEFAULT 0,
    occupancy_adj    DECIMAL(6,4) DEFAULT 0,
    reason           TEXT,
    status           ENUM('pending','applied','dismissed') DEFAULT 'pending',
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_property_date (property_id, date),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    INDEX idx_date (date),
    INDEX idx_property (property_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Cron Logs ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cron_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_name    VARCHAR(100) NOT NULL,
    status      ENUM('success','error','warning') NOT NULL,
    message     TEXT,
    duration_ms INT UNSIGNED DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_job (job_name),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Job Queue (optional async processing) ────────────────────────────────────
CREATE TABLE IF NOT EXISTS job_queue (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_type    VARCHAR(100) NOT NULL,
    payload     JSON,
    status      ENUM('pending','running','done','failed') DEFAULT 'pending',
    retry_count TINYINT UNSIGNED DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Seed demo user ──────────────────────────────────────────────────────────
INSERT IGNORE INTO users (name, email, phone)
VALUES ('Demo Owner', 'demo@strate.ai', '+60123456789');

-- ─── Seed demo properties ─────────────────────────────────────────────────────
INSERT IGNORE INTO properties (user_id, name, location, base_price, min_price, max_price, room_type, bedrooms)
SELECT id, 'KLCC Sky Suite',    'KLCC',          280.00, 220.00, 450.00, 'entire_unit',  2 FROM users WHERE email='demo@strate.ai'
UNION ALL
SELECT id, 'Sunway Cozy Room',  'Sunway',         150.00, 120.00, 250.00, 'private_room', 1 FROM users WHERE email='demo@strate.ai'
UNION ALL
SELECT id, 'BB City Studio',    'Bukit Bintang',  200.00, 160.00, 320.00, 'entire_unit',  1 FROM users WHERE email='demo@strate.ai';
