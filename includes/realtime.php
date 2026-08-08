<?php
/**
 * لایه‌ی Real-Time (مرحله ۵)
 *
 * این فایل پل بین PHP (منبع حقیقت/دیتابیس) و سرور Node.js (پخش‌کننده‌ی
 * بلادرنگ پیام‌ها) است. اگر Real-Time فعال نباشد یا سرور Node در دسترس
 * نباشد، تمام توابع این فایل بی‌صدا شکست می‌خورند و بقیه‌ی برنامه با
 * polling معمولی کار می‌کند — هیچ نقطه‌ای از این فایل نباید یک درخواست
 * را fail کند.
 */

declare(strict_types=1);

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    $padded = str_pad($data, strlen($data) + (4 - strlen($data) % 4) % 4, '=');
    return base64_decode(strtr($padded, '-_', '+/')) ?: '';
}

/** رشته‌ی مخفی مشترک بین PHP و سرور Node (تولید و ذخیره‌ی خودکار در اولین استفاده) */
function realtime_secret(): string
{
    $secret = get_setting('realtime_secret');
    if ($secret === null || $secret === '') {
        $secret = bin2hex(random_bytes(32));
        set_setting('realtime_secret', $secret);
    }
    return $secret;
}

function is_realtime_enabled(): bool
{
    return get_setting('realtime_enabled', '0') === '1' && realtime_base_url() !== null;
}

/** آدرس پایه‌ی سرور Node، مثل https://chat.example.com (بدون / انتهایی) */
function realtime_base_url(): ?string
{
    $url = trim((string) get_setting('realtime_base_url', ''));
    if ($url === '') {
        return null;
    }
    return rtrim($url, '/');
}

/** آدرسی که مرورگر باید برای اتصال WebSocket استفاده کند */
function realtime_ws_url(): ?string
{
    $base = realtime_base_url();
    if ($base === null) {
        return null;
    }
    // http -> ws ، https -> wss
    return preg_replace('/^http/', 'ws', $base) . '/ws';
}

/**
 * ساخت توکن امضاشده برای اتصال WebSocket.
 * فرمت: base64url(payload) + '.' + base64url(hmac-sha256(payload))
 * این فرمت باید دقیقاً با تابع verifyToken در node-server/server.js هم‌خوان بماند.
 */
function mint_ws_token(int $userId, string $username, int $ttlSeconds = 300): string
{
    $payload = json_encode([
        'uid' => $userId,
        'un'  => $username,
        'exp' => time() + $ttlSeconds,
    ], JSON_UNESCAPED_UNICODE);

    $payloadB64 = base64url_encode($payload);
    $signature = hash_hmac('sha256', $payloadB64, realtime_secret(), true);

    return $payloadB64 . '.' . base64url_encode($signature);
}

/**
 * ارسال یک رویداد به سرور Node برای پخش بلادرنگ.
 * $room: 'public' یا 'conversation:123'
 * این تابع هرگز نباید باعث خطا در جریان اصلی برنامه شود.
 */
function broadcast(string $room, string $event, array $payload): void
{
    if (!is_realtime_enabled()) {
        return;
    }

    $url = realtime_base_url() . '/broadcast';
    $body = json_encode(['room' => $room, 'event' => $event, 'payload' => $payload], JSON_UNESCAPED_UNICODE);

    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nX-Internal-Secret: " . realtime_secret() . "\r\n",
            'content'       => $body,
            'timeout'       => 1.5,
            'ignore_errors' => true,
        ],
    ]);

    try {
        @file_get_contents($url, false, $context);
    } catch (Throwable $e) {
        // بی‌صدا رد شو؛ عدم دسترسی به سرور Node نباید چت را از کار بیندازد
        error_log('Chativa broadcast failed: ' . $e->getMessage());
    }
}
