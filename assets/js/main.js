(function () {
  'use strict';

  const cfg = window.CHATIVA || {};
  const messagesEl = document.getElementById('messages');
  const formEl = document.getElementById('chat-form');
  const inputEl = document.getElementById('message-input');
  const csrfEl = document.getElementById('csrf_token');

  if (!messagesEl || !formEl) return;

  // چت گروهی (مرحله ۱۰) دقیقاً مثل چت خصوصی یک id مربوط به خودش (cfg.conversationId)
  // دارد، فقط scope سمت سرور با آن فرق می‌کند - این تابع مرکز تصمیم است
  // تا همه‌ی جاهایی که قبلاً فقط public/private را می‌شناختند، درست کار کنند.
  function currentScope() {
    if (cfg.mode === 'private') return 'private';
    if (cfg.mode === 'group') return 'group';
    return 'public';
  }

  const attachmentInputEl = document.getElementById('attachment-input');
  const btnAttach = document.getElementById('btn-attach');
  const documentInputEl = document.getElementById('document-input');
  const btnDocument = document.getElementById('btn-document');
  const btnEmoji = document.getElementById('btn-emoji');
  const emojiPanelEl = document.getElementById('emoji-panel');
  const btnVoice = document.getElementById('btn-voice');
  const voiceRecorderEl = document.getElementById('voice-recorder');
  const voiceTimerEl = document.getElementById('voice-timer');
  const btnVoiceCancel = document.getElementById('btn-voice-cancel');
  const btnVoiceSend = document.getElementById('btn-voice-send');
  const typingIndicatorEl = document.getElementById('typing-indicator');
  const btnBlock = document.getElementById('btn-block');
  const chatBannerEl = document.getElementById('chat-banner');
  const composeContextEl = document.getElementById('compose-context');
  const pinnedBarEl = document.getElementById('pinned-bar');
  const btnSearch = document.getElementById('btn-search');
  const searchBarEl = document.getElementById('search-bar');
  const searchInputEl = document.getElementById('search-input');
  const btnSearchClose = document.getElementById('btn-search-close');
  const searchResultsEl = document.getElementById('search-results');

  function dialogAlert(message) {
    return window.ChativaDialog ? window.ChativaDialog.alert(message) : Promise.resolve(alert(message));
  }
  function dialogConfirm(message, opts) {
    return window.ChativaDialog ? window.ChativaDialog.confirm(message, opts) : Promise.resolve(confirm(message));
  }

  const POLL_INTERVAL_ACTIVE = 1800;   // ms - وقتی تب فعاله و WebSocket نداریم
  const POLL_INTERVAL_HIDDEN = 6000;   // ms - وقتی تب پس‌زمینه‌ست و WebSocket نداریم
  const POLL_INTERVAL_WS_SAFETY_NET = 20000; // ms - وقتی WebSocket وصله (فقط برای اطمینان)

  let lastId = parseInt(messagesEl.dataset.lastId || '0', 10);
  let pollTimer = null;
  let isSending = false;
  let isUploading = false;
  let replyToId = null;
  let editingMessageId = null;

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
  }

  // تبدیل @username در متنِ از قبل escape‌شده به یک span قابل‌کلیک (باز شدن مودال پروفایل).
  // فقط باید روی خروجیِ escapeHtml اجرا شود، نه متن خام.
  function linkifyMentions(escapedHtml) {
    return escapedHtml.replace(
      /@([a-zA-Z0-9_]{3,32})/g,
      '<span class="msg-mention js-mention" data-username="$1" role="button" tabindex="0">@$1</span>'
    );
  }

  // تبدیل لینک‌های http(s) به <a target="_blank"> + linkifyMentions برای
  // @username - باید دقیقاً هم‌ساختار با linkify_message() در
  // includes/functions.php بماند (همان تکه‌تکه‌کردن بر اساس URL قبل از
  // اجرای linkifyMentions، تا URL هایی که خودشان شامل @username هستند خراب نشوند).
  function linkifyMessage(escapedHtml) {
    const parts = escapedHtml.split(/(https?:\/\/[^\s<>"]+)/i);
    let out = '';
    parts.forEach((part, i) => {
      if (part === '') return;
      if (i % 2 === 1) {
        const cleanUrl = part.replace(/[.,!?؛:؟)\]}]+$/, '');
        const trailing = part.slice(cleanUrl.length);
        if (cleanUrl === '') {
          out += part;
          return;
        }
        out += `<a href="${cleanUrl}" target="_blank" rel="noopener noreferrer nofollow" class="msg-link">${cleanUrl}</a>${trailing}`;
      } else {
        out += linkifyMentions(part);
      }
    });
    return out;
  }

  // نقل‌قول پیامِ ریپلای‌شده بالای حباب (مرحله ۷) - باید دقیقاً هم‌ساختار با
  // reply_quote_html() در includes/functions.php بماند.
  function replyQuoteHtml(reply) {
    if (!reply) return '';
    if (reply.deleted) {
      return `<div class="msg-reply-quote" data-reply-id="${reply.id}"><span class="msg-reply-quote-author">پیام حذف‌شده</span></div>`;
    }
    const author = escapeHtml(reply.author || '');
    const snippet = escapeHtml(reply.snippet || '') || 'پیوست';
    return (
      `<div class="msg-reply-quote" data-reply-id="${reply.id}">` +
      `<span class="msg-reply-quote-author">${author}</span>` +
      `<span class="msg-reply-quote-snippet">${snippet}</span>` +
      '</div>'
    );
  }

  function editedLabelHtml(editedTimeLabel) {
    if (!editedTimeLabel) return '';
    return `<span class="msg-edited-label" title="ویرایش‌شده در ${escapeHtml(editedTimeLabel)}">ویرایش‌شده</span>`;
  }

  function isNearBottom() {
    return messagesEl.scrollHeight - messagesEl.scrollTop - messagesEl.clientHeight < 120;
  }

  function scrollToBottom() {
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function removeEmptyState() {
    const empty = messagesEl.querySelector('.empty-state');
    if (empty) empty.remove();
  }

  // ---------------------------------------------------------
  // ری‌اکشن ایموجی به پیام (مرحله ۱۰)
  // ---------------------------------------------------------
  function reactionPillsHtml(reactions) {
    if (!reactions || !reactions.length) return '';
    return reactions
      .map(
        (r) =>
          `<button type="button" class="msg-reaction-pill${r.mine ? ' mine' : ''}" data-emoji="${escapeHtml(r.emoji)}">` +
          `<span class="msg-reaction-emoji">${escapeHtml(r.emoji)}</span>` +
          `<span class="msg-reaction-count">${r.count}</span>` +
          '</button>'
      )
      .join('');
  }

  function renderReactionsForMessage(messageId, reactions) {
    const msgEl = messagesEl.querySelector(`.msg[data-id="${messageId}"]`);
    if (!msgEl) return;
    const container = msgEl.querySelector('.msg-reactions');
    if (container) container.innerHTML = reactionPillsHtml(reactions);
  }

  async function sendReaction(messageId, emoji) {
    if (!cfg.reactionUrl) return;
    try {
      const res = await fetch(`${cfg.baseUrl}${cfg.reactionUrl}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          scope: cfg.mode === 'private' ? 'private' : 'public',
          message_id: messageId,
          emoji,
          csrf_token: csrfEl.value,
        }),
      });
      const data = await res.json();
      if (res.ok && data.ok) {
        renderReactionsForMessage(data.message_id, data.reactions);
      } else {
        handleErrorResponse(data);
      }
    } catch (err) {
      dialogAlert('خطا در ارتباط با سرور.');
    }
  }

  function openReactionPickerFor(messageId, pos) {
    if (!window.ChativaDialog || !window.ChativaDialog.openReactionPicker) return;
    const emojis = cfg.reactionEmojis || ['👍', '❤️', '😂', '😮', '😢', '🙏'];
    window.ChativaDialog.openReactionPicker(emojis, pos, (emoji) => sendReaction(messageId, emoji));
  }

  messagesEl.addEventListener('click', (event) => {
    if (msgSelectMode) return;
    const pill = event.target.closest('.msg-reaction-pill');
    if (!pill) return;
    const msgEl = pill.closest('.msg[data-id]');
    if (!msgEl) return;
    sendReaction(parseInt(msgEl.dataset.id, 10), pill.dataset.emoji);
  });

  // دابل‌کلیک روی حباب پیام → باز شدن ردیف انتخاب سریع ری‌اکشن (مثل تلگرام/واتساپ)
  messagesEl.addEventListener('dblclick', (event) => {
    if (msgSelectMode) return;
    if (!cfg.reactionUrl) return; // مثلاً چت گروهی که فعلاً ری‌اکشن ندارد
    if (event.target.closest('a, button, .msg-reaction-pill, .msg-mention')) return;
    const msgEl = event.target.closest('.msg[data-id]');
    if (!msgEl) return;
    const messageId = parseInt(msgEl.dataset.id, 10);
    if (!messageId) return;
    openReactionPickerFor(messageId, { x: event.clientX, y: event.clientY });
  });

  /** متن کوتاه پیام برای اعلان مرورگر - مرحله ۱۰ */
  function notificationBodyFor(msg) {
    const raw = (msg.body || '').trim();
    if (raw) return raw.length > 120 ? raw.slice(0, 120) + '…' : raw;
    if (msg.attachment_type === 'image') return '📷 عکس';
    if (msg.attachment_type === 'video') return '🎬 ویدیو';
    if (msg.attachment_type === 'voice') return '🎤 پیام صوتی';
    if (msg.attachment_type === 'document') return '📎 ' + (msg.attachment_name || 'فایل');
    return '';
  }

  function formatFileSize(bytes) {
    if (!bytes || bytes <= 0) return '';
    if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  function fileExtensionOf(path) {
    const match = /\.([a-zA-Z0-9]+)$/.exec(path || '');
    return match ? match[1].toUpperCase() : '';
  }

  function attachmentHtml(msg) {
    if (!msg.attachment_type || !msg.attachment_url) return '';
    const safeUrl = escapeHtml(msg.attachment_url);

    if (msg.attachment_type === 'image') {
      return `<a href="${safeUrl}" target="_blank" rel="noopener" class="attachment-image-link" data-lightbox="image"><img src="${safeUrl}" alt="عکس" class="attachment-image" loading="lazy"></a>`;
    }
    if (msg.attachment_type === 'video') {
      const expandIcon = (window.ChativaIcons && window.ChativaIcons.image) || '';
      return `<div class="attachment-video-wrap"><video src="${safeUrl}" class="attachment-video" controls preload="metadata"></video><button type="button" class="attachment-video-expand" data-lightbox="video" data-src="${safeUrl}" title="نمایش بزرگ">${expandIcon}</button></div>`;
    }
    if (msg.attachment_type === 'voice') {
      return `<audio src="${safeUrl}" class="attachment-voice" controls preload="metadata"></audio>`;
    }
    if (msg.attachment_type === 'document') {
      const displayName = msg.attachment_name || 'فایل';
      const metaParts = [fileExtensionOf(msg.attachment_url), formatFileSize(msg.attachment_size)].filter(Boolean);
      const fileIcon = (window.ChativaIcons && window.ChativaIcons.file) || '';
      const downloadIcon = (window.ChativaIcons && window.ChativaIcons.download) || '';
      return (
        `<a href="${safeUrl}" target="_blank" rel="noopener" download="${escapeHtml(displayName)}" class="attachment-document">` +
        `<span class="attachment-document-icon">${fileIcon}</span>` +
        '<span class="attachment-document-info">' +
        `<span class="attachment-document-name">${escapeHtml(displayName)}</span>` +
        `<span class="attachment-document-meta">${escapeHtml(metaParts.join(' · '))}</span>` +
        '</span>' +
        `<span class="attachment-document-download">${downloadIcon}</span>` +
        '</a>'
      );
    }
    return '';
  }

  function renderMessage(msg) {
    // جلوگیری از رندر تکراری (مثلاً پیامی که هم از پاسخ HTTP ارسال و هم از
    // طریق WebSocket broadcast دریافت شده، یا در polling آهسته‌ی safety-net دوباره آمده)
    if (messagesEl.querySelector('.msg[data-id="' + msg.id + '"]')) {
      return;
    }

    removeEmptyState();
    const wasNearBottom = isNearBottom();

    const wrapper = document.createElement('div');
    wrapper.className = 'msg ' + (msg.is_mine ? 'msg-mine' : 'msg-other');
    wrapper.dataset.id = msg.id;
    wrapper.dataset.rawBody = msg.body || '';

    const displayName = msg.display_name || msg.username;
    const avatarHtml = msg.is_mine
      ? ''
      : msg.avatar_path
        ? `<span class="avatar avatar-sm avatar-photo js-avatar" data-user-id="${msg.user_id}" role="button" tabindex="0"><img src="${escapeHtml(msg.avatar_path)}" alt="" loading="lazy"></span>`
        : `<span class="avatar avatar-sm js-avatar" data-user-id="${msg.user_id}" role="button" tabindex="0" style="background:${escapeHtml(msg.color)}">${escapeHtml(msg.avatar)}</span>`;

    const authorHtml = msg.is_mine ? '' : `<span class="msg-author">${escapeHtml(displayName)}</span>`;
    const hasBody = !!(msg.body && String(msg.body).trim() !== '');
    const checkIcon = (window.ChativaIcons && window.ChativaIcons.check) || '';
    const ticksHtml = (msg.is_mine && cfg.mode === 'private') ? ` <span class="msg-ticks">${checkIcon}</span>` : '';

    wrapper.innerHTML = `
      ${avatarHtml}
      <div class="bubble">
        ${replyQuoteHtml(msg.reply_to)}
        ${authorHtml}
        ${attachmentHtml(msg)}
        ${hasBody ? '<p class="msg-body"></p>' : ''}
        <div class="msg-reactions">${reactionPillsHtml(msg.reactions)}</div>
        <span class="msg-time">
          <span class="msg-time-text">${escapeHtml(msg.time_label)}</span>
          ${editedLabelHtml(msg.edited_time_label)}
          ${ticksHtml}
        </span>
      </div>
    `;

    if (hasBody) {
      wrapper.querySelector('.msg-body').innerHTML = linkifyMessage(escapeHtml(msg.body)).replace(/\n/g, '<br>');
    }

    messagesEl.appendChild(wrapper);
    lastId = Math.max(lastId, msg.id);

    if (wasNearBottom) scrollToBottom();

    // اعلان مرورگر (مرحله ۱۰) - فقط برای پیام‌های دیگران، و خودِ ChativaNotify
    // داخلی بررسی می‌کند که آیا کاربر اجازه داده/فعال کرده و تب پس‌زمینه است
    if (!msg.is_mine && window.ChativaNotify) {
      const isDirect = cfg.mode === 'private';
      const title = isDirect ? displayName : (cfg.mode === 'group' ? (cfg.groupName || 'گروه') : 'چت عمومی');
      const body = isDirect ? notificationBodyFor(msg) : `${displayName}: ${notificationBodyFor(msg)}`;
      window.ChativaNotify.notify(title, body, { tag: 'chativa-msg-' + msg.id });
    }
  }

  /** اعمال یک ویرایش روی پیامی که از قبل در DOM رندر شده (Polling/WebSocket) */
  function applyMessageEdit(messageId, body, editedTimeLabel) {
    const el = messagesEl.querySelector(`.msg[data-id="${messageId}"]`);
    if (!el) return;

    el.dataset.rawBody = body || '';
    const bubble = el.querySelector('.bubble');
    const timeEl = el.querySelector('.msg-time');
    let bodyEl = el.querySelector('.msg-body');
    const hasBody = !!(body && String(body).trim() !== '');

    if (hasBody) {
      if (!bodyEl) {
        bodyEl = document.createElement('p');
        bodyEl.className = 'msg-body';
        bubble.insertBefore(bodyEl, timeEl);
      }
      bodyEl.innerHTML = linkifyMessage(escapeHtml(body)).replace(/\n/g, '<br>');
    } else if (bodyEl) {
      bodyEl.remove();
    }

    if (timeEl) {
      let label = timeEl.querySelector('.msg-edited-label');
      if (!label) {
        label = document.createElement('span');
        label.className = 'msg-edited-label';
        const textEl = timeEl.querySelector('.msg-time-text');
        if (textEl) {
          textEl.insertAdjacentElement('afterend', label);
        } else {
          timeEl.appendChild(label);
        }
      }
      label.textContent = 'ویرایش‌شده';
      label.title = 'ویرایش‌شده در ' + (editedTimeLabel || '');
    }
  }

  // ---------------------------------------------------------
  // حذف/ویرایش/ریپلای پیام از طریق منوی راست‌کلیک/لمس‌طولانی - مرحله ۷
  // ---------------------------------------------------------
  async function deleteMessage(messageId, mode) {
    if (!cfg.deleteUrl) return;
    try {
      const res = await fetch(`${cfg.baseUrl}${cfg.deleteUrl}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          scope: currentScope(),
          message_id: messageId,
          mode,
          csrf_token: csrfEl.value,
        }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) {
        dialogAlert((data && data.error) ? 'خطا: ' + data.error : 'حذف پیام با خطا مواجه شد.');
        return;
      }
      const el = messagesEl.querySelector(`.msg[data-id="${messageId}"]`);
      if (el) el.remove();
    } catch (err) {
      dialogAlert('خطا در ارتباط با سرور.');
    }
  }

  async function confirmAndDeleteMessage(messageId, mode) {
    const message = mode === 'everyone' ? 'این پیام برای همه حذف شود؟' : 'این پیام فقط برای شما حذف شود؟';
    const ok = await dialogConfirm(message, { danger: true, confirmText: 'حذف' });
    if (ok) deleteMessage(messageId, mode);
  }

  // ---------------------------------------------------------
  // پین/آنپین پیام (مرحله ۱۰) - فقط یک پیام همزمان در هر چت/گفتگو پین
  // می‌ماند؛ نوار بالای پیام‌ها با renderPinnedBar زنده به‌روز می‌شود (هم از
  // پاسخ toggle خودمان، هم از polling عادی، هم از WebSocket broadcast).
  // ---------------------------------------------------------
  function renderPinnedBar(pinned) {
    if (!pinnedBarEl) return;
    if (!pinned) {
      pinnedBarEl.classList.add('hidden');
      pinnedBarEl.dataset.messageId = '0';
      return;
    }
    pinnedBarEl.dataset.messageId = String(pinned.id);
    const snippetEl = pinnedBarEl.querySelector('.pinned-bar-snippet');
    if (snippetEl) {
      const author = pinned.author || '';
      const snippet = pinned.snippet || '';
      snippetEl.textContent = author && snippet ? `${author}: ${snippet}` : (author || snippet);
    }
    pinnedBarEl.classList.remove('hidden');
  }

  async function togglePinMessage(messageId) {
    if (!cfg.pinUrl) return;
    try {
      const res = await fetch(`${cfg.baseUrl}${cfg.pinUrl}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          scope: cfg.mode === 'private' ? 'private' : 'public',
          message_id: messageId,
          csrf_token: csrfEl.value,
        }),
      });
      const data = await res.json();
      if (res.ok && data.ok) {
        renderPinnedBar(data.pinned_message);
      } else {
        handleErrorResponse(data);
      }
    } catch (err) {
      dialogAlert('خطا در ارتباط با سرور.');
    }
  }

  if (pinnedBarEl) {
    pinnedBarEl.addEventListener('click', (event) => {
      const messageId = parseInt(pinnedBarEl.dataset.messageId || '0', 10);
      if (!messageId) return;
      if (event.target.closest('.pinned-bar-close')) {
        togglePinMessage(messageId); // پیام پین‌شده است، پس این یعنی آنپین
        return;
      }
      const targetEl = messagesEl.querySelector(`.msg[data-id="${messageId}"]`);
      if (!targetEl) return;
      targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
      targetEl.classList.add('msg-highlight');
      setTimeout(() => targetEl.classList.remove('msg-highlight'), 1500);
    });
  }

  function messageSnippetFor(msgEl) {
    const raw = (msgEl.dataset.rawBody || '').trim();
    if (raw) return raw.length > 80 ? raw.slice(0, 80) + '…' : raw;
    if (msgEl.querySelector('.attachment-image, .attachment-image-link')) return '📷 عکس';
    if (msgEl.querySelector('.attachment-video')) return '🎬 ویدیو';
    if (msgEl.querySelector('.attachment-voice')) return '🎤 پیام صوتی';
    return '';
  }

  function authorNameFor(msgEl) {
    if (msgEl.classList.contains('msg-mine')) return 'شما';
    const authorEl = msgEl.querySelector('.msg-author');
    return (authorEl && authorEl.textContent) || cfg.partnerName || '';
  }

  function updateSendButtonMode() {
    const sendBtn = formEl.querySelector('.btn-send');
    if (!sendBtn) return;
    const iconName = editingMessageId !== null ? 'check' : 'send';
    sendBtn.innerHTML = (window.ChativaIcons && window.ChativaIcons[iconName]) || '';
    sendBtn.title = editingMessageId !== null ? 'ذخیره ویرایش' : 'ارسال';
  }

  function renderComposeContext(iconName, title, snippet) {
    if (!composeContextEl) return;
    composeContextEl.innerHTML =
      `<span class="compose-context-icon">${(window.ChativaIcons && window.ChativaIcons[iconName]) || ''}</span>` +
      '<span class="compose-context-body">' +
      '<span class="compose-context-title"></span>' +
      '<span class="compose-context-snippet"></span>' +
      '</span>' +
      `<button type="button" class="compose-context-cancel" title="انصراف">${(window.ChativaIcons && window.ChativaIcons.x) || ''}</button>`;
    composeContextEl.querySelector('.compose-context-title').textContent = title;
    composeContextEl.querySelector('.compose-context-snippet').textContent = snippet;
    composeContextEl.classList.remove('hidden');
    composeContextEl.querySelector('.compose-context-cancel').addEventListener('click', clearComposeContext);
  }

  function clearComposeContext() {
    replyToId = null;
    if (editingMessageId !== null) {
      editingMessageId = null;
      inputEl.value = '';
      autoResize();
    }
    if (composeContextEl) {
      composeContextEl.classList.add('hidden');
      composeContextEl.innerHTML = '';
    }
    updateSendButtonMode();
  }

  function startReply(messageId, msgEl) {
    editingMessageId = null;
    replyToId = messageId;
    renderComposeContext('reply', 'پاسخ به ' + authorNameFor(msgEl), messageSnippetFor(msgEl));
    updateSendButtonMode();
    inputEl.focus();
  }

  function startEdit(messageId, msgEl) {
    replyToId = null;
    editingMessageId = messageId;
    inputEl.value = msgEl.dataset.rawBody || '';
    autoResize();
    renderComposeContext('edit', 'ویرایش پیام', messageSnippetFor(msgEl));
    updateSendButtonMode();
    inputEl.focus();
  }

  async function editMessageRequest(messageId, body) {
    try {
      const res = await fetch(`${cfg.baseUrl}api/edit_message.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          scope: currentScope(),
          message_id: messageId,
          body,
          csrf_token: csrfEl.value,
        }),
      });
      const data = await res.json();
      if (res.ok && data.ok) {
        applyMessageEdit(data.id, data.body, data.edited_time_label);
      } else {
        handleErrorResponse(data);
      }
    } catch (err) {
      dialogAlert('خطا در ارتباط با سرور.');
    }
  }

  function openMessageContextMenu(msgEl, pos, sheet) {
    const messageId = parseInt(msgEl.dataset.id, 10);
    if (!messageId || !window.ChativaDialog) return;
    const canModerate = msgEl.classList.contains('msg-mine') || !!cfg.isAdmin || !!cfg.isGroupAdmin;
    // پین‌کردن در چت عمومی فقط برای ادمین (مثل کانال)؛ در گفتگوی خصوصی هر دو طرف؛
    // در گروه فعلاً پین/ری‌اکشن/جستجو پیاده نشده (cfg.pinUrl ست نمی‌شود)
    const canPin = !!cfg.pinUrl && (cfg.mode === 'private' || !!cfg.isAdmin);
    const isPinned = !!(pinnedBarEl && parseInt(pinnedBarEl.dataset.messageId || '0', 10) === messageId);

    const items = [];
    // انتخاب چندتایی/حذف دسته‌جمعی پیام فقط در چت خصوصی (messages.php) فعال است
    if (cfg.mode === 'private') {
      items.push({ label: 'انتخاب چندتایی', icon: 'check-circle', onSelect: () => enterMsgSelectMode(messageId) });
    }
    items.push({ label: 'پاسخ', icon: 'reply', onSelect: () => startReply(messageId, msgEl) });
    if (cfg.reactionUrl) {
      items.push({
        label: 'افزودن ری‌اکشن',
        icon: 'smile',
        onSelect: () => openReactionPickerFor(messageId, pos || { x: window.innerWidth / 2, y: window.innerHeight / 2 }),
      });
    }
    if (canPin) {
      items.push(
        isPinned
          ? { label: 'برداشتن پین', icon: 'pin', onSelect: () => togglePinMessage(messageId) }
          : { label: 'پین کردن پیام', icon: 'pin', onSelect: () => togglePinMessage(messageId) }
      );
    }
    if (canModerate) {
      items.push({ label: 'ویرایش', icon: 'edit', onSelect: () => startEdit(messageId, msgEl) });
    }
    items.push({ label: 'حذف برای من', icon: 'trash', onSelect: () => confirmAndDeleteMessage(messageId, 'me') });
    if (canModerate) {
      items.push({ label: 'حذف برای همه', icon: 'trash', danger: true, onSelect: () => confirmAndDeleteMessage(messageId, 'everyone') });
    }

    window.ChativaDialog.openMenu(items, pos, sheet);
  }

  messagesEl.addEventListener('contextmenu', (event) => {
    const msgEl = event.target.closest('.msg');
    if (!msgEl) return;
    event.preventDefault();
    openMessageContextMenu(msgEl, { x: event.clientX, y: event.clientY }, false);
  });

  let longPressTimer = null;
  let longPressStartPos = null;

  function cancelLongPress() {
    if (longPressTimer) {
      clearTimeout(longPressTimer);
      longPressTimer = null;
    }
  }

  messagesEl.addEventListener(
    'touchstart',
    (event) => {
      const msgEl = event.target.closest('.msg');
      if (!msgEl) return;
      const touch = event.touches[0];
      longPressStartPos = { x: touch.clientX, y: touch.clientY };
      longPressTimer = setTimeout(() => {
        longPressTimer = null;
        if (navigator.vibrate) {
          try { navigator.vibrate(15); } catch (e) { /* بی‌اهمیت */ }
        }
        openMessageContextMenu(msgEl, null, true);
      }, 500);
    },
    { passive: true }
  );

  messagesEl.addEventListener(
    'touchmove',
    (event) => {
      if (!longPressTimer || !longPressStartPos) return;
      const touch = event.touches[0];
      if (Math.abs(touch.clientX - longPressStartPos.x) > 10 || Math.abs(touch.clientY - longPressStartPos.y) > 10) {
        cancelLongPress();
      }
    },
    { passive: true }
  );

  messagesEl.addEventListener('touchend', cancelLongPress);
  messagesEl.addEventListener('touchcancel', cancelLongPress);

  // کلیک روی نقل‌قول ریپلای‌شده → اسکرول و هایلایت پیام اصلی (اگر هنوز در DOM باشد)
  messagesEl.addEventListener('click', (event) => {
    if (msgSelectMode) return;
    const quote = event.target.closest('.msg-reply-quote');
    if (!quote) return;
    const targetEl = messagesEl.querySelector(`.msg[data-id="${quote.dataset.replyId}"]`);
    if (!targetEl) return;
    targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    targetEl.classList.add('msg-highlight');
    setTimeout(() => targetEl.classList.remove('msg-highlight'), 1500);
  });

  function removeMessageFromDom(messageId) {
    const el = messagesEl.querySelector(`.msg[data-id="${messageId}"]`);
    if (el) el.remove();
  }

  // ---------------------------------------------------------
  // پاپ‌آپ نمایش بزرگ عکس/ویدیوی پیام‌ها + اسلاید قبلی/بعدی در همان چت.
  // فهرست آیتم‌های قابل‌نمایش هر بار از روی DOM فعلی #messages ساخته
  // می‌شود (نه یک آرایه‌ی جدا که باید دستی به‌روز نگه داشته شود) - یعنی
  // خودبه‌خود با پیام‌های تازه‌رسیده (پولینگ/وب‌سوکت) یا حذف‌شده هماهنگ
  // می‌ماند، دقیقاً مثل الگوی جمع‌آوری آیتم‌های سایر منوها در این پروژه.
  // ---------------------------------------------------------
  let lightboxState = null;

  function collectLightboxItems() {
    return Array.from(messagesEl.querySelectorAll('[data-lightbox]')).map((el) => ({
      type: el.dataset.lightbox,
      src: el.dataset.lightbox === 'image' ? el.getAttribute('href') : el.dataset.src,
      el,
    }));
  }

  function closeLightbox() {
    if (!lightboxState) return;
    document.removeEventListener('keydown', lightboxState.onKeydown, true);
    lightboxState.overlay.remove();
    lightboxState = null;
  }

  function renderLightboxMedia() {
    const state = lightboxState;
    if (!state) return;
    const item = state.items[state.index];
    state.mediaHost.innerHTML = '';
    if (item.type === 'image') {
      const img = document.createElement('img');
      img.src = item.src;
      img.alt = '';
      img.className = 'chativa-lightbox-img';
      state.mediaHost.appendChild(img);
    } else {
      const video = document.createElement('video');
      video.src = item.src;
      video.controls = true;
      video.autoplay = true;
      video.className = 'chativa-lightbox-video';
      state.mediaHost.appendChild(video);
    }
    state.counterEl.textContent = `${state.index + 1} / ${state.items.length}`;
    state.counterEl.classList.toggle('hidden', state.items.length <= 1);
    state.prevBtn.disabled = state.index <= 0;
    state.nextBtn.disabled = state.index >= state.items.length - 1;
  }

  function lightboxGoPrev() {
    if (lightboxState && lightboxState.index > 0) {
      lightboxState.index--;
      renderLightboxMedia();
    }
  }

  function lightboxGoNext() {
    if (lightboxState && lightboxState.index < lightboxState.items.length - 1) {
      lightboxState.index++;
      renderLightboxMedia();
    }
  }

  function openLightbox(startEl) {
    const items = collectLightboxItems();
    const startIndex = items.findIndex((it) => it.el === startEl);
    if (startIndex === -1) return;

    const icons = window.ChativaIcons || {};
    const overlay = document.createElement('div');
    overlay.className = 'chativa-lightbox-overlay';
    overlay.innerHTML =
      `<button type="button" class="chativa-lightbox-close" aria-label="بستن">${icons.x || '×'}</button>` +
      `<button type="button" class="chativa-lightbox-nav chativa-lightbox-prev" aria-label="قبلی">${icons['chevron-back'] || '‹'}</button>` +
      '<div class="chativa-lightbox-media"></div>' +
      `<button type="button" class="chativa-lightbox-nav chativa-lightbox-next" aria-label="بعدی">${icons['chevron-forward'] || '›'}</button>` +
      '<div class="chativa-lightbox-counter"></div>';
    document.body.appendChild(overlay);

    function onKeydown(event) {
      if (event.key === 'Escape') closeLightbox();
      else if (event.key === 'ArrowLeft') lightboxGoPrev();
      else if (event.key === 'ArrowRight') lightboxGoNext();
    }

    lightboxState = {
      items,
      index: startIndex,
      overlay,
      mediaHost: overlay.querySelector('.chativa-lightbox-media'),
      counterEl: overlay.querySelector('.chativa-lightbox-counter'),
      prevBtn: overlay.querySelector('.chativa-lightbox-prev'),
      nextBtn: overlay.querySelector('.chativa-lightbox-next'),
      onKeydown,
    };

    overlay.querySelector('.chativa-lightbox-close').addEventListener('click', closeLightbox);
    overlay.querySelector('.chativa-lightbox-prev').addEventListener('click', lightboxGoPrev);
    overlay.querySelector('.chativa-lightbox-next').addEventListener('click', lightboxGoNext);
    overlay.addEventListener('click', (event) => {
      if (event.target === overlay) closeLightbox();
    });
    document.addEventListener('keydown', onKeydown, true);

    renderLightboxMedia();
  }

  messagesEl.addEventListener('click', (event) => {
    if (msgSelectMode) return; // در حالت انتخاب چندتایی پیام، کلیک فقط باید تیک بزند
    const trigger = event.target.closest('[data-lightbox]');
    if (!trigger) return;
    event.preventDefault();
    openLightbox(trigger);
  });

  // ---------------------------------------------------------
  // انتخاب چندتایی پیام + حذف دسته‌جمعی (فقط چت خصوصی - messages.php)
  // ---------------------------------------------------------
  let msgSelectMode = false;
  let selectedMsgIds = new Set();

  function msgSelectBarEl() {
    return document.getElementById('msg-select-bar');
  }

  function updateMsgSelectBarCount() {
    const bar = msgSelectBarEl();
    if (!bar) return;
    const countEl = bar.querySelector('.msg-select-bar-count');
    if (countEl) {
      countEl.textContent = selectedMsgIds.size > 0 ? selectedMsgIds.size + ' پیام انتخاب شده' : 'پیامی را انتخاب کنید';
    }
    const meBtn = bar.querySelector('.msg-select-bar-delete-me');
    const everyoneBtn = bar.querySelector('.msg-select-bar-delete-everyone');
    if (meBtn) meBtn.disabled = selectedMsgIds.size === 0;
    if (everyoneBtn) everyoneBtn.disabled = selectedMsgIds.size === 0;
  }

  function renderMsgSelectBar() {
    if (msgSelectBarEl()) return;
    const bar = document.createElement('div');
    bar.className = 'msg-select-bar';
    bar.id = 'msg-select-bar';
    bar.innerHTML =
      '<button type="button" class="btn-mini msg-select-bar-cancel">لغو</button>' +
      '<span class="msg-select-bar-count">پیامی را انتخاب کنید</span>' +
      '<button type="button" class="btn-mini msg-select-bar-delete-me" disabled>حذف برای من</button>' +
      '<button type="button" class="btn-mini btn-mini-danger msg-select-bar-delete-everyone" disabled>حذف برای همه</button>';
    messagesEl.parentNode.insertBefore(bar, messagesEl);
    bar.querySelector('.msg-select-bar-cancel').addEventListener('click', exitMsgSelectMode);
    bar.querySelector('.msg-select-bar-delete-me').addEventListener('click', () => confirmAndBulkDeleteMessages('me'));
    bar.querySelector('.msg-select-bar-delete-everyone').addEventListener('click', () => confirmAndBulkDeleteMessages('everyone'));
  }

  function removeMsgSelectBar() {
    const bar = msgSelectBarEl();
    if (bar) bar.remove();
  }

  function syncMsgSelectedClasses() {
    messagesEl.querySelectorAll('.msg[data-id]').forEach((el) => {
      const id = parseInt(el.dataset.id, 10);
      el.classList.toggle('selected', selectedMsgIds.has(id));
    });
    updateMsgSelectBarCount();
  }

  function enterMsgSelectMode(initialId) {
    if (cfg.mode !== 'private') return;
    if (msgSelectMode) {
      if (initialId) {
        selectedMsgIds.add(initialId);
        syncMsgSelectedClasses();
      }
      return;
    }
    msgSelectMode = true;
    selectedMsgIds = new Set();
    if (initialId) selectedMsgIds.add(initialId);
    document.body.classList.add('msg-select-mode');
    renderMsgSelectBar();
    syncMsgSelectedClasses();
  }

  function exitMsgSelectMode() {
    if (!msgSelectMode) return;
    msgSelectMode = false;
    selectedMsgIds = new Set();
    document.body.classList.remove('msg-select-mode');
    removeMsgSelectBar();
    messagesEl.querySelectorAll('.msg.selected').forEach((el) => el.classList.remove('selected'));
  }

  async function bulkDeleteMessages(mode) {
    const ids = Array.from(selectedMsgIds);
    if (ids.length === 0) return;
    try {
      const res = await fetch(`${cfg.baseUrl}api/bulk_delete_messages.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ conversation_id: cfg.conversationId, message_ids: ids, mode, csrf_token: csrfEl.value }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) {
        dialogAlert('حذف پیام‌ها با خطا مواجه شد.');
        return;
      }
      (data.deleted_ids || []).forEach(removeMessageFromDom);
      if (data.skipped_ids && data.skipped_ids.length > 0 && mode === 'everyone') {
        dialogAlert('برخی پیام‌های انتخاب‌شده (چون مال شما نبودند) فقط برای شما حذف نشدند.');
      }
      exitMsgSelectMode();
    } catch (err) {
      dialogAlert('خطا در ارتباط با سرور.');
    }
  }

  async function confirmAndBulkDeleteMessages(mode) {
    if (selectedMsgIds.size === 0) return;
    const message = mode === 'everyone'
      ? selectedMsgIds.size + ' پیام برای همه حذف شود؟ پیام‌هایی که مال شما نباشند نادیده گرفته می‌شوند.'
      : selectedMsgIds.size + ' پیام فقط برای شما حذف شود؟';
    const ok = await dialogConfirm(message, { danger: true, confirmText: 'حذف' });
    if (ok) bulkDeleteMessages(mode);
  }

  if (cfg.mode === 'private') {
    // در حالت انتخاب، کلیک روی هر پیام فقط باید تیک آن را عوض کند - این
    // هندلر قبل از هندلر کلیک روی نقل‌قول ریپلای ثبت می‌شود و با
    // stopImmediatePropagation جلوی اجرای آن (روی همین کلیک) را می‌گیرد.
    messagesEl.addEventListener('click', (event) => {
      if (!msgSelectMode) return;
      const msgEl = event.target.closest('.msg[data-id]');
      if (!msgEl) return;
      event.stopImmediatePropagation();
      event.preventDefault();
      const id = parseInt(msgEl.dataset.id, 10);
      if (selectedMsgIds.has(id)) {
        selectedMsgIds.delete(id);
      } else {
        selectedMsgIds.add(id);
      }
      msgEl.classList.toggle('selected', selectedMsgIds.has(id));
      updateMsgSelectBarCount();
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && msgSelectMode) exitMsgSelectMode();
    });

    const btnSelectMessages = document.getElementById('btn-select-messages');
    if (btnSelectMessages) {
      btnSelectMessages.addEventListener('click', () => {
        if (msgSelectMode) exitMsgSelectMode();
        else enterMsgSelectMode();
      });
    }
  }

  function buildFetchUrl() {
    const fetchPath = cfg.fetchUrl || 'api/fetch_messages.php';
    const params = new URLSearchParams({ after_id: String(lastId) });
    if ((cfg.mode === 'private' || cfg.mode === 'group') && cfg.conversationId) {
      params.set('conversation_id', String(cfg.conversationId));
      // اگر تب مخفی/پس‌زمینه است، پولینگ برای دریافت پیام‌های جدید (badge و...)
      // ادامه دارد اما نباید «دیده‌شده» ثبت شود - فقط وقتی واقعاً کاربر این
      // مکالمه/گروه را می‌بیند علامت خوانده‌شده می‌خورد.
      params.set('mark_seen', document.hidden ? '0' : '1');
    }
    return `${cfg.baseUrl}${fetchPath}?${params.toString()}`;
  }

  // ---------------------------------------------------------
  // زیرعنوان هدر چت خصوصی (#chat-subtitle) - به‌جای @username ثابت قبلی،
  // حالا وضعیت آنلاین/آخرین بازدید طرف مقابل را نشان می‌دهد و وقتی او در
  // حال تایپ است، مثل تلگرام موقتاً جای آن را «در حال تایپ است...» می‌گیرد.
  // ---------------------------------------------------------
  const chatSubtitleEl = document.getElementById('chat-subtitle');
  let partnerOnlineLabel = chatSubtitleEl ? chatSubtitleEl.textContent : '';
  let partnerOnline = chatSubtitleEl ? chatSubtitleEl.classList.contains('online') : false;
  let partnerIsTyping = false;

  function renderChatSubtitle() {
    if (!chatSubtitleEl || cfg.mode !== 'private') return;
    if (partnerIsTyping) {
      chatSubtitleEl.textContent = 'در حال تایپ است...';
    } else {
      chatSubtitleEl.textContent = partnerOnlineLabel;
    }
    chatSubtitleEl.classList.toggle('typing', partnerIsTyping);
    chatSubtitleEl.classList.toggle('online', !partnerIsTyping && partnerOnline);
  }

  function updateTypingIndicator(usernames) {
    // در چت خصوصی، وضعیت تایپ در همان زیرعنوان هدر (#chat-subtitle) نشان
    // داده می‌شود، نه در نوار جداگانه‌ی #typing-indicator (که برای چت
    // عمومی/گروهی با چند کاربر همچنان لازم است).
    if (cfg.mode === 'private') {
      partnerIsTyping = !!(usernames && usernames.length > 0);
      renderChatSubtitle();
      return;
    }
    if (!typingIndicatorEl) return;
    if (!usernames || usernames.length === 0) {
      typingIndicatorEl.classList.add('hidden');
      typingIndicatorEl.textContent = '';
      return;
    }
    let text;
    if (usernames.length === 1) {
      text = `${usernames[0]} در حال تایپ است...`;
    } else if (usernames.length <= 3) {
      text = `${usernames.join('، ')} در حال تایپ‌اند...`;
    } else {
      text = 'چند نفر در حال تایپ‌اند...';
    }
    typingIndicatorEl.textContent = text;
    typingIndicatorEl.classList.remove('hidden');
  }

  function updateSeenTicks(lastSeenId) {
    if (!lastSeenId) return;
    messagesEl.querySelectorAll('.msg-mine').forEach((el) => {
      const id = parseInt(el.dataset.id, 10);
      const ticksEl = el.querySelector('.msg-ticks');
      if (!ticksEl || id > lastSeenId) return;
      ticksEl.innerHTML = (window.ChativaIcons && window.ChativaIcons['check-double']) || '';
      ticksEl.classList.add('seen');
    });
  }

  async function fetchNewMessages() {
    try {
      const res = await fetch(buildFetchUrl(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (!res.ok) {
        // در گروه، ۴۰۳ یعنی دیگر عضو نیستی (خودت خارج شدی/حذف شدی، یا
        // ادمین گروه را حذف کرد) - همین‌جا (بدون نیاز به رفرش) نشانش بده.
        if (res.status === 403 && cfg.mode === 'group') {
          showConversationWiped('شما دیگر عضو این گروه نیستید.');
        }
        return;
      }
      const data = await res.json();
      (data.messages || []).forEach(renderMessage);
      (data.deleted_ids || []).forEach(removeMessageFromDom);
      (data.edited || []).forEach((e) => applyMessageEdit(e.id, e.body, e.edited_time_label));
      updateTypingIndicator(data.typing);
      if ('pinned_message' in data) {
        renderPinnedBar(data.pinned_message);
      }
      if (cfg.mode === 'private' && typeof data.last_seen_id === 'number') {
        updateSeenTicks(data.last_seen_id);
      }
      if (cfg.mode === 'private' && typeof data.partner_status_label === 'string' && data.partner_status_label) {
        partnerOnlineLabel = data.partner_status_label;
        partnerOnline = !!data.partner_online;
        renderChatSubtitle();
      }
      // بلاک/رفع‌بلاک و حذف کامل مکالمه توسط طرف مقابل - از طریق پولینگ هم
      // اعمال می‌شود، نه فقط WebSocket (رفع باگ «باید رفرش کنی تا ببینی»).
      if (cfg.mode === 'private') {
        if (typeof data.blocked_by_partner === 'boolean' && data.blocked_by_partner !== blockedByPartner) {
          blockedByPartner = data.blocked_by_partner;
          updateBlockUI();
        }
        if (typeof data.i_blocked === 'boolean' && data.i_blocked !== iBlocked) {
          iBlocked = data.i_blocked;
          updateBlockUI();
        }
        if (data.conversation_wiped) {
          showConversationWiped();
        }
      }
    } catch (err) {
      // خطای شبکه؛ در تلاش بعدی دوباره امتحان می‌شود
    }
  }

  function currentPollInterval() {
    if (realtime.isConnected()) {
      return POLL_INTERVAL_WS_SAFETY_NET;
    }
    return document.hidden ? POLL_INTERVAL_HIDDEN : POLL_INTERVAL_ACTIVE;
  }

  function schedulePoll() {
    clearTimeout(pollTimer);
    if (destroyed) return;
    pollTimer = setTimeout(async () => {
      if (destroyed) return;
      await fetchNewMessages();
      schedulePoll();
    }, currentPollInterval());
  }

  function handleErrorResponse(data) {
    if (data && data.error === 'user_blocked') {
      dialogAlert('این کاربر مسدود شده و امکان ارسال پیام وجود ندارد.');
    } else if (data && data.error === 'email_not_verified') {
      dialogAlert('برای ارسال پیام ابتدا باید ایمیل خود را تایید کنید. از صفحه‌ی «تنظیمات حساب» ایمیل تایید را دوباره ارسال کنید.');
    } else if (data && data.message) {
      dialogAlert(data.message);
    } else {
      dialogAlert('ارسال با خطا مواجه شد. دوباره امتحان کنید.');
    }
  }

  async function sendMessage(body, replyToMessageId) {
    const sendPath = cfg.sendUrl || 'api/send_message.php';
    const payload = { body, csrf_token: csrfEl.value };
    if ((cfg.mode === 'private' || cfg.mode === 'group') && cfg.conversationId) {
      payload.conversation_id = cfg.conversationId;
    }
    if (replyToMessageId) {
      payload.reply_to_id = replyToMessageId;
    }

    const res = await fetch(`${cfg.baseUrl}${sendPath}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (res.ok && data.message) {
      renderMessage(data.message);
      scrollToBottom();
    } else {
      handleErrorResponse(data);
    }
  }

  formEl.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (isSending) return;

    const text = inputEl.value.trim();
    if (!text) return;

    const wasEditingId = editingMessageId;
    const wasReplyToId = replyToId;

    isSending = true;
    inputEl.value = '';
    autoResize();
    clearComposeContext();

    try {
      if (wasEditingId !== null) {
        await editMessageRequest(wasEditingId, text);
      } else {
        await sendMessage(text, wasReplyToId);
      }
    } finally {
      isSending = false;
      inputEl.focus();
    }
  });

  inputEl.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      formEl.requestSubmit();
    }
  });

  function autoResize() {
    inputEl.style.height = 'auto';
    inputEl.style.height = Math.min(inputEl.scrollHeight, 120) + 'px';
  }
  inputEl.addEventListener('input', autoResize);

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
      fetchNewMessages(); // با mark_seen=1 چون الان تب دیده می‌شود - پیام‌های جامانده را seen می‌کند
      if (cfg.mode === 'private') sendSeenPing();
    }
    schedulePoll();
  });

  // ---------------------------------------------------------
  // نشانگر «در حال تایپ...» - ارسال ping
  // ---------------------------------------------------------
  let lastTypingPingAt = 0;
  function maybePingTyping() {
    if (!inputEl.value.trim()) return;
    const now = Date.now();
    if (now - lastTypingPingAt < 2000) return;
    lastTypingPingAt = now;

    // اگر WebSocket وصله، تایپینگ مستقیم و بدون تماس با PHP ارسال می‌شود (سریع‌تر و سبک‌تر)
    if (realtime.isConnected()) {
      realtime.send({ type: 'typing', room: realtime.room() });
      return;
    }

    if (!cfg.typingUrl) return;
    const payload = { scope: currentScope(), csrf_token: csrfEl.value };
    if ((cfg.mode === 'private' || cfg.mode === 'group') && cfg.conversationId) {
      payload.conversation_id = cfg.conversationId;
    }

    fetch(`${cfg.baseUrl}${cfg.typingUrl}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    }).catch(() => {});
  }
  inputEl.addEventListener('input', maybePingTyping);

  // ---------------------------------------------------------
  // بلاک/رفع بلاک گفتگوی خصوصی
  // ---------------------------------------------------------
  let iBlocked = !!cfg.iBlocked;
  let blockedByPartner = !!cfg.blockedByPartner;

  // اگر طرف مقابل مکالمه را کامل حذف کند، هم از طریق broadcast لحظه‌ای
  // WebSocket و هم از طریق فیلد conversation_wiped در هر پاسخ پولینگ به
  // این تابع می‌رسیم (تا وقتی Real-Time فعال نیست هم بدون رفرش کار کند).
  let conversationWipedHandled = false;
  function showConversationWiped(text) {
    if (conversationWipedHandled) return;
    conversationWipedHandled = true;
    messagesEl.innerHTML = '<p class="empty-state">' + (text || 'این مکالمه حذف شد.') + '</p>';
  }

  function updateBlockUI() {
    if (btnBlock) {
      const icons = window.ChativaIcons || {};
      btnBlock.innerHTML = iBlocked ? (icons.unlock || '') : (icons.ban || '');
      btnBlock.title = iBlocked ? 'رفع بلاک' : 'بلاک کاربر';
    }
    const disable = iBlocked || blockedByPartner;
    [inputEl, btnAttach, btnDocument, btnEmoji, btnVoice].forEach((el) => {
      if (el) el.disabled = disable;
    });
    const sendBtn = formEl.querySelector('.btn-send');
    if (sendBtn) sendBtn.disabled = disable;

    if (chatBannerEl) {
      if (iBlocked) {
        chatBannerEl.textContent = 'این کاربر را بلاک کرده‌اید. برای ارسال پیام، ابتدا رفع بلاک کنید.';
        chatBannerEl.classList.remove('hidden');
      } else if (blockedByPartner) {
        chatBannerEl.textContent = 'امکان ارسال پیام به این کاربر وجود ندارد.';
        chatBannerEl.classList.remove('hidden');
      } else {
        chatBannerEl.classList.add('hidden');
      }
    }
  }

  if (btnBlock && cfg.toggleBlockUrl) {
    btnBlock.addEventListener('click', async () => {
      btnBlock.disabled = true;
      try {
        const res = await fetch(`${cfg.baseUrl}${cfg.toggleBlockUrl}`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ conversation_id: cfg.conversationId, csrf_token: csrfEl.value }),
        });
        const data = await res.json();
        if (res.ok && typeof data.blocked === 'boolean') {
          iBlocked = data.blocked;
          updateBlockUI();
        } else {
          handleErrorResponse(data);
        }
      } catch (err) {
        dialogAlert('خطا در تغییر وضعیت بلاک.');
      } finally {
        btnBlock.disabled = false;
      }
    });
  }

  updateBlockUI();

  // ---------------------------------------------------------
  // Real-Time (WebSocket) - مرحله ۵
  // اگر مدیر سایت این قابلیت را فعال نکرده باشد یا سرور Node در دسترس
  // نباشد، این بخش کاملاً بی‌اثر می‌ماند و همه‌چیز از طریق همان polling
  // بالا (سریع) کار می‌کند. اگر فعال و در دسترس باشد، polling به یک
  // safety-net کند تبدیل می‌شود و بروزرسانی‌ها آنی می‌شوند.
  // ---------------------------------------------------------
  const wsRoom = cfg.mode === 'private' && cfg.conversationId
    ? `conversation:${cfg.conversationId}`
    : cfg.mode === 'group' && cfg.conversationId
      ? `group:${cfg.conversationId}`
      : 'public';

  let ws = null;
  let wsConnected = false;
  let reconnectAttempts = 0;
  let reconnectTimer = null;
  let typingHideTimer = null;
  let callSignalHandler = null;
  let destroyed = false; // بعد از ناوبری PJAX به صفحه‌ی دیگر، true می‌شود

  function sendSeenPing() {
    // فقط وقتی تب واقعاً قابل‌مشاهده است «دیده‌شده» ارسال شود - یک تب
    // خصوصی که در پس‌زمینه باز مانده (مثلاً کاربر به تب دیگری رفته) نباید
    // پیام‌های تازه‌رسیده را خودکار seen کند.
    if (document.hidden) return;
    if (ws && wsConnected) {
      try {
        ws.send(JSON.stringify({ type: 'seen', room: wsRoom }));
      } catch (e) { /* بی‌اهمیت */ }
    }
    if (cfg.markSeenUrl && cfg.conversationId) {
      fetch(`${cfg.baseUrl}${cfg.markSeenUrl}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ conversation_id: cfg.conversationId, csrf_token: csrfEl.value }),
      }).catch(() => {});
    }
  }

  function handleRealtimeMessage(msg) {
    if (!msg || typeof msg.type !== 'string') return;

    if (msg.type === 'auth_error') {
      if (ws) ws.close();
      return;
    }

    if (msg.type === 'auth_ok') {
      ws.send(JSON.stringify({ type: 'join', room: wsRoom }));
      return;
    }

    if (msg.type === 'joined') {
      wsConnected = true;
      reconnectAttempts = 0;
      schedulePoll(); // بازه‌ی polling را به حالت safety-net کند تغییر بده
      fetchNewMessages(); // جبران فاصله‌ی بین رندر اولیه‌ی صفحه و وصل‌شدن WS
      if (cfg.mode === 'private') {
        sendSeenPing();
      }
      return;
    }

    const payload = msg.payload || {};

    if (msg.type === 'message') {
      const isMine = payload.user_id === cfg.myUserId;
      renderMessage(Object.assign({}, payload, { is_mine: isMine }));
      if (isNearBottom()) scrollToBottom();
      if (!isMine && cfg.mode === 'private') {
        sendSeenPing();
      }
      return;
    }

    if (msg.type === 'message_deleted') {
      removeMessageFromDom(payload.id);
      return;
    }

    if (msg.type === 'message_edited') {
      applyMessageEdit(payload.id, payload.body, payload.edited_time_label);
      return;
    }

    if (msg.type === 'message_pinned') {
      renderPinnedBar(payload);
      return;
    }

    if (msg.type === 'message_unpinned') {
      renderPinnedBar(null);
      return;
    }

    if (msg.type === 'reaction_updated') {
      renderReactionsForMessage(payload.message_id, payload.reactions);
      return;
    }

    if (msg.type === 'messages_cleared') {
      messagesEl.innerHTML = '<p class="empty-state">هنوز پیامی ارسال نشده. اولین نفری باش که سلام می‌کنه 👋</p>';
      return;
    }

    if (msg.type === 'conversation_cleared') {
      showConversationWiped();
      return;
    }

    if (msg.type === 'typing') {
      updateTypingIndicator([payload.username]);
      clearTimeout(typingHideTimer);
      typingHideTimer = setTimeout(() => updateTypingIndicator([]), 3000);
      return;
    }

    if (msg.type === 'seen') {
      updateSeenTicks(Number.MAX_SAFE_INTEGER);
      return;
    }

    if (msg.type === 'block') {
      if (payload.by_user_id !== cfg.myUserId) {
        blockedByPartner = !!payload.blocked;
        updateBlockUI();
      }
      return;
    }

    if (msg.type.indexOf('call:') === 0 && callSignalHandler) {
      callSignalHandler(msg.type, payload);
      return;
    }
  }

  function scheduleReconnect() {
    if (destroyed) return;
    reconnectAttempts++;
    const delay = Math.min(20000, 2000 * Math.pow(2, reconnectAttempts - 1));
    clearTimeout(reconnectTimer);
    reconnectTimer = setTimeout(connectRealtime, delay);
  }

  async function connectRealtime() {
    if (destroyed) return;
    if (!cfg.wsTokenUrl) return;

    let tokenData;
    try {
      const res = await fetch(`${cfg.baseUrl}${cfg.wsTokenUrl}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      tokenData = await res.json();
    } catch (e) {
      scheduleReconnect();
      return;
    }

    if (!tokenData || !tokenData.enabled || !tokenData.token || !tokenData.wsUrl) {
      return; // Real-Time غیرفعال است؛ polling سریع معمولی ادامه پیدا می‌کند
    }

    try {
      ws = new WebSocket(tokenData.wsUrl);
    } catch (e) {
      scheduleReconnect();
      return;
    }

    ws.addEventListener('open', () => {
      ws.send(JSON.stringify({ type: 'auth', token: tokenData.token }));
    });

    ws.addEventListener('message', (event) => {
      let msg;
      try {
        msg = JSON.parse(event.data);
      } catch (e) {
        return;
      }
      handleRealtimeMessage(msg);
    });

    ws.addEventListener('close', () => {
      const wasConnected = wsConnected;
      wsConnected = false;
      ws = null;
      if (destroyed) return;
      if (wasConnected) {
        schedulePoll(); // برگشت موقت به polling سریع تا دوباره وصل شویم
      }
      scheduleReconnect();
    });
  }

  const realtime = {
    isConnected: () => wsConnected,
    room: () => wsRoom,
    send: (obj) => {
      if (ws && wsConnected) {
        try {
          ws.send(JSON.stringify(obj));
        } catch (e) { /* بی‌اهمیت */ }
      }
    },
    onCallSignal: (handler) => {
      callSignalHandler = handler;
    },
  };
  window.ChativaRealtime = realtime;

  connectRealtime();

  // هنگام ناوبری PJAX به یک صفحه‌ی دیگر، این نمونه از main.js باید کامل
  // خاموش شود تا نمونه‌ی جدیدی که برای صفحه‌ی بعدی اجرا می‌شود با آن
  // تداخل نکند (تایمرهای تکراری، اتصال WebSocket تکراری و ...).
  window.ChativaTeardowns = window.ChativaTeardowns || [];
  window.ChativaTeardowns.push(function () {
    destroyed = true;
    clearTimeout(pollTimer);
    clearTimeout(reconnectTimer);
    clearTimeout(typingHideTimer);
    if (ws) {
      try { ws.close(); } catch (e) { /* بی‌اهمیت */ }
      ws = null;
    }
    wsConnected = false;
    if (window.ChativaRealtime === realtime) {
      window.ChativaRealtime = null;
    }
  });

  // ---------------------------------------------------------
  // آپلود پیوست (عکس / ویدیو / ویس)
  // ---------------------------------------------------------
  async function uploadAttachment(fileOrBlob, kind, filename, caption, duration) {
    if (!cfg.attachmentUrl || isUploading) return;
    isUploading = true;
    if (btnAttach) btnAttach.disabled = true;
    if (btnDocument) btnDocument.disabled = true;
    if (btnVoice) btnVoice.disabled = true;

    const usedReplyToId = replyToId;
    clearComposeContext();

    try {
      const formData = new FormData();
      formData.append('file', fileOrBlob, filename);
      formData.append('kind', kind);
      formData.append('caption', caption || '');
      formData.append('csrf_token', csrfEl.value);
      if (duration != null) formData.append('duration', String(duration));
      if (usedReplyToId) formData.append('reply_to_id', String(usedReplyToId));
      if ((cfg.mode === 'private' || cfg.mode === 'group') && cfg.conversationId) {
        formData.append('conversation_id', String(cfg.conversationId));
      }

      const res = await fetch(`${cfg.baseUrl}${cfg.attachmentUrl}`, {
        method: 'POST',
        body: formData,
      });
      const data = await res.json();
      if (res.ok && data.message) {
        renderMessage(data.message);
        scrollToBottom();
      } else {
        handleErrorResponse(data);
      }
    } catch (err) {
      dialogAlert('آپلود فایل با خطا مواجه شد.');
    } finally {
      isUploading = false;
      if (btnAttach) btnAttach.disabled = false;
      if (btnDocument) btnDocument.disabled = false;
      if (btnVoice) btnVoice.disabled = false;
    }
  }

  // ---------------------------------------------------------
  // پیش‌نمایش عکس/ویدیو + کپشن اختیاری قبل از ارسال - مرحله ۷
  // ---------------------------------------------------------
  function showAttachmentPreview(file, kind) {
    return new Promise((resolve) => {
      const objectUrl = URL.createObjectURL(file);

      const overlay = document.createElement('div');
      overlay.className = 'chativa-dialog-overlay';

      const box = document.createElement('div');
      box.className = 'chativa-dialog-box attachment-preview-box';
      box.innerHTML =
        `<h3 class="chativa-dialog-title">ارسال ${kind === 'video' ? 'ویدیو' : 'عکس'}</h3>` +
        '<div class="attachment-preview-media"></div>' +
        '<textarea class="attachment-preview-caption" maxlength="2000" placeholder="کپشن (اختیاری)..." rows="2"></textarea>' +
        '<div class="chativa-dialog-actions"></div>';

      const mediaEl = box.querySelector('.attachment-preview-media');
      if (kind === 'video') {
        const video = document.createElement('video');
        video.src = objectUrl;
        video.controls = true;
        mediaEl.appendChild(video);
      } else {
        const img = document.createElement('img');
        img.src = objectUrl;
        img.alt = '';
        mediaEl.appendChild(img);
      }

      const captionEl = box.querySelector('.attachment-preview-caption');
      const actionsEl = box.querySelector('.chativa-dialog-actions');

      const cancelBtn = document.createElement('button');
      cancelBtn.type = 'button';
      cancelBtn.className = 'btn-mini';
      cancelBtn.textContent = 'انصراف';

      const sendBtn = document.createElement('button');
      sendBtn.type = 'button';
      sendBtn.className = 'btn-mini btn-mini-primary';
      sendBtn.textContent = 'ارسال';

      actionsEl.appendChild(cancelBtn);
      actionsEl.appendChild(sendBtn);
      overlay.appendChild(box);
      document.body.appendChild(overlay);
      captionEl.focus();

      function cleanup() {
        URL.revokeObjectURL(objectUrl);
        overlay.remove();
      }

      cancelBtn.addEventListener('click', () => { cleanup(); resolve(null); });
      sendBtn.addEventListener('click', () => {
        const caption = captionEl.value.trim();
        cleanup();
        resolve({ caption });
      });
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) { cleanup(); resolve(null); }
      });
      captionEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          sendBtn.click();
        }
      });
    });
  }

  // ---------------------------------------------------------
  // پیش‌نمایش فایل/سند + کپشن اختیاری قبل از ارسال (مرحله ۱۰) - برخلاف
  // عکس/ویدیو، اینجا رسانه‌ای برای پیش‌نمایش نیست، فقط آیکون+نام+حجم فایل
  // ---------------------------------------------------------
  function showDocumentPreview(file) {
    return new Promise((resolve) => {
      const overlay = document.createElement('div');
      overlay.className = 'chativa-dialog-overlay';

      const box = document.createElement('div');
      box.className = 'chativa-dialog-box attachment-preview-box';
      const fileIcon = (window.ChativaIcons && window.ChativaIcons.file) || '';
      box.innerHTML =
        '<h3 class="chativa-dialog-title">ارسال فایل</h3>' +
        '<div class="attachment-document attachment-document-preview">' +
        `<span class="attachment-document-icon">${fileIcon}</span>` +
        '<span class="attachment-document-info">' +
        `<span class="attachment-document-name">${escapeHtml(file.name)}</span>` +
        `<span class="attachment-document-meta">${escapeHtml(formatFileSize(file.size))}</span>` +
        '</span>' +
        '</div>' +
        '<textarea class="attachment-preview-caption" maxlength="2000" placeholder="کپشن (اختیاری)..." rows="2"></textarea>' +
        '<div class="chativa-dialog-actions"></div>';

      const captionEl = box.querySelector('.attachment-preview-caption');
      const actionsEl = box.querySelector('.chativa-dialog-actions');

      const cancelBtn = document.createElement('button');
      cancelBtn.type = 'button';
      cancelBtn.className = 'btn-mini';
      cancelBtn.textContent = 'انصراف';

      const sendBtn = document.createElement('button');
      sendBtn.type = 'button';
      sendBtn.className = 'btn-mini btn-mini-primary';
      sendBtn.textContent = 'ارسال';

      actionsEl.appendChild(cancelBtn);
      actionsEl.appendChild(sendBtn);
      overlay.appendChild(box);
      document.body.appendChild(overlay);
      captionEl.focus();

      function cleanup() {
        overlay.remove();
      }

      cancelBtn.addEventListener('click', () => { cleanup(); resolve(null); });
      sendBtn.addEventListener('click', () => {
        const caption = captionEl.value.trim();
        cleanup();
        resolve({ caption });
      });
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) { cleanup(); resolve(null); }
      });
      captionEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          sendBtn.click();
        }
      });
    });
  }

  if (btnDocument && documentInputEl) {
    btnDocument.addEventListener('click', () => documentInputEl.click());

    documentInputEl.addEventListener('change', async () => {
      const file = documentInputEl.files && documentInputEl.files[0];
      documentInputEl.value = '';
      if (!file) return;

      const result = await showDocumentPreview(file);
      if (result === null) return; // انصراف

      await uploadAttachment(file, 'document', file.name, result.caption, null);
    });
  }

  if (btnAttach && attachmentInputEl) {
    btnAttach.addEventListener('click', () => attachmentInputEl.click());

    attachmentInputEl.addEventListener('change', async () => {
      const file = attachmentInputEl.files && attachmentInputEl.files[0];
      attachmentInputEl.value = '';
      if (!file) return;

      const kind = file.type.startsWith('video/') ? 'video' : 'image';
      const result = await showAttachmentPreview(file, kind);
      if (result === null) return; // انصراف

      await uploadAttachment(file, kind, file.name, result.caption, null);
    });
  }

  // ---------------------------------------------------------
  // پنل ایموجی سبک
  // ---------------------------------------------------------
  // هر ایموجی با کد‌پوینت یونیکد آن (برای مسیر تصویر SVG محلی در assets/emoji/)
  // مشخص شده؛ روی کلیک، خودِ کاراکتر یونیکد واقعی (نه تصویر) در متن پیام
  // درج می‌شود - یعنی متن پیام همچنان یک ایموجی قابل‌کپی معمولی می‌ماند و
  // فقط نمایش پنل انتخاب از SVG استفاده می‌کند (شبیه تلگرام/دیسکورد).
  const EMOJI_LIST = [
    ['😀', '1f600'], ['😁', '1f601'], ['😂', '1f602'], ['🤣', '1f923'], ['😊', '1f60a'],
    ['😍', '1f60d'], ['😘', '1f618'], ['😜', '1f61c'], ['🤔', '1f914'], ['🙄', '1f644'],
    ['😴', '1f634'], ['😢', '1f622'], ['😭', '1f62d'], ['😡', '1f621'], ['😱', '1f631'],
    ['🥳', '1f973'], ['😎', '1f60e'], ['🤗', '1f917'], ['🙏', '1f64f'], ['👍', '1f44d'],
    ['👎', '1f44e'], ['👏', '1f44f'], ['🙌', '1f64c'], ['💪', '1f4aa'], ['❤️', '2764'],
    ['🧡', '1f9e1'], ['💛', '1f49b'], ['💚', '1f49a'], ['💙', '1f499'], ['💜', '1f49c'],
    ['🔥', '1f525'], ['✨', '2728'], ['🎉', '1f389'], ['🎂', '1f382'], ['🌹', '1f339'],
    ['☕', '2615'], ['🍕', '1f355'], ['⚽', '26bd'], ['🎮', '1f3ae'], ['📷', '1f4f7'],
    ['✅', '2705'], ['❌', '274c'], ['❗', '2757'], ['❓', '2753'], ['💯', '1f4af'],
    ['👌', '1f44c'], ['🤝', '1f91d'], ['👋', '1f44b'], ['😅', '1f605'], ['🥰', '1f970'],
  ];

  if (btnEmoji && emojiPanelEl) {
    if (emojiPanelEl.children.length === 0) {
      EMOJI_LIST.forEach(([emoji, code]) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'emoji-item';
        btn.title = emoji;
        const img = document.createElement('img');
        img.src = `${cfg.baseUrl}assets/emoji/${code}.svg`;
        img.alt = emoji;
        img.loading = 'lazy';
        img.draggable = false;
        btn.appendChild(img);
        btn.addEventListener('click', () => {
          insertAtCursor(inputEl, emoji);
          autoResize();
        });
        emojiPanelEl.appendChild(btn);
      });
    }

    // موقعیت پنل (fixed) با توجه به محل واقعی دکمه‌ی ایموجی محاسبه می‌شود؛
    // چون چیدمان RTL است، لبه‌ی راست پنل با لبه‌ی راست دکمه هم‌تراز می‌شود و
    // پنل بالای دکمه باز می‌شود (اگر بالای صفحه جا نبود، پایین دکمه باز
    // می‌شود) - همان الگوی کلمپ‌کردن به داخل صفحه که در dialog.js برای
    // openMenu/openReactionPicker استفاده شده.
    function positionEmojiPanel() {
      const btnRect = btnEmoji.getBoundingClientRect();
      const panelRect = emojiPanelEl.getBoundingClientRect();
      const vw = window.innerWidth;
      const vh = window.innerHeight;
      let left = btnRect.right - panelRect.width;
      let top = btnRect.top - panelRect.height - 8;
      if (left + panelRect.width > vw - 8) left = vw - panelRect.width - 8;
      if (left < 8) left = 8;
      if (top < 8) top = Math.min(btnRect.bottom + 8, vh - panelRect.height - 8);
      emojiPanelEl.style.left = left + 'px';
      emojiPanelEl.style.top = top + 'px';
    }

    btnEmoji.addEventListener('click', () => {
      const willOpen = emojiPanelEl.classList.contains('hidden');
      emojiPanelEl.classList.toggle('hidden');
      if (willOpen) positionEmojiPanel();
    });

    // این هندلر روی document ثبت می‌شود (نه روی emojiPanelEl)، پس باید در
    // teardown پاک شود؛ وگرنه بعد از چند بار ناوبری PJAX بین صفحات، چندین
    // نمونه‌ی قدیمی (با ارجاع به عنصرهای حذف‌شده‌ی قبلی) روی document
    // انباشته می‌شوند.
    function closeEmojiOnOutsideClick(e) {
      // نکته‌ی مهم: چک `e.target !== btnEmoji` قبلاً باگ داشت - چون دکمه‌ی
      // ایموجی یک آیکون SVG تودرتو دارد، کلیک روی خودِ آیکون e.target را
      // برابر با <svg>/<circle> داخل دکمه می‌کند نه خودِ دکمه، پس این شرط
      // اشتباهاً true می‌شد و پنل را بلافاصله بعد از باز شدن دوباره می‌بست
      // (به همین خاطر باز شدن پنل «هرازگاهی» کار می‌کرد - بسته به این‌که
      // کلیک دقیقاً روی شکل SVG بیفتد یا فضای خالی اطراف آن در دکمه).
      // با btnEmoji.contains(e.target) این مشکل کاملاً رفع می‌شود.
      if (!emojiPanelEl.classList.contains('hidden') && !emojiPanelEl.contains(e.target) && !btnEmoji.contains(e.target)) {
        emojiPanelEl.classList.add('hidden');
      }
    }
    document.addEventListener('click', closeEmojiOnOutsideClick);
    window.ChativaTeardowns = window.ChativaTeardowns || [];
    window.ChativaTeardowns.push(function () {
      document.removeEventListener('click', closeEmojiOnOutsideClick);
    });
  }

  function insertAtCursor(textarea, text) {
    const start = textarea.selectionStart ?? textarea.value.length;
    const end = textarea.selectionEnd ?? textarea.value.length;
    textarea.value = textarea.value.slice(0, start) + text + textarea.value.slice(end);
    const newPos = start + text.length;
    textarea.focus();
    textarea.setSelectionRange(newPos, newPos);
  }

  // ---------------------------------------------------------
  // ضبط پیام صوتی
  // ---------------------------------------------------------
  let mediaRecorder = null;
  let recordedChunks = [];
  let recordingStream = null;
  let recordingStartedAt = 0;
  let recordingTimerHandle = null;

  function formatTimer(seconds) {
    const m = String(Math.floor(seconds / 60)).padStart(2, '0');
    const s = String(seconds % 60).padStart(2, '0');
    return `${m}:${s}`;
  }

  function showVoiceRecorder() {
    if (voiceRecorderEl) voiceRecorderEl.classList.remove('hidden');
    if (formEl) formEl.classList.add('hidden');
  }

  function hideVoiceRecorder() {
    if (voiceRecorderEl) voiceRecorderEl.classList.add('hidden');
    if (formEl) formEl.classList.remove('hidden');
    if (voiceTimerEl) voiceTimerEl.textContent = '00:00';
  }

  function stopRecordingStream() {
    if (recordingStream) {
      recordingStream.getTracks().forEach((track) => track.stop());
      recordingStream = null;
    }
    if (recordingTimerHandle) {
      clearInterval(recordingTimerHandle);
      recordingTimerHandle = null;
    }
  }

  async function startVoiceRecording() {
    if (!navigator.mediaDevices || !window.MediaRecorder) {
      dialogAlert('مرورگر شما از ضبط صدا پشتیبانی نمی‌کند.');
      return;
    }

    try {
      recordingStream = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch (err) {
      dialogAlert('دسترسی به میکروفون رد شد یا امکان‌پذیر نیست.');
      return;
    }

    recordedChunks = [];
    mediaRecorder = new MediaRecorder(recordingStream);
    mediaRecorder.addEventListener('dataavailable', (e) => {
      if (e.data && e.data.size > 0) recordedChunks.push(e.data);
    });

    mediaRecorder.start();
    recordingStartedAt = Date.now();
    showVoiceRecorder();

    recordingTimerHandle = setInterval(() => {
      const elapsed = Math.floor((Date.now() - recordingStartedAt) / 1000);
      if (voiceTimerEl) voiceTimerEl.textContent = formatTimer(elapsed);
      if (elapsed >= 300) { // حداکثر ۵ دقیقه
        finishVoiceRecording(true);
      }
    }, 500);
  }

  function finishVoiceRecording(shouldSend) {
    if (!mediaRecorder || mediaRecorder.state === 'inactive') {
      hideVoiceRecorder();
      stopRecordingStream();
      return;
    }

    const duration = Math.round((Date.now() - recordingStartedAt) / 1000);

    mediaRecorder.addEventListener('stop', async () => {
      stopRecordingStream();
      hideVoiceRecorder();

      if (shouldSend && recordedChunks.length > 0) {
        const mimeType = mediaRecorder.mimeType || 'audio/webm';
        const blob = new Blob(recordedChunks, { type: mimeType });
        const ext = mimeType.includes('ogg') ? 'ogg' : 'webm';
        await uploadAttachment(blob, 'voice', `voice.${ext}`, '', duration);
      }
      recordedChunks = [];
    }, { once: true });

    mediaRecorder.stop();
  }

  if (btnVoice) {
    btnVoice.addEventListener('click', () => {
      startVoiceRecording();
    });
  }
  if (btnVoiceCancel) {
    btnVoiceCancel.addEventListener('click', () => finishVoiceRecording(false));
  }
  if (btnVoiceSend) {
    btnVoiceSend.addEventListener('click', () => finishVoiceRecording(true));
  }

  // ---------------------------------------------------------
  // جستجو در پیام‌ها (مرحله ۱۰)
  // ---------------------------------------------------------
  function scrollToMessageInDom(messageId) {
    const targetEl = messagesEl.querySelector(`.msg[data-id="${messageId}"]`);
    if (!targetEl) return false;
    targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    targetEl.classList.add('msg-highlight');
    setTimeout(() => targetEl.classList.remove('msg-highlight'), 1500);
    return true;
  }

  function closeSearch() {
    if (!searchBarEl) return;
    searchBarEl.classList.add('hidden');
    if (searchInputEl) searchInputEl.value = '';
    if (searchResultsEl) {
      searchResultsEl.innerHTML = '';
      searchResultsEl.classList.add('hidden');
    }
  }

  function renderSearchResults(results) {
    if (!searchResultsEl) return;
    if (!results || results.length === 0) {
      searchResultsEl.innerHTML = '<p class="search-empty">نتیجه‌ای پیدا نشد.</p>';
      searchResultsEl.classList.remove('hidden');
      return;
    }
    searchResultsEl.innerHTML = results
      .map(
        (r) =>
          `<button type="button" class="search-result-item" data-message-id="${r.id}">` +
          `<span class="search-result-author">${escapeHtml(r.is_mine ? 'شما' : r.author)}</span>` +
          `<span class="search-result-snippet">${r.snippet_html}</span>` +
          `<span class="search-result-time">${escapeHtml(r.time_label)}</span>` +
          '</button>'
      )
      .join('');
    searchResultsEl.classList.remove('hidden');
  }

  let searchDebounceTimer = null;
  async function runSearch(query) {
    if (!cfg.searchUrl) return;
    if (!query || query.trim().length < 2) {
      if (searchResultsEl) {
        searchResultsEl.innerHTML = '';
        searchResultsEl.classList.add('hidden');
      }
      return;
    }
    const params = new URLSearchParams({ scope: cfg.mode === 'private' ? 'private' : 'public', q: query.trim() });
    if (cfg.mode === 'private' && cfg.conversationId) {
      params.set('conversation_id', String(cfg.conversationId));
    }
    try {
      const res = await fetch(`${cfg.baseUrl}${cfg.searchUrl}?${params.toString()}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (!res.ok) return;
      const data = await res.json();
      renderSearchResults(data.results || []);
    } catch (err) {
      // خطای شبکه؛ نتیجه‌ی جستجوی قبلی همچنان روی صفحه می‌ماند
    }
  }

  if (btnSearch && searchBarEl) {
    btnSearch.addEventListener('click', () => {
      const isHidden = searchBarEl.classList.contains('hidden');
      if (isHidden) {
        searchBarEl.classList.remove('hidden');
        if (searchInputEl) searchInputEl.focus();
      } else {
        closeSearch();
      }
    });
  }

  if (btnSearchClose) {
    btnSearchClose.addEventListener('click', closeSearch);
  }

  if (searchInputEl) {
    searchInputEl.addEventListener('input', () => {
      clearTimeout(searchDebounceTimer);
      searchDebounceTimer = setTimeout(() => runSearch(searchInputEl.value), 300);
    });
    searchInputEl.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeSearch();
    });
  }

  if (searchResultsEl) {
    searchResultsEl.addEventListener('click', (event) => {
      const item = event.target.closest('.search-result-item');
      if (!item) return;
      const messageId = parseInt(item.dataset.messageId, 10);
      if (!messageId) return;

      if (scrollToMessageInDom(messageId)) {
        closeSearch();
        return;
      }

      // پیام در DOM فعلی بارگذاری نشده (خارج از ۵۰ پیام آخر) - صفحه با
      // پارامتر around دوباره بارگذاری می‌شود تا آن پیام در بافتارش نمایش
      // داده شود (chat.php/messages.php این پارامتر را می‌فهمند)
      if (cfg.mode === 'private' && cfg.conversationId) {
        window.location.href = `${cfg.baseUrl}messages.php?id=${cfg.conversationId}&around=${messageId}`;
      } else {
        window.location.href = `${cfg.baseUrl}chat.php?around=${messageId}`;
      }
    });
  }

  // شروع
  if (cfg.aroundId && scrollToMessageInDom(cfg.aroundId)) {
    // پیام هدف با موفقیت اسکرول+هایلایت شد؛ لازم نیست به پایین هم برویم
  } else {
    scrollToBottom();
  }
  schedulePoll();
})();
