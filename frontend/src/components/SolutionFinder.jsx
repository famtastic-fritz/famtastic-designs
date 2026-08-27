import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router';
import { AnimatePresence, motion } from 'framer-motion';
import { collectUtmParams, postIntake } from '../api/pipeline.js';

export function branchForServiceSlug(slug = '') {
  const s = (slug || '').toLowerCase();
  if (s.includes('chatbot') || s.includes('chat') || s === 'ai') return 'ai-chatbot';
  if (s.includes('ecommerce') || s.includes('store') || s.includes('shop')) return 'ecommerce';
  if (s.includes('rebuild') || s.includes('redesign')) return 'site-rebuild';
  if (s.includes('landing')) return 'landing-page';
  if (s.includes('portal') || s.includes('app')) return 'client-portal';
  if (s.includes('custom') || s.includes('system') || s.includes('automation')) return 'custom-dev';
  if (s.includes('199') || s.includes('basic') || s.includes('quick')) return 'web-basics';
  return null;
}

// The 7 Core FAMtastic Service Branches
const SERVICE_BRANCHES = [
  {
    id: 'web-basics',
    icon: '⚡',
    title: '$199 Quick-Start Site',
    desc: '1 focused page, booking/contact, hosting & domain included',
    price: 199,
    priceFormatted: '$199',
    timeline: '3–5 business days',
    packageSku: 'web-basics',
    packageTitle: 'Web Basics Bundle — $199',
    q2Prompt: 'For the $199 Quick-Start, do you have a domain and logo ready, or are you starting from scratch?',
    q2Options: [
      'I have domain & logo ready',
      'Have domain, need logo created',
      'Starting from scratch (need both)',
      'Replacing an existing site',
    ],
    q3Prompt: 'What is your business name and primary service or city?',
    q3Placeholder: 'e.g. Ace Barbershop in Port St. Lucie, FL',
    q3Options: ['Solo Service Business', 'Storefront / Local Shop', 'Consultant / Freelancer', 'Church / Community'],
    pages: ['1 High-Conversion Single Page', 'Service & Price Guide', 'Lead / Booking Request Form', 'Verified Reviews & Trust', 'Google Map & Contact Section'],
    features: ['Mobile-responsive layout', 'Fast lead alerts to your email', 'Foundational local SEO', '1st-year hosting & domain included'],
  },
  {
    id: 'ai-chatbot',
    icon: '🤖',
    title: 'AI Chatbot & Automation',
    desc: '24/7 AI trained on your business that answers questions, qualifies leads & books appointments',
    price: 6999,
    priceFormatted: '$6,999',
    timeline: '3–4 weeks',
    packageSku: 'premium-ai',
    packageTitle: 'Premium Website + AI System — $6,999',
    q2Prompt: 'Where should your AI Assistant engage with your customers?',
    q2Options: [
      'Website Chat Widget',
      'SMS Text Messaging',
      'Facebook & Instagram DMs',
      'All Channels (Omnichannel AI)',
    ],
    q3Prompt: 'What should the AI automate for your business?',
    q3Placeholder: 'e.g. Answer FAQ, qualify leads, and book calendar appointments...',
    q3Options: [
      'Qualify leads & book calendar appointments',
      '24/7 Customer support & FAQ answers',
      'Calculate instant project price estimates',
      'All of the above',
    ],
    pages: ['AI Chatbot Knowledge Engine', 'Governed Prompt & Rule Set', 'Live Lead Dashboard', 'Web & Messaging Connectors', 'Human Escalation & Email Routing'],
    features: ['Custom AI training on your docs/FAQs', 'Zero hallucination guardrails', 'SMS & CRM sync', 'Full source code & hosting'],
  },
  {
    id: 'site-rebuild',
    icon: '🔄',
    title: 'Website Rebuild & Redesign',
    desc: 'Transform a slow, outdated, or broken site into a fast, modern, high-converting asset',
    price: 499,
    priceFormatted: '$499',
    timeline: '5–10 business days',
    packageSku: 'business-website',
    packageTitle: 'Business Website Bundle — $499',
    q2Prompt: 'What is the biggest problem with your current website right now?',
    q2Options: [
      'Slow loading & outdated design',
      'Broken on mobile smartphones',
      'Does not generate leads or calls',
      'Hard to update / broken WordPress',
    ],
    q3Prompt: 'What is your current website URL and business name?',
    q3Placeholder: 'e.g. https://myoldwebsite.com (Apex Roofing)',
    q3Options: ['Up to 5 standard pages', 'Custom 5–10 page redesign ($1,999)', 'Needs fresh copy & branding too'],
    pages: ['Home (Modernized conversion hero)', 'Services & Offerings', 'About & Team Story', 'Case Studies / Reviews', 'Contact & Lead Capture Form'],
    features: ['Core Web Vitals 90+ speed overhaul', 'Mobile-first responsive build', 'On-page SEO migration', 'Google Analytics GA4 integration'],
  },
  {
    id: 'ecommerce',
    icon: '🛍️',
    title: 'E-Commerce Online Store',
    desc: 'Full-featured online store with product catalog, secure Stripe/PayPal checkout & inventory',
    price: 1999,
    priceFormatted: '$1,999',
    timeline: '2–3 weeks',
    packageSku: 'custom-website',
    packageTitle: 'Custom E-Commerce Store — $1,999',
    q2Prompt: 'What type of products will you be selling online?',
    q2Options: [
      'Physical goods & shipping',
      'Digital downloads / courses',
      'Service bookings & deposits',
      'Subscription memberships',
    ],
    q3Prompt: 'Approximately how many products will you launch with?',
    q3Placeholder: 'e.g. 15 apparel items with size variants...',
    q3Options: ['1–10 starter products', '10–50 products', '50–200 products', '200+ enterprise catalog'],
    pages: ['Storefront & Featured Collections', 'Product Detail Pages with Variants', 'Secure Cart & 1-Click Checkout', 'Customer Account & Order History', 'Shipping, Returns & Policy Pages'],
    features: ['Stripe / PayPal payment processing', 'Inventory & order management', 'Automated customer receipts', 'Mobile-optimized checkout flow'],
  },
  {
    id: 'landing-page',
    icon: '🎯',
    title: 'Landing Page for Ads & Campaigns',
    desc: 'High-converting landing page engineered for paid ads, Google/Meta traffic & product launches',
    price: 1499,
    priceFormatted: '$1,499',
    timeline: '5–7 business days',
    packageSku: 'landing-page',
    packageTitle: 'Campaign Landing Page System — $1,499',
    q2Prompt: 'What is the primary traffic source or campaign goal for this landing page?',
    q2Options: [
      'Google / Meta Paid Ads traffic',
      'Product or service launch offer',
      'High-ticket consultation bookings',
      'Event or webinar registrations',
    ],
    q3Prompt: 'Do you have the sales copy and visual assets ready, or need us to write & design them?',
    q3Placeholder: 'e.g. Need compelling copywriting and custom hero graphics...',
    q3Options: [
      'We need copy & design created from scratch',
      'Copy is ready, need high-converting design',
      'Have everything, need fast responsive build',
    ],
    pages: ['High-Conversion Hero with Strong Hook', 'Social Proof & Testimonial Carousel', 'Problem vs. Solution Section', 'Offer Breakdown & Pricing Table', 'FAQ & Risk-Reversal Guarantee'],
    features: ['A/B testing-ready architecture', 'Dual-grain UTM tracking for ad ROI', 'Instant lead notifications via email/SMS', 'Blazing sub-second load times'],
  },
  {
    id: 'client-portal',
    icon: '🔐',
    title: 'Client Portal & Web App',
    desc: 'Private, branded client portals for client logins, project tracking, invoicing & file sharing',
    price: 3999,
    priceFormatted: '$3,999',
    timeline: '3–4 weeks',
    packageSku: 'business-growth',
    packageTitle: 'Business Growth & Portal System — $3,999',
    q2Prompt: 'Who will be logging into the portal and what is the primary workflow?',
    q2Options: [
      'Clients (View project status, approve proofs, pay invoices)',
      'Members (Exclusive content, community, resources)',
      'Internal Team / Contractors (Task management, files)',
      'Multi-Role (Clients + Team + Admins)',
    ],
    q3Prompt: 'What key integrations or payment systems do you need connected?',
    q3Placeholder: 'e.g. Stripe invoicing, QuickBooks, Google Drive / Dropbox...',
    q3Options: [
      'Stripe invoicing & automated billing',
      'Secure file upload & client proofs',
      'Client messaging & support desk',
      'Custom database & REST API',
    ],
    pages: ['Branded Login & Authentication Gate', 'Client Dashboard with Active Projects', 'Invoicing & Payment History', 'Document & File Vault', 'Messaging & Support Hub'],
    features: ['Role-based access control (RBAC)', 'SSL encryption & security audit', 'Automated email alerts on status updates', 'Exportable client records'],
  },
  {
    id: 'custom-dev',
    icon: '🛠️',
    title: 'Custom Bespoke Development',
    desc: 'Bespoke web architecture, custom API integrations & enterprise digital systems',
    price: 1999,
    priceFormatted: '$1,999',
    timeline: '2–3 weeks',
    packageSku: 'custom-website',
    packageTitle: 'Custom Website — $1,999',
    q2Prompt: 'What is the primary technical requirement or goal for this custom build?',
    q2Options: [
      'Unique bespoke design (no templates)',
      'Complex multi-system API integrations',
      'Multi-location or franchise architecture',
      'High-performance custom web application',
    ],
    q3Prompt: 'What is your company name and target audience?',
    q3Placeholder: 'e.g. Nexus Logistics, B2B enterprise clients...',
    q3Options: ['B2B Enterprise', 'Local Services / Multi-location', 'Tech Startup / SaaS', 'Creative Agency'],
    pages: ['Bespoke Custom Homepage', 'Interactive Capabilities / Solutions Hub', 'Case Studies & Results Showcase', 'Company & Leadership', 'Custom Intake / Request Architecture'],
    features: ['Tailored Information Architecture', 'Dual revision rounds with design tokens', 'Comprehensive SEO & schema markup', 'Full code handoff & training'],
  },
];

export default function SolutionFinder({ initialBranch = null }) {
  const [isOpen, setIsOpen] = useState(false);
  const [step, setStep] = useState(1); // 1: Pick Service -> 2: Branch Specific Q2 -> 3: Branch Specific Q3 -> 4: Timeline -> 5: Scope Reveal & Email
  const [selectedBranch, setSelectedBranch] = useState(null);
  const [answers, setAnswers] = useState({
    branchId: '',
    branchTitle: '',
    q2Answer: '',
    q3Answer: '',
    timeline: '',
    email: '',
    phone: '',
    businessName: '',
  });

  const [messages, setMessages] = useState([]);
  const [chips, setChips] = useState([]);
  const [inputVal, setInputVal] = useState('');
  const [inputPlaceholder, setInputPlaceholder] = useState('Or type your response here...');
  const [isTyping, setIsTyping] = useState(false);

  const [status, setStatus] = useState('idle');
  const [requestId, setRequestId] = useState(null);

  const chatBodyRef = useRef(null);
  const inputRef = useRef(null);

  useEffect(() => {
    if (chatBodyRef.current) {
      chatBodyRef.current.scrollTop = chatBodyRef.current.scrollHeight;
    }
  }, [messages, isTyping, chips]);

  // Initial branch trigger if provided (e.g. from service page)
  useEffect(() => {
    if (initialBranch) {
      const match = SERVICE_BRANCHES.find((b) => b.id === initialBranch || b.packageSku === initialBranch);
      if (match) {
        openChat();
        handleServiceSelect(match);
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initialBranch]);

  function openChat() {
    setIsOpen(true);
    document.body.style.overflow = 'hidden';
    if (messages.length === 0) {
      startDynamicFlow();
    }
  }

  function closeChat() {
    setIsOpen(false);
    document.body.style.overflow = '';
  }

  function startDynamicFlow() {
    setStep(1);
    setIsTyping(true);
    setTimeout(() => {
      setIsTyping(false);
      setMessages([
        {
          id: 1,
          who: 'bot',
          text: "Welcome to FAMtastic. What kind of website or digital system are you looking to build?",
        },
      ]);
      setChips(
        SERVICE_BRANCHES.map((b) => ({
          label: `${b.icon} ${b.title}`,
          primary: b.id === 'web-basics',
          fn: () => handleServiceSelect(b),
        }))
      );
      setInputPlaceholder('Or describe your project in your own words...');
    }, 350);
  }

  function handleServiceSelect(branch) {
    setSelectedBranch(branch);
    setAnswers((prev) => ({
      ...prev,
      branchId: branch.id,
      branchTitle: branch.title,
    }));
    appendUserMsg(`${branch.icon} ${branch.title}`);
    setChips([]);
    setStep(2);
    setIsTyping(true);

    setTimeout(() => {
      setIsTyping(false);
      setMessages((prev) => [
        ...prev,
        {
          id: Date.now(),
          who: 'bot',
          text: branch.q2Prompt,
        },
      ]);
      setChips(
        branch.q2Options.map((opt) => ({
          label: opt,
          fn: () => handleQ2Select(branch, opt),
        }))
      );
      setInputPlaceholder('Type your answer or select an option...');
      inputRef.current?.focus();
    }, 450);
  }

  function handleQ2Select(branch, optionText) {
    appendUserMsg(optionText);
    setAnswers((prev) => ({ ...prev, q2Answer: optionText }));
    setChips([]);
    setStep(3);
    setIsTyping(true);

    setTimeout(() => {
      setIsTyping(false);
      setMessages((prev) => [
        ...prev,
        {
          id: Date.now(),
          who: 'bot',
          text: branch.q3Prompt,
        },
      ]);
      setChips(
        branch.q3Options.map((opt) => ({
          label: opt,
          fn: () => handleQ3Select(branch, opt),
        }))
      );
      setInputPlaceholder(branch.q3Placeholder);
      inputRef.current?.focus();
    }, 450);
  }

  function handleQ3Select(branch, optionText) {
    appendUserMsg(optionText);
    setAnswers((prev) => ({ ...prev, q3Answer: optionText, businessName: optionText }));
    setChips([]);
    setStep(4);
    setIsTyping(true);

    setTimeout(() => {
      setIsTyping(false);
      setMessages((prev) => [
        ...prev,
        {
          id: Date.now(),
          who: 'bot',
          text: 'Last question — when is your target launch date for this project?',
        },
      ]);
      setChips([
        { label: '⚡ ASAP (within 1–2 weeks)', primary: true, fn: () => revealDynamicScope(branch, 'ASAP (within 1–2 weeks)') },
        { label: '📅 Within this month', fn: () => revealDynamicScope(branch, 'Within this month') },
        { label: '🔍 Planning ahead / Researching', fn: () => revealDynamicScope(branch, 'Planning ahead / Researching') },
      ]);
      setInputPlaceholder('Select launch timeline...');
    }, 450);
  }

  function revealDynamicScope(branch, timelineText) {
    appendUserMsg(timelineText);
    const updatedAnswers = { ...answers, timeline: timelineText };
    setAnswers(updatedAnswers);
    setChips([]);
    setStep(5);
    setIsTyping(true);

    setTimeout(() => {
      setIsTyping(false);
      setMessages((prev) => [
        ...prev,
        {
          id: Date.now(),
          who: 'bot',
          isScopeCard: true,
          scopeData: {
            icon: branch.icon,
            title: branch.packageTitle,
            price: branch.price,
            priceFormatted: branch.priceFormatted,
            timeline: timelineText.includes('ASAP') ? branch.timeline : 'Scheduled per agreement',
            pages: branch.pages,
            features: branch.features,
            q2Answer: updatedAnswers.q2Answer || 'Confirmed',
            q3Answer: updatedAnswers.q3Answer || 'Custom Scope',
            packageSku: branch.packageSku,
          },
        },
      ]);

      setIsTyping(true);
      setTimeout(() => {
        setIsTyping(false);
        setMessages((prev) => [
          ...prev,
          {
            id: Date.now(),
            who: 'bot',
            text: `Here is your exact project blueprint and locked price (${branch.priceFormatted}). Where should I email your full PDF scope & specifications?`,
          },
        ]);
        setChips([
          {
            label: `Start with this Package (${branch.priceFormatted}) →`,
            primary: true,
            fn: () => (window.location.href = `/purchase?bundle=${branch.packageSku}`),
          },
          { label: 'Talk to a real human', fn: () => escalateHuman() },
        ]);
        setInputPlaceholder('Enter your work email address...');
        inputRef.current?.focus();
      }, 500);
    }, 900);
  }

  function finishWithEmail(email) {
    appendUserMsg(email);
    const finalData = { ...answers, email };
    setAnswers(finalData);
    setChips([]);
    setIsTyping(true);

    void submitIntakeData(finalData, selectedBranch);

    setTimeout(() => {
      setIsTyping(false);
      setMessages((prev) => [
        ...prev,
        {
          id: Date.now(),
          who: 'bot',
          text: `All set! Your complete blueprint and specifications have been sent to ${email}. Your ${selectedBranch?.priceFormatted || '$199'} price is locked for 30 days. You can start the build with one click from that email or right here.`,
        },
      ]);
      setChips([
        {
          label: `Start My Build (${selectedBranch?.priceFormatted || '$199'}) →`,
          primary: true,
          fn: () => (window.location.href = `/purchase?bundle=${selectedBranch?.packageSku || 'web-basics'}`),
        },
        { label: 'Talk to a real human', fn: () => escalateHuman() },
        { label: 'Close window', fn: () => closeChat() },
      ]);
    }, 500);
  }

  function escalateHuman() {
    appendUserMsg('I want to talk to a real human');
    setChips([]);
    setIsTyping(true);

    setTimeout(() => {
      setIsTyping(false);
      setMessages((prev) => [
        ...prev,
        {
          id: Date.now(),
          who: 'bot',
          text: "You got it! I've flagged our lead engineer for a direct follow-up within 1 business day. What is the best phone number or email to reach you?",
        },
      ]);
      setInputPlaceholder('Enter your phone or email...');
      inputRef.current?.focus();
    }, 450);
  }

  function appendUserMsg(text) {
    setMessages((prev) => [...prev, { id: Date.now(), who: 'user', text }]);
  }

  function handleUserTextSubmit(raw) {
    const text = (raw || inputVal).trim();
    if (!text || isTyping) return;
    setInputVal('');

    // If in Email step
    if (step === 5 && text.includes('@')) {
      finishWithEmail(text);
      return;
    }

    // Dynamic handling per step
    if (step === 1) {
      // Find matching branch from user text
      const lower = text.toLowerCase();
      const matched =
        SERVICE_BRANCHES.find(
          (b) =>
            lower.includes(b.id) ||
            lower.includes(b.title.toLowerCase()) ||
            (b.id === 'web-basics' && (lower.includes('199') || lower.includes('starter') || lower.includes('simple'))) ||
            (b.id === 'ai-chatbot' && (lower.includes('chat') || lower.includes('bot') || lower.includes('ai'))) ||
            (b.id === 'ecommerce' && (lower.includes('store') || lower.includes('shop') || lower.includes('e-commerce') || lower.includes('product'))) ||
            (b.id === 'site-rebuild' && (lower.includes('rebuild') || lower.includes('redesign') || lower.includes('fix') || lower.includes('update'))) ||
            (b.id === 'landing-page' && (lower.includes('landing') || lower.includes('ad') || lower.includes('campaign'))) ||
            (b.id === 'client-portal' && (lower.includes('portal') || lower.includes('login') || lower.includes('member') || lower.includes('app')))
        ) || SERVICE_BRANCHES[0];

      handleServiceSelect(matched);
      return;
    }

    if (step === 2 && selectedBranch) {
      handleQ2Select(selectedBranch, text);
      return;
    }

    if (step === 3 && selectedBranch) {
      handleQ3Select(selectedBranch, text);
      return;
    }

    if (step === 4 && selectedBranch) {
      revealDynamicScope(selectedBranch, text);
      return;
    }

    // General text handling
    appendUserMsg(text);
    setIsTyping(true);
    setTimeout(() => {
      setIsTyping(false);
      setMessages((prev) => [
        ...prev,
        { id: Date.now(), who: 'bot', text: 'Got that noted! Where should we send your official project scope PDF?' },
      ]);
      setInputPlaceholder('Enter your email...');
    }, 400);
  }

  async function submitIntakeData(finalAnswers, branch) {
    if (status === 'submitting' || requestId) return;
    setStatus('submitting');

    const transcript = messages.map((m) => `${m.who.toUpperCase()}: ${m.text || '[Card]'}`).join('\n');

    const payload = {
      source: 'famtastic-dynamic-scout',
      branch: branch?.packageSku || 'web-basics',
      answers: {
        selectedService: branch?.title,
        branchSpecificAnswers: {
          q2Answer: finalAnswers.q2Answer,
          q3Answer: finalAnswers.q3Answer,
          timeline: finalAnswers.timeline,
        },
        businessName: finalAnswers.businessName,
        email: finalAnswers.email,
        phone: finalAnswers.phone,
        conversationTranscript: transcript,
        recommendedPackage: branch?.packageTitle,
      },
      estimate: {
        low: branch?.price || 199,
        high: branch?.price || 199,
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
      setRequestId(res?.request_id || null);
      setStatus('success');
    } catch {
      setStatus('error');
    }
  }

  return (
    <div className="sf" id="solution-finder">
      {/* ============ HERO ENTRY POINT ============ */}
      <section className="sf__hero-entry">
        <span className="sf__ai-badge">⚡ FAMtastic Solution Finder · Dynamic Project Intake</span>
        <h2 className="sf__hero-title">
          Scope your custom system <span className="sf__accent-green">in 60 seconds.</span>
        </h2>
        <p className="sf__hero-sub">
          Dynamic, one-question-at-a-time guided intake. Tailored specifications, custom sitemaps, and exact locked pricing across all 6 core services—before you spend a dime.
        </p>

        <div className="sf__entry-box">
          <button
            type="button"
            className="sf__entry-btn"
            onClick={openChat}
            aria-haspopup="dialog"
          >
            <span className="sf__entry-btn-text">What do you want to build? (e.g. $199 Special, AI Chatbot, E-Commerce...)</span>
            <span className="sf__entry-btn-go">Start scoping →</span>
          </button>

          <div className="sf__hero-chips">
            {SERVICE_BRANCHES.slice(0, 6).map((b) => (
              <button
                key={b.id}
                type="button"
                onClick={() => {
                  openChat();
                  handleServiceSelect(b);
                }}
              >
                {b.icon} {b.title}
              </button>
            ))}
          </div>

          <p className="sf__entry-hint">
            <strong>One question at a time.</strong> Clickable choices + fill-in-the-blank · <strong>Locked pricing from $199</strong>
          </p>
        </div>

        <div className="sf__trust-row">
          <span><strong>22+ yrs</strong> engineering systems</span>
          <span><strong>Fixed price</strong> before we start</span>
          <span><strong>Verified working</strong> before you pay</span>
        </div>
      </section>

      {/* ============ CHAT OVERLAY MODAL ============ */}
      <AnimatePresence>
        {isOpen && (
          <motion.div
            className="sf__overlay"
            role="dialog"
            aria-modal="true"
            aria-label="Chat with FAMtastic Advisor"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.25 }}
          >
            <motion.div
              className="sf__sheet"
              initial={{ scale: 0.96, y: 20 }}
              animate={{ scale: 1, y: 0 }}
              exit={{ scale: 0.96, y: 20 }}
              transition={{ duration: 0.3, ease: [0.22, 1, 0.36, 1] }}
            >
              {/* Header */}
              <div className="sf__chat-head">
                <div className="sf__avatar">✦</div>
                <div className="sf__who">
                  <b>FAMtastic Advisor</b>
                  <span>Dynamic Intake Engine · online</span>
                </div>
                <button type="button" className="sf__chat-close" onClick={closeChat} aria-label="Close chat">
                  ✕
                </button>
              </div>

              {/* Progress Bar */}
              <div className="sf__progress-wrap">
                <div className="sf__progress-label">
                  <span>
                    Step {step} of 5 ·{' '}
                    {step === 1
                      ? 'Select Service'
                      : step === 2
                      ? 'Branch Focus'
                      : step === 3
                      ? 'Project Specifics'
                      : step === 4
                      ? 'Launch Timeline'
                      : 'Scope & Price Reveal'}
                  </span>
                  <span>{step * 20}%</span>
                </div>
                <div className="sf__progress-bar">
                  <div className="sf__progress-bar-fill" style={{ width: `${step * 20}%` }} />
                </div>
              </div>

              {/* Chat Body */}
              <div className="sf__chat-body" ref={chatBodyRef}>
                {messages.map((msg) => (
                  <div key={msg.id} className={`sf__msg-row ${msg.who}`}>
                    {msg.isScopeCard ? (
                      <div className="sf__scope-card">
                        <span className="sf__scope-tag">{msg.scopeData.icon} Tailored Project Scope</span>
                        <h3>{msg.scopeData.title}</h3>
                        <div className="sf__scope-city">Timeline: {msg.scopeData.timeline}</div>

                        <div className="sf__scope-row">
                          <span className="k">Target Objective</span>
                          <span className="v">{msg.scopeData.q2Answer}</span>
                        </div>
                        <div className="sf__scope-row">
                          <span className="k">Project Context</span>
                          <span className="v">{msg.scopeData.q3Answer}</span>
                        </div>

                        <div style={{ margin: '0.85rem 0 0.5rem', borderTop: '1px solid #d1cebe', paddingTop: '0.65rem' }}>
                          <strong style={{ fontSize: '0.82rem', textTransform: 'uppercase', letterSpacing: '0.06em', color: '#4b634b' }}>
                            Included Architecture & Features:
                          </strong>
                          <ul style={{ margin: '0.4rem 0 0', paddingLeft: '1.2rem', fontSize: '0.84rem', lineHeight: '1.4' }}>
                            {msg.scopeData.pages.slice(0, 4).map((p, i) => (
                              <li key={i}>{p}</li>
                            ))}
                          </ul>
                        </div>

                        <div className="sf__scope-price-box">
                          <span className="label">Exact Fixed Price</span>
                          <span className="amount">{msg.scopeData.priceFormatted}</span>
                        </div>
                        <div className="sf__scope-note">
                          Price locked for 30 days · Verified working before balance is due
                        </div>
                      </div>
                    ) : (
                      <div className="sf__bubble">{msg.text}</div>
                    )}
                  </div>
                ))}

                {isTyping && (
                  <div className="sf__msg-row bot">
                    <div className="sf__typing-pill">
                      <span /><span /><span />
                    </div>
                  </div>
                )}
              </div>

              {/* Chat Footer */}
              <div className="sf__chat-foot">
                {chips && chips.length > 0 && (
                  <div className="sf__chips-row">
                    {chips.map((chip, idx) => (
                      <button
                        key={idx}
                        type="button"
                        className={`sf__chip-btn${chip.primary ? ' is-primary' : ''}`}
                        onClick={chip.fn}
                      >
                        {chip.label}
                      </button>
                    ))}
                  </div>
                )}

                <form
                  className="sf__input-form"
                  onSubmit={(e) => {
                    e.preventDefault();
                    handleUserTextSubmit(inputVal);
                  }}
                >
                  <input
                    ref={inputRef}
                    type="text"
                    className="sf__text-input"
                    value={inputVal}
                    onChange={(e) => setInputVal(e.target.value)}
                    placeholder={inputPlaceholder}
                    disabled={isTyping}
                  />
                  <button
                    type="submit"
                    className="sf__send-btn"
                    disabled={isTyping || !inputVal.trim()}
                    aria-label="Send response"
                  >
                    ➤
                  </button>
                </form>
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
