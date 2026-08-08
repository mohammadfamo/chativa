/**
 * پاپ‌آپ باکس‌های سراسری سایت (مرحله ۷)
 * -------------------------------------------------
 * جایگزین alert()/confirm() بومی مرورگر با یک باکس زیبا و هماهنگ با ظاهر
 * سایت، به‌علاوه یک منوی اکشن (Action Sheet / Context Menu) قابل‌استفاده
 * برای منوی راست‌کلیک/لمس‌طولانی روی پیام‌ها و هر جای دیگر سایت.
 *
 * این اسکریپت یک‌بار و به‌صورت سراسری در همه‌ی صفحات بارگذاری می‌شود
 * (مشابه profile.js) تا API آن (window.ChativaDialog) همیشه در دسترس باشد.
 */
(function () {
  'use strict';

  function icon(name) {
    return (window.ChativaIcons && window.ChativaIcons[name]) || '';
  }

  function trapFocus(container) {
    const focusable = container.querySelectorAll('button, [href], input, textarea, [tabindex]:not([tabindex="-1"])');
    if (focusable.length === 0) return;
    focusable[0].focus();
  }

  // ---------------------------------------------------------
  // Alert / Confirm
  // ---------------------------------------------------------
  function showBox({ title, message, buttons, danger }) {
    return new Promise((resolve) => {
      const overlay = document.createElement('div');
      overlay.className = 'chativa-dialog-overlay';

      const box = document.createElement('div');
      box.className = 'chativa-dialog-box' + (danger ? ' danger' : '');
      box.setAttribute('role', 'alertdialog');
      box.setAttribute('aria-modal', 'true');

      let html = '';
      if (title) html += `<h3 class="chativa-dialog-title">${title}</h3>`;
      html += `<p class="chativa-dialog-message"></p>`;
      html += '<div class="chativa-dialog-actions"></div>';
      box.innerHTML = html;

      box.querySelector('.chativa-dialog-message').textContent = message == null ? '' : String(message);

      const actionsEl = box.querySelector('.chativa-dialog-actions');
      buttons.forEach((btnDef) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-mini' + (btnDef.primary ? ' btn-mini-primary' : '') + (btnDef.dangerBtn ? ' btn-mini-danger' : '');
        btn.textContent = btnDef.text;
        btn.addEventListener('click', () => {
          cleanup();
          resolve(btnDef.value);
        });
        actionsEl.appendChild(btn);
      });

      overlay.appendChild(box);
      document.body.appendChild(overlay);

      function onKeydown(e) {
        if (e.key === 'Escape') {
          cleanup();
          resolve(buttons.find((b) => b.isCancel) ? buttons.find((b) => b.isCancel).value : buttons[0].value);
        }
      }

      function cleanup() {
        document.removeEventListener('keydown', onKeydown, true);
        overlay.remove();
      }

      document.addEventListener('keydown', onKeydown, true);
      requestAnimationFrame(() => trapFocus(box));
    });
  }

  /** جایگزین alert() - یک باکس با دکمه‌ی «باشه» */
  function chativaAlert(message, opts) {
    opts = opts || {};
    return showBox({
      title: opts.title || '',
      message,
      buttons: [{ text: opts.okText || 'باشه', value: true, primary: true, isCancel: true }],
    });
  }

  /** جایگزین confirm() - Promise<boolean> */
  function chativaConfirm(message, opts) {
    opts = opts || {};
    return showBox({
      title: opts.title || '',
      message,
      danger: !!opts.danger,
      buttons: [
        { text: opts.cancelText || 'انصراف', value: false, isCancel: true },
        { text: opts.confirmText || 'تایید', value: true, primary: !opts.danger, dangerBtn: !!opts.danger },
      ],
    });
  }

  // ---------------------------------------------------------
  // منوی اکشن (برای راست‌کلیک/لمس‌طولانی روی پیام و موارد مشابه)
  // دسکتاپ: نزدیک نقطه‌ی کلیک باز می‌شود (با کلمپ به داخل صفحه).
  // موبایل/لمس: از پایین صفحه به‌صورت Action Sheet باز می‌شود.
  // ---------------------------------------------------------
  let openMenuCleanup = null;

  function closeOpenMenu() {
    if (openMenuCleanup) {
      openMenuCleanup();
      openMenuCleanup = null;
    }
  }

  /**
   * items: [{ label, icon: iconName, danger: bool, onSelect: fn }]
   * pos: { x, y } برای دسکتاپ. اگر sheet=true باشد، پوزیشن نادیده گرفته می‌شود.
   */
  function openMenu(items, pos, sheet) {
    closeOpenMenu();

    const overlay = document.createElement('div');
    overlay.className = 'chativa-menu-overlay' + (sheet ? ' sheet' : '');

    const menu = document.createElement('div');
    menu.className = 'chativa-menu' + (sheet ? ' chativa-menu-sheet' : '');
    menu.setAttribute('role', 'menu');

    items.forEach((item) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'chativa-menu-item' + (item.danger ? ' danger' : '');
      btn.setAttribute('role', 'menuitem');
      btn.innerHTML = (item.icon ? `<span class="chativa-menu-icon">${icon(item.icon)}</span>` : '') + `<span>${item.label}</span>`;
      btn.addEventListener('click', () => {
        cleanup();
        if (item.onSelect) item.onSelect();
      });
      menu.appendChild(btn);
    });

    overlay.appendChild(menu);
    document.body.appendChild(overlay);

    if (!sheet && pos) {
      // ابتدا رندر می‌شود تا اندازه‌ی واقعی منو مشخص شود، سپس پوزیشن کلمپ‌شده ست می‌شود
      const rect = menu.getBoundingClientRect();
      const vw = window.innerWidth;
      const vh = window.innerHeight;
      let left = pos.x;
      let top = pos.y;
      if (left + rect.width > vw - 8) left = vw - rect.width - 8;
      if (top + rect.height > vh - 8) top = vh - rect.height - 8;
      if (left < 8) left = 8;
      if (top < 8) top = 8;
      menu.style.left = left + 'px';
      menu.style.top = top + 'px';
    }

    function onOverlayClick(e) {
      if (e.target === overlay) cleanup();
    }
    function onKeydown(e) {
      if (e.key === 'Escape') cleanup();
    }

    overlay.addEventListener('click', onOverlayClick);
    document.addEventListener('keydown', onKeydown, true);

    function cleanup() {
      overlay.removeEventListener('click', onOverlayClick);
      document.removeEventListener('keydown', onKeydown, true);
      overlay.remove();
      if (openMenuCleanup === cleanup) openMenuCleanup = null;
    }

    openMenuCleanup = cleanup;
  }

  // ---------------------------------------------------------
  // ردیف کوچک انتخاب سریع ری‌اکشن (مرحله ۱۰) - همان زیرساخت overlay/کلمپ/
  // Escape منوی اکشن را دوباره استفاده می‌کند، فقط آیتم‌ها ایموجی‌اند نه
  // برچسب+آیکون.
  // emojis: string[]، pos: {x,y}|null، onSelect: fn(emoji)
  // ---------------------------------------------------------
  function openReactionPicker(emojis, pos, onSelect) {
    closeOpenMenu();

    const overlay = document.createElement('div');
    overlay.className = 'chativa-menu-overlay';

    const bar = document.createElement('div');
    bar.className = 'chativa-reaction-picker';
    bar.setAttribute('role', 'menu');

    emojis.forEach((emoji) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'chativa-reaction-picker-item';
      btn.textContent = emoji;
      btn.addEventListener('click', () => {
        cleanup();
        if (onSelect) onSelect(emoji);
      });
      bar.appendChild(btn);
    });

    overlay.appendChild(bar);
    document.body.appendChild(overlay);

    if (pos) {
      const rect = bar.getBoundingClientRect();
      const vw = window.innerWidth;
      const vh = window.innerHeight;
      let left = pos.x;
      let top = pos.y;
      if (left + rect.width > vw - 8) left = vw - rect.width - 8;
      if (top + rect.height > vh - 8) top = vh - rect.height - 8;
      if (left < 8) left = 8;
      if (top < 8) top = 8;
      bar.style.left = left + 'px';
      bar.style.top = top + 'px';
    }

    function onOverlayClick(e) {
      if (e.target === overlay) cleanup();
    }
    function onKeydown(e) {
      if (e.key === 'Escape') cleanup();
    }

    overlay.addEventListener('click', onOverlayClick);
    document.addEventListener('keydown', onKeydown, true);

    function cleanup() {
      overlay.removeEventListener('click', onOverlayClick);
      document.removeEventListener('keydown', onKeydown, true);
      overlay.remove();
      if (openMenuCleanup === cleanup) openMenuCleanup = null;
    }

    openMenuCleanup = cleanup;
  }

  window.ChativaDialog = {
    alert: chativaAlert,
    confirm: chativaConfirm,
    openMenu: openMenu,
    closeMenu: closeOpenMenu,
    openReactionPicker: openReactionPicker,
  };

  // ---------------------------------------------------------
  // فرم‌هایی که data-confirm دارند (مثلاً حذف کاربر در پنل ادمین) به‌جای
  // confirm() بومی، از همین پاپ‌آپ استفاده می‌کنند. با فاز capture ثبت
  // می‌شود تا مستقل از ترتیب بارگذاری اسکریپت‌ها، همیشه قبل از هندلر
  // submit بومی pjax.js اجرا شود (دقیقاً مثل الگوی profile.js).
  // ---------------------------------------------------------
  document.addEventListener(
    'submit',
    (event) => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement)) return;
      const msg = form.getAttribute('data-confirm');
      if (!msg) return;

      if (form.dataset.confirmed === '1') {
        delete form.dataset.confirmed;
        return; // قبلاً تایید شده؛ اجازه بده ادامه پیدا کند
      }

      event.preventDefault();
      event.stopImmediatePropagation();

      chativaConfirm(msg, {
        danger: form.dataset.confirmDanger !== '0',
        confirmText: form.dataset.confirmText || 'تایید',
      }).then((ok) => {
        if (ok) {
          form.dataset.confirmed = '1';
          if (form.requestSubmit) form.requestSubmit();
          else form.submit();
        }
      });
    },
    true
  );

  // ---------------------------------------------------------
  // دکمه‌ی تغییر حالت روشن/تاریک (سایدبار اصلی + هدر پنل ادمین، مرحله ۱۰).
  // چون این دکمه هر بار سایدبار دوباره رندر می‌شود (ناوبری PJAX) از نو در
  // DOM ساخته می‌شود، از event delegation روی document استفاده می‌کنیم تا
  // نیازی به اتصال مجدد listener نباشد. آیکون دکمه هم بعد از هر بار رندر
  // (بارگذاری اول یا PJAX) با وضعیت واقعی تم هماهنگ می‌شود.
  // ---------------------------------------------------------
  // حالت‌ها: 'light' (روشن) / 'dark' (تاریک) / 'system' (خودکار، هماهنگ با
  // سیستم‌عامل/مرورگر - از جمله تغییر زنده‌ی روز/شب بدون رفرش صفحه).
  const THEME_ICON_BY_MODE = { light: 'sun', dark: 'moon', system: 'monitor' };
  const THEME_LABEL_BY_MODE = { light: 'روشن', dark: 'تاریک', system: 'خودکار (هماهنگ با سیستم)' };

  function syncThemeToggleButtons() {
    if (!window.ChativaTheme) return;
    const mode = window.ChativaTheme.mode ? window.ChativaTheme.mode() : (window.ChativaTheme.current() === 'dark' ? 'dark' : 'light');
    const iconHtml = (window.ChativaIcons && window.ChativaIcons[THEME_ICON_BY_MODE[mode] || 'moon']) || '';
    document.querySelectorAll('#btn-theme-toggle').forEach((btn) => {
      btn.innerHTML = iconHtml;
      btn.title = 'حالت نمایش: ' + (THEME_LABEL_BY_MODE[mode] || '') + ' (برای تغییر کلیک کنید)';
    });
  }

  document.addEventListener('click', (event) => {
    if (!event.target.closest('#btn-theme-toggle')) return;
    if (!window.ChativaTheme) return;
    window.ChativaTheme.toggle();
    syncThemeToggleButtons();
  });

  document.addEventListener('pjax:loaded', syncThemeToggleButtons);
  // اگر حالت 'system' فعال باشد و سیستم/مرورگر خودش تم را عوض کند (بدون
  // کلیک کاربر روی دکمه)، theme.js رویداد chativa:theme-changed را
  // شلیک می‌کند - آیکون دکمه باید همان لحظه هماهنگ شود.
  document.addEventListener('chativa:theme-changed', syncThemeToggleButtons);
  syncThemeToggleButtons();
})();
