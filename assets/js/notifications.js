/**
 * اعلان مرورگر (Push Notification, فاز ۱) - مرحله ۱۰
 * -------------------------------------------------
 * این اسکریپت یک‌بار و به‌صورت سراسری (خارج از #page-content) بارگذاری
 * می‌شود، پس بین ناوبری‌های PJAX هم زنده می‌ماند - دقیقاً مثل theme.js.
 *
 * فعلاً از Notification API خودِ مرورگر استفاده می‌کند (بدون Service Worker)،
 * یعنی فقط وقتی خودِ تب چت باز است ولی کاربر به تب/برنامه‌ی دیگری رفته
 * (document.hidden) کار می‌کند - برای اعلان واقعی حتی با بسته‌بودن تب باید
 * بعداً Web Push + Service Worker اضافه شود (فاز ۲، در نقشه‌ی راه).
 *
 * تنظیم «فعال/غیرفعال» کاربر در localStorage ذخیره می‌شود چون یک ترجیح
 * مخصوصِ همین مرورگر/دستگاه است، نه بخشی از حساب کاربری سمت سرور.
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'chativa-notify-enabled';

  function isSupported() {
    return typeof window.Notification !== 'undefined';
  }

  function isEnabled() {
    if (!isSupported()) return false;
    try {
      return Notification.permission === 'granted' && localStorage.getItem(STORAGE_KEY) === '1';
    } catch (e) {
      return false;
    }
  }

  function setEnabled(value) {
    try {
      localStorage.setItem(STORAGE_KEY, value ? '1' : '0');
    } catch (e) {
      /* بی‌اهمیت - مثلاً حالت خصوصی مرورگر که localStorage را مسدود می‌کند */
    }
  }

  /** @return {Promise<'granted'|'denied'|'default'|'unsupported'>} */
  async function requestPermission() {
    if (!isSupported()) return 'unsupported';

    if (Notification.permission === 'granted') {
      setEnabled(true);
      return 'granted';
    }
    if (Notification.permission === 'denied') {
      setEnabled(false);
      return 'denied';
    }

    var result = await Notification.requestPermission();
    setEnabled(result === 'granted');
    return result;
  }

  /**
   * فقط وقتی همه‌ی این شرط‌ها برقرار باشد یک اعلان دسکتاپ نشان می‌دهد:
   * پشتیبانی مرورگر + اجازه‌ی کاربر + تنظیم فعال + تب در پس‌زمینه (کاربر
   * همین الان دارد به همین چت نگاه نمی‌کند).
   */
  function notify(title, body, opts) {
    if (!isEnabled() || !document.hidden) return;
    try {
      var n = new Notification(title, Object.assign({ body: body || '' }, opts || {}));
      n.onclick = function () {
        window.focus();
        n.close();
      };
    } catch (e) {
      /* بعضی مرورگرها (مثلاً برخی نسخه‌های موبایل) new Notification را پرتاب می‌کنند */
    }
  }

  window.ChativaNotify = {
    isSupported: isSupported,
    isEnabled: isEnabled,
    setEnabled: setEnabled,
    requestPermission: requestPermission,
    notify: notify,
  };
})();
