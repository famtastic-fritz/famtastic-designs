import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';
import { seoForPath } from '../seo.js';

function upsertMeta(selector, attributes) {
  let node = document.head.querySelector(selector);
  if (!node) {
    node = document.createElement('meta');
    document.head.appendChild(node);
  }
  Object.entries(attributes).forEach(([key, value]) => node.setAttribute(key, value));
}

function upsertLink(selector, attributes) {
  let node = document.head.querySelector(selector);
  if (!node) {
    node = document.createElement('link');
    document.head.appendChild(node);
  }
  Object.entries(attributes).forEach(([key, value]) => node.setAttribute(key, value));
}

export default function SEO() {
  const { pathname } = useLocation();

  useEffect(() => {
    const seo = seoForPath(pathname);
    document.title = seo.title;
    upsertMeta('meta[name="description"]', { name: 'description', content: seo.description });
    upsertLink('link[rel="canonical"]', { rel: 'canonical', href: seo.canonical });
    upsertMeta('meta[property="og:site_name"]', { property: 'og:site_name', content: seo.siteName });
    upsertMeta('meta[property="og:type"]', { property: 'og:type', content: 'website' });
    upsertMeta('meta[property="og:title"]', { property: 'og:title', content: seo.title });
    upsertMeta('meta[property="og:description"]', { property: 'og:description', content: seo.description });
    upsertMeta('meta[property="og:url"]', { property: 'og:url', content: seo.canonical });
    upsertMeta('meta[name="twitter:card"]', { name: 'twitter:card', content: 'summary_large_image' });
    upsertMeta('meta[name="twitter:title"]', { name: 'twitter:title', content: seo.title });
    upsertMeta('meta[name="twitter:description"]', { name: 'twitter:description', content: seo.description });
  }, [pathname]);

  return null;
}
