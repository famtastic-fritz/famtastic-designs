const measurementId = import.meta.env.VITE_GA_MEASUREMENT_ID?.trim();

let initialized = false;

function safeLocation(path) {
  const url = new URL(window.location.href);
  const cleanPath = String(path || url.pathname)
    .replace(/^\/proofs\/share\/[^/]+\/[^/]+/, '/proofs/share/unlisted')
    .replace(/^\/portal\/[^/]+/, '/portal/personalized')
    .replace(/^\/p\/[^/]+/, '/p/personalized');
  url.pathname = cleanPath;
  ['token', 'key', 'code', 'session', 'secret'].forEach((key) => url.searchParams.delete(key));
  url.hash = '';
  return { path: `${cleanPath}${url.search}`, location: url.toString() };
}

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
  const safe = safeLocation(path);

  window.gtag('event', 'page_view', {
    page_location: safe.location,
    page_path: safe.path,
    page_title: document.title,
  });
}
