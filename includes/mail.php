<?php
/**
 * ارسال ایمیل با PHP mail() ساده - مرحله ۶
 * -------------------------------------------------
 * برای تایید ایمیل حساب و ریست رمز عبور استفاده می‌شود. روی اکثر
 * هاست‌های cPanel بدون تنظیم اضافه کار می‌کند. اگر ارسال با خطا مواجه شود
 * (مثلاً هاست mail() را غیرفعال کرده)، false برمی‌گردد و صفحه‌ی فراخوان
 * باید پیام مناسب نشان دهد - چیزی کرش نمی‌کند.
 */

declare(strict_types=1);

/** آدرس کامل یک مسیر داخلی (برای لینک داخل ایمیل - برخلاف url() که نسبی است) */
function full_url(string $path): string
{
    return app_public_url() . '/' . ltrim($path, '/');
}

/** ارسال یک ایمیل HTML ساده. در صورت موفقیت true برمی‌گرداند. */
function send_app_mail(string $to, string $subject, string $htmlBody): bool
{
    $fromEmail = get_setting('mail_from_address', '');
    $fromName = get_setting('mail_from_name', app_name());

    if ($fromEmail === '' || $fromEmail === null) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // حذف پورت احتمالی (مثل localhost:8000) چون در ایمیل معتبر نیست
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        $fromEmail = 'no-reply@' . $host;
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . mb_encode_mimeheader((string) $fromName, 'UTF-8') . ' <' . $fromEmail . '>',
        'X-Mailer: ' . app_name(),
    ];

    $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8');

    try {
        return @mail($to, $encodedSubject, $htmlBody, implode("\r\n", $headers));
    } catch (Throwable $e) {
        error_log('Chativa mail() failed: ' . $e->getMessage());
        return false;
    }
}

/** ساخت توکن تایید ایمیل و ارسال لینک آن به کاربر. در صورت موفقیت در ارسال، true. */
function send_verification_email(int $userId, string $email, string $usernameForGreeting): bool
{
    $token = create_action_token($userId, 'email_verify', 60 * 60 * 24); // ۲۴ ساعت اعتبار
    $link = full_url('verify_email.php?token=' . urlencode($token));

    $appName = app_name();
    $html = render_mail_template(
        "تایید ایمیل حساب {$appName}",
        "سلام {$usernameForGreeting}،\n\nبرای فعال‌سازی کامل حساب و امکان ارسال پیام، ایمیل خود را تایید کنید. این لینک تا ۲۴ ساعت معتبر است.",
        'تایید ایمیل',
        $link
    );

    return send_app_mail($email, "تایید ایمیل حساب {$appName}", $html);
}

/** ساخت توکن ریست رمز عبور و ارسال لینک آن. در صورت موفقیت در ارسال، true. */
function send_password_reset_email(int $userId, string $email, string $usernameForGreeting): bool
{
    $token = create_action_token($userId, 'password_reset', 60 * 60); // ۱ ساعت اعتبار
    $link = full_url('reset_password.php?token=' . urlencode($token));

    $appName = app_name();
    $html = render_mail_template(
        "ریست رمز عبور {$appName}",
        "سلام {$usernameForGreeting}،\n\nبرای تعیین رمز عبور جدید روی دکمه‌ی زیر کلیک کنید. این لینک تا ۱ ساعت معتبر است. اگر این درخواست را شما نداده‌اید، این ایمیل را نادیده بگیرید.",
        'تعیین رمز عبور جدید',
        $link
    );

    return send_app_mail($email, "ریست رمز عبور {$appName}", $html);
}

/** قالب مشترک ساده برای ایمیل‌های سیستمی (تایید ایمیل، ریست رمز) */
function render_mail_template(string $title, string $message, string $buttonText, string $buttonUrl): string
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $safeButtonText = htmlspecialchars($buttonText, ENT_QUOTES, 'UTF-8');
    $safeButtonUrl = htmlspecialchars($buttonUrl, ENT_QUOTES, 'UTF-8');

    return <<<HTML
    <div dir="rtl" style="font-family: Tahoma, Arial, sans-serif; max-width: 480px; margin: 0 auto; padding: 24px; background: #f5f5f7;">
      <div style="background: #ffffff; border-radius: 12px; padding: 28px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <h2 style="color: #6C5CE7; margin-top: 0;">{$safeTitle}</h2>
        <p style="color: #333; font-size: 14px; line-height: 1.8;">{$safeMessage}</p>
        <p style="text-align: center; margin: 28px 0;">
          <a href="{$safeButtonUrl}" style="background:#6C5CE7;color:#fff;text-decoration:none;padding:12px 28px;border-radius:8px;font-size:14px;display:inline-block;">{$safeButtonText}</a>
        </p>
        <p style="color: #999; font-size: 11.5px; word-break: break-all;">اگر دکمه کار نکرد، این لینک را در مرورگر باز کنید:<br>{$safeButtonUrl}</p>
      </div>
    </div>
    HTML;
}
