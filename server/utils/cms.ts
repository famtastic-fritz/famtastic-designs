import { getLocalFamtasticContent } from '~/app/utils/cms';
import { fetchDirectusCollection } from '~/server/utils/directus';

function toArray(value: unknown) {
  if (Array.isArray(value)) return value;
  return [];
}

function mapDirectusContent(payload: Record<string, any>) {
  const local = getLocalFamtasticContent();
  const settings = payload.site_settings?.[0] || {};
  const homepage = payload.homepage?.[0] || {};

  return {
    ...local,
    company: {
      ...local.company,
      name: settings.site_name || local.company.name,
      domain: settings.domain || local.company.domain,
      email: settings.contact_email || local.company.email,
      phone: settings.phone || local.company.phone,
      tagline: homepage.hero_subheadline || local.company.tagline,
    },
    serviceCards: payload.services?.length
      ? payload.services.map((item: any) => ({
          title: item.title,
          sentence: item.short_description,
          deliverables: toArray(item.bullets),
          cta: `/services#${item.slug || item.title?.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`,
        }))
      : local.serviceCards,
    pricingPackages: payload.pricing_packages?.length
      ? payload.pricing_packages.map((item: any) => ({
          name: item.name,
          price: item.price_label,
          description: item.description,
          features: toArray(item.features),
          cta: item.cta_label || 'Get Started',
          href: item.cta_url || '/get-started',
          featured: Boolean(item.highlighted),
        }))
      : local.pricingPackages,
    faqItems: payload.faqs?.length
      ? payload.faqs.map((item: any) => ({ q: item.question, a: item.answer }))
      : local.faqItems,
    featuredWork: payload.portfolio_projects?.length
      ? payload.portfolio_projects.map((item: any) => ({
          title: item.title,
          type: item.project_type,
          summary: item.summary,
          result: item.result_label,
          demoLabel: item.demo_label || 'Demo / placeholder',
        }))
      : local.featuredWork,
    testimonials: payload.testimonials?.length
      ? payload.testimonials.map((item: any) => ({
          quote: item.quote,
          name: item.name,
          title: item.title,
          company: item.company,
          rating: item.rating,
        }))
      : local.testimonials,
  };
}

export async function getServerCmsContent() {
  const config = useRuntimeConfig();
  const mode = config.public.cmsMode;

  if (mode === 'local' || !config.directusUrl) {
    return getLocalFamtasticContent();
  }

  try {
    const [site_settings, homepage, services, pricing_packages, faqs, portfolio_projects, testimonials] = await Promise.all([
      fetchDirectusCollection('site_settings', { limit: 1 }),
      fetchDirectusCollection('homepage', { limit: 1 }),
      fetchDirectusCollection('services', { sort: ['sort'], filter: { status: { _eq: 'published' } } }),
      fetchDirectusCollection('pricing_packages', { sort: ['sort'], filter: { status: { _eq: 'published' } } }),
      fetchDirectusCollection('faqs', { sort: ['sort'], filter: { status: { _eq: 'published' } } }),
      fetchDirectusCollection('portfolio_projects', { sort: ['sort'], filter: { status: { _eq: 'published' } } }),
      fetchDirectusCollection('testimonials', { sort: ['sort'], filter: { status: { _eq: 'published' } } }),
    ]);

    return mapDirectusContent({ site_settings, homepage, services, pricing_packages, faqs, portfolio_projects, testimonials });
  } catch (error) {
    console.error('[cms] Directus mode failed, falling back to local content.', error);
    return getLocalFamtasticContent();
  }
}
