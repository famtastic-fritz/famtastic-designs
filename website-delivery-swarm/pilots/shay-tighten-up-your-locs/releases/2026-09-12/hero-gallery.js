export function initHeroGallery(doc = document, win = window) {
  const root = doc.querySelector('[data-hero-gallery]');
  if (!root || root.dataset.ready) return;
  root.dataset.ready = 'true';
  const slides = [...root.querySelectorAll('[data-slide]')];
  const toggle = root.querySelector('[data-rotation]');
  const count = root.querySelector('[data-slide-count]');
  const motion = win.matchMedia('(prefers-reduced-motion: reduce)');
  let index = 0, paused = motion.matches, hovered = false, timer, pointerPaused;
  function schedule() {
    win.clearTimeout(timer);
    toggle.textContent = paused ? 'Play slideshow' : 'Pause slideshow';
    if (!paused && !hovered && !doc.hidden) timer = win.setTimeout(() => { move(1); schedule(); }, 6000);
  }
  function move(step) {
    for (let offset = 1; offset < slides.length; offset++) {
      const next = (index + step * offset + slides.length) % slides.length;
      const img = slides[next].querySelector('img');
      if (!img.complete || !img.naturalWidth) continue;
      slides[index].hidden = true;
      slides[next].hidden = false;
      index = next;
      count.textContent = `${index + 1} / ${slides.length}`;
      break;
    }
  }
  root.querySelector('[data-previous]').addEventListener('click', () => { paused = true; move(-1); schedule(); });
  root.querySelector('[data-next]').addEventListener('click', () => { paused = true; move(1); schedule(); });
  toggle.addEventListener('pointerdown', () => { pointerPaused = paused; });
  toggle.addEventListener('pointercancel', () => { pointerPaused = undefined; });
  toggle.addEventListener('click', () => { paused = !(pointerPaused ?? paused); pointerPaused = undefined; schedule(); });
  root.addEventListener('focusin', () => { paused = true; schedule(); });
  root.addEventListener('mouseenter', () => { hovered = true; schedule(); });
  root.addEventListener('mouseleave', () => { hovered = false; schedule(); });
  doc.addEventListener('visibilitychange', schedule);
  motion.addEventListener('change', () => { if (motion.matches) paused = true; schedule(); });
  root.querySelector('[data-gallery-controls]').hidden = false;
  schedule();
}
