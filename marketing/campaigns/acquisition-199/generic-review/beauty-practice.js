'use strict';
// Page-local practice only. No fetch, storage, account creation or transmission.
const byId=id=>document.getElementById(id);
byId('open-inquiry').addEventListener('click',()=>{const detail=byId('inquiry-detail');detail.hidden=!detail.hidden;byId('open-inquiry').setAttribute('aria-expanded',String(!detail.hidden));byId('open-inquiry').textContent=detail.hidden?'Open the inquiry':'Close the inquiry';});
byId('save-reply').addEventListener('click',()=>{byId('reply-notice').textContent=byId('reply').value.trim()?'Reply draft saved in this practice. Nothing sent.':'Write a reply before saving.';});
byId('request-status').addEventListener('change',()=>{byId('status-label').textContent=byId('request-status').value;byId('status-notice').textContent='Status updated in this practice only.';});
byId('save-instructions').addEventListener('click',()=>{byId('instructions-notice').textContent=byId('instructions').value.trim()?'Instructions saved in this practice. Nothing published.':'Add instructions before saving.';});
byId('interview-form').addEventListener('submit',event=>{event.preventDefault();byId('interview-notice').textContent='Practice interview notes saved for '+byId('business').value.trim()+'. No account created or interview submitted. Reload clears these notes.';});