export const GALLERY_INTERVAL = 6000;
export function swipeDirection(start, end) {
  const dx = end.x - start.x, dy = end.y - start.y;
  return Math.abs(dx) >= 45 && Math.abs(dx) > Math.abs(dy) * 1.3 ? (dx < 0 ? 1 : -1) : 0;
}
export function initHeroGallery(doc = document, win = window) {
  const root = doc.querySelector('[data-hero-gallery]');
  if (!root || root.dataset.ready) return;
  root.dataset.ready = 'true';
  const slides = [...root.querySelectorAll('[data-slide]')];
  const dots = [...root.querySelectorAll('[data-slide-dot]')];
  const toggle = root.querySelector('[data-rotation]');
  const announcement = root.querySelector('[data-gallery-status]');
  const motion = win.matchMedia('(prefers-reduced-motion: reduce)');
  let index = 0, paused = motion.matches, hovered = false, focused = false, timer, pointer;
  function schedule() {
    win.clearTimeout(timer);
    toggle.textContent = paused ? 'Resume slideshow' : 'Pause slideshow';
    toggle.setAttribute('aria-pressed', String(paused));
    if (!paused && !hovered && !focused && !pointer && !doc.hidden) timer = win.setTimeout(() => { move(1); schedule(); }, GALLERY_INTERVAL);
  }
  function show(next, manual = false) {
    const image = slides[next]?.querySelector('img');
    if (!image?.complete || !image.naturalWidth) return false;
    slides[index].hidden = true; slides[next].hidden = false; index = next;
    dots.forEach((dot, i) => dot.setAttribute('aria-pressed', String(i === index)));
    if (manual) announcement.textContent = `${index + 1} of ${slides.length}: ${slides[index].querySelector('figcaption').textContent}`;
    return true;
  }
  function move(step, manual = false) {
    for (let offset = 1; offset < slides.length; offset++) if (show((index + step * offset + slides.length) % slides.length, manual)) break;
  }
  dots.forEach((dot, i) => dot.addEventListener('click', () => { show(i, true); schedule(); }));
  toggle.addEventListener('click', () => { paused = !paused; schedule(); });
  root.addEventListener('keydown', event => {
    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
      event.preventDefault(); move(event.key === 'ArrowRight' ? 1 : -1, true); schedule();
    } else if ((event.key === ' ' || event.key.toLowerCase() === 'p') && event.target === root) {
      event.preventDefault(); paused = !paused; schedule(); announcement.textContent = paused ? 'Slideshow paused.' : 'Slideshow will resume when focus leaves the gallery.';
    }
  });
  root.addEventListener('focusin', event => { focused = event.target.matches(':focus-visible'); schedule(); });
  root.addEventListener('focusout', event => { if (!root.contains(event.relatedTarget)) { focused = false; schedule(); } });
  root.addEventListener('mouseenter', () => { hovered = true; schedule(); });
  root.addEventListener('mouseleave', () => { hovered = false; schedule(); });
  root.addEventListener('pointerdown', event => {
    if (event.isPrimary === false || (event.button !== undefined && event.button !== 0) || event.target.closest('button')) return;
    pointer = { id: event.pointerId, x: event.clientX, y: event.clientY }; schedule();
  });
  // pan-y preserves vertical scrolling; never preventDefault on touch/pointer movement.
  root.addEventListener('pointerup', event => {
    if (!pointer || pointer.id !== event.pointerId) return;
    const direction = swipeDirection(pointer, { x: event.clientX, y: event.clientY }); pointer = null;
    if (direction) move(direction, true);
    schedule();
  });
  root.addEventListener('pointercancel', () => { pointer = null; schedule(); });
  root.addEventListener('pointerleave', event => { if (event.pointerType === 'mouse') { pointer = null; schedule(); } });
  doc.addEventListener('visibilitychange', schedule);
  motion.addEventListener('change', () => { paused = motion.matches; schedule(); });
  root.querySelector('[data-gallery-controls]').hidden = false; schedule();
}
