import { useRuntimeConfig } from '#imports';
import { getBaseFamtasticContent } from '../../data/famtastic/content';
import { applyAdminOverrides, isAdminProofEnabled } from './admin-proof';
import { fetchDirectusCollection } from './directus';

function toArray(value: unknown) {
  return Array.isArray(value) ? value : [];
}

function mapDirectusContent(payload: Record<string, any>) {
  const base = getBaseFamtasticContent();
  const settings = payload.site_settings?.[0] || {};
  const homepage = payload.homepage?.[0] || {};
  const navigation = payload.navigation || [];
  const legalPages = payload.legal_pages || [];
  const seoPages = payload.seo_pages || [];

  const legalBySlug = Object.fromEntries(legalPages.map((item: any) => [item.slug, item]));
  const seoBySlug = Object.fromEntries(seoPages.map((item: any) => [item.slug, item]));

  return {
    ...base,
    siteSettings: {
      ...base.siteSettings,
      siteName: settings.site_name || base.siteSettings.siteName,
      domain: settings.domain || base.siteSettings.domain,
      contactEmail: settings.contact_email || base.siteSettings.contactEmail,
      phone: settings.phone || base.siteSettings.phone,
      bookingLink: settings.booking_link || base.siteSettings.bookingLink,
      auditPaymentLink: settings.audit_payment_link || base.siteSettings.auditPaymentLink,
    },
    homepage: {
      ...base.homepage,
      heroEyebrow: homepage.hero_eyebrow || base.homepage.heroEyebrow,
      heroHeadline: homepage.hero_headline || base.homepage.heroHeadline,
      heroSubheadline: homepage.hero_subheadline || base.homepage.heroSubheadline,
      primaryCtaLabel: homepage.primary_cta_label || base.homepage.primaryCtaLabel,
      primaryCtaHref: homepage.primary_cta_url || base.homepage.primaryCtaHref,
      secondaryCtaLabel: homepage.secondary_cta_label || base.homepage.secondaryCtaLabel,
      secondaryCtaHref: homepage.secondary_cta_url || base.homepage.secondaryCtaHref,
      finalCtaHeadline: homepage.final_cta_headline || base.homepage.finalCtaHeadline,
    },
    navigation: navigation.length
      ? navigation.map((item: any) => ({ label: item.label, to: item.href || item.to }))
      : base.navigation,
    services: payload.services?.length
      ? payload.services.map((item: any) => ({
          title: item.title,
          slug: item.slug,
          shortDescription: item.short_description,
          deliverables: toArray(item.deliverables || item.bullets),
          cta: item.cta_label || 'Get Started',
        }))
      : base.services,
    packages: payload.pricing_packages?.length
      ? payload.pricing_packages.map((item: any) => ({
          packageName: item.package_name || item.name,
          priceLabel: item.price_label,
          description: item.description,
          bestFor: item.best_for,
          features: toArray(item.features),
          cta: item.cta_label || 'Get Started',
          sortOrder: item.sort || 0,
          highlighted: Boolean(item.highlighted),
        }))
      : base.packages,
    addons: payload.addons?.length
      ? payload.addons.map((item: any) => ({ name: item.name, description: item.description, priceLabel: item.price_label, cta: item.cta_label || 'Learn More' }))
      : base.addons,
    portfolio: payload.portfolio_projects?.length
      ? payload.portfolio_projects.map((item: any) => ({
          title: item.title,
          projectType: item.project_type,
          summary: item.summary,
          resultLabel: item.result_label,
          demoOrReal: item.demo_or_real || 'demo',
          status: item.status || 'draft',
        }))
      : base.portfolio,
    testimonials: payload.testimonials?.length
      ? payload.testimonials.map((item: any) => ({
          quote: item.quote,
          name: item.name,
          titleCompany: item.title_company || item.title || '',
          realOrDemo: item.real_or_demo || 'expectation',
          status: item.status || 'published',
        }))
      : base.testimonials,
    faqs: payload.faqs?.length
      ? payload.faqs.map((item: any) => ({ question: item.question, answer: item.answer }))
      : base.faqs,
    legal: {
      privacyPolicy: legalBySlug['privacy-policy'] || base.legal.privacyPolicy,
      termsOfService: legalBySlug['terms-of-service'] || base.legal.termsOfService,
      cookiePolicy: legalBySlug['cookie-policy'] || base.legal.cookiePolicy,
    },
    seo: {
      ...base.seo,
      pages: {
        ...base.seo.pages,
        home: seoBySlug.home || base.seo.pages.home,
        services: seoBySlug.services || base.seo.pages.services,
        packages: seoBySlug.packages || base.seo.pages.packages,
        getStarted: seoBySlug['get-started'] || base.seo.pages.getStarted,
        contact: seoBySlug.contact || base.seo.pages.contact,
      },
    },
  };
}

export async function getServerCmsContent() {
  const config = useRuntimeConfig();
  const mode = config.public.cmsMode || 'local';
  const base = getBaseFamtasticContent();

  if (mode !== 'directus' || !config.directusUrl) {
    return isAdminProofEnabled() ? await applyAdminOverrides(base) : base;
  }

  try {
    const [site_settings, homepage, navigation, services, pricing_packages, addons, faqs, portfolio_projects, testimonials, legal_pages, seo_pages] = await Promise.all([
      fetchDirectusCollection('site_settings', { limit: 1 }),
      fetchDirectusCollection('homepage', { limit: 1 }),
      fetchDirectusCollection('navigation', { sort: ['sort'] }),
      fetchDirectusCollection('services', { sort: ['sort'] }),
      fetchDirectusCollection('pricing_packages', { sort: ['sort'] }),
      fetchDirectusCollection('addons', { sort: ['sort'] }),
      fetchDirectusCollection('faqs', { sort: ['sort'] }),
      fetchDirectusCollection('portfolio_projects', { sort: ['sort'] }),
      fetchDirectusCollection('testimonials', { sort: ['sort'] }),
      fetchDirectusCollection('legal_pages', { sort: ['sort'] }),
      fetchDirectusCollection('seo_pages', { sort: ['sort'] }),
    ]);

    return isAdminProofEnabled() ? await applyAdminOverrides(mapDirectusContent({ site_settings, homepage, navigation, services, pricing_packages, addons, faqs, portfolio_projects, testimonials, legal_pages, seo_pages })) : mapDirectusContent({ site_settings, homepage, navigation, services, pricing_packages, addons, faqs, portfolio_projects, testimonials, legal_pages, seo_pages });
  } catch (error) {
    console.error('[cms] Directus mode failed, falling back to local content.', error);
    return isAdminProofEnabled() ? await applyAdminOverrides(base) : base;
  }
}
