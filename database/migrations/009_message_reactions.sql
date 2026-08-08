-- Chativa - Migration 009 (مرحله ۱۰): ری‌اکشن ایموجی به پیام
-- یک جدول مشترک برای هر دو نوع پیام (عمومی/خصوصی) با ستون scope؛ هر کاربر
-- برای هر پیام حداکثر یک ری‌اکشن فعال دارد (کلیک روی همان ایموجی = حذف،
-- کلیک روی ایموجی دیگر = جایگزینی - دقیقاً مثل رفتار ساده‌ی تلگرام/واتساپ).
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
