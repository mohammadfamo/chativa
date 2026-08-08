-- Chativa - Migration 008 (مرحله ۱۰): بی‌صدا کردن و پین‌کردن مکالمه + پین پیام
-- برای نصب‌های موجود؛ اسکیمای تازه از database/schema.sql ساخته می‌شود.

ALTER TABLE `conversations`
    ADD COLUMN `user_a_muted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'user_a این مکالمه را بی‌صدا کرده' AFTER `user_b_hidden_at`,
    ADD COLUMN `user_b_muted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'user_b این مکالمه را بی‌صدا کرده' AFTER `user_a_muted`,
    ADD COLUMN `user_a_pinned_at` DATETIME NULL DEFAULT NULL COMMENT 'زمان پین‌کردن مکالمه توسط user_a' AFTER `user_b_muted`,
    ADD COLUMN `user_b_pinned_at` DATETIME NULL DEFAULT NULL COMMENT 'زمان پین‌کردن مکالمه توسط user_b' AFTER `user_a_pinned_at`;

ALTER TABLE `messages`
    ADD COLUMN `pinned_at` DATETIME NULL DEFAULT NULL AFTER `edited_at`;

ALTER TABLE `private_messages`
    ADD COLUMN `pinned_at` DATETIME NULL DEFAULT NULL AFTER `edited_at`;
