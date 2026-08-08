-- مرحله ۲: چت خصوصی بین دو نفر + پنل ادمین
-- این فایل فقط برای نصب‌های قبلی (مرحله ۱) اجرا می‌شود.
-- نصب‌های تازه این جداول را مستقیماً از database/schema.sql می‌گیرند.

CREATE TABLE IF NOT EXISTS `conversations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_a_id` INT UNSIGNED NOT NULL,
    `user_b_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_conversation_pair` (`user_a_id`, `user_b_id`),
    CONSTRAINT `fk_conv_user_a` FOREIGN KEY (`user_a_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_conv_user_b` FOREIGN KEY (`user_b_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `private_messages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `conversation_id` INT UNSIGNED NOT NULL,
    `sender_id` INT UNSIGNED NOT NULL,
    `body` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pm_conversation` (`conversation_id`, `id`),
    CONSTRAINT `fk_pm_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pm_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
