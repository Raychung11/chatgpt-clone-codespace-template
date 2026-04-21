-- migrate_clone_avatar.sql
-- Run once in phpMyAdmin to add Clone Avatar (realman_avatar_creation_task) support.

CREATE TABLE IF NOT EXISTS `clone_avatar_jobs` (
    `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED     NOT NULL,

    -- Input
    `resource_id`   VARCHAR(200)     NOT NULL  COMMENT 'Clone Avatar resource_id (admin-configured)',
    `audio_path`    VARCHAR(500)     NULL      COMMENT 'Server path to uploaded audio file, or NULL if URL',
    `audio_url`     VARCHAR(1000)    NOT NULL  COMMENT 'Public audio URL passed to BytePlus',

    -- Pricing
    `credit_cost`   DECIMAL(10,2)    NOT NULL  DEFAULT 0.00,

    -- Processing
    `status`        ENUM('queued','processing','completed','failed','refunded')
                                     NOT NULL  DEFAULT 'queued',
    `api_task_id`   VARCHAR(200)     NULL,
    `api_response`  JSON             NULL,
    `error_message` TEXT             NULL,

    -- Output
    `video_url`     VARCHAR(1000)    NULL      COMMENT 'Final MP4 URL from BytePlus (expires ~1 h)',
    `duration`      FLOAT            NULL      COMMENT 'Video duration in seconds',

    -- Refund tracking
    `refunded_at`   TIMESTAMP        NULL,

    -- Timestamps
    `created_at`    TIMESTAMP        NOT NULL  DEFAULT CURRENT_TIMESTAMP,
    `started_at`    TIMESTAMP        NULL,
    `completed_at`  TIMESTAMP        NULL,

    PRIMARY KEY (`id`),
    KEY `idx_user_status` (`user_id`, `status`),
    KEY `idx_task_id`     (`api_task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings rows
INSERT INTO `settings` (`key`, `value`, `type`, `label`, `group`)
VALUES
    ('clone_avatar_resource_id', '', 'string',
     'Clone Avatar Resource ID (from BytePlus training → output/avatar_result.json)', 'byteplus'),
    ('clone_avatar_credit_cost', '10.00', 'string',
     'Clone Avatar job credit cost', 'pricing')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

-- Confirm
SELECT `key`, `value`, `label` FROM `settings`
WHERE `key` LIKE 'clone_avatar%'
ORDER BY `key`;
