<?php
/**
 * ست آیکون‌های SVG رابط کاربری (مرحله ۶).
 * جایگزین ایموجی‌های یونیکد قبلی (📎 😀 🎤 ✔ 🚫 🔓 📞 📵 ➤ ‹ 🌐 🛠 🚪 ...)
 * با آیکون‌های خطی سبک و یکدست که با currentColor رنگ می‌گیرند، پس با
 * تغییر رنگ متن/دکمه به‌طور خودکار رنگشان هم عوض می‌شود.
 *
 * نسخه‌ی جاوااسکریپتی همین آیکون‌ها (برای مواردی که پویا با JS ساخته
 * می‌شوند مثل تیک دیده‌شدن یا آیکون بلاک) در assets/js/icons.js نگه‌داری
 * می‌شود و باید هم‌گام با این فایل بماند.
 */

declare(strict_types=1);

/** خروجی HTML یک آیکون SVG. اگر نام نامعتبر باشد، رشته‌ی خالی برمی‌گرداند. */
function icon_svg(string $name, string $class = ''): string
{
    $icons = [
        'attach' => [
            '24 24',
            '<path d="M17.5 8.5 9.9 16.1a3 3 0 1 1-4.2-4.2l8.5-8.5a2 2 0 1 1 2.8 2.8l-8.1 8.1a1 1 0 1 1-1.4-1.4l7.1-7.1"/>',
        ],
        'smile' => [
            '24 24',
            '<circle cx="12" cy="12" r="9"/><path d="M8 13.5c.8 1.3 2.2 2 4 2s3.2-.7 4-2"/>'
            . '<circle cx="9" cy="9.5" r="1" fill="currentColor" stroke="none"/>'
            . '<circle cx="15" cy="9.5" r="1" fill="currentColor" stroke="none"/>',
        ],
        'mic' => [
            '24 24',
            '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0"/>'
            . '<line x1="12" y1="18" x2="12" y2="21"/><line x1="9" y1="21" x2="15" y2="21"/>',
        ],
        'mic-off' => [
            '24 24',
            '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0"/>'
            . '<line x1="12" y1="18" x2="12" y2="21"/><line x1="9" y1="21" x2="15" y2="21"/>'
            . '<line x1="3.5" y1="3.5" x2="20.5" y2="20.5"/>',
        ],
        'check' => [
            '24 24',
            '<polyline points="4 12.5 9.5 18 20 6"/>',
        ],
        'check-double' => [
            '24 20',
            '<polyline points="1 11 6.5 16.5 15 7"/><polyline points="8.5 11 14 16.5 23 5.5"/>',
        ],
        'ban' => [
            '24 24',
            '<circle cx="12" cy="12" r="9"/><line x1="5.8" y1="18.2" x2="18.2" y2="5.8"/>',
        ],
        'unlock' => [
            '24 24',
            '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 7.5-2"/>',
        ],
        'phone' => [
            '24 24',
            '<path fill="currentColor" stroke="none" d="M6.6 10.8c1.4 2.8 3.8 5.2 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25c1.15.4 2.4.6 3.6.6.6 0 1 .45 1 1V20a1 1 0 0 1-1 1C11.7 21 3 12.3 3 1.6a1 1 0 0 1 1-1h3.4c.55 0 1 .4 1 1 0 1.25.2 2.45.6 3.6a1 1 0 0 1-.25 1z"/>',
        ],
        'phone-hangup' => [
            '24 24',
            '<path transform="rotate(135 12 12)" fill="currentColor" stroke="none" d="M6.6 10.8c1.4 2.8 3.8 5.2 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25c1.15.4 2.4.6 3.6.6.6 0 1 .45 1 1V20a1 1 0 0 1-1 1C11.7 21 3 12.3 3 1.6a1 1 0 0 1 1-1h3.4c.55 0 1 .4 1 1 0 1.25.2 2.45.6 3.6a1 1 0 0 1-.25 1z"/>',
        ],
        'send' => [
            '24 24',
            '<line x1="4" y1="12" x2="20" y2="12"/><polyline points="14 6 20 12 14 18"/>',
        ],
        'chevron-back' => [
            '24 24',
            '<polyline points="15 4 7 12 15 20"/>',
        ],
        'chevron-forward' => [
            '24 24',
            '<polyline points="9 4 17 12 9 20"/>',
        ],
        'globe' => [
            '24 24',
            '<circle cx="12" cy="12" r="9"/><line x1="3" y1="12" x2="21" y2="12"/>'
            . '<path d="M12 3c2.4 2.5 3.8 5.6 3.8 9s-1.4 6.5-3.8 9c-2.4-2.5-3.8-5.6-3.8-9s1.4-6.5 3.8-9z"/>',
        ],
        'user' => [
            '24 24',
            '<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.5-6 8-6s8 2 8 6"/>',
        ],
        'settings' => [
            '24 24',
            '<line x1="4" y1="6" x2="20" y2="6"/><circle cx="9" cy="6" r="2" fill="currentColor" stroke="none"/>'
            . '<line x1="4" y1="12" x2="20" y2="12"/><circle cx="15" cy="12" r="2" fill="currentColor" stroke="none"/>'
            . '<line x1="4" y1="18" x2="20" y2="18"/><circle cx="7" cy="18" r="2" fill="currentColor" stroke="none"/>',
        ],
        'logout' => [
            '24 24',
            '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/>'
            . '<line x1="21" y1="12" x2="9" y2="12"/>',
        ],
        'bolt' => [
            '24 24',
            '<polygon fill="currentColor" stroke="none" points="13 2 4 14 11 14 10 22 20 10 13 10 13 2"/>',
        ],
        'check-circle' => [
            '24 24',
            '<circle cx="12" cy="12" r="9"/><polyline points="8 12.3 11 15.3 16 9"/>',
        ],
        'trash' => [
            '24 24',
            '<path d="M4 7h16"/><path d="M9 7V4h6v3"/>'
            . '<path d="M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13"/>'
            . '<line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
        ],
        'x-circle' => [
            '24 24',
            '<circle cx="12" cy="12" r="9"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/>',
        ],
        'x' => [
            '24 24',
            '<line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/>',
        ],
        'reply' => [
            '24 24',
            '<polyline points="9 6 3 12 9 18"/><path d="M3 12h11a7 7 0 0 1 7 7v1"/>',
        ],
        'edit' => [
            '24 24',
            '<path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3z"/><line x1="13.5" y1="6.5" x2="17.5" y2="10.5"/>',
        ],
        'image' => [
            '24 24',
            '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5" fill="currentColor" stroke="none"/>'
            . '<path d="M21 16l-5.5-5.5a2 2 0 0 0-2.8 0L4 19"/>',
        ],
        'dots' => [
            '24 24',
            '<circle cx="12" cy="5" r="1.4" fill="currentColor" stroke="none"/>'
            . '<circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none"/>'
            . '<circle cx="12" cy="19" r="1.4" fill="currentColor" stroke="none"/>',
        ],
        'camera' => [
            '24 24',
            '<path d="M4 8h3l1.6-2.2A2 2 0 0 1 10.2 5h3.6a2 2 0 0 1 1.6.8L17 8h3a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2z"/><circle cx="12" cy="13.5" r="3.5"/>',
        ],
        'warning' => [
            '24 24',
            '<path d="M12 3.5 21.5 20h-19z"/><line x1="12" y1="9.5" x2="12" y2="14"/>'
            . '<circle cx="12" cy="17" r="1" fill="currentColor" stroke="none"/>',
        ],
        'sun' => [
            '24 24',
            '<circle cx="12" cy="12" r="4.5"/>'
            . '<line x1="12" y1="2" x2="12" y2="4.5"/><line x1="12" y1="19.5" x2="12" y2="22"/>'
            . '<line x1="2" y1="12" x2="4.5" y2="12"/><line x1="19.5" y1="12" x2="22" y2="12"/>'
            . '<line x1="4.9" y1="4.9" x2="6.6" y2="6.6"/><line x1="17.4" y1="17.4" x2="19.1" y2="19.1"/>'
            . '<line x1="4.9" y1="19.1" x2="6.6" y2="17.4"/><line x1="17.4" y1="6.6" x2="19.1" y2="4.9"/>',
        ],
        'moon' => [
            '24 24',
            '<path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5z"/>',
        ],
        'pin' => [
            '24 24',
            '<path d="M12 2c-2.8 0-5 2.2-5 5 0 3.5 5 11 5 11s5-7.5 5-11c0-2.8-2.2-5-5-5z"/><circle cx="12" cy="7" r="2"/>',
        ],
        'bell-off' => [
            '24 24',
            '<path d="M6 8a6 6 0 0 1 6-6c1.2 0 2.3.35 3.2.95"/>'
            . '<path d="M18 8a6 6 0 0 1 1 3.3V13c0 2 .5 3.2 1.5 4.5H4"/>'
            . '<path d="M9 19a3 3 0 0 0 5.5 1.5"/><line x1="3.5" y1="3.5" x2="20.5" y2="20.5"/>',
        ],
        'search' => [
            '24 24',
            '<circle cx="10.5" cy="10.5" r="6.5"/><line x1="15.3" y1="15.3" x2="21" y2="21"/>',
        ],
        'file' => [
            '24 24',
            '<path d="M6 2h8l6 6v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/><path d="M14 2v6h6"/>',
        ],
        'users' => [
            '24 24',
            '<circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c0-3.5 3-5.5 6.5-5.5s6.5 2 6.5 5.5"/>'
            . '<circle cx="17" cy="9" r="2.6"/><path d="M15.5 14.2c2.7.4 5 2.2 5 5.8"/>',
        ],
        'download' => [
            '24 24',
            '<path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M4 19h16"/>',
        ],
        'link' => [
            '24 24',
            '<path d="M9.5 14.5 14.5 9.5"/>'
            . '<path d="M11 6.5 12.4 5a3.7 3.7 0 0 1 5.2 5.2l-1.5 1.4"/>'
            . '<path d="M13 17.5 11.6 19a3.7 3.7 0 0 1-5.2-5.2l1.5-1.4"/>',
        ],
        'crown' => [
            '24 24',
            '<path d="M3 8l4 3 5-6 5 6 4-3-2 10H5z"/><line x1="5" y1="21" x2="19" y2="21"/>',
        ],
        'monitor' => [
            '24 24',
            '<rect x="3" y="4" width="18" height="13" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
        ],
    ];

    if (!isset($icons[$name])) {
        return '';
    }

    [$viewBox, $inner] = $icons[$name];
    $fillOnly = in_array($name, ['phone', 'phone-hangup', 'bolt'], true);
    $baseAttrs = $fillOnly
        ? 'fill="currentColor" stroke="none"'
        : 'fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"';

    $classAttr = trim('icon-svg ' . $class);

    return '<svg class="' . e($classAttr) . '" viewBox="0 0 ' . e($viewBox) . '" ' . $baseAttrs . ' aria-hidden="true" focusable="false">'
        . $inner . '</svg>';
}
