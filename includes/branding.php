<?php
/**
 * لوگوی سایت + تولید فاوآیکون در سایزهای مختلف از یک PNG ۵۱۲×۵۱۲ آپلودشده.
 * (بخش ۳ - تنظیمات برندینگ پنل ادمین)
 *
 * چون GD نمی‌تواند فایل .ico کلاسیک بسازد، به‌جای favicon.ico از چند
 * <link rel="icon" type="image/png" sizes="..."> با اندازه‌های مختلف
 * استفاده می‌شود - رویکرد استانداردِ مدرن که همه‌ی مرورگرهای امروزی
 * پشتیبانی می‌کنند (فقط سافاری‌های خیلی قدیمی به favicon.ico نیاز داشتند).
 */

declare(strict_types=1);

const BRANDING_DIR = 'uploads/branding';
const FAVICON_SIZES = [16, 32, 48, 180, 192, 512];

class BrandingException extends RuntimeException
{
}

/**
 * آپلود لوگوی سایت (باید PNG باشد) - یک نسخه‌ی مستر ۵۱۲×۵۱۲ ذخیره می‌شود و
 * از روی همان، فاوآیکون در سایزهای FAVICON_SIZES تولید می‌شود.
 * @return string مسیر نسبی فایل مستر (برای ذخیره در setting site_logo_path)
 * @throws BrandingException
 */
function save_site_logo(array $file): string
{
    if (!extension_loaded('gd')) {
        throw new BrandingException('افزونه‌ی GD در PHP این سرور فعال نیست؛ امکان پردازش تصویر لوگو وجود ندارد.');
    }

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new BrandingException('آپلود فایل با خطا مواجه شد.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new BrandingException('درخواست آپلود نامعتبر است.');
    }

    $maxSize = 5 * 1024 * 1024; // 5MB برای فایل لوگو کافی است
    if ((int) $file['size'] <= 0 || (int) $file['size'] > $maxSize) {
        throw new BrandingException('حجم فایل لوگو نباید بیشتر از ۵ مگابایت باشد.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ($realMime !== 'image/png') {
        throw new BrandingException('لوگو باید یک فایل PNG باشد.');
    }

    $sourceImage = @imagecreatefrompng($file['tmp_name']);
    if ($sourceImage === false) {
        throw new BrandingException('فایل PNG معتبر نیست.');
    }

    $targetDir = ROOT_PATH . '/' . BRANDING_DIR;
    if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        imagedestroy($sourceImage);
        throw new BrandingException('پوشه‌ی آپلود لوگو در دسترس نیست.');
    }

    foreach (FAVICON_SIZES as $size) {
        $resized = imagecreatetruecolor($size, $size);
        // حفظ شفافیت (alpha channel) پی‌ان‌جی - وگرنه پس‌زمینه‌ی شفاف مشکی می‌شود
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefilledrectangle($resized, 0, 0, $size, $size, $transparent);

        imagecopyresampled(
            $resized,
            $sourceImage,
            0,
            0,
            0,
            0,
            $size,
            $size,
            (int) imagesx($sourceImage),
            (int) imagesy($sourceImage)
        );

        imagepng($resized, $targetDir . '/favicon-' . $size . '.png');
        imagedestroy($resized);
    }

    imagedestroy($sourceImage);

    // نسخه‌ی ۵۱۲ همان فایلی است که هم به‌عنوان لوگوی برند (سایدبار/صفحات ورود)
    // و هم به‌عنوان بزرگ‌ترین فاوآیکون استفاده می‌شود - نیازی به فایل مستر جدا نیست.
    return BRANDING_DIR . '/favicon-512.png';
}

/** حذف تمام فایل‌های لوگو/فاوآیکون قبلی (وقتی ادمین لوگو را برمی‌دارد) */
function delete_site_logo(): void
{
    foreach (FAVICON_SIZES as $size) {
        $path = ROOT_PATH . '/' . BRANDING_DIR . '/favicon-' . $size . '.png';
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
