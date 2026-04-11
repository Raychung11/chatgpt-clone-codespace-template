-- Migration: add byteplus_endpoint_id_10s setting
-- Run once in phpMyAdmin or via CLI:  mysql -u root videosaas < sql/migrate_endpoint_10s.sql
--
-- BytePlus Seedance Lite (ep-xxx) only produces 5-second videos.
-- A separate Pro model endpoint (ep-yyy) is required for 10-second videos.
-- Set the value in Admin → Settings → API after creating a Pro endpoint in ModelArk.

INSERT INTO `settings` (`key`, `value`, `type`, `label`, `group`)
VALUES ('byteplus_endpoint_id_10s', '', 'string', 'BytePlus Endpoint ID — 10s Pro (ep-xxx)', 'api')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);
