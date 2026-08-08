-- Chativa - Migration 007 (مرحله ۸): حذف مکالمه از سایدبار (برای من) + حذف کامل مکالمه
-- برای نصب‌های موجود؛ اسکیمای تازه از database/schema.sql ساخته می‌شود.

ALTER TABLE `conversations`
    ADD COLUMN `user_a_hidden_at` DATETIME NULL DEFAULT NULL AFTER `user_b_blocked`,
    ADD COLUMN `user_b_hidden_at` DATETIME NULL DEFAULT NULL AFTER `user_a_hidden_at`;
