(() => {
  'use strict';

  const measurementId = 'G-T2ENFBZR4K';
  const canonicalPath = '/and-if-it-is/';
  const canonicalUrl = `https://famtasticdesigns.com${canonicalPath}`;
  const contentId = 'and-if-it-is-rattler-lifers-v1';
  const storageKey = 'and-if-it-is-roll-call-v1';

  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function gtag() {
    window.dataLayer.push(arguments);
  };
  window.gtag('js', new Date());
  window.gtag('config', measurementId, { send_page_view: false });

  const analyticsScript = document.createElement('script');
  analyticsScript.async = true;
  analyticsScript.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
  document.head.appendChild(analyticsScript);

  const track = (eventName, parameters = {}) => {
    window.gtag('event', eventName, { content_id: contentId, ...parameters });
  };

  track('page_view', {
    page_location: canonicalUrl,
    page_path: canonicalPath,
    page_title: document.title,
  });

  document.querySelectorAll('[data-track]').forEach((link) => {
    link.addEventListener('click', () => track('cta_clicked', {
      cta_id: link.dataset.track,
      cta_location: link.dataset.location || 'navigation',
    }));
  });

  const header = document.querySelector('[data-header]');
  const setHeaderState = () => {
    header?.classList.toggle('is-fixed', window.scrollY > 720);
  };

  setHeaderState();
  window.addEventListener('scroll', setHeaderState, { passive: true });

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const reveals = document.querySelectorAll('.reveal');

  if (reducedMotion || !('IntersectionObserver' in window)) {
    reveals.forEach((item) => item.classList.add('is-visible'));
  } else {
    const revealObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      });
    }, { threshold: 0.14, rootMargin: '0px 0px -40px' });

    reveals.forEach((item) => revealObserver.observe(item));
  }

  const rollForm = document.querySelector('[data-roll-form]');
  const rollResult = document.querySelector('[data-roll-result]');
  const rollOutput = document.querySelector('[data-roll-output]');
  const rollStatus = document.querySelector('[data-roll-status]');
  const copyButton = document.querySelector('[data-roll-copy]');
  const shareButton = document.querySelector('[data-roll-share]');
  let shareText = '';

  const formatRollCall = (year, memory) => `CLASS OF ${year} · ${memory} — Once a Rattler. Always a Rattler. #AndIfItIs`;

  const showRollCall = (year, memory, restored = false) => {
    shareText = formatRollCall(year, memory);
    rollOutput.textContent = shareText;
    rollResult.hidden = false;
    rollStatus.textContent = restored ? 'Your private roll call was restored from this device.' : 'Your roll call is ready. Copy it or share it when you choose.';
  };

  try {
    const saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
    if (saved && /^\d{4}$/.test(saved.year) && typeof saved.memory === 'string' && saved.memory.trim()) {
      rollForm.elements.year.value = saved.year;
      rollForm.elements.memory.value = saved.memory;
      showRollCall(saved.year, saved.memory, true);
    }
  } catch {
    localStorage.removeItem(storageKey);
  }

  rollForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    const data = new FormData(rollForm);
    const year = String(data.get('year') || '').trim();
    const memory = String(data.get('memory') || '').trim();

    if (!/^\d{4}$/.test(year) || !memory) {
      rollResult.hidden = false;
      rollOutput.textContent = 'Add a four-digit class year and the memory that brings you back.';
      rollStatus.textContent = '';
      return;
    }

    localStorage.setItem(storageKey, JSON.stringify({ year, memory }));
    showRollCall(year, memory);
    track('roll_call_generated', { storage_scope: 'device_only' });
  });

  const copyRollCall = async () => {
    if (!shareText) return false;
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(shareText);
      return true;
    }
    const helper = document.createElement('textarea');
    helper.value = shareText;
    helper.setAttribute('readonly', '');
    helper.style.position = 'fixed';
    helper.style.opacity = '0';
    document.body.appendChild(helper);
    helper.select();
    const copied = document.execCommand('copy');
    helper.remove();
    return copied;
  };

  copyButton?.addEventListener('click', async () => {
    try {
      const copied = await copyRollCall();
      rollStatus.textContent = copied ? 'Copied. Your roll call is ready to paste.' : 'Copy was unavailable in this browser.';
      if (copied) track('roll_call_shared', { method: 'clipboard' });
    } catch {
      rollStatus.textContent = 'Copy was unavailable in this browser.';
    }
  });

  shareButton?.addEventListener('click', async () => {
    if (!shareText) return;
    try {
      if (navigator.share) {
        await navigator.share({ title: 'AND IF IT IS? Rattler Roll Call', text: shareText, url: canonicalUrl });
        rollStatus.textContent = 'Shared from your device.';
        track('roll_call_shared', { method: 'native_share' });
      } else {
        const copied = await copyRollCall();
        rollStatus.textContent = copied ? 'Sharing is not available here, so the roll call was copied instead.' : 'Sharing is unavailable in this browser.';
        if (copied) track('roll_call_shared', { method: 'clipboard_fallback' });
      }
    } catch (error) {
      if (error?.name !== 'AbortError') rollStatus.textContent = 'Sharing was not completed. Your roll call remains private on this device.';
    }
  });
})();
