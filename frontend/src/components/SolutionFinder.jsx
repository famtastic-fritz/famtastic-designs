import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router';
import { AnimatePresence, motion } from 'framer-motion';
import { collectUtmParams, getAiSolutionAdvice, postIntake } from '../api/pipeline.js';

const NICHES = {
  barber: {
    label: 'Barbershop / Salon',
    fact: "In your area, high-intent searches for grooming and styling happen overwhelmingly on mobile devices.",
    win: 'The top shops winning online let clients book a chair or service right from the homepage—no DM, no phone tag.',
    cost: "Every 'near me' search tonight lands on whoever made booking easiest. Without a mobile site, those clients go to a competitor.",
  },
  food: {
    label: 'Restaurant / Food',
    fact: 'Diners decide in under a minute—and over 80% check the menu and pricing online on mobile first.',
    win: 'Winning spots put their menu, catering inquiries, and call/order buttons at the very top of the page.',
    cost: "No website means local search shows a competitor's menu while yours stays invisible.",
  },
  church: {
    label: 'Church / Non-Profit',
    fact: 'New visitors almost always check service times, location, and beliefs online before ever visiting in person.',
    win: 'Growing organizations lead with service times, a "Plan Your Visit" button, and online giving options.',
    cost: "Without a dedicated site, first-time guests struggle to find times or directions—and most never ask.",
  },
  trades: {
    label: 'Trades & Home Services',
    fact: 'For plumbers, HVAC, electricians, and contractors, emergency local searches are the #1 source of high-ticket jobs.',
    win: 'The pros getting the calls showcase 5-star reviews, verified service areas, and a tap-to-call quote button.',
    cost: 'No website means the urgent repair call goes straight to the competitor who has one.',
  },
  health: {
    label: 'Healthcare & Wellness',
    fact: 'Patients and wellness clients prioritize provider credibility, credentials, and transparent consultation booking.',
    win: 'Top practices highlight provider bios, care options, and a seamless new-patient inquiry form.',
    cost: 'Without a trusted web presence, clients default to platform directories or competing clinics.',
  },
  default: {
    label: 'Local Business',
    fact: 'In almost every local market, customers check a business online before they ever call or visit.',
    win: 'The businesses winning make it effortless to see what they offer and take action right from a phone.',
    cost: 'Without a site, those ready-to-buy searches go straight to whoever shows up first.',
  },
};

function getNiche(biz = '') {
  const s = biz.toLowerCase();
  if (s.includes('barber') || s.includes('salon') || s.includes('hair') || s.includes('braid') || s.includes('nail')) return NICHES.barber;
  if (s.includes('restaurant') || s.includes('food') || s.includes('cafe') || s.includes('pizza') || s.includes('taco') || s.includes('catering')) return NICHES.food;
  if (s.includes('church') || s.includes('ministry') || s.includes('org') || s.includes('nonprofit')) return NICHES.church;
  if (s.includes('plumb') || s.includes('hvac') || s.includes('roof') || s.includes('clean') || s.includes('landscap') || s.includes('electric') || s.includes('trade')) return NICHES.trades;
  if (s.includes('dent') || s.includes('health') || s.includes('chiro') || s.includes('therapy') || s.includes('wellness') || s.includes('med')) return NICHES.health;
  return NICHES.default;
}

export function branchForServiceSlug(slug = '') {
  const s = slug.toLowerCase();
  if (s.includes('chatbot') || s.includes('chat') || s === 'ai') return 'chatbot';
  if (s.includes('app') || s.includes('mobile')) return 'app';
  if (s.includes('portal')) return 'portal';
  if (s.includes('custom') || s.includes('system') || s.includes('automation') || s.includes('integration')) return 'custom';
  if (s.includes('site') || s.includes('web') || s.includes('design') || s.includes('seo')) return 'website';
  return null;
}

export default function SolutionFinder({ initialBranch = null }) {
  const [isOpen, setIsOpen] = useState(false);
  const [step, setStep] = useState(1);
  const [messages, setMessages] = useState([]);
  const [chips, setChips] = useState([]);
  const [inputVal, setInputVal] = useState('');
  const [inputPlaceholder, setInputPlaceholder] = useState('Type your business and city...');
  const [isTyping, setIsTyping] = useState(false);
  const [state, setState] = useState({
    business: '',
    city: '',
    goal: '',
    visuals: '',
    when: '',
    price: 199,
    packageSku: 'web-basics',
    packageTitle: 'Web Basics Bundle — $199',
    email: '',
    phone: '',
    leadScoreHot: false,
  });

  const [status, setStatus] = useState('idle');
  const [requestId, setRequestId] = useState(null);
  const [registrationUrl, setRegistrationUrl] = useState(null);

  const chatBodyRef = useRef(null);
  const inputRef = useRef(null);

  // Auto-scroll chat body
  useEffect(() => {
    if (chatBodyRef.current) {
      chatBodyRef.current.scrollTop = chatBodyRef.current.scrollHeight;
    }
  }, [messages, isTyping, chips]);

  // Initial branch trigger if provided
  useEffect(() => {
    if (initialBranch) {
      openChatWithInitial(initialBranch);
    }
  }, [initialBranch]);

  function openChatWithInitial(initialText = '') {
    setIsOpen(true);
    document.body.style.overflow = 'hidden';
    if (messages.length === 0) {
      startChatFlow(initialText);
    }
  }

  function closeChat() {
    setIsOpen(false);
    document.body.style.overflow = '';
  }

  function startChatFlow(initialText = '') {
    setStep(1);
    setIsTyping(true);
    setTimeout(() => {
      setIsTyping(false);
      setMessages([
        {
          id: 1,
          who: 'bot',
          text: "Hey — I'm Scout. Tell me two things: what kind of business you run, and what city you're in. I'll show you something useful in about 20 seconds.",
        },
      ]);
      setChips([
        { label: 'Barbershop / Salon', fn: () => handleBusinessSelect('Barbershop / Salon') },
        { label: 'Restaurant / Food', fn: () => handleBusinessSelect('Restaurant / Food') },
        { label: 'Trades & Home Services', fn: () => handleBusinessSelect('Trades & Home Services') },
        { label: 'Healthcare & Wellness', fn: () => handleBusinessSelect('Healthcare & Wellness') },
        { label: 'Church / Non-Profit', fn: () => handleBusinessSelect('Church / Non-Profit') },
      ]);
      setInputPlaceholder('e.g. Barbershop in Port St. Lucie...');

      if (initialText) {
        handleUserText(initialText);
      }
    }, 450);
  }

  function handleBusinessSelect(biz) {
    appendUserMsg(biz);
    setState((prev) => ({ ...prev, business: biz }));
    setChips([]);
    setIsTyping(true);

    setTimeout(() => {
      setIsTyping(false);
      setMessages((prev) => [
        ...prev,
        { id: Date.now(), who: 'bot', text: `Nice. And what city or town are you located in?` },
      ]);
      setInputPlaceholder('e.g. Port St. Lucie, FL or Austin, TX');
      inputRef.current?.focus();
    }, 400);
  }

  function runMarketScan(biz, city) {
    setStep(2);
    setChips([]);
    setIsTyping(true);

    const niche = getNiche(biz);
    const cityClean = city || 'your local area';

    setTimeout(() => {
      setIsTyping(false);
      setMessages((prev) => [
        ...prev,
        {
          id: Date.now(),
          who: 'bot',
          isScanCard: true,
          scanData: {
            niche: niche.label,
            city: cityClean,
            fact: niche.fact,
            win: niche.win,
            cost: niche.cost,
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
            text: "That's the landscape. Want me to scope what it'd take to get your site live? About a minute, three quick questions.",
          },
        ]);
        setChips([
          { label: 'Yes, scope it →', primary: true, fn: () => askGoal(biz, cityClean) },
          { label: 'How much does this cost?', fn: () => askCost(biz, cityClean) },
          { label: 'Talk to a real human', fn: () => escalateHuman() },
        ]);
      }, 500);
    }, 1200);
  }

  function askCost(biz, city) {
    appendUserMsg('How much does this cost?');
    setChips([]);
    setIsTyping(true);

    setTimeout(() => {
      setIsTyping(false);
      setMessages((prev) => [
        ...prev,
        {
          id: Date.now(),
          who: 'bot',
          text: "The scan is 100% free. If you want the site, a professional one-page build starts at $199 (about 55¢ a day). Multi-page and booking systems are $499. You'll see the exact, locked price right on screen before you commit.",
        },
      ]);
      setChips([
        { label: 'Okay, scope it →', primary: true, fn: () => askGoal(biz, city) },
        { label: 'Talk to a real human', fn: () => escalateHuman() },
      ]);
    }, 450);
  }

  function askGoal(biz, city) {
    appendUserMsg('Yes, scope it →');
    setStep(3);
    setChips([]);
    setIsTyping(true);

    setTimeout(() => {
      setIsTyping(false);
      setMessages((prev) => [
        ...prev,
        {
          id: Date.now(),
          who: 'bot',
          text: "Question 1 of 3 — do you need customers to book appointments or buy online, or is this more of an informational 'here's who we are' site?",
        },
      ]);
      setChips([
        {
          label: 'Bookings / Online Sales ($499)',
          fn: () => {
            setState((p) => ({ ...p, goal: 'Bookings & Payments', price: 499, packageSku: 'business-website', packageTitle: 'Business Website Bundle — $499' }));
            askVisuals('Bookings / Online Sales ($499)');
          },
        },
        {
          label: 'Info + Contact Form ($199)',
          fn: () => {
            setState((p) => ({ ...p, goal: 'Info & Lead Capture', price: 199, packageSku: 'web-basics', packageTitle: 'Web Basics Bundle — $199' }));
            askVisuals('Info + Contact Form ($199)');
          },
        },
        {
          label: 'Full Connected System ($3,999)',
          fn: () => {
            setState((p) => ({ ...p, goal: 'Full Business Growth System', price: 3999, packageSku: 'business-growth', packageTitle: 'Business Growth System — $3,999' }));
            askVisuals('Full Connected System ($3,999)');
          },
        },
      ]);
    }, 450);
  }

  function askVisuals(answerText) {
    appendUserMsg(answerText);
    setChips([]);
    setIsTyping(true);

    setTimeout(() => {
      setIsTyping(false);
      setMessages((prev) => [
        ...prev,
        {
          id: Date.now(),
          who: 'bot',
          text: 'Question 2 of 3 — do you already have photos and a logo ready, or should we help create the visuals too?',
        },
      ]);
      setChips([
        { label: 'I have them ready', fn: () => askWhen('I have them ready') },
        { label: 'Need them created', fn: () => askWhen('Need them created') },
        { label: 'Some of both', fn: () => askWhen('Some of both') },
      ]);
    }, 450);
  }

  function askWhen(answerText) {
    appendUserMsg(answerText);
    setState((p) => ({ ...p, visuals: answerText }));
    setChips([]);
    setIsTyping(true);

    setTimeout(() => {
      setIsTyping(false);
      setMessages((prev) => [
        ...prev,
        {
          id: Date.now(),
          who: 'bot',
          text: 'Last one — when do you want to be live?',
        },
      ]);
      setChips([
        { label: 'ASAP (3–5 business days)', fn: () => revealScope('ASAP (3–5 business days)') },
        { label: 'Within this month', fn: () => revealScope('Within this month') },
        { label: 'Just researching for now', fn: () => revealScope('Just researching for now') },
      ]);
    }, 450);
  }

  function revealScope(whenAnswer) {
    appendUserMsg(whenAnswer);
    setStep(4);
    setState((p) => ({ ...p, when: whenAnswer }));
    setChips([]);
    setIsTyping(true);

    setTimeout(() => {
      setIsTyping(false);
      const niche = getNiche(state.business);

      setMessages((prev) => [
        ...prev,
        {
          id: Date.now(),
          who: 'bot',
          isScopeCard: true,
          scopeData: {
            niche: niche.label,
            city: state.city || 'Your Area',
            pages: state.price === 199 ? '1 Focused High-Conversion Page' : state.price === 499 ? 'Up to 5 Pages (Home, Services, Reviews, Booking, Contact)' : 'Comprehensive Multi-Page System',
            features: state.goal || 'Contact form + Google Maps & local SEO',
            visuals: state.visuals || 'Provided by client / assisted',
            timeline: whenAnswer.includes('ASAP') ? 'Live in 3–5 business days' : 'Live in 5–10 business days',
            price: state.price,
            packageTitle: state.packageTitle,
            packageSku: state.packageSku,
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
            text: "There it is — in writing, with a real number and a locked price. What email address should I use to send you this complete PDF blueprint?",
          },
        ]);
        setChips([
          { label: `Start with this Package ($${state.price}) →`, primary: true, fn: () => window.location.href = `/purchase?bundle=${state.packageSku}` },
          { label: 'Talk to a real human', fn: () => escalateHuman() },
        ]);
        setInputPlaceholder('Enter your email to receive PDF blueprint...');
        inputRef.current?.focus();
      }, 500);
    }, 1100);
  }

  function finishWithEmail(email) {
    appendUserMsg(email);
    setState((p) => ({ ...p, email }));
    setChips([]);
    setIsTyping(true);

    // Save lead into Drupal pipeline
    void submitIntakeData({ ...state, email });

    setTimeout(() => {
      setIsTyping(false);
      setMessages((prev) => [
        ...prev,
        {
          id: Date.now(),
          who: 'bot',
          text: `Done! Your project blueprint is on its way to ${email}. Your $${state.price} price is locked for 30 days with zero follow-up pressure. When you're ready, you can start the build with one click from that email or right here.`,
        },
      ]);
      setChips([
        { label: `Start My Build ($${state.price}) →`, primary: true, fn: () => window.location.href = `/purchase?bundle=${state.packageSku}` },
        { label: 'Talk to a real human', fn: () => escalateHuman() },
        { label: 'Done for now', fn: () => closeChat() },
      ]);
    }, 500);
  }

  function escalateHuman() {
    appendUserMsg('I want to talk to a real human');
    setState((p) => ({ ...p, leadScoreHot: true }));
    setChips([]);
    setIsTyping(true);

    setTimeout(() => {
      setIsTyping(false);
      setMessages((prev) => [
        ...prev,
        {
          id: Date.now(),
          who: 'bot',
          text: "Absolutely! I've flagged our senior team for a personal follow-up within one business day. What's the best phone number or email to reach you?",
        },
      ]);
      setInputPlaceholder('Enter your phone or email...');
      inputRef.current?.focus();
    }, 450);
  }

  function appendUserMsg(text) {
    setMessages((prev) => [...prev, { id: Date.now(), who: 'user', text }]);
  }

  function handleUserText(raw) {
    const text = (raw || inputVal).trim();
    if (!text || isTyping) return;
    setInputVal('');

    // If waiting for email
    if (step === 4 && text.includes('@')) {
      finishWithEmail(text);
      return;
    }

    // If in initial step
    if (step === 1) {
      appendUserMsg(text);
      // Check if business and city are supplied together (e.g. "Barbershop in Port St. Lucie")
      const m = text.match(/^(.+?)\s+(?:in|near|around)\s+([A-Za-z][A-Za-z .']{2,})$/i)
             || text.match(/^(.+?),\s*([A-Za-z][A-Za-z .']{2,})$/);

      if (m && m[1].trim().length > 2) {
        const biz = m[1].trim();
        const city = m[2].trim();
        setState((p) => ({ ...p, business: biz, city }));
        runMarketScan(biz, city);
        return;
      }

      if (!state.business) {
        setState((p) => ({ ...p, business: text }));
        setChips([]);
        setIsTyping(true);
        setTimeout(() => {
          setIsTyping(false);
          setMessages((prev) => [
            ...prev,
            { id: Date.now(), who: 'bot', text: `Nice. And what city or town are you located in?` },
          ]);
          setInputPlaceholder('e.g. Port St. Lucie, FL');
          inputRef.current?.focus();
        }, 400);
        return;
      }

      if (!state.city) {
        setState((p) => ({ ...p, city: text }));
        runMarketScan(state.business, text);
        return;
      }
    }

    // General fallback message handling
    appendUserMsg(text);
    setIsTyping(true);
    setTimeout(() => {
      setIsTyping(false);
      setMessages((prev) => [
        ...prev,
        { id: Date.now(), who: 'bot', text: "Got that noted! Where should we send your official project scope PDF?" },
      ]);
      setInputPlaceholder('Enter your email...');
    }, 450);
  }

  async function submitIntakeData(fullData) {
    if (status === 'submitting' || requestId) return;
    setStatus('submitting');

    const transcript = messages.map((m) => `${m.who.toUpperCase()}: ${m.text || '[Card]'}`).join('\n');

    const payload = {
      source: 'famtastic-scout-overlay',
      branch: fullData.packageSku || 'web-basics',
      answers: {
        businessName: fullData.business,
        city: fullData.city,
        goal: fullData.goal,
        visuals: fullData.visuals,
        timeline: fullData.when,
        email: fullData.email,
        phone: fullData.phone,
        leadScoreHot: fullData.leadScoreHot,
        conversationTranscript: transcript,
        recommendedPackage: fullData.packageTitle,
      },
      estimate: {
        low: fullData.price,
        high: fullData.price,
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
      setRegistrationUrl(res?.registration_url || null);
      setStatus('success');
    } catch {
      setStatus('error');
    }
  }

  function handleHeroChipClick(nicheName) {
    openChatWithInitial(nicheName);
  }

  return (
    <div className="sf" id="solution-finder">
      {/* ============ HERO ENTRY POINT (NOT a giant clunky panel) ============ */}
      <section className="sf__hero-entry">
        <span className="sf__ai-badge">⚡ FAMtastic Scout · Instant Market Scan</span>
        <h2 className="sf__hero-title">
          See what your market is doing <span className="sf__accent-green">in 20 seconds.</span>
        </h2>
        <p className="sf__hero-sub">
          Real local competitive scans, a custom sitemap, and exact pricing—before you spend a dime. No account. No email. Just answers.
        </p>

        <div className="sf__entry-box">
          <button
            type="button"
            className="sf__entry-btn"
            onClick={() => openChatWithInitial('')}
            aria-haspopup="dialog"
          >
            <span className="sf__entry-btn-text">Tell Scout your business + city…</span>
            <span className="sf__entry-btn-go">Start free scan →</span>
          </button>

          <div className="sf__hero-chips">
            <button type="button" onClick={() => handleHeroChipClick('Barbershop in Port St. Lucie')}>Barbershop</button>
            <button type="button" onClick={() => handleHeroChipClick('Restaurant & Catering')}>Restaurant</button>
            <button type="button" onClick={() => handleHeroChipClick('Church & Community')}>Church</button>
            <button type="button" onClick={() => handleHeroChipClick('Plumbing & Trades')}>Trades</button>
            <button type="button" onClick={() => handleHeroChipClick('Dental & Healthcare')}>Healthcare</button>
          </div>

          <p className="sf__entry-hint">
            <strong>20 seconds.</strong> Free instant scan · No sign-up to look · <strong>From $199</strong>
          </p>
        </div>

        <div className="sf__trust-row">
          <span><strong>22+ yrs</strong> engineering systems</span>
          <span><strong>Fixed price</strong> before we start</span>
          <span><strong>Verified working</strong> before you pay</span>
        </div>
      </section>

      {/* ============ CHAT OVERLAY (Mobile Full-Screen / Desktop Focused Modal Sheet) ============ */}
      <AnimatePresence>
        {isOpen && (
          <motion.div
            className="sf__overlay"
            role="dialog"
            aria-modal="true"
            aria-label="Chat with FAMtastic Scout"
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
                <div className="sf__avatar">S</div>
                <div className="sf__who">
                  <b>Scout</b>
                  <span>AI Market Advisor · online</span>
                </div>
                <button type="button" className="sf__chat-close" onClick={closeChat} aria-label="Close chat">
                  ✕
                </button>
              </div>

              {/* Progress Bar */}
              <div className="sf__progress-wrap">
                <div className="sf__progress-label">
                  <span>Step {step} of 4 · {step === 1 ? 'Your business' : step === 2 ? 'Market scan' : step === 3 ? 'Custom scope' : 'Instant blueprint'}</span>
                  <span>{step * 25}%</span>
                </div>
                <div className="sf__progress-bar">
                  <div className="sf__progress-bar-fill" style={{ width: `${step * 25}%` }} />
                </div>
              </div>

              {/* Chat Body */}
              <div className="sf__chat-body" ref={chatBodyRef}>
                {messages.map((msg) => (
                  <div key={msg.id} className={`sf__msg-row ${msg.who}`}>
                    {msg.isScanCard ? (
                      <div className="sf__scan-card">
                        <span className="sf__scan-tag">Live Market Scan</span>
                        <h3>{msg.scanData.niche}</h3>
                        <div className="sf__scan-city">{msg.scanData.city}</div>
                        <div className="sf__scan-item">
                          <span className="sf__scan-dot">✓</span>
                          <span>{msg.scanData.fact}</span>
                        </div>
                        <div className="sf__scan-item">
                          <span className="sf__scan-dot">✓</span>
                          <span>{msg.scanData.win}</span>
                        </div>
                        <div className="sf__scan-verdict">
                          <strong>The gap:</strong> {msg.scanData.cost}
                        </div>
                      </div>
                    ) : msg.isScopeCard ? (
                      <div className="sf__scope-card">
                        <span className="sf__scope-tag">Your Project Scope</span>
                        <h3>{msg.scopeData.niche}</h3>
                        <div className="sf__scope-city">{msg.scopeData.city}</div>

                        <div className="sf__scope-row">
                          <span className="k">Pages</span>
                          <span className="v">{msg.scopeData.pages}</span>
                        </div>
                        <div className="sf__scope-row">
                          <span className="k">Features</span>
                          <span className="v">{msg.scopeData.features}</span>
                        </div>
                        <div className="sf__scope-row">
                          <span className="k">Visuals</span>
                          <span className="v">{msg.scopeData.visuals}</span>
                        </div>
                        <div className="sf__scope-row">
                          <span className="k">Timeline</span>
                          <span className="v">{msg.scopeData.timeline}</span>
                        </div>

                        <div className="sf__scope-price-box">
                          <span className="label">Fixed Price</span>
                          <span className="amount">${msg.scopeData.price}</span>
                        </div>
                        <div className="sf__scope-note">
                          Price locked for 30 days · 1st-year hosting & domain included
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
                    handleUserText(inputVal);
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
                    aria-label="Send message"
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
