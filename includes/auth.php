<?php
/**
 * توابع مربوط به احراز هویت
 */

declare(strict_types=1);

const USERNAME_REGEX = '/^[a-zA-Z0-9_]{3,32}$/';
const AVATAR_PALETTE = ['#6C5CE7', '#00B894', '#0984E3', '#E17055', '#D63031', '#00CEC9', '#FD79A8', '#FDCB6E'];

/** نام‌های کاربری حساس/رزروشده که هیچ کاربری (جز خود ادمین از طریق دیتابیس) نمی‌تواند انتخاب کند */
const RESERVED_USERNAMES = [
    'admin', 'administrator', 'root', 'support', 'chativa', 'system', 'moderator', 'mod',
    'help', 'official', 'staff', 'null', 'undefined', 'api', 'www', 'mail', 'webmaster',
    'superuser', 'owner', 'security', 'billing', 'info', 'contact', 'test', 'guest',
];

/** آیا این نام کاربری در فهرست نام‌های حساس/رزروشده است؟ (case-insensitive) */
function is_username_reserved(string $username): bool
{
    return in_array(mb_strtolower(trim($username)), RESERVED_USERNAMES, true);
}

/** آیا این نام کاربری قبلاً توسط کاربر دیگری گرفته شده؟ ($excludeUserId برای نادیده‌گرفتن خود کاربر هنگام ویرایش) */
function is_username_taken(string $username, ?int $excludeUserId = null): bool
{
    if ($excludeUserId !== null) {
        $stmt = db()->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $stmt->execute([$username, $excludeUserId]);
    } else {
        $stmt = db()->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
    }
    return (bool) $stmt->fetch();
}

function current_user(): ?array
{
    static $user = false; // false = هنوز چک نشده

    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $stmt = db()->prepare(
                'SELECT id, username, email, avatar_color, display_name, avatar_path, bio, email_verified_at, is_admin, is_blocked
                 FROM users WHERE id = ?'
            );
            $stmt->execute([$_SESSION['user_id']]);
            $row = $stmt->fetch();
            $user = $row ?: null;

            if ($user !== null) {
                touch_last_seen((int) $user['id']);
            }
        }
    }

    return $user;
}

/** به‌روزرسانی last_seen، حداکثر هر ۶۰ ثانیه یک‌بار در هر session (برای وضعیت «آنلاین») */
function touch_last_seen(int $userId): void
{
    $now = time();
    if (!empty($_SESSION['last_seen_touched_at']) && ($now - (int) $_SESSION['last_seen_touched_at']) < 60) {
        return;
    }
    $_SESSION['last_seen_touched_at'] = $now;

    $stmt = db()->prepare('UPDATE users SET last_seen = NOW() WHERE id = ?');
    $stmt->execute([$userId]);
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function require_guest(): void
{
    if (is_logged_in()) {
        redirect('chat.php');
    }
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && (int) $user['is_admin'] === 1;
}

/** آیا ایمیل این کاربر تایید شده است؟ (آرایه‌ی current_user() یا هر ردیف حاوی email_verified_at) */
function is_email_verified(array $user): bool
{
    return !empty($user['email_verified_at']);
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('دسترسی غیرمجاز.');
    }
}

/**
 * ثبت‌نام کاربر جدید.
 * @return array{0: bool, 1: string, 2: ?int} [موفقیت, پیام خطا, شناسه‌ی کاربر جدید در صورت موفقیت]
 */
function register_user(string $username, string $email, string $password, string $confirm): array
{
    $username = trim($username);
    $email = trim($email);

    if ($username === '' || $email === '' || $password === '') {
        return [false, 'لطفاً همه‌ی فیلدها را پر کنید.', null];
    }
    if (!preg_match(USERNAME_REGEX, $username)) {
        return [false, 'نام کاربری باید ۳ تا ۳۲ کاراکتر و فقط شامل حروف انگلیسی، عدد و آندرلاین باشد.', null];
    }
    if (is_username_reserved($username)) {
        return [false, 'این نام کاربری رزرو شده و قابل استفاده نیست.', null];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [false, 'ایمیل معتبر نیست.', null];
    }
    if (mb_strlen($password) < 6) {
        return [false, 'رمز عبور باید حداقل ۶ کاراکتر باشد.', null];
    }
    if ($password !== $confirm) {
        return [false, 'رمز عبور و تکرار آن یکسان نیستند.', null];
    }

    if (is_username_taken($username)) {
        return [false, 'این نام کاربری قبلاً ثبت شده است.', null];
    }
    $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return [false, 'این ایمیل قبلاً ثبت شده است.', null];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $color = AVATAR_PALETTE[array_rand(AVATAR_PALETTE)];

    // اولین کاربری که ثبت‌نام می‌کند به‌صورت خودکار ادمین می‌شود
    $isFirstUser = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;

    $stmt = db()->prepare(
        'INSERT INTO users (username, email, password_hash, avatar_color, is_admin) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$username, $email, $hash, $color, $isFirstUser ? 1 : 0]);

    return [true, '', (int) db()->lastInsertId()];
}

/**
 * ورود کاربر.
 * @return array{0: bool, 1: string}
 */
function login_user(string $username, string $password): array
{
    $username = trim($username);
    if ($username === '' || $password === '') {
        return [false, 'لطفاً نام کاربری و رمز عبور را وارد کنید.'];
    }

    $stmt = db()->prepare('SELECT id, password_hash, is_blocked FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $username]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['password_hash'])) {
        return [false, 'نام کاربری یا رمز عبور اشتباه است.'];
    }
    if ((int) $row['is_blocked'] === 1) {
        return [false, 'حساب کاربری شما مسدود شده است.'];
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['id'];

    $upd = db()->prepare('UPDATE users SET last_seen = NOW() WHERE id = ?');
    $upd->execute([$row['id']]);

    return [true, ''];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie('PHPSESSID', '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
