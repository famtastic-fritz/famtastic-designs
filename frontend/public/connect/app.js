(() => {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const intro = $('intro');
  const qrPrompt = $('qrPrompt');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const hintKey = 'famtastic-connect-qr-hint-v1';
  let hintSeen = false;
  // Preference storage is optional; blocked storage must never break the card.
  for (const storageName of ['localStorage', 'sessionStorage']) {
    try { hintSeen = hintSeen || window[storageName].getItem(hintKey) === 'seen'; } catch {}
  }
  let hintTimer;
  let hintSettleTimer;
  const rememberHint = () => {
    hintSeen = true;
    for (const storageName of ['localStorage', 'sessionStorage']) {
      try { window[storageName].setItem(hintKey, 'seen'); } catch {}
    }
  };
  const settleHint = () => {
    clearTimeout(hintTimer);
    clearTimeout(hintSettleTimer);
    qrPrompt.classList.add('is-visible');
    qrPrompt.classList.remove('is-nudging');
    rememberHint();
  };
  const scheduleHint = () => {
    clearTimeout(hintTimer);
    if (hintSeen || reducedMotion.matches) { settleHint(); return; }
    hintTimer = setTimeout(() => {
      if (document.hidden) return;
      qrPrompt.classList.add('is-visible');
      qrPrompt.classList.add('is-nudging');
      rememberHint();
      hintSettleTimer = setTimeout(settleHint, 2400);
    }, 1250);
  };
  reducedMotion.addEventListener('change', () => { if (reducedMotion.matches) settleHint(); });
  let introTimer;
  let introExitTimer;
  let closed = false;
  let introStartedAt = 0;
  const standalone = () => window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  const dismissIntro = () => {
    if (closed) return;
    closed = true;
    clearTimeout(introTimer);
    intro.classList.add('is-leaving');
    document.body.classList.add('enter');
    if (document.activeElement === $('skipIntro')) $('watch').focus({preventScroll: true});
    introExitTimer = setTimeout(() => {
      intro.hidden = true;
      intro.setAttribute('aria-hidden', 'true');
      scheduleHint();
    }, 400);
  };
  const startIntro = (focusSkip = false) => {
    clearTimeout(introTimer);
    clearTimeout(introExitTimer);
    if (standalone() || reducedMotion.matches) {
      intro.hidden = true;
      intro.setAttribute('aria-hidden', 'true');
      if (focusSkip) $('watch').focus({preventScroll: true});
      scheduleHint();
      return;
    }
    closed = false;
    introStartedAt = Date.now();
    intro.classList.remove('is-leaving');
    document.body.classList.remove('enter');
    intro.hidden = false;
    intro.setAttribute('aria-hidden', 'false');
    if (focusSkip) $('skipIntro').focus({preventScroll: true});
    introTimer = setTimeout(dismissIntro, 2300);
  };
  $('skipIntro').addEventListener('click', dismissIntro);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !intro.hidden) dismissIntro(); });
  // The card stays usable if an animation or timer is interrupted.
  window.addEventListener('pageshow', (event) => { if (event.persisted && introStartedAt) dismissIntro(); });
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden && introStartedAt && Date.now() - introStartedAt >= 4000) dismissIntro();
    if (!document.hidden && intro.hidden) scheduleHint();
  });
  if (location.hash !== '#qr') startIntro();
  const videoDialog = $('videoDialog');
  const video = $('commercial');
  $('watch').addEventListener('click', () => {
    if (!videoDialog.showModal) { location.href = 'commercial.mp4'; return; }
    videoDialog.showModal();
    $('videoMessage').hidden = true;
    video.muted = false;
    const promise = video.play();
    if (promise) promise.catch(() => { $('videoMessage').hidden = false; });
  });
  $('closeVideo').addEventListener('click', () => videoDialog.close());
  videoDialog.addEventListener('close', () => video.pause());
  video.addEventListener('error', () => {
    $('videoMessage').textContent = 'The video could not load. Try “Open video directly” below.';
    $('videoMessage').hidden = false;
  });
  $('saveContact').addEventListener('click', () => { $('contactHint').hidden = false; });
  const qrDialog = $('qrDialog');
  let qrOpener = $('showQrPrimary');
  const openQr = () => {
    settleHint();
    dismissIntro();
    if (qrDialog.showModal) {
      if (!qrDialog.open) {
        $('qrShareStatus').hidden = true;
        qrDialog.showModal();
        $('closeQr').focus({preventScroll: true});
      }
    }
    else location.href = 'qr.svg';
  };
  for (const button of [$('showQrPrimary'), $('showQr')]) {
    button.addEventListener('click', () => { qrOpener = button; location.hash = 'qr'; openQr(); });
  }
  qrDialog.addEventListener('close', () => {
    if (location.hash === '#qr') history.replaceState(null, '', location.pathname + location.search);
    qrOpener.focus({preventScroll: true});
  });
  window.addEventListener('hashchange', () => {
    if (location.hash === '#qr') openQr();
    else if (qrDialog.open) qrDialog.close();
  });
  $('closeQr').addEventListener('click', () => qrDialog.close());
  // Keep Tab cycling through the sheet's controls, including on browsers that
  // otherwise move focus into browser chrome at a native dialog's boundary.
  qrDialog.addEventListener('keydown', (event) => {
    if (event.key !== 'Tab') return;
    const controls = [...qrDialog.querySelectorAll('button:not(:disabled), a[href]')]
      .filter(control => control.getClientRects().length);
    const first = controls[0];
    const last = controls[controls.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });
  $('shareQrLink').addEventListener('click', async () => {
    const url = 'https://famtasticdesigns.com/connect';
    const status = $('qrShareStatus');
    status.hidden = true;
    if (navigator.share) {
      try { await navigator.share({ title: 'Connect with Fritz · FAMtastic Designs', url }); return; }
      catch (error) { if (error.name === 'AbortError') return; }
    }
    try {
      await navigator.clipboard.writeText(url);
      status.textContent = 'Link copied. Paste it wherever you want to share.';
    } catch {
      status.textContent = `Copy this link: ${url}`;
    }
    status.hidden = false;
  });
  document.querySelectorAll('[data-open-card]').forEach((link) => {
    link.addEventListener('click', (e) => {
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button > 0) return;
      e.preventDefault();
      qrDialog.close();
      history.replaceState(null, '', location.pathname + location.search);
      window.scrollTo(0, 0);
      startIntro(true);
    });
  });
  for (const dialog of [videoDialog, qrDialog]) {
    dialog.addEventListener('click', (e) => {
      const r = dialog.getBoundingClientRect();
      if (e.target === dialog && (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom)) dialog.close();
    });
  }
  if (location.hash === '#qr') openQr();
})();
