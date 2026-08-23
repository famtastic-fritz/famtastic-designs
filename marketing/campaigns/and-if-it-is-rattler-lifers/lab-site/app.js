(() => {
  'use strict';

  const measurementId = 'G-T2ENFBZR4K';
  const canonicalPath = '/lab/and-if-it-is/';
  const contentId = 'famtastic-lab-and-if-it-is-v1';

  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function gtag() {
    window.dataLayer.push(arguments);
  };

  window.gtag('js', new Date());
  window.gtag('config', measurementId, { send_page_view: false });

  const analyticsScript = document.createElement('script');
  analyticsScript.async = true;
  analyticsScript.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
  document.head.appendChild(analyticsScript);

  window.gtag('event', 'page_view', {
    page_location: `https://famtasticdesigns.com${canonicalPath}`,
    page_path: canonicalPath,
    page_title: document.title,
    content_id: contentId,
  });

  document.querySelectorAll('[data-track]').forEach((link) => {
    link.addEventListener('click', () => {
      window.gtag('event', 'cta_clicked', {
        content_id: contentId,
        cta_id: link.dataset.track,
        cta_location: link.dataset.location || 'navigation',
        destination_type: link.dataset.track === 'intake' ? 'owned_intake' : 'owned_experience',
      });
    });
  });

  document.querySelectorAll('[data-year]').forEach((node) => {
    node.textContent = String(new Date().getFullYear());
  });

  const revealNodes = [...document.querySelectorAll('.reveal')];
  if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      });
    }, { threshold: 0.12 });
    revealNodes.forEach((node) => observer.observe(node));
  } else {
    revealNodes.forEach((node) => node.classList.add('is-visible'));
  }
})();
