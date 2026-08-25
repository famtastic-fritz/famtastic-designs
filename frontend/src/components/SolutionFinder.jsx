import { useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router';
import { AnimatePresence, motion } from 'framer-motion';
import { collectUtmParams, postIntake } from '../api/pipeline.js';

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
  'A website for my restaurant...',
  'A mobile app for my customers...',
  'An AI chatbot for leads...',
  'A portal where clients log in...',
  'A custom system for my workflow...',
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
              { value: 'new_domain', label: 'I need a new domain' },
              { value: 'existing_domain', label: 'I already own a domain' },
              { value: 'undecided', label: 'I am not sure' },
            ],
          },
          { name: 'domainDetails', label: 'Domain, hosting, email, or repository details', type: 'textarea', required: false, placeholder: 'Desired domains or an existing domain, registrar, host, email provider, website login, Git repository, or anything you know.' },
          { name: 'businessEmailNeeds', label: 'Custom business email needs', type: 'textarea', required: false, placeholder: 'Examples: info@, sales@, two mailboxes, forwarding only, or help choosing.' },
          { name: 'referenceSites', label: 'Sites you like or dislike—and why', type: 'textarea', required: false, placeholder: 'URLs plus what to borrow or avoid. We use the reasons, not just the links.' },
        ],
      },
      {
        title: 'Features, budget & timeline',
        fields: [
          {
            name: 'aiFeatures', label: 'Want AI features?', type: 'multi', required: false,
            options: [
              { value: 'chatbot', label: 'AI chatbot' },
              { value: 'automation', label: 'Automation' },
              { value: 'none', label: 'No thanks' },
            ],
          },
          {
            name: 'budget', label: 'Budget', type: 'select', required: true,
            options: [
              { value: '199', label: '$199 starter' },
              { value: '500-2k', label: '$500 – $2K' },
              { value: '2k-5k', label: '$2K – $5K' },
              { value: '5k+', label: '$5K+' },
            ],
          },
          {
            name: 'timeline', label: 'Timeline', type: 'select', required: true,
            options: [
              { value: 'asap', label: 'ASAP' },
              { value: '1-week', label: 'Within 1 week' },
              { value: '1-month', label: 'Within 1 month' },
              { value: 'flexible', label: 'Flexible' },
            ],
          },
          { name: 'customNeeds', label: 'Anything else you need—even if we do not list it?', type: 'textarea', required: false, placeholder: 'Products, services, integrations, accessibility, legal, or unusual workflow needs.' },
          {
            name: 'mockupInterest', label: 'What would help you decide?', type: 'select', required: true,
            options: [
              { value: 'mockup', label: 'A no-account mockup / visual direction' },
              { value: 'quote', label: 'A quote and recommendation' },
              { value: 'both', label: 'Both a mockup and quote' },
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
        title: 'Tell us about your business',
        fields: [
          { name: 'businessName', label: 'Business name', type: 'text', required: true, autoComplete: 'organization' },
          { name: 'industry', label: 'Industry', type: 'text', required: true, placeholder: 'e.g. fitness, delivery, retail' },
        ],
      },
      {
        title: 'Platform & purpose',
        fields: [
          {
            name: 'platform', label: 'Platform', type: 'select', required: true,
            options: [
              { value: 'ios', label: 'iOS' },
              { value: 'android', label: 'Android' },
              { value: 'both', label: 'Both' },
            ],
          },
          {
            name: 'purpose', label: 'What is the app for?', type: 'select', required: true,
            options: [
              { value: 'customer', label: 'Customer app' },
              { value: 'internal', label: 'Internal tool' },
              { value: 'marketplace', label: 'Marketplace' },
            ],
          },
        ],
      },
      {
        title: 'Features & budget',
        fields: [
          {
            name: 'features', label: 'Features you need', type: 'multi', required: true,
            options: [
              { value: 'push', label: 'Push notifications' },
              { value: 'payments', label: 'Payments' },
              { value: 'login', label: 'User login' },
              { value: 'booking', label: 'Booking' },
              { value: 'maps', label: 'Maps / GPS' },
              { value: 'chat', label: 'In-app chat' },
            ],
          },
          {
            name: 'budget', label: 'Budget', type: 'select', required: true,
            options: [
              { value: '5k-10k', label: '$5K – $10K' },
              { value: '10k-25k', label: '$10K – $25K' },
              { value: '25k+', label: '$25K+' },
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
        title: 'Tell us about your business',
        fields: [
          { name: 'businessName', label: 'Business name', type: 'text', required: true, autoComplete: 'organization' },
          { name: 'industry', label: 'Industry', type: 'text', required: true, placeholder: 'e.g. salon, HVAC, real estate' },
        ],
      },
      {
        title: 'Where & why',
        fields: [
          {
            name: 'deployWhere', label: 'Where should it live?', type: 'select', required: true,
            options: [
              { value: 'website', label: 'Website' },
              { value: 'facebook', label: 'Facebook' },
              { value: 'sms', label: 'SMS' },
              { value: 'all', label: 'All of them' },
            ],
          },
          {
            name: 'purpose', label: 'Main purpose', type: 'select', required: true,
            options: [
              { value: 'lead-capture', label: 'Lead capture' },
              { value: 'faq', label: 'Answering FAQs' },
              { value: 'booking', label: 'Booking' },
              { value: 'all', label: 'All of the above' },
            ],
          },
        ],
      },
      {
        title: 'Expected volume',
        fields: [
          {
            name: 'monthlyConversations', label: 'Approx. monthly conversations', type: 'select', required: true,
            options: [
              { value: '<100', label: 'Under 100' },
              { value: '100-500', label: '100 – 500' },
              { value: '500-2k', label: '500 – 2,000' },
              { value: '2k+', label: '2,000+' },
            ],
          },
        ],
      },
      CONTACT_STEP,
    ],
  },
  portal: {
    label: 'Client Portal',
    steps: [
      {
        title: 'Tell us about your business',
        fields: [
          { name: 'businessName', label: 'Business name', type: 'text', required: true, autoComplete: 'organization' },
          {
            name: 'clientCount', label: 'How many clients do you have?', type: 'select', required: true,
            options: [
              { value: '<10', label: 'Under 10' },
              { value: '10-50', label: '10 – 50' },
              { value: '50-200', label: '50 – 200' },
              { value: '200+', label: '200+' },
            ],
          },
        ],
      },
      {
        title: 'Portal features',
        fields: [
          {
            name: 'features', label: 'What should clients be able to do?', type: 'multi', required: true,
            options: [
              { value: 'login', label: 'Secure login' },
              { value: 'files', label: 'File sharing' },
              { value: 'payments', label: 'Payments' },
              { value: 'messaging', label: 'Messaging' },
            ],
          },
          {
            name: 'budget', label: 'Budget', type: 'select', required: true,
            options: [
              { value: '3k-5k', label: '$3K – $5K' },
              { value: '5k-10k', label: '$5K – $10K' },
              { value: '10k+', label: '$10K+' },
            ],
          },
        ],
      },
      CONTACT_STEP,
    ],
  },
  custom: {
    label: 'Custom System',
    steps: [
      {
        title: 'Tell us about your business',
        fields: [
          { name: 'businessName', label: 'Business name', type: 'text', required: true, autoComplete: 'organization' },
          { name: 'industry', label: 'Industry', type: 'text', required: true, placeholder: 'e.g. logistics, healthcare, trades' },
        ],
      },
      {
        title: 'What are you trying to do?',
        fields: [
          { name: 'description', label: "Describe what you're trying to do", type: 'textarea', required: true, placeholder: 'The more detail, the sharper the quote.' },
          { name: 'painPoints', label: 'Current pain points', type: 'textarea', required: true, placeholder: 'What is slow, manual, or breaking today?' },
        ],
      },
      {
        title: 'Budget & timeline',
        fields: [
          {
            name: 'budget', label: 'Approx. budget', type: 'select', required: true,
            options: [
              { value: '<2k', label: 'Under $2K' },
              { value: '2k-5k', label: '$2K – $5K' },
              { value: '5k-10k', label: '$5K – $10K' },
              { value: '10k+', label: '$10K+' },
            ],
          },
          {
            name: 'timeline', label: 'Timeline', type: 'select', required: true,
            options: [
              { value: 'asap', label: 'ASAP' },
              { value: '1-month', label: 'Within 1 month' },
              { value: 'quarter', label: 'This quarter' },
              { value: 'flexible', label: 'Flexible' },
            ],
          },
        ],
      },
      CONTACT_STEP,
    ],
  },
  unsure: {
    label: 'Guided',
    steps: [
      {
        title: 'What does your business do?',
        fields: [
          { name: 'businessDescription', label: 'In a sentence or two', type: 'textarea', required: true, placeholder: 'e.g. We groom dogs and take appointments over the phone.' },
        ],
      },
      {
        title: 'What is your biggest challenge?',
        fields: [
          {
            name: 'challenges', label: 'Pick all that apply', type: 'multi', required: true,
            options: [
              { value: 'no-presence', label: 'No online presence' },
              { value: 'leads', label: 'Not enough leads' },
              { value: 'missed-messages', label: 'Missed calls & messages' },
              { value: 'busywork', label: 'Too much manual busywork' },
              { value: 'client-communication', label: 'Keeping clients updated' },
              { value: 'payments-booking', label: 'Payments & booking' },
            ],
          },
        ],
      },
      {
        title: 'Where are you starting from?',
        fields: [
          {
            name: 'currentAssets', label: 'What do you currently have?', type: 'select', required: true,
            options: [
              { value: 'nothing', label: 'Nothing yet' },
              { value: 'social', label: 'Social media only' },
              { value: 'basic-site', label: 'A basic website' },
              { value: 'site-and-social', label: 'Website + social' },
            ],
          },
          {
            name: 'volume', label: 'Rough customer volume', type: 'select', required: true,
            options: [
              { value: 'starting', label: 'Just starting out' },
              { value: '1-50', label: '1 – 50 / month' },
              { value: '50-200', label: '50 – 200 / month' },
              { value: '200+', label: '200+ / month' },
            ],
          },
        ],
      },
      CONTACT_STEP,
    ],
  },
};

/* ------------------------------------------------------------------ */
/* Deterministic estimate tables (derived from the package ladder     */
/* $199 / $499 / $1,999 / $3,999 / $6,999)                             */
/* ------------------------------------------------------------------ */

function estimateWebsite(v) {
  const base = { '1': [199, 499], '3-5': [499, 1999], '5-10': [1999, 3999], '10+': [3999, 6999] }[v.pages] || [499, 1999];
  let [low, high] = base;
  const includes = ['Custom design & build', 'Mobile-first responsive layout', 'On-page SEO basics', 'Launch + analytics setup'];
  if (v.aiFeatures?.includes('chatbot')) {
    low += 199; high += 500;
    includes.push('AI chatbot trained on your business');
  }
  if (v.aiFeatures?.includes('automation')) {
    low += 499; high += 1000;
    includes.push('Workflow automation (leads, follow-ups, notifications)');
  }
  if (v.brandStatus === 'help_needed') {
    low += 249; high += 249;
    includes.push('Logo and Brand Starter add-on');
  }
  if (String(v.businessEmailNeeds || '').trim()) {
    low += 99; high += 99;
    includes.push('Business Email Setup add-on');
  }
  return { low, high, includes };
}

function estimateApp(v) {
  let [low, high] = v.platform === 'both' ? [8000, 15000] : [5000, 10000];
  if (v.purpose === 'marketplace') { low += 5000; high += 10000; }
  if (v.purpose === 'customer') { low += 1000; high += 3000; }
  const featureCount = (v.features || []).length;
  low += featureCount * 500;
  high += featureCount * 1500;
  const includes = ['UX/UI design', 'Native-quality iOS/Android build', 'App Store & Play submission', '30 days post-launch support'];
  return { low, high, includes };
}

function estimateChatbot(v) {
  let [low, high] = v.deployWhere === 'all' ? [499, 999] : [199, 499];
  if (v.purpose === 'booking') { low += 200; high += 500; }
  if (v.purpose === 'all') { low += 300; high += 700; }
  if (v.monthlyConversations === '500-2k') { low += 100; high += 300; }
  if (v.monthlyConversations === '2k+') { low += 300; high += 800; }
  const includes = ['Bot trained on your FAQs & services', 'Lead capture with instant notifications', 'Conversation transcripts', 'Monthly tuning & reporting'];
  return { low, high, includes };
}

function estimatePortal(v) {
  let [low, high] = { '<10': [3000, 5000], '10-50': [4000, 6000], '50-200': [6000, 10000], '200+': [10000, 15000] }[v.clientCount] || [3000, 5000];
  const adds = { files: [500, 1000], payments: [1000, 2000], messaging: [800, 1500] };
  for (const f of v.features || []) {
    if (adds[f]) { low += adds[f][0]; high += adds[f][1]; }
  }
  const includes = ['Secure client logins', 'Branded dashboard', 'Admin console', 'Onboarding & training'];
  return { low, high, includes };
}

function estimateCustom(v) {
  const map = { '<2k': [1999, 3999], '2k-5k': [2000, 5000], '5k-10k': [5000, 10000], '10k+': [10000, 25000] };
  const [low, high] = map[v.budget] || [1999, 6999];
  const includes = ['Discovery & workflow mapping', 'Fixed-scope proposal before build', 'Weekly progress check-ins', 'Verified working before final payment'];
  return { low, high, includes, review: true };
}

function recommendBranch(v) {
  const c = v.challenges || [];
  if (c.includes('missed-messages') || c.includes('client-communication')) return 'chatbot';
  if (c.includes('busywork')) return 'custom';
  if (c.includes('payments-booking')) return 'portal';
  return 'website'; // no-presence / leads / default
}

const UNSURE_BASE = {
  website: { low: 499, high: 1999 },
  chatbot: { low: 199, high: 499 },
  portal: { low: 3000, high: 5000 },
  custom: { low: 1999, high: 6999 },
  app: { low: 5000, high: 10000 },
};

function estimateUnsure(v, recommended) {
  const { low, high } = UNSURE_BASE[recommended] || UNSURE_BASE.website;
  const includes = [`Recommended solution: ${BRANCHES[recommended].label}`, 'Fixed-scope proposal before build', 'Verified working before final payment'];
  return { low, high, includes };
}

function computeEstimate(branch, values, recommended) {
  switch (branch) {
    case 'website': return estimateWebsite(values);
    case 'app': return estimateApp(values);
    case 'chatbot': return estimateChatbot(values);
    case 'portal': return estimatePortal(values);
    case 'custom': return estimateCustom(values);
    case 'unsure': return estimateUnsure(values, recommended);
    default: return { low: 199, high: 499, includes: [] };
  }
}

/* ------------------------------------------------------------------ */
/* Search matching                                                    */
/* ------------------------------------------------------------------ */

function matchBranch(query) {
  const q = query.trim().toLowerCase();
  if (!q) return null;
  let best = null;
  let bestScore = 0;
  for (const [id, words] of Object.entries(KEYWORDS)) {
    const score = words.reduce((acc, w) => (q.includes(w) ? acc + w.length : acc), 0);
    if (score > bestScore) { best = id; bestScore = score; }
  }
  return best;
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

/* ------------------------------------------------------------------ */
/* Component                                                          */
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

  // Cycling placeholder examples.
  useEffect(() => {
    const t = setInterval(() => setPlaceholderIdx((i) => (i + 1) % PLACEHOLDERS.length), 3000);
    return () => clearInterval(t);
  }, []);

  const matched = useMemo(() => matchBranch(query), [query]);

  const steps = branch ? BRANCHES[branch].steps : [];
  const step = steps[stepIndex];
  const isLastStep = stepIndex === steps.length - 1;

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
    const target = matched || 'unsure';
    const prefill = !matched && query.trim() ? { businessDescription: query.trim() } : {};
    startBranch(target, prefill);
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
            <p className="v1-eyebrow" style={{ textAlign: 'center' }}>Solution Finder</p>
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
                aria-label="Describe what you want to build"
              />
              <button
                type="button"
                className="sf__search-go"
                onClick={() => handleEnterKey({ key: 'Enter', preventDefault: () => {} })}
                aria-label="Start"
              >
                →
              </button>
            </div>

            <div className="sf__chips" role="group" aria-label="Solution types">
              {CHIPS.map((chip) => (
                <button
                  key={chip.id}
                  type="button"
                  className={`sf__chip${matched === chip.id ? ' is-match' : ''}`}
                  onClick={() => startBranch(chip.id)}
                >
                  {chip.label}
                </button>
              ))}
            </div>
            <p className="sf__hint">Share the basics in about 60 seconds. We’ll save your request and show a starter recommendation.</p>
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
                onClick={() => { setStage('entry'); setValues({}); setBranch(null); setStepIndex(0); setStatus('idle'); setSubmitError(null); setServerMessage(null); setRequestId(null); setRegistrationUrl(null); setOfflineEstimate(false); setQuery(''); }}
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
