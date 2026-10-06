'use strict';
// Fictional browser-page practice only; no network, storage, account, provider or publication calls.
const byId = id => document.getElementById(id);
const defaults = {
  reply: 'Thanks for reaching out. It helps to know what kind of coat care you have in mind and what makes an introduction comfortable for Juniper. We can talk through the service details together.',
  status: 'New inquiry',
  instructions: 'To help start the conversation, share the service you have in mind and any handling preferences you would like us to know. Please leave health and medication details out of this form.',
  content: 'A fictional pet-care example with clear service paths and thoughtful first questions for grooming and training inquiries.'
};
let replySaved = false;

function updateSummary() {
  const reply = byId('reply').value.trim() || 'No reply drafted yet.';
  const instructions = byId('instructions').value.trim() || 'No customer instructions added.';
  const content = byId('page-content').value.trim() || 'No page introduction added.';
  byId('local-summary').textContent = [
    `Grooming inquiry · Status: ${byId('request-status').value}.`,
    `Reply ${replySaved ? 'saved as an unsent draft' : 'not saved'}: ${reply}`,
    `Customer instructions: ${instructions}`,
    `Public page introduction: ${content}`
  ].join('\n');
}

byId('open-inquiry').addEventListener('click', () => {
  const detail = byId('inquiry-detail');
  detail.hidden = !detail.hidden;
  byId('open-inquiry').setAttribute('aria-expanded', String(!detail.hidden));
  byId('open-inquiry').innerHTML = detail.hidden ? 'Open inquiry details <span aria-hidden="true">＋</span>' : 'Close inquiry details <span aria-hidden="true">−</span>';
});

byId('save-reply').addEventListener('click', () => {
  const value = byId('reply').value.trim();
  if (!value) {
    byId('reply-notice').textContent = 'Write a reply before saving.';
    return;
  }
  replySaved = true;
  byId('reply-notice').textContent = 'Reply draft saved in this practice. Nothing sent.';
  updateSummary();
});
byId('reply').addEventListener('input', () => { replySaved = false; updateSummary(); });

byId('request-status').addEventListener('change', () => {
  byId('status-label').textContent = byId('request-status').value;
  byId('status-notice').textContent = 'Status updated in this practice only.';
  updateSummary();
});

byId('save-instructions').addEventListener('click', () => {
  const value = byId('instructions').value.trim();
  if (!value) {
    byId('instructions-notice').textContent = 'Add customer instructions before saving.';
    return;
  }
  byId('customer-instructions').textContent = value;
  byId('instructions-notice').textContent = 'Customer instructions updated in this practice. Nothing published.';
  updateSummary();
});
byId('instructions').addEventListener('input', updateSummary);

byId('save-content').addEventListener('click', () => {
  const value = byId('page-content').value.trim();
  if (!value) {
    byId('content-notice').textContent = 'Add page copy before saving.';
    return;
  }
  byId('hero-copy').textContent = value;
  byId('content-notice').textContent = 'Page introduction updated in this practice. Nothing published.';
  updateSummary();
});
byId('page-content').addEventListener('input', updateSummary);

byId('reset-practice').addEventListener('click', () => {
  byId('reply').value = defaults.reply;
  byId('request-status').value = defaults.status;
  byId('status-label').textContent = defaults.status;
  byId('instructions').value = defaults.instructions;
  byId('customer-instructions').textContent = defaults.instructions;
  byId('page-content').value = defaults.content;
  byId('hero-copy').textContent = defaults.content;
  byId('inquiry-detail').hidden = true;
  byId('open-inquiry').setAttribute('aria-expanded', 'false');
  byId('open-inquiry').innerHTML = 'Open inquiry details <span aria-hidden="true">＋</span>';
  replySaved = false;
  ['reply-notice', 'instructions-notice', 'content-notice'].forEach(id => { byId(id).textContent = ''; });
  byId('status-notice').textContent = 'Status is shown in this practice only.';
  updateSummary();
});

updateSummary();
