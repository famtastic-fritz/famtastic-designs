import { siteSettings } from './site';
import { homepageContent } from './homepage';
import { navigationItems, footerLinks } from './navigation';
import { serviceItems } from './services';
import { packageItems } from './packages';
import { addonItems } from './addons';
import { portfolioItems, portalPreviewItems } from './portfolio';
import { testimonialsOrExpectations, leadStatuses } from './testimonials';
import { faqItems } from './faqs';
import { legalContent } from './legal';
import { seoContent } from './seo';

export function getBaseFamtasticContent() {
  return {
    siteSettings,
    homepage: homepageContent,
    navigation: navigationItems,
    footerLinks,
    services: serviceItems,
    packages: packageItems,
    addons: addonItems,
    portfolio: portfolioItems,
    testimonials: testimonialsOrExpectations,
    faqs: faqItems,
    legal: legalContent,
    seo: seoContent,
    leadStatuses,
    platformStrip: [
      'WordPress',
      'Shopify',
      'Webflow',
      'Custom websites',
      'Landing pages',
      'CMS/admin setups',
      'Automations',
      'Booking/payment integrations',
    ],
    process: homepageContent.process,
    portalPreview: portalPreviewItems,
    paymentPlaceholders: {
      auditOffer: 'Website Audit / Strategy Session',
      auditPrice: '$149–$297',
      auditLink: '/get-started?intent=audit',
      auditPaymentOptions: ['PayPal checkout/payment link', 'PayPal invoice/payment flow', 'audit/deposit payment'],
      carePlanPaymentOptions: ['PayPal invoice/payment flow', 'care plan/monthly payment path'],
      carePlans: [
        { name: 'Care Plan', price: '$149/mo' },
        { name: 'Growth Plan', price: '$299/mo' },
        { name: 'Partner Plan', price: '$599+/mo' },
      ],
    },
  };
}
