<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_guest();

$errors = [];
$old = ['username' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $old['username'] = trim($_POST['username'] ?? '');

    [$ok, $message] = login_user($old['username'], $_POST['password'] ?? '');

    if ($ok) {
        redirect('chat.php');
    } else {
        $errors[] = $message;
    }
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
<title>ورود | <?= e(app_name()) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="brand">
      <?= brand_logo_html() ?>
      <h1><?= e(app_name()) ?></h1>
    </div>
    <p class="auth-subtitle">ورود به حساب کاربری</p>

    <?php foreach ($flashes as $flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" class="auth-form">
      <?= csrf_field() ?>
      <label>نام کاربری یا ایمیل
        <input type="text" name="username" value="<?= e($old['username']) ?>" required autofocus>
      </label>
      <label>رمز عبور
        <input type="password" name="password" required>
      </label>
      <button type="submit" class="btn btn-primary">ورود</button>
    </form>

    <p class="auth-switch"><a href="<?= e(url('forgot_password.php')) ?>">رمز عبور را فراموش کرده‌اید؟</a></p>
    <?php if (registration_open()): ?>
      <p class="auth-switch">حساب ندارید؟ <a href="<?= e(url('register.php')) ?>">ثبت‌نام کنید</a></p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
