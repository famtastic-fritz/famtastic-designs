'use strict';
// Local-only practice controls. No network, storage, account creation, or transmission.
const $ = (id) => document.getElementById(id);
const initial = {reply: $('reply').value, instructions: $('instructions').value};
$('toggle-detail').addEventListener('click', () => {
  const panel = $('job-details'); panel.hidden = !panel.hidden;
  $('toggle-detail').setAttribute('aria-expanded', String(!panel.hidden));
  $('toggle-detail').innerHTML = panel.hidden ? 'View job details <span>＋</span>' : 'Hide job details <span>−</span>';
});
$('job-status').addEventListener('change', () => {$('status-note').textContent = `Status changed in this practice: ${$('job-status').value}.`;});
$('save-reply').addEventListener('click', () => {$('reply-note').textContent = $('reply').value.trim() ? 'Reply draft saved in this page.' : 'Add a reply before saving.';});
$('save-instructions').addEventListener('click', () => {$('instructions-note').textContent = $('instructions').value.trim() ? 'Visitor instructions saved in this page.' : 'Add instructions before saving.';});
$('reset').addEventListener('click', () => {
  $('reply').value = initial.reply; $('instructions').value = initial.instructions; $('job-status').selectedIndex = 0;
  $('status-note').textContent = 'Needs review'; $('reply-note').textContent = ''; $('instructions-note').textContent = '';
   $('job-details').hidden = true; $('toggle-detail').setAttribute('aria-expanded', 'false'); $('toggle-detail').innerHTML = 'View job details <span>＋</span>';
});
