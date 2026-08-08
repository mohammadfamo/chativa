/**
 * مودال پروفایل کاربر (مرحله ۶)
 * -------------------------------------------------
 * این اسکریپت یک‌بار و به‌صورت سراسری بارگذاری می‌شود و روی هر عنصری با
 * کلاس js-avatar (خروجی تابع avatar_html() در PHP، یا اِلمان‌های مشابه که
 * با جاوااسکریپت رندر شده‌اند مثل پیام‌های آنی) کلیک را می‌گیرد و
 * مودال پروفایل همان کاربر را با گزینه‌ی «شروع گفتگو» و «بلاک/رفع بلاک»
 * باز می‌کند - در همه‌ی صفحات سایت (چت عمومی، چت خصوصی، پنل ادمین).
 *
 * از فاز capture برای گوش‌دادن به کلیک استفاده می‌شود تا اگر آواتار داخل
 * یک لینک ناوبری (مثلاً آیتم گفتگو در سایدبار) باشد، به‌جای رفتن به آن
 * لینک، مودال پروفایل باز شود - این کار مستقل از ترتیب بارگذاری اسکریپت‌ها
 * (نسبت به pjax.js) درست کار می‌کند چون فاز capture همیشه زودتر اجرا می‌شود.
 */
(function () {
  'use strict';

  const cfg = window.CHATIVA || {};
  let modalEl = null;
  let currentUserId = null;
  let requestToken = 0;

  // اگر dialog.js (که همیشه قبل از این اسکریپت لود می‌شود) در دسترس نبود،
  // به alert() بومی مرورگر برمی‌گردیم تا هیچ‌وقت خطا کامل بی‌صدا نشود.
  function dialogAlert(message) {
    if (window.ChativaDialog && window.ChativaDialog.alert) {
      return window.ChativaDialog.alert(message);
    }
    alert(message);
  }

  function baseUrl() {
    if (cfg.baseUrl) return cfg.baseUrl;
    const globalEl = document.getElementById('global-csrf-token');
    return (globalEl && globalEl.dataset.baseUrl) || '/';
  }

  function getCsrfToken() {
    const globalEl = document.getElementById('global-csrf-token');
    if (globalEl) return globalEl.value;
    const chatEl = document.getElementById('csrf_token');
    return chatEl ? chatEl.value : '';
  }

  function ensureModal() {
    if (modalEl) return modalEl;

    modalEl = document.createElement('div');
    modalEl.id = 'chativa-profile-modal';
    modalEl.className = 'profile-modal-overlay hidden';
    modalEl.innerHTML =
      '<div class="profile-modal" role="dialog" aria-modal="true">' +
      '  <button type="button" class="profile-modal-close" aria-label="بستن">' +
      '    <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><line x1="5" y1="5" x2="19" y2="19"/><line x1="19" y1="5" x2="5" y2="19"/></svg>' +
      '  </button>' +
      '  <div class="profile-modal-body">' +
      '    <div class="profile-modal-avatar"></div>' +
      '    <h3 class="profile-modal-name"></h3>' +
      '    <p class="profile-modal-username" dir="ltr"></p>' +
      '    <p class="profile-modal-bio hidden"></p>' +
      '    <p class="profile-modal-meta"></p>' +
      '  </div>' +
      '  <div class="profile-modal-actions"></div>' +
      '</div>';

    document.body.appendChild(modalEl);

    modalEl.addEventListener('click', (event) => {
      if (event.target === modalEl) closeModal();
    });
    modalEl.querySelector('.profile-modal-close').addEventListener('click', closeModal);

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !modalEl.classList.contains('hidden')) {
        closeModal();
      }
    });

    return modalEl;
  }

  function closeModal() {
    if (modalEl) modalEl.classList.add('hidden');
    currentUserId = null;
  }

  async function openProfile(userId) {
    if (!userId) return;
    return renderProfile(`id=${encodeURIComponent(String(userId))}`, userId);
  }

  // برای منشن @username داخل متن پیام - چون شناسه‌ی عددی کاربر در متن موجود
  // نیست، پروفایل مستقیماً با نام کاربری از سرور واکشی می‌شود.
  async function openProfileByUsername(username) {
    if (!username) return;
    return renderProfile(`username=${encodeURIComponent(username)}`, 'u:' + username);
  }

  async function renderProfile(queryString, tokenKey) {
    currentUserId = tokenKey;
    const myToken = ++requestToken;

    const modal = ensureModal();
    modal.classList.remove('hidden');

    const nameEl = modal.querySelector('.profile-modal-name');
    const usernameEl = modal.querySelector('.profile-modal-username');
    const bioEl = modal.querySelector('.profile-modal-bio');
    const metaEl = modal.querySelector('.profile-modal-meta');
    const avatarEl = modal.querySelector('.profile-modal-avatar');
    const actionsEl = modal.querySelector('.profile-modal-actions');

    nameEl.textContent = 'در حال بارگذاری...';
    usernameEl.textContent = '';
    bioEl.classList.add('hidden');
    bioEl.textContent = '';
    metaEl.textContent = '';
    avatarEl.innerHTML = '';
    actionsEl.innerHTML = '';

    try {
      const res = await fetch(`${baseUrl()}api/user_profile.php?${queryString}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      const data = await res.json();

      if (myToken !== requestToken) return; // مودال دیگری در این فاصله باز شده

      if (!res.ok || data.error) {
        nameEl.textContent = 'کاربر پیدا نشد یا حذف شده است.';
        return;
      }

      avatarEl.innerHTML = data.avatar_html || '';
      nameEl.textContent = data.display_name || data.username;
      usernameEl.textContent = '@' + data.username;

      if (data.bio) {
        bioEl.textContent = data.bio;
        bioEl.classList.remove('hidden');
      }

      metaEl.innerHTML = '';
      const metaParts = [];
      if (data.online) metaParts.push({ dot: true, text: 'آنلاین' });
      if (data.is_admin) metaParts.push({ text: 'ادمین' });
      if (data.is_blocked_account) metaParts.push({ text: 'این حساب توسط مدیر مسدود شده' });
      metaParts.forEach((part, i) => {
        if (i > 0) metaEl.appendChild(document.createTextNode(' · '));
        if (part.dot) {
          const dot = document.createElement('span');
          dot.className = 'profile-modal-online-dot';
          metaEl.appendChild(dot);
        }
        metaEl.appendChild(document.createTextNode(part.text));
      });

      if (!data.is_self) {
        const chatBtn = document.createElement('button');
        chatBtn.type = 'button';
        chatBtn.className = 'btn btn-primary';
        chatBtn.textContent = data.conversation_id ? 'مشاهده گفتگو' : 'شروع گفتگو';
        chatBtn.addEventListener('click', () => startChat(data.id));
        actionsEl.appendChild(chatBtn);

        const blockBtn = document.createElement('button');
        blockBtn.type = 'button';
        blockBtn.className = 'btn-mini btn-mini-danger';
        blockBtn.textContent = data.i_blocked ? 'رفع بلاک' : 'بلاک کاربر';
        blockBtn.addEventListener('click', () => toggleBlock(data.id, blockBtn));
        actionsEl.appendChild(blockBtn);
      }
    } catch (e) {
      if (myToken === requestToken) {
        nameEl.textContent = 'خطا در بارگذاری پروفایل.';
      }
    }
  }

  function startChat(userId) {
    closeModal();
    const form = document.createElement('form');
    form.method = 'post';
    form.action = `${baseUrl()}start_conversation.php`;
    form.style.display = 'none';

    const csrfInput = document.createElement('input');
    csrfInput.name = 'csrf_token';
    csrfInput.value = getCsrfToken();
    form.appendChild(csrfInput);

    const idInput = document.createElement('input');
    idInput.name = 'user_id';
    idInput.value = String(userId);
    form.appendChild(idInput);

    document.body.appendChild(form);
    if (form.requestSubmit) {
      form.requestSubmit();
    } else {
      form.submit();
    }
    setTimeout(() => form.remove(), 2000);
  }

  async function toggleBlock(userId, btnEl) {
    btnEl.disabled = true;
    try {
      const res = await fetch(`${baseUrl()}api/toggle_block_user.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId, csrf_token: getCsrfToken() }),
      });
      const data = await res.json();
      if (res.ok && typeof data.blocked === 'boolean') {
        btnEl.textContent = data.blocked ? 'رفع بلاک' : 'بلاک کاربر';
      } else {
        dialogAlert((data && data.error) ? 'خطا: ' + data.error : 'خطا در تغییر وضعیت بلاک.');
      }
    } catch (e) {
      dialogAlert('خطا در ارتباط با سرور.');
    } finally {
      btnEl.disabled = false;
    }
  }

  document.addEventListener(
    'click',
    (event) => {
      const avatarTrigger = event.target.closest('.js-avatar');
      if (avatarTrigger) {
        const userId = parseInt(avatarTrigger.dataset.userId || '0', 10);
        if (!userId) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        openProfile(userId);
        return;
      }

      const mentionTrigger = event.target.closest('.js-mention');
      if (mentionTrigger) {
        const username = mentionTrigger.dataset.username || '';
        if (!username) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        openProfileByUsername(username);
      }
    },
    true
  );

  document.addEventListener(
    'keydown',
    (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      const avatarTrigger = event.target.closest && event.target.closest('.js-avatar');
      if (avatarTrigger) {
        const userId = parseInt(avatarTrigger.dataset.userId || '0', 10);
        if (!userId) return;
        event.preventDefault();
        openProfile(userId);
        return;
      }

      const mentionTrigger = event.target.closest && event.target.closest('.js-mention');
      if (mentionTrigger) {
        const username = mentionTrigger.dataset.username || '';
        if (!username) return;
        event.preventDefault();
        openProfileByUsername(username);
      }
    },
    true
  );

  window.ChativaProfile = { open: openProfile, openByUsername: openProfileByUsername, close: closeModal };
})();
