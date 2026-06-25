type PublicRuntimeConfig = {
  paymentMode?: string;
  bookingProvider?: string;
  bookingUrl?: string;
  bookingEmbedUrl?: string;
  caldiyUrl?: string;
  calendsoUrl?: string;
  easyappointmentsUrl?: string;
  paypalCheckoutUrl?: string;
  paypalInvoiceUrl?: string;
  paypalCarePlanUrl?: string;
  stripeAuditPaymentLink?: string;
};

type SiteSettings = {
  bookingLink?: string;
  auditPaymentLink?: string;
};

const SAFE_BOOKING_FALLBACK = '/get-started';
const SAFE_PAYMENT_FALLBACK = '/get-started?intent=payment-options';
const SAFE_AUDIT_FALLBACK = '/get-started?intent=audit';

export function resolveBookingLink(config: PublicRuntimeConfig, site?: SiteSettings) {
  const provider = (config.bookingProvider || '').toLowerCase();
  const configured = config.bookingUrl || site?.bookingLink || '';

  if (provider === 'external_url' && configured) return configured;
  if (provider === 'caldiy' && (config.caldiyUrl || configured)) return config.caldiyUrl || configured;
  if (provider === 'calendso' && (config.calendsoUrl || configured)) return config.calendsoUrl || configured;
  if (provider === 'easyappointments' && (config.easyappointmentsUrl || configured)) return config.easyappointmentsUrl || configured;
  if (provider === 'manual' || provider === 'mock' || !provider) return SAFE_BOOKING_FALLBACK;
  return configured || SAFE_BOOKING_FALLBACK;
}

export function resolveAuditPaymentLink(config: PublicRuntimeConfig, site?: SiteSettings) {
  if ((config.paymentMode || 'mock') === 'mock') return SAFE_AUDIT_FALLBACK;
  return config.paypalCheckoutUrl || site?.auditPaymentLink || config.stripeAuditPaymentLink || SAFE_AUDIT_FALLBACK;
}

export function resolvePaymentOptionsLink(config: PublicRuntimeConfig) {
  if ((config.paymentMode || 'mock') === 'mock') return SAFE_PAYMENT_FALLBACK;
  return config.paypalInvoiceUrl || config.paypalCheckoutUrl || SAFE_PAYMENT_FALLBACK;
}

export function isExternalLink(href: string) {
  return /^https?:\/\//.test(href) || href.startsWith('mailto:') || href.startsWith('tel:');
}
