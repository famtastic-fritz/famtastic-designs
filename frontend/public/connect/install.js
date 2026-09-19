(() => {
  'use strict';
  const buttons = [...document.querySelectorAll('[data-install]')];
  const dialog = document.getElementById('installDialog');
  const nativeButton = document.getElementById('installNative');
  const steps = document.getElementById('installSteps');
  const intro = document.getElementById('installIntro');
  const status = document.getElementById('installStatus');
  const standalone = window.matchMedia('(display-mode: standalone)');
  const appUrl = 'https://famtasticdesigns.com/connect/';
  let deferredPrompt = null;
  let installed = standalone.matches || navigator.standalone === true;
  let prompting = false;

  function report(message) {
    status.textContent = message;
    status.hidden = false;
  }

  function syncButtons() {
    buttons.forEach((button) => {
      button.disabled = installed || prompting;
      button.querySelector('[data-install-label]').textContent = installed ? 'Added to your device' : 'Save to this device';
      button.querySelector('[data-install-detail]').textContent = installed ? 'Open it from your FAMtastic icon' : 'Add a FAMtastic icon to your Home Screen';
    });
    nativeButton.hidden = installed || !deferredPrompt;
    nativeButton.disabled = prompting;
  }

  function showInstructions() {
    const ua = navigator.userAgent;
    const ios = /iPhone|iPad|iPod/i.test(ua) || (/Macintosh/i.test(ua) && navigator.maxTouchPoints > 1);
    let instructions;
    if (ios) {
      instructions = [
        'Open this page in Safari. If you are inside another app, copy the link below and paste it into Safari.',
        'Open the Share menu and choose “Add to Home Screen.” You may need to scroll through the options.',
        'If shown, turn on “Open as Web App,” then tap “Add.”'
      ];
    } else if (/SamsungBrowser/i.test(ua)) {
      instructions = [
        'Open the Samsung Internet menu.',
        'Look for “Add page to” → “Home screen,” or the install option.',
        'Confirm “Add” or “Install” to keep the FAMtastic icon.'
      ];
    } else if (/Android/i.test(ua)) {
      instructions = [
        'Open this page in Chrome. If you are inside another app, copy the link below and paste it into Chrome.',
        'Tap the three-dot menu. Choose “Install and create shortcut” → “Install,” or “Add to Home screen” if that is the option shown.',
        'Confirm “Add” or “Install” to keep the FAMtastic icon.'
      ];
    } else {
      instructions = [
        'In Chrome or Edge, look for the install icon in the address bar or the browser’s install menu.',
        'On a Mac with Safari, use File → Add to Dock. If installation is unavailable, bookmark this page.'
      ];
    }
    steps.replaceChildren(...instructions.map((text) => {
      const item = document.createElement('li');
      item.textContent = text;
      return item;
    }));
    intro.textContent = 'Add a FAMtastic icon to reopen this card. Your browser asks you to confirm.';
    status.hidden = true;
    syncButtons();
    if (dialog.showModal) {
      if (!dialog.open) dialog.showModal();
    } else {
      window.alert(instructions.join('\n\n'));
    }
  }

  async function install() {
    if (installed || prompting) return;
    if (!deferredPrompt) { showInstructions(); return; }
    const event = deferredPrompt;
    deferredPrompt = null;
    prompting = true;
    syncButtons();
    try {
      // Call prompt while this click still carries the browser's user activation.
      await event.prompt();
      const choice = await event.userChoice;
      if (choice.outcome === 'accepted') {
        if (dialog.open) report('Installation requested. Follow the browser confirmation to finish.');
      } else {
        showInstructions();
        report('Nothing was added. You can use your browser menu whenever you are ready.');
      }
    } catch {
      showInstructions();
      report('Use the browser menu above to add your Home Screen icon.');
    } finally {
      prompting = false;
      syncButtons();
    }
  }

  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredPrompt = event;
    syncButtons();
  });
  window.addEventListener('appinstalled', () => {
    installed = true;
    deferredPrompt = null;
    syncButtons();
    if (dialog.open) dialog.close();
  });
  standalone.addEventListener?.('change', (event) => {
    if (event.matches) installed = true;
    syncButtons();
  });
  buttons.forEach((button) => button.addEventListener('click', install));
  nativeButton.addEventListener('click', install);
  document.getElementById('closeInstall').addEventListener('click', () => dialog.close());
  document.getElementById('copyInstallLink').addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(appUrl);
      report('Link copied. Paste it into your phone’s browser.');
    } catch {
      report('Copy this address into your browser: ' + appUrl);
    }
  });
  dialog.addEventListener('click', (event) => {
    const r = dialog.getBoundingClientRect();
    if (event.target === dialog && (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom)) dialog.close();
  });
  syncButtons();
})();
