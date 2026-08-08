-- Chativa - Stage 1 Database Schema
-- Compatible with MySQL 5.7+ / MariaDB 10+
-- Character set: utf8mb4 (full emoji & Persian/Arabic support for future stages)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------
-- Table: users
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(32) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `avatar_color` VARCHAR(7) NOT NULL DEFAULT '#6C5CE7',
    `display_name` VARCHAR(50) NULL DEFAULT NULL COMMENT 'نام نمایشی؛ اگر خالی باشد از username استفاده می‌شود',
    `avatar_path` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مسیر عکس پروفایل آپلودشده؛ اگر خالی باشد از رنگ+حرف اول استفاده می‌شود',
    `bio` VARCHAR(160) NULL DEFAULT NULL,
    `email_verified_at` DATETIME NULL DEFAULT NULL,
    `last_read_public_message_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'برای شمارش پیام خوانده‌نشده‌ی چت عمومی',
    `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
    `is_blocked` TINYINT(1) NOT NULL DEFAULT 0,
    `last_seen` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`),
    UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Table: messages  (چت عمومی)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `body` TEXT NOT NULL,
    `reply_to_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'ریپلای به یک پیام دیگر - مرحله ۷',
    `attachment_type` VARCHAR(10) NULL DEFAULT NULL COMMENT 'image, video, voice, document',
    `attachment_path` VARCHAR(255) NULL DEFAULT NULL,
    `attachment_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نام اصلی فایل - فقط برای document لازم است',
    `attachment_size` INT UNSIGNED NULL DEFAULT NULL,
    `attachment_duration` SMALLINT UNSIGNED NULL DEFAULT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL COMMENT 'حذف دوطرفه (برای همه)',
    `edited_at` DATETIME NULL DEFAULT NULL COMMENT 'زمان آخرین ویرایش - مرحله ۷',
    `pinned_at` DATETIME NULL DEFAULT NULL COMMENT 'زمان پین‌شدن پیام - مرحله ۱۰',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_messages_created_at` (`created_at`),
    CONSTRAINT `fk_messages_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_messages_reply_to` FOREIGN KEY (`reply_to_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Table: conversations  (چت خصوصی بین دو نفر - Stage 2)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `conversations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_a_id` INT UNSIGNED NOT NULL COMMENT 'شناسه کوچک‌تر',
    `user_b_id` INT UNSIGNED NOT NULL COMMENT 'شناسه بزرگ‌تر',
    `user_a_blocked` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'user_a این گفتگو را بلاک کرده',
    `user_b_blocked` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'user_b این گفتگو را بلاک کرده',
    `user_a_hidden_at` DATETIME NULL DEFAULT NULL COMMENT 'زمان حذف مکالمه از سایدبار توسط user_a (فقط برای خودش)',
    `user_b_hidden_at` DATETIME NULL DEFAULT NULL COMMENT 'زمان حذف مکالمه از سایدبار توسط user_b (فقط برای خودش)',
    `user_a_muted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'user_a این مکالمه را بی‌صدا کرده',
    `user_b_muted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'user_b این مکالمه را بی‌صدا کرده',
    `user_a_pinned_at` DATETIME NULL DEFAULT NULL COMMENT 'زمان پین‌کردن مکالمه توسط user_a',
    `user_b_pinned_at` DATETIME NULL DEFAULT NULL COMMENT 'زمان پین‌کردن مکالمه توسط user_b',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_conversation_pair` (`user_a_id`, `user_b_id`),
    CONSTRAINT `fk_conv_user_a` FOREIGN KEY (`user_a_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_conv_user_b` FOREIGN KEY (`user_b_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Table: private_messages  (پیام‌های چت خصوصی - Stage 2)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `private_messages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `conversation_id` INT UNSIGNED NOT NULL,
    `sender_id` INT UNSIGNED NOT NULL,
    `body` TEXT NOT NULL,
    `reply_to_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'ریپلای به یک پیام دیگر - مرحله ۷',
    `attachment_type` VARCHAR(10) NULL DEFAULT NULL COMMENT 'image, video, voice, document',
    `attachment_path` VARCHAR(255) NULL DEFAULT NULL,
    `attachment_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نام اصلی فایل - فقط برای document لازم است',
    `attachment_size` INT UNSIGNED NULL DEFAULT NULL,
    `attachment_duration` SMALLINT UNSIGNED NULL DEFAULT NULL,
    `seen_at` DATETIME NULL DEFAULT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL COMMENT 'حذف دوطرفه (برای همه)',
    `edited_at` DATETIME NULL DEFAULT NULL COMMENT 'زمان آخرین ویرایش - مرحله ۷',
    `pinned_at` DATETIME NULL DEFAULT NULL COMMENT 'زمان پین‌شدن پیام - مرحله ۱۰',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pm_conversation` (`conversation_id`, `id`),
    CONSTRAINT `fk_pm_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pm_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pm_reply_to` FOREIGN KEY (`reply_to_id`) REFERENCES `private_messages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Table: typing_status  (نشانگر «در حال تایپ...» - Stage 4)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `typing_status` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `scope` VARCHAR(10) NOT NULL COMMENT 'public یا private',
    `conversation_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 یعنی چت عمومی (بدون گفتگوی خاص)',
    `user_id` INT UNSIGNED NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_typing_scope_conv_user` (`scope`, `conversation_id`, `user_id`),
    KEY `idx_typing_updated_at` (`updated_at`),
    CONSTRAINT `fk_typing_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Table: action_tokens  (تایید ایمیل / ریست رمز عبور - مرحله ۶)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `action_tokens` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `purpose` VARCHAR(20) NOT NULL COMMENT 'email_verify یا password_reset',
    `token_hash` CHAR(64) NOT NULL COMMENT 'sha256 توکن؛ خود توکن ذخیره نمی‌شود',
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_action_tokens_hash` (`token_hash`),
    KEY `idx_action_tokens_user` (`user_id`, `purpose`),
    CONSTRAINT `fk_action_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Table: message_hides  (حذف یک‌طرفه پیام چت عمومی - مرحله ۶)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `message_hides` (
    `message_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `hidden_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`message_id`, `user_id`),
    CONSTRAINT `fk_mh_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Table: private_message_hides  (حذف یک‌طرفه پیام خصوصی - مرحله ۶)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `private_message_hides` (
    `message_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `hidden_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`message_id`, `user_id`),
    CONSTRAINT `fk_pmh_message` FOREIGN KEY (`message_id`) REFERENCES `private_messages` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pmh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Table: groups / group_members / group_messages / group_message_hides
-- (چت گروهی/کانال - مرحله ۱۰)
-- ---------------------------------------------------------
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

-- ---------------------------------------------------------
-- Table: message_reactions  (ری‌اکشن ایموجی به پیام - مرحله ۱۰)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `message_reactions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `scope` ENUM('public', 'private') NOT NULL,
    `message_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `emoji` VARCHAR(16) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_scope_message_user` (`scope`, `message_id`, `user_id`),
    KEY `idx_scope_message` (`scope`, `message_id`),
    CONSTRAINT `fk_message_reactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Table: settings  (تنظیمات کلی سایت - Stage 4)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(64) NOT NULL,
    `setting_value` TEXT NULL,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Table: schema_migrations  (برای آپدیت خودکار نصب‌های قبلی)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `migration` VARCHAR(190) NOT NULL,
    `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_migration` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
