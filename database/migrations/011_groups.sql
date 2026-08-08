-- Chativa - Migration 011 (مرحله ۱۰): چت گروهی/کانال
-- گروه‌ها یک نوع سوم چت (در کنار عمومی/خصوصی) هستند؛ برای هماهنگی با
-- الگوی موجود، از همان ستون‌های پیوست/ریپلای/ویرایش/حذف/پین که در
-- messages و private_messages هست استفاده می‌شود.
CREATE TABLE IF NOT EXISTS `groups` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL DEFAULT NULL,
    `avatar_path` VARCHAR(255) NULL DEFAULT NULL,
    `avatar_color` VARCHAR(7) NOT NULL DEFAULT '#7C6CF0',
    `owner_id` INT UNSIGNED NOT NULL,
    `invite_code` VARCHAR(20) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_groups_invite_code` (`invite_code`),
    CONSTRAINT `fk_groups_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `group_members` (
    `group_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `role` ENUM('owner', 'admin', 'member') NOT NULL DEFAULT 'member',
    `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_read_message_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `muted` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`group_id`, `user_id`),
    CONSTRAINT `fk_gm_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_gm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `group_messages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_id` INT UNSIGNED NOT NULL,
    `sender_id` INT UNSIGNED NOT NULL,
    `body` TEXT NOT NULL,
    `reply_to_id` INT UNSIGNED NULL DEFAULT NULL,
    `attachment_type` VARCHAR(10) NULL DEFAULT NULL,
    `attachment_path` VARCHAR(255) NULL DEFAULT NULL,
    `attachment_name` VARCHAR(255) NULL DEFAULT NULL,
    `attachment_size` INT UNSIGNED NULL DEFAULT NULL,
    `attachment_duration` SMALLINT UNSIGNED NULL DEFAULT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    `edited_at` DATETIME NULL DEFAULT NULL,
    `pinned_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_group_messages_group_created` (`group_id`, `created_at`),
    CONSTRAINT `fk_gmsg_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_gmsg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `group_message_hides` (
    `message_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `hidden_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`message_id`, `user_id`),
    CONSTRAINT `fk_gmh_message` FOREIGN KEY (`message_id`) REFERENCES `group_messages` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_gmh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
