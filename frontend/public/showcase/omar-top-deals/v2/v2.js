(() => {
  'use strict';

  const STORAGE_KEY = 'famtastic.omar-top-deals.v1';
  const fallback = {
    items: [
      { id: 'headwear', category: 'headwear', title: 'Statement Headwear', description: 'Color, pattern, and personality customers can see before they reach the table.', status: 'Fresh finds', value: '$25', valueNote: 'each · 2 for $40', visible: true },
      { id: 'culture', category: 'culture', title: 'Culture + Gift Finds', description: 'Conversation-starting pieces selected for events, gifting, and the people who want something different.', status: 'Ask Omar', value: '$20+', valueNote: 'assortment changes', visible: true },
      { id: 'event', category: 'event', title: 'Event-Day Specials', description: 'A rotating category for limited offers Omar chooses for a specific market or festival.', status: 'Pop-up only', value: '$25+', valueNote: 'Omar confirms the piece', visible: true },
      { id: 'surprise', category: 'culture', title: 'The Surprise Table', description: 'The unexpected deal that rewards people who stop, look, and talk with Omar.', status: 'Changes often', value: '$30', valueNote: 'size and stock unconfirmed', visible: true }
    ],
    holds: [],
    event: { title: 'THE NEXT\nPOP-UP', location: 'Location announced by Omar', date: 'Coming soon', status: 'planning', mapLink: '' },
    links: {}
  };

  const safeText = (value) => String(value ?? '').trim().slice(0, 500);
  const safeUrl = (value) => {
    try {
      const parsed = new URL(safeText(value));
      return ['https:', 'http:'].includes(parsed.protocol) ? parsed.href : '';
    } catch {
      return '';
    }
  };
  const loadState = () => {
    try {
      const parsed = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
      if (!parsed || !Array.isArray(parsed.holds)) return structuredClone(fallback);
      const items = fallback.items.map((item) => ({ ...item, ...(parsed.items.find((candidate) => candidate.id === item.id) || {}) }));
      return { ...structuredClone(fallback), ...parsed, items, event: { ...fallback.event, ...(parsed.event || {}) } };
    } catch {
      return structuredClone(fallback);
    }
  };

  let state = loadState();
  let toastTimer;
  const dialog = document.getElementById('hold-dialog');
  const toast = (message) => {
    const node = document.getElementById('toast');
    if (!node) return;
    node.textContent = message;
    node.classList.add('visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => node.classList.remove('visible'), 3600);
  };

  function renderEvent() {
    const event = state.event;
    const labels = { planning: 'Event details being confirmed', confirmed: 'Event confirmed by Omar', today: 'Omar is popping up today', complete: 'This event is complete' };
    const setText = (id, value) => { const node = document.getElementById(id); if (node) node.textContent = value; };
    const title = document.getElementById('event-title');
    if (title) title.innerHTML = safeText(event.title || fallback.event.title).replaceAll('\n', '<br>');
    setText('event-location', event.location || fallback.event.location);
    setText('event-date', event.date || fallback.event.date);
    setText('event-status-label', labels[event.status] || labels.planning);
    const map = document.getElementById('event-map-link');
    if (map) {
      const href = safeUrl(event.mapLink);
      map.classList.toggle('hidden', !href);
      if (href) map.href = href;
    }
  }

  function renderValues() {
    state.items.forEach((item) => {
      const value = document.querySelector(`[data-demo-value="${CSS.escape(item.id)}"]`);
      const note = document.querySelector(`[data-demo-note="${CSS.escape(item.id)}"]`);
      if (value && item.value) value.textContent = safeText(item.value);
      if (note && item.valueNote) note.textContent = safeText(item.valueNote);
    });
  }

  document.querySelectorAll('[data-open-hold]').forEach((button) => button.addEventListener('click', () => {
    const requested = safeText(button.dataset.productName);
    const select = document.getElementById('hold-item');
    if (requested && select && [...select.options].some((option) => option.value === requested)) select.value = requested;
    dialog?.showModal();
  }));
  document.querySelector('[data-close-hold]')?.addEventListener('click', () => dialog?.close());
  dialog?.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });

  document.getElementById('hold-form')?.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!event.currentTarget.reportValidity()) return;
    const data = new FormData(event.currentTarget);
    state.holds.unshift({
      id: `demo-hold-${Date.now()}`,
      type: 'hold',
      name: safeText(data.get('name')).slice(0, 50),
      reply: safeText(data.get('reply')).slice(0, 100),
      item: safeText(data.get('item')).slice(0, 80),
      note: safeText(data.get('note')).slice(0, 200),
      updates: data.get('updates') === 'on',
      status: 'open',
      createdAt: new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date())
    });
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    event.currentTarget.reset();
    dialog?.close();
    toast('Demo hold saved to Omar’s owner desk on this device. Nothing was sent or charged.');
  });

  document.getElementById('motion-toggle')?.addEventListener('click', (event) => {
    const paused = document.body.classList.toggle('motion-paused');
    event.currentTarget.setAttribute('aria-pressed', String(paused));
    event.currentTarget.textContent = paused ? 'Play motion' : 'Pause motion';
  });

  window.addEventListener('storage', (event) => {
    if (event.key !== STORAGE_KEY) return;
    state = loadState();
    renderEvent();
    renderValues();
  });

  renderEvent();
  renderValues();
})();
