-- fix_ark_url.sql
-- Run once in phpMyAdmin to fix the BytePlus ModelArk API URL.
-- ark.ap-southeast.byteplus.com doesn't resolve on many hosts.
-- ark.byteplusapi.com is the correct international endpoint.

UPDATE `settings`
SET `value` = 'https://ark.byteplusapi.com/api/v3'
WHERE `key` = 'byteplus_api_url';

-- Optional: if ark.byteplusapi.com also doesn't resolve via server DNS,
-- run the debug panel on /client/generate.php to get the IP from DoH,
-- then set byteplus_dns_override to that IP:
-- INSERT INTO `settings` (`key`,`value`,`label`,`type`,`group`)
-- VALUES ('byteplus_dns_override','<IP>','ModelArk DNS override IP','string','byteplus')
-- ON DUPLICATE KEY UPDATE `value`=VALUES(`value`);
