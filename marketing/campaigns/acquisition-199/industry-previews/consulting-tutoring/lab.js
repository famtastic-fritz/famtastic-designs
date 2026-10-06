'use strict';
// Fictional in-page practice only. No network, storage, account or publication calls.
const byId = id => document.getElementById(id);
const defaults = {
  reply: 'Thanks for reaching out. A useful place to begin is who you want the offer to help and what you would like them to understand. We can use that as a starting point for the conversation.',
  status: 'New inquiry',
  guidance: 'To help start the conversation, share the service you have in mind, the goal or topic, and the question you would like to discuss. Please leave sensitive personal or student information out of this message.',
  content: 'A fictional independent practice showing how consulting, tutoring and creative work can be explained with a clear offer and a thoughtful first conversation.'
};
let replySaved = false;
function updateSummary() {
  const reply = byId('reply').value.trim() || 'No reply drafted yet.';
  const guidance = byId('customer-guide').value.trim() || 'No first-contact guidance added.';
  const content = byId('page-content').value.trim() || 'No website introduction added.';
  byId('local-summary').textContent = [
    `Consulting inquiry · Status: ${byId('request-status').value}.`,
    `Reply ${replySaved ? 'saved as an unsent draft' : 'not saved'}: ${reply}`,
    `First-contact guidance: ${guidance}`,
    `Website introduction: ${content}`
  ].join('\n');
}
byId('open-inquiry').addEventListener('click', () => {
  const detail = byId('inquiry-detail');
  detail.hidden = !detail.hidden;
  byId('open-inquiry').setAttribute('aria-expanded', String(!detail.hidden));
  byId('open-inquiry').innerHTML = detail.hidden ? 'Open inquiry details <span aria-hidden="true">＋</span>' : 'Close inquiry details <span aria-hidden="true">−</span>';
});
byId('save-reply').addEventListener('click', () => {
  if (!byId('reply').value.trim()) { byId('reply-notice').textContent = 'Write a reply before saving.'; return; }
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
byId('save-guidance').addEventListener('click', () => {
  const value = byId('customer-guide').value.trim();
  if (!value) { byId('guidance-notice').textContent = 'Add first-contact guidance before saving.'; return; }
  byId('customer-instructions').textContent = value;
  byId('guidance-notice').textContent = 'Guidance updated in this practice. Nothing published.';
  updateSummary();
});
byId('customer-guide').addEventListener('input', updateSummary);
byId('save-content').addEventListener('click', () => {
  const value = byId('page-content').value.trim();
  if (!value) { byId('content-notice').textContent = 'Add an introduction before saving.'; return; }
  byId('hero-copy').textContent = value;
  byId('content-notice').textContent = 'Website introduction updated in this practice. Nothing published.';
  updateSummary();
});
byId('page-content').addEventListener('input', updateSummary);
byId('reset-practice').addEventListener('click', () => {
  byId('reply').value = defaults.reply;
  byId('request-status').value = defaults.status;
  byId('status-label').textContent = defaults.status;
  byId('customer-guide').value = defaults.guidance;
  byId('customer-instructions').textContent = defaults.guidance;
  byId('page-content').value = defaults.content;
  byId('hero-copy').textContent = defaults.content;
  byId('inquiry-detail').hidden = true;
  byId('open-inquiry').setAttribute('aria-expanded', 'false');
  byId('open-inquiry').innerHTML = 'Open inquiry details <span aria-hidden="true">＋</span>';
  replySaved = false;
  ['reply-notice', 'guidance-notice', 'content-notice'].forEach(id => { byId(id).textContent = ''; });
  byId('status-notice').textContent = 'Status is shown in this practice only.';
  updateSummary();
});
updateSummary();
