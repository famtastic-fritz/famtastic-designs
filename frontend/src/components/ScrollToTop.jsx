import { useEffect } from 'react';
import { useLocation } from 'react-router';

/**
 * React Router preserves the document scroll position between client-side
 * navigations. Marketing pages should open at their beginning unless the URL
 * intentionally targets an in-page anchor.
 */
export default function ScrollToTop() {
  const { pathname, search, hash } = useLocation();

  useEffect(() => {
    if (hash) {
      const target = document.getElementById(decodeURIComponent(hash.slice(1)));
      if (target) {
        target.scrollIntoView();
        return;
      }
    }
    window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
  }, [pathname, search, hash]);

  return null;
}
