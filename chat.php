<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_login();

$me = current_user();
$activeConversationId = null;

// نتیجه‌ی کلیک روی یک آیتم جستجو (مرحله ۱۰): به‌جای ۵۰ پیام آخر، چند پیام
// قبل/بعد از پیام موردنظر بارگذاری می‌شود تا کاربر بلافاصله آن را در بافتار
// واقعی‌اش ببیند؛ main.js با دیدن cfg.aroundId به‌جای اسکرول به پایین،
// همان پیام را اسکرول+هایلایت می‌کند.
$aroundId = isset($_GET['around']) ? (int) $_GET['around'] : 0;

$messageSelect =
    'SELECT m.id, m.body, m.reply_to_id, m.edited_at, m.created_at, m.attachment_type, m.attachment_path, m.attachment_name, m.attachment_size, m.attachment_duration,
            u.id AS user_id, u.username, u.display_name, u.avatar_color, u.avatar_path,
            rm.body AS reply_body, rm.deleted_at AS reply_deleted_at, ru.username AS reply_username, ru.display_name AS reply_display_name
     FROM messages m
     JOIN users u ON u.id = m.user_id
     LEFT JOIN messages rm ON rm.id = m.reply_to_id
     LEFT JOIN users ru ON ru.id = rm.user_id
     WHERE m.deleted_at IS NULL
       AND NOT EXISTS (SELECT 1 FROM message_hides mh WHERE mh.message_id = m.id AND mh.user_id = ?)';

if ($aroundId > 0) {
    $beforeStmt = db()->prepare($messageSelect . ' AND m.id <= ? ORDER BY m.id DESC LIMIT 25');
    $beforeStmt->execute([(int) $me['id'], $aroundId]);
    $afterStmt = db()->prepare($messageSelect . ' AND m.id > ? ORDER BY m.id ASC LIMIT 25');
    $afterStmt->execute([(int) $me['id'], $aroundId]);
    $initialMessages = array_merge(array_reverse($beforeStmt->fetchAll()), $afterStmt->fetchAll());
} else {
    $stmt = db()->prepare($messageSelect . ' ORDER BY m.id DESC LIMIT 50');
    $stmt->execute([(int) $me['id']]);
    $initialMessages = array_reverse($stmt->fetchAll());
}
$lastId = $initialMessages !== [] ? (int) end($initialMessages)['id'] : 0;

// باز کردن این صفحه یعنی چت عمومی تا این لحظه دیده شده
mark_public_read((int) $me['id'], $lastId);

$pinnedMessage = get_pinned_message_payload('public', null);
$reactionsByMessage = get_reactions_for_messages('public', array_map(static fn (array $m): int => (int) $m['id'], $initialMessages), (int) $me['id']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= favicon_links_html() ?>
<script src="<?= e(url('assets/js/theme.js')) ?>"></script>
<title>چت عمومی | <?= e(app_name()) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div id="page-content">
<div class="app-shell">

  <?php require ROOT_PATH . '/includes/partials/sidebar.php'; ?>

  <main class="chat-main">
    <header class="chat-header">
      <button type="button" class="btn-back-mobile" onclick="document.body.classList.add('mobile-view-list')" aria-label="بازگشت"><?= icon_svg('chevron-back') ?></button>
      <span class="avatar" style="background:var(--color-primary)"><?= icon_svg('globe') ?></span>
      <div class="chat-header-title">
        <span class="username">چت عمومی</span>
        <span class="chat-subtitle">همه‌ی کاربران</span>
      </div>
      <div class="chat-header-actions">
        <button type="button" id="btn-search" class="icon-action-btn" title="جستجو در پیام‌ها"><?= icon_svg('search') ?></button>
      </div>
    </header>

    <div id="search-bar" class="search-bar hidden">
      <span class="search-bar-icon"><?= icon_svg('search') ?></span>
      <input type="text" id="search-input" class="search-input" placeholder="جستجو در پیام‌ها...">
      <button type="button" id="btn-search-close" class="icon-action-btn" title="بستن جستجو"><?= icon_svg('x') ?></button>
      <div id="search-results" class="search-results hidden"></div>
    </div>

    <?= pinned_bar_html($pinnedMessage) ?>

    <div id="messages" class="messages" data-last-id="<?= $lastId ?>">
      <?php if ($initialMessages === []): ?>
        <p class="empty-state">هنوز پیامی ارسال نشده. اولین نفری باش که سلام می‌کنه 👋</p>
      <?php endif; ?>
      <?php foreach ($initialMessages as $msg): ?>
        <?php $mine = (int) $msg['user_id'] === (int) $me['id']; ?>
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
            <?= reactions_html($reactionsByMessage[(int) $msg['id']] ?? []) ?>
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
    mode: 'public',
    conversationId: null,
    fetchUrl: 'api/fetch_messages.php',
    sendUrl: 'api/send_message.php',
    attachmentUrl: 'api/send_attachment.php',
    typingUrl: 'api/typing.php',
    wsTokenUrl: 'api/ws_token.php',
    deleteUrl: 'api/delete_message.php',
    pinUrl: 'api/toggle_pin_message.php',
    reactionUrl: 'api/toggle_reaction.php',
    reactionEmojis: <?= json_encode(allowed_reaction_emojis(), JSON_UNESCAPED_UNICODE) ?>,
    searchUrl: 'api/search_messages.php',
    aroundId: <?= (int) $aroundId ?>,
    isAdmin: <?= is_admin() ? 'true' : 'false' ?>
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
