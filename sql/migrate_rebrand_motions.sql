-- migrate_rebrand_motions.sql
-- Run this once in phpMyAdmin to rebrand the live database from VideoSaaS → Motions.
-- Safe to run multiple times (ON DUPLICATE KEY UPDATE).

UPDATE `settings` SET `value` = 'Motions' WHERE `key` = 'site_name';
UPDATE `settings` SET `value` = 'Motions' WHERE `key` = 'mail_from_name';

-- Confirm
SELECT `key`, `value` FROM `settings` WHERE `key` IN ('site_name','mail_from_name');
