<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_admin();

$activeAdminTab = 'settings';

// نگاشت هر فرم به تبی که باید بعد از ذخیره روی آن برگردیم - تجربه‌ی کاربری
// بهتر از قبل که همیشه بعد از ذخیره به بالای صفحه (تب اول) پرت می‌شدیم.
$formToTab = [
    'branding'             => 'branding',
    'branding_remove_logo' => 'branding',
    'realtime'             => 'realtime',
    'registration'         => 'registration',
    'upload_limits'        => 'registration',
    'email_verification'   => 'email',
    'maintenance'          => 'maintenance',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $form = (string) ($_POST['form'] ?? '');

    if ($form === 'branding') {
        $siteName = trim((string) ($_POST['site_name'] ?? ''));
        $siteUrl = trim((string) ($_POST['site_url'] ?? ''));
        set_setting('site_name', $siteName);
        set_setting('site_url', rtrim($siteUrl, '/'));

        if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $logoPath = save_site_logo($_FILES['site_logo']);
                set_setting('site_logo_path', $logoPath);
                set_flash('success', 'برندینگ سایت (نام/آدرس/لوگو) ذخیره شد.');
            } catch (BrandingException $e) {
                set_flash('error', $e->getMessage());
            }
        } else {
            set_flash('success', 'نام و آدرس سایت ذخیره شد.');
        }
    } elseif ($form === 'branding_remove_logo') {
        delete_site_logo();
        set_setting('site_logo_path', '');
        set_flash('success', 'لوگوی سایت حذف شد؛ به‌جای آن مونوگرام حرف اول نام سایت نمایش داده می‌شود.');
    } elseif ($form === 'registration') {
        $regEnabled = isset($_POST['registration_enabled']) ? '1' : '0';
        set_setting('registration_enabled', $regEnabled);
        set_flash('success', 'تنظیمات ثبت‌نام ذخیره شد.');
    } elseif ($form === 'upload_limits') {
        foreach (['image', 'video', 'voice', 'document', 'avatar'] as $kind) {
            $mb = (int) ($_POST['upload_limit_' . $kind . '_mb'] ?? 0);
            if ($mb > 0 && $mb <= 500) {
                set_setting('upload_limit_' . $kind . '_mb', (string) $mb);
            }
        }
        set_flash('success', 'محدودیت حجم آپلود ذخیره شد.');
    } elseif ($form === 'maintenance') {
        $maintenance = isset($_POST['maintenance_mode']) ? '1' : '0';
        $message = trim((string) ($_POST['maintenance_message'] ?? ''));

        set_setting('maintenance_mode', $maintenance);
        if ($message !== '') {
            set_setting('maintenance_message', $message);
        }

        set_flash('success', 'تنظیمات تعمیر و نگهداری ذخیره شد.');
    } elseif ($form === 'realtime') {
        $enabled = isset($_POST['realtime_enabled']) ? '1' : '0';
        $baseUrl = trim((string) ($_POST['realtime_base_url'] ?? ''));
        $turnUrl = trim((string) ($_POST['turn_url'] ?? ''));
        $turnUsername = trim((string) ($_POST['turn_username'] ?? ''));
        $turnCredential = trim((string) ($_POST['turn_credential'] ?? ''));

        set_setting('realtime_enabled', $enabled);
        set_setting('realtime_base_url', rtrim($baseUrl, '/'));
        set_setting('turn_url', $turnUrl);
        set_setting('turn_username', $turnUsername);
        set_setting('turn_credential', $turnCredential);

        set_flash('success', 'تنظیمات Real-Time ذخیره شد.');
    } elseif ($form === 'email_verification') {
        $required = isset($_POST['require_email_verification']) ? '1' : '0';
        set_setting('require_email_verification', $required);
        set_flash('success', 'تنظیمات تایید ایمیل ذخیره شد.');
    }

    redirect('admin/settings.php?tab=' . ($formToTab[$form] ?? 'branding'));
}

$allowedTabs = ['branding', 'realtime', 'registration', 'email', 'maintenance', 'system'];
$activeTab = (string) ($_GET['tab'] ?? 'branding');
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'branding';
}

$siteName = get_setting('site_name', '');
$siteUrl = get_setting('site_url', '');
$logoPath = app_logo_path();

$maintenanceMode = is_maintenance_mode();
$maintenanceMessage = get_setting('maintenance_message', 'سایت موقتاً در حال تعمیر و نگهداری است. لطفاً کمی بعد دوباره سر بزنید.');
$requireEmailVerification = email_verification_required();
$registrationEnabled = registration_open();

$uploadKindLabels = [
    'image'    => 'عکس',
    'video'    => 'ویدیو',
    'voice'    => 'پیام صوتی',
    'document' => 'فایل/سند',
    'avatar'   => 'آواتار',
];
$uploadLimits = [];
foreach ($uploadKindLabels as $kind => $label) {
    $uploadLimits[$kind] = upload_limit_mb($kind, (int) (UPLOAD_LIMITS[$kind] / 1024 / 1024));
}

$realtimeEnabled = get_setting('realtime_enabled', '0') === '1';
$realtimeBaseUrl = get_setting('realtime_base_url', '');
$turnUrl = get_setting('turn_url', '');
$turnUsername = get_setting('turn_username', '');
$turnCredential = get_setting('turn_credential', '');
$secret = realtime_secret();
$phpBaseUrlForNode = full_base_url();

// اطلاعات سیستم
$dbSizeMb = (float) (db()->query(
    "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema = DATABASE()"
)->fetchColumn() ?: 0);

function dir_size_mb(string $dir): float
{
    if (!is_dir($dir)) {
        return 0.0;
    }
    $bytes = 0;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($items as $item) {
        if ($item->isFile()) {
            $bytes += $item->getSize();
        }
    }
    return round($bytes / 1024 / 1024, 2);
}

$uploadsSizeMb = dir_size_mb(ROOT_PATH . '/uploads');

$flashes = get_flashes();

$tabDefs = [
    'branding'     => ['label' => 'برندینگ سایت', 'icon' => 'image'],
    'realtime'     => ['label' => 'Real-Time', 'icon' => 'bolt'],
    'registration' => ['label' => 'ثبت‌نام و آپلود', 'icon' => 'users'],
    'email'        => ['label' => 'ایمیل و امنیت', 'icon' => 'check-circle'],
    'maintenance'  => ['label' => 'تعمیر و نگهداری', 'icon' => 'settings'],
    'system'       => ['label' => 'اطلاعات سیستم', 'icon' => 'monitor'],
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= favicon_links_html() ?>
<script src="<?= e(url('assets/js/theme.js')) ?>"></script>
<title>تنظیمات | <?= e(app_name()) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="admin-page" id="page-content">
  <?php require ROOT_PATH . '/includes/partials/admin_header.php'; ?>

  <main class="admin-content">
    <?php foreach ($flashes as $flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>

    <nav class="settings-tabs">
      <?php foreach ($tabDefs as $key => $def): ?>
        <a href="<?= e(url('admin/settings.php?tab=' . $key)) ?>" class="settings-tab-link <?= $activeTab === $key ? 'active' : '' ?>">
          <?= icon_svg($def['icon']) ?>
          <span><?= e($def['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="settings-tab-panel <?= $activeTab === 'branding' ? '' : 'hidden' ?>">
      <div class="settings-box">
        <h2 class="admin-section-title"><?= icon_svg('image') ?> نام، آدرس و لوگوی سایت</h2>
        <p class="settings-hint">
          این‌ها هرجای سایت که لازم باشد استفاده می‌شوند: عنوان تب مرورگر، برند سایدبار و صفحات ورود/ثبت‌نام،
          موضوع ایمیل‌های سیستمی (تایید ایمیل/ریست رمز) و فاوآیکون/site.webmanifest.
        </p>
        <form method="post" class="settings-form" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="form" value="branding">
          <label>نام سایت
            <input type="text" name="site_name" value="<?= e($siteName) ?>" placeholder="Chativa" maxlength="60">
          </label>
          <label>آدرس عمومی سایت (اختیاری - اگر خالی بماند، آدرس فعلی به‌طور خودکار تشخیص داده می‌شود)
            <input type="text" name="site_url" value="<?= e($siteUrl) ?>" placeholder="https://example.com" dir="ltr">
          </label>

          <div class="settings-logo-row">
            <div class="settings-logo-preview">
              <?= brand_logo_html('settings-logo-preview-img') ?>
            </div>
            <div class="settings-logo-field">
              <label>آپلود لوگو (PNG مربع، ترجیحاً ۵۱۲×۵۱۲ پیکسل)
                <input type="file" name="site_logo" accept="image/png">
              </label>
              <p class="settings-hint">
                از همین یک فایل، هم به‌عنوان لوگوی برند سایت و هم برای ساخت فاوآیکون در سایزهای مختلف
                (۱۶ تا ۵۱۲ پیکسل) و site.webmanifest به‌صورت خودکار استفاده می‌شود.
              </p>
            </div>
          </div>

          <button type="submit" class="btn btn-primary" style="width:auto;">ذخیره برندینگ</button>
        </form>

        <?php if ($logoPath !== null): ?>
          <form method="post" data-confirm="لوگوی سایت حذف شود و به مونوگرام حرف اول نام سایت برگردد؟" style="margin-top:10px;">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="branding_remove_logo">
            <button type="submit" class="btn-mini btn-mini-danger"><?= icon_svg('trash') ?> حذف لوگوی فعلی</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="settings-tab-panel <?= $activeTab === 'realtime' ? '' : 'hidden' ?>">
      <div class="settings-box">
        <h2 class="admin-section-title"><?= icon_svg('bolt') ?> Real-Time (WebSocket)</h2>
        <p class="settings-hint">
          برای فعال‌سازی، ابتدا سرور Node.js را طبق <code>DEPLOY.md</code> روی cPanel (بخش Setup Node.js App) راه‌اندازی کنید،
          سپس آدرس عمومی آن را اینجا وارد کنید. تا وقتی این بخش خاموش یا آدرس آن نامعتبر باشد، برنامه به‌طور کامل با
          Polling کار می‌کند و هیچ‌چیزی خراب نمی‌شود.
        </p>
        <form method="post" class="settings-form">
          <?= csrf_field() ?>
          <input type="hidden" name="form" value="realtime">
          <label class="settings-checkbox">
            <input type="checkbox" name="realtime_enabled" <?= $realtimeEnabled ? 'checked' : '' ?>>
            فعال کردن Real-Time واقعی (WebSocket)
          </label>
          <label>آدرس عمومی سرور Node.js (بدون / انتهایی)
            <input type="text" name="realtime_base_url" value="<?= e($realtimeBaseUrl) ?>" placeholder="https://chat.example.com" dir="ltr">
          </label>

          <div class="settings-readonly-box">
            <strong>مقادیری که باید در تنظیمات Node.js App روی cPanel وارد کنید:</strong>
            <div class="settings-kv"><span>WS_SECRET</span><code dir="ltr"><?= e($secret) ?></code></div>
            <div class="settings-kv"><span>PHP_BASE_URL</span><code dir="ltr"><?= e($phpBaseUrlForNode) ?></code></div>
          </div>

          <label>آدرس سرور TURN (اختیاری — برای بهبود اتصال تماس صوتی پشت NAT/فایروال)
            <input type="text" name="turn_url" value="<?= e($turnUrl) ?>" placeholder="turn:turn.example.com:3478" dir="ltr">
          </label>
          <label>یوزرنیم TURN
            <input type="text" name="turn_username" value="<?= e($turnUsername) ?>" dir="ltr">
          </label>
          <label>پسورد/Credential TURN
            <input type="text" name="turn_credential" value="<?= e($turnCredential) ?>" dir="ltr">
          </label>

          <button type="submit" class="btn btn-primary" style="width:auto;">ذخیره تنظیمات Real-Time</button>
        </form>

        <?php if ($realtimeBaseUrl !== ''): ?>
          <div class="settings-test-box">
            <button type="button" id="btn-test-realtime" class="btn-mini">تست اتصال به سرور Node</button>
            <span id="realtime-test-result"></span>
          </div>
          <script>
            document.getElementById('btn-test-realtime').addEventListener('click', async () => {
              const resultEl = document.getElementById('realtime-test-result');
              const iconOk = <?= json_encode(icon_svg('check-circle'), JSON_UNESCAPED_SLASHES) ?>;
              const iconFail = <?= json_encode(icon_svg('x-circle'), JSON_UNESCAPED_SLASHES) ?>;
              resultEl.textContent = 'در حال بررسی...';
              try {
                const res = await fetch(<?= json_encode($realtimeBaseUrl, JSON_UNESCAPED_SLASHES) ?> + '/health', { cache: 'no-store' });
                const data = await res.json();
                resultEl.innerHTML = (res.ok && data.ok) ? (iconOk + ' سرور Node در دسترس است') : (iconFail + ' پاسخ نامعتبر از سرور');
              } catch (e) {
                resultEl.innerHTML = iconFail + ' اتصال برقرار نشد (شاید هنوز دیپلوی نشده یا CORS تنظیم نیست)';
              }
            });
          </script>
        <?php endif; ?>
      </div>
    </div>

    <div class="settings-tab-panel <?= $activeTab === 'registration' ? '' : 'hidden' ?>">
      <div class="settings-box">
        <h2 class="admin-section-title"><?= icon_svg('users') ?> ثبت‌نام کاربران جدید</h2>
        <form method="post" class="settings-form">
          <?= csrf_field() ?>
          <input type="hidden" name="form" value="registration">
          <label class="settings-checkbox">
            <input type="checkbox" name="registration_enabled" <?= $registrationEnabled ? 'checked' : '' ?>>
            اجازه‌ی ثبت‌نام حساب کاربری جدید
          </label>
          <p class="settings-hint">
            در صورت غیرفعال بودن، صفحه‌ی ثبت‌نام برای کاربران جدید بسته می‌شود و فقط کاربرانی که از قبل حساب دارند
            می‌توانند وارد شوند. برای مدیریت کاربران فعلی از بخش «کاربران» استفاده کنید.
          </p>
          <button type="submit" class="btn btn-primary" style="width:auto;">ذخیره تنظیمات</button>
        </form>
      </div>

      <div class="settings-box">
        <h2 class="admin-section-title" style="margin-top:0;">محدودیت حجم آپلود</h2>
        <form method="post" class="settings-form">
          <?= csrf_field() ?>
          <input type="hidden" name="form" value="upload_limits">
          <?php foreach ($uploadKindLabels as $kind => $label): ?>
            <label>حداکثر حجم <?= e($label) ?> (مگابایت)
              <input type="number" name="upload_limit_<?= e($kind) ?>_mb" value="<?= (int) $uploadLimits[$kind] ?>" min="1" max="500">
            </label>
          <?php endforeach; ?>
          <p class="settings-hint">
            این مقادیر مستقل از تنظیم <code>upload_max_filesize</code> سرور PHP هستند؛ اگر عددی بزرگ‌تر از محدودیت
            خود سرور وارد کنید، همچنان محدودیت سرور برای فایل‌های بزرگ‌تر خطا می‌دهد.
          </p>
          <button type="submit" class="btn btn-primary" style="width:auto;">ذخیره تنظیمات</button>
        </form>
      </div>
    </div>

    <div class="settings-tab-panel <?= $activeTab === 'email' ? '' : 'hidden' ?>">
      <div class="settings-box">
        <h2 class="admin-section-title"><?= icon_svg('check-circle') ?> تایید ایمیل حساب</h2>
        <form method="post" class="settings-form">
          <?= csrf_field() ?>
          <input type="hidden" name="form" value="email_verification">
          <label class="settings-checkbox">
            <input type="checkbox" name="require_email_verification" <?= $requireEmailVerification ? 'checked' : '' ?>>
            اجازه‌ی ارسال پیام فقط به کاربرانی که ایمیل خود را تایید کرده‌اند
          </label>
          <p class="settings-hint">
            در صورت فعال بودن، کاربرانی که روی لینک ارسال‌شده به ایمیلشان کلیک نکرده باشند نمی‌توانند پیام بفرستند
            (اما همچنان می‌توانند وارد شوند و پیام‌های دیگران را ببینند). ایمیل با <code>mail()</code> پی‌اچ‌پی ارسال می‌شود.
          </p>
          <button type="submit" class="btn btn-primary" style="width:auto;">ذخیره تنظیمات</button>
        </form>
      </div>
    </div>

    <div class="settings-tab-panel <?= $activeTab === 'maintenance' ? '' : 'hidden' ?>">
      <div class="settings-box">
        <h2 class="admin-section-title"><?= icon_svg('settings') ?> حالت تعمیر و نگهداری</h2>
        <form method="post" class="settings-form">
          <?= csrf_field() ?>
          <input type="hidden" name="form" value="maintenance">
          <label class="settings-checkbox">
            <input type="checkbox" name="maintenance_mode" <?= $maintenanceMode ? 'checked' : '' ?>>
            فعال کردن حالت تعمیر و نگهداری (فقط ادمین‌ها می‌توانند سایت را ببینند)
          </label>
          <label>پیام نمایش‌داده‌شده به کاربران
            <textarea name="maintenance_message" rows="3"><?= e($maintenanceMessage) ?></textarea>
          </label>
          <button type="submit" class="btn btn-primary" style="width:auto;">ذخیره تنظیمات</button>
        </form>
      </div>
    </div>

    <div class="settings-tab-panel <?= $activeTab === 'system' ? '' : 'hidden' ?>">
      <div class="settings-box">
        <h2 class="admin-section-title" style="margin-top:0;"><?= icon_svg('monitor') ?> اطلاعات سیستم</h2>
        <div class="stat-grid">
          <div class="stat-card">
            <span class="stat-value"><?= e(PHP_VERSION) ?></span>
            <span class="stat-label">نسخه PHP</span>
          </div>
          <div class="stat-card">
            <span class="stat-value"><?= $dbSizeMb ?> MB</span>
            <span class="stat-label">حجم دیتابیس</span>
          </div>
          <div class="stat-card">
            <span class="stat-value"><?= $uploadsSizeMb ?> MB</span>
            <span class="stat-label">حجم فایل‌های آپلودشده</span>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="<?= e(url('assets/js/icons.js')) ?>"></script>
<script src="<?= e(url('assets/js/dialog.js')) ?>"></script>
<script src="<?= e(url('assets/js/notifications.js')) ?>"></script>
<script src="<?= e(url('assets/js/profile.js')) ?>"></script>
<script src="<?= e(url('assets/js/pjax.js')) ?>"></script>
</body>
</html>
