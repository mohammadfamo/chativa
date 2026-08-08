<?php
/**
 * تنظیمات کلی سایت (key-value) — برای مثال حالت تعمیر و نگهداری
 */

declare(strict_types=1);

function get_setting(string $key, ?string $default = null): ?string
{
    static $cache = [];

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();

    $cache[$key] = $value !== false ? $value : $default;
    return $cache[$key];
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

function is_maintenance_mode(): bool
{
    return get_setting('maintenance_mode', '0') === '1';
}

/** آیا ارسال پیام فقط برای کاربران با ایمیل تایید‌شده مجاز است؟ (تنظیم پنل ادمین) */
function email_verification_required(): bool
{
    return get_setting('require_email_verification', '0') === '1';
}

/** آیا ثبت‌نام کاربران جدید باز است؟ (تنظیم پنل ادمین) */
function registration_open(): bool
{
    return get_setting('registration_enabled', '1') === '1';
}

/** نام سایت/برند - قابل تغییر از پنل ادمین (پیش‌فرض: Chativa) - در title صفحات، برند سایدبار/هدر ادمین و ایمیل‌ها استفاده می‌شود */
function app_name(): string
{
    $name = trim((string) get_setting('site_name', ''));
    return $name !== '' ? $name : 'Chativa';
}

/**
 * آدرس عمومی سایت - اگر ادمین از پنل مقداری وارد کرده باشد همان استفاده
 * می‌شود (مثلاً پشت پروکسی/CDN که تشخیص خودکار HTTP_HOST درست نیست)،
 * وگرنه به‌صورت خودکار از full_base_url() (تشخیص زنده از روی درخواست فعلی) گرفته می‌شود.
 */
function app_public_url(): string
{
    $configured = trim((string) get_setting('site_url', ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    return rtrim(full_base_url(), '/');
}

/** مسیر لوگوی آپلودشده‌ی سایت (نسبی) یا null اگر ادمین لوگو آپلود نکرده - برای فاوآیکون/site.webmanifest/برند */
function app_logo_path(): ?string
{
    $path = trim((string) get_setting('site_logo_path', ''));
    return $path !== '' ? $path : null;
}

/**
 * حداکثر حجم مجاز آپلود برای یک نوع پیوست (بایت) - قابل تنظیم از پنل ادمین،
 * با مقدار پیش‌فرض همان ثابت‌های UPLOAD_LIMITS در includes/uploads.php.
 */
function upload_limit_mb(string $kind, int $defaultMb): int
{
    $value = (int) get_setting('upload_limit_' . $kind . '_mb', (string) $defaultMb);
    return $value > 0 ? $value : $defaultMb;
}
