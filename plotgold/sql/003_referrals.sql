-- ============================================================
-- PlotGold Malaysia — Referral System Migration
-- Run after 001_schema.sql
-- ============================================================

-- Add referral columns to users table
ALTER TABLE users
    ADD COLUMN referral_code VARCHAR(20) NULL UNIQUE AFTER remember_token,
    ADD COLUMN referred_by_user_id INT UNSIGNED NULL AFTER referral_code,
    ADD INDEX idx_referral_code (referral_code);

-- Referrals tracking table
CREATE TABLE IF NOT EXISTS referrals (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    referrer_id INT UNSIGNED NOT NULL,
    referee_id INT UNSIGNED NOT NULL,
    status ENUM('registered','active','rewarded') NOT NULL DEFAULT 'registered',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_referee (referee_id),
    INDEX idx_referrer (referrer_id),
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referee_id)  REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
