import { useEffect } from 'react';
import { useLocation } from 'react-router';
import { trackPageView } from '../lib/googleAnalytics.js';

let lastTrackedPath;

export default function GoogleAnalytics() {
  const location = useLocation();
  const path = `${location.pathname}${location.search}`;

  useEffect(() => {
    // Signed proof URLs are bearer links. Do not initialize or send an
    // analytics page event that could retain either kind of signature.
    if (location.pathname.startsWith('/proofs/share/') || location.pathname.startsWith('/proofs/preview/')) return;
    if (path === lastTrackedPath) return;
    lastTrackedPath = path;
    trackPageView(path);
  }, [location.pathname, path]);

  return null;
}
