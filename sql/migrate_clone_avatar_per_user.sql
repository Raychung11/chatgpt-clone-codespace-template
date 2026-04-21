-- migrate_clone_avatar_per_user.sql
-- Adds per-user Clone Avatar resource_id to the users table.
-- Run once in phpMyAdmin after migrate_clone_avatar.sql.

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `clone_avatar_resource_id` VARCHAR(200) NULL
        COMMENT 'User''s trained Clone Avatar resource_id from BytePlus'
    AFTER `avatar`;

-- Confirm
SHOW COLUMNS FROM `users` LIKE 'clone_avatar_resource_id';
