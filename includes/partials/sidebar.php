<?php
/**
 * سایدبار مشترک بین چت عمومی و چت‌های خصوصی.
 * متغیرهای مورد انتظار قبل از include شدن این فایل:
 *   $me                    آرایه‌ی کاربر جاری (از current_user())
 *   $activeConversationId  شناسه گفتگوی خصوصی فعال یا null اگر چت عمومی فعال است
 *   $activeGroupId         شناسه گروه فعال (اختیاری - اگر تنظیم نشود، هیچ گروهی فعال نیست)
 */

declare(strict_types=1);

$sidebarConversations = list_conversations_for_user((int) $me['id']);
$publicUnread = count_unread_public((int) $me['id']);
$sidebarGroups = list_groups_for_user((int) $me['id']);
$activeGroupId = $activeGroupId ?? null;
$activeConversationId = $activeConversationId ?? null;
?>
<input type="hidden" id="global-csrf-token" value="<?= e(csrf_token()) ?>" data-base-url="<?= e(url('')) ?>">
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="brand">
      <?= brand_logo_html() ?>
      <span class="brand-name"><?= e(app_name()) ?></span>
    </div>
    <div class="sidebar-header-actions">
      <button type="button" id="btn-sidebar-menu" class="icon-btn" title="منو" data-is-admin="<?= is_admin() ? '1' : '0' ?>"><?= icon_svg('dots') ?></button>
    </div>
  </div>

  <form action="<?= e(url('new_chat.php')) ?>" method="get" class="sidebar-search">
    <input type="text" name="q" placeholder="جستجوی کاربر برای شروع گفتگو..." autocomplete="off">
  </form>

  <div class="sidebar-quick-actions">
    <a href="<?= e(url('new_group.php')) ?>" class="sidebar-quick-link">
      <?= icon_svg('users') ?>
      <span>گروه جدید</span>
    </a>
    <a href="<?= e(url('join_group.php')) ?>" class="sidebar-quick-link">
      <?= icon_svg('link') ?>
      <span>عضویت با لینک دعوت</span>
    </a>
  </div>

  <nav class="conversation-list">
    <a href="<?= e(url('chat.php')) ?>" class="conv-item <?= $activeConversationId === null && $activeGroupId === null ? 'active' : '' ?>">
      <span class="avatar" style="background:var(--color-primary)"><?= icon_svg('globe') ?></span>
      <span class="conv-info">
        <span class="conv-name">چت عمومی</span>
        <span class="conv-preview">گفتگوی همگانی</span>
      </span>
      <span class="unread-badge <?= $publicUnread > 0 ? '' : 'hidden' ?>" data-badge="public"><?= $publicUnread > 99 ? '99+' : $publicUnread ?></span>
    </a>

    <div id="group-conv-list">
      <?php foreach ($sidebarGroups as $group): ?>
        <?php $isGroupActive = $activeGroupId === (int) $group['group_id']; ?>
        <?= group_item_html($group, $isGroupActive) ?>
      <?php endforeach; ?>
    </div>

    <div id="private-conv-list">
      <?php foreach ($sidebarConversations as $conv): ?>
        <?php $isActive = $activeConversationId === (int) $conv['conversation_id']; ?>
        <?php $convUnread = count_unread_private((int) $conv['conversation_id'], (int) $me['id']); ?>
        <?= conv_item_html($conv, $convUnread, $isActive) ?>
      <?php endforeach; ?>

      <?php if ($sidebarConversations === [] && $sidebarGroups === []): ?>
        <p class="conv-empty" id="conv-empty-msg">هنوز گفتگوی خصوصی نداری. از باکس بالا یک کاربر پیدا کن.</p>
      <?php endif; ?>
    </div>
  </nav>
</aside>
