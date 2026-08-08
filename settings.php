<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_login();

$me = current_user();
$activeConversationId = -1; // هیچ‌کدام از آیتم‌های سایدبار فعال نباشند

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $displayName = trim((string) ($_POST['display_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $bio = trim((string) ($_POST['bio'] ?? ''));
    $removeAvatar = isset($_POST['remove_avatar']);

    if ($displayName !== '' && mb_strlen($displayName) > 50) {
        $errors[] = 'نام نمایشی نباید بیشتر از ۵۰ کاراکتر باشد.';
    }
    if (!preg_match(USERNAME_REGEX, $username)) {
        $errors[] = 'آیدی باید ۳ تا ۳۲ کاراکتر و فقط شامل حروف انگلیسی، عدد و آندرلاین باشد.';
    } elseif (is_username_reserved($username)) {
        $errors[] = 'این آیدی رزرو شده و قابل استفاده نیست.';
    }
    if (mb_strlen($bio) > 160) {
        $errors[] = 'بیو نباید بیشتر از ۱۶۰ کاراکتر باشد.';
    }

    if ($errors === [] && $username !== $me['username'] && is_username_taken($username, (int) $me['id'])) {
        $errors[] = 'این آیدی قبلاً توسط کاربر دیگری استفاده شده است.';
    }

    $newAvatarPath = $me['avatar_path'] ?? null;
    $avatarChanged = false;

    if ($errors === [] && $removeAvatar) {
        $newAvatarPath = null;
        $avatarChanged = true;
    } elseif ($errors === [] && !empty($_FILES['avatar']['name'])) {
        try {
            $saved = save_uploaded_file($_FILES['avatar'], 'avatar');
            $newAvatarPath = $saved['path'];
            $avatarChanged = true;
        } catch (UploadException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if ($errors === []) {
        $oldAvatarPath = $me['avatar_path'] ?? null;

        $sql = 'UPDATE users SET display_name = ?, username = ?, bio = ?';
        $params = [$displayName !== '' ? $displayName : null, $username, $bio !== '' ? $bio : null];
        if ($avatarChanged) {
            $sql .= ', avatar_path = ?';
            $params[] = $newAvatarPath;
        }
        $sql .= ' WHERE id = ?';
        $params[] = $me['id'];

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        if ($avatarChanged && $oldAvatarPath !== null && $oldAvatarPath !== $newAvatarPath) {
            $absoluteOld = ROOT_PATH . '/' . ltrim((string) $oldAvatarPath, '/');
            if (is_file($absoluteOld)) {
                @unlink($absoluteOld);
            }
        }

        set_flash('success', 'تنظیمات پروفایل با موفقیت ذخیره شد.');
        redirect('settings.php');
    }

    // در صورت خطا، مقادیر واردشده برای نمایش دوباره در فرم نگه داشته می‌شوند
    $me = array_merge($me, [
        'display_name' => $displayName,
        'username' => $username,
        'bio' => $bio,
    ]);
}

$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= favicon_links_html() ?>
<script src="<?= e(url('assets/js/theme.js')) ?>"></script>
<title>تنظیمات حساب | <?= e(app_name()) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div id="page-content">
<div class="app-shell page-static-scroll">

  <?php require ROOT_PATH . '/includes/partials/sidebar.php'; ?>

  <main class="chat-main">
    <header class="chat-header">
      <button type="button" class="btn-back-mobile" onclick="document.body.classList.add('mobile-view-list')" aria-label="بازگشت"><?= icon_svg('chevron-back') ?></button>
      <div class="chat-header-title">
        <span class="username">تنظیمات حساب</span>
      </div>
    </header>

    <div class="new-chat-body settings-page">
      <div class="settings-page-header">
        <h1>تنظیمات حساب</h1>
        <p>اطلاعات پروفایل، امنیت و حساب کاربری خود را از این‌جا مدیریت کنید.</p>
      </div>

      <?php foreach ($flashes as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
      <?php endforeach; ?>
      <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= e($err) ?></div>
      <?php endforeach; ?>

      <?php if (!is_email_verified($me)): ?>
        <div class="alert alert-error" id="email-verify-banner">
          ایمیل شما هنوز تایید نشده و امکان ارسال پیام محدود است.
          <button type="button" id="btn-resend-verify" class="btn-mini" style="margin-inline-start:8px;">ارسال مجدد ایمیل تایید</button>
          <span id="resend-verify-result" class="field-hint"></span>
        </div>
        <script>
          document.getElementById('btn-resend-verify').addEventListener('click', async function () {
            const btn = this;
            const resultEl = document.getElementById('resend-verify-result');
            const tokenEl = document.getElementById('global-csrf-token') || document.getElementById('csrf_token');
            btn.disabled = true;
            resultEl.textContent = 'در حال ارسال...';
            try {
              const res = await fetch((tokenEl.dataset.baseUrl || '') + 'api/resend_verification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ csrf_token: tokenEl.value }),
              });
              const data = await res.json();
              if (data.ok) {
                resultEl.textContent = 'ایمیل تایید دوباره ارسال شد.';
              } else if (data.error === 'too_soon') {
                resultEl.textContent = 'کمی صبر کنید و دوباره امتحان کنید.';
              } else {
                resultEl.textContent = 'ارسال ایمیل ناموفق بود.';
              }
            } catch (e) {
              resultEl.textContent = 'خطا در ارتباط با سرور.';
            } finally {
              btn.disabled = false;
            }
          });
        </script>
      <?php endif; ?>

      <div class="settings-card">
        <h2 class="settings-card-title">اطلاعات پروفایل</h2>
        <form method="post" enctype="multipart/form-data" class="settings-form">
          <?= csrf_field() ?>

          <div class="settings-profile-header">
            <div class="avatar-upload-wrap avatar-upload-wrap-lg">
              <?= avatar_html($me, 'avatar-lg', false) ?>
              <label class="avatar-upload-overlay" for="avatar-input" title="تغییر عکس پروفایل">
                <?= icon_svg('camera') ?>
              </label>
              <input type="file" id="avatar-input" name="avatar" accept="image/jpeg,image/png,image/webp" hidden onchange="document.getElementById('avatar-filename').textContent = this.files[0] ? this.files[0].name : '';">
            </div>
            <div class="settings-profile-header-name"><?= e(display_name_of($me)) ?></div>
            <div class="settings-profile-header-username">@<?= e($me['username']) ?></div>
            <div class="settings-profile-header-actions">
              <span class="avatar-upload-hint">برای تغییر عکس پروفایل روی آن کلیک کنید</span>
              <span id="avatar-filename" class="field-hint"></span>
              <?php if (!empty($me['avatar_path'])): ?>
                <label class="settings-checkbox">
                  <input type="checkbox" name="remove_avatar" value="1">
                  حذف عکس پروفایل فعلی
                </label>
              <?php endif; ?>
            </div>
          </div>

          <div class="settings-field-group">
            <label>نام نمایشی
              <input type="text" name="display_name" value="<?= e((string) ($me['display_name'] ?? '')) ?>" maxlength="50" placeholder="مثلاً: علی محمدی">
              <span class="field-hint">اگر خالی بگذارید، آیدی شما به‌جای آن نمایش داده می‌شود.</span>
            </label>

            <label>آیدی (یکتا - برای منشن‌کردن با @ در پیام‌ها استفاده می‌شود)
              <input type="text" name="username" id="settings-username" value="<?= e($me['username']) ?>" maxlength="32" required dir="ltr" autocomplete="off" data-original="<?= e($me['username']) ?>">
              <span class="field-hint" id="settings-username-hint">فقط حروف انگلیسی، عدد و آندرلاین، بین ۳ تا ۳۲ کاراکتر.</span>
            </label>

            <label>بیو
              <textarea name="bio" rows="3" maxlength="160" placeholder="چند کلمه درباره‌ی خودتان..."><?= e((string) ($me['bio'] ?? '')) ?></textarea>
            </label>

            <div class="settings-readonly-box">
              <span class="settings-kv-label">ایمیل</span>
              <span class="settings-kv-value" dir="ltr"><?= e($me['email']) ?></span>
              <span class="field-hint">ایمیل قابل تغییر نیست.</span>
            </div>
          </div>

          <div class="settings-form-actions">
            <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
          </div>
        </form>
      </div>

      <div class="settings-card">
        <h2 class="settings-card-title">اعلان مرورگر</h2>
        <p class="settings-card-desc">
          وقتی تب چت باز است اما شما به تب/برنامه‌ی دیگری رفته‌اید، برای پیام‌های خصوصی و منشن‌های جدید یک اعلان دسکتاپ نشان داده می‌شود.
        </p>
        <label class="settings-toggle-row">
          <span>فعال‌سازی اعلان مرورگر</span>
          <span class="settings-toggle">
            <input type="checkbox" id="notify-toggle">
            <span class="settings-toggle-slider"></span>
          </span>
        </label>
        <p class="field-hint" id="notify-status"></p>
      </div>

      <div class="danger-zone">
        <h3 class="danger-zone-title"><?= icon_svg('warning') ?> حذف حساب کاربری</h3>
        <p>
          با حذف حساب، تمام پیام‌ها (چه در چت عمومی چه خصوصی)، فایل‌های ارسالی (عکس/ویدیو/ویس) و عکس پروفایل شما
          برای همیشه پاک می‌شوند. این عمل غیرقابل‌بازگشت است.
        </p>
        <form method="post" action="<?= e(url('delete_account.php')) ?>" data-confirm="آیا کاملاً مطمئنید؟ حساب شما و تمام پیام‌ها و فایل‌های آن برای همیشه حذف خواهند شد.">
          <?= csrf_field() ?>
          <label class="danger-zone-checkbox">
            <input type="checkbox" name="confirm_wipe" id="confirm-wipe-checkbox" value="1" required>
            می‌دانم این عمل غیرقابل‌بازگشت است و تمام پیام‌ها و فایل‌های من کاملاً پاک می‌شوند.
          </label>
          <button type="submit" class="btn-mini btn-mini-danger">حذف همیشگی حساب کاربری</button>
        </form>
      </div>
    </div>
  </main>

  <script>
    (function () {
      var input = document.getElementById('settings-username');
      var hint = document.getElementById('settings-username-hint');
      if (!input || !hint) return;

      var originalValue = input.dataset.original || '';
      var defaultHintText = hint.textContent;
      var tokenEl = document.getElementById('global-csrf-token');
      var baseUrl = (tokenEl && tokenEl.dataset.baseUrl) || '/';
      var timer = null;
      var requestToken = 0;

      var REASONS = {
        invalid_format: 'باید ۳ تا ۳۲ کاراکتر و فقط حروف انگلیسی، عدد و آندرلاین باشد.',
        reserved: 'این آیدی رزرو شده و قابل استفاده نیست.',
        taken: 'این آیدی قبلاً توسط کاربر دیگری استفاده شده است.',
        empty: '',
      };

      input.addEventListener('input', function () {
        clearTimeout(timer);
        var value = input.value.trim();

        if (value === '' || value === originalValue) {
          hint.textContent = defaultHintText;
          hint.className = 'field-hint';
          return;
        }

        hint.textContent = 'در حال بررسی...';
        hint.className = 'field-hint';
        timer = setTimeout(function () { checkUsername(value); }, 400);
      });

      function checkUsername(value) {
        var myToken = ++requestToken;
        fetch(baseUrl + 'api/check_username.php?username=' + encodeURIComponent(value), {
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
          .then(function (res) { return res.json(); })
          .then(function (data) {
            if (myToken !== requestToken) return;
            if (data.available) {
              hint.textContent = 'این آیدی آزاد است.';
              hint.className = 'field-hint ok';
            } else {
              hint.textContent = REASONS[data.reason] || 'این آیدی قابل استفاده نیست.';
              hint.className = 'field-hint error';
            }
          })
          .catch(function () {
            if (myToken === requestToken) {
              hint.textContent = defaultHintText;
              hint.className = 'field-hint';
            }
          });
      }
    })();
  </script>

  <script>
    (function () {
      var toggle = document.getElementById('notify-toggle');
      var status = document.getElementById('notify-status');
      if (!toggle || !status) return;

      function refresh() {
        if (!window.ChativaNotify || !window.ChativaNotify.isSupported()) {
          toggle.disabled = true;
          status.textContent = 'مرورگر شما از اعلان دسکتاپ پشتیبانی نمی‌کند.';
          return;
        }
        toggle.checked = window.ChativaNotify.isEnabled();
        if (Notification.permission === 'denied') {
          status.textContent = 'اجازه‌ی اعلان از تنظیمات مرورگر مسدود شده. برای فعال‌سازی، از تنظیمات سایت در مرورگرتان اجازه بدهید.';
        } else {
          status.textContent = '';
        }
      }

      toggle.addEventListener('change', async function () {
        if (!window.ChativaNotify) return;
        if (toggle.checked) {
          var result = await window.ChativaNotify.requestPermission();
          if (result !== 'granted') {
            toggle.checked = false;
          }
        } else {
          window.ChativaNotify.setEnabled(false);
        }
        refresh();
      });

      refresh();
    })();
  </script>

</div>
</div>
<script src="<?= e(url('assets/js/icons.js')) ?>"></script>
<script src="<?= e(url('assets/js/dialog.js')) ?>"></script>
<script src="<?= e(url('assets/js/notifications.js')) ?>"></script>
<script src="<?= e(url('assets/js/profile.js')) ?>"></script>
<script src="<?= e(url('assets/js/sidebar-unread.js')) ?>"></script>
<script src="<?= e(url('assets/js/pjax.js')) ?>"></script>
</body>
</html>
