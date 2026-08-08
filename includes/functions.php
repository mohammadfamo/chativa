<?php
/**
 * توابع کمکی عمومی
 */

declare(strict_types=1);

/**
 * لوگوی برند سایت (سایدبار/صفحات ورود/هدر ادمین) - اگر ادمین از پنل یک لوگو
 * آپلود کرده باشد همان تصویر نمایش داده می‌شود، وگرنه یک مونوگرام ساده از
 * حرف اول نام سایت (app_name()) به‌جایش می‌آید. $extraClass برای اندازه‌های
 * مختلف (مثلاً بزرگ‌تر در صفحات ورود) استفاده می‌شود.
 */
function brand_logo_html(string $extraClass = ''): string
{
    $logoPath = function_exists('app_logo_path') ? app_logo_path() : null;
    $cls = trim('brand-logo ' . $extraClass);
    if ($logoPath !== null) {
        return '<img src="' . e(url($logoPath)) . '" alt="" class="' . e($cls) . ' brand-logo-img">';
    }
    $letter = function_exists('app_name') ? mb_substr(app_name(), 0, 1) : 'C';
    return '<span class="' . e($cls) . '">' . e($letter) . '</span>';
}

/**
 * تگ‌های <link> فاوآیکون + مانیفست، برای درج در <head> هر صفحه. اگر ادمین
 * لوگو آپلود نکرده باشد فقط لینک مانیفست برمی‌گردد (فاوآیکون پیش‌فرض مرورگر).
 * از چند PNG با sizes مختلف به‌جای favicon.ico کلاسیک استفاده می‌شود چون
 * GD (کتابخانه‌ی تصویرِ این پروژه) نمی‌تواند فایل .ico بسازد - این روش در
 * تمام مرورگرهای مدرن به‌درستی کار می‌کند.
 */
function favicon_links_html(): string
{
    $html = '';
    if (function_exists('app_logo_path') && app_logo_path() !== null) {
        foreach ([512, 192, 48, 32, 16] as $size) {
            $href = e(url(BRANDING_DIR . '/favicon-' . $size . '.png'));
            $html .= '<link rel="icon" type="image/png" sizes="' . $size . 'x' . $size . '" href="' . $href . '">' . "\n";
        }
        $html .= '<link rel="apple-touch-icon" href="' . e(url(BRANDING_DIR . '/favicon-180.png')) . '">' . "\n";
    }
    $html .= '<link rel="manifest" href="' . e(url('manifest.php')) . '">' . "\n";
    return $html;
}

/** خروجی امن HTML */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * تبدیل الگوی @username در متنِ از قبل escape‌شده به یک span قابل‌کلیک که
 * با profile.js مودال پروفایل همان کاربر را باز می‌کند. باید همیشه بعد از
 * e() فراخوانی شود (ورودی باید از قبل escape شده باشد، نه خام).
 */
function linkify_mentions(string $escapedHtml): string
{
    return (string) preg_replace(
        '/@([a-zA-Z0-9_]{3,32})/',
        '<span class="msg-mention js-mention" data-username="$1" role="button" tabindex="0">@$1</span>',
        $escapedHtml
    );
}

/**
 * تبدیل لینک‌های http(s) داخل متنِ از قبل escape‌شده به <a target="_blank">
 * قابل‌کلیک، به‌علاوه‌ی همان linkify_mentions() برای @username. باید همیشه
 * بعد از e() فراخوانی شود (ورودی باید از قبل escape شده باشد).
 *
 * ترتیب پردازش عمداً این‌طور است: ابتدا متن بر اساس URL ها تکه‌تکه می‌شود و
 * فقط تکه‌های غیر-URL به linkify_mentions() سپرده می‌شوند - وگرنه اگر یک URL
 * خودش شامل الگوی @username باشد (مثلاً https://x.com/@user)، اجرای
 * linkify_mentions() روی کل متن قبل از لینک‌کردن، وسط آدرس یک span خراب‌کننده
 * درج می‌کرد.
 */
function linkify_message(string $escapedHtml): string
{
    $parts = preg_split('/(https?:\/\/[^\s<>"]+)/i', $escapedHtml, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return linkify_mentions($escapedHtml);
    }

    $out = '';
    foreach ($parts as $i => $part) {
        if ($part === '') {
            continue;
        }
        if ($i % 2 === 1) {
            // این تکه خودِ URL است (به‌لطف PREG_SPLIT_DELIM_CAPTURE) - نشانه‌گذاری
            // پایانی رایج (نقطه/کاما/پرانتز و مشابه) که معمولاً بعد از لینک در
            // جمله می‌آید از داخل لینک جدا می‌شود تا به آدرس نچسبد.
            $cleanUrl = rtrim($part, ".,!?؛:؟)]}");
            $trailing = substr($part, strlen($cleanUrl));
            if ($cleanUrl === '') {
                $out .= $part;
                continue;
            }
            $out .= '<a href="' . $cleanUrl . '" target="_blank" rel="noopener noreferrer nofollow" class="msg-link">'
                . $cleanUrl . '</a>' . $trailing;
        } else {
            $out .= linkify_mentions($part);
        }
    }

    return $out;
}

/** ریدایرکت به یک مسیر داخلی */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** پیام فلش (نمایش یک‌بار مصرف بعد از redirect) */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/** تولید و بررسی توکن CSRF */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/** فقط درخواست‌های POST مجازند و باید CSRF معتبر داشته باشند */
function require_post_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
        http_response_code(403);
        exit('درخواست نامعتبر است.');
    }
}

/** تولید حروف اول نام کاربری برای آواتار */
function initials(string $username): string
{
    $trimmed = trim($username);
    if ($trimmed === '') {
        return '?';
    }
    $mb = mb_substr($trimmed, 0, 1, 'UTF-8');
    return mb_strtoupper($mb, 'UTF-8');
}

/**
 * اجرای یک فایل/متن SQL شامل چند دستور.
 * ابتدا خطوط کامنت (شروع‌شونده با --) به‌طور کامل حذف می‌شوند و سپس بر اساس
 * ; تفکیک می‌شود. اگر کامنت‌ها قبل از تفکیک حذف نشوند، دستوراتی که بعد از یک
 * بلاک کامنت می‌آیند (مثل CREATE TABLE بعد از توضیح جدول) به اشتباه نادیده
 * گرفته می‌شوند.
 */
function run_sql_script(PDO $pdo, string $sql): void
{
    $lines = explode("\n", $sql);
    $cleanLines = array_filter($lines, static function (string $line): bool {
        return !str_starts_with(trim($line), '--');
    });
    $cleaned = implode("\n", $cleanLines);

    foreach (array_filter(array_map('trim', explode(';', $cleaned))) as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
}

/** تبدیل حجم بایت به رشته‌ی خوانا (KB/MB) - برای کارت دانلود سند */
function format_file_size(?int $bytes): string
{
    if ($bytes === null || $bytes <= 0) {
        return '';
    }
    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 0) . ' KB';
    }
    return number_format($bytes / (1024 * 1024), 1) . ' MB';
}

/** پسوند فایل از روی مسیر ذخیره‌شده‌اش (برای برچسب کوچک نوع فایل روی کارت سند) */
function file_extension_of(?string $path): string
{
    if ($path === null) {
        return '';
    }
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    return $ext !== '' ? mb_strtoupper($ext) : '';
}

/** ساخت HTML امن برای نمایش پیوست (عکس/ویدیو/ویس/سند) داخل حباب پیام */
function render_attachment_html(?string $type, ?string $path, ?int $duration, ?string $name = null, ?int $size = null): string
{
    if ($type === null || $path === null) {
        return '';
    }

    $src = e(url($path));

    if ($type === 'image') {
        // data-lightbox روی همین لینک است تا main.js بتواند کلیک را بگیرد و
        // به‌جای باز شدن در تب جدید، پاپ‌آپ اسلایدی را باز کند؛ خودِ href
        // به‌عنوان fallback بدون‌جاوااسکریپت باقی می‌ماند.
        return '<a href="' . $src . '" target="_blank" rel="noopener" class="attachment-image-link" data-lightbox="image">'
            . '<img src="' . $src . '" alt="عکس" class="attachment-image" loading="lazy"></a>';
    }

    if ($type === 'video') {
        // دکمه‌ی کوچک «بزرگ‌نمایی» گوشه‌ی ویدیو - عمداً کلیک روی خودِ ویدیو را
        // نمی‌گیریم تا کنترل‌های پخش پیش‌فرض (پلی/پاز/سیک) دست‌نخورده بماند.
        return '<div class="attachment-video-wrap">'
            . '<video src="' . $src . '" class="attachment-video" controls preload="metadata"></video>'
            . '<button type="button" class="attachment-video-expand" data-lightbox="video" data-src="' . $src . '" title="نمایش بزرگ">'
            . icon_svg('image') . '</button>'
            . '</div>';
    }

    if ($type === 'voice') {
        return '<audio src="' . $src . '" class="attachment-voice" controls preload="metadata"></audio>';
    }

    if ($type === 'document') {
        $displayName = $name !== null && $name !== '' ? $name : 'فایل';
        $meta = trim(file_extension_of($path) . ' · ' . format_file_size($size), ' ·');
        return '<a href="' . $src . '" target="_blank" rel="noopener" download="' . e($displayName) . '" class="attachment-document">'
            . '<span class="attachment-document-icon">' . icon_svg('file') . '</span>'
            . '<span class="attachment-document-info">'
            . '<span class="attachment-document-name">' . e($displayName) . '</span>'
            . '<span class="attachment-document-meta">' . e($meta) . '</span>'
            . '</span>'
            . '<span class="attachment-document-download">' . icon_svg('download') . '</span>'
            . '</a>';
    }

    return '';
}

/** نام نمایشی کاربر (اگر display_name ست نشده باشد، از username استفاده می‌شود) */
function display_name_of(array $user): string
{
    $keys = ['display_name', 'partner_display_name', 'sender_display_name', 'author_display_name'];
    foreach ($keys as $key) {
        if (!empty($user[$key])) {
            return (string) $user[$key];
        }
    }
    $usernameKeys = ['username', 'partner_username', 'sender_username', 'author_username'];
    foreach ($usernameKeys as $key) {
        if (!empty($user[$key])) {
            return (string) $user[$key];
        }
    }
    return '؟';
}

/**
 * رندر HTML آواتار یک کاربر (عکس آپلودشده یا حروف اول با رنگ)، همراه با
 * data-attribute هایی که برای باز کردن مودال پروفایل (assets/js/profile.js)
 * روی هر آواتار در سراسر سایت استفاده می‌شوند.
 *
 * آرایه‌ی ورودی می‌تواند از خروجی current_user()، JOIN های مختلف
 * (partner_*، sender_* و ...) باشد؛ کلیدهای رایج به‌صورت خودکار تشخیص داده می‌شوند.
 */
function avatar_html(array $user, string $extraClass = '', bool $clickable = true, ?bool $online = null): string
{
    // نکته‌ی مهم: وقتی این تابع روی سطر یک پیام (نه خود کاربر) صدا زده می‌شود،
    // آن سطر معمولاً هم ستون id خودِ پیام را دارد و هم user_id/sender_id
    // صاحب پیام را؛ کلیدهای اختصاصی‌تر (user_id/sender_id/...) باید همیشه
    // اولویت داشته باشند تا id (شناسه‌ی پیام) اشتباهی به‌جای شناسه‌ی کاربر
    // استفاده نشود - قبلاً همین اشتباه باعث می‌شد کلیک روی آواتار در چت
    // عمومی پیام «کاربر پیدا نشد» بدهد.
    $userId = (int) ($user['user_id'] ?? $user['sender_id'] ?? $user['partner_id'] ?? $user['author_id'] ?? $user['id'] ?? 0);
    $usernameKeys = ['username', 'partner_username', 'sender_username', 'author_username'];
    $username = '';
    foreach ($usernameKeys as $key) {
        if (!empty($user[$key])) {
            $username = (string) $user[$key];
            break;
        }
    }

    $class = trim('avatar ' . ($clickable ? 'js-avatar' : '') . ' ' . $extraClass);
    $attrs = $clickable && $userId > 0 ? ' data-user-id="' . $userId . '" role="button" tabindex="0"' : '';

    $avatarPathKeys = ['avatar_path', 'partner_avatar_path', 'sender_avatar_path', 'author_avatar_path'];
    $avatarPath = null;
    foreach ($avatarPathKeys as $key) {
        if (!empty($user[$key])) {
            $avatarPath = (string) $user[$key];
            break;
        }
    }

    $statusDot = $online === null ? '' : '<span class="avatar-status-dot ' . ($online ? 'online' : 'offline') . '"></span>';

    if ($avatarPath !== null) {
        $src = e(url($avatarPath));
        return '<span class="' . e($class) . ' avatar-photo"' . $attrs . '>'
            . '<img src="' . $src . '" alt="" loading="lazy">' . $statusDot . '</span>';
    }

    $colorKeys = ['avatar_color', 'partner_color', 'sender_color', 'author_color'];
    $color = '#6C5CE7';
    foreach ($colorKeys as $key) {
        if (!empty($user[$key])) {
            $color = (string) $user[$key];
            break;
        }
    }

    return '<span class="' . e($class) . '" style="background:' . e($color) . '"' . $attrs . '>'
        . e(initials($username !== '' ? $username : '؟')) . $statusDot . '</span>';
}

/** آیا کاربر با توجه به last_seen «آنلاین» محسوب می‌شود (کمتر از ۵ دقیقه از آخرین فعالیت گذشته) */
function is_user_online(?string $lastSeen): bool
{
    if ($lastSeen === null || $lastSeen === '') {
        return false;
    }
    $ts = strtotime($lastSeen);
    return $ts !== false && (time() - $ts) < 300;
}

/**
 * متن وضعیت «آنلاین / آخرین بازدید» یک کاربر - برای زیرعنوان هدر چت خصوصی
 * (messages.php، جایگزین @username ثابت قبلی). همان آستانه‌ی ۵ دقیقه‌ای
 * is_user_online() را برای تشخیص «آنلاین» به کار می‌برد.
 */
function partner_status_label(?string $lastSeen): string
{
    if (is_user_online($lastSeen)) {
        return 'آنلاین';
    }
    if ($lastSeen === null || $lastSeen === '') {
        return 'آخرین بازدید نامشخص';
    }
    $ts = strtotime($lastSeen);
    if ($ts === false) {
        return 'آخرین بازدید نامشخص';
    }

    $time = date('H:i', $ts);
    $today = date('Y-m-d');
    $seenDate = date('Y-m-d', $ts);
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    if ($seenDate === $today) {
        return "آخرین بازدید امروز ساعت {$time}";
    }
    if ($seenDate === $yesterday) {
        return "آخرین بازدید دیروز ساعت {$time}";
    }
    return 'آخرین بازدید ' . date('Y/m/d', $ts) . " ساعت {$time}";
}

/**
 * جعبه‌ی نقل‌قول پیام ریپلای‌شده، بالای متن پیام (مرحله ۷). اگر پیام
 * ریپلایی نداشته باشد رشته‌ی خالی برمی‌گرداند. ساختار HTML این تابع دقیقاً
 * با نسخه‌ی جاوااسکریپتی آن در main.js (تابع replyQuoteHtml) هم‌خوان است.
 * @param array $row باید کلیدهای reply_to_id, reply_body, reply_deleted_at,
 *                    reply_username/reply_display_name را داشته باشد.
 */
function reply_quote_html(array $row): string
{
    $replyToId = $row['reply_to_id'] ?? null;
    if ($replyToId === null) {
        return '';
    }

    $isDeleted = !empty($row['reply_deleted_at']);
    if ($isDeleted) {
        return '<div class="msg-reply-quote" data-reply-id="' . (int) $replyToId . '">'
            . '<span class="msg-reply-quote-author">پیام حذف‌شده</span>'
            . '</div>';
    }

    $author = display_name_of([
        'display_name' => $row['reply_display_name'] ?? null,
        'username'     => $row['reply_username'] ?? null,
    ]);
    $snippet = trim((string) ($row['reply_body'] ?? ''));
    $snippet = mb_strlen($snippet) > 80 ? mb_substr($snippet, 0, 80) . '…' : $snippet;
    if ($snippet === '') {
        $snippet = 'پیوست';
    }

    return '<div class="msg-reply-quote" data-reply-id="' . (int) $replyToId . '">'
        . '<span class="msg-reply-quote-author">' . e($author) . '</span>'
        . '<span class="msg-reply-quote-snippet">' . e($snippet) . '</span>'
        . '</div>';
}

/**
 * نسخه‌ی JSON-پسند اطلاعات پیام ریپلای‌شده (برای اندپوینت‌های fetch_*.php)،
 * دقیقاً با شکلی که main.js's renderMessage() انتظار دارد (msg.reply_to).
 * @return array{id:int, author:string, snippet:string, deleted:bool}|null
 */
function reply_to_payload(array $row): ?array
{
    $replyToId = $row['reply_to_id'] ?? null;
    if ($replyToId === null) {
        return null;
    }

    $isDeleted = !empty($row['reply_deleted_at']);
    if ($isDeleted) {
        return ['id' => (int) $replyToId, 'author' => '', 'snippet' => '', 'deleted' => true];
    }

    $author = display_name_of([
        'display_name' => $row['reply_display_name'] ?? null,
        'username'     => $row['reply_username'] ?? null,
    ]);
    $snippet = trim((string) ($row['reply_body'] ?? ''));
    $snippet = mb_strlen($snippet) > 80 ? mb_substr($snippet, 0, 80) . '…' : $snippet;

    return ['id' => (int) $replyToId, 'author' => $author, 'snippet' => $snippet, 'deleted' => false];
}

/** برچسب «ویرایش‌شده» زیر پیام (مرحله ۷) - اگر ویرایش نشده باشد رشته‌ی خالی. */
function edited_label_html(?string $editedAt): string
{
    if ($editedAt === null) {
        return '';
    }
    $timeLabel = e(date('H:i', strtotime($editedAt)));
    return '<span class="msg-edited-label" title="ویرایش‌شده در ' . $timeLabel . '">ویرایش‌شده</span>';
}

/**
 * قطعه‌متن اطراف نخستین وقوع عبارت جستجو، با هایلایت <mark> روی همان عبارت
 * (مرحله ۱۰ - جستجو در پیام‌ها). چون e() هم روی قطعه‌متن و هم روی خودِ
 * عبارت اجرا می‌شود، جایگزینی‌ِ preg_replace روی نسخه‌ی escape‌شده امن است
 * (کاراکترهای HTML خاص در query عملاً همیشه امن رندر می‌شوند).
 */
function highlight_snippet(string $body, string $term, int $context = 40): string
{
    $body = trim($body);
    $pos = mb_stripos($body, $term);
    if ($pos === false) {
        return e(mb_substr($body, 0, $context * 2));
    }

    $start = max(0, $pos - $context);
    $length = mb_strlen($term) + $context * 2;
    $snippetRaw = mb_substr($body, $start, $length);
    $prefix = $start > 0 ? '…' : '';
    $suffix = ($start + mb_strlen($snippetRaw)) < mb_strlen($body) ? '…' : '';

    $escapedSnippet = e($snippetRaw);
    $escapedTerm = e($term);
    $pattern = '/' . preg_quote($escapedTerm, '/') . '/iu';
    $highlighted = preg_replace($pattern, '<mark>$0</mark>', $escapedSnippet, 1);

    return $prefix . ($highlighted ?? $escapedSnippet) . $suffix;
}

/**
 * ردیف قرص‌های ری‌اکشن زیر یک پیام (مرحله ۱۰). کانتینر حتی وقتی خالی است
 * رندر می‌شود (CSS با :empty مخفی‌اش می‌کند) تا main.js بتواند بعداً
 * innerHTML آن را زنده جایگزین کند بدون نیاز به ساخت/حذف کل عنصر.
 * @param array<int, array{emoji:string, count:int, mine:bool}> $reactions
 */
function reactions_html(array $reactions): string
{
    $pills = '';
    foreach ($reactions as $r) {
        $mineClass = !empty($r['mine']) ? ' mine' : '';
        $pills .= '<button type="button" class="msg-reaction-pill' . $mineClass . '" data-emoji="' . e($r['emoji']) . '">'
            . '<span class="msg-reaction-emoji">' . e($r['emoji']) . '</span>'
            . '<span class="msg-reaction-count">' . (int) $r['count'] . '</span>'
            . '</button>';
    }
    return '<div class="msg-reactions">' . $pills . '</div>';
}

/**
 * نوار «پیام پین‌شده» بالای لیست پیام‌ها (مرحله ۱۰). المان همیشه رندر
 * می‌شود (حتی وقتی چیزی پین نیست) تا main.js با همان id بتواند بعداً
 * محتوایش را زنده به‌روزرسانی کند؛ وقتی پینی نیست فقط کلاس hidden دارد.
 * @param array{id:int, author:string, snippet:string}|null $pinned
 */
function pinned_bar_html(?array $pinned): string
{
    $hiddenClass = $pinned === null ? ' hidden' : '';
    $id = $pinned['id'] ?? 0;
    $author = e($pinned['author'] ?? '');
    $snippet = e($pinned['snippet'] ?? '');
    $sep = ($author !== '' && $snippet !== '') ? ': ' : '';

    return '<div id="pinned-bar" class="pinned-bar' . $hiddenClass . '" data-message-id="' . (int) $id . '">'
        . '<span class="pinned-bar-icon">' . icon_svg('pin') . '</span>'
        . '<span class="pinned-bar-body">'
        . '<span class="pinned-bar-title">پیام پین‌شده</span>'
        . '<span class="pinned-bar-snippet">' . $author . $sep . $snippet . '</span>'
        . '</span>'
        . '<button type="button" class="pinned-bar-close" title="برداشتن پین">' . icon_svg('x') . '</button>'
        . '</div>';
}

/** فرمت زمان نسبی ساده */
function time_ago(string $datetime): string
{
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '';
    }
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'همین الان';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' دقیقه پیش';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' ساعت پیش';
    }
    return date('Y/m/d H:i', $timestamp);
}
