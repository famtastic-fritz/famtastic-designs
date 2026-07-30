/**
 * drupalAdapter — transforms RAW Drupal JSON:API resources into the exact
 * prop shapes the v1 components expect. Every transformer is defensive:
 * missing/odd field values degrade to '' or [] so partially-seeded nodes
 * still render a clean page.
 *
 * Built on the raw helpers from src/api/drupal.js (getNodesRaw,
 * getNodeByAlias, resolveIncluded) and the field readers from
 * src/utils/content.js — this module adds no new fetch logic.
 */

import { resolveIncluded } from '../api/drupal.js';
import {
  linkHref,
  listValues,
  nodeSlug,
  paraField,
  textValue,
  titleSlug,
} from '../utils/content.js';

/* ------------------------------------------------------------------ */
/* Small shared readers                                                */
/* ------------------------------------------------------------------ */

/** Drupal link field → { label, href } with fallbacks. */
function ctaValue(field, { label = '', href = '/contact' } = {}) {
  return {
    label: textValue(field?.title) || label,
    href: linkHref(field, href),
  };
}

/** paragraph--process_step → { number, title, body }. */
function processStep(resource, index) {
  return {
    id: resource.id,
    number:
      paraField(resource, ['field_step_number']) || String(index + 1).padStart(2, '0'),
    title: paraField(resource, ['field_step_title', 'field_title']),
    body: paraField(resource, ['field_step_description', 'field_description', 'field_body']),
  };
}

/** paragraph--faq_qa → { question, answer }. */
function faqItem(resource, index) {
  return {
    id: resource.id ?? index,
    question: paraField(resource, ['field_question']) || `Question ${index + 1}`,
    answer: paraField(resource, ['field_answer']),
  };
}

/* ------------------------------------------------------------------ */
/* service_page                                                        */
/* ------------------------------------------------------------------ */

/**
 * service_page node → props for ServiceCard / the full ServicePage layout
 * (Hero → PainPoints → Solution → Process → Testimonial → Features → FAQ → CTA).
 */
export function transformServiceNode(node, included = []) {
  if (!node) return null;
  const attrs = node.attributes ?? {};

  return {
    id: node.id,
    slug: nodeSlug(node),
    title: attrs.title ?? 'Service',
    icon: textValue(attrs.field_service_icon),
    sortOrder: Number(attrs.field_sort_order) || 0,
    headline: textValue(attrs.field_hero_headline) || attrs.title || 'Service',
    subheadline: textValue(attrs.field_hero_subheadline),
    painPointsTitle: textValue(attrs.field_pain_points_title),
    painPoints: listValues(attrs.field_pain_points),
    solutionTitle: textValue(attrs.field_solution_title),
    solutionBullets: listValues(attrs.field_solution_bullets),
    processTitle: textValue(attrs.field_process_title),
    processSteps: resolveIncluded(node, included, 'field_process_steps').map(processStep),
    testimonial: {
      quote: textValue(attrs.field_testimonial_quote) || textValue(attrs.field_proof_quote),
      attribution:
        textValue(attrs.field_testimonial_attribution) || textValue(attrs.field_proof_attribution),
    },
    featuresTitle: textValue(attrs.field_features_title),
    features: listValues(attrs.field_features).length
      ? listValues(attrs.field_features)
      : listValues(attrs.field_whats_included),
    faqTitle: textValue(attrs.field_faq_title),
    faqs: resolveIncluded(node, included, 'field_faq_qa').map(faqItem),
    ctaText: textValue(attrs.field_cta_text) || 'Book a Call',
    ctaHref: linkHref(attrs.field_cta_link),
    metaTitle: textValue(attrs.field_meta_title),
    metaDescription: textValue(attrs.field_meta_description),
  };
}

/* ------------------------------------------------------------------ */
/* package_page                                                        */
/* ------------------------------------------------------------------ */

/** package_page node → props for PricingCard / PackagePage. */
export function transformPackageNode(node, included = []) {
  if (!node) return null;
  const attrs = node.attributes ?? {};
  const badge = textValue(attrs.field_badge);

  return {
    id: node.id,
    slug: nodeSlug(node),
    title: attrs.title ?? 'Package',
    sortOrder: Number(attrs.field_sort_order) || 0,
    price: textValue(attrs.field_price),
    timeline: textValue(attrs.field_timeline),
    badge,
    highlighted: ['best_value', 'most_popular'].includes(badge.toLowerCase()),
    bestFor: textValue(attrs.field_best_for),
    features: listValues(attrs.field_features),
    whatsIncluded: listValues(attrs.field_whats_included),
    headline: textValue(attrs.field_hero_headline) || attrs.title || 'Package',
    subheadline: textValue(attrs.field_hero_subheadline),
    ctaText: textValue(attrs.field_cta_text) || 'Get Started',
    ctaHref: linkHref(attrs.field_cta_link),
    addons: resolveIncluded(node, included, 'field_addons').map((addon, i) => ({
      id: addon.id ?? i,
      name: paraField(addon, ['field_addon_name', 'field_name', 'field_title']),
      description: paraField(addon, ['field_addon_description', 'field_description']),
      price: paraField(addon, ['field_addon_price', 'field_price']),
    })),
    metaTitle: textValue(attrs.field_meta_title),
    metaDescription: textValue(attrs.field_meta_description),
  };
}

/* ------------------------------------------------------------------ */
/* homepage                                                            */
/* ------------------------------------------------------------------ */

/**
 * homepage node → props for the HomePage composition
 * (hero + stats + why + process + service area + final CTA).
 */
export function transformHomepageNode(node, included = []) {
  if (!node) return null;
  const attrs = node.attributes ?? {};

  return {
    id: node.id,
    headline: textValue(attrs.field_hero_headline),
    subheadline: textValue(attrs.field_hero_subheadline),
    primaryCta: {
      label: textValue(attrs.field_cta_primary_text) || 'Start Your Project',
      href: linkHref(attrs.field_cta_primary_link, '/contact'),
    },
    secondaryCta: {
      label: textValue(attrs.field_cta_secondary_text) || 'See Our Work',
      href: linkHref(attrs.field_cta_secondary_link, '/work'),
    },
    stats: resolveIncluded(node, included, 'field_stats_items')
      .map((item) => ({
        id: item.id,
        value: paraField(item, ['field_metric_value', 'field_value']),
        label: paraField(item, ['field_metric_label', 'field_label']),
      }))
      .filter((item) => item.value || item.label),
    whyTitle: textValue(attrs.field_why_title),
    whyItems: resolveIncluded(node, included, 'field_why_items').map((item, i) => ({
      id: item.id ?? i,
      title: paraField(item, ['field_why_title', 'field_title']),
      body: paraField(item, ['field_why_body', 'field_body', 'field_description']),
    })),
    processTitle: textValue(attrs.field_process_title),
    processSteps: resolveIncluded(node, included, 'field_process_steps').map(processStep),
    serviceAreaTitle: textValue(attrs.field_service_area_title),
    serviceAreaCities: listValues(attrs.field_service_area_cities),
    finalCta: {
      title: textValue(attrs.field_final_cta_title),
      bodyHtml: textValue(attrs.field_final_cta_body),
    },
  };
}

/* ------------------------------------------------------------------ */
/* faq_item                                                            */
/* ------------------------------------------------------------------ */

/**
 * faq_item nodes → flat list of { id, question, answer, category }, sorted
 * by field_sort_order. The category is a taxonomy term resolved from
 * `included` (pass include: 'field_faq_category' to getNodesRaw).
 */
export function transformFaqNodes(nodes = [], included = []) {
  const termNames = new Map(
    (included ?? [])
      .filter((r) => String(r.type).startsWith('taxonomy_term--'))
      .map((r) => [r.id, textValue(r.attributes?.name)]),
  );

  return (nodes ?? [])
    .map((node, i) => {
      const attrs = node.attributes ?? {};
      const termRef = node.relationships?.field_faq_category?.data;
      const termId = Array.isArray(termRef) ? termRef[0]?.id : termRef?.id;
      return {
        id: node.id ?? i,
        sortOrder: Number(attrs.field_sort_order) || 0,
        question: textValue(attrs.field_question) || attrs.title || `Question ${i + 1}`,
        answer: textValue(attrs.field_answer) || textValue(attrs.body),
        category: termNames.get(termId) || textValue(attrs.field_faq_category) || 'General',
      };
    })
    .sort((a, b) => a.sortOrder - b.sortOrder);
}

/* ------------------------------------------------------------------ */
/* case_study                                                          */
/* ------------------------------------------------------------------ */

/** case_study node → props for WorkHubPage cards / CaseStudyPage. */
export function transformCaseStudyNode(node) {
  if (!node) return null;
  const attrs = node.attributes ?? {};

  return {
    id: node.id,
    slug: nodeSlug(node),
    title: attrs.title ?? 'Case Study',
    projectType: textValue(attrs.field_project_type),
    subtitle: textValue(attrs.field_subtitle) || textValue(attrs.field_summary),
    results: listValues(attrs.field_results),
    bodyHtml: textValue(attrs.body),
    summary:
      textValue(attrs.field_summary) ||
      textValue(attrs.body?.summary) ||
      textValue(attrs.field_subtitle),
  };
}

/* ------------------------------------------------------------------ */
/* blog_post (also tolerates article nodes)                            */
/* ------------------------------------------------------------------ */

/** blog_post/article node → props for BlogHubPage cards / BlogPostPage. */
export function transformBlogNode(node) {
  if (!node) return null;
  const attrs = node.attributes ?? {};
  const created = attrs.created ?? null;

  return {
    id: node.id,
    slug: nodeSlug(node) || titleSlug(attrs.title),
    title: attrs.title ?? 'Untitled post',
    summary: textValue(attrs.field_summary) || textValue(attrs.body?.summary),
    bodyHtml: textValue(attrs.body),
    created,
    dateLabel: created
      ? new Date(created).toLocaleDateString(undefined, {
          year: 'numeric',
          month: 'short',
          day: 'numeric',
        })
      : '',
  };
}

/* ------------------------------------------------------------------ */
/* generic page (about / contact / splash)                             */
/* ------------------------------------------------------------------ */

/** page node → hero + body + CTA props (used by ContactPage / AliasPage). */
export function transformPageNode(node) {
  if (!node) return null;
  const attrs = node.attributes ?? {};

  return {
    id: node.id,
    title: attrs.title ?? 'Page',
    pageType: textValue(attrs.field_page_type),
    headline: textValue(attrs.field_hero_headline) || attrs.title || '',
    subheadline: textValue(attrs.field_hero_subheadline),
    bodyHtml: textValue(attrs.body),
    cta: ctaValue(attrs.field_cta_link, {
      label: textValue(attrs.field_cta_text),
      href: '/contact',
    }),
    ctaText: textValue(attrs.field_cta_text),
  };
}
