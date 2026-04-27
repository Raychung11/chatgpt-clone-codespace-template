-- migrate_seed_llm.sql
-- Updates the LLM prompt-enhancer setting to use BytePlus Seed 2.0 Lite.
-- seed-2-0-lite-260228 is a direct model ID — no custom endpoint needed.
-- Safe to run multiple times (updates only if the key exists).

UPDATE `settings`
SET
    `value` = CASE WHEN `value` = '' THEN 'seed-2-0-lite-260228' ELSE `value` END,
    `label` = 'LLM Model for Prompt Enhancement (e.g. seed-2-0-lite-260228 or seed-2-0-260228)'
WHERE `key` = 'llm_endpoint_id';

-- Add the setting if it doesn't exist yet
INSERT IGNORE INTO `settings` (`key`, `value`, `type`, `label`, `group`)
VALUES ('llm_endpoint_id', 'seed-2-0-lite-260228', 'string',
        'LLM Model for Prompt Enhancement (e.g. seed-2-0-lite-260228 or seed-2-0-260228)',
        'api');

-- Confirm
SELECT `key`, `value`, `label` FROM `settings` WHERE `key` = 'llm_endpoint_id';
