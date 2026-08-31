const body = document.body;
const motionToggle = document.querySelector('#motion-toggle');
const inquiryDialog = document.querySelector('#inquiry-dialog');
const inquiryForm = document.querySelector('#inquiry-form');
const eventType = document.querySelector('#event-type');
const eventName = document.querySelector('#event-name');
const eventNote = document.querySelector('#event-note');
const messagePreview = document.querySelector('#message-preview');
const copyMessage = document.querySelector('#copy-message');
const toast = document.querySelector('#toast');

function refreshMessage() {
  const subject = eventType?.value || 'an upcoming event';
  const name = eventName?.value.trim();
  const note = eventNote?.value.trim();
  const intro = name ? `Hi Thirst Trap! I'm reaching out from ${name}.` : 'Hi Thirst Trap!';
  const detail = note ? ` The early detail is: ${note}.` : '';
  messagePreview.textContent = `${intro} I’d love to ask about bringing the pink tent to ${subject}.${detail} Are you available to share details?`;
}

motionToggle?.addEventListener('click', () => {
  const paused = body.classList.toggle('motion-paused');
  motionToggle.setAttribute('aria-pressed', String(paused));
  motionToggle.textContent = paused ? 'Play motion' : 'Pause motion';
});

document.querySelectorAll('[data-open-inquiry]').forEach((button) => button.addEventListener('click', () => inquiryDialog?.showModal()));
document.querySelector('[data-close-inquiry]')?.addEventListener('click', () => inquiryDialog?.close());
[eventType, eventName, eventNote].forEach((field) => field?.addEventListener('input', refreshMessage));

copyMessage?.addEventListener('click', async () => {
  refreshMessage();
  try {
    await navigator.clipboard.writeText(messagePreview.textContent);
    toast.textContent = 'Event message copied. Nothing was sent.';
  } catch {
    toast.textContent = 'Select the message and copy it manually. Nothing was sent.';
  }
  toast.classList.add('show');
  window.setTimeout(() => toast.classList.remove('show'), 3200);
});

inquiryForm?.addEventListener('submit', (event) => event.preventDefault());
refreshMessage();
