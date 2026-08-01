const SITE_URL = 'https://famtasticdesigns.com';
const SITE_NAME = 'FAMtastic Designs';
const DEFAULT_IMAGE = `${SITE_URL}/og-image.jpg`;
const DEFAULT_KEYWORDS = 'custom website design, AI solutions, business applications, web development, digital transformation, agentic AI';

export const SEO_PAGES = {
  '/': {
    title: 'FAMtastic Designs | Agentic AI Business Solutions Engineering Studio',
    description:
      'FAMtastic Designs is an agentic AI business solutions engineering studio. We build custom websites, intelligent applications, AI-powered systems, and digital experiences that grow businesses worldwide.',
    ogDescription:
      'We engineer intelligent systems that capture leads, automate operations, and grow businesses — worldwide. Custom websites, AI applications, and digital solutions.',
    twitterDescription:
      'We engineer intelligent systems that capture leads, automate operations, and grow businesses — worldwide.',
  },
  '/services': {
    title: 'Services | Custom AI Business Solutions Engineering',
    description:
      'Explore custom websites, intelligent applications, AI-powered systems, and digital experiences engineered by FAMtastic Designs for businesses worldwide.',
  },
  '/packages': {
    title: 'Packages | Custom Website & AI Solution Builds',
    description:
      'Review FAMtastic Designs packages for custom websites, business applications, AI-powered systems, and digital solution builds.',
  },
  '/work': {
    title: 'Work | FAMtastic Designs Case Studies',
    description:
      'See FAMtastic Designs project examples, case studies, and custom AI business solutions engineered for real outcomes.',
  },
  '/blog': {
    title: 'Blog | Agentic AI, Web Engineering & Digital Solutions',
    description:
      'Read practical notes from FAMtastic Designs on agentic AI, custom websites, business applications, digital transformation, and solution engineering.',
  },
  '/faq': {
    title: 'FAQ | FAMtastic Designs Questions Answered',
    description:
      'Get answers about FAMtastic Designs pricing, process, timelines, discovery builds, custom websites, AI systems, and support.',
  },
  '/contact': {
    title: 'Request a Quote | FAMtastic Designs',
    description:
      'Request a quote from FAMtastic Designs for a custom website, intelligent application, AI-powered system, or digital business solution.',
  },
  '/start': {
    title: 'Start Your Project | FAMtastic Designs',
    description:
      'Use the FAMtastic Designs Solution Finder to describe your custom website, application, AI-powered system, or digital solution project.',
  },
  '/199': {
    title: 'Professional Website for $199 | FAMtastic Designs',
    description:
      'Get a custom, mobile-ready website for your business for a one-time $199. Lead capture form, SEO foundation, one revision, and launch support included.',
    ogDescription:
      'A custom, mobile-ready website for your business — one-time $199, launch support included. No monthly surprises.',
    twitterDescription:
      'A custom, mobile-ready website for your business — one-time $199, launch support included.',
    keywords:
      'affordable website design, $199 website, small business website, cheap website design, mobile responsive website, local business web design',
  },
};

/**
 * Structured-data blocks are keyed by element id so a page can add its own
 * without disturbing another's, and so the prerendered shell and the runtime
 * update the same node instead of emitting duplicates for crawlers to
 * reconcile.
 */
export const OFFER_JSONLD_ID = 'famtastic-offer-jsonld';
export const FAQ_JSONLD_ID = 'famtastic-faq-jsonld';
export const POST_JSONLD_ID = 'famtastic-post-jsonld';

/** Upsert a JSON-LD script tag by id. Safe to call on every render. */
export function applyJsonLd(id, data) {
  if (typeof document === 'undefined') return;
  let node = document.getElementById(id);
  if (!node) {
    node = document.createElement('script');
    node.type = 'application/ld+json';
    node.id = id;
    document.head.appendChild(node);
  }
  node.textContent = JSON.stringify(data);
}

export function removeJsonLd(id) {
  if (typeof document === 'undefined') return;
  document.getElementById(id)?.remove();
}

/**
 * FAQPage markup for /faq. Google can surface these as expandable results,
 * which is why the questions are worth marking up rather than just rendering.
 */
export function faqPageJsonLd(faqs = []) {
  return {
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    mainEntity: faqs
      .filter((item) => item.question && item.answer)
      .map((item) => ({
        '@type': 'Question',
        name: item.question,
        acceptedAnswer: { '@type': 'Answer', text: item.answer },
      })),
  };
}

/** BlogPosting markup for a single post. */
export function blogPostingJsonLd(post, slug) {
  return {
    '@context': 'https://schema.org',
    '@type': 'BlogPosting',
    headline: post?.title ?? 'FAMtastic Designs',
    description: post?.summary ?? '',
    datePublished: post?.created ?? undefined,
    mainEntityOfPage: { '@type': 'WebPage', '@id': `${SITE_URL}/blog/${slug}/` },
    author: { '@type': 'Organization', name: SITE_NAME, url: SITE_URL },
    publisher: { '@type': 'Organization', name: SITE_NAME, url: SITE_URL },
    image: DEFAULT_IMAGE,
  };
}

/**
 * Per-post metadata. Without this every blog post inherited the homepage
 * title, description, and canonical URL — which reads to a search engine as
 * one page duplicated N times.
 */
export function blogSeo(post, slug) {
  const title = post?.title ? `${post.title} | FAMtastic Designs` : 'Blog | FAMtastic Designs';
  const description =
    post?.summary ||
    `Notes from FAMtastic Designs on websites, SEO, and getting small businesses online.`;
  return {
    siteName: SITE_NAME,
    title,
    description,
    ogDescription: description,
    twitterDescription: description,
    keywords: DEFAULT_KEYWORDS,
    canonical: `${SITE_URL}/blog/${slug}/`,
    image: DEFAULT_IMAGE,
    path: `/blog/${slug}`,
  };
}

export function offerJsonLd() {
  return {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: 'FAMtastic Basic Website',
    description:
      'A custom, mobile-responsive website for your business including core pages, a lead capture form, a basic SEO foundation, one revision, and launch support.',
    brand: { '@type': 'Brand', name: SITE_NAME },
    url: `${SITE_URL}/199/`,
    image: DEFAULT_IMAGE,
    offers: {
      '@type': 'Offer',
      price: '199.00',
      priceCurrency: 'USD',
      availability: 'https://schema.org/InStock',
      url: `${SITE_URL}/199/`,
      seller: { '@type': 'Organization', name: SITE_NAME, url: SITE_URL },
    },
  };
}

export function applyOfferJsonLd() {
  applyJsonLd(OFFER_JSONLD_ID, offerJsonLd());
}

export function removeOfferJsonLd() {
  removeJsonLd(OFFER_JSONLD_ID);
}

export function normalizePath(pathname = '/') {
  const path = pathname.split('?')[0].split('#')[0].replace(/\/+$/, '') || '/';
  return SEO_PAGES[path] ? path : '/';
}

export function seoForPath(pathname = '/') {
  const path = normalizePath(pathname);
  const page = SEO_PAGES[path] || SEO_PAGES['/'];
  const canonical = `${SITE_URL}${path === '/' ? '/' : `${path}/`}`;
  return {
    siteName: SITE_NAME,
    title: page.title,
    description: page.description,
    ogDescription: page.ogDescription || page.description,
    twitterDescription: page.twitterDescription || page.ogDescription || page.description,
    keywords: page.keywords || DEFAULT_KEYWORDS,
    canonical,
    image: DEFAULT_IMAGE,
    path,
  };
}

export function serviceSeo(service, slug) {
  const title = service?.metaTitle || `${service?.title || 'Custom Website Design'} | FAMtastic Designs`;
  const description =
    service?.metaDescription ||
    `${service?.headline || service?.title || 'Custom website design'} engineered for your business by FAMtastic Designs.`;
  return {
    siteName: SITE_NAME,
    title,
    description,
    ogDescription: description,
    twitterDescription: description,
    keywords: DEFAULT_KEYWORDS,
    canonical: `${SITE_URL}/services/${slug}/`,
    image: DEFAULT_IMAGE,
    path: `/services/${slug}`,
  };
}

export function packageSeo(plan, slug) {
  const title = plan?.metaTitle || `${plan?.title || 'Website Package'} | FAMtastic Designs`;
  const description =
    plan?.metaDescription ||
    `${plan?.title || 'Custom website package'} for businesses that need a clear path from idea to launched digital solution.`;
  return {
    siteName: SITE_NAME,
    title,
    description,
    ogDescription: description,
    twitterDescription: description,
    keywords: DEFAULT_KEYWORDS,
    canonical: `${SITE_URL}/packages/${slug}/`,
    image: DEFAULT_IMAGE,
    path: `/packages/${slug}`,
  };
}

export function proofSeo(campaign, token) {
  const businessName = campaign?.business_name || 'Your Business';
  const canonical = token ? `${SITE_URL}/p/${token}` : SITE_URL;
  return {
    siteName: SITE_NAME,
    title: `${businessName} — Free Website Preview | FAMtastic Designs`,
    description: `See 3 custom website designs for ${businessName}. Pick your favorite and choose a launch package.`,
    ogDescription: `Your free website preview from FAMtastic Designs: 3 custom design directions built for ${businessName}.`,
    twitterDescription: `See 3 custom website designs for ${businessName}. Pick your favorite and choose a launch package.`,
    keywords: DEFAULT_KEYWORDS,
    canonical,
    image: DEFAULT_IMAGE,
    path: '/p',
  };
}
