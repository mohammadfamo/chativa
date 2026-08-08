<?php
/**
 * هدر مشترک پنل ادمین.
 * انتظار می‌رود متغیر $activeAdminTab قبل از include تعریف شده باشد.
 */
declare(strict_types=1);
?>
<input type="hidden" id="global-csrf-token" value="<?= e(csrf_token()) ?>" data-base-url="<?= e(url('')) ?>">
<header class="admin-header">
  <div class="brand">
    <?= brand_logo_html() ?>
    <span class="brand-name">پنل ادمین</span>
  </div>
  <nav class="admin-nav">
    <a href="<?= e(url('admin/index.php')) ?>" class="<?= $activeAdminTab === 'dashboard' ? 'active' : '' ?>">داشبورد</a>
    <a href="<?= e(url('admin/users.php')) ?>" class="<?= $activeAdminTab === 'users' ? 'active' : '' ?>">کاربران</a>
    <a href="<?= e(url('admin/messages.php')) ?>" class="<?= $activeAdminTab === 'messages' ? 'active' : '' ?>">چت عمومی</a>
    <a href="<?= e(url('admin/settings.php')) ?>" class="<?= $activeAdminTab === 'settings' ? 'active' : '' ?>">تنظیمات</a>
    <a href="<?= e(url('chat.php')) ?>">بازگشت به چت</a>
    <button type="button" id="btn-theme-toggle" class="icon-btn" title="تغییر حالت روشن/تاریک"><?= icon_svg('moon') ?></button>
  </nav>
</header>
