(() => {
  'use strict';

  const SITE_KEY = 'thirst-trap-772';
  const API = `/web/api/microsite/${SITE_KEY}`;
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let liveSite = null;

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
      const numericPrice = Number.isInteger(product.price_cents) ? `$${(product.price_cents / 100).toFixed(2)}` : '';
      const price = el('b', 'price-label', product.price_label || numericPrice || (product.status === 'sold_out' ? 'SOLD OUT FOR NOW' : 'CURRENT LINEUP AT THE TENT'));
      card.append(price);
      return card;
    }));
    watchReveals(grid);
  }

  function money(cents) {
    return Number.isInteger(cents) ? new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(cents / 100) : 'Confirmed after review';
  }

  function updatePreorderTotal() {
    const output = document.querySelector('#preorder-total');
    if (!output || !liveSite) return;
    let total = 0;
    let selected = 0;
    let known = true;
    document.querySelectorAll('#preorder-items [data-product-id]').forEach((row) => {
      const checkbox = row.querySelector('input[type="checkbox"]');
      const quantity = row.querySelector('input[type="number"]');
      if (!checkbox.checked) return;
      const count = Math.max(1, Math.min(20, Number(quantity.value) || 1));
      selected += count;
      const cents = Number(row.dataset.priceCents);
      if (!Number.isInteger(cents) || cents < 0) known = false;
      else total += cents * count;
    });
    output.textContent = selected === 0 ? 'Select items' : known ? money(total) : 'Confirmed after review';
  }

  function renderPreorders(site) {
    const form = document.querySelector('#preorder-form');
    const unavailable = document.querySelector('#preorder-unavailable');
    const root = document.querySelector('#preorder-items');
    const pickup = document.querySelector('#preorder-pickup');
    if (!form || !unavailable || !root || !pickup) return;
    const products = Array.isArray(site.products) ? site.products.filter((product) => product.status === 'active') : [];
    const enabled = Boolean(site.payments && site.payments.preorders_enabled && products.length);
    form.hidden = !enabled;
    unavailable.hidden = enabled;
    if (!enabled) return;
    root.replaceChildren(...products.map((product) => {
      const row = el('label', 'preorder-item');
      row.dataset.productId = product.id;
      if (Number.isInteger(product.price_cents)) row.dataset.priceCents = String(product.price_cents);
      const choose = document.createElement('input');
      choose.type = 'checkbox'; choose.name = `choose-${product.id}`;
      const copy = el('span', 'preorder-item-copy');
      copy.append(el('small', '', product.kicker || 'CURRENT DROP'), el('strong', '', product.name), el('em', '', product.price_label || (Number.isInteger(product.price_cents) ? money(product.price_cents) : 'Price confirmed after review')));
      const quantity = document.createElement('input');
      quantity.type = 'number'; quantity.min = '1'; quantity.max = '20'; quantity.step = '1'; quantity.value = '1'; quantity.inputMode = 'numeric'; quantity.setAttribute('aria-label', `Quantity for ${product.name}`); quantity.disabled = true;
      choose.addEventListener('change', () => { quantity.disabled = !choose.checked; updatePreorderTotal(); });
      quantity.addEventListener('input', updatePreorderTotal);
      row.append(choose, copy, quantity);
      return row;
    }));
    const options = [new Option('Coordinate pickup directly', 'coordinate')];
    (Array.isArray(site.events) ? site.events : []).filter((event) => event.status === 'scheduled').forEach((event) => {
      options.push(new Option([event.title, event.date_label, event.location].filter(Boolean).join(' · '), event.id));
    });
    pickup.replaceChildren(...options);
    updatePreorderTotal();
  }

  function showPreorderConfirmation(payload) {
    const form = document.querySelector('#preorder-form');
    const confirmation = document.querySelector('#preorder-confirmation');
    const order = payload.order || {};
    const payment = payload.payment || {};
    form.hidden = true;
    confirmation.hidden = false;
    document.querySelector('#preorder-reference').textContent = `Reference ${order.reference || 'saved'} · ${money(order.total_cents)} · ${order.pickup_label || 'Pickup coordinated directly'}`;
    document.querySelector('#preorder-payment-copy').textContent = payment.available
      ? (payment.instructions || 'Use the Cash App link below, include the order reference, and wait for owner confirmation.')
      : 'Your preorder is saved. Thirst Trap 772 will confirm availability, exact price, pickup, and payment instructions directly.';
    const panel = document.querySelector('#cash-app-panel');
    const url = typeof payment.url === 'string' && /^https:\/\/cash\.app\//i.test(payment.url) ? payment.url : '';
    panel.hidden = !payment.available || !url;
    if (!panel.hidden) {
      document.querySelector('#cash-app-link').href = url;
      document.querySelector('#cash-app-label').textContent = payment.label || 'CASH APP';
      const qrRoot = document.querySelector('#cash-app-qr');
      qrRoot.replaceChildren();
      if (typeof window.qrcode === 'function') {
        const qr = window.qrcode(0, 'M');
        qr.addData(url);
        qr.make();
        qrRoot.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 0, scalable: true });
      }
    }
    confirmation.focus();
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
      liveSite = site;
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
      renderPreorders(site);
    } catch (_) {
      // The static, truthful fallback remains visible when the API is offline.
    }
  }

  async function submitPreorder(form) {
    const status = form.querySelector('.form-status');
    if (!form.reportValidity()) return;
    const items = [...form.querySelectorAll('#preorder-items [data-product-id]')].flatMap((row) => {
      const checkbox = row.querySelector('input[type="checkbox"]');
      if (!checkbox.checked) return [];
      return [{ product_id: row.dataset.productId, quantity: Number(row.querySelector('input[type="number"]').value) || 1 }];
    });
    if (!items.length) {
      status.className = 'form-status is-error full';
      status.textContent = 'Choose at least one item before saving the preorder.';
      return;
    }
    const button = form.querySelector('button[type="submit"]');
    const data = Object.fromEntries(new FormData(form).entries());
    data.items = items;
    data.source = 'thirst-trap-v2-preorder';
    status.className = 'form-status full'; status.textContent = 'Saving your preorder…'; button.disabled = true;
    try {
      const response = await fetch(`${API}/preorder`, { method: 'POST', credentials: 'omit', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(data) });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok) throw new Error(payload.error || 'preorder_failed');
      showPreorderConfirmation(payload);
    } catch (_) {
      status.className = 'form-status is-error full';
      status.textContent = 'The preorder did not save yet. Please try again or use the official social links.';
    } finally {
      button.disabled = false;
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

  const preorder = document.querySelector('#preorder-form');
  if (preorder) preorder.addEventListener('submit', (event) => { event.preventDefault(); submitPreorder(preorder); });
  const another = document.querySelector('#another-preorder');
  if (another) another.addEventListener('click', () => {
    document.querySelector('#preorder-confirmation').hidden = true;
    preorder.reset();
    preorder.querySelectorAll('input[type="number"]').forEach((input) => { input.disabled = true; input.value = '1'; });
    preorder.hidden = false;
    updatePreorderTotal();
    preorder.querySelector('input[name="name"]').focus();
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
