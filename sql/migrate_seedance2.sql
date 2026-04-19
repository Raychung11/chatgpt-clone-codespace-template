-- migrate_seedance2.sql
-- Adds Seedance 2.0 endpoint settings and the i2v endpoint setting.
-- Safe to run multiple times (INSERT IGNORE).

INSERT IGNORE INTO `settings` (`key`, `value`, `type`, `label`, `group`) VALUES
-- Image-to-Video endpoint (used for 30s Ad feature, clips 2 & 3)
('byteplus_endpoint_id_i2v', '', 'string', 'BytePlus Endpoint ID — I2V (ep-xxx)', 'api'),
-- Seedance 2.0 endpoints (create in ModelArk → Online inference)
-- These override the 5s / 10s / i2v endpoints when set.
-- Leave blank to continue using Seedance 1.5 endpoints.
('byteplus_endpoint_id_s2_5s',  '', 'string', 'BytePlus Endpoint ID — Seedance 2.0 Lite T2V 5s (ep-xxx)',  'api'),
('byteplus_endpoint_id_s2_10s', '', 'string', 'BytePlus Endpoint ID — Seedance 2.0 Pro T2V 10s (ep-xxx)', 'api'),
('byteplus_endpoint_id_s2_i2v', '', 'string', 'BytePlus Endpoint ID — Seedance 2.0 Pro I2V (ep-xxx)',     'api');

-- Confirm
SELECT `key`, `value`, `label` FROM `settings`
WHERE `key` LIKE 'byteplus_endpoint_id%'
ORDER BY `key`;
