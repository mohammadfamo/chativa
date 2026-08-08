<?php
/**
 * توکن‌های یک‌بارمصرف برای تایید ایمیل و ریست رمز عبور - مرحله ۶
 * -------------------------------------------------
 * خودِ توکن هرگز در دیتابیس ذخیره نمی‌شود؛ فقط هش SHA-256 آن ذخیره
 * می‌شود (مشابه ذخیره‌ی رمز عبور هش‌شده) تا حتی در صورت دسترسی به
 * دیتابیس، توکن‌های فعال قابل استفاده نباشند.
 */

declare(strict_types=1);

const ACTION_TOKEN_PURPOSES = ['email_verify', 'password_reset'];

/** ساخت یک توکن جدید و ذخیره‌ی هش آن. خودِ توکن خام (برای لینک) برگردانده می‌شود. */
function create_action_token(int $userId, string $purpose, int $ttlSeconds): string
{
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);

    $stmt = db()->prepare(
        'INSERT INTO action_tokens (user_id, purpose, token_hash, expires_at) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $purpose, $hash, $expiresAt]);

    return $token;
}

/**
 * بررسی و مصرف یک توکن. اگر معتبر، منقضی‌نشده و مصرف‌نشده باشد، آن را
 * یک‌بارمصرف می‌کند و شناسه‌ی کاربر را برمی‌گرداند؛ در غیر این صورت null.
 */
function consume_action_token(string $token, string $purpose): ?int
{
    if ($token === '' || !in_array($purpose, ACTION_TOKEN_PURPOSES, true)) {
        return null;
    }

    $hash = hash('sha256', $token);

    $stmt = db()->prepare(
        'SELECT id, user_id FROM action_tokens
         WHERE token_hash = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW()'
    );
    $stmt->execute([$hash, $purpose]);
    $row = $stmt->fetch();

    if ($row === false) {
        return null;
    }

    db()->prepare('UPDATE action_tokens SET used_at = NOW() WHERE id = ?')->execute([$row['id']]);

    return (int) $row['user_id'];
}

/** بررسی معتبربودن توکن بدون مصرف آن (برای نمایش فرم قبل از ارسال) */
function action_token_is_valid(string $token, string $purpose): bool
{
    if ($token === '' || !in_array($purpose, ACTION_TOKEN_PURPOSES, true)) {
        return false;
    }

    $hash = hash('sha256', $token);
    $stmt = db()->prepare(
        'SELECT id FROM action_tokens WHERE token_hash = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW()'
    );
    $stmt->execute([$hash, $purpose]);

    return $stmt->fetch() !== false;
}

/** حذف توکن‌های منقضی‌شده‌ی قدیمی (نگه‌داری دیتابیس) */
function cleanup_expired_action_tokens(): void
{
    db()->exec('DELETE FROM action_tokens WHERE expires_at < (NOW() - INTERVAL 7 DAY)');
}
