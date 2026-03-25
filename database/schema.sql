-- =====================================================
-- F&B Loyalty Platform – Full Database Schema
-- /database/schema.sql
-- Engine: MySQL 8.0+  Charset: utf8mb4
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ------------------------------------------------
-- 1. USERS (shared auth table)
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `name`            VARCHAR(120)      NOT NULL,
    `email`           VARCHAR(180)      NULL UNIQUE,
    `phone`           VARCHAR(20)       NOT NULL UNIQUE,
    `password_hash`   VARCHAR(255)      NULL,
    `role`            ENUM('customer','staff','admin','superadmin') NOT NULL DEFAULT 'customer',
    `status`          ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
    `email_verified`  TINYINT(1)        NOT NULL DEFAULT 0,
    `phone_verified`  TINYINT(1)        NOT NULL DEFAULT 0,
    `last_login_at`   DATETIME          NULL,
    `created_at`      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_users_phone`  (`phone`),
    INDEX `idx_users_email`  (`email`),
    INDEX `idx_users_role`   (`role`),
    INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------
-- 2. OTP TOKENS
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS `otp_tokens` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `phone`      VARCHAR(20)  NOT NULL,
    `otp`        VARCHAR(10)  NOT NULL,
    `purpose`    ENUM('login','register','reset') NOT NULL DEFAULT 'login',
    `attempts`   TINYINT      NOT NULL DEFAULT 0,
    `is_used`    TINYINT(1)   NOT NULL DEFAULT 0,
    `expires_at` DATETIME     NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_otp_phone` (`phone`, `is_used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------
-- 3. CUSTOMER PROFILES
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS `customer_profiles` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED NOT NULL UNIQUE,
    `gender`          ENUM('male','female','other') NULL,
    `date_of_birth`   DATE         NULL,
    `avatar_url`      VARCHAR(255) NULL,
    `preferred_outlet_id` INT UNSIGNED NULL,
    `total_points`    INT UNSIGNED NOT NULL DEFAULT 0,
    `lifetime_points` INT UNSIGNED NOT NULL DEFAULT 0,
    `tier`            ENUM('bronze','silver','gold','platinum') NOT NULL DEFAULT 'bronze',
    `referral_code`   VARCHAR(20)  NULL UNIQUE,
    `referred_by`     INT UNSIGNED NULL,   -- user_id of referrer
    `address`         TEXT         NULL,
    `notes`           TEXT         NULL,   -- merchant CRM notes
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_cp_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_cp_tier`     (`tier`),
    INDEX `idx_cp_referral` (`referral_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------
-- 4. OUTLETS
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS `outlets` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(150) NOT NULL,
    `slug`        VARCHAR(160) NOT NULL UNIQUE,
    `address`     TEXT         NOT NULL,
    `city`        VARCHAR(80)  NOT NULL,
    `state`       VARCHAR(80)  NOT NULL,
    `postcode`    VARCHAR(10)  NOT NULL,
    `phone`       VARCHAR(20)  NULL,
    `email`       VARCHAR(180) NULL,
    `lat`         DECIMAL(10,8) NULL,
    `lng`         DECIMAL(11,8) NULL,
    `image_url`   VARCHAR(255) NULL,
    `opening_hours` JSON       NULL,    -- {"mon":"09:00-22:00","tue":"09:00-22:00",...}
    `status`      ENUM('active','inactive','temporarily_closed') NOT NULL DEFAULT 'active',
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_outlets_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------
-- 5. REWARDS
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS `rewards` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`            VARCHAR(150)  NOT NULL,
    `description`     TEXT          NULL,
    `image_url`       VARCHAR(255)  NULL,
    `points_required` INT UNSIGNED  NOT NULL DEFAULT 0,
    `reward_type`     ENUM('voucher','free_item','discount','experience') NOT NULL DEFAULT 'voucher',
    `discount_value`  DECIMAL(8,2)  NULL,   -- for discount type
    `discount_type`   ENUM('fixed','percent') NULL,
    `stock`           INT           NULL,    -- NULL = unlimited
    `valid_from`      DATE          NULL,
    `valid_until`     DATE          NULL,
    `outlet_id`       INT UNSIGNED  NULL,    -- NULL = all outlets
    `status`          ENUM('active','inactive','out_of_stock') NOT NULL DEFAULT 'active',
    `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_rewards_status` (`status`),
    INDEX `idx_rewards_points` (`points_required`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------
-- 6. REWARD REDEMPTIONS
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS `reward_redemptions` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NOT NULL,
    `reward_id`   INT UNSIGNED NOT NULL,
    `points_used` INT UNSIGNED NOT NULL,
    `voucher_code` VARCHAR(40) NULL UNIQUE,
    `redeemed_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `used_at`     DATETIME    NULL,
    `outlet_id`   INT UNSIGNED NULL,
    `status`      ENUM('pending','used','expired','cancelled') NOT NULL DEFAULT 'pending',
    `created_at`  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_rr_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`),
    CONSTRAINT `fk_rr_reward` FOREIGN KEY (`reward_id`) REFERENCES `rewards`(`id`),
    INDEX `idx_rr_user`   (`user_id`),
    INDEX `idx_rr_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------
-- 7. LOYALTY TRANSACTIONS
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS `loyalty_transactions` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`          INT UNSIGNED  NOT NULL,
    `points`           INT           NOT NULL,   -- positive = earn, negative = redeem
    `type`             ENUM('earn','redeem','expire','adjust','referral','bonus') NOT NULL,
    `reference_type`   VARCHAR(50)   NULL,        -- 'order','redemption','campaign',etc.
    `reference_id`     INT UNSIGNED  NULL,
    `description`      VARCHAR(255)  NULL,
    `balance_after`    INT UNSIGNED  NOT NULL DEFAULT 0,
    `outlet_id`        INT UNSIGNED  NULL,
    `created_by`       INT UNSIGNED  NULL,        -- staff/admin who processed
    `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_lt_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
    INDEX `idx_lt_user`   (`user_id`),
    INDEX `idx_lt_type`   (`type`),
    INDEX `idx_lt_ref`    (`reference_type`, `reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------
-- 8. CAMPAIGNS
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS `campaigns` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(150) NOT NULL,
    `description`  TEXT         NULL,
    `image_url`    VARCHAR(255) NULL,
    `type`         ENUM('announcement','promotion','birthday','inactivity','referral','double_points') NOT NULL DEFAULT 'announcement',
    `channel`      SET('whatsapp','push','email','in_app') NOT NULL DEFAULT 'in_app',
    `message_template` TEXT    NULL,
    `target_segment` ENUM('all','bronze','silver','gold','platinum','inactive_30','inactive_60','birthday_today') NOT NULL DEFAULT 'all',
    `bonus_points` INT UNSIGNED NULL,
    `valid_from`   DATETIME     NULL,
    `valid_until`  DATETIME     NULL,
    `status`       ENUM('draft','active','completed','cancelled') NOT NULL DEFAULT 'draft',
    `sent_count`   INT UNSIGNED NOT NULL DEFAULT 0,
    `created_by`   INT UNSIGNED NULL,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_campaigns_status` (`status`),
    INDEX `idx_campaigns_type`   (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------
-- 9. CAMPAIGN LOGS
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS `campaign_logs` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campaign_id` INT UNSIGNED NOT NULL,
    `user_id`     INT UNSIGNED NOT NULL,
    `channel`     VARCHAR(20)  NOT NULL,
    `status`      ENUM('sent','delivered','failed','opened') NOT NULL DEFAULT 'sent',
    `sent_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_cl_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`id`),
    INDEX `idx_cl_campaign` (`campaign_id`),
    INDEX `idx_cl_user`     (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------
-- 10. RESERVATIONS
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS `reservations` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reservation_no`  VARCHAR(20)  NOT NULL UNIQUE,
    `user_id`         INT UNSIGNED NOT NULL,
    `outlet_id`       INT UNSIGNED NOT NULL,
    `party_size`      TINYINT      NOT NULL DEFAULT 2,
    `reserved_date`   DATE         NOT NULL,
    `reserved_time`   TIME         NOT NULL,
    `occasion`        VARCHAR(80)  NULL,          -- birthday, anniversary, etc.
    `special_request` TEXT         NULL,
    `status`          ENUM('pending','confirmed','seated','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
    `confirmed_by`    INT UNSIGNED NULL,           -- staff user_id
    `points_earned`   INT UNSIGNED NULL DEFAULT 0,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_res_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`),
    CONSTRAINT `fk_res_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `outlets`(`id`),
    INDEX `idx_res_date`   (`reserved_date`),
    INDEX `idx_res_outlet` (`outlet_id`),
    INDEX `idx_res_user`   (`user_id`),
    INDEX `idx_res_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------
-- 11. MENU CATEGORIES
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS `menu_categories` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `outlet_id`  INT UNSIGNED NULL,       -- NULL = all outlets
    `name`       VARCHAR(100) NOT NULL,
    `sort_order` TINYINT      NOT NULL DEFAULT 0,
    `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------
-- 12. MENU ITEMS
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS `menu_items` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `category_id` INT UNSIGNED  NOT NULL,
    `name`        VARCHAR(150)  NOT NULL,
    `description` TEXT          NULL,
    `image_url`   VARCHAR(255)  NULL,
    `price`       DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
    `is_available` TINYINT(1)   NOT NULL DEFAULT 1,
    `is_featured`  TINYINT(1)   NOT NULL DEFAULT 0,
    `sort_order`  TINYINT       NOT NULL DEFAULT 0,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_mi_cat` FOREIGN KEY (`category_id`) REFERENCES `menu_categories`(`id`),
    INDEX `idx_mi_category`  (`category_id`),
    INDEX `idx_mi_available` (`is_available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------
-- 13. ORDERS
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_no`       VARCHAR(20)   NOT NULL UNIQUE,
    `user_id`        INT UNSIGNED  NOT NULL,
    `outlet_id`      INT UNSIGNED  NOT NULL,
    `reservation_id` INT UNSIGNED  NULL,
    `order_type`     ENUM('dine_in','takeaway','delivery') NOT NULL DEFAULT 'dine_in',
    `table_no`       VARCHAR(20)   NULL,
    `subtotal`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `points_earned`  INT UNSIGNED  NOT NULL DEFAULT 0,
    `points_used`    INT UNSIGNED  NOT NULL DEFAULT 0,
    `payment_method` ENUM('cash','card','fpx','points','mixed') NULL,
    `payment_status` ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
    `payment_ref`    VARCHAR(80)   NULL,
    `status`         ENUM('pending','confirmed','preparing','ready','completed','cancelled') NOT NULL DEFAULT 'pending',
    `notes`          TEXT          NULL,
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_ord_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`),
    CONSTRAINT `fk_ord_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `outlets`(`id`),
    INDEX `idx_ord_user`   (`user_id`),
    INDEX `idx_ord_outlet` (`outlet_id`),
    INDEX `idx_ord_status` (`status`),
    INDEX `idx_ord_date`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------
-- 14. ORDER ITEMS
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`    INT UNSIGNED  NOT NULL,
    `menu_item_id` INT UNSIGNED NOT NULL,
    `name`        VARCHAR(150)  NOT NULL,  -- snapshot at time of order
    `price`       DECIMAL(8,2)  NOT NULL,
    `qty`         TINYINT       NOT NULL DEFAULT 1,
    `subtotal`    DECIMAL(10,2) NOT NULL,
    `notes`       VARCHAR(255)  NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    INDEX `idx_oi_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------
-- 15. REFERRAL LINKS
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS `referral_links` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED NOT NULL,
    `code`         VARCHAR(20)  NOT NULL UNIQUE,
    `clicks`       INT UNSIGNED NOT NULL DEFAULT 0,
    `conversions`  INT UNSIGNED NOT NULL DEFAULT 0,
    `points_earned` INT UNSIGNED NOT NULL DEFAULT 0,
    `reward_points_per_referral` INT UNSIGNED NOT NULL DEFAULT 100,
    `status`       ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_rl_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
    INDEX `idx_rl_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------
-- 16. NOTIFICATIONS
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NOT NULL,
    `title`       VARCHAR(150) NOT NULL,
    `body`        TEXT         NOT NULL,
    `type`        ENUM('points','reward','promotion','reservation','order','system') NOT NULL DEFAULT 'system',
    `channel`     ENUM('in_app','push','whatsapp','email') NOT NULL DEFAULT 'in_app',
    `is_read`     TINYINT(1)   NOT NULL DEFAULT 0,
    `reference_type` VARCHAR(50) NULL,
    `reference_id`   INT UNSIGNED NULL,
    `sent_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `read_at`     DATETIME     NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_notif_user`   (`user_id`, `is_read`),
    INDEX `idx_notif_type`   (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------
-- 17. ADMIN ACTIVITY LOG
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_logs` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NULL,
    `action`     VARCHAR(100) NOT NULL,
    `module`     VARCHAR(50)  NOT NULL,
    `reference_id` INT UNSIGNED NULL,
    `description` TEXT        NULL,
    `ip_address` VARCHAR(45)  NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_al_user`   (`user_id`),
    INDEX `idx_al_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------
-- 18. SETTINGS (key-value store for app config)
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `key`        VARCHAR(100) NOT NULL,
    `value`      TEXT         NULL,
    `group`      VARCHAR(50)  NOT NULL DEFAULT 'general',
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------
-- SEED: Default admin user (password: Admin@1234)
-- ------------------------------------------------
INSERT IGNORE INTO `users` (`name`, `email`, `phone`, `password_hash`, `role`, `status`, `phone_verified`)
VALUES ('Super Admin', 'admin@example.com', '60000000000',
        '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Admin@1234
        'superadmin', 'active', 1);

-- ------------------------------------------------
-- SEED: Default settings
-- ------------------------------------------------
INSERT IGNORE INTO `settings` (`key`, `value`, `group`) VALUES
('loyalty_points_per_myr', '1', 'loyalty'),
('tier_silver_threshold', '500', 'loyalty'),
('tier_gold_threshold', '2000', 'loyalty'),
('tier_platinum_threshold', '5000', 'loyalty'),
('referral_reward_points', '100', 'referral'),
('referral_referee_points', '50', 'referral'),
('reservation_points', '10', 'reservation'),
('currency', 'MYR', 'general'),
('tax_rate', '0.06', 'general'),
('brand_name', 'F&B Loyalty Platform', 'general'),
('brand_logo', '', 'general');
