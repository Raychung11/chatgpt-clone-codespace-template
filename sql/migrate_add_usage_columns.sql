-- ============================================================
-- Migration: add token/cost tracking columns to video_jobs
-- Run once in phpMyAdmin if you already imported schema.sql
-- ============================================================

ALTER TABLE `video_jobs`
    ADD COLUMN IF NOT EXISTS `tokens_used`  INT UNSIGNED  NULL COMMENT 'API tokens consumed' AFTER `error_message`,
    ADD COLUMN IF NOT EXISTS `api_cost_usd` DECIMAL(10,6) NULL COMMENT 'Estimated API cost USD' AFTER `tokens_used`;
