import { useState } from 'react';
import { useParams, Link, useNavigate } from 'react-router';
import { postIntake, collectUtmParams } from '../api/pipeline.js';
import SEO from '../components/SEO.jsx';
import '../portal.css';

export const INTAKE_CONFIGS = {
  'hosting-domain': {
    slug: 'hosting-domain',
    badge: '🌐 Infrastructure & Cloud Setup',
    title: 'Managed Hosting & Custom Domain Intake',
    subtitle: 'Provide your domain and hosting preferences so our team can provision your high-speed cloud environment, SSL certificate, and DNS routing.',
    packageSku: 'FAM-FOOT-199',
    packageTitle: 'Cloud Hosting & Domain Setup',
    price: 199,
    steps: [
      {
        id: 'domain_info',
        title: 'Domain & Registrar',
        fields: [
          {
            name: 'domain_choice',
            label: 'Do you need a new domain or already have one?',
            type: 'chips',
            options: [
              { value: 'new_domain', label: 'I need a new domain (.com/.org/.net)' },
              { value: 'existing_domain', label: 'I already own my domain' },
              { value: 'undecided', label: 'I need advice choosing a domain' },
            ],
            required: true,
          },
          {
            name: 'target_domain',
            label: 'Domain name (e.g. yourbusiness.com)',
            type: 'text',
            placeholder: 'yourbusiness.com (or your top choice)',
            required: true,
          },
          {
            name: 'registrar_info',
            label: 'Where is your domain registered? (if already owned)',
            type: 'chips',
            options: [
              { value: 'godaddy', label: 'GoDaddy' },
              { value: 'namecheap', label: 'Namecheap' },
              { value: 'google', label: 'Google Domains / Squarespace' },
              { value: 'cloudflare', label: 'Cloudflare' },
              { value: 'other', label: 'Other / Not sure' },
            ],
          },
        ],
      },
      {
        id: 'infrastructure_needs',
        title: 'Hosting & Business Email',
        fields: [
          {
            name: 'email_needs',
            label: 'Do you need professional business email addresses?',
            type: 'chips',
            options: [
              { value: 'google_workspace', label: 'Google Workspace (Gmail for Business)' },
              { value: 'microsoft_365', label: 'Microsoft 365 / Outlook' },
              { value: 'forwarding', label: 'Simple email forwarding (free)' },
              { value: 'none', label: 'Already have email set up' },
            ],
            required: true,
          },
          {
            name: 'mailboxes_list',
            label: 'Desired email accounts (e.g. info@, support@, fritz@)',
            type: 'textarea',
            placeholder: 'info@yourbusiness.com, sales@yourbusiness.com',
          },
          {
            name: 'migration_status',
            label: 'Are we migrating an existing website or starting fresh?',
            type: 'chips',
            options: [
              { value: 'fresh', label: 'Fresh website setup' },
              { value: 'migration', label: 'Migrate existing site content & files' },
              { value: 'redirection', label: 'Keep current site until new one is ready' },
            ],
            required: true,
          },
        ],
      },
    ],
  },
  'ai-chatbot': {
    slug: 'ai-chatbot',
    badge: '🤖 AI Business Agent',
    title: 'AI Chatbot & Automation Intake',
    subtitle: 'Define your business knowledge, customer support workflows, and escalation rules so we can train and deploy your custom AI agent.',
    packageSku: 'FAM-AI-6999',
    packageTitle: 'Custom AI Agent & Automation',
    price: 999,
    steps: [
      {
        id: 'bot_goals',
        title: 'Agent Objectives & Knowledge',
        fields: [
          {
            name: 'primary_agent_purpose',
            label: 'What is the primary role of this AI agent?',
            type: 'chips',
            options: [
              { value: 'support_faq', label: '24/7 Customer Support & FAQ Answering' },
              { value: 'lead_triage', label: 'Lead Qualification & Intake Capture' },
              { value: 'booking', label: 'Appointment & Consultation Booking' },
              { value: 'product_recommender', label: 'Product & Pricing Recommender' },
            ],
            required: true,
          },
          {
            name: 'knowledge_sources',
            label: 'Knowledge sources (Website URLs, FAQ documents, or service menus)',
            type: 'textarea',
            placeholder: 'https://example.com/services, PDF links, or paste common customer questions here...',
            required: true,
          },
          {
            name: 'tone_of_voice',
            label: 'Desired conversation style & tone',
            type: 'chips',
            options: [
              { value: 'professional', label: 'Professional & Polished' },
              { value: 'friendly', label: 'Warm, Friendly & Conversational' },
              { value: 'bold', label: 'Direct, Energetic & High-Impact' },
              { value: 'concise', label: 'Concise & Technical' },
            ],
            required: true,
          },
        ],
      },
      {
        id: 'bot_escalation',
        title: 'Escalation & Tool Integration',
        fields: [
          {
            name: 'escalation_method',
            label: 'How should the AI hand off complex inquiries to humans?',
            type: 'chips',
            options: [
              { value: 'email_alert', label: 'Instant Email Notification' },
              { value: 'sms_alert', label: 'Instant SMS / Text Alert' },
              { value: 'crm_webhook', label: 'Direct CRM / Webhook Sync' },
              { value: 'ticket', label: 'Create Support Ticket in Portal' },
            ],
            required: true,
          },
          {
            name: 'escalation_contact',
            label: 'Escalation contact (Email address or phone number for alerts)',
            type: 'text',
            placeholder: 'alerts@yourbusiness.com or (555) 000-0000',
            required: true,
          },
          {
            name: 'booking_link',
            label: 'Scheduling tool link (Calendly, Square, Acuity, etc. - optional)',
            type: 'text',
            placeholder: 'https://calendly.com/your-business/meeting',
          },
        ],
      },
    ],
  },
  'client-portal': {
    slug: 'client-portal',
    badge: '💼 Custom Web Application',
    title: 'Client Portal & Web App Intake',
    subtitle: 'Specify your user roles, dashboard workflows, file sharing, and database requirements for your custom business portal.',
    packageSku: 'FAM-GROWTH-3999',
    packageTitle: 'Custom Client Portal System',
    price: 3999,
    steps: [
      {
        id: 'portal_users',
        title: 'User Roles & Core Modules',
        fields: [
          {
            name: 'user_personas',
            label: 'Who will log into this portal?',
            type: 'chips',
            options: [
              { value: 'clients', label: 'Paying Clients & Customers' },
              { value: 'internal_team', label: 'Internal Staff & Employees' },
              { value: 'contractors', label: 'Vendors & Subcontractors' },
              { value: 'all_above', label: 'Multi-Role System (Clients + Team + Admins)' },
            ],
            required: true,
          },
          {
            name: 'core_features',
            label: 'What key capabilities must the portal have?',
            type: 'textarea',
            placeholder: 'e.g. Secure client document uploads, invoice history, project approval tracking, direct support messaging, downloadable reports...',
            required: true,
          },
          {
            name: 'auth_preference',
            label: 'Authentication method',
            type: 'chips',
            options: [
              { value: 'magic_link', label: 'Passwordless Email Magic Links (Fast & Secure)' },
              { value: 'password_2fa', label: 'Standard Password + 2FA' },
              { value: 'oauth', label: 'Google / Microsoft SSO' },
            ],
            required: true,
          },
        ],
      },
      {
        id: 'portal_integrations',
        title: 'Payments & System Integrations',
        fields: [
          {
            name: 'payment_processing',
            label: 'Will clients pay bills or purchase services inside the portal?',
            type: 'chips',
            options: [
              { value: 'stripe', label: 'Stripe (Credit Cards, Apple Pay, ACH)' },
              { value: 'quickbooks', label: 'QuickBooks / Invoice sync' },
              { value: 'cashapp_qr', label: 'Owner QR Code (Direct to your Cash App / Zelle)' },
              { value: 'none', label: 'No payments needed' },
            ],
            required: true,
          },
          {
            name: 'third_party_tools',
            label: 'Existing databases or APIs to connect (optional)',
            type: 'textarea',
            placeholder: 'CRM, Google Sheets, Airtable, ERP, or legacy database details...',
          },
        ],
      },
    ],
  },
  'maintenance': {
    slug: 'maintenance',
    badge: '🛠️ Care & Operations',
    title: 'Website Care & Maintenance Intake',
    subtitle: 'Share your current site setup so we can conduct a full health audit and establish automated backups, security patching, and speed tuning.',
    packageSku: 'FAM-MAINTENANCE-149',
    packageTitle: 'Website Care & Maintenance',
    price: 149,
    steps: [
      {
        id: 'site_diagnostics',
        title: 'Current Website Setup',
        fields: [
          {
            name: 'existing_site_url',
            label: 'Current Website URL',
            type: 'text',
            placeholder: 'https://yourwebsite.com',
            required: true,
          },
          {
            name: 'cms_platform',
            label: 'What platform is the website built on?',
            type: 'chips',
            options: [
              { value: 'wordpress', label: 'WordPress / WooCommerce' },
              { value: 'drupal', label: 'Drupal' },
              { value: 'shopify', label: 'Shopify' },
              { value: 'custom_react', label: 'Custom React / Node / Vite' },
              { value: 'squarespace_wix', label: 'Squarespace or Wix' },
              { value: 'other', label: 'Other / Not sure' },
            ],
            required: true,
          },
          {
            name: 'maintenance_priorities',
            label: 'What is your top maintenance priority?',
            type: 'chips',
            options: [
              { value: 'security_updates', label: 'Security patches & plugin/module updates' },
              { value: 'speed_optimization', label: 'Page speed & Core Web Vitals optimization' },
              { value: 'content_changes', label: 'Regular content, image, & pricing updates' },
              { value: 'emergency_recovery', label: 'Fixing current broken features or malware' },
            ],
            required: true,
          },
        ],
      },
      {
        id: 'hosting_access',
        title: 'Hosting & Access Details',
        fields: [
          {
            name: 'current_host',
            label: 'Where is your website currently hosted?',
            type: 'text',
            placeholder: 'e.g. GoDaddy, Bluehost, WP Engine, SiteGround, AWS...',
          },
          {
            name: 'update_frequency',
            label: 'How often do you need updates or new content added?',
            type: 'chips',
            options: [
              { value: 'weekly', label: 'Weekly / Ongoing active updates' },
              { value: 'monthly', label: 'Monthly maintenance & checkup' },
              { value: 'as_needed', label: 'As-needed emergency on-call support' },
            ],
            required: true,
          },
          {
            name: 'known_issues',
            label: 'Any current errors, broken links, or speed complaints?',
            type: 'textarea',
            placeholder: 'Describe any current issues or features that need attention...',
          },
        ],
      },
    ],
  },
  'website-launch': {
    slug: 'website-launch',
    badge: '⚡ Solutions Studio Architecture',
    title: 'Custom Website Launch Intake',
    subtitle: 'Give us the blueprint of your vision. We will architect your custom layout, interactive working concepts, and complete go-live roadmap.',
    packageSku: 'FAM-CUSTOM-1999',
    packageTitle: 'Custom Website Architecture',
    price: 1999,
    steps: [
      {
        id: 'website_scope',
        title: 'Project Goals & Structure',
        fields: [
          {
            name: 'website_goal',
            label: 'What is the primary goal of this website?',
            type: 'chips',
            options: [
              { value: 'generate_leads', label: 'Generate calls, quote requests, & qualified leads' },
              { value: 'ecommerce', label: 'Sell products or digital downloads directly online' },
              { value: 'brand_authority', label: 'Establish high-end brand authority & portfolio' },
              { value: 'booking_appointments', label: 'Client self-scheduling & appointment booking' },
            ],
            required: true,
          },
          {
            name: 'page_structure',
            label: 'Estimated pages & key sections needed',
            type: 'textarea',
            placeholder: 'e.g. Home, About, Services/Pricing, Case Studies/Gallery, FAQ, Contact & Booking...',
            required: true,
          },
          {
            name: 'famtastic_intensity',
            label: 'Visual intensity & feel',
            type: 'chips',
            options: [
              { value: 'clean_minimal', label: 'Clean, Minimal & Professional' },
              { value: 'modern_balanced', label: 'Modern, Dynamic & Polished (Recommended)' },
              { value: 'cinematic_bold', label: 'Cinematic, Rich Motion & Maximum FAMtastic' },
            ],
            required: true,
          },
        ],
      },
      {
        id: 'website_brand',
        title: 'Brand Inspiration & Timing',
        fields: [
          {
            name: 'reference_sites',
            label: 'Websites you like or admire (competitors or inspiration)',
            type: 'textarea',
            placeholder: 'https://example1.com, https://example2.com — what you like about them...',
          },
          {
            name: 'brand_colors',
            label: 'Brand colors & style preferences (hex codes or color names)',
            type: 'text',
            placeholder: 'e.g. Forest green, gold, charcoal, sleek dark mode...',
          },
          {
            name: 'desired_launch',
            label: 'Target launch timeframe',
            type: 'chips',
            options: [
              { value: 'urgent_7_days', label: 'Urgent (Within 7-10 days)' },
              { value: 'standard_2_weeks', label: 'Standard (Within 2-3 weeks)' },
              { value: 'flexible', label: 'Flexible / Next month' },
            ],
            required: true,
          },
        ],
      },
    ],
  },
};

export default function SpecializedIntakePage() {
  const { serviceSlug } = useParams();
  const navigate = useNavigate();
  const config = INTAKE_CONFIGS[serviceSlug] || INTAKE_CONFIGS['website-launch'];

  const [currentStepIndex, setCurrentStepIndex] = useState(0);
  const [formData, setFormData] = useState({});
  const [contact, setContact] = useState({ name: '', businessName: '', email: '', phone: '', notes: '' });
  const [status, setStatus] = useState('idle'); // idle | submitting | success | error
  const [errorMessage, setErrorMessage] = useState('');
  const [requestId, setRequestId] = useState('');

  const totalSteps = config.steps.length + 1; // config steps + 1 final contact step
  const isFinalStep = currentStepIndex === config.steps.length;

  const handleFieldChange = (name, value) => {
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const handleContactChange = (e) => {
    const { name, value } = e.target;
    setContact((prev) => ({ ...prev, [name]: value }));
  };

  const handleNext = (e) => {
    e.preventDefault();
    if (currentStepIndex < config.steps.length) {
      // Validate required fields in current step
      const currentFields = config.steps[currentStepIndex].fields;
      for (const field of currentFields) {
        if (field.required && !formData[field.name]) {
          setErrorMessage(`Please complete "${field.label}" before proceeding.`);
          return;
        }
      }
      setErrorMessage('');
      setCurrentStepIndex((prev) => prev + 1);
    }
  };

  const handleBack = () => {
    setErrorMessage('');
    if (currentStepIndex > 0) {
      setCurrentStepIndex((prev) => prev - 1);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!contact.email || !contact.email.includes('@')) {
      setErrorMessage('Please enter a valid email address.');
      return;
    }
    setErrorMessage('');
    setStatus('submitting');

    const payload = {
      source: `famtastic-intake-${config.slug}`,
      branch: config.packageSku,
      name: contact.name,
      email: contact.email,
      phone: contact.phone,
      business_name: contact.businessName || contact.name,
      answers: {
        intakeType: config.slug,
        intakeTitle: config.title,
        selectedService: config.packageTitle,
        businessName: contact.businessName,
        contactName: contact.name,
        email: contact.email,
        phone: contact.phone,
        notes: contact.notes,
        technicalSpecs: formData,
        recommendedPackage: config.packageTitle,
      },
      estimate: {
        low: config.price,
        high: config.price,
      },
      utm: {
        ...collectUtmParams(),
        path: window.location.pathname,
        timestamp: new Date().toISOString(),
        referrer: document.referrer || null,
      },
    };

    try {
      const res = await postIntake(payload);
      setRequestId(res?.request_id || res?.token || 'FAM-' + Math.floor(100000 + Math.random() * 900000));
      setStatus('success');
    } catch (err) {
      setStatus('error');
      setErrorMessage('There was a problem submitting your intake. Please try again or contact us directly at hello@famtasticdesigns.com.');
    }
  };

  return (
    <main className="portal-main" style={{ maxWidth: '840px', margin: '0 auto', padding: '2rem 1.25rem 4rem' }}>
      <SEO
        title={`${config.title} | FAMtastic Solutions Studio`}
        description={config.subtitle}
      />

      <nav style={{ marginBottom: '1.5rem', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
        <Link to="/intake" style={{ color: '#7cfc00', textDecoration: 'none', fontSize: '0.88rem', fontWeight: '700' }}>
          ← All Specialized Intake Forms
        </Link>
        <span style={{ fontSize: '0.8rem', color: '#8e988e' }}>Step {currentStepIndex + 1} of {totalSteps}</span>
      </nav>

      {status === 'success' ? (
        <section className="portal-panel" style={{ textAlign: 'center', padding: '2.5rem 1.5rem', border: '1px solid #7cfc00', borderRadius: '18px', background: 'linear-gradient(145deg, rgba(124,252,0,0.06), #080a08)' }}>
          <div style={{ width: '64px', height: '64px', margin: '0 auto 1.25rem', borderRadius: '50%', background: 'rgba(124,252,0,0.15)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1.8rem', color: '#7cfc00' }}>
            ✓
          </div>
          <span style={{ color: '#7cfc00', fontSize: '0.78rem', fontWeight: '800', textTransform: 'uppercase', letterSpacing: '0.12em' }}>
            Intake Received &amp; Queued
          </span>
          <h1 style={{ margin: '0.5rem 0 1rem', fontSize: '2rem' }}>Thank You, {contact.name || 'Friend'}!</h1>
          <p style={{ maxWidth: '580px', margin: '0 auto 1.5rem', color: '#b5c0b5', fontSize: '1rem', lineHeight: '1.55' }}>
            We have captured your specific requirements for <strong>{config.title}</strong>. Our solutions engineering team is reviewing your specifications now.
          </p>

          <div style={{ maxWidth: '420px', margin: '0 auto 2rem', padding: '1rem', borderRadius: '12px', background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.08)' }}>
            <small style={{ display: 'block', color: '#8e988e', fontSize: '0.78rem', textTransform: 'uppercase', letterSpacing: '0.08em' }}>Intake Reference</small>
            <strong style={{ color: '#7cfc00', fontSize: '1.25rem', fontFamily: 'monospace' }}>{requestId}</strong>
          </div>

          <div style={{ display: 'flex', gap: '1rem', justifyContent: 'center', flexWrap: 'wrap' }}>
            <button type="button" onClick={() => navigate('/portal')} style={{ minHeight: '46px', padding: '0.75rem 1.5rem', background: '#7cfc00', color: '#000', fontWeight: '800', border: 'none', borderRadius: '9px', cursor: 'pointer' }}>
              Open Customer Portal →
            </button>
            <Link to="/" style={{ minHeight: '46px', padding: '0.75rem 1.5rem', border: '1px solid #465046', borderRadius: '9px', color: '#fff', textDecoration: 'none', display: 'inline-flex', alignItems: 'center', fontWeight: '700' }}>
              Return Home
            </Link>
          </div>
        </section>
      ) : (
        <section className="portal-panel" style={{ padding: '2rem', borderRadius: '18px', border: '1px solid var(--p-line, #283228)', background: '#0a0d0a' }}>
          <header style={{ marginBottom: '1.75rem', borderBottom: '1px solid rgba(255,255,255,0.08)', paddingBottom: '1.25rem' }}>
            <span style={{ color: '#7cfc00', fontSize: '0.78rem', fontWeight: '800', textTransform: 'uppercase', letterSpacing: '0.1em' }}>
              {config.badge}
            </span>
            <h1 style={{ margin: '0.35rem 0 0.5rem', fontSize: '1.85rem' }}>{config.title}</h1>
            <p style={{ margin: 0, color: '#9da79d', fontSize: '0.92rem', lineHeight: '1.5' }}>{config.subtitle}</p>
          </header>

          {/* Progress bar */}
          <div style={{ height: '4px', width: '100%', background: 'rgba(255,255,255,0.08)', borderRadius: '2px', margin: '0 0 1.75rem', overflow: 'hidden' }}>
            <div style={{ height: '100%', width: `${((currentStepIndex + 1) / totalSteps) * 100}%`, background: '#7cfc00', transition: 'width 0.3s ease' }} />
          </div>

          {errorMessage && (
            <div style={{ padding: '0.85rem 1rem', marginBottom: '1.5rem', borderRadius: '10px', background: 'rgba(255,80,80,0.12)', border: '1px solid #ff5050', color: '#ff8080', fontSize: '0.88rem' }}>
              {errorMessage}
            </div>
          )}

          {!isFinalStep ? (
            <form onSubmit={handleNext}>
              <h2 style={{ fontSize: '1.25rem', marginBottom: '1.25rem', color: '#e0e8e0' }}>
                {config.steps[currentStepIndex].title}
              </h2>

              <div style={{ display: 'grid', gap: '1.35rem' }}>
                {config.steps[currentStepIndex].fields.map((field) => (
                  <div key={field.name}>
                    <label style={{ display: 'block', fontWeight: '700', fontSize: '0.9rem', marginBottom: '0.5rem', color: '#d0dad0' }}>
                      {field.label} {field.required && <span style={{ color: '#7cfc00' }}>*</span>}
                    </label>

                    {field.type === 'chips' ? (
                      <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.5rem' }}>
                        {field.options.map((opt) => {
                          const isSelected = formData[field.name] === opt.value;
                          return (
                            <button
                              key={opt.value}
                              type="button"
                              onClick={() => handleFieldChange(field.name, opt.value)}
                              style={{
                                padding: '0.65rem 1rem',
                                borderRadius: '10px',
                                border: isSelected ? '1px solid #7cfc00' : '1px solid rgba(255,255,255,0.12)',
                                background: isSelected ? 'rgba(124,252,0,0.12)' : 'rgba(255,255,255,0.03)',
                                color: isSelected ? '#7cfc00' : '#c8d4c8',
                                fontSize: '0.85rem',
                                fontWeight: isSelected ? '800' : '500',
                                cursor: 'pointer',
                                transition: 'all 0.15s ease',
                              }}
                            >
                              {isSelected ? '✓ ' : ''}{opt.label}
                            </button>
                          );
                        })}
                      </div>
                    ) : field.type === 'textarea' ? (
                      <textarea
                        name={field.name}
                        value={formData[field.name] || ''}
                        onChange={(e) => handleFieldChange(field.name, e.target.value)}
                        placeholder={field.placeholder || ''}
                        rows={3}
                        style={{ width: '100%', padding: '0.75rem', borderRadius: '10px', border: '1px solid rgba(255,255,255,0.15)', background: '#101410', color: '#fff', fontSize: '0.9rem', font: 'inherit', boxSizing: 'border-box' }}
                      />
                    ) : (
                      <input
                        type="text"
                        name={field.name}
                        value={formData[field.name] || ''}
                        onChange={(e) => handleFieldChange(field.name, e.target.value)}
                        placeholder={field.placeholder || ''}
                        style={{ width: '100%', height: '44px', padding: '0 0.75rem', borderRadius: '10px', border: '1px solid rgba(255,255,255,0.15)', background: '#101410', color: '#fff', fontSize: '0.9rem', boxSizing: 'border-box' }}
                      />
                    )}
                  </div>
                ))}
              </div>

              <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '2rem', paddingTop: '1.25rem', borderTop: '1px solid rgba(255,255,255,0.08)' }}>
                {currentStepIndex > 0 ? (
                  <button type="button" onClick={handleBack} style={{ padding: '0.65rem 1.25rem', border: '1px solid #465046', background: 'transparent', color: '#c8d4c8', borderRadius: '9px', cursor: 'pointer', fontWeight: '700' }}>
                    ← Back
                  </button>
                ) : <div />}
                <button type="submit" style={{ padding: '0.65rem 1.5rem', border: 'none', background: '#7cfc00', color: '#000', borderRadius: '9px', cursor: 'pointer', fontWeight: '800' }}>
                  Next Step →
                </button>
              </div>
            </form>
          ) : (
            <form onSubmit={handleSubmit}>
              <h2 style={{ fontSize: '1.25rem', marginBottom: '1.25rem', color: '#e0e8e0' }}>
                Where should we send your project specification &amp; confirmation?
              </h2>

              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '1rem', marginBottom: '1.25rem' }}>
                <div>
                  <label style={{ display: 'block', fontWeight: '700', fontSize: '0.9rem', marginBottom: '0.4rem', color: '#d0dad0' }}>
                    Your Name <span style={{ color: '#7cfc00' }}>*</span>
                  </label>
                  <input
                    type="text"
                    name="name"
                    value={contact.name}
                    onChange={handleContactChange}
                    placeholder="Jane Doe"
                    required
                    style={{ width: '100%', height: '44px', padding: '0 0.75rem', borderRadius: '10px', border: '1px solid rgba(255,255,255,0.15)', background: '#101410', color: '#fff', fontSize: '0.9rem', boxSizing: 'border-box' }}
                  />
                </div>
                <div>
                  <label style={{ display: 'block', fontWeight: '700', fontSize: '0.9rem', marginBottom: '0.4rem', color: '#d0dad0' }}>
                    Business / Brand Name
                  </label>
                  <input
                    type="text"
                    name="businessName"
                    value={contact.businessName}
                    onChange={handleContactChange}
                    placeholder="Acme Co."
                    style={{ width: '100%', height: '44px', padding: '0 0.75rem', borderRadius: '10px', border: '1px solid rgba(255,255,255,0.15)', background: '#101410', color: '#fff', fontSize: '0.9rem', boxSizing: 'border-box' }}
                  />
                </div>
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '1rem', marginBottom: '1.25rem' }}>
                <div>
                  <label style={{ display: 'block', fontWeight: '700', fontSize: '0.9rem', marginBottom: '0.4rem', color: '#d0dad0' }}>
                    Email Address <span style={{ color: '#7cfc00' }}>*</span>
                  </label>
                  <input
                    type="email"
                    name="email"
                    value={contact.email}
                    onChange={handleContactChange}
                    placeholder="jane@example.com"
                    required
                    style={{ width: '100%', height: '44px', padding: '0 0.75rem', borderRadius: '10px', border: '1px solid rgba(255,255,255,0.15)', background: '#101410', color: '#fff', fontSize: '0.9rem', boxSizing: 'border-box' }}
                  />
                </div>
                <div>
                  <label style={{ display: 'block', fontWeight: '700', fontSize: '0.9rem', marginBottom: '0.4rem', color: '#d0dad0' }}>
                    Phone Number (Optional)
                  </label>
                  <input
                    type="tel"
                    name="phone"
                    value={contact.phone}
                    onChange={handleContactChange}
                    placeholder="(555) 000-0000"
                    style={{ width: '100%', height: '44px', padding: '0 0.75rem', borderRadius: '10px', border: '1px solid rgba(255,255,255,0.15)', background: '#101410', color: '#fff', fontSize: '0.9rem', boxSizing: 'border-box' }}
                  />
                </div>
              </div>

              <div style={{ marginBottom: '1.5rem' }}>
                <label style={{ display: 'block', fontWeight: '700', fontSize: '0.9rem', marginBottom: '0.4rem', color: '#d0dad0' }}>
                  Anything else Fritz &amp; team should know? (Optional)
                </label>
                <textarea
                  name="notes"
                  value={contact.notes}
                  onChange={handleContactChange}
                  placeholder="Special timeline requirements, links to existing brand assets, or specific questions..."
                  rows={3}
                  style={{ width: '100%', padding: '0.75rem', borderRadius: '10px', border: '1px solid rgba(255,255,255,0.15)', background: '#101410', color: '#fff', fontSize: '0.9rem', font: 'inherit', boxSizing: 'border-box' }}
                />
              </div>

              <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '2rem', paddingTop: '1.25rem', borderTop: '1px solid rgba(255,255,255,0.08)' }}>
                <button type="button" onClick={handleBack} disabled={status === 'submitting'} style={{ padding: '0.65rem 1.25rem', border: '1px solid #465046', background: 'transparent', color: '#c8d4c8', borderRadius: '9px', cursor: 'pointer', fontWeight: '700' }}>
                  ← Back
                </button>
                <button type="submit" disabled={status === 'submitting'} style={{ padding: '0.75rem 1.75rem', border: 'none', background: '#7cfc00', color: '#000', borderRadius: '9px', cursor: 'pointer', fontWeight: '800' }}>
                  {status === 'submitting' ? 'Submitting Specifications…' : 'Submit Project Intake →'}
                </button>
              </div>
            </form>
          )}
        </section>
      )}
    </main>
  );
}
