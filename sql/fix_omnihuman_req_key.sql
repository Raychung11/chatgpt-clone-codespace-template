-- fix_omnihuman_req_key.sql
-- Run once in phpMyAdmin to update OmniHuman req_key to the correct value from official docs.
-- Source: https://docs.byteplus.com/en/docs/byteplus-vision/omnihuman-video_generation
--
-- Correct req_key for OmniHuman 1.5 Video Generation: realman_avatar_picture_omni15_cv
-- (the old dreamina_omni_human_v1_5 is not supported on cv.byteplusapi.com)

UPDATE `settings`
SET `value` = 'realman_avatar_picture_omni15_cv',
    `label` = 'OmniHuman req_key (Video Generation)'
WHERE `key` = 'omnihuman_req_key';
