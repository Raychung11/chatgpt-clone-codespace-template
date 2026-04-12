-- fix_byteplus_url.sql
-- Run once in phpMyAdmin to set the correct Vision AI base URL.
-- cv.byteplusapi.com is the BytePlus international CV endpoint.
-- Service=cv, Region=ap-singapore-1, Version=2024-06-06, signing=Volcengine V4 HMAC-SHA256.

UPDATE `settings`
SET `value` = 'https://cv.byteplusapi.com'
WHERE `key` = 'vision_ai_url';
