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
  if (s.includes('business-website') || s.includes('499')) return 'site-rebuild';
  if (s.includes('199') || s.includes('basic') || s.includes('quick')) return 'web-basics';
  return null;
}

// Public research branches. They deliberately gather context without
// promising a price, timeframe, deliverable list, or automated outcome.
const SERVICE_BRANCHES = [
  {
    id: 'web-basics',
    icon: '⚡',
    title: 'Starter Mobile Business Foundation — $199 starting point',
    q2Prompt: 'For a focused first website, do you have a domain and logo ready, or are you starting from scratch?',
    q2Options: [
      'I have domain & logo ready',
      'Have domain, need logo created',
      'Starting from scratch (need both)',
      'Replacing an existing site',
    ],
    q3Prompt: 'What is your business name and primary service or city?',
    q3Placeholder: 'e.g. Ace Barbershop in Port St. Lucie, FL',
    q3Options: ['Solo Service Business', 'Storefront / Local Shop', 'Consultant / Freelancer', 'Church / Community'],
  },
  {
    id: 'ai-chatbot',
    icon: '🤖',
    title: 'AI assistant or automation',
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
      'Customer support & FAQ answers',
      'Guided project-price questions',
      'All of the above',
    ],
  },
  {
    id: 'site-rebuild',
    icon: '🔄',
    title: 'Business Website — $499 starting point',
    q2Prompt: 'What is the biggest problem with your current website right now?',
    q2Options: [
      'Slow loading & outdated design',
      'Broken on mobile smartphones',
      'Does not generate leads or calls',
      'Hard to update / broken WordPress',
    ],
    q3Prompt: 'What is your current website URL and business name?',
    q3Placeholder: 'e.g. https://myoldwebsite.com (Apex Roofing)',
    q3Options: ['Up to 5 standard pages', 'More than 5 pages', 'Needs fresh copy & branding too'],
  },
  {
    id: 'ecommerce',
    icon: '🛍️',
    title: 'Ecommerce or online store',
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
  },
  {
    id: 'landing-page',
    icon: '🎯',
    title: 'Campaign or landing page',
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
  },
  {
    id: 'client-portal',
    icon: '🔐',
    title: 'Client portal or web app',
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
  },
  {
    id: 'custom-dev',
    icon: '🛠️',
    title: 'Custom website or digital system',
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
  const [submissionError, setSubmissionError] = useState('');

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
      const match = SERVICE_BRANCHES.find((b) => b.id === initialBranch);
      if (match) {
        openChat(match);
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initialBranch]);

  function openChat(branch = null) {
    setIsOpen(true);
    document.body.style.overflow = 'hidden';
    if (branch) {
      handleServiceSelect(branch);
      return;
    }
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
            title: branch.title,
            q2Answer: updatedAnswers.q2Answer || 'Confirmed',
            q3Answer: updatedAnswers.q3Answer || 'Custom Scope',
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
            text: 'This is a research summary, not a quote or a reserved package. Where should we save it so you can continue with a fuller brief?',
          },
        ]);
        setChips([
          { label: 'Compare $199 and $499 website options', fn: () => (window.location.href = '/website-options') },
        ]);
        setInputPlaceholder('Enter your work email address...');
        inputRef.current?.focus();
      }, 500);
    }, 900);
  }

  async function finishWithEmail(email) {
    appendUserMsg(email);
    const finalData = { ...answers, email };
    setAnswers(finalData);
    setChips([]);
    setIsTyping(true);
    await submitIntakeData(finalData, selectedBranch);
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
          text: 'Save your research with an email first. Once the server confirms the request, you can create an account and add the details the team should review.',
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
    if (status === 'submitting') return;
    setStatus('submitting');
    setSubmissionError('');

    const transcript = messages.map((m) => `${m.who.toUpperCase()}: ${m.text || '[Card]'}`).join('\n');

    const payload = {
      source: 'solution-finder',
      branch: branch?.id || 'web-basics',
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
        recommendedStartingPoint: branch?.title,
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
      if (res?.ok !== true || !res?.request_id) {
        throw new Error(res?.message || 'We could not confirm that your request was saved.');
      }
      setRequestId(res.request_id);
      setStatus(res.status === 'partial_success' ? 'partial' : 'success');
      setMessages((prev) => [
        ...prev,
        {
          id: Date.now(),
          who: 'bot',
          text: res.status === 'partial_success'
            ? `Request #${res.request_id} is saved, but the confirmation notification needs attention. You can still continue securely with the account link below.`
            : `Request #${res.request_id} is saved. ${res.message || 'Continue with a free account to add the full brief.'}`,
        },
      ]);
      setChips([
        ...(res.registration_url
          ? [{
              label: 'Create a free account to continue',
              primary: true,
              fn: () => window.location.assign(res.registration_url),
            }]
          : []),
        { label: 'Compare website options', fn: () => (window.location.href = '/website-options') },
        { label: 'Close window', fn: () => closeChat() },
      ]);
    } catch (error) {
      setStatus('error');
      setSubmissionError(error?.message || 'We could not save your request.');
      setMessages((prev) => [
        ...prev,
        {
          id: Date.now(),
          who: 'bot',
          text: 'We could not confirm that your request was saved. Nothing has been submitted yet. Your answers are still here so you can try again.',
        },
      ]);
      setChips([
        { label: 'Try saving again', primary: true, fn: () => submitIntakeData(finalAnswers, branch) },
        { label: 'Change my email', fn: () => inputRef.current?.focus() },
        { label: 'Close window', fn: () => closeChat() },
      ]);
    } finally {
      setIsTyping(false);
    }
  }

  return (
    <div className="sf" id="solution-finder">
      {/* ============ HERO ENTRY POINT ============ */}
      <section className="sf__hero-entry">
        <span className="sf__ai-badge">FAMtastic Solutions Studio · Guided project research</span>
        <h2 className="sf__hero-title">
          Start with <span className="sf__accent-green">what your business needs to do.</span>
        </h2>
        <p className="sf__hero-sub">
          Answer a few practical questions. We will save a research summary only after the server confirms it, then you can add the full brief in your account.
        </p>

        <div className="sf__entry-box">
          <button
            type="button"
            className="sf__entry-btn"
            onClick={() => openChat()}
            aria-haspopup="dialog"
          >
            <span className="sf__entry-btn-text">What does your business need help with? (e.g. first website, store, client workspace...)</span>
            <span className="sf__entry-btn-go">Start research →</span>
          </button>

          <div className="sf__hero-chips">
            {SERVICE_BRANCHES.slice(0, 6).map((b) => (
              <button
                key={b.id}
                type="button"
                onClick={() => {
                  openChat(b);
                }}
              >
                {b.icon} {b.title}
              </button>
            ))}
          </div>

          <p className="sf__entry-hint">
            <strong>One question at a time.</strong> Clickable choices + fill-in-the-blank · <strong>saved only after confirmation</strong>
          </p>
        </div>

        <div className="sf__trust-row">
          <span><strong>Server-confirmed</strong> request saving</span>
          <span><strong>Account-based</strong> detailed brief</span>
          <span><strong>Scope-first</strong> website path</span>
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
                      : 'Research Summary'}
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
                        <span className="sf__scope-tag">{msg.scopeData.icon} Research summary</span>
                        <h3>{msg.scopeData.title}</h3>
                        <div className="sf__scope-city">We will use this context to prepare the next recommendation.</div>

                        <div className="sf__scope-row">
                          <span className="k">Target Objective</span>
                          <span className="v">{msg.scopeData.q2Answer}</span>
                        </div>
                        <div className="sf__scope-row">
                          <span className="k">Project Context</span>
                          <span className="v">{msg.scopeData.q3Answer}</span>
                        </div>

                        <div className="sf__scope-note">
                          This is not a quote, reserved price, or purchase approval. The saved request and your account brief determine the next step.
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
                {status === 'submitting' && <p className="sf__submission-status" role="status">Saving your request…</p>}
                {status === 'error' && <p className="sf__submission-status is-error" role="alert">{submissionError}</p>}
                {(status === 'success' || status === 'partial') && requestId && (
                  <p className="sf__submission-status" role="status">Server-confirmed request #{requestId}.</p>
                )}
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
                    disabled={isTyping || status === 'submitting' || status === 'success' || status === 'partial'}
                  />
                  <button
                    type="submit"
                    className="sf__send-btn"
                    disabled={isTyping || status === 'submitting' || status === 'success' || status === 'partial' || !inputVal.trim()}
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
