(() => {
  'use strict';

  const API = '/web/api/microsite/thirst-trap-772/owner';
  let state = null;
  const qs = (selector, scope = document) => scope.querySelector(selector);
  const value = (input) => String(input == null ? '' : input);
  const make = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  };

  function field(label, name, current, type = 'text', className = '') {
    const wrap = make('label', className, label);
    const control = type === 'textarea' ? document.createElement('textarea') : type === 'select' ? document.createElement('select') : document.createElement('input');
    control.name = name;
    if (type === 'textarea') {
      control.rows = 3; control.maxLength = 280; control.value = value(current);
    } else if (type === 'select') {
      const options = name.includes('product-status') ? [['active','Active'],['sold_out','Sold out'],['hidden','Hidden']] : name.includes('event-status') ? [['scheduled','Scheduled'],['cancelled','Cancelled'],['hidden','Hidden']] : [['citrus','Citrus'],['berry','Berry'],['tropical','Tropical'],['pink','Pink'],['lime','Lime'],['orange','Orange']];
      options.forEach(([optionValue, optionLabel]) => control.add(new Option(optionLabel, optionValue, false, optionValue === current)));
    } else {
      control.type = type; control.value = value(current); control.maxLength = 160;
      if (type === 'number') { control.min = '0'; control.max = '100000'; control.step = '.01'; control.inputMode = 'decimal'; }
    }
    wrap.append(control);
    return wrap;
  }

  function renderProducts() {
    const root = qs('#product-editor');
    root.replaceChildren();
    (state.site.products || []).forEach((item, index) => {
      const card = make('article', 'editor-card'); card.dataset.index = index;
      const price = Number.isInteger(item.price_cents) ? (item.price_cents / 100).toFixed(2) : '';
      card.append(
        make('h3', '', `Product ${index + 1}`),
        field('Name', `product-name-${index}`, item.name),
        field('Short label', `product-kicker-${index}`, item.kicker),
        field('Display price label', `product-price-${index}`, item.price_label),
        field('Preorder price in USD', `product-price-amount-${index}`, price, 'number'),
        field('Description', `product-description-${index}`, item.description, 'textarea', 'description'),
        field('Status', `product-status-${index}`, item.status, 'select'),
        field('Color family', `product-visual-${index}`, item.visual, 'select'),
      );
      card.append(make('small', 'field-note', 'The numeric preorder price calculates the customer total. Leave blank when the price must be confirmed manually.'));
      const remove = make('button', 'remove', 'Remove'); remove.type = 'button';
      remove.addEventListener('click', () => { state.site.products.splice(index, 1); renderProducts(); });
      card.append(remove); root.append(card);
    });
    if (!state.site.products.length) root.append(make('p', 'empty', 'No products yet. Add the first menu item.'));
  }

  function renderEvents() {
    const root = qs('#event-editor'); root.replaceChildren();
    (state.site.events || []).forEach((item, index) => {
      const card = make('article', 'editor-card'); card.dataset.index = index;
      card.append(make('h3', '', `Event ${index + 1}`), field('Event name', `event-title-${index}`, item.title), field('Date + time label', `event-date-${index}`, item.date_label), field('Location', `event-location-${index}`, item.location), field('Details', `event-details-${index}`, item.details, 'textarea', 'description'), field('Status', `event-status-${index}`, item.status, 'select'));
      const remove = make('button', 'remove', 'Remove'); remove.type = 'button';
      remove.addEventListener('click', () => { state.site.events.splice(index, 1); renderEvents(); });
      card.append(remove); root.append(card);
    });
    if (!state.site.events.length) root.append(make('p', 'empty', 'No confirmed dates are public yet. Add one when it is real.'));
  }

  function money(cents) {
    return Number.isInteger(cents) ? new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(cents / 100) : 'Price to confirm';
  }

  function selectControl(options, current, label) {
    const wrap = make('label', 'order-control', label);
    const select = document.createElement('select');
    options.forEach(([optionValue, optionLabel]) => select.add(new Option(optionLabel, optionValue, false, optionValue === current)));
    wrap.append(select);
    return { wrap, select };
  }

  function renderOrders() {
    const root = qs('#order-list'); root.replaceChildren();
    if (!state.orders || !state.orders.length) { root.append(make('p', 'empty', 'No preorder requests yet.')); return; }
    state.orders.forEach((order) => {
      const card = make('article', 'message-card order-card');
      const copy = document.createElement('div');
      copy.append(
        make('small', '', `${order.order_number} · ${new Date(order.created * 1000).toLocaleString()}`),
        make('h3', '', `${order.customer_name} · ${money(order.total_cents)}`),
        make('p', '', [order.email, order.phone, order.pickup_label].filter(Boolean).join(' · ')),
      );
      const lines = make('ul', 'order-lines');
      (order.items || []).forEach((item) => lines.append(make('li', '', `${item.quantity} × ${item.name}${Number.isInteger(item.unit_price_cents) ? ` · ${money(item.unit_price_cents)} each` : ''}`)));
      copy.append(lines);
      if (order.notes) copy.append(make('p', 'order-note', order.notes));
      const controls = make('div', 'order-controls');
      const fulfillment = selectControl([['requested','Requested'],['confirmed','Confirmed'],['ready','Ready for pickup'],['completed','Completed'],['cancelled','Cancelled']], order.order_status, 'Order status');
      const payment = selectControl([['unverified','Payment unverified'],['confirmed','Payment confirmed'],['refunded','Refunded'],['not_required','No payment required']], order.payment_status, 'Payment status');
      const save = make('button', '', 'Save order status'); save.type = 'button';
      save.addEventListener('click', () => updateOrder(order.id, fulfillment.select.value, payment.select.value, save));
      controls.append(fulfillment.wrap, payment.wrap, save);
      card.append(copy, controls); root.append(card);
    });
  }

  function renderMessages() {
    const root = qs('#message-list'); root.replaceChildren();
    if (!state.messages.length) { root.append(make('p', 'empty', 'No website messages yet.')); return; }
    state.messages.forEach((item) => {
      const card = make('article', 'message-card');
      const copy = document.createElement('div');
      copy.append(make('small', '', `${item.kind.toUpperCase()} · ${new Date(item.created * 1000).toLocaleString()} · ${item.status.toUpperCase()}`), make('h3', '', item.name || item.email), make('p', '', [item.email, item.phone].filter(Boolean).join(' · ')), make('p', '', item.message || (item.kind === 'subscriber' ? 'Consented mailing-list subscriber.' : '')));
      card.append(copy);
      if (item.status !== 'resolved' && item.status !== 'unsubscribed') {
        const button = make('button', '', 'Mark resolved'); button.type = 'button';
        button.addEventListener('click', () => updateMessage(item.id, 'resolved', button)); card.append(button);
      }
      root.append(card);
    });
  }

  function renderStudio() {
    const payments = state.site.payments || {};
    qs('#brand-name').value = state.site.brand.name || '';
    qs('#brand-tagline').value = state.site.brand.tagline || '';
    qs('#brand-area').value = state.site.brand.service_area || '';
    qs('#brand-intro').value = state.site.brand.intro || '';
    qs('#social-instagram').value = state.site.socials && state.site.socials.instagram || '';
    qs('#social-facebook').value = state.site.socials && state.site.socials.facebook || '';
    qs('#preorders-enabled').checked = Boolean(payments.preorders_enabled);
    qs('#cash-app-url').value = payments.cash_app_url || '';
    qs('#cash-app-label').value = payments.cash_app_label || '';
    qs('#payment-note').value = payments.payment_note || '';
    qs('#pickup-note').value = payments.pickup_note || '';
    renderProducts(); renderEvents(); renderOrders(); renderMessages();
    qs('#access-panel').hidden = true; qs('#studio').hidden = false; qs('#save-top').hidden = false;
  }

  function centsFromInput(input) {
    const raw = input.value.trim();
    if (raw === '') return null;
    const amount = Number(raw);
    if (!Number.isFinite(amount) || amount < 0 || amount > 100000) throw new Error('price');
    return Math.round(amount * 100);
  }

  function collect() {
    state.site.brand = { name: qs('#brand-name').value, tagline: qs('#brand-tagline').value, service_area: qs('#brand-area').value, intro: qs('#brand-intro').value };
    state.site.socials = { instagram: qs('#social-instagram').value, facebook: qs('#social-facebook').value };
    state.site.payments = { preorders_enabled: qs('#preorders-enabled').checked, cash_app_url: qs('#cash-app-url').value, cash_app_label: qs('#cash-app-label').value, payment_note: qs('#payment-note').value, pickup_note: qs('#pickup-note').value };
    state.site.products = [...document.querySelectorAll('#product-editor .editor-card')].map((card, index) => ({
      id: state.site.products[index] && state.site.products[index].id || `product-${Date.now()}-${index}`,
      name: qs(`[name="product-name-${index}"]`, card).value,
      kicker: qs(`[name="product-kicker-${index}"]`, card).value,
      price_label: qs(`[name="product-price-${index}"]`, card).value,
      price_cents: centsFromInput(qs(`[name="product-price-amount-${index}"]`, card)),
      description: qs(`[name="product-description-${index}"]`, card).value,
      status: qs(`[name="product-status-${index}"]`, card).value,
      visual: qs(`[name="product-visual-${index}"]`, card).value,
    }));
    state.site.events = [...document.querySelectorAll('#event-editor .editor-card')].map((card, index) => ({ id: state.site.events[index] && state.site.events[index].id || `event-${Date.now()}-${index}`, title: qs(`[name="event-title-${index}"]`, card).value, date_label: qs(`[name="event-date-${index}"]`, card).value, location: qs(`[name="event-location-${index}"]`, card).value, details: qs(`[name="event-details-${index}"]`, card).value, status: qs(`[name="event-status-${index}"]`, card).value }));
    return state.site;
  }

  async function csrf() {
    const response = await fetch('/web/session/token', { credentials: 'same-origin' });
    if (!response.ok) throw new Error('csrf');
    return response.text();
  }

  async function save() {
    const status = qs('#save-status'); status.className = ''; status.textContent = 'Saving…';
    try {
      const content = collect();
      const token = await csrf();
      const response = await fetch(API, { method: 'PUT', credentials: 'same-origin', headers: { 'Content-Type':'application/json','X-CSRF-Token':token,Accept:'application/json' }, body: JSON.stringify(content) });
      const payload = await response.json();
      if (!response.ok || !payload.ok) throw new Error(payload.error || 'save');
      state.site = payload.site; status.className = 'status-success'; status.textContent = 'Saved. The public site now reads this content.'; renderProducts(); renderEvents();
    } catch (error) {
      status.className = 'status-error'; status.textContent = error.message === 'price' ? 'Check the numeric preorder prices. No changes were saved.' : 'Save failed. Check the Cash App link and owner connection; no public content was changed.';
    }
  }

  async function updateMessage(id, status, button) {
    button.disabled = true;
    try {
      const token = await csrf();
      const response = await fetch(`${API}/messages/${id}`, { method:'PATCH', credentials:'same-origin', headers:{'Content-Type':'application/json','X-CSRF-Token':token}, body:JSON.stringify({status}) });
      if (!response.ok) throw new Error('update');
      const item = state.messages.find((message) => message.id === id); if (item) item.status = status; renderMessages();
    } catch (_) { button.disabled = false; button.textContent = 'Try again'; }
  }

  async function updateOrder(id, orderStatus, paymentStatus, button) {
    button.disabled = true; button.textContent = 'Saving…';
    try {
      const token = await csrf();
      const response = await fetch(`${API}/orders/${id}`, { method:'PATCH', credentials:'same-origin', headers:{'Content-Type':'application/json','X-CSRF-Token':token}, body:JSON.stringify({order_status:orderStatus,payment_status:paymentStatus}) });
      if (!response.ok) throw new Error('update');
      const order = state.orders.find((item) => item.id === id); if (order) { order.order_status = orderStatus; order.payment_status = paymentStatus; }
      button.textContent = 'Saved'; setTimeout(() => { button.disabled = false; button.textContent = 'Save order status'; }, 900);
    } catch (_) { button.disabled = false; button.textContent = 'Try again'; }
  }

  qs('#add-product').addEventListener('click', () => { if (state.site.products.length >= 24) return; state.site.products.push({id:`product-${Date.now()}`,name:'',kicker:'',description:'',price_label:'',price_cents:null,status:'active',visual:'pink'}); renderProducts(); });
  qs('#add-event').addEventListener('click', () => { if (state.site.events.length >= 20) return; state.site.events.push({id:`event-${Date.now()}`,title:'',date_label:'',location:'',details:'',status:'scheduled'}); renderEvents(); });
  qs('#save-top').addEventListener('click', save); qs('#save-bottom').addEventListener('click', save);

  async function boot() {
    const login = qs('#login-link'); login.href = `/login?redirect=${encodeURIComponent(location.pathname)}`;
    try {
      const response = await fetch(API, { credentials:'same-origin', headers:{Accept:'application/json'} });
      const payload = await response.json().catch(() => ({}));
      if (response.status === 401) { qs('#access-copy').textContent = 'Sign in with the verified FAMtastic account linked to this site.'; login.hidden = false; return; }
      if (response.status === 403) { qs('#access-copy').textContent = 'This account is signed in, but it has not been linked to Thirst Trap 772 yet. FAMtastic can complete the owner handoff after the business email is verified.'; return; }
      if (!response.ok || !payload.ok) throw new Error('load');
      state = payload; state.orders = state.orders || []; renderStudio();
    } catch (_) { qs('#access-copy').textContent = 'The owner studio could not connect. No public content was changed.'; }
  }

  boot();
})();
