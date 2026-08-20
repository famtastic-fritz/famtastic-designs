(function (Drupal, once) {
  'use strict';

  /**
   * Makes the custom Operations surface honest on a phone. This does not try
   * to turn Drupal's dense entity editors into a mobile form builder; it gives
   * the owner a clear triage mode and preserves normal browsing after dismissal.
   */
  Drupal.behaviors.famtasticOperationsMobile = {
    attach(context) {
      const operations = once('famtastic-operations-mobile', '.famtastic-ops', context)[0];
      const mobile = window.matchMedia('(max-width: 720px) and (pointer: coarse)').matches;
      if (!operations || !mobile || window.sessionStorage.getItem('famtastic-ops-mobile-note') === 'dismissed') {
        return;
      }

      const notice = document.createElement('section');
      notice.className = 'famtastic-ops__mobile-note';
      notice.setAttribute('role', 'status');
      notice.setAttribute('aria-label', 'Mobile Operations guidance');
      notice.innerHTML = '<div><strong>Mobile Operations view</strong><p>Use this screen to review alerts, requests, and next steps. Detailed Drupal editors and wide record tables are safer on desktop; this view keeps you in triage mode so taps and scrolling stay predictable.</p></div><button type="button">Got it</button>';
      notice.querySelector('button').addEventListener('click', () => {
        window.sessionStorage.setItem('famtastic-ops-mobile-note', 'dismissed');
        notice.remove();
      });
      operations.prepend(notice);
    },
  };
})(Drupal, once);
