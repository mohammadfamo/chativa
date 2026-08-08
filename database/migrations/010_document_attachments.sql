-- Chativa - Migration 010 (مرحله ۱۰): ارسال فایل/سند (غیر از عکس/ویدیو/ویس)
-- attachment_name نام اصلی فایل را نگه می‌دارد (برای عکس/ویدیو/ویس استفاده
-- نمی‌شود، اما برای نمایش کارت دانلود سند لازم است - نام فایل ذخیره‌شده روی
-- دیسک تصادفی/هش‌شده است، نه نام اصلی).
ALTER TABLE `messages`
    ADD COLUMN `attachment_name` VARCHAR(255) NULL DEFAULT NULL AFTER `attachment_path`;
ALTER TABLE `private_messages`
    ADD COLUMN `attachment_name` VARCHAR(255) NULL DEFAULT NULL AFTER `attachment_path`;
