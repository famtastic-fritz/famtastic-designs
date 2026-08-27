import { useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router';
import { AnimatePresence, motion } from 'framer-motion';
import { collectUtmParams, getAiSolutionAdvice, postIntake } from '../api/pipeline.js';

const INITIAL_GREETING = "Hi there! I'm FAMtastic's AI Project Advisor. What is your business name and what kind of website or digital system are you looking to build?";

const INITIAL_CHIPS = [
  'Local Service Business',
  'Restaurant & Catering',
  'Healthcare & Wellness',
  'Professional Consulting',
  'E-Commerce & Online Store',
];

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
  // Conversational state
  const [messages, setMessages] = useState([
    { role: 'assistant', content: INITIAL_GREETING },
  ]);
  const [quickChips, setQuickChips] = useState(INITIAL_CHIPS);
  const [gatheredData, setGatheredData] = useState({});
  const [inputVal, setInputVal] = useState('');
  const [inputPlaceholder, setInputPlaceholder] = useState('e.g. Bella Cucina — authentic Italian restaurant in Dallas...');
  const [isTyping, setIsTyping] = useState(false);
  const [isComplete, setIsComplete] = useState(false);
  const [recommendation, setRecommendation] = useState({
    package_sku: 'business-website',
    package_title: 'Business Website Bundle — $499',
    price_formatted: '$499',
    price_estimate: 499,
  });

  // Intake submission status
  const [status, setStatus] = useState('idle'); // idle | submitting | success | error
  const [requestId, setRequestId] = useState(null);
  const [registrationUrl, setRegistrationUrl] = useState(null);
  const [serverMessage, setServerMessage] = useState(null);

  const messagesEndRef = useRef(null);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, isTyping]);

  // Initial branch handling if passed
  useEffect(() => {
    if (initialBranch) {
      void handleUserSend(`I'm interested in the ${initialBranch} solution.`);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initialBranch]);

  async function handleUserSend(textToSend) {
    const text = (textToSend || inputVal).trim();
    if (!text || isTyping) return;

    setInputVal('');
    const newMessages = [...messages, { role: 'user', content: text }];
    setMessages(newMessages);
    setIsTyping(true);

    try {
      const res = await getAiSolutionAdvice({
        messages: newMessages,
        gathered_data: gatheredData,
        context: collectUtmParams(),
      });

      if (res?.turn) {
        const turn = res.turn;
        const updatedData = turn.gathered_data || gatheredData;
        setGatheredData(updatedData);

        if (turn.recommendation) {
          setRecommendation(turn.recommendation);
        }

        setQuickChips(turn.quick_chips || []);
        if (turn.input_placeholder) {
          setInputPlaceholder(turn.input_placeholder);
        }

        setMessages((prev) => [...prev, { role: 'assistant', content: turn.reply }]);

        if (turn.is_complete || updatedData.email) {
          setIsComplete(true);
          void submitIntake(updatedData, turn.recommendation || recommendation, newMessages);
        }
      } else if (res?.recommendation) {
        setRecommendation(res.recommendation);
        setMessages((prev) => [
          ...prev,
          { role: 'assistant', content: `Got it! I recommend our ${res.recommendation.package_title}. What is the best email to send your quote receipt and project blueprint?` },
        ]);
        setQuickChips([]);
      }
    } catch {
      setMessages((prev) => [
        ...prev,
        { role: 'assistant', content: "Thanks for sharing! What's the best email address to send your personalized proposal and project blueprint?" },
      ]);
    } finally {
      setIsTyping(false);
    }
  }

  async function submitIntake(data, rec, allMsgs) {
    if (status === 'submitting' || requestId) return;
    setStatus('submitting');

    const conversationTranscript = allMsgs.map((m) => `${m.role.toUpperCase()}: ${m.content}`).join('\n');

    const payload = {
      source: 'conversational-ai-solution-finder',
      branch: rec?.package_sku || 'business-website',
      answers: {
        ...data,
        conversationTranscript,
        recommendedPackage: rec?.package_title,
      },
      estimate: {
        low: rec?.price_estimate || 499,
        high: rec?.price_estimate || 499,
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
      setServerMessage(res?.message || 'Your project brief has been saved and our team has been notified.');
      setStatus('success');
    } catch {
      setStatus('error');
    }
  }

  function handleReset() {
    setMessages([{ role: 'assistant', content: INITIAL_GREETING }]);
    setQuickChips(INITIAL_CHIPS);
    setGatheredData({});
    setInputVal('');
    setInputPlaceholder('e.g. Bella Cucina — authentic Italian restaurant in Dallas...');
    setIsTyping(false);
    setIsComplete(false);
    setStatus('idle');
    setRequestId(null);
    setRegistrationUrl(null);
    setServerMessage(null);
    setRecommendation({
      package_sku: 'business-website',
      package_title: 'Business Website Bundle — $499',
      price_formatted: '$499',
      price_estimate: 499,
    });
  }

  return (
    <div className="sf" id="solution-finder">
      <div style={{ textAlign: 'center', marginBottom: '1rem' }}>
        <span className="sf__ai-badge">⚡ AI-Powered Conversational Discovery</span>
        <h2 className="sf__title" style={{ margin: '0.4rem 0 0.6rem' }}>Let's Scope Your Project Together</h2>
        <p className="sf__hint" style={{ margin: 0 }}>
          Chat with our Drupal AI Project Advisor to get a tailored scope, exact price, and sitemap blueprint in under 2 minutes.
        </p>
      </div>

      <div className="sf__chat-container">
        {/* Header with status and live estimate pill */}
        <div className="sf__chat-header">
          <div className="sf__chat-header-info">
            <div className="sf__chat-status-dot" />
            <span style={{ fontWeight: 600, fontSize: '0.9rem' }}>FAMtastic Project Advisor</span>
          </div>
          <div className="sf__live-estimate-pill">
            <span>Estimated: {recommendation.price_formatted || '$499'}</span>
          </div>
        </div>

        {/* Message Stream */}
        <div className="sf__chat-messages">
          {messages.map((msg, idx) => (
            <motion.div
              key={idx}
              className={`sf__msg sf__msg--${msg.role}`}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.25 }}
            >
              <div className="sf__msg-avatar">
                {msg.role === 'assistant' ? '✦' : 'You'}
              </div>
              <div className="sf__msg-bubble">
                {msg.content}
              </div>
            </motion.div>
          ))}

          {isTyping && (
            <motion.div
              className="sf__msg sf__msg--assistant"
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
            >
              <div className="sf__msg-avatar">✦</div>
              <div className="sf__msg-bubble">
                <div className="sf__typing-indicator">
                  <span />
                  <span />
                  <span />
                </div>
              </div>
            </motion.div>
          )}

          <div ref={messagesEndRef} />
        </div>

        {/* Quick Chips & Text Input */}
        <div className="sf__chat-footer">
          {quickChips && quickChips.length > 0 && !isComplete && (
            <div className="sf__chat-chips" role="group" aria-label="Suggested responses">
              {quickChips.map((chip, idx) => (
                <button
                  key={idx}
                  type="button"
                  className="sf__chat-chip-btn"
                  onClick={() => void handleUserSend(chip)}
                  disabled={isTyping}
                >
                  {chip}
                </button>
              ))}
            </div>
          )}

          {!isComplete ? (
            <form
              className="sf__chat-input-bar"
              onSubmit={(e) => {
                e.preventDefault();
                void handleUserSend(inputVal);
              }}
            >
              <input
                type="text"
                className="sf__chat-input"
                value={inputVal}
                onChange={(e) => setInputVal(e.target.value)}
                placeholder={inputPlaceholder}
                disabled={isTyping}
                aria-label="Your response to the AI Advisor"
              />
              <button
                type="submit"
                className="sf__chat-send-btn"
                disabled={isTyping || !inputVal.trim()}
                title="Send message"
              >
                ➤
              </button>
            </form>
          ) : (
            <div style={{ display: 'flex', justifyContent: 'center', gap: '0.75rem' }}>
              <button
                type="button"
                className="v1-btn v1-btn--ghost v1-btn--sm"
                onClick={handleReset}
              >
                ↺ Start a New Consultation
              </button>
            </div>
          )}
        </div>
      </div>

      {/* Completed Proposal Card */}
      <AnimatePresence>
        {isComplete && recommendation && (
          <motion.div
            initial={{ opacity: 0, y: 24 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -16 }}
            transition={{ duration: 0.45 }}
            style={{ marginTop: '2rem' }}
          >
            <div className="sf__ai-card">
              <div className="sf__ai-header">
                <div>
                  <span className="sf__ai-badge">⚡ Official AI Project Recommendation</span>
                  <h3>{recommendation.package_title}</h3>
                  <small style={{ color: 'var(--fam-text-muted)' }}>
                    Estimated Timeline: {recommendation.timeline || '5–10 business days'}
                  </small>
                </div>
                <div className="sf__ai-price-pill">
                  {recommendation.price_formatted}
                </div>
              </div>

              <div className="sf__ai-rationale">
                <strong>Why this fits your business:</strong>
                <p style={{ margin: '0.4rem 0 0' }}>
                  {recommendation.personalized_rationale || 'Tailored to your specific industry requirements with hosting, domain, and lead capture included.'}
                </p>
              </div>

              <div className="sf__ai-grid">
                <div className="sf__ai-grid-box">
                  <h4>Recommended Sitemap</h4>
                  <ul>
                    {(recommendation.recommended_pages || ['Home', 'Services', 'About Us', 'Reviews', 'Contact & Booking']).map((p) => (
                      <li key={p}>{p}</li>
                    ))}
                  </ul>
                </div>

                <div className="sf__ai-grid-box">
                  <h4>Included Capabilities</h4>
                  <ul>
                    {(recommendation.recommended_features || ['Mobile-responsive layout', 'Local SEO foundations', 'Lead capture & email alerts', '1st-year hosting & domain']).map((f) => (
                      <li key={f}>{f}</li>
                    ))}
                  </ul>
                </div>
              </div>

              {serverMessage && (
                <p className="sf__note" style={{ marginBottom: '1.25rem' }}>
                  ✓ {serverMessage}
                </p>
              )}

              <div className="sf__result-actions">
                <Link
                  to={`/purchase?bundle=${encodeURIComponent(recommendation.package_sku || 'business-website')}`}
                  className="v1-btn v1-btn--primary"
                >
                  Start with this Package ({recommendation.price_formatted}) →
                </Link>

                {registrationUrl ? (
                  <a
                    href={registrationUrl}
                    className="v1-btn v1-btn--ghost"
                  >
                    Access Your Client Portal Brief →
                  </a>
                ) : (
                  <Link to="/contact#contact-form" className="v1-btn v1-btn--ghost">
                    Speak with Our Team
                  </Link>
                )}
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
