<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_guest();

$token = trim((string) ($_GET['token'] ?? ($_POST['token'] ?? '')));
$errors = [];
$done = false;

$tokenValid = $token !== '' && action_token_is_valid($token, 'password_reset');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (!$tokenValid) {
        $errors[] = 'لینک ریست رمز نامعتبر یا منقضی‌شده است.';
    } elseif (mb_strlen($password) < 6) {
        $errors[] = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
    } elseif ($password !== $confirm) {
        $errors[] = 'رمز عبور و تکرار آن یکسان نیستند.';
    } else {
        $userId = consume_action_token($token, 'password_reset');
        if ($userId === null) {
            $errors[] = 'لینک ریست رمز نامعتبر یا منقضی‌شده است.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);
            $done = true;
        }
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
<title>تعیین رمز عبور جدید | <?= e(app_name()) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="brand">
      <?= brand_logo_html() ?>
      <h1><?= e(app_name()) ?></h1>
    </div>
    <p class="auth-subtitle">تعیین رمز عبور جدید</p>

    <?php if ($done): ?>
      <div class="alert alert-success">رمز عبور شما با موفقیت تغییر کرد. حالا می‌توانید وارد شوید.</div>
      <p class="auth-switch"><a href="<?= e(url('login.php')) ?>">ورود به حساب</a></p>
    <?php elseif (!$tokenValid && $errors === []): ?>
      <div class="alert alert-error">لینک ریست رمز نامعتبر یا منقضی‌شده است.</div>
      <p class="auth-switch"><a href="<?= e(url('forgot_password.php')) ?>">درخواست لینک جدید</a></p>
    <?php else: ?>
      <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= e($err) ?></div>
      <?php endforeach; ?>

      <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label>رمز عبور جدید
          <input type="password" name="password" placeholder="حداقل ۶ کاراکتر" required autofocus>
        </label>
        <label>تکرار رمز عبور جدید
          <input type="password" name="confirm_password" required>
        </label>
        <button type="submit" class="btn btn-primary">تغییر رمز عبور</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
