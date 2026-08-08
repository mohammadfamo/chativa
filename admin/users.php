<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_admin();

$activeAdminTab = 'users';
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    $targetId = (int) ($_POST['user_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    if ($targetId > 0 && $targetId !== (int) $me['id']) {
        if ($action === 'toggle_block') {
            db()->prepare('UPDATE users SET is_blocked = 1 - is_blocked WHERE id = ?')->execute([$targetId]);
            set_flash('success', 'وضعیت مسدودیت کاربر تغییر کرد.');
        } elseif ($action === 'toggle_admin') {
            db()->prepare('UPDATE users SET is_admin = 1 - is_admin WHERE id = ?')->execute([$targetId]);
            set_flash('success', 'سطح دسترسی کاربر تغییر کرد.');
        } elseif ($action === 'toggle_verify') {
            db()->prepare(
                'UPDATE users SET email_verified_at = IF(email_verified_at IS NULL, NOW(), NULL) WHERE id = ?'
            )->execute([$targetId]);
            set_flash('success', 'وضعیت تایید ایمیل کاربر تغییر کرد.');
        } elseif ($action === 'delete') {
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
            set_flash('success', 'کاربر و تمام پیام‌های او حذف شد.');
        }
    }
    redirect('admin/users.php');
}

$query = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];
if ($query !== '') {
    $where = 'WHERE username LIKE ? OR email LIKE ?';
    $params = ['%' . $query . '%', '%' . $query . '%'];
}

$totalUsers = (int) (function () use ($where, $params) {
    $stmt = db()->prepare("SELECT COUNT(*) FROM users {$where}");
    $stmt->execute($params);
    return $stmt->fetchColumn();
})();
$totalPages = max(1, (int) ceil($totalUsers / $perPage));

$stmt = db()->prepare(
    "SELECT id, username, display_name, email, avatar_color, avatar_path, is_admin, is_blocked, email_verified_at, created_at
     FROM users {$where}
     ORDER BY id ASC
     LIMIT {$perPage} OFFSET {$offset}"
);
$stmt->execute($params);
$users = $stmt->fetchAll();

$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= favicon_links_html() ?>
<script src="<?= e(url('assets/js/theme.js')) ?>"></script>
<title>مدیریت کاربران | <?= e(app_name()) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="admin-page" id="page-content">
  <?php require ROOT_PATH . '/includes/partials/admin_header.php'; ?>

  <main class="admin-content">
    <?php foreach ($flashes as $flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>

    <form method="get" class="admin-search-form">
      <input type="text" name="q" value="<?= e($query) ?>" placeholder="جستجو با نام کاربری یا ایمیل...">
      <button type="submit" class="btn-mini">جستجو</button>
      <?php if ($query !== ''): ?>
        <a href="<?= e(url('admin/users.php')) ?>" class="btn-mini">پاک کردن</a>
      <?php endif; ?>
    </form>

    <div class="admin-dtable" role="table" aria-label="لیست کاربران">
      <div class="admin-drow admin-drow-head" role="row">
        <div class="admin-dcell" role="columnheader">کاربر</div>
        <div class="admin-dcell" role="columnheader">ایمیل</div>
        <div class="admin-dcell admin-dcell-narrow" role="columnheader">عضویت</div>
        <div class="admin-dcell admin-dcell-wide" role="columnheader">وضعیت</div>
        <div class="admin-dcell admin-dcell-wide" role="columnheader">عملیات</div>
      </div>

      <?php if ($users === []): ?>
        <p class="admin-dtable-empty">کاربری پیدا نشد.</p>
      <?php endif; ?>

      <?php foreach ($users as $u): ?>
        <div class="admin-drow" role="row">
          <div class="admin-dcell admin-user-cell" role="cell" data-label="کاربر">
            <?= avatar_html($u, 'avatar-sm') ?>
            <?= e($u['username']) ?>
          </div>
          <div class="admin-dcell" role="cell" data-label="ایمیل"><?= e($u['email']) ?></div>
          <div class="admin-dcell admin-dcell-narrow" role="cell" data-label="عضویت"><?= e(date('Y/m/d', strtotime($u['created_at']))) ?></div>
          <div class="admin-dcell admin-dcell-wide" role="cell" data-label="وضعیت">
            <?php if ((int) $u['is_admin'] === 1): ?><span class="badge badge-admin">ادمین</span><?php endif; ?>
            <?php if ((int) $u['is_blocked'] === 1): ?><span class="badge badge-blocked">مسدود</span><?php endif; ?>
            <?php if (empty($u['email_verified_at'])): ?>
              <span class="badge badge-blocked">ایمیل تایید‌نشده</span>
            <?php else: ?>
              <span class="badge badge-admin">ایمیل تایید‌شده</span>
            <?php endif; ?>
          </div>
          <div class="admin-dcell admin-dcell-wide admin-actions" role="cell" data-label="عملیات">
            <?php if ((int) $u['id'] === (int) $me['id']): ?>
              <span class="text-muted">خودت</span>
            <?php else: ?>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                <input type="hidden" name="action" value="toggle_block">
                <button type="submit" class="btn-mini">
                  <?= (int) $u['is_blocked'] === 1 ? 'رفع مسدودیت' : 'مسدود کردن' ?>
                </button>
              </form>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                <input type="hidden" name="action" value="toggle_admin">
                <button type="submit" class="btn-mini">
                  <?= (int) $u['is_admin'] === 1 ? 'حذف ادمین' : 'ادمین کردن' ?>
                </button>
              </form>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                <input type="hidden" name="action" value="toggle_verify">
                <button type="submit" class="btn-mini">
                  <?= empty($u['email_verified_at']) ? 'تایید دستی ایمیل' : 'لغو تایید ایمیل' ?>
                </button>
              </form>
              <form method="post" data-confirm="کاربر و تمام پیام‌های او برای همیشه حذف شود؟">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn-mini btn-mini-danger">حذف</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="admin-pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <a href="<?= e(url('admin/users.php?' . http_build_query(['q' => $query, 'page' => $p]))) ?>"
             class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </main>
</div>
<script src="<?= e(url('assets/js/icons.js')) ?>"></script>
<script src="<?= e(url('assets/js/dialog.js')) ?>"></script>
<script src="<?= e(url('assets/js/notifications.js')) ?>"></script>
<script src="<?= e(url('assets/js/profile.js')) ?>"></script>
<script src="<?= e(url('assets/js/pjax.js')) ?>"></script>
</body>
</html>
