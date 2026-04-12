-- fix_byteplus_url.sql
-- Run once in phpMyAdmin to correct the typo in the stored Vision AI URL.
-- 'bytepluses.com' → 'byteplus.com'

UPDATE `settings`
SET `value` = 'https://visual.ap-southeast-1.byteplus.com'
WHERE `key` = 'vision_ai_url'
  AND `value` LIKE '%bytepluses%';

-- Also fix BytePlus Ark URL if stored incorrectly
UPDATE `settings`
SET `value` = REPLACE(`value`, 'bytepluses.com', 'byteplus.com')
WHERE `value` LIKE '%bytepluses.com%';
