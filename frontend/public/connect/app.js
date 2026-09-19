(() => {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const intro = $('intro');
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
    introExitTimer = setTimeout(() => { intro.hidden = true; intro.setAttribute('aria-hidden', 'true'); }, 400);
  };
  const startIntro = (focusSkip = false) => {
    clearTimeout(introTimer);
    clearTimeout(introExitTimer);
    if (standalone() || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      intro.hidden = true;
      intro.setAttribute('aria-hidden', 'true');
      if (focusSkip) $('watch').focus({preventScroll: true});
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
  const openQr = () => {
    dismissIntro();
    if (qrDialog.showModal) { if (!qrDialog.open) qrDialog.showModal(); }
    else location.href = 'qr.svg';
  };
  $('showQr').addEventListener('click', () => { location.hash = 'qr'; openQr(); });
  qrDialog.addEventListener('close', () => {
    if (location.hash === '#qr') history.replaceState(null, '', location.pathname + location.search);
  });
  window.addEventListener('hashchange', () => {
    if (location.hash === '#qr') openQr();
    else if (qrDialog.open) qrDialog.close();
  });
  $('closeQr').addEventListener('click', () => qrDialog.close());
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
