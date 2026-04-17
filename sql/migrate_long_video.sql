-- migrate_long_video.sql
-- 30-Second Ad feature: chained Seedance 1.5 clips with first-frame continuation.
-- Run once in phpMyAdmin before using the 30s Ad page.

CREATE TABLE IF NOT EXISTS `long_video_jobs` (
    `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `user_id`          INT UNSIGNED     NOT NULL,

    -- Shot prompts (one per 10-second clip)
    `prompt1`          TEXT             NOT NULL COMMENT 'Clip 1 — establishing shot',
    `prompt2`          TEXT             NOT NULL COMMENT 'Clip 2 — continuation shot',
    `prompt3`          TEXT             NOT NULL COMMENT 'Clip 3 — ending shot',
    `resolution`       VARCHAR(10)      NOT NULL DEFAULT '1080p',

    -- Optional user-supplied hero/ending frame for clip 3 last_frame
    `hero_frame_path`  VARCHAR(500)     NULL     COMMENT 'Server path to uploaded hero frame',

    -- State machine
    `status`           ENUM(
                           'queued',     -- waiting to submit clip 1
                           'clip1',      -- clip 1 generating
                           'clip2',      -- clip 2 generating
                           'clip3',      -- clip 3 generating
                           'stitching',  -- all clips done, running FFmpeg
                           'completed',
                           'failed',
                           'refunded'
                       ) NOT NULL DEFAULT 'queued',
    `error_message`    TEXT             NULL,

    -- BytePlus ModelArk task IDs
    `clip1_task_id`    VARCHAR(200)     NULL,
    `clip2_task_id`    VARCHAR(200)     NULL,
    `clip3_task_id`    VARCHAR(200)     NULL,

    -- CDN video URLs returned by BytePlus
    `clip1_url`        VARCHAR(1000)    NULL,
    `clip2_url`        VARCHAR(1000)    NULL,
    `clip3_url`        VARCHAR(1000)    NULL,

    -- Local paths to downloaded clip files (needed for FFmpeg stitch)
    `clip1_local`      VARCHAR(500)     NULL,
    `clip2_local`      VARCHAR(500)     NULL,
    `clip3_local`      VARCHAR(500)     NULL,

    -- Extracted last-frame paths (served as public URLs for next clip's first_frame)
    `frame1_path`      VARCHAR(500)     NULL COMMENT 'Last frame of clip1 → first_frame of clip2',
    `frame2_path`      VARCHAR(500)     NULL COMMENT 'Last frame of clip2 → first_frame of clip3',

    -- Final stitched output
    `final_video_url`  VARCHAR(1000)    NULL,
    `final_local`      VARCHAR(500)     NULL,

    -- Pricing & refund
    `credit_cost`      DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
    `refunded_at`      TIMESTAMP        NULL,

    -- Timestamps
    `created_at`       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `started_at`       TIMESTAMP        NULL,
    `completed_at`     TIMESTAMP        NULL,

    PRIMARY KEY (`id`),
    KEY `idx_lv_user_status` (`user_id`, `status`),
    KEY `idx_lv_status`      (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings: credit cost (defaults to 3 × HD-10s = 3 × 35 = 105)
INSERT INTO `settings` (`key`, `value`, `label`, `type`, `group`)
VALUES ('long_video_credit_cost', '105.00', '30s Ad credit cost (3 clips total)', 'string', 'pricing')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);
