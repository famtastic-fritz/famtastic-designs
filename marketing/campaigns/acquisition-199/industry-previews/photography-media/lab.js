'use strict';
// Browser-local practice only. No fetch, storage, account action or submission.
const byId = id => document.getElementById(id);
byId('open-inquiry').addEventListener('click', () => { const detail = byId('inquiry-detail'); detail.hidden = !detail.hidden; byId('open-inquiry').setAttribute('aria-expanded', String(!detail.hidden)); byId('open-inquiry').textContent = detail.hidden ? 'Open the inquiry' : 'Close the inquiry'; });
byId('save-reply').addEventListener('click', () => { byId('reply-notice').textContent = byId('reply').value.trim() ? 'Reply draft saved in this practice. Nothing sent.' : 'Write a reply before saving.'; });
byId('request-status').addEventListener('change', () => { byId('status-label').textContent = byId('request-status').value; byId('status-notice').textContent = 'Status updated in this practice only.'; });
byId('save-instructions').addEventListener('click', () => { byId('instructions-notice').textContent = byId('instructions').value.trim() ? 'Instructions saved in this practice. Nothing published.' : 'Add instructions before saving.'; });
byId('request-form').addEventListener('submit', event => { event.preventDefault(); const values = ['project-type','date','area','output','contact-preference'].map(id => byId(id).value.trim()).filter(Boolean); byId('form-summary').textContent = `Local example brief: ${values.join(' · ')}. Nothing was submitted or recorded.`; });
byId('reset-practice').addEventListener('click', () => window.location.reload());
