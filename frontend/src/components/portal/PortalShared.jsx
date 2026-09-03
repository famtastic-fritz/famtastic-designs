export const GROUPS = [
  ['Workspace', [
    ['home', 'Home'],
    ['projects', 'My Projects'],
    ['services', 'Services & Add-ons'],
    ['files', 'Files & Assets'],
    ['results', 'Growth & Analytics'],
  ]],
  ['Communications & AI', [
    ['messages', 'Messages'],
    ['shay', 'Shay AI Advisor'],
    ['support', 'Support'],
  ]],
  ['Knowledge & Growth', [
    ['faq', 'Knowledge & FAQs'],
    ['grow', 'Growth Ideas'],
    ['referrals', 'Referrals'],
  ]],
  ['Account & Billing', [
    ['billing', 'Billing & Orders'],
    ['settings', 'Settings & Alerts'],
    ['account', 'Profile & Team'],
  ]],
];

export const LABELS = Object.fromEntries(GROUPS.flatMap(([, items]) => items));

export const title = (value) =>
  String(value || 'Preparing')
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase());

export const money = (amount = 0, currency = 'usd') =>
  new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: (currency || 'USD').toUpperCase(),
  }).format(amount / 100);

export const date = (stamp) =>
  stamp ? new Date(Number(stamp) * 1000).toLocaleDateString() : 'Not scheduled';

export function Panel({ eyebrow, title: heading, children, className = '', id, tabIndex }) {
  return (
    <article id={id} tabIndex={tabIndex} className={`portal-panel ${className}`}>
      {eyebrow && <span>{eyebrow}</span>}
      {heading && <h2>{heading}</h2>}
      {children}
    </article>
  );
}

export function Empty({ children }) {
  return <p className="portal-empty">{children}</p>;
}

export function serviceMeta(type) {
  switch (type) {
    case 'hosting':
    case 'basic_hosting':
    case 'hosting_included_year':
    case 'hosting_recurring':
      return {
        title: 'Managed Cloud Hosting & SSL',
        desc: 'High-speed NVMe cloud hosting, automated daily backups, global CDN, and auto-renewing SSL certificate.',
        target: 'projects',
        btn: 'View Project',
        icon: '☁️',
      };
    case 'hosting_business_included_year':
    case 'hosting_business_recurring':
      return {
        title: 'Business Managed Cloud Hosting & CDN',
        desc: 'Dedicated resource allocation, daily snapshots, high availability, edge caching, and SSL.',
        target: 'projects',
        btn: 'View Project',
        icon: '⚡',
      };
    case 'domain_choice':
    case 'domain_registration':
      return {
        title: 'Custom Domain Registration & Privacy',
        desc: '1-Year custom business domain registration (.com/.org/.net) with DNS routing and WHOIS privacy.',
        target: 'projects',
        btn: 'Configure Domain',
        icon: '🌐',
      };
    case 'domain_connection':
      return {
        title: 'Custom Domain DNS Connection',
        desc: 'DNS routing connecting your existing registrar (GoDaddy, Namecheap, Cloudflare) to FAMtastic cloud.',
        target: 'projects',
        btn: 'View DNS Setup',
        icon: '🔗',
      };
    case 'website':
    case 'website_service':
    case 'starter_website':
    case 'custom_website':
    case 'business_website_service':
      return {
        title: 'Custom Website System',
        desc: 'Tailored architecture, 3 to 6 interactive concepts, mobile-responsive layout, and SEO metadata.',
        target: 'projects',
        btn: 'Project Hub',
        icon: '✨',
      };
    case 'lead_capture':
    case 'lead_automation':
      return {
        title: 'Lead Capture & Notification Engine',
        desc: 'Instant email & SMS lead notifications, CRM webhook routing, and auto-response dispatch.',
        target: 'services',
        btn: 'View Workflow',
        icon: '📥',
      };
    case 'ai_site_agent':
      return {
        title: 'AI Website Concierge Assistant',
        desc: 'Sourced customer-facing chatbot trained exclusively on your approved business services and FAQs.',
        target: 'shay',
        btn: 'AI Knowledge',
        icon: '🤖',
      };
    case 'customer_analytics':
    case 'analytics_connection':
      return {
        title: 'Monthly Growth Analytics & Telemetry',
        desc: 'GA4 and search conversion tracking with actionable monthly performance digests.',
        target: 'results',
        btn: 'View Telemetry',
        icon: '📊',
      };
    case 'foundational_seo':
    case 'local_seo':
      return {
        title: 'Local SEO & Schema Markup',
        desc: 'Structured business data, Google Search Console indexing, and local citations.',
        target: 'results',
        btn: 'SEO Insights',
        icon: '🎯',
      };
    case 'maintenance':
      return {
        title: 'Ongoing Managed Site Care',
        desc: 'Monthly core updates, security scans, uptime monitoring, and small content revisions.',
        target: 'support',
        btn: 'Get Support',
        icon: '🛡️',
      };
    default:
      return {
        title: title(type),
        desc: 'Active business system entitlement connected to your customer workspace.',
        target: 'messages',
        btn: 'Manage',
        icon: '📦',
      };
  }
}
