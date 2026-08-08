-- مرحله ۳: ارسال عکس، ویدیو و ویس
-- این فایل فقط برای نصب‌های قبلی (مرحله ۱ و ۲) اجرا می‌شود.

ALTER TABLE `messages`
    ADD COLUMN `attachment_type` VARCHAR(10) NULL DEFAULT NULL COMMENT 'image, video, voice' AFTER `body`,
    ADD COLUMN `attachment_path` VARCHAR(255) NULL DEFAULT NULL AFTER `attachment_type`,
    ADD COLUMN `attachment_size` INT UNSIGNED NULL DEFAULT NULL AFTER `attachment_path`,
    ADD COLUMN `attachment_duration` SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `attachment_size`;

ALTER TABLE `private_messages`
    ADD COLUMN `attachment_type` VARCHAR(10) NULL DEFAULT NULL COMMENT 'image, video, voice' AFTER `body`,
    ADD COLUMN `attachment_path` VARCHAR(255) NULL DEFAULT NULL AFTER `attachment_type`,
    ADD COLUMN `attachment_size` INT UNSIGNED NULL DEFAULT NULL AFTER `attachment_path`,
    ADD COLUMN `attachment_duration` SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `attachment_size`;
