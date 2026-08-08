<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

$token = trim((string) ($_GET['token'] ?? ''));
$success = false;
$message = 'لینک تایید نامعتبر یا منقضی‌شده است.';

if ($token !== '') {
    $userId = consume_action_token($token, 'email_verify');
    if ($userId !== null) {
        db()->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ? AND email_verified_at IS NULL')
            ->execute([$userId]);
        $success = true;
        $message = 'ایمیل شما با موفقیت تایید شد.';
    }
}

$loggedIn = is_logged_in();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= favicon_links_html() ?>
<script src="<?= e(url('assets/js/theme.js')) ?>"></script>
<title>تایید ایمیل | <?= e(app_name()) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="brand">
      <?= brand_logo_html() ?>
      <h1><?= e(app_name()) ?></h1>
    </div>
    <div class="alert alert-<?= $success ? 'success' : 'error' ?>"><?= e($message) ?></div>
    <p class="auth-switch">
      <?php if ($loggedIn): ?>
        <a href="<?= e(url('chat.php')) ?>">بازگشت به چت</a>
      <?php else: ?>
        <a href="<?= e(url('login.php')) ?>">ورود به حساب</a>
      <?php endif; ?>
    </p>
  </div>
</div>
</body>
</html>
