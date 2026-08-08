<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_login();

$me = current_user();
$activeConversationId = -1;
$activeGroupId = -1;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= favicon_links_html() ?>
<script src="<?= e(url('assets/js/theme.js')) ?>"></script>
<title>گروه جدید | <?= e(app_name()) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div id="page-content">
<div class="app-shell">

  <?php require ROOT_PATH . '/includes/partials/sidebar.php'; ?>

  <main class="chat-main">
    <header class="chat-header">
      <button type="button" class="btn-back-mobile" onclick="document.body.classList.add('mobile-view-list')" aria-label="بازگشت"><?= icon_svg('chevron-back') ?></button>
      <div class="chat-header-title">
        <span class="username">ساخت گروه جدید</span>
      </div>
    </header>

    <div class="new-chat-body">
      <form method="post" action="<?= e(url('create_group.php')) ?>" class="simple-form">
        <?= csrf_field() ?>
        <label class="form-field">
          <span>نام گروه</span>
          <input type="text" name="name" maxlength="100" placeholder="مثلاً تیم توسعه" required autofocus>
        </label>
        <label class="form-field">
          <span>توضیحات (اختیاری)</span>
          <textarea name="description" maxlength="300" rows="3" placeholder="درباره‌ی این گروه..."></textarea>
        </label>
        <button type="submit" class="btn btn-primary">ساخت گروه</button>
      </form>
    </div>
  </main>

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
