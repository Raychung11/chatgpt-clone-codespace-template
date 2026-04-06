-- ==============================================================
-- AI Marketing Video SaaS Platform - Full Database Schema
-- Phase 1+2: Auth, Wallet, Payments, Referral, Video, Sharing
-- Import via phpMyAdmin: Database > Import > select this file
-- ==============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- --------------------------------------------------------------
-- 1. admins
-- --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
    `id`              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `name`            VARCHAR(100)      NOT NULL,
    `email`           VARCHAR(191)      NOT NULL,
    `password_hash`   VARCHAR(255)      NOT NULL,
    `role`            ENUM('super_admin','staff_admin','finance_admin') NOT NULL DEFAULT 'staff_admin',
    `is_active`       TINYINT(1)        NOT NULL DEFAULT 1,
    `last_login_at`   DATETIME          NULL,
    `created_at`      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 2. users (clients)
-- --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`               VARCHAR(100) NOT NULL,
    `email`              VARCHAR(191) NOT NULL,
    `password_hash`      VARCHAR(255) NOT NULL,
    `phone`              VARCHAR(30)  NULL,
    `avatar`             VARCHAR(255) NULL,
    `referral_code`      VARCHAR(20)  NOT NULL,
    `referred_by`        INT UNSIGNED NULL,
    `is_active`          TINYINT(1)   NOT NULL DEFAULT 1,
    `email_verified_at`  DATETIME     NULL,
    `reset_token`        VARCHAR(100) NULL,
    `reset_token_expiry` DATETIME     NULL,
    `last_login_at`      DATETIME     NULL,
    `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email`         (`email`),
    UNIQUE KEY `uq_users_referral_code` (`referral_code`),
    KEY `idx_users_referred_by`         (`referred_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 3. wallets (one row per user)
-- --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wallets` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED  NOT NULL,
    `balance`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wallets_user` (`user_id`),
    CONSTRAINT `fk_wallets_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 4. wallet_transactions
-- --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED    NOT NULL,
    `type`           ENUM('topup','deduction','refund','referral_reward','admin_adjustment') NOT NULL,
    `amount`         DECIMAL(12,2)   NOT NULL,
    `balance_before` DECIMAL(12,2)   NOT NULL,
    `balance_after`  DECIMAL(12,2)   NOT NULL,
    `reference_type` VARCHAR(50)     NULL,
    `reference_id`   INT UNSIGNED    NULL,
    `note`           TEXT            NULL,
    `created_by`     VARCHAR(50)     NULL,
    `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_wt_user` (`user_id`),
    KEY `idx_wt_type` (`type`),
    CONSTRAINT `fk_wt_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 5. credit_packages
-- --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `credit_packages` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100)  NOT NULL,
    `description` TEXT          NULL,
    `credits`     DECIMAL(12,2) NOT NULL,
    `price`       DECIMAL(10,2) NOT NULL,
    `currency`    VARCHAR(10)   NOT NULL DEFAULT 'MYR',
    `is_popular`  TINYINT(1)    NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
    `sort_order`  INT           NOT NULL DEFAULT 0,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 6. payment_orders
-- --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_orders` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED  NOT NULL,
    `package_id`     INT UNSIGNED  NULL,
    `amount`         DECIMAL(10,2) NOT NULL,
    `currency`       VARCHAR(10)   NOT NULL DEFAULT 'MYR',
    `credits`        DECIMAL(12,2) NOT NULL,
    `payment_method` VARCHAR(50)   NOT NULL DEFAULT 'bank_transfer',
    `status`         ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    `approved_by`    INT UNSIGNED  NULL,
    `approved_at`    DATETIME      NULL,
    `reject_reason`  TEXT          NULL,
    `note`           TEXT          NULL,
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_po_user`   (`user_id`),
    KEY `idx_po_status` (`status`),
    CONSTRAINT `fk_po_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 7. payment_receipts
-- --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_receipts` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payment_order_id` INT UNSIGNED NOT NULL,
    `file_path`        VARCHAR(255) NOT NULL,
    `original_name`    VARCHAR(255) NOT NULL,
    `file_size`        INT UNSIGNED NULL,
    `mime_type`        VARCHAR(100) NULL,
    `uploaded_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_pr_order` FOREIGN KEY (`payment_order_id`) REFERENCES `payment_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 8. generation_pricing_rules
-- --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `generation_pricing_rules` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100)  NOT NULL,
    `description` TEXT          NULL,
    `resolution`  VARCHAR(20)   NULL,
    `duration`    INT           NULL,
    `credit_cost` DECIMAL(10,2) NOT NULL,
    `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 9. video_jobs
-- --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `video_jobs` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED    NOT NULL,
    `pricing_rule_id` INT UNSIGNED    NULL,
    `prompt`          TEXT            NOT NULL,
    `model`           VARCHAR(100)    NOT NULL DEFAULT 'bytedance/v1.5',
    `resolution`      VARCHAR(20)     NULL,
    `duration`        INT             NULL,
    `credit_cost`     DECIMAL(10,2)   NOT NULL,
    `status`          ENUM('queued','processing','completed','failed','refunded') NOT NULL DEFAULT 'queued',
    `api_task_id`     VARCHAR(255)    NULL,
    `api_response`    JSON            NULL,
    `error_message`   TEXT            NULL,
    `started_at`      DATETIME        NULL,
    `completed_at`    DATETIME        NULL,
    `refunded_at`     DATETIME        NULL,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_vj_user`   (`user_id`),
    KEY `idx_vj_status` (`status`),
    KEY `idx_vj_task`   (`api_task_id`),
    CONSTRAINT `fk_vj_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 10. video_outputs
-- --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `video_outputs` (
    `id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `job_id`    BIGINT UNSIGNED NOT NULL,
    `user_id`   INT UNSIGNED    NOT NULL,
    `file_path` VARCHAR(255)    NULL,
    `cdn_url`   VARCHAR(512)    NULL,
    `thumbnail` VARCHAR(512)    NULL,
    `caption`   TEXT            NULL,
    `hashtags`  TEXT            NULL,
    `duration`  INT             NULL,
    `file_size` BIGINT UNSIGNED NULL,
    `created_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_vo_job` (`job_id`),
    CONSTRAINT `fk_vo_job` FOREIGN KEY (`job_id`) REFERENCES `video_jobs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 11. prompt_templates
-- --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `prompt_templates` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100) NOT NULL,
    `category`   VARCHAR(50)  NULL,
    `template`   TEXT         NOT NULL,
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 12. referrals
-- --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `referrals` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `referrer_id` INT UNSIGNED NOT NULL,
    `referee_id`  INT UNSIGNED NOT NULL,
    `status`      ENUM('pending','rewarded') NOT NULL DEFAULT 'pending',
    `rewarded_at` DATETIME     NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_referrals_referee` (`referee_id`),
    KEY `idx_referrals_referrer` (`referrer_id`),
    CONSTRAINT `fk_ref_referrer` FOREIGN KEY (`referrer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ref_referee`  FOREIGN KEY (`referee_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 13. referral_rewards
-- --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `referral_rewards` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `referral_id` INT UNSIGNED  NOT NULL,
    `user_id`     INT UNSIGNED  NOT NULL,
    `credits`     DECIMAL(10,2) NOT NULL,
    `note`        TEXT          NULL,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_rr_referral` FOREIGN KEY (`referral_id`) REFERENCES `referrals`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 14. settings
-- --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`        VARCHAR(100) NOT NULL,
    `value`      TEXT         NULL,
    `type`       ENUM('string','integer','float','boolean','json') NOT NULL DEFAULT 'string',
    `label`      VARCHAR(200) NULL,
    `group`      VARCHAR(50)  NULL,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 15. activity_logs
-- --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actor_type`  ENUM('user','admin','system') NOT NULL DEFAULT 'system',
    `actor_id`    INT UNSIGNED    NULL,
    `action`      VARCHAR(100)    NOT NULL,
    `description` TEXT            NULL,
    `ip_address`  VARCHAR(45)     NULL,
    `user_agent`  VARCHAR(500)    NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_al_actor`  (`actor_type`,`actor_id`),
    KEY `idx_al_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- 16. social_share_logs
-- --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `social_share_logs` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED    NOT NULL,
    `video_job_id` BIGINT UNSIGNED NOT NULL,
    `platform`     ENUM('whatsapp','facebook','twitter','linkedin','instagram','tiktok','copy_link') NOT NULL,
    `caption`      TEXT            NULL,
    `hashtags`     TEXT            NULL,
    `share_type`   ENUM('link','download','caption_copy','hashtag_copy') NOT NULL DEFAULT 'link',
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ssl_user` (`user_id`),
    KEY `idx_ssl_job`  (`video_job_id`),
    CONSTRAINT `fk_ssl_user` FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`)      ON DELETE CASCADE,
    CONSTRAINT `fk_ssl_job`  FOREIGN KEY (`video_job_id`) REFERENCES `video_jobs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================
-- SEED DATA
-- ==============================================================

-- Default settings
INSERT INTO `settings` (`key`, `value`, `type`, `label`, `group`) VALUES
('site_name',            'VideoSaaS',                          'string',  'Site Name',                    'general'),
('site_url',             'https://example.com',                'string',  'Site URL',                     'general'),
('support_email',        'support@example.com',                'string',  'Support Email',                'general'),
('currency',             'MYR',                                'string',  'Currency Code',                'general'),
('byteplus_api_key',     '',                                   'string',  'BytePlus API Key',             'api'),
('byteplus_api_url',     'https://api.byteplus.com/visugc/v1', 'string',  'BytePlus API Base URL',        'api'),
('referral_reward_credits','10.00',                            'float',   'Referral Reward (credits)',    'referral'),
('mail_driver',          'mail',                               'string',  'Mail Driver (mail or smtp)',    'email'),
('mail_from_name',       'VideoSaaS',                          'string',  'Mail From Name',               'email'),
('mail_from_email',      'noreply@example.com',                'string',  'Mail From Email',              'email'),
('smtp_host',            'smtp.mailtrap.io',                   'string',  'SMTP Host',                    'email'),
('smtp_port',            '587',                                'integer', 'SMTP Port',                    'email'),
('smtp_user',            '',                                   'string',  'SMTP Username',                'email'),
('smtp_pass',            '',                                   'string',  'SMTP Password',                'email'),
('smtp_secure',          'tls',                                'string',  'SMTP Encryption (tls/ssl)',    'email'),
('bank_name',            'Maybank',                            'string',  'Bank Name',                    'payment'),
('bank_account_number',  '1234567890',                         'string',  'Bank Account Number',          'payment'),
('bank_account_name',    'Your Company Sdn Bhd',               'string',  'Bank Account Name',            'payment'),
('max_upload_size_mb',   '5',                                  'integer', 'Max Receipt Upload Size (MB)', 'payment'),
('video_poll_interval',  '30',                                 'integer', 'Video Poll Interval (seconds)','api'),
('billplz_api_key',      '',                                   'string',  'Billplz API Key',              'payment'),
('billplz_x_signature_key','',                                 'string',  'Billplz X-Signature Key',      'payment'),
('stripe_secret_key',    '',                                   'string',  'Stripe Secret Key',            'payment'),
('stripe_publishable_key','',                                  'string',  'Stripe Publishable Key',       'payment'),
('stripe_webhook_secret','',                                   'string',  'Stripe Webhook Secret',        'payment')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- Sample credit packages
INSERT INTO `credit_packages` (`name`, `description`, `credits`, `price`, `is_popular`, `sort_order`) VALUES
('Starter',  '50 credits - great for testing',      50.00,   19.00, 0, 1),
('Basic',    '150 credits - ideal for small teams', 150.00,  49.00, 0, 2),
('Pro',      '400 credits - most popular choice',   400.00,  99.00, 1, 3),
('Business', '1000 credits - for power users',     1000.00, 199.00, 0, 4)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Default generation pricing rules
INSERT INTO `generation_pricing_rules` (`name`, `description`, `resolution`, `duration`, `credit_cost`) VALUES
('Standard 5s',  '720p, 5 second video',   '720p',  5,  10.00),
('Standard 10s', '720p, 10 second video',  '720p',  10, 18.00),
('HD 5s',        '1080p, 5 second video',  '1080p', 5,  20.00),
('HD 10s',       '1080p, 10 second video', '1080p', 10, 35.00)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Default prompt templates
INSERT INTO `prompt_templates` (`name`, `category`, `template`, `is_active`) VALUES
('Product Launch', 'product',
 'A vibrant product launch video for {{product_name}}. Show the product against {{background_description}} with {{visual_style}} cinematography. Key selling points: {{key_benefits}}. Target audience: {{target_audience}}. Tone: {{tone}}.',
 1),
('Brand Story', 'brand',
 'A compelling brand story video for {{brand_name}}. Show {{brand_values}} through real-world scenes. The video should feel {{mood}} and appeal to {{target_audience}}. End with the tagline: {{tagline}}.',
 1),
('Promo Offer', 'promo',
 'A high-energy promotional video for {{offer_name}}. Highlight the {{discount_or_deal}} offer. Show {{product_or_service}} in action. Call to action: {{cta_text}}. Valid until {{expiry}}. Tone: urgent and exciting.',
 1),
('Event Invite', 'event',
 'An elegant event invitation video for {{event_name}} on {{event_date}} at {{event_venue}}. Highlight {{event_highlights}}. Target attendees: {{target_audience}}. Tone: {{tone}}.',
 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ==============================================================
-- DEFAULT SUPER ADMIN
-- Change the password hash before use!
-- Generate hash with: password_hash('YourPassword', PASSWORD_BCRYPT, ['cost'=>12])
-- ==============================================================
INSERT INTO `admins` (`name`, `email`, `password_hash`, `role`) VALUES
('Super Admin', 'admin@example.com', '$2y$12$REPLACE_THIS_WITH_REAL_HASH_XXXXX', 'super_admin')
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);
