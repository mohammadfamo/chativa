<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_login();

$me = current_user();
$conversationId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($conversationId <= 0 || !user_in_conversation($conversationId, (int) $me['id'])) {
    set_flash('error', 'گفتگوی مورد نظر پیدا نشد.');
    redirect('chat.php');
}

$activeConversationId = $conversationId;
$partner = conversation_partner($conversationId, (int) $me['id']);

if ($partner === null) {
    redirect('chat.php');
}

// باز کردن این صفحه یعنی پیام‌های طرف مقابل را دیده‌ام
mark_conversation_seen($conversationId, (int) $me['id']);

$lastSeenId = last_seen_message_id($conversationId, (int) $me['id']);
$blockState = conversation_block_state($conversationId, (int) $me['id']) ?? ['i_blocked' => false, 'blocked_by_partner' => false];

// نتیجه‌ی کلیک روی یک آیتم جستجو (مرحله ۱۰): به‌جای ۵۰ پیام آخر، چند پیام
// قبل/بعد از پیام موردنظر بارگذاری می‌شود تا کاربر بلافاصله آن را در بافتار
// واقعی‌اش ببیند؛ main.js با دیدن cfg.aroundId به‌جای اسکرول به پایین،
// همان پیام را اسکرول+هایلایت می‌کند.
$aroundId = isset($_GET['around']) ? (int) $_GET['around'] : 0;

$messageSelect =
    'SELECT pm.id, pm.sender_id, pm.body, pm.reply_to_id, pm.edited_at, pm.created_at,
            pm.attachment_type, pm.attachment_path, pm.attachment_name, pm.attachment_size, pm.attachment_duration,
            rpm.body AS reply_body, rpm.deleted_at AS reply_deleted_at,
            ru.username AS reply_username, ru.display_name AS reply_display_name
     FROM private_messages pm
     LEFT JOIN private_messages rpm ON rpm.id = pm.reply_to_id
     LEFT JOIN users ru ON ru.id = rpm.sender_id
     WHERE pm.conversation_id = ? AND pm.deleted_at IS NULL
       AND NOT EXISTS (SELECT 1 FROM private_message_hides pmh WHERE pmh.message_id = pm.id AND pmh.user_id = ?)';

if ($aroundId > 0) {
    $beforeStmt = db()->prepare($messageSelect . ' AND pm.id <= ? ORDER BY pm.id DESC LIMIT 25');
    $beforeStmt->execute([$conversationId, (int) $me['id'], $aroundId]);
    $afterStmt = db()->prepare($messageSelect . ' AND pm.id > ? ORDER BY pm.id ASC LIMIT 25');
    $afterStmt->execute([$conversationId, (int) $me['id'], $aroundId]);
    $initialMessages = array_merge(array_reverse($beforeStmt->fetchAll()), $afterStmt->fetchAll());
} else {
    $stmt = db()->prepare($messageSelect . ' ORDER BY pm.id DESC LIMIT 50');
    $stmt->execute([$conversationId, (int) $me['id']]);
    $initialMessages = array_reverse($stmt->fetchAll());
}
$lastId = $initialMessages !== [] ? (int) end($initialMessages)['id'] : 0;

$pinnedMessage = get_pinned_message_payload('private', $conversationId);
$reactionsByMessage = get_reactions_for_messages('private', array_map(static fn (array $m): int => (int) $m['id'], $initialMessages), (int) $me['id']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= favicon_links_html() ?>
<script src="<?= e(url('assets/js/theme.js')) ?>"></script>
<title><?= e($partner['username']) ?> | <?= e(app_name()) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div id="page-content">
<div class="app-shell">

  <?php require ROOT_PATH . '/includes/partials/sidebar.php'; ?>

  <main class="chat-main">
    <header class="chat-header">
      <button type="button" class="btn-back-mobile" onclick="document.body.classList.add('mobile-view-list')" aria-label="بازگشت"><?= icon_svg('chevron-back') ?></button>
      <?= avatar_html($partner) ?>
      <div class="chat-header-title">
        <span class="username"><?= e(display_name_of($partner)) ?></span>
        <span class="chat-subtitle <?= is_user_online($partner['last_seen'] ?? null) ? 'online' : '' ?>" id="chat-subtitle"><?= e(partner_status_label($partner['last_seen'] ?? null)) ?></span>
      </div>
      <div class="chat-header-actions">
        <button type="button" id="btn-search" class="icon-action-btn" title="جستجو در پیام‌ها"><?= icon_svg('search') ?></button>
        <button type="button" id="btn-call" class="icon-action-btn" title="تماس صوتی زنده"><?= icon_svg('phone') ?></button>
        <button type="button" id="btn-block" class="icon-action-btn" title="<?= $blockState['i_blocked'] ? 'رفع بلاک' : 'بلاک کاربر' ?>">
          <?= $blockState['i_blocked'] ? icon_svg('unlock') : icon_svg('ban') ?>
        </button>
        <button type="button" id="btn-select-messages" class="icon-action-btn" title="انتخاب چندتایی پیام‌ها"><?= icon_svg('check-circle') ?></button>
      </div>
    </header>

    <div id="search-bar" class="search-bar hidden">
      <span class="search-bar-icon"><?= icon_svg('search') ?></span>
      <input type="text" id="search-input" class="search-input" placeholder="جستجو در پیام‌ها...">
      <button type="button" id="btn-search-close" class="icon-action-btn" title="بستن جستجو"><?= icon_svg('x') ?></button>
      <div id="search-results" class="search-results hidden"></div>
    </div>

    <div id="call-bar" class="call-bar hidden">
      <div class="call-bar-info">
        <span class="call-bar-avatar"><?= icon_svg('mic') ?></span>
        <div class="call-bar-text">
          <span id="call-bar-title">در حال تماس...</span>
          <span id="call-bar-timer" class="call-bar-timer hidden">00:00</span>
        </div>
      </div>
      <div class="call-bar-actions">
        <button type="button" id="btn-call-mute" class="icon-action-btn hidden" title="بی‌صدا"><?= icon_svg('mic') ?></button>
        <button type="button" id="btn-call-decline" class="btn-mini btn-mini-danger hidden">رد تماس</button>
        <button type="button" id="btn-call-accept" class="btn-mini btn-mini-primary hidden">پاسخ</button>
        <button type="button" id="btn-call-hangup" class="icon-action-btn call-hangup hidden" title="پایان تماس"><?= icon_svg('phone-hangup') ?></button>
      </div>
    </div>
    <audio id="call-remote-audio" autoplay hidden></audio>

    <?= pinned_bar_html($pinnedMessage) ?>

    <?php
      $bannerVisible = $blockState['i_blocked'] || $blockState['blocked_by_partner'];
      $bannerText = $blockState['i_blocked']
          ? 'این کاربر را بلاک کرده‌اید. برای ارسال پیام، ابتدا رفع بلاک کنید.'
          : 'امکان ارسال پیام به این کاربر وجود ندارد.';
    ?>
    <div class="chat-banner <?= $bannerVisible ? '' : 'hidden' ?>" id="chat-banner"><?= e($bannerText) ?></div>

    <div id="messages" class="messages" data-last-id="<?= $lastId ?>">
      <?php if ($initialMessages === []): ?>
        <p class="empty-state">اولین پیام رو به <?= e($partner['username']) ?> بفرست 👋</p>
      <?php endif; ?>
      <?php foreach ($initialMessages as $msg): ?>
        <?php $mine = (int) $msg['sender_id'] === (int) $me['id']; ?>
        <?php $seen = $mine && (int) $msg['id'] <= $lastSeenId; ?>
        <div class="msg <?= $mine ? 'msg-mine' : 'msg-other' ?>" data-id="<?= (int) $msg['id'] ?>" data-raw-body="<?= e($msg['body']) ?>">
          <span class="msg-select-check"><?= icon_svg('check') ?></span>
          <?php if (!$mine): ?>
            <?= avatar_html($partner, 'avatar-sm') ?>
          <?php endif; ?>
          <div class="bubble">
            <?= reply_quote_html($msg) ?>
            <?= render_attachment_html($msg['attachment_type'], $msg['attachment_path'], $msg['attachment_duration'] !== null ? (int) $msg['attachment_duration'] : null, $msg['attachment_name'], $msg['attachment_size'] !== null ? (int) $msg['attachment_size'] : null) ?>
            <?php if (trim((string) $msg['body']) !== ''): ?>
              <p class="msg-body"><?= nl2br(linkify_message(e($msg['body']))) ?></p>
            <?php endif; ?>
            <?= reactions_html($reactionsByMessage[(int) $msg['id']] ?? []) ?>
            <span class="msg-time">
              <span class="msg-time-text"><?= e(date('H:i', strtotime($msg['created_at']))) ?></span>
              <?= edited_label_html($msg['edited_at']) ?>
              <?php if ($mine): ?>
                <span class="msg-ticks <?= $seen ? 'seen' : '' ?>"><?= $seen ? icon_svg('check-double') : icon_svg('check') ?></span>
              <?php endif; ?>
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
    mode: 'private',
    conversationId: <?= (int) $conversationId ?>,
    fetchUrl: 'api/fetch_private_messages.php',
    sendUrl: 'api/send_private_message.php',
    attachmentUrl: 'api/send_private_attachment.php',
    typingUrl: 'api/typing.php',
    toggleBlockUrl: 'api/toggle_block_conversation.php',
    wsTokenUrl: 'api/ws_token.php',
    markSeenUrl: 'api/mark_seen.php',
    deleteUrl: 'api/delete_message.php',
    pinUrl: 'api/toggle_pin_message.php',
    reactionUrl: 'api/toggle_reaction.php',
    reactionEmojis: <?= json_encode(allowed_reaction_emojis(), JSON_UNESCAPED_UNICODE) ?>,
    searchUrl: 'api/search_messages.php',
    aroundId: <?= (int) $aroundId ?>,
    isAdmin: <?= is_admin() ? 'true' : 'false' ?>,
    partnerName: <?= json_encode($partner['username'], JSON_UNESCAPED_UNICODE) ?>,
    iBlocked: <?= $blockState['i_blocked'] ? 'true' : 'false' ?>,
    blockedByPartner: <?= $blockState['blocked_by_partner'] ? 'true' : 'false' ?>,
    turn: {
      url: <?= json_encode(get_setting('turn_url', ''), JSON_UNESCAPED_SLASHES) ?>,
      username: <?= json_encode(get_setting('turn_username', ''), JSON_UNESCAPED_UNICODE) ?>,
      credential: <?= json_encode(get_setting('turn_credential', ''), JSON_UNESCAPED_UNICODE) ?>
    }
  };
</script>
<script src="<?= e(url('assets/js/icons.js')) ?>"></script>
<script src="<?= e(url('assets/js/main.js')) ?>"></script>
<script src="<?= e(url('assets/js/call.js')) ?>"></script>
</div>
<script src="<?= e(url('assets/js/dialog.js')) ?>"></script>
<script src="<?= e(url('assets/js/notifications.js')) ?>"></script>
<script src="<?= e(url('assets/js/profile.js')) ?>"></script>
<script src="<?= e(url('assets/js/sidebar-unread.js')) ?>"></script>
<script src="<?= e(url('assets/js/pjax.js')) ?>"></script>
</body>
</html>
