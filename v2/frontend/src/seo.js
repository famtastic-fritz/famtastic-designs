const SITE_URL = 'https://famtasticdesigns.com';
const SITE_NAME = 'FAMtastic Designs';

export const SEO_PAGES = {
  '/': {
    title: 'FAMtastic Designs | AI Websites, Lead Capture & Automation',
    description:
      'FAMtastic Designs builds intelligent websites, lead capture systems, AI chatbots, and automation for growing businesses.',
  },
  '/services': {
    title: 'Services | AI Websites, Chatbots & Business Automation',
    description:
      'Explore FAMtastic Designs services for websites, AI chatbots, lead capture, client portals, and business automation systems.',
  },
  '/packages': {
    title: 'Packages | Fixed-Scope Website & Automation Builds',
    description:
      'Review fixed-scope FAMtastic Designs packages, starting with a $199 discovery build and clear next steps for your project.',
  },
  '/work': {
    title: 'Work | FAMtastic Designs Case Studies',
    description:
      'See FAMtastic Designs project examples, case studies, and intelligent systems built for real business outcomes.',
  },
  '/blog': {
    title: 'Blog | AI, Automation & Web Design Notes',
    description:
      'Read practical notes from FAMtastic Designs on AI systems, automation, websites, lead capture, and business growth.',
  },
  '/faq': {
    title: 'FAQ | FAMtastic Designs Questions Answered',
    description:
      'Get answers about FAMtastic Designs pricing, process, timelines, discovery builds, websites, automation, and support.',
  },
  '/contact': {
    title: 'Request a Quote | FAMtastic Designs',
    description:
      'Request a quote from FAMtastic Designs for a website, AI system, chatbot, lead capture workflow, or business automation project.',
  },
  '/start': {
    title: 'Start Your Project | FAMtastic Designs',
    description:
      'Use the FAMtastic Designs Solution Finder to describe your project and get a fast estimate for your website or automation build.',
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
    canonical,
    path,
  };
}
