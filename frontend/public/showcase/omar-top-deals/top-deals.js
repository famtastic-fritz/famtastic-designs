(() => {
  'use strict';

  const STORAGE_KEY = 'famtastic.omar-top-deals.v1';
  const PUBLIC_URL = 'https://famtasticdesigns.com/showcase/omar-top-deals/';
  const defaults = Object.freeze({
    items: [
      { id: 'headwear', category: 'headwear', title: 'Statement Headwear', description: 'Color, pattern, and personality customers can see before they reach the table.', status: 'Fresh finds', value: '$25', valueNote: 'each · 2 for $40', visible: true },
      { id: 'culture', category: 'culture', title: 'Culture + Gift Finds', description: 'Conversation-starting pieces selected for events, gifting, and the people who want something different.', status: 'Ask Omar', value: '$20+', valueNote: 'assortment changes', visible: true },
      { id: 'event', category: 'event', title: 'Event-Day Specials', description: 'A rotating category for limited offers Omar chooses for a specific market or festival.', status: 'Pop-up only', value: '$25+', valueNote: 'Omar confirms the piece', visible: true },
      { id: 'surprise', category: 'culture', title: 'The Surprise Table', description: 'The unexpected deal that rewards people who stop, look, and talk with Omar.', status: 'Changes often', value: '$30', valueNote: 'size and stock unconfirmed', visible: true }
    ],
    event: { title: 'THE NEXT\nPOP-UP', location: 'Location announced by Omar', date: 'Coming soon', status: 'planning', mapLink: '', note: '' },
    links: { socialLink: '', paymentLink: '', paymentLabel: "Use Omar's approved payment", replyEmail: '' },
    holds: [
      { id: 'sample-hold-1', type: 'hold', name: 'Sample customer', reply: 'Demo contact', item: 'Statement Headwear', note: 'Looking for a bold color.', updates: false, status: 'open', createdAt: 'Prototype sample' },
      { id: 'sample-hold-2', type: 'hold', name: 'Sample customer', reply: 'Demo contact', item: 'Culture + Gift Finds', note: 'Gift idea for an upcoming celebration.', updates: true, status: 'open', createdAt: 'Prototype sample' },
      { id: 'sample-question-1', type: 'question', name: 'Sample visitor', reply: 'Demo contact', item: 'General question', note: 'Where will the next table be?', updates: false, status: 'open', createdAt: 'Prototype sample' }
    ]
  });

  const cloneDefaults = () => JSON.parse(JSON.stringify(defaults));
  const safeText = (value) => String(value ?? '').trim().slice(0, 500);
  const escapeHtml = (value) => safeText(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

  function safeUrl(value) {
    const raw = safeText(value);
    if (!raw) return '';
    try {
      const parsed = new URL(raw);
      return ['https:', 'http:'].includes(parsed.protocol) ? parsed.href : '';
    } catch {
      return '';
    }
  }

  function loadState() {
    try {
      const parsed = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
      if (!parsed || !Array.isArray(parsed.items) || !Array.isArray(parsed.holds)) return cloneDefaults();
      const normalizedItems = parsed.items.map((item) => ({ ...(defaults.items.find((candidate) => candidate.id === item.id) || {}), ...item }));
      return { ...cloneDefaults(), ...parsed, items: normalizedItems, event: { ...defaults.event, ...(parsed.event || {}) }, links: { ...defaults.links, ...(parsed.links || {}) } };
    } catch {
      return cloneDefaults();
    }
  }

  let state = loadState();
  let toastTimer;

  function saveState(message = '') {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    if (message) toast(message);
  }

  function toast(message) {
    const node = document.getElementById('toast');
    if (!node) return;
    node.textContent = message;
    node.classList.add('visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => node.classList.remove('visible'), 3600);
  }

  function recordFromForm(form, type) {
    const data = new FormData(form);
    return {
      id: `demo-${type}-${Date.now()}`,
      type,
      name: safeText(data.get('name')).slice(0, 50),
      reply: safeText(data.get('reply')).slice(0, 100),
      item: type === 'hold' ? safeText(data.get('item')).slice(0, 80) : 'General question',
      note: safeText(type === 'hold' ? data.get('note') : data.get('message')).slice(0, 240),
      updates: data.get('updates') === 'on',
      status: 'open',
      createdAt: new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date())
    };
  }

  function renderEvent() {
    const event = state.event;
    const title = document.getElementById('event-title');
    const location = document.getElementById('event-location');
    const date = document.getElementById('event-date');
    const status = document.getElementById('event-status-label');
    const map = document.getElementById('event-map-link');
    const dashTitle = document.getElementById('dashboard-event-title');
    const dashPlace = document.getElementById('dashboard-event-place');
    const publicStatus = document.getElementById('public-event-status');
    const labels = { planning: 'Event details being confirmed', confirmed: 'Event confirmed by Omar', today: 'Omar is popping up today', complete: 'This event is complete' };
    if (title) title.innerHTML = escapeHtml(event.title || defaults.event.title).replaceAll('\n', '<br>');
    if (location) location.textContent = event.location || defaults.event.location;
    if (date) date.textContent = event.date || defaults.event.date;
    if (status) status.textContent = labels[event.status] || labels.planning;
    if (publicStatus) publicStatus.textContent = String(event.status || 'planning').toUpperCase();
    if (dashTitle) dashTitle.textContent = (event.title || 'Details in progress').replaceAll('\n', ' ');
    if (dashPlace) dashPlace.textContent = event.location || defaults.event.location;
    if (map) {
      const href = safeUrl(event.mapLink);
      map.classList.toggle('hidden', !href);
      if (href) map.href = href;
    }
  }

  function renderPublic() {
    const grid = document.getElementById('deal-grid');
    if (!grid) return;
    const active = document.querySelector('[data-filter].active')?.dataset.filter || 'all';
    const visible = state.items.filter((item) => item.visible && (active === 'all' || item.category === active));
    grid.innerHTML = visible.length ? visible.map((item, index) => `
      <article class="deal-card" data-category="${escapeHtml(item.category)}">
        <span class="deal-number">0${index + 1}</span>
        <h3>${escapeHtml(item.title)}</h3>
        <p>${escapeHtml(item.description)}</p>
        <div class="deal-meta"><span>${escapeHtml(item.status)}</span><span>Demo category</span></div>
      </article>`).join('') : '<p class="deal-card">No featured categories match this view yet.</p>';

    const select = document.getElementById('hold-item');
    if (select) select.innerHTML = state.items.filter((item) => item.visible).map((item) => `<option>${escapeHtml(item.title)}</option>`).join('');
    const openHolds = state.holds.filter((item) => item.type === 'hold' && item.status === 'open').length;
    const publicOpen = document.getElementById('public-open-holds');
    const publicItems = document.getElementById('public-live-items');
    if (publicOpen) publicOpen.textContent = String(openHolds);
    if (publicItems) publicItems.textContent = String(state.items.filter((item) => item.visible).length);
    renderEvent();
  }

  function initPublic() {
    renderPublic();
    document.querySelectorAll('[data-filter]').forEach((button) => button.addEventListener('click', () => {
      document.querySelectorAll('[data-filter]').forEach((item) => item.classList.remove('active'));
      button.classList.add('active');
      renderPublic();
    }));

    const dialog = document.getElementById('hold-dialog');
    document.querySelectorAll('[data-open-hold]').forEach((button) => button.addEventListener('click', () => dialog?.showModal()));
    document.querySelector('[data-close-hold]')?.addEventListener('click', () => dialog?.close());
    dialog?.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });

    document.getElementById('hold-form')?.addEventListener('submit', (event) => {
      event.preventDefault();
      if (!event.currentTarget.reportValidity()) return;
      state.holds.unshift(recordFromForm(event.currentTarget, 'hold'));
      saveState('Demo hold saved to Omar’s control desk on this device. Nothing was sent.');
      event.currentTarget.reset();
      dialog?.close();
      renderPublic();
    });

    document.getElementById('contact-form')?.addEventListener('submit', (event) => {
      event.preventDefault();
      if (!event.currentTarget.reportValidity()) return;
      state.holds.unshift(recordFromForm(event.currentTarget, 'question'));
      saveState('Demo question saved on this device. Nothing was emailed or texted.');
      event.currentTarget.reset();
      renderPublic();
    });

    document.getElementById('motion-toggle')?.addEventListener('click', (event) => {
      const paused = document.body.classList.toggle('motion-paused');
      event.currentTarget.setAttribute('aria-pressed', String(paused));
      event.currentTarget.textContent = paused ? 'Play motion' : 'Pause motion';
    });
  }

  function holdMarkup(record) {
    const label = record.type === 'hold' ? record.item : 'Question';
    return `<article class="hold-row ${record.status === 'done' ? 'done' : ''}" data-record-id="${escapeHtml(record.id)}">
      <div><h3>${escapeHtml(record.name)} · ${escapeHtml(label)}</h3><p>${escapeHtml(record.note || 'No note')} · ${escapeHtml(record.createdAt)}${record.updates ? ' · opted into demo updates' : ''}</p></div>
      <button type="button" data-toggle-record="${escapeHtml(record.id)}">${record.status === 'done' ? 'Reopen' : 'Mark handled'}</button>
    </article>`;
  }

  function renderOwner() {
    const openHolds = state.holds.filter((item) => item.type === 'hold' && item.status === 'open');
    const openQuestions = state.holds.filter((item) => item.type === 'question' && item.status === 'open');
    const set = (id, value) => { const node = document.getElementById(id); if (node) node.textContent = String(value); };
    set('stat-holds', openHolds.length);
    set('stat-questions', openQuestions.length);
    set('stat-items', state.items.filter((item) => item.visible).length);

    const today = document.getElementById('today-holds');
    const all = document.getElementById('owner-hold-list');
    if (today) today.innerHTML = state.holds.filter((item) => item.status === 'open').slice(0, 4).map(holdMarkup).join('') || '<p>No open demo requests.</p>';
    if (all) all.innerHTML = state.holds.map(holdMarkup).join('') || '<p>No prototype requests on this device.</p>';

    const itemsForm = document.getElementById('items-form');
    if (itemsForm) itemsForm.innerHTML = state.items.map((item, index) => `
      <div class="item-setting" data-item-id="${escapeHtml(item.id)}">
        <b>0${index + 1}</b>
        <label>Category title<input name="title" maxlength="50" value="${escapeHtml(item.title)}"></label>
        <label>Public description<input name="description" maxlength="150" value="${escapeHtml(item.description)}"></label>
        <label>Demo value<input name="value" maxlength="16" value="${escapeHtml(item.value || '')}" placeholder="$25"></label>
        <label>Value note<input name="valueNote" maxlength="42" value="${escapeHtml(item.valueNote || '')}" placeholder="each · 2 for $40"></label>
        <label>Status<select name="status">${['Fresh finds','Ask Omar','Pop-up only','Changes often','Limited','Sold / paused'].map((status) => `<option ${status === item.status ? 'selected' : ''}>${status}</option>`).join('')}</select></label>
        <label class="visible-toggle"><input name="visible" type="checkbox" ${item.visible ? 'checked' : ''}> Visible</label>
      </div>`).join('');

    const eventForm = document.getElementById('event-form');
    if (eventForm) {
      for (const [key, value] of Object.entries(state.event)) {
        const field = eventForm.elements.namedItem(key);
        if (field) field.value = value;
      }
    }
    const linksForm = document.getElementById('links-form');
    if (linksForm) {
      for (const [key, value] of Object.entries(state.links)) {
        const field = linksForm.elements.namedItem(key);
        if (field) field.value = value;
      }
    }
    renderEvent();
  }

  function activateTab(name) {
    document.querySelectorAll('[data-owner-tab]').forEach((button) => button.classList.toggle('active', button.dataset.ownerTab === name));
    document.querySelectorAll('[data-owner-panel]').forEach((panel) => panel.classList.toggle('active', panel.dataset.ownerPanel === name));
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function initOwner() {
    renderOwner();
    document.querySelectorAll('[data-owner-tab]').forEach((button) => button.addEventListener('click', () => activateTab(button.dataset.ownerTab)));
    document.querySelectorAll('[data-jump-tab]').forEach((button) => button.addEventListener('click', () => activateTab(button.dataset.jumpTab)));
    document.addEventListener('click', (event) => {
      const button = event.target.closest('[data-toggle-record]');
      if (!button) return;
      const record = state.holds.find((item) => item.id === button.dataset.toggleRecord);
      if (!record) return;
      record.status = record.status === 'done' ? 'open' : 'done';
      saveState('Prototype request status changed on this device only.');
      renderOwner();
    });

    document.getElementById('save-items')?.addEventListener('click', () => {
      document.querySelectorAll('.item-setting').forEach((row) => {
        const item = state.items.find((candidate) => candidate.id === row.dataset.itemId);
        if (!item) return;
        item.title = safeText(row.querySelector('[name="title"]').value).slice(0, 50) || item.title;
        item.description = safeText(row.querySelector('[name="description"]').value).slice(0, 150) || item.description;
        item.status = safeText(row.querySelector('[name="status"]').value).slice(0, 32);
        item.value = safeText(row.querySelector('[name="value"]').value).slice(0, 16);
        item.valueNote = safeText(row.querySelector('[name="valueNote"]').value).slice(0, 42);
        item.visible = row.querySelector('[name="visible"]').checked;
      });
      saveState('Table changes saved on this device. Open the public view to see them.');
      renderOwner();
    });

    document.getElementById('event-form')?.addEventListener('submit', (event) => {
      event.preventDefault();
      const data = new FormData(event.currentTarget);
      state.event = {
        title: safeText(data.get('title')).slice(0, 70) || defaults.event.title,
        location: safeText(data.get('location')).slice(0, 100) || defaults.event.location,
        date: safeText(data.get('date')).slice(0, 80) || defaults.event.date,
        status: ['planning','confirmed','today','complete'].includes(data.get('status')) ? data.get('status') : 'planning',
        mapLink: safeUrl(data.get('mapLink')),
        note: safeText(data.get('note')).slice(0, 220)
      };
      saveState('Next pop-up updated on this device only.');
      renderOwner();
    });

    document.getElementById('links-form')?.addEventListener('submit', (event) => {
      event.preventDefault();
      const data = new FormData(event.currentTarget);
      state.links = {
        socialLink: safeUrl(data.get('socialLink')),
        paymentLink: safeUrl(data.get('paymentLink')),
        paymentLabel: safeText(data.get('paymentLabel')).slice(0, 32),
        replyEmail: safeText(data.get('replyEmail')).slice(0, 100)
      };
      saveState('Front-door choices saved locally. No account, payment, or inbox was connected.');
      renderOwner();
    });

    document.getElementById('copy-public-link')?.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(PUBLIC_URL);
        toast('Public showcase link copied.');
      } catch {
        toast(PUBLIC_URL);
      }
    });

    document.querySelectorAll('[data-copy-caption]').forEach((button) => button.addEventListener('click', async () => {
      const draft = document.getElementById(button.dataset.copyCaption)?.textContent?.trim() || '';
      if (!draft) return;
      try {
        await navigator.clipboard.writeText(draft);
        toast('Draft caption copied. Review every fact and link before posting.');
      } catch {
        toast('Copy was blocked by this browser. The draft remains visible above.');
      }
    }));

    document.getElementById('reset-demo')?.addEventListener('click', () => {
      state = cloneDefaults();
      saveState('Sample data restored. No external system changed.');
      renderOwner();
    });
  }

  window.addEventListener('storage', (event) => {
    if (event.key !== STORAGE_KEY) return;
    state = loadState();
    document.body.dataset.view === 'owner' ? renderOwner() : renderPublic();
  });

  if (document.body.dataset.view === 'owner') initOwner(); else initPublic();
})();
