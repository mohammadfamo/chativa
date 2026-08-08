/**
 * تشخیص/اعمال حالت تاریک - مرحله ۱۰ (+ حالت خودکار/سیستم، بازبینی بعدی)
 * -------------------------------------------------
 * این اسکریپت باید در <head> و پیش از بارگذاری استایل صفحه اجرا شود (نه در
 * انتهای body) تا بدون هیچ فِلَش نور اشتباهی، همان لحظه‌ی اول attribute
 * data-theme روی <html> ست شود. به همین دلیل هم فایل جداگانه است (نه بخشی
 * از main.js/dialog.js که در پایین صفحه لود می‌شوند) و هم به‌صورت
 * synchronous (بدون async/defer) در <head> هر صفحه include شده.
 *
 * سه «حالت» (mode) وجود دارد: 'light'، 'dark' و 'system'. حالت 'system'
 * یعنی تم فعلی همیشه از prefers-color-scheme مرورگر/سیستم‌عامل خوانده
 * می‌شود - هم در بارگذاری اول و هم زنده (با matchMedia listener) اگر کاربر
 * تم سیستم را عوض کند (مثلاً روشن/تاریک خودکار شبانه‌روزی ویندوز/مک/موبایل)
 * بدون نیاز به رفرش صفحه اعمال می‌شود. پیش‌فرض کاربرانی که هنوز هیچ انتخابی
 * نکرده‌اند هم 'system' است (نه یک حدس ثابت).
 *
 * تابع window.ChativaTheme.toggle() برای دکمه‌ی تغییر تم در سایدبار
 * استفاده می‌شود (که در assets/js/dialog.js بعداً لود می‌شود، پس این تابع
 * باید همین‌جا و زودهنگام روی window قرار بگیرد).
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'chativa-theme';
  var mql = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  function getStoredMode() {
    try {
      var v = localStorage.getItem(STORAGE_KEY);
      return v === 'dark' || v === 'light' || v === 'system' ? v : null;
    } catch (e) {
      return null; // مثلاً حالت خصوصی مرورگر که localStorage را مسدود می‌کند
    }
  }

  function systemPrefersDark() {
    return !!(mql && mql.matches);
  }

  function effectiveTheme(mode) {
    return mode === 'system' ? (systemPrefersDark() ? 'dark' : 'light') : mode;
  }

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
  }

  var currentMode = getStoredMode() || 'system';

  function setMode(mode) {
    if (mode !== 'light' && mode !== 'dark' && mode !== 'system') return;
    currentMode = mode;
    try {
      localStorage.setItem(STORAGE_KEY, mode);
    } catch (e) { /* بی‌اهمیت */ }
    applyTheme(effectiveTheme(mode));
    document.dispatchEvent(new CustomEvent('chativa:theme-changed', { detail: { mode: mode, theme: effectiveTheme(mode) } }));
  }

  /** سازگاری با کد قبلی: مقدار مستقیم 'light'/'dark' (بدون 'system') می‌گیرد */
  function setTheme(theme) {
    setMode(theme === 'dark' ? 'dark' : 'light');
  }

  function currentTheme() {
    return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  }

  function currentMode_() {
    return currentMode;
  }

  /** چرخه‌ی سه‌حالته‌ی دکمه‌ی سایدبار: روشن → تاریک → خودکار(سیستم) → روشن ... */
  function toggleTheme() {
    var next = currentMode === 'light' ? 'dark' : (currentMode === 'dark' ? 'system' : 'light');
    setMode(next);
  }

  // اعمال فوری (پیش از رندر body) بر اساس حالت ذخیره‌شده یا پیش‌فرض 'system'
  applyTheme(effectiveTheme(currentMode));

  // در حالت 'system'، تغییر زنده‌ی ترجیح سیستم (مثلاً غروب/طلوع خودکار
  // ویندوز یا مک) بدون رفرش صفحه اعمال می‌شود.
  if (mql) {
    var onSystemChange = function () {
      if (currentMode === 'system') {
        applyTheme(effectiveTheme('system'));
        document.dispatchEvent(new CustomEvent('chativa:theme-changed', { detail: { mode: 'system', theme: effectiveTheme('system') } }));
      }
    };
    if (mql.addEventListener) {
      mql.addEventListener('change', onSystemChange);
    } else if (mql.addListener) {
      mql.addListener(onSystemChange); // سازگاری با مرورگرهای قدیمی‌تر (Safari قدیم)
    }
  }

  window.ChativaTheme = {
    toggle: toggleTheme,
    set: setTheme,
    setMode: setMode,
    current: currentTheme,
    mode: currentMode_,
  };
})();
