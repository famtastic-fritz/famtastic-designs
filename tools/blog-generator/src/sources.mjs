/**
 * Grounding material for the blog generator.
 *
 * Everything a post is allowed to assert about FAMtastic Designs comes from
 * here — the site's own services, packages, FAQs, and case studies, read over
 * anonymous JSON:API (the same read-only access the SPA uses). Nothing is
 * invented at draft time, which is what keeps the output specific to this
 * business rather than generic web-design filler.
 */

import { readFile, writeFile } from 'node:fs/promises';

const BUNDLES = ['service_page', 'package_page', 'faq_item', 'case_study'];

/** Drupal text fields arrive as {value, processed} or a bare string. */
function text(field) {
  if (field == null) return '';
  if (typeof field === 'string') return field.trim();
  if (Array.isArray(field)) return field.map(text).filter(Boolean).join('\n');
  if (typeof field === 'object') return text(field.value ?? field.processed ?? '');
  return String(field).trim();
}

/** Multi-value list fields arrive as an array of {value} entries. */
function list(field) {
  if (!Array.isArray(field)) {
    const single = text(field);
    return single ? [single] : [];
  }
  return field.map(text).filter(Boolean);
}

function alias(node) {
  return node?.attributes?.path?.alias || '';
}

/** Strip Drupal's processed HTML down to plain text for prompt use. */
function plain(html) {
  return text(html)
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<\/(p|li|h[1-6])>/gi, '\n')
    .replace(/<[^>]+>/g, '')
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/\n{3,}/g, '\n\n')
    .trim();
}

async function fetchBundle(baseUrl, bundle) {
  const url = `${baseUrl}/jsonapi/node/${bundle}?filter[status]=1&page[limit]=50`;
  const res = await fetch(url, { headers: { Accept: 'application/vnd.api+json' } });
  if (!res.ok) throw new Error(`${bundle}: HTTP ${res.status} from ${url}`);
  const json = await res.json();
  return json.data ?? [];
}

const SHAPERS = {
  service_page: (n) => ({
    title: n.attributes?.title ?? '',
    path: alias(n),
    headline: text(n.attributes?.field_hero_headline),
    summary: text(n.attributes?.field_hero_subheadline) || text(n.attributes?.field_meta_description),
    painPoints: list(n.attributes?.field_pain_points),
    included: list(n.attributes?.field_whats_included).concat(list(n.attributes?.field_features)),
  }),
  package_page: (n) => ({
    title: n.attributes?.title ?? '',
    path: alias(n),
    price: text(n.attributes?.field_price),
    timeline: text(n.attributes?.field_timeline),
    bestFor: text(n.attributes?.field_best_for),
    included: list(n.attributes?.field_whats_included).concat(list(n.attributes?.field_features)),
  }),
  faq_item: (n) => ({
    question: text(n.attributes?.field_question) || n.attributes?.title || '',
    answer: plain(n.attributes?.field_answer ?? n.attributes?.body),
  }),
  case_study: (n) => ({
    title: n.attributes?.title ?? '',
    path: alias(n),
    projectType: text(n.attributes?.field_project_type),
    summary: text(n.attributes?.field_summary) || text(n.attributes?.field_subtitle),
    results: list(n.attributes?.field_results),
  }),
};

/**
 * Pull every grounding bundle from a live Drupal and normalise it.
 * Bundles that are absent or empty are reported rather than silently dropped —
 * a thin corpus produces thin posts, and the operator should know before
 * spending tokens on a run.
 */
export async function collectSources(baseUrl) {
  const base = baseUrl.replace(/\/+$/, '');
  const out = { generatedFrom: base, services: [], packages: [], faqs: [], caseStudies: [] };
  const keyFor = {
    service_page: 'services',
    package_page: 'packages',
    faq_item: 'faqs',
    case_study: 'caseStudies',
  };

  for (const bundle of BUNDLES) {
    const nodes = await fetchBundle(base, bundle);
    out[keyFor[bundle]] = nodes.map(SHAPERS[bundle]).filter((entry) => {
      const label = entry.title || entry.question || '';
      return label.length > 0;
    });
  }

  return out;
}

export async function loadSources(path) {
  return JSON.parse(await readFile(path, 'utf8'));
}

export async function saveSources(path, sources) {
  await writeFile(path, `${JSON.stringify(sources, null, 2)}\n`);
}

/** Human-readable count line, used by the CLI and the thin-corpus warning. */
export function describeSources(s) {
  return `${s.services.length} services, ${s.packages.length} packages, ${s.faqs.length} FAQs, ${s.caseStudies.length} case studies`;
}

/**
 * Render the corpus as the prompt's reference section. Kept deterministic
 * (stable key order, no timestamps) so it caches cleanly across every draft
 * in a run — see the cache_control breakpoint in claude.mjs.
 */
export function renderSources(s) {
  const lines = [];

  if (s.packages.length) {
    lines.push('## Packages and prices');
    for (const p of s.packages) {
      lines.push(`### ${p.title}${p.price ? ` — ${p.price}` : ''}`);
      if (p.path) lines.push(`Page: ${p.path}`);
      if (p.timeline) lines.push(`Timeline: ${p.timeline}`);
      if (p.bestFor) lines.push(`Best for: ${p.bestFor}`);
      if (p.included.length) lines.push(`Includes: ${p.included.join('; ')}`);
      lines.push('');
    }
  }

  if (s.services.length) {
    lines.push('## Services');
    for (const svc of s.services) {
      lines.push(`### ${svc.title}`);
      if (svc.path) lines.push(`Page: ${svc.path}`);
      if (svc.summary) lines.push(svc.summary);
      if (svc.painPoints.length) lines.push(`Problems it solves: ${svc.painPoints.join('; ')}`);
      if (svc.included.length) lines.push(`Includes: ${svc.included.join('; ')}`);
      lines.push('');
    }
  }

  if (s.faqs.length) {
    lines.push('## Questions real customers ask (with the studio’s answers)');
    for (const f of s.faqs) lines.push(`Q: ${f.question}\nA: ${f.answer}\n`);
  }

  if (s.caseStudies.length) {
    lines.push('## Completed work');
    for (const c of s.caseStudies) {
      lines.push(`### ${c.title}${c.projectType ? ` (${c.projectType})` : ''}`);
      if (c.path) lines.push(`Page: ${c.path}`);
      if (c.summary) lines.push(c.summary);
      if (c.results.length) lines.push(`Results: ${c.results.join('; ')}`);
      lines.push('');
    }
  }

  return lines.join('\n').trim();
}
