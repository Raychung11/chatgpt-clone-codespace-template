-- ============================================================
-- AI Marketing Video SaaS Platform — Full Database Schema
-- Phase 1: Auth + Wallet + Payments + Referral + Video + Share
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 1. admins
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`            VARCHAR(100) NOT NULL,
    `email`           VARCHAR(191) NOT NULL UNIQUE,
    `password_hash`   VARCHAR(255) NOT NULL,
    `role`            ENUM('super_admin','staff_admin','finance_admin') NOT NULL DEFAULT 'staff_admin',
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `last_login_at`   DATETIME NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. users (clients)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`              VARCHAR(100) NOT NULL,
    `email`             VARCHAR(191) NOT NULL UNIQUE,
    `password_hash`     VARCHAR(255) NOT NULL,
    `phone`             VARCHAR(30) NULL,
    `avatar`            VARCHAR(255) NULL,
    `referral_code`     VARCHAR(20) NOT NULL UNIQUE,
    `referred_by`       INT UNSIGNED NULL,        -- FK to users.id
    `is_active`         TINYINT(1) NOT NULL DEFAULT 1,
    `email_verified_at` DATETIME NULL,
    `reset_token`       VARCHAR(100) NULL,
    `reset_token_expiry` DATETIME NULL,
    `last_login_at`     DATETIME NULL,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_referred_by` (`referred_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. wallets  (one per user)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wallets` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL UNIQUE,
    `balance`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,  -- credits (not money)
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_wallet_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. wallet_transactions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NOT NULL,
    `type`        ENUM('topup','deduction','refund','referral_reward','admin_adjustment') NOT NULL,
    `amount`      DECIMAL(12,2) NOT NULL,            -- positive = credit added, negative = deducted
    `balance_before` DECIMAL(12,2) NOT NULL,
    `balance_after`  DECIMAL(12,2) NOT NULL,
    `reference_type` VARCHAR(50) NULL,               -- 'payment_order','video_job','referral_reward'
    `reference_id`   INT UNSIGNED NULL,
    `note`        TEXT NULL,
    `created_by`  VARCHAR(50) NULL,                  -- 'system','admin:<id>','user'
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_wt_user` (`user_id`),
    KEY `idx_wt_type` (`type`),
    CONSTRAINT `fk_wt_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. credit_packages
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `credit_packages` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `credits`     DECIMAL(12,2) NOT NULL,      -- credits granted on purchase
    `price`       DECIMAL(10,2) NOT NULL,      -- price in local currency (e.g. MYR)
    `currency`    VARCHAR(10) NOT NULL DEFAULT 'MYR',
    `is_popular`  TINYINT(1) NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order`  INT NOT NULL DEFAULT 0,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. payment_orders
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_orders` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED NOT NULL,
    `package_id`     INT UNSIGNED NULL,          -- NULL = custom top-up
    `amount`         DECIMAL(10,2) NOT NULL,
    `currency`       VARCHAR(10) NOT NULL DEFAULT 'MYR',
    `credits`        DECIMAL(12,2) NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL DEFAULT 'bank_transfer',
    `status`         ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    `approved_by`    INT UNSIGNED NULL,          -- admin id
    `approved_at`    DATETIME NULL,
    `reject_reason`  TEXT NULL,
    `note`           TEXT NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_po_user` (`user_id`),
    KEY `idx_po_status` (`status`),
    CONSTRAINT `fk_po_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. payment_receipts
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_receipts` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payment_order_id` INT UNSIGNED NOT NULL,
    `file_path`        VARCHAR(255) NOT NULL,
    `original_name`    VARCHAR(255) NOT NULL,
    `file_size`        INT UNSIGNED NULL,
    `mime_type`        VARCHAR(100) NULL,
    `uploaded_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_pr_order` FOREIGN KEY (`payment_order_id`) REFERENCES `payment_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 8. generation_pricing_rules
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `generation_pricing_rules` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,           -- e.g. "Standard 5s", "HD 10s"
    `description` TEXT NULL,
    `resolution`  VARCHAR(20) NULL,               -- e.g. "720p","1080p"
    `duration`    INT NULL,                        -- seconds
    `credit_cost` DECIMAL(10,2) NOT NULL,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 9. video_jobs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `video_jobs` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED NOT NULL,
    `pricing_rule_id` INT UNSIGNED NULL,
    `prompt`          TEXT NOT NULL,
    `model`           VARCHAR(100) NOT NULL DEFAULT 'bytedance/v1.5',
    `resolution`      VARCHAR(20) NULL,
    `duration`        INT NULL,
    `credit_cost`     DECIMAL(10,2) NOT NULL,
    `status`          ENUM('queued','processing','completed','failed','refunded') NOT NULL DEFAULT 'queued',
    `api_task_id`     VARCHAR(255) NULL,          -- external task ID from BytePlus
    `api_response`    JSON NULL,                  -- raw API response
    `error_message`   TEXT NULL,
    `started_at`      DATETIME NULL,
    `completed_at`    DATETIME NULL,
    `refunded_at`     DATETIME NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_vj_user` (`user_id`),
    KEY `idx_vj_status` (`status`),
    KEY `idx_vj_task` (`api_task_id`),
    CONSTRAINT `fk_vj_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 10. video_outputs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `video_outputs` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `job_id`       BIGINT UNSIGNED NOT NULL,
    `user_id`      INT UNSIGNED NOT NULL,
    `file_path`    VARCHAR(255) NULL,              -- local stored path
    `cdn_url`      VARCHAR(512) NULL,              -- original URL from API
    `thumbnail`    VARCHAR(512) NULL,
    `caption`      TEXT NULL,                      -- AI-generated caption
    `hashtags`     TEXT NULL,                      -- AI-generated hashtags
    `duration`     INT NULL,
    `file_size`    BIGINT UNSIGNED NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_vo_job` (`job_id`),
    CONSTRAINT `fk_vo_job` FOREIGN KEY (`job_id`) REFERENCES `video_jobs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 11. prompt_templates
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `prompt_templates` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `category`    VARCHAR(50) NULL,               -- e.g. "product","brand","event"
    `template`    TEXT NOT NULL,                  -- template with {{placeholders}}
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 12. referrals
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `referrals` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `referrer_id`  INT UNSIGNED NOT NULL,          -- the one who referred
    `referee_id`   INT UNSIGNED NOT NULL UNIQUE,   -- the new user
    `status`       ENUM('pending','rewarded') NOT NULL DEFAULT 'pending',
    `rewarded_at`  DATETIME NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ref_referrer` (`referrer_id`),
    CONSTRAINT `fk_ref_referrer` FOREIGN KEY (`referrer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ref_referee`  FOREIGN KEY (`referee_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 13. referral_rewards
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `referral_rewards` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `referral_id`   INT UNSIGNED NOT NULL,
    `user_id`       INT UNSIGNED NOT NULL,          -- who received the reward
    `credits`       DECIMAL(10,2) NOT NULL,
    `note`          TEXT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_rr_referral` FOREIGN KEY (`referral_id`) REFERENCES `referrals`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 14. settings
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`         VARCHAR(100) NOT NULL UNIQUE,
    `value`       TEXT NULL,
    `type`        ENUM('string','integer','float','boolean','json') NOT NULL DEFAULT 'string',
    `label`       VARCHAR(200) NULL,
    `group`       VARCHAR(50) NULL,                -- 'general','payment','referral','api','email'
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 15. activity_logs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actor_type`  ENUM('user','admin','system') NOT NULL DEFAULT 'system',
    `actor_id`    INT UNSIGNED NULL,
    `action`      VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `ip_address`  VARCHAR(45) NULL,
    `user_agent`  VARCHAR(500) NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_al_actor` (`actor_type`,`actor_id`),
    KEY `idx_al_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 16. social_share_logs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `social_share_logs` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED NOT NULL,
    `video_job_id` BIGINT UNSIGNED NOT NULL,
    `platform`     ENUM('whatsapp','facebook','twitter','linkedin','instagram','tiktok','copy_link') NOT NULL,
    `caption`      TEXT NULL,
    `hashtags`     TEXT NULL,
    `share_type`   ENUM('link','download','caption_copy','hashtag_copy') NOT NULL DEFAULT 'link',
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ssl_user` (`user_id`),
    KEY `idx_ssl_job` (`video_job_id`),
    CONSTRAINT `fk_ssl_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ssl_job`  FOREIGN KEY (`video_job_id`) REFERENCES `video_jobs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Seed: Default settings
-- ============================================================
INSERT INTO `settings` (`key`,`value`,`type`,`label`,`group`) VALUES
('site_name',           'VideoSaaS',        'string',  'Site Name',                   'general'),
('site_url',            'https://example.com','string','Site URL',                    'general'),
('support_email',       'support@example.com','string','Support Email',               'general'),
('currency',            'MYR',              'string',  'Currency Code',               'general'),
('byteplus_api_key',    '',                 'string',  'BytePlus API Key',            'api'),
('byteplus_api_url',    'https://api.byteplus.com/visugc/v1', 'string', 'BytePlus API Base URL', 'api'),
('referral_reward_credits','10.00',         'float',   'Referral Reward (credits)',   'referral'),
('bank_name',           'Maybank',          'string',  'Bank Name',                   'payment'),
('bank_account_number', '1234567890',       'string',  'Bank Account Number',         'payment'),
('bank_account_name',   'Your Company Sdn Bhd','string','Bank Account Name',          'payment'),
('max_upload_size_mb',  '5',                'integer', 'Max Receipt Upload Size (MB)','payment'),
('video_poll_interval', '30',               'integer', 'Video Poll Interval (seconds)','api')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- ============================================================
-- Seed: Default super admin (password: Admin@1234 — CHANGE THIS)
-- ============================================================
INSERT INTO `admins` (`name`,`email`,`password_hash`,`role`) VALUES
('Super Admin','admin@example.com','$2y$12$YourHashHere_CHANGE_THIS','super_admin')
ON DUPLICATE KEY UPDATE `email` = `email`;

-- ============================================================
-- Seed: Sample credit packages
-- ============================================================
INSERT INTO `credit_packages` (`name`,`description`,`credits`,`price`,`is_popular`,`sort_order`) VALUES
('Starter',  '50 credits — great for testing',     50.00,  19.00, 0, 1),
('Basic',    '150 credits — ideal for small teams',150.00, 49.00, 0, 2),
('Pro',      '400 credits — most popular choice',  400.00, 99.00, 1, 3),
('Business', '1000 credits — for power users',     1000.00,199.00, 0, 4)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ============================================================
-- Seed: Default generation pricing rules
-- ============================================================
INSERT INTO `generation_pricing_rules` (`name`,`description`,`resolution`,`duration`,`credit_cost`) VALUES
('Standard 5s',  '720p, 5 second video',  '720p',  5,  10.00),
('Standard 10s', '720p, 10 second video', '720p',  10, 18.00),
('HD 5s',        '1080p, 5 second video', '1080p', 5,  20.00),
('HD 10s',       '1080p, 10 second video','1080p', 10, 35.00)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
