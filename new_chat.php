<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_login();

$me = current_user();
$activeConversationId = -1; // هیچ‌کدام از آیتم‌های سایدبار فعال نباشند

$query = trim($_GET['q'] ?? '');
$results = [];

if ($query !== '') {
    $stmt = db()->prepare(
        'SELECT id, username, display_name, avatar_color, avatar_path FROM users
         WHERE username LIKE ? AND id != ?
         ORDER BY username ASC
         LIMIT 30'
    );
    $stmt->execute(['%' . $query . '%', $me['id']]);
    $results = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= favicon_links_html() ?>
<script src="<?= e(url('assets/js/theme.js')) ?>"></script>
<title>گفتگوی جدید | <?= e(app_name()) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div id="page-content">
<div class="app-shell">

  <?php require ROOT_PATH . '/includes/partials/sidebar.php'; ?>

  <main class="chat-main">
    <header class="chat-header">
      <button type="button" class="btn-back-mobile" onclick="document.body.classList.add('mobile-view-list')" aria-label="بازگشت">‹</button>
      <div class="chat-header-title">
        <span class="username">شروع گفتگوی جدید</span>
      </div>
    </header>

    <div class="new-chat-body">
      <form method="get" class="new-chat-search">
        <input type="text" name="q" value="<?= e($query) ?>" placeholder="نام کاربری را وارد کنید..." autofocus>
        <button type="submit" class="btn btn-primary">جستجو</button>
      </form>

      <?php if ($query !== '' && $results === []): ?>
        <p class="empty-state">کاربری با این نام پیدا نشد.</p>
      <?php endif; ?>

      <ul class="user-results">
        <?php foreach ($results as $user): ?>
          <li class="user-result">
            <?= avatar_html($user) ?>
            <span class="conv-name"><?= e(display_name_of($user)) ?></span>
            <form method="post" action="<?= e(url('start_conversation.php')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
              <button type="submit" class="btn-start-chat">شروع گفتگو</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
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
