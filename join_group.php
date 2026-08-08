<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_login();

$me = current_user();
$activeConversationId = -1;
$activeGroupId = -1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $code = trim((string) ($_POST['code'] ?? ''));
    $group = $code !== '' ? join_group_by_invite_code($code, (int) $me['id']) : null;

    if ($group === null) {
        set_flash('error', 'لینک دعوت نامعتبر است.');
        redirect('join_group.php');
    }

    redirect('group.php?id=' . (int) $group['id']);
}

$code = trim((string) ($_GET['code'] ?? ''));
$previewGroup = null;
$alreadyMember = false;

if ($code !== '') {
    $stmt = db()->prepare('SELECT * FROM `groups` WHERE invite_code = ?');
    $stmt->execute([$code]);
    $previewGroup = $stmt->fetch() ?: null;

    if ($previewGroup !== null) {
        $alreadyMember = is_group_member((int) $previewGroup['id'], (int) $me['id']);
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
<title>عضویت در گروه | <?= e(app_name()) ?></title>
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
        <span class="username">عضویت در گروه</span>
      </div>
    </header>

    <div class="new-chat-body">
      <?php if ($code !== '' && $previewGroup === null): ?>
        <p class="empty-state">لینک دعوت نامعتبر است یا گروه دیگر وجود ندارد.</p>
      <?php elseif ($previewGroup !== null): ?>
        <div class="group-join-preview">
          <?= avatar_html(['username' => $previewGroup['name'], 'avatar_color' => $previewGroup['avatar_color'], 'avatar_path' => $previewGroup['avatar_path']], 'avatar-lg', false) ?>
          <h3><?= e($previewGroup['name']) ?></h3>
          <?php if (!empty($previewGroup['description'])): ?>
            <p class="group-join-desc"><?= e($previewGroup['description']) ?></p>
          <?php endif; ?>

          <?php if ($alreadyMember): ?>
            <a href="<?= e(url('group.php?id=' . (int) $previewGroup['id'])) ?>" class="btn btn-primary">ورود به گروه</a>
          <?php else: ?>
            <form method="post" action="<?= e(url('join_group.php')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="code" value="<?= e($code) ?>">
              <button type="submit" class="btn btn-primary">عضویت در گروه</button>
            </form>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <form method="get" action="<?= e(url('join_group.php')) ?>" class="simple-form">
          <label class="form-field">
            <span>کد دعوت گروه</span>
            <input type="text" name="code" placeholder="کد دعوت را وارد کن..." required autofocus>
          </label>
          <button type="submit" class="btn btn-primary">بررسی</button>
        </form>
      <?php endif; ?>
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
