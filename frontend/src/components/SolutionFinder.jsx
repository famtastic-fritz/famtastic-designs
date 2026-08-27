import { useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router';
import { AnimatePresence, motion } from 'framer-motion';
import { collectUtmParams, getAiSolutionAdvice, postIntake } from '../api/pipeline.js';

const INITIAL_GREETING = "Tell me your business and your city — just that. I'll show you something in 20 seconds.";

const INITIAL_CHIPS = [
  'Barbershop / Salon',
  'Restaurant / Food',
  'Plumber / HVAC / Trades',
  'Dental / Healthcare',
  'Cleaning / Pressure Washing',
  'Something Else',
];

const ROADMAP_STEPS = [
  { num: 1, label: '1. Business & City' },
  { num: 2, label: '2. Market Scan' },
  { num: 3, label: '3. Custom Scope' },
  { num: 4, label: '4. Instant Blueprint' },
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
  // Scout State
  const [currentStepNum, setCurrentStepNum] = useState(1);
  const [messages, setMessages] = useState([
    { role: 'assistant', content: INITIAL_GREETING, marketScan: null },
  ]);
  const [quickChips, setQuickChips] = useState(INITIAL_CHIPS);
  const [gatheredData, setGatheredData] = useState({});
  const [inputVal, setInputVal] = useState('');
  const [inputPlaceholder, setInputPlaceholder] = useState('e.g. Barbershop in Port St. Lucie or Italian restaurant in Austin...');
  const [isTyping, setIsTyping] = useState(false);
  const [isArtifactReady, setIsArtifactReady] = useState(false);
  const [isComplete, setIsComplete] = useState(false);
  const [recommendation, setRecommendation] = useState({
    package_sku: 'web-basics',
    package_title: 'Web Basics Bundle — $199',
    price_formatted: '$199',
    price_estimate: 199,
  });

  // Intake submission status
  const [status, setStatus] = useState('idle'); // idle | submitting | success | error
  const [requestId, setRequestId] = useState(null);
  const [registrationUrl, setRegistrationUrl] = useState(null);
  const [serverMessage, setServerMessage] = useState(null);

  const messagesEndRef = useRef(null);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, isTyping, isArtifactReady]);

  // Initial branch handling
  useEffect(() => {
    if (initialBranch) {
      void handleUserSend(`I run a ${initialBranch} business.`);
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

        if (turn.step_number) {
          setCurrentStepNum(turn.step_number);
        }

        if (turn.recommendation) {
          setRecommendation(turn.recommendation);
        }

        setQuickChips(turn.quick_chips || []);
        if (turn.input_placeholder) {
          setInputPlaceholder(turn.input_placeholder);
        }

        // Add assistant message with market scan if present
        setMessages((prev) => [
          ...prev,
          {
            role: 'assistant',
            content: turn.reply,
            marketScan: turn.market_scan || null,
          },
        ]);

        if (turn.is_artifact_ready) {
          setIsArtifactReady(true);
        }

        if (turn.is_complete || updatedData.email) {
          setIsComplete(true);
          void submitIntake(updatedData, turn.recommendation || recommendation, newMessages);
        }
      }
    } catch {
      setMessages((prev) => [
        ...prev,
        {
          role: 'assistant',
          content: "I've synthesized your custom scope below! Where should I email your complete PDF blueprint?",
        },
      ]);
      setIsArtifactReady(true);
    } finally {
      setIsTyping(false);
    }
  }

  async function submitIntake(data, rec, allMsgs) {
    if (status === 'submitting' || requestId) return;
    setStatus('submitting');

    const conversationTranscript = allMsgs.map((m) => `${m.role.toUpperCase()}: ${m.content}`).join('\n');

    const payload = {
      source: 'famtastic-scout-ai',
      branch: rec?.package_sku || 'web-basics',
      answers: {
        ...data,
        conversationTranscript,
        recommendedPackage: rec?.package_title,
        leadScoreHot: data.lead_score_hot || false,
      },
      estimate: {
        low: rec?.price_estimate || 199,
        high: rec?.price_estimate || 199,
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
      setServerMessage(res?.message || 'Your project blueprint is saved and our team has been notified.');
      setStatus('success');
    } catch {
      setStatus('error');
    }
  }

  function handleReset() {
    setCurrentStepNum(1);
    setMessages([{ role: 'assistant', content: INITIAL_GREETING, marketScan: null }]);
    setQuickChips(INITIAL_CHIPS);
    setGatheredData({});
    setInputVal('');
    setInputPlaceholder('e.g. Barbershop in Port St. Lucie or Italian restaurant in Austin...');
    setIsTyping(false);
    setIsArtifactReady(false);
    setIsComplete(false);
    setStatus('idle');
    setRequestId(null);
    setRegistrationUrl(null);
    setServerMessage(null);
    setRecommendation({
      package_sku: 'web-basics',
      package_title: 'Web Basics Bundle — $199',
      price_formatted: '$199',
      price_estimate: 199,
    });
  }

  return (
    <div className="sf" id="solution-finder">
      <div style={{ textAlign: 'center', marginBottom: '1.25rem' }}>
        <span className="sf__ai-badge">⚡ FAMtastic Scout · Instant Market Scan</span>
        <h2 className="sf__title" style={{ margin: '0.4rem 0 0.5rem' }}>See What Your Market Is Doing in 20 Seconds</h2>
        <p className="sf__hint" style={{ margin: 0 }}>
          Real local competitive scans, custom sitemaps, and exact pricing—before you spend a dime.
        </p>
      </div>

      {/* 4-Step Roadmap Bar */}
      <div className="sf__scout-roadmap" role="progressbar" aria-valuenow={currentStepNum} aria-valuemin={1} aria-valuemax={4}>
        {ROADMAP_STEPS.map((s) => {
          const isActive = currentStepNum === s.num;
          const isPast = currentStepNum > s.num;
          return (
            <div
              key={s.num}
              className={`sf__scout-step-item${isActive ? ' is-active' : ''}${isPast ? ' is-past' : ''}`}
            >
              <div className="sf__scout-step-dot" />
              <span>{s.label}</span>
            </div>
          );
        })}
      </div>

      <div className="sf__chat-container">
        {/* Header with status and live estimate pill */}
        <div className="sf__chat-header">
          <div className="sf__chat-header-info">
            <div className="sf__chat-status-dot" />
            <span style={{ fontWeight: 600, fontSize: '0.9rem' }}>FAMtastic Scout (AI Market Advisor)</span>
          </div>
          <div className="sf__live-estimate-pill">
            <span>Price: {recommendation.price_formatted || '$199'}</span>
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
                <div>{msg.content}</div>

                {/* Render Market Scan Radar Box when present */}
                {msg.marketScan && (
                  <motion.div
                    className="sf__market-scan-box"
                    initial={{ opacity: 0, scale: 0.96 }}
                    animate={{ opacity: 1, scale: 1 }}
                    transition={{ duration: 0.3 }}
                  >
                    <div className="sf__market-scan-header">
                      <div className="sf__market-scan-radar" />
                      <span>{msg.marketScan.category} · {msg.marketScan.city} Scan</span>
                    </div>
                    <div className="sf__market-scan-headline">{msg.marketScan.headline}</div>
                    <div className="sf__market-scan-factor">
                      <strong>Winning Formula:</strong> {msg.marketScan.key_factor}
                    </div>
                  </motion.div>
                )}
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
              aria-label="Your message to Scout"
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
        </div>
      </div>

      {/* Payoff Artifact (Rendered on-screen!) */}
      <AnimatePresence>
        {isArtifactReady && recommendation && (
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
                  <span className="sf__ai-badge">⚡ Instant Project Blueprint</span>
                  <h3>{recommendation.package_title}</h3>
                  <small style={{ color: 'var(--fam-text-muted)' }}>
                    Turnaround: {recommendation.timeline || '3–5 business days'} · 1st-Year Hosting & Domain Included
                  </small>
                </div>
                <div className="sf__ai-price-pill">
                  {recommendation.price_formatted}
                </div>
              </div>

              <div className="sf__ai-rationale">
                <strong>Target Deliverable:</strong>
                <p style={{ margin: '0.4rem 0 0' }}>
                  {recommendation.personalized_rationale || 'Engineered for your local market with mobile lead capture, SEO indexing, and hosting included.'}
                </p>
              </div>

              <div className="sf__ai-grid">
                <div className="sf__ai-grid-box">
                  <h4>Recommended Architecture</h4>
                  <ul>
                    {(recommendation.recommended_pages || ['Home & Fast Booking', 'Services & Price Guide', 'Verified Reviews', 'Contact & Hours']).map((p) => (
                      <li key={p}>{p}</li>
                    ))}
                  </ul>
                </div>

                <div className="sf__ai-grid-box">
                  <h4>Included Deliverables</h4>
                  <ul>
                    {(recommendation.recommended_features || ['Mobile-responsive design', 'Lead capture & email alerts', 'Foundational local SEO', 'First-year hosting & domain']).map((f) => (
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
                  to={`/purchase?bundle=${encodeURIComponent(recommendation.package_sku || 'web-basics')}`}
                  className="v1-btn v1-btn--primary"
                >
                  Start with this Package ({recommendation.price_formatted}) →
                </Link>

                <button
                  type="button"
                  className="v1-btn v1-btn--ghost"
                  onClick={() => void handleUserSend('I want to talk to a real human')}
                >
                  Talk to a Real Human
                </button>

                {registrationUrl && (
                  <a
                    href={registrationUrl}
                    className="v1-btn v1-btn--ghost"
                  >
                    Access Your Client Portal Brief →
                  </a>
                )}

                <button
                  type="button"
                  className="v1-btn v1-btn--ghost"
                  onClick={handleReset}
                >
                  ↺ Scan Another Business
                </button>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
