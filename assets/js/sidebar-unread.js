/**
 * سایدبار زنده (مرحله ۶ + مرحله ۸)
 * -------------------------------------------------
 * این اسکریپت یک‌بار و به‌صورت سراسری بارگذاری می‌شود و به‌طور دوره‌ای
 * api/unread_counts.php را صدا می‌زند و:
 *   - badge پیام‌خوانده‌نشده‌ی چت عمومی و هر گفتگوی خصوصی را به‌روز می‌کند
 *   - پیش‌نمایش آخرین پیام + وضعیت آنلاین/آفلاین + ترتیب هر آیتم مکالمه را
 *     زنده و بدون نیاز به رفرش صفحه به‌روز می‌کند (کل #private-conv-list با
 *     HTML تازه‌ای که سرور رندر کرده جایگزین می‌شود، پس مکالمه‌ی تازه/اولین‌
 *     پیامِ یک گفتگوی جدید هم بدون رفرش در سایدبار ظاهر می‌شود)
 * چون هر بار DOM را دوباره پیدا می‌کند (نه یک رفرنس ثابت)، بعد از هر ناوبری
 * PJAX هم بدون نیاز به راه‌اندازی مجدد به‌درستی کار می‌کند.
 *
 * منوی راست‌کلیک/لمس‌طولانی روی هر آیتم مکالمه (حذف/حذف‌کامل/بلاک) هم اینجا
 * با event delegation روی document ثبت می‌شود، چون #private-conv-list هر
 * چند ثانیه محتوایش عوض می‌شود و addEventListener مستقیم روی خود آیتم‌ها
 * بعد از هر رفرش از بین می‌رود.
 */
(function () {
  'use strict';

  const ACTIVE_INTERVAL = 6000; // ms - وقتی تب فعاله
  const HIDDEN_INTERVAL = 20000; // ms - وقتی تب پس‌زمینه‌ست

  let timer = null;
  let lastPrivateHtml = null;

  // ---------------------------------------------------------
  // انتخاب چندتایی مکالمه‌ها + حذف گروهی (بازبینی بعدی)
  // ---------------------------------------------------------
  let selectMode = false;
  let selectedIds = new Set();

  function csrfEl() {
    return document.getElementById('global-csrf-token');
  }

  function baseUrl() {
    const el = csrfEl();
    return (el && el.dataset.baseUrl) || '/';
  }

  function csrfToken() {
    const el = csrfEl();
    return el ? el.value : '';
  }

  function currentInterval() {
    return document.hidden ? HIDDEN_INTERVAL : ACTIVE_INTERVAL;
  }

  function activeConversationId() {
    return window.CHATIVA && window.CHATIVA.mode === 'private' ? Number(window.CHATIVA.conversationId) || 0 : 0;
  }

  function updateBadge(el, count) {
    if (!el) return;
    const n = Number(count) || 0;
    if (n > 0) {
      el.textContent = n > 99 ? '99+' : String(n);
      el.classList.remove('hidden');
    } else {
      el.textContent = '';
      el.classList.add('hidden');
    }
  }

  function renderPrivateList(items) {
    const container = document.getElementById('private-conv-list');
    if (!container) return;

    const html = items.length
      ? items.map((it) => it.html).join('')
      : '<p class="conv-empty" id="conv-empty-msg">هنوز گفتگوی خصوصی نداری. از باکس بالا یک کاربر پیدا کن.</p>';

    if (html === lastPrivateHtml) return; // چیزی تغییر نکرده؛ از دستکاری بی‌مورد DOM پرهیز کن
    lastPrivateHtml = html;
    container.innerHTML = html;
  }

  async function poll() {
    try {
      const res = await fetch(`${baseUrl()}api/unread_counts.php?active_id=${activeConversationId()}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (res.ok) {
        const data = await res.json();
        updateBadge(document.querySelector('[data-badge="public"]'), data.public || 0);
        if (Array.isArray(data.conversation_items)) {
          renderPrivateList(data.conversation_items);
          // اگر در حالت انتخاب چندتایی هستیم، رندر تازه‌ی سرور کلاس‌های
          // .selected را پاک می‌کند (چون innerHTML کل کانتینر عوض شده) -
          // باید بر اساس شناسه‌های انتخاب‌شده دوباره اعمال شود.
          if (selectMode) syncSelectedClasses();
        }
      }
    } catch (e) {
      // خطای شبکه؛ در دور بعدی polling دوباره امتحان می‌شود
    }
    schedule();
  }

  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(poll, currentInterval());
  }

  schedule();

  // بعد از هر ناوبری PJAX، سایدبار از سرور تازه رندر شده؛ کش را خالی کن تا
  // دور بعدی poll همیشه با DOM واقعی مقایسه‌ی درستی انجام دهد.
  document.addEventListener('pjax:loaded', () => {
    lastPrivateHtml = null;
    exitSelectMode();
  });

  // ---------------------------------------------------------
  // منوی راست‌کلیک/لمس‌طولانی روی آیتم مکالمه در سایدبار
  // ---------------------------------------------------------
  function dialogAlert(message) {
    return window.ChativaDialog ? window.ChativaDialog.alert(message) : Promise.resolve(alert(message));
  }

  async function deleteConversation(convId, wipe, convEl) {
    try {
      const res = await fetch(`${baseUrl()}api/delete_conversation.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ conversation_id: convId, wipe: !!wipe, csrf_token: csrfToken() }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) {
        dialogAlert('حذف مکالمه با خطا مواجه شد.');
        return;
      }
      if (convEl) convEl.remove();
      lastPrivateHtml = null; // مجبور کن دور بعدی poll حتماً دوباره رندر کند
      if (activeConversationId() === convId) {
        window.location.href = `${baseUrl()}chat.php`;
      }
    } catch (e) {
      dialogAlert('خطا در ارتباط با سرور.');
    }
  }

  async function confirmAndDeleteConversation(convId, wipe, convEl) {
    if (!window.ChativaDialog) return;
    const msg = wipe
      ? 'این مکالمه و تمام پیام‌ها و فایل‌های آن برای همیشه پاک می‌شود. این عمل غیرقابل‌بازگشت است. ادامه می‌دهید؟'
      : 'این مکالمه از سایدبار شما حذف می‌شود (طرف مقابل چیزی متوجه نمی‌شود). ادامه می‌دهید؟';
    const ok = await window.ChativaDialog.confirm(msg, {
      danger: wipe,
      confirmText: wipe ? 'حذف کامل' : 'حذف مکالمه',
    });
    if (ok) deleteConversation(convId, wipe, convEl);
  }

  async function toggleBlockConversation(convId, convEl) {
    try {
      const res = await fetch(`${baseUrl()}api/toggle_block_conversation.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ conversation_id: convId, csrf_token: csrfToken() }),
      });
      const data = await res.json();
      if (res.ok && typeof data.blocked === 'boolean' && convEl) {
        convEl.dataset.blocked = data.blocked ? '1' : '0';
      } else if (!res.ok) {
        dialogAlert('تغییر وضعیت بلاک با خطا مواجه شد.');
      }
    } catch (e) {
      dialogAlert('خطا در ارتباط با سرور.');
    }
  }

  async function toggleMuteConversation(convId, convEl) {
    try {
      const res = await fetch(`${baseUrl()}api/toggle_mute_conversation.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ conversation_id: convId, csrf_token: csrfToken() }),
      });
      const data = await res.json();
      if (res.ok && typeof data.muted === 'boolean' && convEl) {
        convEl.dataset.muted = data.muted ? '1' : '0';
        lastPrivateHtml = null; // بج/آیکون بی‌صدا در دور بعدی poll دوباره رندر شود
      } else if (!res.ok) {
        dialogAlert('تغییر وضعیت بی‌صدا با خطا مواجه شد.');
      }
    } catch (e) {
      dialogAlert('خطا در ارتباط با سرور.');
    }
  }

  async function togglePinConversation(convId, convEl) {
    try {
      const res = await fetch(`${baseUrl()}api/toggle_pin_conversation.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ conversation_id: convId, csrf_token: csrfToken() }),
      });
      const data = await res.json();
      if (res.ok && typeof data.pinned === 'boolean' && convEl) {
        convEl.dataset.pinned = data.pinned ? '1' : '0';
        lastPrivateHtml = null; // ترتیب/آیکون پین در دور بعدی poll دوباره رندر شود
      } else if (!res.ok) {
        dialogAlert('تغییر وضعیت پین با خطا مواجه شد.');
      }
    } catch (e) {
      dialogAlert('خطا در ارتباط با سرور.');
    }
  }

  function openConvContextMenu(convEl, pos, sheet) {
    if (!window.ChativaDialog) return;
    const convId = parseInt(convEl.dataset.conversationId, 10);
    if (!convId) return;
    const blocked = convEl.dataset.blocked === '1';
    const muted = convEl.dataset.muted === '1';
    const pinned = convEl.dataset.pinned === '1';

    const items = [
      { label: 'انتخاب چندتایی', icon: 'check-circle', onSelect: () => enterSelectMode(convId) },
      pinned
        ? { label: 'برداشتن پین', icon: 'pin', onSelect: () => togglePinConversation(convId, convEl) }
        : { label: 'پین کردن', icon: 'pin', onSelect: () => togglePinConversation(convId, convEl) },
      muted
        ? { label: 'فعال کردن صدا', icon: 'bell-off', onSelect: () => toggleMuteConversation(convId, convEl) }
        : { label: 'بی‌صدا کردن', icon: 'bell-off', onSelect: () => toggleMuteConversation(convId, convEl) },
      { label: 'حذف مکالمه', icon: 'trash', onSelect: () => confirmAndDeleteConversation(convId, false, convEl) },
      { label: 'حذف کامل مکالمه و فایل‌ها', icon: 'trash', danger: true, onSelect: () => confirmAndDeleteConversation(convId, true, convEl) },
      blocked
        ? { label: 'رفع بلاک کاربر', icon: 'unlock', onSelect: () => toggleBlockConversation(convId, convEl) }
        : { label: 'بلاک کاربر', icon: 'ban', danger: true, onSelect: () => toggleBlockConversation(convId, convEl) },
    ];

    window.ChativaDialog.openMenu(items, pos, sheet);
  }

  document.addEventListener('contextmenu', (event) => {
    const convEl = event.target.closest('.conv-item[data-conversation-id]');
    if (!convEl) return;
    event.preventDefault();
    openConvContextMenu(convEl, { x: event.clientX, y: event.clientY }, false);
  });

  let longPressTimer = null;
  let longPressStartPos = null;

  function cancelLongPress() {
    if (longPressTimer) {
      clearTimeout(longPressTimer);
      longPressTimer = null;
    }
  }

  document.addEventListener(
    'touchstart',
    (event) => {
      const convEl = event.target.closest('.conv-item[data-conversation-id]');
      if (!convEl) return;
      const touch = event.touches[0];
      longPressStartPos = { x: touch.clientX, y: touch.clientY };
      longPressTimer = setTimeout(() => {
        longPressTimer = null;
        if (navigator.vibrate) {
          try { navigator.vibrate(15); } catch (e) { /* بی‌اهمیت */ }
        }
        openConvContextMenu(convEl, null, true);
      }, 500);
    },
    { passive: true }
  );

  document.addEventListener(
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

  document.addEventListener('touchend', cancelLongPress);
  document.addEventListener('touchcancel', cancelLongPress);

  // ---------------------------------------------------------
  // انتخاب چندتایی مکالمه‌ها + حذف گروهی
  // ---------------------------------------------------------
  function selectBarEl() {
    return document.getElementById('conv-select-bar');
  }

  function updateSelectBarCount() {
    const bar = selectBarEl();
    if (!bar) return;
    const countEl = bar.querySelector('.conv-select-bar-count');
    if (countEl) {
      countEl.textContent = selectedIds.size > 0 ? selectedIds.size + ' مکالمه انتخاب شده' : 'مکالمه‌ای را انتخاب کنید';
    }
    const deleteBtn = bar.querySelector('.conv-select-bar-delete');
    if (deleteBtn) deleteBtn.disabled = selectedIds.size === 0;
  }

  function renderSelectBar() {
    const list = document.querySelector('.conversation-list');
    if (!list || selectBarEl()) return;
    const bar = document.createElement('div');
    bar.className = 'conv-select-bar';
    bar.id = 'conv-select-bar';
    bar.innerHTML =
      '<button type="button" class="btn-mini conv-select-bar-cancel">لغو</button>' +
      '<span class="conv-select-bar-count">مکالمه‌ای را انتخاب کنید</span>' +
      '<button type="button" class="btn-mini btn-mini-danger conv-select-bar-delete" disabled>حذف</button>';
    list.parentNode.insertBefore(bar, list);
    bar.querySelector('.conv-select-bar-cancel').addEventListener('click', exitSelectMode);
    bar.querySelector('.conv-select-bar-delete').addEventListener('click', confirmAndBulkDelete);
  }

  function removeSelectBar() {
    const bar = selectBarEl();
    if (bar) bar.remove();
  }

  function syncSelectedClasses() {
    document.querySelectorAll('.conv-item[data-conversation-id]').forEach((el) => {
      const id = parseInt(el.dataset.conversationId, 10);
      el.classList.toggle('selected', selectedIds.has(id));
    });
    updateSelectBarCount();
  }

  function enterSelectMode(initialConvId) {
    if (selectMode) {
      if (initialConvId) {
        selectedIds.add(initialConvId);
        syncSelectedClasses();
      }
      return;
    }
    selectMode = true;
    selectedIds = new Set();
    if (initialConvId) selectedIds.add(initialConvId);
    document.body.classList.add('conv-select-mode');
    renderSelectBar();
    syncSelectedClasses();
  }

  function exitSelectMode() {
    if (!selectMode) return;
    selectMode = false;
    selectedIds = new Set();
    document.body.classList.remove('conv-select-mode');
    removeSelectBar();
    document.querySelectorAll('.conv-item.selected').forEach((el) => el.classList.remove('selected'));
  }

  async function bulkDeleteSelected() {
    const ids = Array.from(selectedIds);
    if (ids.length === 0) return;
    try {
      const res = await fetch(`${baseUrl()}api/bulk_delete_conversations.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ conversation_ids: ids, csrf_token: csrfToken() }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) {
        dialogAlert('حذف مکالمه‌ها با خطا مواجه شد.');
        return;
      }
      const deletedIds = (Array.isArray(data.deleted_ids) ? data.deleted_ids : ids).map(Number);
      const wasActiveDeleted = deletedIds.includes(activeConversationId());
      deletedIds.forEach((id) => {
        const el = document.querySelector(`.conv-item[data-conversation-id="${id}"]`);
        if (el) el.remove();
      });
      lastPrivateHtml = null; // مجبور کن دور بعدی poll حتماً دوباره رندر کند
      exitSelectMode();
      if (wasActiveDeleted) {
        window.location.href = `${baseUrl()}chat.php`;
      }
    } catch (e) {
      dialogAlert('خطا در ارتباط با سرور.');
    }
  }

  async function confirmAndBulkDelete() {
    if (!window.ChativaDialog || selectedIds.size === 0) return;
    const ok = await window.ChativaDialog.confirm(
      selectedIds.size + ' مکالمه از سایدبار شما حذف می‌شود (طرف مقابل چیزی متوجه نمی‌شود). ادامه می‌دهید؟',
      { danger: true, confirmText: 'حذف' }
    );
    if (ok) bulkDeleteSelected();
  }

  // دکمه‌ی «⋮» هدر سایدبار: به‌جای ردیف چند آیکون جدا (که قبلاً بود)، همه‌ی
  // اکشن‌های سطح‌بالا (تم/انتخاب‌چندتایی/تنظیمات/ادمین/خروج) در یک منوی
  // باز شونده‌ی واحد جمع شده‌اند - از همان کامپوننت ChativaDialog.openMenu
  // که برای منوی راست‌کلیک پیام/مکالمه هم استفاده می‌شود، پس ظاهرش با بقیه‌ی
  // سایت یکدست است.
  const THEME_MODE_NEXT_LABEL = { light: 'تغییر به حالت تاریک', dark: 'تغییر به حالت خودکار (سیستم)', system: 'تغییر به حالت روشن' };

  document.addEventListener('click', (event) => {
    const btn = event.target.closest('#btn-sidebar-menu');
    if (!btn || !window.ChativaDialog) return;

    const isAdmin = btn.dataset.isAdmin === '1';
    const mode = window.ChativaTheme ? window.ChativaTheme.mode() : 'light';
    const themeItemLabel = THEME_MODE_NEXT_LABEL[mode] || 'تغییر تم';

    const items = [
      { label: themeItemLabel, icon: 'moon', onSelect: () => { if (window.ChativaTheme) window.ChativaTheme.toggle(); } },
      { label: 'انتخاب چندتایی مکالمه‌ها', icon: 'check-circle', onSelect: () => enterSelectMode() },
      {
        label: 'تنظیمات حساب من',
        icon: 'user',
        onSelect: () => {
          if (window.pjaxNavigate) window.pjaxNavigate(`${baseUrl()}settings.php`);
          else window.location.href = `${baseUrl()}settings.php`;
        },
      },
    ];
    if (isAdmin) {
      items.push({
        label: 'پنل ادمین',
        icon: 'settings',
        onSelect: () => {
          if (window.pjaxNavigate) window.pjaxNavigate(`${baseUrl()}admin/index.php`);
          else window.location.href = `${baseUrl()}admin/index.php`;
        },
      });
    }
    items.push({ label: 'خروج', icon: 'logout', onSelect: () => { window.location.href = `${baseUrl()}logout.php`; } });

    const rect = btn.getBoundingClientRect();
    const sheet = window.matchMedia && window.matchMedia('(max-width: 680px)').matches;
    window.ChativaDialog.openMenu(items, { x: rect.left, y: rect.bottom + 6 }, sheet);
  });

  // وقتی در حالت انتخابیم، کلیک روی هر آیتم مکالمه باید فقط تیک آن را عوض
  // کند، نه این‌که وارد آن گفتگو شود. این هندلر در فاز capture ثبت می‌شود
  // تا قبل از هندلر ناوبری PJAX (که در فاز bubble روی document است) اجرا
  // شود و با preventDefault/stopPropagation جلوی باز شدن گفتگو را بگیرد -
  // دقیقاً همان الگوی intercept سراسری که dialog.js برای data-confirm دارد.
  document.addEventListener(
    'click',
    (event) => {
      if (!selectMode) return;
      const convEl = event.target.closest('.conv-item[data-conversation-id]');
      if (!convEl) return;
      event.preventDefault();
      event.stopPropagation();
      const id = parseInt(convEl.dataset.conversationId, 10);
      if (selectedIds.has(id)) {
        selectedIds.delete(id);
      } else {
        selectedIds.add(id);
      }
      convEl.classList.toggle('selected', selectedIds.has(id));
      updateSelectBarCount();
    },
    true
  );

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && selectMode) exitSelectMode();
  });
})();
