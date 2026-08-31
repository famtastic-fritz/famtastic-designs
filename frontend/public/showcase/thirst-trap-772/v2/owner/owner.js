(() => {
  'use strict';
  const API = '/web/api/microsite/thirst-trap-772/owner';
  let state = null;

  const qs = (selector, scope = document) => scope.querySelector(selector);
  const escapeText = (value) => String(value == null ? '' : value);
  const make = (tag, className, text) => { const node = document.createElement(tag); if (className) node.className = className; if (text !== undefined) node.textContent = text; return node; };

  function field(label, name, value, type = 'text', className = '') {
    const wrap = make('label', className, label);
    const control = type === 'textarea' ? document.createElement('textarea') : type === 'select' ? document.createElement('select') : document.createElement('input');
    control.name = name;
    if (type === 'textarea') { control.rows = 3; control.maxLength = 280; control.value = escapeText(value); }
    else if (type === 'select') {
      const options = name.includes('product-status') ? [['active','Active'],['sold_out','Sold out'],['hidden','Hidden']] : name.includes('event-status') ? [['scheduled','Scheduled'],['cancelled','Cancelled'],['hidden','Hidden']] : [['citrus','Citrus'],['berry','Berry'],['tropical','Tropical'],['pink','Pink'],['lime','Lime'],['orange','Orange']];
      options.forEach(([optionValue, optionLabel]) => { const option = new Option(optionLabel, optionValue, false, optionValue === value); control.add(option); });
    } else { control.type = type; control.value = escapeText(value); control.maxLength = 160; }
    wrap.append(control);
    return wrap;
  }

  function renderProducts() {
    const root = qs('#product-editor');
    root.replaceChildren();
    (state.site.products || []).forEach((item, index) => {
      const card = make('article', 'editor-card'); card.dataset.index = index;
      card.append(make('h3', '', `Product ${index + 1}`), field('Name', `product-name-${index}`, item.name), field('Short label', `product-kicker-${index}`, item.kicker), field('Price label', `product-price-${index}`, item.price_label), field('Description', `product-description-${index}`, item.description, 'textarea', 'description'), field('Status', `product-status-${index}`, item.status, 'select'), field('Color family', `product-visual-${index}`, item.visual, 'select'));
      const remove = make('button', 'remove', 'Remove'); remove.type = 'button'; remove.addEventListener('click', () => { state.site.products.splice(index, 1); renderProducts(); }); card.append(remove); root.append(card);
    });
    if (!state.site.products.length) root.append(make('p', 'empty', 'No products yet. Add the first menu item.'));
  }

  function renderEvents() {
    const root = qs('#event-editor'); root.replaceChildren();
    (state.site.events || []).forEach((item, index) => {
      const card = make('article', 'editor-card'); card.dataset.index = index;
      card.append(make('h3', '', `Event ${index + 1}`), field('Event name', `event-title-${index}`, item.title), field('Date + time label', `event-date-${index}`, item.date_label), field('Location', `event-location-${index}`, item.location), field('Details', `event-details-${index}`, item.details, 'textarea', 'description'), field('Status', `event-status-${index}`, item.status, 'select'));
      const remove = make('button', 'remove', 'Remove'); remove.type = 'button'; remove.addEventListener('click', () => { state.site.events.splice(index, 1); renderEvents(); }); card.append(remove); root.append(card);
    });
    if (!state.site.events.length) root.append(make('p', 'empty', 'No confirmed dates are public yet. Add one when it is real.'));
  }

  function renderMessages() {
    const root = qs('#message-list'); root.replaceChildren();
    if (!state.messages.length) { root.append(make('p', 'empty', 'No website messages yet.')); return; }
    state.messages.forEach((item) => {
      const card = make('article', 'message-card');
      const copy = document.createElement('div');
      copy.append(make('small', '', `${item.kind.toUpperCase()} · ${new Date(item.created * 1000).toLocaleString()} · ${item.status.toUpperCase()}`), make('h3', '', item.name || item.email), make('p', '', [item.email, item.phone].filter(Boolean).join(' · ')), make('p', '', item.message || (item.kind === 'subscriber' ? 'Consented mailing-list subscriber.' : '')));
      card.append(copy);
      if (item.status !== 'resolved' && item.status !== 'unsubscribed') { const button = make('button', '', 'Mark resolved'); button.type = 'button'; button.addEventListener('click', () => updateMessage(item.id, 'resolved', button)); card.append(button); }
      root.append(card);
    });
  }

  function renderStudio() {
    qs('#brand-name').value = state.site.brand.name || '';
    qs('#brand-tagline').value = state.site.brand.tagline || '';
    qs('#brand-area').value = state.site.brand.service_area || '';
    qs('#brand-intro').value = state.site.brand.intro || '';
    qs('#social-instagram').value = state.site.socials && state.site.socials.instagram || '';
    qs('#social-facebook').value = state.site.socials && state.site.socials.facebook || '';
    renderProducts(); renderEvents(); renderMessages();
    qs('#access-panel').hidden = true; qs('#studio').hidden = false; qs('#save-top').hidden = false;
  }

  function collect() {
    state.site.brand = { name: qs('#brand-name').value, tagline: qs('#brand-tagline').value, service_area: qs('#brand-area').value, intro: qs('#brand-intro').value };
    state.site.socials = { instagram: qs('#social-instagram').value, facebook: qs('#social-facebook').value };
    state.site.products = [...document.querySelectorAll('#product-editor .editor-card')].map((card, index) => ({ id: state.site.products[index] && state.site.products[index].id || `product-${Date.now()}-${index}`, name: qs(`[name="product-name-${index}"]`, card).value, kicker: qs(`[name="product-kicker-${index}"]`, card).value, price_label: qs(`[name="product-price-${index}"]`, card).value, description: qs(`[name="product-description-${index}"]`, card).value, status: qs(`[name="product-status-${index}"]`, card).value, visual: qs(`[name="product-visual-${index}"]`, card).value }));
    state.site.events = [...document.querySelectorAll('#event-editor .editor-card')].map((card, index) => ({ id: state.site.events[index] && state.site.events[index].id || `event-${Date.now()}-${index}`, title: qs(`[name="event-title-${index}"]`, card).value, date_label: qs(`[name="event-date-${index}"]`, card).value, location: qs(`[name="event-location-${index}"]`, card).value, details: qs(`[name="event-details-${index}"]`, card).value, status: qs(`[name="event-status-${index}"]`, card).value }));
    return state.site;
  }

  async function csrf() { const response = await fetch('/web/session/token', { credentials: 'same-origin' }); if (!response.ok) throw new Error('csrf'); return response.text(); }
  async function save() {
    const status = qs('#save-status'); status.className = ''; status.textContent = 'Saving…';
    try { const token = await csrf(); const response = await fetch(API, { method: 'PUT', credentials: 'same-origin', headers: { 'Content-Type':'application/json','X-CSRF-Token':token,Accept:'application/json' }, body: JSON.stringify(collect()) }); const payload = await response.json(); if (!response.ok || !payload.ok) throw new Error(payload.error || 'save'); state.site = payload.site; status.className = 'status-success'; status.textContent = 'Saved. The public site now reads this content.'; renderProducts(); renderEvents(); }
    catch (_) { status.className = 'status-error'; status.textContent = 'Save failed. Your account may need to be linked again; no public content was changed.'; }
  }
  async function updateMessage(id, status, button) { button.disabled = true; try { const token = await csrf(); const response = await fetch(`${API}/messages/${id}`, { method:'PATCH', credentials:'same-origin', headers:{'Content-Type':'application/json','X-CSRF-Token':token}, body:JSON.stringify({status}) }); if (!response.ok) throw new Error('update'); const item = state.messages.find((message) => message.id === id); if (item) item.status = status; renderMessages(); } catch (_) { button.disabled = false; button.textContent = 'Try again'; } }

  qs('#add-product').addEventListener('click', () => { if (state.site.products.length >= 24) return; state.site.products.push({id:`product-${Date.now()}`,name:'',kicker:'',description:'',price_label:'',status:'active',visual:'pink'}); renderProducts(); });
  qs('#add-event').addEventListener('click', () => { if (state.site.events.length >= 20) return; state.site.events.push({id:`event-${Date.now()}`,title:'',date_label:'',location:'',details:'',status:'scheduled'}); renderEvents(); });
  qs('#save-top').addEventListener('click', save); qs('#save-bottom').addEventListener('click', save);

  async function boot() {
    const login = qs('#login-link'); login.href = `/login?redirect=${encodeURIComponent(location.pathname)}`;
    try { const response = await fetch(API, { credentials:'same-origin', headers:{Accept:'application/json'} }); const payload = await response.json().catch(() => ({})); if (response.status === 401) { qs('#access-copy').textContent = 'Sign in with the verified FAMtastic account linked to this site.'; login.hidden = false; return; } if (response.status === 403) { qs('#access-copy').textContent = 'This account is signed in, but it has not been linked to Thirst Trap 772 yet. FAMtastic can complete the owner handoff after the business email is verified.'; return; } if (!response.ok || !payload.ok) throw new Error('load'); state = payload; renderStudio(); }
    catch (_) { qs('#access-copy').textContent = 'The owner studio could not connect. No public content was changed.'; }
  }
  boot();
})();
