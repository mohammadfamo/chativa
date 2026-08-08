<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_login();

$me = current_user();
$groupId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($groupId <= 0 || !is_group_member($groupId, (int) $me['id'])) {
    set_flash('error', 'گروه مورد نظر پیدا نشد.');
    redirect('chat.php');
}

$activeGroupId = $groupId;
$group = get_group_row($groupId);

if ($group === null) {
    redirect('chat.php');
}

$myRole = group_member_role($groupId, (int) $me['id']);
$isGroupAdmin = in_array($myRole, ['owner', 'admin'], true);
$memberCount = group_member_count($groupId);

// نتیجه‌ی کلیک روی یک آیتم جستجو در آینده هم همین الگو را دنبال می‌کند
// (فعلاً جستجو برای گروه پیاده نشده)، اما بارگذاری «حول یک پیام» برای
// لینک‌های آینده از قبل آماده نگه داشته شده.
$aroundId = isset($_GET['around']) ? (int) $_GET['around'] : 0;

$messageSelect =
    'SELECT gm.id, gm.sender_id, gm.body, gm.reply_to_id, gm.edited_at, gm.created_at,
            gm.attachment_type, gm.attachment_path, gm.attachment_name, gm.attachment_size, gm.attachment_duration,
            u.username, u.display_name, u.avatar_color, u.avatar_path,
            rgm.body AS reply_body, rgm.deleted_at AS reply_deleted_at,
            ru.username AS reply_username, ru.display_name AS reply_display_name
     FROM group_messages gm
     JOIN users u ON u.id = gm.sender_id
     LEFT JOIN group_messages rgm ON rgm.id = gm.reply_to_id
     LEFT JOIN users ru ON ru.id = rgm.sender_id
     WHERE gm.group_id = ? AND gm.deleted_at IS NULL
       AND NOT EXISTS (SELECT 1 FROM group_message_hides gmh WHERE gmh.message_id = gm.id AND gmh.user_id = ?)';

if ($aroundId > 0) {
    $beforeStmt = db()->prepare($messageSelect . ' AND gm.id <= ? ORDER BY gm.id DESC LIMIT 25');
    $beforeStmt->execute([$groupId, (int) $me['id'], $aroundId]);
    $afterStmt = db()->prepare($messageSelect . ' AND gm.id > ? ORDER BY gm.id ASC LIMIT 25');
    $afterStmt->execute([$groupId, (int) $me['id'], $aroundId]);
    $initialMessages = array_merge(array_reverse($beforeStmt->fetchAll()), $afterStmt->fetchAll());
} else {
    $stmt = db()->prepare($messageSelect . ' ORDER BY gm.id DESC LIMIT 50');
    $stmt->execute([$groupId, (int) $me['id']]);
    $initialMessages = array_reverse($stmt->fetchAll());
}
$lastId = $initialMessages !== [] ? (int) end($initialMessages)['id'] : 0;

// باز کردن این صفحه یعنی پیام‌های گروه تا این لحظه دیده شده
mark_group_seen($groupId, (int) $me['id'], $lastId);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= favicon_links_html() ?>
<script src="<?= e(url('assets/js/theme.js')) ?>"></script>
<title><?= e($group['name']) ?> | <?= e(app_name()) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div id="page-content">
<div class="app-shell">

  <?php require ROOT_PATH . '/includes/partials/sidebar.php'; ?>

  <main class="chat-main">
    <header class="chat-header">
      <button type="button" class="btn-back-mobile" onclick="document.body.classList.add('mobile-view-list')" aria-label="بازگشت"><?= icon_svg('chevron-back') ?></button>
      <?= avatar_html(['username' => $group['name'], 'avatar_color' => $group['avatar_color'], 'avatar_path' => $group['avatar_path']], '', false) ?>
      <a href="<?= e(url('group_settings.php?id=' . $groupId)) ?>" class="chat-header-title chat-header-title-link">
        <span class="username"><?= e($group['name']) ?></span>
        <span class="chat-subtitle"><?= (int) $memberCount ?> عضو</span>
      </a>
      <div class="chat-header-actions">
        <a href="<?= e(url('group_settings.php?id=' . $groupId)) ?>" class="icon-action-btn" title="اعضا و تنظیمات گروه"><?= icon_svg('users') ?></a>
      </div>
    </header>

    <div id="messages" class="messages" data-last-id="<?= $lastId ?>">
      <?php if ($initialMessages === []): ?>
        <p class="empty-state">هنوز پیامی در این گروه ارسال نشده. اولین نفری باش که سلام می‌کنه 👋</p>
      <?php endif; ?>
      <?php foreach ($initialMessages as $msg): ?>
        <?php $mine = (int) $msg['sender_id'] === (int) $me['id']; ?>
        <div class="msg <?= $mine ? 'msg-mine' : 'msg-other' ?>" data-id="<?= (int) $msg['id'] ?>" data-raw-body="<?= e($msg['body']) ?>">
          <?php if (!$mine): ?>
            <?= avatar_html($msg, 'avatar-sm') ?>
          <?php endif; ?>
          <div class="bubble">
            <?= reply_quote_html($msg) ?>
            <?php if (!$mine): ?><span class="msg-author"><?= e(display_name_of($msg)) ?></span><?php endif; ?>
            <?= render_attachment_html($msg['attachment_type'], $msg['attachment_path'], $msg['attachment_duration'] !== null ? (int) $msg['attachment_duration'] : null, $msg['attachment_name'], $msg['attachment_size'] !== null ? (int) $msg['attachment_size'] : null) ?>
            <?php if (trim((string) $msg['body']) !== ''): ?>
              <p class="msg-body"><?= nl2br(linkify_message(e($msg['body']))) ?></p>
            <?php endif; ?>
            <div class="msg-reactions"></div>
            <span class="msg-time">
              <span class="msg-time-text"><?= e(date('H:i', strtotime($msg['created_at']))) ?></span>
              <?= edited_label_html($msg['edited_at']) ?>
            </span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div id="typing-indicator" class="typing-indicator hidden"></div>

    <div id="compose-context" class="compose-context hidden"></div>
    <form id="chat-form" class="chat-input-bar" data-no-pjax>
      <input type="hidden" id="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="file" id="attachment-input" accept="image/*,video/*" hidden>
      <input type="file" id="document-input" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.7z,.txt,.csv,.json" hidden>
      <button type="button" id="btn-attach" class="icon-action-btn" title="ارسال عکس/ویدیو"><?= icon_svg('attach') ?></button>
      <button type="button" id="btn-document" class="icon-action-btn" title="ارسال فایل/سند"><?= icon_svg('file') ?></button>
      <button type="button" id="btn-emoji" class="icon-action-btn" title="ایموجی"><?= icon_svg('smile') ?></button>
      <textarea id="message-input" placeholder="پیام خود را بنویسید..." rows="1" maxlength="2000" required></textarea>
      <button type="button" id="btn-voice" class="icon-action-btn" title="ضبط پیام صوتی"><?= icon_svg('mic') ?></button>
      <button type="submit" class="btn-send" aria-label="ارسال"><?= icon_svg('send') ?></button>
    </form>
    <div id="emoji-panel" class="emoji-panel hidden"></div>
    <div id="voice-recorder" class="voice-recorder hidden">
      <span class="voice-recorder-dot"></span>
      <span id="voice-timer">00:00</span>
      <span class="voice-recorder-label">در حال ضبط...</span>
      <button type="button" id="btn-voice-cancel" class="btn-mini">لغو</button>
      <button type="button" id="btn-voice-send" class="btn-mini btn-mini-primary">ارسال</button>
    </div>
  </main>

</div>

<script>
  window.CHATIVA = {
    baseUrl: <?= json_encode(url(''), JSON_UNESCAPED_SLASHES) ?>,
    myUserId: <?= (int) $me['id'] ?>,
    mode: 'group',
    conversationId: <?= (int) $groupId ?>,
    fetchUrl: 'api/fetch_group_messages.php',
    sendUrl: 'api/send_group_message.php',
    attachmentUrl: 'api/send_group_attachment.php',
    typingUrl: 'api/typing.php',
    wsTokenUrl: 'api/ws_token.php',
    deleteUrl: 'api/delete_message.php',
    isAdmin: <?= is_admin() ? 'true' : 'false' ?>,
    isGroupAdmin: <?= $isGroupAdmin ? 'true' : 'false' ?>,
    groupName: <?= json_encode($group['name'], JSON_UNESCAPED_UNICODE) ?>
  };
</script>
<script src="<?= e(url('assets/js/icons.js')) ?>"></script>
<script src="<?= e(url('assets/js/main.js')) ?>"></script>
</div>
<script src="<?= e(url('assets/js/dialog.js')) ?>"></script>
<script src="<?= e(url('assets/js/notifications.js')) ?>"></script>
<script src="<?= e(url('assets/js/profile.js')) ?>"></script>
<script src="<?= e(url('assets/js/sidebar-unread.js')) ?>"></script>
<script src="<?= e(url('assets/js/pjax.js')) ?>"></script>
</body>
</html>
