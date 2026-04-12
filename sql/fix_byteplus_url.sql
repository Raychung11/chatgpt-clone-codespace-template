-- fix_byteplus_url.sql
-- Run once in phpMyAdmin to set the correct Vision AI base URL.
-- visual.volcengineapi.com is the Volcano Engine international endpoint
-- (byteplus.com subdomains may not resolve on all hosting providers).

UPDATE `settings`
SET `value` = 'https://visual.volcengineapi.com'
WHERE `key` = 'vision_ai_url';
