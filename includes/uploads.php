<?php
/**
 * مدیریت آپلود امن پیوست‌ها (عکس، ویدیو، ویس)
 */

declare(strict_types=1);

const UPLOAD_LIMITS = [
    'image'    => 5 * 1024 * 1024,   // 5MB
    'video'    => 30 * 1024 * 1024,  // 30MB
    'voice'    => 10 * 1024 * 1024,  // 10MB
    'avatar'   => 3 * 1024 * 1024,   // 3MB
    'document' => 20 * 1024 * 1024,  // 20MB
];

const UPLOAD_MIME_MAP = [
    'image' => [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ],
    'avatar' => [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ],
    'video' => [
        'video/mp4'       => 'mp4',
        'video/webm'      => 'webm',
        'video/quicktime' => 'mov',
        'video/ogg'       => 'ogv',
    ],
    'voice' => [
        'audio/webm' => 'webm',
        // libmagic گاهی فایل‌های صوتی webm بدون ترک ویدیو را video/webm تشخیص می‌دهد
        'video/webm' => 'webm',
        'audio/ogg'  => 'ogg',
        'video/ogg'  => 'ogg',
        'audio/mpeg' => 'mp3',
        'audio/mp4'  => 'm4a',
        'audio/wav'  => 'wav',
        'audio/x-wav' => 'wav',
    ],
    // اسناد/فایل‌های عمومی - مرحله ۱۰ (چیزی غیر از عکس/ویدیو/ویس)
    'document' => [
        'application/pdf'                                                        => 'pdf',
        'application/msword'                                                     => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel'                                               => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'      => 'xlsx',
        'application/vnd.ms-powerpoint'                                          => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/zip'              => 'zip',
        'application/x-zip-compressed' => 'zip',
        'application/x-rar-compressed' => 'rar',
        'application/vnd.rar'          => 'rar',
        'application/x-7z-compressed'  => '7z',
        'application/json'             => 'json',
        'text/plain'                   => 'txt',
        'text/csv'                     => 'csv',
    ],
];

class UploadException extends RuntimeException
{
}

/**
 * حداکثر حجم مجاز (بایت) برای یک نوع پیوست - اگر ادمین از پنل مقداری برایش
 * تنظیم کرده باشد همان استفاده می‌شود، وگرنه مقدار پیش‌فرض UPLOAD_LIMITS.
 * (تابع get_setting در includes/settings.php تعریف شده که بعد از این فایل
 * لود می‌شود، اما چون اینجا فقط در لحظه‌ی آپلود واقعی صدا زده می‌شود، مشکلی
 * برای ترتیب include ایجاد نمی‌کند.)
 */
function upload_max_bytes(string $kind): int
{
    $defaultMb = (int) (UPLOAD_LIMITS[$kind] / 1024 / 1024);
    if (function_exists('upload_limit_mb')) {
        return upload_limit_mb($kind, $defaultMb) * 1024 * 1024;
    }
    return UPLOAD_LIMITS[$kind];
}

/**
 * اعتبارسنجی و ذخیره‌ی یک فایل آپلودشده.
 *
 * @param array  $file آرایه‌ی مربوط به $_FILES['xxx']
 * @param string $kind یکی از image|video|voice|document|avatar
 * @return array{type: string, path: string, size: int, original_name: string}
 * @throws UploadException در صورت نامعتبر بودن فایل
 */
function save_uploaded_file(array $file, string $kind): array
{
    if (!in_array($kind, ['image', 'video', 'voice', 'avatar', 'document'], true)) {
        throw new UploadException('نوع پیوست نامعتبر است.');
    }

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
            throw new UploadException(
                'حجم فایل از محدودیت سرور (upload_max_filesize) بیشتر است. با مدیر سرور تماس بگیرید.'
            );
        }
        throw new UploadException('آپلود فایل با خطا مواجه شد.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new UploadException('درخواست آپلود نامعتبر است.');
    }

    $maxSize = upload_max_bytes($kind);
    if ((int) $file['size'] <= 0 || (int) $file['size'] > $maxSize) {
        $maxMb = (int) ($maxSize / 1024 / 1024);
        throw new UploadException("حجم فایل نباید بیشتر از {$maxMb} مگابایت باشد.");
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = UPLOAD_MIME_MAP[$kind];
    if (!isset($allowedMimes[$realMime])) {
        throw new UploadException('فرمت فایل پشتیبانی نمی‌شود.');
    }

    // برای عکس/آواتار، یک بار دیگر با getimagesize مطمئن می‌شویم واقعاً تصویر است
    if (in_array($kind, ['image', 'avatar'], true) && @getimagesize($file['tmp_name']) === false) {
        throw new UploadException('فایل تصویری معتبر نیست.');
    }

    $extension = $allowedMimes[$realMime];
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $subdirMap = ['image' => 'images', 'video' => 'videos', 'voice' => 'voice', 'avatar' => 'avatars', 'document' => 'documents'];
    $subdir = $subdirMap[$kind];
    $targetDir = ROOT_PATH . '/uploads/' . $subdir;

    if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new UploadException('پوشه‌ی آپلود در دسترس نیست.');
    }

    $targetPath = $targetDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new UploadException('ذخیره‌ی فایل با خطا مواجه شد.');
    }

    // نام اصلی فایل (برای کارت دانلود سند) - پاک‌سازی از کاراکترهای کنترلی/مسیر
    $originalName = isset($file['name']) ? basename((string) $file['name']) : '';
    $originalName = preg_replace('/[\x00-\x1F\x7F]/', '', $originalName) ?? '';
    if (mb_strlen($originalName) > 150) {
        $originalName = mb_substr($originalName, 0, 150);
    }

    return [
        'type'          => $kind,
        'path'          => 'uploads/' . $subdir . '/' . $filename,
        'size'          => (int) $file['size'],
        'original_name' => $originalName,
    ];
}
