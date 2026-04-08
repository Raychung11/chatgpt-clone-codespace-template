-- migrate_llm_prompt.sql
-- Run once in phpMyAdmin.

-- 1. Add enhanced_prompt column to video_jobs
ALTER TABLE `video_jobs`
    ADD COLUMN IF NOT EXISTS `enhanced_prompt` TEXT NULL
        COMMENT 'LLM-rewritten prompt actually sent to BytePlus API'
        AFTER `prompt`;

-- 2. Add LLM settings
INSERT INTO `settings` (`key`, `value`, `label`, `type`, `group`)
VALUES
    ('llm_endpoint_id', '',  'LLM Text Model Endpoint ID (for prompt enhancement)',    'text', 'byteplus'),
    ('llm_enabled',     '1', 'Enable AI prompt enhancement (1=yes, 0=no)',             'text', 'byteplus')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);
