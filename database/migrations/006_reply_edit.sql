-- Chativa - Migration 006 (مرحله ۷): ریپلای + ویرایش پیام
-- برای نصب‌های موجود؛ اسکیمای تازه از database/schema.sql ساخته می‌شود.

ALTER TABLE `messages`
    ADD COLUMN `reply_to_id` INT UNSIGNED NULL DEFAULT NULL AFTER `body`,
    ADD COLUMN `edited_at` DATETIME NULL DEFAULT NULL AFTER `deleted_at`;

ALTER TABLE `messages`
    ADD CONSTRAINT `fk_messages_reply_to` FOREIGN KEY (`reply_to_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL;

ALTER TABLE `private_messages`
    ADD COLUMN `reply_to_id` INT UNSIGNED NULL DEFAULT NULL AFTER `body`,
    ADD COLUMN `edited_at` DATETIME NULL DEFAULT NULL AFTER `deleted_at`;

ALTER TABLE `private_messages`
    ADD CONSTRAINT `fk_pm_reply_to` FOREIGN KEY (`reply_to_id`) REFERENCES `private_messages` (`id`) ON DELETE SET NULL;
