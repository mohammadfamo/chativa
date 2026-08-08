<?php
/**
 * Chativa - فایل اصلی پیکربندی (Bootstrap)
 * سازگار با PHP 8+ | بدون URL هاردکد | قابل اجرا روی لوکال و هاست cPanel
 */

declare(strict_types=1);

// ---------------------------------------------------------
// نمایش خطاها (در Production باید false شود)
// ---------------------------------------------------------
define('APP_DEBUG', false);

// همیشه همه‌ی خطاها گزارش می‌شوند (برای ثبت در لاگ)، فقط نمایش‌شان کنترل می‌شود.
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

$logDir = dirname(__DIR__) . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
if (is_dir($logDir) && is_writable($logDir)) {
    ini_set('error_log', $logDir . '/php-error.log');
}
// اگر پوشه‌ی logs قابل نوشتن نبود، PHP از لاگ پیش‌فرض سرور (معمولاً در cPanel > Metrics > Errors) استفاده می‌کند.

date_default_timezone_set('Asia/Tehran');

// ---------------------------------------------------------
// شروع Session (قبل از هر خروجی)
// ---------------------------------------------------------
// نکته‌ی مهم برای هاست‌های cPanel: با open_basedir فعال، مسیر پیش‌فرض
// session.save_path سرور (مثلاً /var/cpanel/php/sessions/ea-phpXX یا /tmp)
// اغلب خارج از دسترسی اکانت است. در این حالت session_start() بدون هیچ
// خطای قابل‌مشاهده‌ای اجرا می‌شود، اما نوشتن سشن روی دیسک بی‌صدا شکست
// می‌خورد - نتیجه‌اش دقیقاً همین علامت است: لاگین با موفقیت اعتبارسنجی
// می‌شود ولی در ریکوئست بعدی session گم شده و کاربر بدون هیچ خطایی به
// صفحه‌ی ورود برمی‌گردد. برای رفع این مشکل، یک پوشه‌ی سشن اختصاصی و
// قابل‌نوشتن داخل خود پروژه در نظر گرفته شده که همیشه داخل open_basedir
// اکانت است؛ اگر قابل‌نوشتن باشد به‌جای مسیر پیش‌فرض سرور استفاده می‌شود.
$sessionDir = dirname(__DIR__) . '/sessions';
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0755, true);
}
if (is_dir($sessionDir) && is_writable($sessionDir)) {
    session_save_path($sessionDir);
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---------------------------------------------------------
// مسیرهای پروژه
// ---------------------------------------------------------
define('ROOT_PATH', dirname(__DIR__));

/**
 * تشخیص خودکار مسیر پایه‌ی پروژه بدون هاردکد کردن دامنه یا مسیر.
 * چه در ریشه‌ی دامنه نصب شده باشد (example.com) و چه در ساب‌فولدر
 * (example.com/chativa)، و چه فایل جاری در ریشه‌ی پروژه باشد چه داخل
 * زیرپوشه‌هایی مثل api/ یا admin/، همیشه مسیر درست را برمی‌گرداند.
 */
function base_path(): string
{
    static $path = null;
    if ($path !== null) {
        return $path;
    }

    // روش اصلی: مقایسه‌ی مسیر واقعی پوشه‌ی پروژه با ریشه‌ی وب‌سرور.
    // این روش مستقل از عمق فایل جاری (ریشه، api/، admin/ و ...) کار می‌کند.
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if ($docRoot !== '') {
        $docRootReal = str_replace('\\', '/', realpath($docRoot) ?: $docRoot);
        $projectReal = str_replace('\\', '/', realpath(ROOT_PATH) ?: ROOT_PATH);
        $docRootReal = rtrim($docRootReal, '/');

        if ($projectReal !== '' && str_starts_with($projectReal, $docRootReal)) {
            $path = rtrim(substr($projectReal, strlen($docRootReal)), '/');
            return $path;
        }
    }

    // Fallback: اگر DOCUMENT_ROOT در دسترس نبود، بر اساس مسیر اسکریپت فعلی حدس می‌زنیم
    // (برای فایل‌های ریشه‌ی پروژه درست کار می‌کند).
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $path = rtrim($scriptDir, '/');
    return $path;
}

/** ساخت URL نسبی درست برای اَست‌ها و لینک‌های داخلی */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $base = base_path();
    return ($base === '' ? '' : $base) . '/' . $path;
}

/** آدرس کامل فعلی (برای redirect بعد از لاگین و ...) */
function full_base_url(): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host . base_path();
}

// ---------------------------------------------------------
// بررسی نصب بودن پروژه
// ---------------------------------------------------------
define('DB_CONFIG_FILE', ROOT_PATH . '/config/db_config.php');

if (!file_exists(DB_CONFIG_FILE)) {
    // اگر همین الان روی install.php نیستیم، به آن هدایت شو
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($current !== 'install.php') {
        header('Location: ' . url('install.php'));
        exit;
    }
} else {
    define('DB_CONFIG', require DB_CONFIG_FILE);
}

// ---------------------------------------------------------
// بارگذاری هسته‌ی برنامه
// ---------------------------------------------------------
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/icons.php';

if (defined('DB_CONFIG')) {
    require_once ROOT_PATH . '/includes/db.php';
    require_once ROOT_PATH . '/includes/auth.php';
    require_once ROOT_PATH . '/includes/conversations.php';
    require_once ROOT_PATH . '/includes/groups.php';
    require_once ROOT_PATH . '/includes/uploads.php';
    require_once ROOT_PATH . '/includes/branding.php';
    require_once ROOT_PATH . '/includes/typing.php';
    require_once ROOT_PATH . '/includes/settings.php';
    require_once ROOT_PATH . '/includes/realtime.php';
    require_once ROOT_PATH . '/includes/moderation.php';
    require_once ROOT_PATH . '/includes/reactions.php';
    require_once ROOT_PATH . '/includes/action_tokens.php';
    require_once ROOT_PATH . '/includes/mail.php';
    require_once ROOT_PATH . '/includes/migrate.php';
    run_pending_migrations();

    // ---------------------------------------------------------
    // حالت تعمیر و نگهداری (ادمین‌ها همیشه دسترسی دارند)
    // ---------------------------------------------------------
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $isApiRequest = str_contains($scriptName, '/api/');
    $isAdminRequest = str_contains($scriptName, '/admin/');
    $isInternalRequest = str_contains($scriptName, '/api/internal/');
    $currentScript = basename($scriptName);
    $maintenanceExempt = $isAdminRequest
        || $isInternalRequest
        || in_array($currentScript, ['install.php', 'login.php', 'logout.php'], true);

    if (is_maintenance_mode() && !$maintenanceExempt && !is_admin()) {
        if ($isApiRequest) {
            http_response_code(503);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'maintenance']);
        } else {
            http_response_code(503);
            require ROOT_PATH . '/includes/partials/maintenance.php';
        }
        exit;
    }
}
