<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_guest();

$errors = [];
$submitted = false;
$old = ['email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $old['email'] = trim((string) ($_POST['email'] ?? ''));

    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'ایمیل معتبر نیست.';
    } else {
        // برای جلوگیری از افشای اینکه یک ایمیل ثبت‌نام شده یا نه، همیشه پیام موفقیت یکسان نمایش داده می‌شود.
        $stmt = db()->prepare('SELECT id, username, email FROM users WHERE email = ?');
        $stmt->execute([$old['email']]);
        $user = $stmt->fetch();

        if ($user) {
            send_password_reset_email((int) $user['id'], (string) $user['email'], (string) $user['username']);
        }

        $submitted = true;
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
<title>فراموشی رمز عبور | <?= e(app_name()) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="brand">
      <?= brand_logo_html() ?>
      <h1><?= e(app_name()) ?></h1>
    </div>
    <p class="auth-subtitle">بازیابی رمز عبور</p>

    <?php if ($submitted): ?>
      <div class="alert alert-success">
        اگر این ایمیل در سیستم ثبت شده باشد، لینک ریست رمز عبور برایش ارسال شد. صندوق ایمیل خود را بررسی کنید.
      </div>
      <p class="auth-switch"><a href="<?= e(url('login.php')) ?>">بازگشت به ورود</a></p>
    <?php else: ?>
      <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= e($err) ?></div>
      <?php endforeach; ?>

      <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <label>ایمیل حساب
          <input type="email" name="email" value="<?= e($old['email']) ?>" placeholder="you@example.com" required autofocus>
        </label>
        <button type="submit" class="btn btn-primary">ارسال لینک ریست رمز</button>
      </form>

      <p class="auth-switch"><a href="<?= e(url('login.php')) ?>">بازگشت به ورود</a></p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
