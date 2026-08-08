-- مرحله ۶: پروفایل کاربری، حذف پیام، تایید ایمیل، ریست رمز عبور
-- این فایل فقط برای نصب‌های قبلی اجرا می‌شود.

ALTER TABLE `users`
    ADD COLUMN `display_name` VARCHAR(50) NULL DEFAULT NULL AFTER `avatar_color`,
    ADD COLUMN `avatar_path` VARCHAR(255) NULL DEFAULT NULL AFTER `display_name`,
    ADD COLUMN `bio` VARCHAR(160) NULL DEFAULT NULL AFTER `avatar_path`,
    ADD COLUMN `email_verified_at` DATETIME NULL DEFAULT NULL AFTER `bio`,
    ADD COLUMN `last_read_public_message_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `email_verified_at`;

ALTER TABLE `messages`
    ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL AFTER `attachment_duration`;

ALTER TABLE `private_messages`
    ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL AFTER `seen_at`;

CREATE TABLE IF NOT EXISTS `action_tokens` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `purpose` VARCHAR(20) NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_action_tokens_hash` (`token_hash`),
    KEY `idx_action_tokens_user` (`user_id`, `purpose`),
    CONSTRAINT `fk_action_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `message_hides` (
    `message_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `hidden_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`message_id`, `user_id`),
    CONSTRAINT `fk_mh_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `private_message_hides` (
    `message_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `hidden_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`message_id`, `user_id`),
    CONSTRAINT `fk_pmh_message` FOREIGN KEY (`message_id`) REFERENCES `private_messages` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pmh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
