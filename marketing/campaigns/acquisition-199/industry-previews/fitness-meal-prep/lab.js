'use strict';
// Browser-page practice only: no network, storage, account, provider or publication calls.
const byId = id => document.getElementById(id);
const defaults = {
  reply: 'Thanks for reaching out. I can share how this example service is structured and what a first conversation could cover.',
  status: 'Needs reply',
  instructions: 'Ask about the training format, what a first conversation covers and any practical next steps.',
  content: 'A fictional movement studio example, with a clear path to ask about personal training and a separate, optional meal-prep offering.'
};

byId('open-inquiry').addEventListener('click', () => {
  const detail = byId('inquiry-detail');
  detail.hidden = !detail.hidden;
  byId('open-inquiry').setAttribute('aria-expanded', String(!detail.hidden));
  byId('open-inquiry').innerHTML = detail.hidden ? 'Open inquiry details <span aria-hidden="true">＋</span>' : 'Close inquiry details <span aria-hidden="true">−</span>';
});

byId('save-reply').addEventListener('click', () => {
  const value = byId('reply').value.trim();
  byId('reply-notice').textContent = value ? 'Reply draft saved in this practice. Nothing sent.' : 'Write a reply before saving.';
});

byId('request-status').addEventListener('change', () => {
  byId('status-label').textContent = byId('request-status').value;
  byId('status-notice').textContent = 'Status updated in this practice only.';
});

byId('save-instructions').addEventListener('click', () => {
  byId('instructions-notice').textContent = byId('instructions').value.trim() ? 'Instructions saved in this practice. Nothing published.' : 'Add instructions before saving.';
});

byId('save-content').addEventListener('click', () => {
  const value = byId('page-content').value.trim();
  if (!value) {
    byId('content-notice').textContent = 'Add page copy before saving.';
    return;
  }
  byId('hero-copy').textContent = value;
  byId('content-notice').textContent = 'Page introduction updated in this practice. Nothing published.';
});

byId('reset-practice').addEventListener('click', () => {
  byId('reply').value = defaults.reply;
  byId('request-status').value = defaults.status;
  byId('status-label').textContent = defaults.status;
  byId('instructions').value = defaults.instructions;
  byId('page-content').value = defaults.content;
  byId('hero-copy').textContent = defaults.content;
  byId('inquiry-detail').hidden = true;
  byId('open-inquiry').setAttribute('aria-expanded', 'false');
  byId('open-inquiry').innerHTML = 'Open inquiry details <span aria-hidden="true">＋</span>';
  ['reply-notice', 'instructions-notice', 'content-notice'].forEach(id => { byId(id).textContent = ''; });
  byId('status-notice').textContent = 'Status is shown in this practice only.';
});
