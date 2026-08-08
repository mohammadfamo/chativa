/**
 * ناوبری آجاکسی (PJAX) بین صفحات و فرم‌های سایت - مرحله ۶
 * -------------------------------------------------
 * این اسکریپت یک‌بار و به‌صورت سراسری (خارج از #page-content) بارگذاری
 * می‌شود و هم کلیک روی لینک‌های داخلی و هم ارسال فرم‌ها (GET و POST، شامل
 * آپلود فایل) را می‌گیرد، پاسخ سرور را با fetch می‌آورد، فقط محتوای داخل
 * #page-content را جایگزین می‌کند و اسکریپت‌های آن صفحه را دوباره اجرا
 * می‌کند - بدون رفرش کامل مرورگر. سایدبار، URL و دکمه‌ی back/forward
 * به‌درستی کار می‌کنند. چون این پروژه از الگوی Redirect-After-POST استفاده
 * می‌کند، همین منطق برای فرم‌ها هم بدون هیچ تغییری در PHP کار می‌کند.
 *
 * اگر صفحه‌ی مقصد فاقد #page-content باشد (مثل login.php یا register.php
 * که عمداً از PJAX مستثنی هستند)، به‌صورت خودکار به ناوبری/ارسال کامل
 * مرورگر برمی‌گردد (بدون از دست رفتن داده‌ی فرم).
 *
 * برای مستثنی‌کردن یک لینک یا فرم خاص (مثلاً فرمی که خودش منطق AJAX
 * اختصاصی دارد مثل ارسال پیام در main.js)، صفت data-no-pjax اضافه کنید.
 */
(function () {
  'use strict';

  const CONTENT_ID = 'page-content';
  let loading = false;

  function runTeardowns() {
    const fns = window.ChativaTeardowns || [];
    window.ChativaTeardowns = [];
    fns.forEach((fn) => {
      try {
        fn();
      } catch (e) {
        /* بی‌اهمیت */
      }
    });
  }

  function executeScripts(container) {
    const scripts = Array.from(container.querySelectorAll('script'));
    scripts.forEach((oldScript) => {
      const newScript = document.createElement('script');
      for (const attr of Array.from(oldScript.attributes)) {
        newScript.setAttribute(attr.name, attr.value);
      }
      if (newScript.src) {
        newScript.async = false; // حفظ ترتیب اجرا نسبت به سایر اسکریپت‌های همین صفحه
      }
      newScript.textContent = oldScript.textContent;
      oldScript.replaceWith(newScript);
    });
  }

  function setLoading(isLoading) {
    loading = isLoading;
    document.documentElement.classList.toggle('pjax-loading', isLoading);
  }

  async function swapFromResponse(res, fallbackUrl, push, fallbackFn) {
    const html = await res.text();
    const finalUrl = res.url || fallbackUrl;

    const doc = new DOMParser().parseFromString(html, 'text/html');
    const newContent = doc.getElementById(CONTENT_ID);
    const oldContent = document.getElementById(CONTENT_ID);

    if (!newContent || !oldContent) {
      fallbackFn();
      return;
    }

    runTeardowns();

    if (doc.title) {
      document.title = doc.title;
    }
    oldContent.innerHTML = newContent.innerHTML;
    executeScripts(oldContent);

    if (push) {
      history.pushState({ pjax: true }, '', finalUrl);
    } else {
      history.replaceState({ pjax: true }, '', finalUrl);
    }

    window.scrollTo(0, 0);
    // بعد از هر ناوبری موفق، حالت موبایل به نمایش محتوای اصلی برمی‌گردد
    // (نه فهرست سایدبار) - دقیقاً مثل یک بارگذاری کامل تازه
    document.body.classList.remove('mobile-view-list');
    document.dispatchEvent(new CustomEvent('pjax:loaded', { detail: { url: finalUrl } }));
  }

  async function go(url, opts) {
    const options = opts || {};
    const push = options.push !== false;

    if (loading) return;
    setLoading(true);
    try {
      const res = await fetch(url, {
        headers: { 'X-Pjax': '1' },
        credentials: 'same-origin',
      });
      await swapFromResponse(res, url, push, () => {
        window.location.href = url;
      });
    } catch (e) {
      window.location.href = url;
    } finally {
      setLoading(false);
    }
  }

  async function goForm(form, url, method) {
    if (loading) return;
    setLoading(true);
    try {
      const formData = new FormData(form);
      const fetchUrl = method === 'GET' ? url : url.split('#')[0];
      const fetchOptions = { headers: { 'X-Pjax': '1' }, credentials: 'same-origin', method };

      let finalUrl = fetchUrl;
      if (method === 'GET') {
        const target = new URL(fetchUrl, window.location.href);
        target.search = new URLSearchParams(formData).toString();
        finalUrl = target.href;
      } else {
        fetchOptions.body = formData;
      }

      const res = await fetch(finalUrl, fetchOptions);
      await swapFromResponse(res, finalUrl, true, () => {
        // برای POST نباید با یک GET ساده جایگزین شود (داده از دست می‌رود)؛
        // با submit() واقعی فرم، دقیقاً همان رفتاری که بدون PJAX رخ می‌داد اجرا می‌شود.
        form.submit();
      });
    } catch (e) {
      form.submit();
    } finally {
      setLoading(false);
    }
  }

  function isQualifyingLink(link, event) {
    if (!link || !link.href) return false;
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
    if (link.target && link.target !== '' && link.target !== '_self') return false;
    if (link.hasAttribute('download')) return false;
    if (link.dataset.noPjax !== undefined) return false;

    let url;
    try {
      url = new URL(link.href, window.location.href);
    } catch (e) {
      return false;
    }
    if (url.origin !== window.location.origin) return false;
    if (url.protocol !== 'http:' && url.protocol !== 'https:') return false;

    const rawHref = link.getAttribute('href') || '';
    if (rawHref.startsWith('#') || rawHref.startsWith('mailto:') || rawHref.startsWith('tel:') || rawHref.startsWith('javascript:')) {
      return false;
    }
    return true;
  }

  document.addEventListener('click', (event) => {
    if (event.defaultPrevented) return;
    const link = event.target.closest('a[href]');
    if (!isQualifyingLink(link, event)) return;

    const url = new URL(link.href, window.location.href);
    if (url.href === window.location.href) return;

    event.preventDefault();
    go(url.href, { push: true });
  });

  document.addEventListener('submit', (event) => {
    if (event.defaultPrevented) return; // مثلاً وقتی onsubmit="return confirm(...)" با کلیک «لغو» جلوی ارسال را گرفته
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.dataset.noPjax !== undefined) return;
    if (form.target && form.target !== '' && form.target !== '_self') return;

    let actionUrl;
    try {
      actionUrl = new URL(form.getAttribute('action') || window.location.href, window.location.href);
    } catch (e) {
      return;
    }
    if (actionUrl.origin !== window.location.origin) return;

    const method = (form.getAttribute('method') || 'GET').toUpperCase();
    if (method !== 'GET' && method !== 'POST') return;

    event.preventDefault();
    goForm(form, actionUrl.href, method);
  });

  window.addEventListener('popstate', () => {
    go(window.location.href, { push: false });
  });

  history.replaceState({ pjax: true }, '', window.location.href);

  // برای استفاده‌ی برنامه‌ای (مثلاً بعد از یک اکشن AJAX که باید صفحه عوض شود)
  window.pjaxNavigate = function (url) {
    go(url, { push: true });
  };
})();
