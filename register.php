<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_guest();

if (!registration_open()) {
    set_flash('error', 'ثبت‌نام کاربران جدید در حال حاضر توسط مدیر سایت غیرفعال شده است.');
    redirect('login.php');
}

$errors = [];
$old = ['username' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $old['username'] = trim($_POST['username'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');

    [$ok, $message, $newUserId] = register_user(
        $old['username'],
        $old['email'],
        $_POST['password'] ?? '',
        $_POST['confirm_password'] ?? ''
    );

    if ($ok) {
        $mailSent = $newUserId !== null && send_verification_email($newUserId, $old['email'], $old['username']);
        set_flash('success', $mailSent
            ? 'ثبت‌نام با موفقیت انجام شد! یک ایمیل تایید برای شما ارسال شد، سپس وارد شوید.'
            : 'ثبت‌نام با موفقیت انجام شد! حالا وارد شوید.');
        redirect('login.php');
    } else {
        $errors[] = $message;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= favicon_links_html() ?>
<script src="<?= e(url('assets/js/theme.js')) ?>"></script>
<title>ثبت‌نام | <?= e(app_name()) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="brand">
      <?= brand_logo_html() ?>
      <h1><?= e(app_name()) ?></h1>
    </div>
    <p class="auth-subtitle">ساخت حساب کاربری جدید</p>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" class="auth-form">
      <?= csrf_field() ?>
      <label>نام کاربری
        <input type="text" name="username" id="reg-username" value="<?= e($old['username']) ?>" placeholder="مثلاً ali_2020" required autofocus autocomplete="off" dir="ltr">
        <span class="field-hint" id="reg-username-hint"></span>
      </label>
      <label>ایمیل
        <input type="email" name="email" value="<?= e($old['email']) ?>" placeholder="you@example.com" required>
      </label>
      <label>رمز عبور
        <input type="password" name="password" placeholder="حداقل ۶ کاراکتر" required>
      </label>
      <label>تکرار رمز عبور
        <input type="password" name="confirm_password" required>
      </label>
      <button type="submit" class="btn btn-primary">ثبت‌نام</button>
    </form>

    <p class="auth-switch">قبلاً حساب دارید؟ <a href="<?= e(url('login.php')) ?>">وارد شوید</a></p>
  </div>
</div>
<script>
(function () {
  var BASE_URL = <?= json_encode(url(''), JSON_UNESCAPED_SLASHES) ?>;
  var input = document.getElementById('reg-username');
  var hint = document.getElementById('reg-username-hint');
  var timer = null;
  var requestToken = 0;

  var REASONS = {
    invalid_format: 'باید ۳ تا ۳۲ کاراکتر و فقط حروف انگلیسی، عدد و آندرلاین باشد.',
    reserved: 'این نام کاربری رزرو شده و قابل استفاده نیست.',
    taken: 'این نام کاربری قبلاً گرفته شده است.',
    empty: '',
  };

  input.addEventListener('input', function () {
    clearTimeout(timer);
    var value = input.value.trim();
    if (value === '') {
      hint.textContent = '';
      hint.className = 'field-hint';
      return;
    }
    hint.textContent = 'در حال بررسی...';
    hint.className = 'field-hint';
    timer = setTimeout(function () { checkUsername(value); }, 400);
  });

  function checkUsername(value) {
    var myToken = ++requestToken;
    fetch(BASE_URL + 'api/check_username.php?username=' + encodeURIComponent(value), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (myToken !== requestToken) return;
        if (data.available) {
          hint.textContent = 'این نام کاربری آزاد است.';
          hint.className = 'field-hint ok';
        } else {
          hint.textContent = REASONS[data.reason] || 'این نام کاربری قابل استفاده نیست.';
          hint.className = 'field-hint error';
        }
      })
      .catch(function () {
        if (myToken === requestToken) {
          hint.textContent = '';
          hint.className = 'field-hint';
        }
      });
  }
})();
</script>
</body>
</html>
