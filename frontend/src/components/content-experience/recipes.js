/** Owned public-page recipes. Only Web Basics is opted in during owner review. */
export const CONTENT_SYSTEM_VERSION = '1.0.0';
export const CONTENT_PREVIEW_ROUTES = ['/packages/199-quick-start'];
export const CONTENT_RECIPES = {
  'package-detail': { intensity: 2, character: 'commercial', sections: ['offer', 'features', 'fit', 'addons', 'pricing', 'education', 'finale'] },
  'packages-hub': { intensity: 2, character: 'comparison', status: 'planned', sections: ['intro', 'comparison', 'education', 'finale'] },
  'solution-detail': { intensity: 2, character: 'engineered', status: 'planned', sections: ['problem', 'system', 'outcome', 'process', 'related', 'finale'] },
  'services-hub': { intensity: 2, character: 'capability', status: 'planned', sections: ['intro', 'capabilities', 'related', 'finale'] },
  about: { intensity: 3, character: 'editorial', status: 'planned', sections: ['story', 'philosophy', 'fam-definition', 'founder', 'process', 'finale'] },
  contact: { intensity: 2, character: 'invitation', status: 'planned', sections: ['invitation', 'conversation', 'next-step'] },
};
export const CONTENT_MATERIALS = ['glass', 'obsidian', 'metal', 'brush', 'spotlight', 'technical'];

/** Never infer recipe enrollment from a price/title match. */
export function hasContentExperience(slug) {
  return CONTENT_PREVIEW_ROUTES.includes(`/packages/${slug}`);
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
