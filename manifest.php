<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

/**
 * site.webmanifest به‌صورت پویا (نه یک فایل استاتیک) - چون نام/آیکون سایت از
 * پنل ادمین قابل تغییر است، این مسیر همیشه آخرین مقدار را برمی‌گرداند.
 * صفحات با <link rel="manifest" href="manifest.php"> به این مسیر اشاره می‌کنند.
 */
header('Content-Type: application/manifest+json; charset=utf-8');

$icons = [];
if (app_logo_path() !== null) {
    foreach ([192, 512] as $size) {
        $icons[] = [
            'src'   => url(BRANDING_DIR . '/favicon-' . $size . '.png'),
            'sizes' => $size . 'x' . $size,
            'type'  => 'image/png',
        ];
    }
}

$name = app_name();

echo json_encode([
    'name'             => $name,
    'short_name'       => mb_substr($name, 0, 12),
    'start_url'        => url(''),
    'display'          => 'standalone',
    'background_color' => '#ffffff',
    'theme_color'      => '#6C5CE7',
    'icons'            => $icons,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
