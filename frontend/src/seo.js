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
  '/about': {
    title: 'About FAMtastic Designs | Systems Built Around Your Business',
    description: 'Meet FAMtastic Designs, an engineering-led studio building useful websites, customer systems, automation, commerce, analytics, and AI experiences.',
  },
  '/55-cents-a-day-website': {
    title: '$199 Website | About 55 Cents a Day | FAMtastic Designs',
    description: 'Get a professional one-page business website for $199—about 55 cents a day across one year—with first-year basic hosting and a domain path included.',
    keywords: '$199 website, 55 cents a day website, affordable small business website, domain and hosting included, one page website',
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
    title: 'Blogs | Agentic AI, Web Engineering & Digital Solutions',
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
};

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

export function blogSeo(post) {
  const title = post?.metaTitle || `${post?.title || 'Article'} | FAMtastic Designs`;
  const description = post?.metaDescription || post?.summary || SEO_PAGES['/blog'].description;
  return {
    siteName: SITE_NAME,
    title,
    description,
    ogDescription: description,
    twitterDescription: description,
    keywords: [DEFAULT_KEYWORDS, ...(post?.tags ?? [])].join(', '),
    canonical: `${SITE_URL}/blog/${post?.slug || ''}/`,
    image: DEFAULT_IMAGE,
    path: `/blog/${post?.slug || ''}`,
    type: 'article',
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
