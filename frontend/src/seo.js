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
    title: 'Our Work | FAMtastic Designs Creative Worlds',
    description:
      'Enter live launches, campaign worlds, and clearly labeled concept labs by FAMtastic Designs—digital experiences built with story, motion, systems, and purpose.',
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
  '/buy': {
    title: 'Secure Checkout | Start Your Project | FAMtastic Designs',
    description: 'Purchase your custom website package or digital system securely with first-year hosting and domain included.',
  },
  '/purchase': {
    title: 'Secure Checkout | Start Your Project | FAMtastic Designs',
    description: 'Purchase your custom website package or digital system securely with first-year hosting and domain included.',
  },
  '/pricing': {
    title: 'Packages & Pricing | FAMtastic Designs',
    description: 'Transparent fixed pricing for custom websites, AI applications, and digital business solutions.',
  },
  '/intake': {
    title: 'Direct Project Intake Forms | FAMtastic Solutions Studio',
    description: 'Specialized intake forms for Hosting & Domain Setup, AI Chatbots, Custom Client Portals, Website Care, and Custom Website Launches.',
  },
  '/intake/hosting-domain': {
    title: 'Hosting & Domain Setup Intake | FAMtastic Solutions Studio',
    description: 'Provide your domain and hosting preferences so our team can provision your high-speed cloud environment, SSL certificate, and DNS routing.',
  },
  '/intake/ai-chatbot': {
    title: 'AI Chatbot & Automation Intake | FAMtastic Solutions Studio',
    description: 'Define your business knowledge, customer support workflows, and escalation rules so we can train and deploy your custom AI agent.',
  },
  '/intake/client-portal': {
    title: 'Client Portal & Web App Intake | FAMtastic Solutions Studio',
    description: 'Specify your user roles, dashboard workflows, file sharing, and database requirements for your custom business portal.',
  },
  '/intake/maintenance': {
    title: 'Website Care & Maintenance Intake | FAMtastic Solutions Studio',
    description: 'Share your current site setup so we can conduct a full health audit and establish automated backups, security patching, and speed tuning.',
  },
  '/intake/website-launch': {
    title: 'Custom Website Launch Intake | FAMtastic Solutions Studio',
    description: 'Give us the blueprint of your vision. We will architect your custom layout, interactive working concepts, and complete go-live roadmap.',
  },
};

export function normalizePath(pathname = '/') {
  const path = pathname.split('?')[0].split('#')[0].replace(/\/+$/, '') || '/';
  return SEO_PAGES[path] ? path : '/';
}

export function seoForPath(pathname = '/') {
  if (pathname.startsWith('/deep-dive/')) {
    const description = 'Private, token-scoped website planning interview.';
    return {
      siteName: SITE_NAME,
      title: 'Private website planning interview | FAMtastic Designs',
      description,
      ogDescription: description,
      twitterDescription: description,
      keywords: DEFAULT_KEYWORDS,
      canonical: `${SITE_URL}/deep-dive/`,
      image: DEFAULT_IMAGE,
      path: '/deep-dive',
      robots: 'noindex, nofollow, noarchive',
      referrer: 'no-referrer',
    };
  }
  if (pathname.startsWith('/proofs/share/') || pathname.startsWith('/proofs/preview/')) {
    const description = 'An unlisted website concept review shared through FAMtastic Designs.';
    return {
      siteName: SITE_NAME,
      title: 'Unlisted Website Concept Review | FAMtastic Designs',
      description,
      ogDescription: description,
      twitterDescription: description,
      keywords: DEFAULT_KEYWORDS,
      canonical: `${SITE_URL}/proofs/share/`,
      image: DEFAULT_IMAGE,
      path: '/proofs/share',
      robots: 'noindex, nofollow, noarchive',
      referrer: 'no-referrer',
    };
  }
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
