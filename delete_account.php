<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_login();

$me = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('settings.php');
}

require_post_csrf();

if (!isset($_POST['confirm_wipe'])) {
    set_flash('error', 'برای حذف حساب، ابتدا تیک تایید را بزنید.');
    redirect('settings.php');
}

$userId = (int) $me['id'];

// جمع‌آوری مسیر تمام فایل‌های آپلودشده‌ی این کاربر (آواتار + پیوست پیام‌های
// عمومی و خصوصی) قبل از حذف ردیف‌های دیتابیس - چون بعد از DELETE (که با
// CASCADE ردیف‌ها را هم پاک می‌کند)، دیگر امکان پیدا کردن مسیر فایل‌ها نیست.
$filePaths = [];

if (!empty($me['avatar_path'])) {
    $filePaths[] = (string) $me['avatar_path'];
}

$stmt = db()->prepare('SELECT attachment_path FROM messages WHERE user_id = ? AND attachment_path IS NOT NULL');
$stmt->execute([$userId]);
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $path) {
    $filePaths[] = (string) $path;
}

$stmt = db()->prepare('SELECT attachment_path FROM private_messages WHERE sender_id = ? AND attachment_path IS NOT NULL');
$stmt->execute([$userId]);
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $path) {
    $filePaths[] = (string) $path;
}

foreach ($filePaths as $path) {
    $absolute = ROOT_PATH . '/' . ltrim($path, '/');
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

// حذف ردیف کاربر - تمام جدول‌های وابسته (پیام‌ها، مکالمات و پیام‌های خصوصی
// درون آن‌ها، توکن‌ها، وضعیت تایپ، حذف‌های شخصی و ...) با ON DELETE CASCADE
// به‌طور خودکار پاک می‌شوند.
db()->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);

logout_user();
set_flash('success', 'حساب کاربری شما و تمام پیام‌ها و فایل‌های آن برای همیشه حذف شد.');
redirect('login.php');
