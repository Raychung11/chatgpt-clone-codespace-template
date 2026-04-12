-- migrate_avatar.sql
-- Run once in phpMyAdmin to add OmniHuman avatar job support.

CREATE TABLE IF NOT EXISTS `avatar_jobs` (
    `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED     NOT NULL,

    -- Input
    `portrait_path`   VARCHAR(500)     NOT NULL  COMMENT 'Server path to uploaded portrait image',
    `audio_path`      VARCHAR(500)     NULL      COMMENT 'Server path to uploaded audio file (or NULL if TTS)',
    `tts_text`        TEXT             NULL      COMMENT 'Text for TTS voice (when no audio file)',
    `duration`        TINYINT UNSIGNED NOT NULL  DEFAULT 10,
    `resolution`      VARCHAR(20)      NOT NULL  DEFAULT '720p',

    -- Pricing
    `credit_cost`     DECIMAL(10,2)    NOT NULL  DEFAULT 0.00,

    -- Processing
    `status`          ENUM('queued','processing','completed','failed','refunded')
                                       NOT NULL  DEFAULT 'queued',
    `api_task_id`     VARCHAR(200)     NULL,
    `api_response`    JSON             NULL,
    `error_message`   TEXT             NULL,

    -- Output
    `video_url`       VARCHAR(1000)    NULL      COMMENT 'Final rendered video URL from OmniHuman',
    `thumbnail_url`   VARCHAR(1000)    NULL,

    -- Refund tracking
    `refunded_at`     TIMESTAMP        NULL,

    -- Timestamps
    `created_at`      TIMESTAMP        NOT NULL  DEFAULT CURRENT_TIMESTAMP,
    `started_at`      TIMESTAMP        NULL,
    `completed_at`    TIMESTAMP        NULL,

    PRIMARY KEY (`id`),
    KEY `idx_user_status` (`user_id`, `status`),
    KEY `idx_task_id`     (`api_task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings rows for Vision AI credentials
INSERT INTO `settings` (`key`, `value`, `label`, `type`, `group`)
VALUES
    ('vision_ai_ak',              '', 'Vision AI Access Key (AK)',                   'string', 'byteplus'),
    ('vision_ai_sk',              '', 'Vision AI Secret Key (SK)',                   'string', 'byteplus'),
    ('vision_ai_url',             'https://visual.volcengineapi.com', 'Vision AI Base URL', 'string', 'byteplus'),
    ('vision_ai_region',          'ap-southeast-1', 'Vision AI Region (Volcengine)', 'string', 'byteplus'),
    ('omnihuman_req_key',         'dreamina_omni_human_v1_5', 'OmniHuman req_key',  'string', 'byteplus'),
    ('omnihuman_action_generate', 'CVSubmitTask', 'OmniHuman submit Action (Volcengine)', 'string', 'byteplus'),
    ('omnihuman_action_query',    'CVGetResult',  'OmniHuman query Action (Volcengine)',  'string', 'byteplus'),
    ('avatar_credit_cost',        '5.00', 'Avatar job credit cost',                 'string', 'pricing')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);
