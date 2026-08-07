const measurementId = import.meta.env.VITE_GA_MEASUREMENT_ID?.trim();

let initialized = false;

export function initializeGoogleAnalytics() {
  if (!measurementId || initialized || typeof window === 'undefined') return false;

  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function gtag() {
    window.dataLayer.push(arguments);
  };

  window.gtag('js', new Date());
  window.gtag('config', measurementId, { send_page_view: false });

  const script = document.createElement('script');
  script.async = true;
  script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
  document.head.appendChild(script);

  initialized = true;
  return true;
}

export function trackPageView(path) {
  if (!initializeGoogleAnalytics() && !initialized) return;

  window.gtag('event', 'page_view', {
    page_location: window.location.href,
    page_path: path,
    page_title: document.title,
  });
}
