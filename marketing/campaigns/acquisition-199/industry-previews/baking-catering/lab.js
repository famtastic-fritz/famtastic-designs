(function () {
  'use strict';

  const status = document.getElementById('inquiryStatus');
  const statusBadge = document.getElementById('statusBadge');
  const detailsToggle = document.getElementById('detailsToggle');
  const moreDetails = document.getElementById('moreDetails');
  const replyDraft = document.getElementById('replyDraft');
  const replyState = document.getElementById('replyState');
  const pageIntro = document.getElementById('pageIntro');
  const ownerInstructions = document.getElementById('ownerInstructions');
  const contentState = document.getElementById('contentState');
  const pageIntroRender = document.getElementById('pageIntroRender');
  const instructionRender = document.getElementById('instructionRender');
  const resetButton = document.getElementById('resetLab');
  const defaults = {
    reply: replyDraft.value,
    intro: pageIntro.value,
    instructions: ownerInstructions.value
  };

  status.addEventListener('change', function () {
    statusBadge.textContent = status.value;
  });

  detailsToggle.addEventListener('click', function () {
    const isOpen = detailsToggle.getAttribute('aria-expanded') === 'true';
    detailsToggle.setAttribute('aria-expanded', String(!isOpen));
    moreDetails.hidden = isOpen;
    detailsToggle.innerHTML = isOpen
      ? 'View inquiry details <span aria-hidden="true">+</span>'
      : 'Hide inquiry details <span aria-hidden="true">−</span>';
  });

  document.getElementById('saveReply').addEventListener('click', function () {
    replyState.textContent = 'Draft held in this page · unsent';
  });

  document.getElementById('saveContent').addEventListener('click', function () {
    const savedIntro = pageIntro.value.trim();
    const savedInstructions = ownerInstructions.value.trim();
    const displayIntro = savedIntro || 'Make room for the moment.';
    const displayInstructions = savedInstructions || 'Confirm the occasion, date, location and approximate guest count.';
    document.querySelector('.hero h1').textContent = displayIntro;
    pageIntroRender.textContent = displayIntro;
    instructionRender.textContent = displayInstructions;
    ownerInstructions.value = savedInstructions;
    contentState.textContent = 'Page notes updated in this sample';
  });

  resetButton.addEventListener('click', function () {
    status.value = 'New';
    statusBadge.textContent = 'New';
    detailsToggle.setAttribute('aria-expanded', 'false');
    detailsToggle.innerHTML = 'View inquiry details <span aria-hidden="true">+</span>';
    moreDetails.hidden = true;
    replyDraft.value = defaults.reply;
    replyState.textContent = 'Not saved';
    pageIntro.value = defaults.intro;
    ownerInstructions.value = defaults.instructions;
    document.querySelector('.hero h1').textContent = defaults.intro;
    pageIntroRender.textContent = defaults.intro;
    instructionRender.textContent = defaults.instructions;
    contentState.textContent = 'Not saved';
  });
}());
