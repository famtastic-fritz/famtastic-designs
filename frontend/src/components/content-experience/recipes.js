/** Owner approved propagation from Web Basics. Explicit existing routes only. */
export const CONTENT_SYSTEM_VERSION = '1.1.0';
export const CONTENT_ROUTES = {
  '/video-review': 'video-review',
  '/packages': 'packages-hub',
  '/packages/199-quick-start': 'package-detail',
  '/packages/499-site-upgrade': 'package-detail',
  '/packages/starter-website': 'package-detail',
  '/packages/business-website': 'package-detail',
  '/packages/premium-website-ai': 'package-detail',
  '/packages/landing-page': 'package-detail',
  '/packages/website-care-plan': 'package-detail',
  '/services': 'services-hub',
  '/services/ai-chatbot': 'solution-detail',
  '/services/site-rebuild': 'solution-detail',
  '/services/custom-website-development': 'solution-detail',
  '/services/landing-page-design': 'solution-detail',
  '/services/client-portal-systems': 'solution-detail',
  '/services/e-commerce-solutions': 'solution-detail',
  '/about': 'about',
  '/contact': 'contact',
};
export const CONTENT_PREVIEW_ROUTES = Object.keys(CONTENT_ROUTES);
export const CONTENT_RECIPES = {
  'video-review': { intensity: 2, character: 'cinematic', sections: ['intro', 'video-index', 'walking', 'faster-ad', 'voice-test'] },
  'package-detail': { intensity: 2, character: 'commercial', sections: ['offer', 'features', 'fit', 'addons', 'pricing', 'education', 'finale'] },
  'packages-hub': { intensity: 2, character: 'comparison', sections: ['intro', 'comparison', 'finale'] },
  'solution-detail': { intensity: 2, character: 'engineered', sections: ['intro', 'problem', 'system', 'process', 'proof', 'deliverables', 'faq', 'education', 'start', 'finale'] },
  'services-hub': { intensity: 2, character: 'capability', sections: ['intro', 'capabilities', 'finale'] },
  about: { intensity: 3, character: 'editorial', sections: ['story', 'fam-definition', 'philosophy', 'existing-story', 'finale'] },
  contact: { intensity: 2, character: 'invitation', sections: ['invitation', 'project-fit', 'contact-form'] },
};
export const CONTENT_MATERIALS = ['glass', 'obsidian', 'metal', 'brush', 'spotlight', 'technical'];

/** Never infer recipe enrollment from a price/title match. */
export function hasContentExperience(slug) {
  return contentRecipeForPath(`/packages/${slug}`) === 'package-detail';
}

export function contentRecipeForPath(pathname) {
  return CONTENT_ROUTES[String(pathname).replace(/\/+$/, '')] || null;
}

/** The primitive accepts navigation, never script/data/protocol-relative URLs. */
export function safeContentHref(href) {
  if (typeof href !== 'string' || /[\s\\\u0000-\u001f]/.test(href)) return null;
  if (/^\/(?!\/)/.test(href)) return href;
  try {
    const url = new URL(href);
    return url.protocol === 'https:' && !url.username && !url.password ? url.href : null;
  } catch { return null; }
}
