-- مرحله ۴: Seen، Typing، بلاک در سطح گفتگو، تنظیمات سایت
-- این فایل فقط برای نصب‌های قبلی اجرا می‌شود.

ALTER TABLE `conversations`
    ADD COLUMN `user_a_blocked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `user_b_id`,
    ADD COLUMN `user_b_blocked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `user_a_blocked`;

ALTER TABLE `private_messages`
    ADD COLUMN `seen_at` DATETIME NULL DEFAULT NULL AFTER `attachment_duration`;

CREATE TABLE IF NOT EXISTS `typing_status` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `scope` VARCHAR(10) NOT NULL,
    `conversation_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `user_id` INT UNSIGNED NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_typing_scope_conv_user` (`scope`, `conversation_id`, `user_id`),
    KEY `idx_typing_updated_at` (`updated_at`),
    CONSTRAINT `fk_typing_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(64) NOT NULL,
    `setting_value` TEXT NULL,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
