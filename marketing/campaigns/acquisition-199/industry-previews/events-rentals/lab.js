(function () {
  'use strict';
  const status = document.getElementById('requestStatus');
  const statusBadge = document.getElementById('statusBadge');
  const summaryStatus = document.getElementById('summaryStatus');
  const detailsToggle = document.getElementById('detailsToggle');
  const moreDetails = document.getElementById('moreDetails');
  const replyDraft = document.getElementById('replyDraft');
  const replyState = document.getElementById('replyState');
  const pageIntro = document.getElementById('pageIntro');
  const ownerInstructions = document.getElementById('ownerInstructions');
  const contentState = document.getElementById('contentState');
  const pageIntroRender = document.getElementById('pageIntroRender');
  const instructionRender = document.getElementById('instructionRender');
  const defaults = { reply: replyDraft.value, intro: pageIntro.value, instructions: ownerInstructions.value };
  const renderStatus = function () {
    statusBadge.textContent = status.value;
    summaryStatus.textContent = status.value + ' · about 28 guests';
  };
  status.addEventListener('change', renderStatus);
  detailsToggle.addEventListener('click', function () {
    const open = detailsToggle.getAttribute('aria-expanded') === 'true';
    detailsToggle.setAttribute('aria-expanded', String(!open));
    moreDetails.hidden = open;
    detailsToggle.innerHTML = open ? 'View request details <span aria-hidden="true">+</span>' : 'Hide request details <span aria-hidden="true">−</span>';
  });
  document.getElementById('saveReply').addEventListener('click', function () { replyState.textContent = 'Draft held in this page · unsent'; });
  document.getElementById('saveContent').addEventListener('click', function () {
    const intro = pageIntro.value.trim() || 'Bring the pieces together.';
    const instructions = ownerInstructions.value.trim() || 'Share the event details you would like to discuss.';
    document.querySelector('.hero h1').textContent = intro;
    pageIntroRender.textContent = intro;
    instructionRender.textContent = instructions;
    contentState.textContent = 'Page notes updated in this sample';
  });
  document.getElementById('resetLab').addEventListener('click', function () {
    status.value = 'New'; renderStatus();
    detailsToggle.setAttribute('aria-expanded', 'false');
    detailsToggle.innerHTML = 'View request details <span aria-hidden="true">+</span>';
    moreDetails.hidden = true;
    replyDraft.value = defaults.reply; replyState.textContent = 'Not saved';
    pageIntro.value = defaults.intro; ownerInstructions.value = defaults.instructions;
    document.querySelector('.hero h1').textContent = defaults.intro;
    pageIntroRender.textContent = defaults.intro; instructionRender.textContent = defaults.instructions;
    contentState.textContent = 'Not saved';
  });
}());
