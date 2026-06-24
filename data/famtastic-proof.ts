import { getBaseFamtasticContent } from './famtastic/content';

const base = getBaseFamtasticContent();

export const company = base.siteSettings;
export const nav = base.navigation;
export const serviceCards = base.services;
export const pricingPackages = base.packages;
export const monthlyPlans = base.paymentPlaceholders.carePlans;
export const faqItems = base.faqs;
export const featuredWork = base.portfolio;
export const portalPreview = base.portalPreview;
export const testimonials = base.testimonials;
export const leadStatuses = base.leadStatuses;