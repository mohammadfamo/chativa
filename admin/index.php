<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_admin();

$activeAdminTab = 'dashboard';

$stats = [
    'users'            => (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'online_users'     => (int) db()->query("SELECT COUNT(*) FROM users WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn(),
    'blocked_users'    => (int) db()->query('SELECT COUNT(*) FROM users WHERE is_blocked = 1')->fetchColumn(),
    'public_messages'  => (int) db()->query('SELECT COUNT(*) FROM messages')->fetchColumn(),
    'conversations'    => (int) db()->query('SELECT COUNT(*) FROM conversations')->fetchColumn(),
    'private_messages' => (int) db()->query('SELECT COUNT(*) FROM private_messages')->fetchColumn(),
];

$recentUsers = db()->query(
    'SELECT username, display_name, avatar_color, avatar_path, email, created_at FROM users ORDER BY id DESC LIMIT 8'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= favicon_links_html() ?>
<script src="<?= e(url('assets/js/theme.js')) ?>"></script>
<title>داشبورد ادمین | <?= e(app_name()) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="admin-page" id="page-content">
  <?php require ROOT_PATH . '/includes/partials/admin_header.php'; ?>

  <main class="admin-content">
    <div class="stat-grid">
      <div class="stat-card">
        <span class="stat-card-icon"><?= icon_svg('user') ?></span>
        <span class="stat-card-body">
          <span class="stat-value"><?= $stats['users'] ?></span>
          <span class="stat-label">کاربران</span>
        </span>
      </div>
      <div class="stat-card">
        <span class="stat-card-icon success"><?= icon_svg('bolt') ?></span>
        <span class="stat-card-body">
          <span class="stat-value"><?= $stats['online_users'] ?></span>
          <span class="stat-label">آنلاین (۵ دقیقه اخیر)</span>
        </span>
      </div>
      <div class="stat-card">
        <span class="stat-card-icon danger"><?= icon_svg('ban') ?></span>
        <span class="stat-card-body">
          <span class="stat-value"><?= $stats['blocked_users'] ?></span>
          <span class="stat-label">مسدود شده</span>
        </span>
      </div>
      <div class="stat-card">
        <span class="stat-card-icon info"><?= icon_svg('globe') ?></span>
        <span class="stat-card-body">
          <span class="stat-value"><?= $stats['public_messages'] ?></span>
          <span class="stat-label">پیام چت عمومی</span>
        </span>
      </div>
      <div class="stat-card">
        <span class="stat-card-icon"><?= icon_svg('reply') ?></span>
        <span class="stat-card-body">
          <span class="stat-value"><?= $stats['conversations'] ?></span>
          <span class="stat-label">گفتگوی خصوصی</span>
        </span>
      </div>
      <div class="stat-card">
        <span class="stat-card-icon info"><?= icon_svg('send') ?></span>
        <span class="stat-card-body">
          <span class="stat-value"><?= $stats['private_messages'] ?></span>
          <span class="stat-label">پیام خصوصی</span>
        </span>
      </div>
    </div>

    <h2 class="admin-section-title"><?= icon_svg('user') ?> آخرین کاربران ثبت‌نام‌شده</h2>
    <div class="admin-dtable" role="table" aria-label="آخرین کاربران ثبت‌نام‌شده">
      <div class="admin-drow admin-drow-head" role="row">
        <div class="admin-dcell" role="columnheader">کاربر</div>
        <div class="admin-dcell" role="columnheader">ایمیل</div>
        <div class="admin-dcell" role="columnheader">تاریخ عضویت</div>
      </div>
      <?php if ($recentUsers === []): ?>
        <p class="admin-dtable-empty">کاربری وجود ندارد.</p>
      <?php endif; ?>
      <?php foreach ($recentUsers as $u): ?>
        <div class="admin-drow" role="row">
          <div class="admin-dcell admin-user-cell" role="cell" data-label="کاربر">
            <?= avatar_html($u, 'avatar-sm') ?>
            <?= e(display_name_of($u)) ?>
          </div>
          <div class="admin-dcell" role="cell" data-label="ایمیل"><?= e($u['email']) ?></div>
          <div class="admin-dcell" role="cell" data-label="تاریخ عضویت"><?= e(date('Y/m/d H:i', strtotime($u['created_at']))) ?></div>
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
