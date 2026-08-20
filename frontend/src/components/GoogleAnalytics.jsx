import { useEffect } from 'react';
import { useLocation } from 'react-router';
import { trackPageView } from '../lib/googleAnalytics.js';

let lastTrackedPath;

export default function GoogleAnalytics() {
  const location = useLocation();
  const path = `${location.pathname}${location.search}`;

  useEffect(() => {
    if (location.pathname.startsWith('/proofs/share/')) return;
    if (path === lastTrackedPath) return;
    lastTrackedPath = path;
    trackPageView(path);
  }, [location.pathname, path]);

  return null;
}
