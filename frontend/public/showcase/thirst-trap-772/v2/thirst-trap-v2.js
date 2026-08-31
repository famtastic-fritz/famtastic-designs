(() => {
  'use strict';

  const SITE_KEY = 'thirst-trap-772';
  const API = `/web/api/microsite/${SITE_KEY}`;
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function watchReveals(scope = document) {
    const items = [...scope.querySelectorAll('.reveal:not(.reveal-ready)')];
    items.forEach((item) => item.classList.add('reveal-ready'));
    if (reduceMotion || !('IntersectionObserver' in window)) {
      items.forEach((item) => item.classList.add('in-view'));
      return;
    }
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('in-view');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: .12, rootMargin: '0px 0px -6% 0px' });
    items.forEach((item) => observer.observe(item));
  }

  function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function renderProducts(products) {
    const grid = document.querySelector('#product-grid');
    if (!grid || !Array.isArray(products) || products.length === 0) return;
    grid.replaceChildren(...products.map((product, index) => {
      const visual = ['citrus', 'berry', 'tropical', 'pink', 'lime', 'orange'].includes(product.visual) ? product.visual : 'pink';
      const card = el('article', `product-card ${visual} reveal`);
      card.append(el('span', 'number', String(index + 1).padStart(2, '0')));
      const crop = el('div', `product-crop crop-${visual}`);
      crop.setAttribute('aria-hidden', 'true');
      card.append(crop, el('small', '', product.kicker || 'CURRENT DROP'), el('h3', '', product.name || 'Featured item'), el('p', '', product.description || 'Ask at the tent for today’s details.'));
      const price = el('b', 'price-label', product.price_label || (product.status === 'sold_out' ? 'SOLD OUT FOR NOW' : 'CURRENT LINEUP AT THE TENT'));
      card.append(price);
      return card;
    }));
    watchReveals(grid);
  }

  function renderEvents(events) {
    const list = document.querySelector('#event-list');
    if (!list) return;
    const active = Array.isArray(events) ? events.filter((event) => event.status !== 'hidden' && event.status !== 'cancelled') : [];
    if (active.length === 0) return;
    list.replaceChildren(...active.map((event) => {
      const card = document.createElement('article');
      card.append(el('small', '', event.date_label || 'DATE COMING SOON'), el('h3', '', event.title || 'Next pop-up'), el('p', '', [event.location, event.details].filter(Boolean).join(' · ')));
      return card;
    }));
  }

  async function loadLiveContent() {
    try {
      const response = await fetch(API, { credentials: 'omit', headers: { Accept: 'application/json' } });
      if (!response.ok) return;
      const payload = await response.json();
      const site = payload && payload.site;
      if (!site) return;
      const intro = document.querySelector('[data-brand-intro]');
      if (intro && site.brand && site.brand.intro) intro.textContent = site.brand.intro;
      if (site.socials && typeof site.socials === 'object') {
        ['instagram', 'facebook'].forEach((network) => {
          const url = site.socials[network];
          if (!url || !/^https:\/\//i.test(url)) return;
          document.querySelectorAll(`[data-social="${network}"]`).forEach((link) => { link.href = url; });
        });
      }
      renderProducts(site.products);
      renderEvents(site.events);
    } catch (_) {
      // The static, truthful fallback remains visible when the API is offline.
    }
  }

  async function submitCapture(form, kind, successMessage) {
    const status = form.querySelector('.form-status');
    if (!form.reportValidity()) return;
    const button = form.querySelector('button[type="submit"]');
    const data = Object.fromEntries(new FormData(form).entries());
    data.consent = Boolean(form.elements.consent && form.elements.consent.checked);
    data.source = 'thirst-trap-v2';
    status.className = 'form-status';
    status.textContent = 'Sending…';
    button.disabled = true;
    try {
      const response = await fetch(`${API}/${kind}`, {
        method: 'POST',
        credentials: 'omit',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(data),
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok) throw new Error(payload.error || 'request_failed');
      form.reset();
      status.className = 'form-status is-success';
      status.textContent = successMessage;
    } catch (_) {
      status.className = 'form-status is-error';
      status.textContent = 'That did not go through yet. Please use the official Instagram or Facebook link while the form reconnects.';
    } finally {
      button.disabled = false;
    }
  }

  const contact = document.querySelector('#contact-form');
  if (contact) contact.addEventListener('submit', (event) => {
    event.preventDefault();
    submitCapture(contact, 'contact', 'Got it. Your message is saved for Thirst Trap 772 to review.');
  });

  const subscribe = document.querySelector('#subscribe-form');
  if (subscribe) subscribe.addEventListener('submit', (event) => {
    event.preventDefault();
    submitCapture(subscribe, 'subscriber', 'You’re on the pink list. Watch for the next confirmed drop.');
  });

  if (!reduceMotion) {
    let queued = false;
    window.addEventListener('scroll', () => {
      if (queued) return;
      queued = true;
      requestAnimationFrame(() => {
        const art = document.querySelector('.hero-art');
        if (art && window.scrollY < window.innerHeight) art.style.transform = `scale(1.04) translateY(${Math.min(window.scrollY * .035, 24)}px)`;
        queued = false;
      });
    }, { passive: true });
  }

  watchReveals();
  loadLiveContent();
})();
