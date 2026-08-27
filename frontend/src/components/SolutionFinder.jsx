import { useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router';
import { AnimatePresence, motion } from 'framer-motion';
import { collectUtmParams, getAiSolutionAdvice, postIntake } from '../api/pipeline.js';

const CONTACT_EMAIL = 'hello@famtasticdesigns.com';

/* ------------------------------------------------------------------ */
/* Branch catalog                                                     */
/* ------------------------------------------------------------------ */

const CHIPS = [
  { id: 'website', label: 'Website' },
  { id: 'app', label: 'Mobile App' },
  { id: 'chatbot', label: 'AI Chatbot' },
  { id: 'portal', label: 'Client Portal' },
  { id: 'custom', label: 'Custom System' },
  { id: 'unsure', label: "I'm Not Sure" },
];

const KEYWORDS = {
  website: ['website', 'web site', 'site', 'web', 'landing', 'page', 'restaurant', 'store', 'shop', 'blog', 'online presence'],
  app: ['app', 'mobile', 'ios', 'iphone', 'android'],
  chatbot: ['chatbot', 'chat', 'bot', 'ai', 'automation', 'automate', 'leads', 'answer'],
  portal: ['portal', 'client', 'clients', 'dashboard', 'login', 'members'],
  custom: ['system', 'custom', 'software', 'crm', 'workflow', 'integration', 'internal', 'tool'],
};

const PLACEHOLDERS = [
  'A website for my restaurant with online catering requests...',
  'A mobile app for my fitness clients...',
  'An AI chatbot to answer customer questions 24/7...',
  'A client portal where customers log in and upload files...',
  'A custom business growth system for my dental practice...',
];

const money = (n) => `$${n.toLocaleString('en-US')}`;
const range = (low, high) => `${money(low)} – ${money(high)}`;

/* ------------------------------------------------------------------ */
/* Branch step definitions                                            */
/* ------------------------------------------------------------------ */

const CONTACT_STEP = {
  title: 'Where should we send your quote?',
  fields: [
    { name: 'email', label: 'Email', type: 'email', required: true, autoComplete: 'email', placeholder: 'you@business.com' },
    { name: 'phone', label: 'Phone', type: 'tel', required: false, autoComplete: 'tel', placeholder: '(optional)' },
  ],
};

const BRANCHES = {
  website: {
    label: 'Website',
    steps: [
      {
        title: 'Tell us about your business',
        fields: [
          { name: 'businessName', label: 'Business name', type: 'text', required: true, autoComplete: 'organization' },
          { name: 'industry', label: 'Industry', type: 'text', required: true, placeholder: 'e.g. restaurant, landscaping, dental' },
          { name: 'location', label: 'Location', type: 'text', required: true, placeholder: 'City, State' },
          { name: 'businessModel', label: 'How does the business make money today?', type: 'textarea', required: true, placeholder: 'What you sell, how customers find you, and how they buy or book.' },
        ],
      },
      {
        title: 'Your current setup',
        fields: [
          {
            name: 'setup', label: 'Do you have a website today?', type: 'select', required: true,
            options: [
              { value: 'none', label: 'No site yet' },
              { value: 'old', label: 'Old site' },
              { value: 'redesign', label: 'Need a redesign' },
            ],
          },
          {
            name: 'pages', label: 'How many pages do you need?', type: 'select', required: true,
            options: [
              { value: '1', label: '1 page' },
              { value: '3-5', label: '3–5 pages' },
              { value: '5-10', label: '5–10 pages' },
              { value: '10+', label: '10+ pages' },
            ],
          },
          {
            name: 'brandStatus', label: 'Logo and brand status', type: 'select', required: true,
            options: [
              { value: 'ready', label: 'I have a logo / brand assets' },
              { value: 'no_logo_no_help', label: 'No logo, and I do not want one' },
              { value: 'help_needed', label: 'No logo, and I want help creating one' },
              { value: 'partial', label: 'I have some brand pieces' },
            ],
          },
        ],
      },
      {
        title: 'Domain, email & inspiration',
        fields: [
          {
            name: 'domainChoice', label: 'Domain situation', type: 'select', required: true,
            options: [
              { value: 'own_domain', label: 'I already own a domain' },
              { value: 'need_new_domain', label: 'I need a new domain registered (included in year 1)' },
              { value: 'undecided', label: 'Not sure yet' },
            ],
          },
          {
            name: 'businessEmail', label: 'Business email situation', type: 'select', required: true,
            options: [
              { value: 'have_email', label: 'I already have a professional email' },
              { value: 'need_setup', label: 'I need help setting up professional email' },
              { value: 'not_needed', label: 'Not needed right now' },
            ],
          },
          {
            name: 'referenceSites', label: 'Websites or brands you admire (optional)', type: 'textarea', required: false,
            placeholder: 'Paste links or describe the visual style you like...',
          },
        ],
      },
      {
        title: 'Features you need',
        fields: [
          {
            name: 'features', label: 'Check all that apply', type: 'multi', required: true,
            options: [
              { value: 'contact', label: 'Contact Form' },
              { value: 'booking', label: 'Online Booking / Scheduling' },
              { value: 'ecommerce', label: 'Online Store / Payments' },
              { value: 'blog', label: 'Blog / Articles' },
              { value: 'reviews', label: 'Customer Reviews / Testimonials' },
              { value: 'chat', label: 'Live Chat / AI Chatbot' },
              { value: 'none', label: 'Just the essentials' },
            ],
          },
        ],
      },
      CONTACT_STEP,
    ],
  },
  app: {
    label: 'Mobile App',
    steps: [
      {
        title: 'Tell us about your app idea',
        fields: [
          { name: 'businessName', label: 'Project or business name', type: 'text', required: true },
          { name: 'platforms', label: 'Platforms needed', type: 'select', required: true,
            options: [
              { value: 'both', label: 'iOS & Android (Cross-platform)' },
              { value: 'ios', label: 'iOS only' },
              { value: 'android', label: 'Android only' },
            ],
          },
          { name: 'appSummary', label: 'What is the core purpose of the app?', type: 'textarea', required: true, placeholder: 'Describe what users will do in the app...' },
        ],
      },
      {
        title: 'Core app features',
        fields: [
          {
            name: 'appFeatures', label: 'Key capabilities', type: 'multi', required: true,
            options: [
              { value: 'auth', label: 'User Accounts / Login' },
              { value: 'payments', label: 'In-App Payments / Subscriptions' },
              { value: 'push', label: 'Push Notifications' },
              { value: 'chat', label: 'In-App Messaging' },
              { value: 'maps', label: 'GPS / Location Services' },
              { value: 'offline', label: 'Offline Support' },
            ],
          },
        ],
      },
      CONTACT_STEP,
    ],
  },
  chatbot: {
    label: 'AI Chatbot',
    steps: [
      {
        title: 'Where will your AI chatbot live?',
        fields: [
          { name: 'businessName', label: 'Business name', type: 'text', required: true },
          {
            name: 'channels', label: 'Channels needed', type: 'multi', required: true,
            options: [
              { value: 'website', label: 'On my website' },
              { value: 'whatsapp', label: 'WhatsApp' },
              { value: 'instagram', label: 'Instagram / Messenger' },
              { value: 'internal', label: 'Internal Staff Assistant' },
            ],
          },
          { name: 'botGoal', label: 'What should the chatbot do?', type: 'textarea', required: true, placeholder: 'e.g. Answer FAQ, qualify leads, book appointments, check order status...' },
        ],
      },
      CONTACT_STEP,
    ],
  },
  portal: {
    label: 'Client Portal',
    steps: [
      {
        title: 'Tell us about your portal',
        fields: [
          { name: 'businessName', label: 'Business name', type: 'text', required: true },
          { name: 'userType', label: 'Who will log in?', type: 'select', required: true,
            options: [
              { value: 'clients', label: 'Clients / Customers' },
              { value: 'employees', label: 'Internal Employees' },
              { value: 'partners', label: 'Vendors / Partners' },
              { value: 'mixed', label: 'Both Clients and Staff' },
            ],
          },
          { name: 'portalGoal', label: 'What key actions will users take?', type: 'textarea', required: true, placeholder: 'e.g. View invoices, upload docs, track project progress...' },
        ],
      },
      CONTACT_STEP,
    ],
  },
  custom: {
    label: 'Custom System',
    steps: [
      {
        title: 'Describe your custom system',
        fields: [
          { name: 'businessName', label: 'Business name', type: 'text', required: true },
          { name: 'systemType', label: 'What type of system is this?', type: 'select', required: true,
            options: [
              { value: 'crm', label: 'Custom CRM / Lead Management' },
              { value: 'workflow', label: 'Internal Workflow Automation' },
              { value: 'api', label: 'API / Database Integration' },
              { value: 'other', label: 'Unique Proprietary Tool' },
            ],
          },
          { name: 'systemDetails', label: 'Tell us what you want to automate or build', type: 'textarea', required: true, placeholder: 'Describe your current manual process and desired outcome...' },
        ],
      },
      CONTACT_STEP,
    ],
  },
  unsure: {
    label: 'General Inquiry',
    steps: [
      {
        title: 'Tell us what you are trying to accomplish',
        fields: [
          { name: 'businessName', label: 'Business name', type: 'text', required: true },
          { name: 'goal', label: 'What is your main goal right now?', type: 'textarea', required: true, placeholder: 'Tell us what your business needs in plain English...' },
        ],
      },
      CONTACT_STEP,
    ],
  },
};

/* ------------------------------------------------------------------ */
/* Recommendation and Estimate Engine                                 */
/* ------------------------------------------------------------------ */

function recommendBranch(values) {
  const goal = (values.goal || '').toLowerCase();
  if (goal.includes('app') || goal.includes('mobile')) return 'app';
  if (goal.includes('chat') || goal.includes('bot') || goal.includes('ai')) return 'chatbot';
  if (goal.includes('portal') || goal.includes('dashboard') || goal.includes('login')) return 'portal';
  if (goal.includes('system') || goal.includes('custom') || goal.includes('crm')) return 'custom';
  return 'website';
}

/** Map a service slug (e.g. /services/ai-chatbot) to a branch id. */
export function branchForServiceSlug(slug = '') {
  const s = slug.toLowerCase();
  if (s.includes('chatbot') || s.includes('chat') || s === 'ai') return 'chatbot';
  if (s.includes('app') || s.includes('mobile')) return 'app';
  if (s.includes('portal')) return 'portal';
  if (s.includes('custom') || s.includes('system') || s.includes('automation') || s.includes('integration')) return 'custom';
  if (s.includes('site') || s.includes('web') || s.includes('design') || s.includes('seo')) return 'website';
  return null;
}


function computeEstimate(branch, values, recommended) {
  const target = branch === 'unsure' ? (recommended || 'website') : branch;
  let low = 499;
  let high = 1999;
  const includes = [];

  if (target === 'website') {
    if (values.pages === '1') {
      low = 199;
      high = 199;
      includes.push('One focused business page', '1st-year hosting included', 'Domain connection', 'Lead-capture form');
    } else if (values.pages === '3-5') {
      low = 499;
      high = 499;
      includes.push('Up to 5 business pages', 'Mobile-first responsive design', 'Local SEO & GA4', '1st-year hosting included');
    } else if (values.pages === '5-10') {
      low = 1999;
      high = 1999;
      includes.push('Up to 5 custom page designs', 'Brand discovery & architecture', 'Original visual design system', 'Conversion tracking');
    } else {
      low = 3999;
      high = 3999;
      includes.push('Business Growth System (up to 10 pages)', 'Booking / CRM integration', 'Lead automation workflows', 'Dual-grain UTM attribution');
    }
  } else if (target === 'chatbot') {
    low = 499;
    high = 1499;
    includes.push('AI chatbot setup & training', 'Website embed or WhatsApp connection', 'Lead capture & routing to owner');
  } else if (target === 'portal') {
    low = 3999;
    high = 6999;
    includes.push('Secure client portal authentication', 'Document upload & status tracking', 'Stripe customer integration');
  } else if (target === 'app') {
    low = 4999;
    high = 8999;
    includes.push('iOS & Android cross-platform app', 'Push notifications & auth', 'App store preparation');
  } else {
    low = 2999;
    high = 5999;
    includes.push('Custom workflow automation', 'API / database integration', 'Owner training & documentation');
  }

  return { low, high, includes, review: high >= 3999 };
}

/* ------------------------------------------------------------------ */
/* Main SolutionFinder Component                                      */
/* ------------------------------------------------------------------ */

export default function SolutionFinder({ initialBranch = null }) {
  const [stage, setStage] = useState(initialBranch && BRANCHES[initialBranch] ? 'branch' : 'entry');
  const [branch, setBranch] = useState(initialBranch && BRANCHES[initialBranch] ? initialBranch : null);
  const [stepIndex, setStepIndex] = useState(0);
  const [values, setValues] = useState({});
  const [errors, setErrors] = useState({});
  const [query, setQuery] = useState('');
  const [placeholderIdx, setPlaceholderIdx] = useState(0);
  const [status, setStatus] = useState('idle'); // idle | submitting | success | error
  const [submitError, setSubmitError] = useState(null);
  const [serverMessage, setServerMessage] = useState(null);
  const [requestId, setRequestId] = useState(null);
  const [registrationUrl, setRegistrationUrl] = useState(null);
  const [offlineEstimate, setOfflineEstimate] = useState(false);
  const searchRef = useRef(null);

  // AI Advisor State
  const [aiData, setAiData] = useState(null);
  const [aiLoading, setAiLoading] = useState(false);
  const [aiError, setAiError] = useState(null);

  // Cycling placeholder examples.
  useEffect(() => {
    const t = setInterval(() => setPlaceholderIdx((i) => (i + 1) % PLACEHOLDERS.length), 3200);
    return () => clearInterval(t);
  }, []);

  const steps = branch ? BRANCHES[branch].steps : [];
  const step = steps[stepIndex];
  const isLastStep = stepIndex === steps.length - 1;

  async function handleAiConsult(promptText, extraAnswers = {}) {
    const text = (promptText || query).trim();
    if (!text && Object.keys(extraAnswers).length === 0) return;
    setAiLoading(true);
    setAiError(null);
    setStage('ai_consult');

    try {
      const res = await getAiSolutionAdvice({
        prompt: text,
        answers: { ...values, ...extraAnswers },
        utm: collectUtmParams(),
      });
      if (res?.recommendation) {
        setAiData(res.recommendation);
      } else {
        throw new Error('Could not compute advice');
      }
    } catch {
      // Fallback locally
      const rec = recommendBranch({ goal: text, ...extraAnswers });
      const est = computeEstimate(rec, extraAnswers, rec);
      setAiData({
        package_sku: est.low === 199 ? 'web-basics' : est.low === 499 ? 'business-website' : est.low === 1999 ? 'custom-website' : 'business-growth',
        package_title: est.low === 199 ? 'Web Basics Bundle — $199' : est.low === 499 ? 'Business Website Bundle — $499' : est.low === 1999 ? 'Custom Website — $1,999' : 'Business Growth System — $3,999',
        price_estimate: est.low,
        price_formatted: money(est.low),
        timeline: 'Confirmed after intake',
        personalized_rationale: 'Tailored for your business requirements with hosting and domain included.',
        recommended_pages: est.includes.slice(0, 4),
        recommended_features: est.includes,
        follow_up_questions: [],
        scope_summary: 'Starter recommendation prepared by FAMtastic.',
      });
    } finally {
      setAiLoading(false);
    }
  }

  function startBranch(id, prefill = {}) {
    setBranch(id);
    setValues((prev) => ({ ...prefill, ...prev }));
    setStepIndex(0);
    setErrors({});
    setStatus('idle');
    setSubmitError(null);
    setServerMessage(null);
    setRequestId(null);
    setRegistrationUrl(null);
    setOfflineEstimate(false);
    setStage('branch');
  }

  function handleEnterKey(event) {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    if (query.trim()) {
      void handleAiConsult(query.trim());
    }
  }

  function setValue(name, value) {
    setValues((prev) => ({ ...prev, [name]: value }));
    setErrors((prev) => ({ ...prev, [name]: undefined }));
  }

  function toggleMulti(name, option) {
    const current = values[name] || [];
    let next;
    if (current.includes(option)) {
      next = current.filter((v) => v !== option);
    } else if (option === 'none') {
      next = ['none'];
    } else {
      next = [...current.filter((v) => v !== 'none'), option];
    }
    setValue(name, next);
  }

  function validateStep() {
    const next = {};
    for (const field of step.fields) {
      if (!field.required) continue;
      const val = values[field.name];
      if (field.type === 'multi') {
        if (!val || val.length === 0) next[field.name] = 'Pick at least one.';
      } else if (!val || !String(val).trim()) {
        next[field.name] = 'This field is required.';
      } else if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(val).trim())) {
        next[field.name] = 'A valid email is required.';
      }
    }
    setErrors(next);
    return Object.keys(next).length === 0;
  }

  function handleNext() {
    if (!validateStep()) return;
    if (!isLastStep) {
      setStepIndex((i) => i + 1);
      return;
    }
    void doSubmit();
  }

  function buildPayload() {
    const recommended = branch === 'unsure' ? recommendBranch(values) : null;
    const estimate = computeEstimate(branch, values, recommended);
    const payload = {
      source: 'solution-finder',
      branch,
      recommendedBranch: recommended,
      answers: values,
      estimate: { low: estimate.low, high: estimate.high },
      utm: {
        ...collectUtmParams(),
        path: window.location.pathname,
        branch,
        timestamp: new Date().toISOString(),
        referrer: document.referrer || null,
      },
    };
    return { payload, estimate, recommended };
  }

  async function doSubmit() {
    if (status === 'submitting' || requestId) return;
    setStatus('submitting');
    setSubmitError(null);
    setServerMessage(null);
    const { payload } = buildPayload();
    try {
      const res = await postIntake(payload);
      setRequestId(res?.request_id || null);
      setRegistrationUrl(res?.registration_url || null);
      setServerMessage(res?.message || 'We received your request. Your estimate is being prepared, and our team has been notified.');
      setStatus('success');
      setStage('result');
    } catch (err) {
      setStatus('error');
      setSubmitError(err?.message || 'Network error — please try again.');
    }
  }

  const result = useMemo(() => {
    if (stage !== 'result' && !offlineEstimate) return null;
    const { estimate, recommended } = buildPayload();
    return { estimate, recommended };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [stage, offlineEstimate]);

  const mailtoSummary = useMemo(() => {
    if (!result) return '#';
    const lines = [
      `Solution Finder request (offline fallback)`,
      `Branch: ${BRANCHES[branch]?.label || branch}`,
      '',
      ...Object.entries(values).map(([k, v]) => `${k}: ${Array.isArray(v) ? v.join(', ') : v}`),
    ];
    return `mailto:${CONTACT_EMAIL}?subject=${encodeURIComponent('Solution Finder inquiry')}&body=${encodeURIComponent(lines.join('\n'))}`;
  }, [result, branch, values]);

  /* ------------------------------ render ------------------------------ */

  return (
    <div className="sf">
      <AnimatePresence mode="wait">
        {stage === 'entry' && (
          <motion.div
            key="entry"
            initial={{ opacity: 0, y: 24 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -16 }}
            transition={{ duration: 0.45, ease: [0.22, 1, 0.36, 1] }}
          >
            <div style={{ textAlign: 'center' }}>
              <span className="sf__ai-badge">⚡ AI-Powered Project Advisor</span>
            </div>
            <h2 className="sf__title">What can we help you build?</h2>

            <div className="sf__search-wrap">
              <input
                ref={searchRef}
                type="text"
                className="sf__search"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                onKeyDown={handleEnterKey}
                placeholder={PLACEHOLDERS[placeholderIdx]}
                aria-label="Describe what you want to build in plain English"
              />
              <button
                type="button"
                className="sf__search-go"
                onClick={() => void handleAiConsult(query)}
                aria-label="Ask AI Advisor"
                title="Consult AI Advisor"
              >
                ✦
              </button>
            </div>

            <div className="sf__chips" role="group" aria-label="Solution types">
              {CHIPS.map((chip) => (
                <button
                  key={chip.id}
                  type="button"
                  className="sf__chip"
                  onClick={() => {
                    if (chip.id === 'unsure') {
                      startBranch('unsure');
                    } else {
                      void handleAiConsult(`I need a ${chip.label} for my business`);
                    }
                  }}
                >
                  {chip.label}
                </button>
              ))}
            </div>
            <p className="sf__hint">
              Describe your project in plain English or pick a category. Our Drupal AI advisor will analyze your requirements and recommend the exact package.
            </p>
          </motion.div>
        )}

        {stage === 'ai_consult' && (
          <motion.div
            key="ai_consult"
            initial={{ opacity: 0, y: 24 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -16 }}
            transition={{ duration: 0.45, ease: [0.22, 1, 0.36, 1] }}
          >
            {aiLoading && (
              <div className="sf__ai-loading">
                <div className="sf__ai-spinner" />
                <span className="sf__ai-badge">⚡ Drupal AI Engine</span>
                <h3>Analyzing your project requirements...</h3>
                <p className="sf__note">Evaluating your business scope against our 16-SKU catalog and pricing ladder.</p>
              </div>
            )}

            {!aiLoading && aiData && (
              <div className="sf__ai-card">
                <div className="sf__ai-header">
                  <div>
                    <span className="sf__ai-badge">⚡ Recommended by Drupal AI</span>
                    <h3>{aiData.package_title}</h3>
                    <small style={{ color: 'var(--fam-text-muted)' }}>Estimated Delivery: {aiData.timeline}</small>
                  </div>
                  <div className="sf__ai-price-pill">
                    {aiData.price_formatted}
                  </div>
                </div>

                <div className="sf__ai-rationale">
                  <strong>Why this fits your business:</strong>
                  <p style={{ margin: '0.4rem 0 0' }}>{aiData.personalized_rationale}</p>
                </div>

                <div className="sf__ai-grid">
                  <div className="sf__ai-grid-box">
                    <h4>Recommended Architecture</h4>
                    <ul>
                      {aiData.recommended_pages?.map((p) => (
                        <li key={p}>{p}</li>
                      ))}
                    </ul>
                  </div>

                  <div className="sf__ai-grid-box">
                    <h4>Included Capabilities</h4>
                    <ul>
                      {aiData.recommended_features?.map((f) => (
                        <li key={f}>{f}</li>
                      ))}
                    </ul>
                  </div>
                </div>

                {aiData.follow_up_questions && aiData.follow_up_questions.length > 0 && (
                  <div className="sf__ai-followups">
                    <p>✦ Tailor this scope further:</p>
                    {aiData.follow_up_questions.map((q) => (
                      <div key={q.id || q.question} style={{ marginTop: '0.5rem' }}>
                        <small style={{ display: 'block', marginBottom: '0.4rem', color: 'var(--fam-text-muted)' }}>{q.question}</small>
                        <div className="sf__ai-followup-chips">
                          {q.options?.map((opt) => (
                            <button
                              key={opt}
                              type="button"
                              className="sf__chip"
                              onClick={() => void handleAiConsult(query, { [q.id || 'clarify']: opt })}
                            >
                              {opt}
                            </button>
                          ))}
                        </div>
                      </div>
                    ))}
                  </div>
                )}

                <div className="sf__result-actions">
                  <Link
                    to={`/purchase?bundle=${encodeURIComponent(aiData.package_sku)}`}
                    className="v1-btn v1-btn--primary"
                  >
                    Start with this Package ({aiData.price_formatted}) →
                  </Link>

                  <button
                    type="button"
                    className="v1-btn v1-btn--ghost"
                    onClick={() => startBranch('website', { businessDescription: query, ...values })}
                  >
                    Customize Details Step-by-Step
                  </button>

                  <button
                    type="button"
                    className="v1-btn v1-btn--ghost"
                    onClick={() => { setStage('entry'); setAiData(null); setQuery(''); }}
                  >
                    Start Over
                  </button>
                </div>
              </div>
            )}
          </motion.div>
        )}

        {stage === 'branch' && step && (
          <motion.div
            key={`branch-${branch}`}
            initial={{ opacity: 0, y: 24 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -16 }}
            transition={{ duration: 0.45, ease: [0.22, 1, 0.36, 1] }}
            className="sf__card"
          >
            <div className="sf__progress">
              <span className="sf__progress-label">
                {BRANCHES[branch].label} · Step {stepIndex + 1} of {steps.length}
              </span>
              <div className="sf__bar" aria-hidden="true">
                <div className="sf__bar-fill" style={{ width: `${((stepIndex + 1) / steps.length) * 100}%` }} />
              </div>
            </div>

            <AnimatePresence mode="wait">
              <motion.div
                key={stepIndex}
                initial={{ opacity: 0, x: 32 }}
                animate={{ opacity: 1, x: 0 }}
                exit={{ opacity: 0, x: -32 }}
                transition={{ duration: 0.3, ease: [0.22, 1, 0.36, 1] }}
              >
                <h3 className="sf__step-title">{step.title}</h3>

                {step.fields.map((field) => (
                  <div className="sf__field" key={field.name}>
                    <span className="v1-field__label" id={`sf-label-${field.name}`}>
                      {field.label}{field.required ? ' *' : ''}
                    </span>

                    {(field.type === 'text' || field.type === 'email' || field.type === 'tel') && (
                      <input
                        className="v1-field__input sf__input"
                        type={field.type}
                        value={values[field.name] || ''}
                        onChange={(e) => setValue(field.name, e.target.value)}
                        placeholder={field.placeholder || ''}
                        autoComplete={field.autoComplete}
                        aria-labelledby={`sf-label-${field.name}`}
                        aria-invalid={Boolean(errors[field.name])}
                      />
                    )}

                    {field.type === 'textarea' && (
                      <textarea
                        className="v1-field__input v1-field__textarea sf__input"
                        rows={4}
                        value={values[field.name] || ''}
                        onChange={(e) => setValue(field.name, e.target.value)}
                        placeholder={field.placeholder || ''}
                        aria-labelledby={`sf-label-${field.name}`}
                        aria-invalid={Boolean(errors[field.name])}
                      />
                    )}

                    {field.type === 'select' && (
                      <div className="sf__options" role="radiogroup" aria-labelledby={`sf-label-${field.name}`}>
                        {field.options.map((opt) => (
                          <button
                            key={opt.value}
                            type="button"
                            role="radio"
                            aria-checked={values[field.name] === opt.value}
                            className={`sf__option${values[field.name] === opt.value ? ' is-selected' : ''}`}
                            onClick={() => setValue(field.name, opt.value)}
                          >
                            {opt.label}
                          </button>
                        ))}
                      </div>
                    )}

                    {field.type === 'multi' && (
                      <div className="sf__options" role="group" aria-labelledby={`sf-label-${field.name}`}>
                        {field.options.map((opt) => {
                          const selected = (values[field.name] || []).includes(opt.value);
                          return (
                            <button
                              key={opt.value}
                              type="button"
                              aria-pressed={selected}
                              className={`sf__option${selected ? ' is-selected' : ''}`}
                              onClick={() => toggleMulti(field.name, opt.value)}
                            >
                              {opt.label}
                            </button>
                          );
                        })}
                      </div>
                    )}

                    {errors[field.name] && <span className="v1-field__error">{errors[field.name]}</span>}
                  </div>
                ))}
              </motion.div>
            </AnimatePresence>

            {status === 'error' && (
              <div className="sf__error" role="alert">
                <p><strong>We couldn't reach the server</strong> — your request was <em>not</em> sent. ({submitError})</p>
                <div className="sf__error-actions">
                  <button type="button" className="v1-btn v1-btn--primary v1-btn--sm" onClick={() => void doSubmit()} disabled={status === 'submitting'}>
                    Retry
                  </button>
                  <button
                    type="button"
                    className="v1-btn v1-btn--ghost v1-btn--sm"
                    onClick={() => { setOfflineEstimate(true); setStage('result'); }}
                  >
                    Show my estimate anyway
                  </button>
                </div>
              </div>
            )}

            <div className="sf__nav">
              <button
                type="button"
                className="v1-btn v1-btn--ghost"
                onClick={() => (stepIndex === 0 ? setStage('entry') : setStepIndex((i) => i - 1))}
                disabled={status === 'submitting'}
              >
                ← Back
              </button>
              <button
                type="button"
                className="v1-btn v1-btn--primary"
                onClick={handleNext}
                disabled={status === 'submitting'}
              >
                {status === 'submitting' ? 'Sending…' : isLastStep ? 'Get my estimate' : 'Continue →'}
              </button>
            </div>
          </motion.div>
        )}

        {stage === 'result' && result && (
          <motion.div
            key="result"
            initial={{ opacity: 0, y: 24 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -16 }}
            transition={{ duration: 0.45, ease: [0.22, 1, 0.36, 1] }}
            className="sf__card sf__result"
          >
            {offlineEstimate ? (
              <div className="sf__error" role="alert" style={{ marginBottom: '1.25rem' }}>
                <p style={{ margin: 0 }}>
                  <strong>Heads up:</strong> we couldn't reach the server, so your request was <em>not</em> sent.
                  Your estimate is below — please{' '}
                  <a href={mailtoSummary}>email it to us</a> and we'll pick it up from there.
                </p>
              </div>
            ) : (
              <p className="v1-eyebrow">Thank you!</p>
            )}
            <h3 className="sf__step-title">Your starter recommendation</h3>
            <p className="sf__price">{range(result.estimate.low, result.estimate.high)}</p>
            {!offlineEstimate && serverMessage && (
              <p className="sf__note">{serverMessage}</p>
            )}
            {result.estimate.review && (
              <p className="sf__note">Flagged for review — a custom system needs a human eye, so we'll prepare a tailored quote.</p>
            )}
            {result.recommended && (
              <p className="sf__note">
                Based on your answers, we recommend: <strong>{BRANCHES[result.recommended].label}</strong>.
              </p>
            )}

            <p className="sf__includes-title">What's included</p>
            <ul className="v1-dot-list">
              {result.estimate.includes.map((item) => (
                <li key={item}>{item}</li>
              ))}
            </ul>

            {!offlineEstimate && (
              <p className="sf__note">
                This planning range is based on the basic information you shared—not a final quote or a finished design proof. We saved the lead and sent the next step to <strong>{values.email}</strong>.
              </p>
            )}

            {!offlineEstimate && registrationUrl && (
              <div className="sf__upgrade">
                <p className="v1-eyebrow">Want the full experience?</p>
                <h4>Get working website demos—not just a basic mockup.</h4>
                <p>Create a free account with the same email. Your saved request follows you into the client portal, where the detailed brief covers your brand, business model, domain, email, content, features, reference sites, and custom needs.</p>
                <a className="v1-btn v1-btn--primary" href={registrationUrl}>Create my free account →</a>
                <small>No payment is required to register or complete the detailed brief.</small>
              </div>
            )}

            <div className="sf__result-actions">
              {!registrationUrl && <Link to="/contact#contact-form" className="v1-btn v1-btn--primary">Email Your Questions</Link>}
              <button
                type="button"
                className="v1-btn v1-btn--ghost"
                onClick={() => { setStage('entry'); setValues({}); setBranch(null); setStepIndex(0); setStatus('idle'); setSubmitError(null); setServerMessage(null); setRequestId(null); setRegistrationUrl(null); setOfflineEstimate(false); setQuery(''); setAiData(null); }}
              >
                Start over
              </button>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
