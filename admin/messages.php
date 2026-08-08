<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_admin();

$activeAdminTab = 'messages';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $action = (string) ($_POST['action'] ?? '');
    $messageId = (int) ($_POST['message_id'] ?? 0);

    if ($messageId > 0 && $action === 'delete') {
        delete_message_for_everyone('public', $messageId);
        set_flash('success', 'پیام حذف شد.');
    } elseif ($action === 'delete_all') {
        $count = delete_all_public_messages();
        set_flash('success', $count > 0 ? "تمام پیام‌های چت عمومی ({$count} پیام) حذف شد." : 'پیامی برای حذف وجود نداشت.');
    }
    redirect('admin/messages.php');
}

$messages = db()->query(
    'SELECT m.id, m.body, m.created_at, u.username
     FROM messages m
     JOIN users u ON u.id = m.user_id
     WHERE m.deleted_at IS NULL
     ORDER BY m.id DESC
     LIMIT 100'
)->fetchAll();

$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= favicon_links_html() ?>
<script src="<?= e(url('assets/js/theme.js')) ?>"></script>
<title>مدیریت چت عمومی | <?= e(app_name()) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="admin-page" id="page-content">
  <?php require ROOT_PATH . '/includes/partials/admin_header.php'; ?>

  <main class="admin-content">
    <?php foreach ($flashes as $flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>

    <?php if ($messages !== []): ?>
      <form method="post" data-confirm="تمام پیام‌های چت عمومی برای همه حذف شود؟ این عمل غیرقابل‌بازگشت است." style="margin-bottom:16px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_all">
        <button type="submit" class="btn-mini btn-mini-danger"><?= icon_svg('trash') ?> حذف تمام پیام‌های چت عمومی</button>
      </form>
    <?php endif; ?>

    <div class="admin-dtable" role="table" aria-label="پیام‌های چت عمومی">
      <div class="admin-drow admin-drow-head" role="row">
        <div class="admin-dcell" role="columnheader">کاربر</div>
        <div class="admin-dcell admin-dcell-wide" role="columnheader">متن پیام</div>
        <div class="admin-dcell" role="columnheader">زمان</div>
        <div class="admin-dcell" role="columnheader">عملیات</div>
      </div>
      <?php if ($messages === []): ?>
        <p class="admin-dtable-empty">پیامی وجود ندارد.</p>
      <?php endif; ?>
      <?php foreach ($messages as $msg): ?>
        <div class="admin-drow" role="row">
          <div class="admin-dcell" role="cell" data-label="کاربر"><?= e($msg['username']) ?></div>
          <div class="admin-dcell admin-dcell-wide admin-message-body" role="cell" data-label="متن پیام"><?= e(mb_substr($msg['body'], 0, 120)) ?></div>
          <div class="admin-dcell" role="cell" data-label="زمان"><?= e(date('Y/m/d H:i', strtotime($msg['created_at']))) ?></div>
          <div class="admin-dcell" role="cell" data-label="عملیات">
            <form method="post" data-confirm="این پیام حذف شود؟">
              <?= csrf_field() ?>
              <input type="hidden" name="message_id" value="<?= (int) $msg['id'] ?>">
              <input type="hidden" name="action" value="delete">
              <button type="submit" class="btn-mini btn-mini-danger">حذف</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
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
