const WEBSITE_SERVICE_ENTITLEMENTS = new Set([
  'website_service',
  'business_website_service',
]);

const HOSTING_ENTITLEMENTS = new Set([
  'hosting',
  'hosting_included_year',
  'hosting_business_included_year',
]);

const DOMAIN_ENTITLEMENTS = new Set([
  'domain_choice',
]);

/**
 * Derive the homepage's website fulfillment facts from durable records.
 * An order alone is not evidence of a website, hosting, domain, or SSL.
 */
export function derivePortalFulfillmentState(workspace = {}) {
  const activeEntitlements = (workspace.entitlements || []).filter((item) => item.status === 'active');
  const entitlementTypes = new Set(activeEntitlements.map((item) => item.entitlement_type));
  const websiteRequests = workspace.website_requests || [];
  const projects = workspace.projects || [];
  const orders = workspace.orders || [];
  const paidOrder = orders.find((item) => item.payment_status === 'paid') || orders[0] || null;
  const hasWebsiteService = [...WEBSITE_SERVICE_ENTITLEMENTS].some((type) => entitlementTypes.has(type));
  const hasHosting = [...HOSTING_ENTITLEMENTS].some((type) => entitlementTypes.has(type));
  const hasDomain = [...DOMAIN_ENTITLEMENTS].some((type) => entitlementTypes.has(type));
  // Pre-purchase requests and drafts belong in Projects, not in an order
  // fulfillment banner. The homepage may claim provisioning only after the
  // durable website-service entitlement exists.
  const hasWebsiteWork = hasWebsiteService;

  return {
    show: hasWebsiteWork,
    packageName: paidOrder?.package || 'Website project',
    paymentConfirmed: paidOrder?.payment_status === 'paid',
    hasWebsiteService,
    hasHosting,
    hasDomain,
    hasProofWork: websiteRequests.length > 0 || projects.length > 0,
    hasLiveSite: projects.some((item) => Boolean(item.live_url)),
    hostingLabel: hasHosting ? 'Managed hosting entitlement active' : 'Hosting is not included in this purchase',
    domainLabel: hasDomain ? 'Domain choice recorded' : 'Domain choice still required',
  };
}
