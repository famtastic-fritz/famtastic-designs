import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router';
import { answerDeepDive, getDeepDive } from '../api/customer.js';
import '../portal.css';

const continuationKey = 'famtastic.deep_dive_continuation';

function normalizePublicUrl(value) {
  const trimmed = value.trim();
  return trimmed && !/^[a-z][a-z\d+.-]*:\/\//i.test(trimmed) ? `https://${trimmed}` : trimmed;
}

export default function DeepDivePage() {
  const { invitation = '' } = useParams();
  const navigate = useNavigate();
  const secret = useMemo(() => window.location.hash.replace(/^#/, ''), []);
  const [deepDive, setDeepDive] = useState(null);
  const [answer, setAnswer] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    let current = true;
    if (!secret) {
      setError('This private interview link is incomplete. Please return to the original email link.');
      return undefined;
    }
    getDeepDive(invitation, secret)
      .then((result) => { if (current) setDeepDive(result); })
      .catch((requestError) => { if (current) setError(requestError.message); });
    return () => { current = false; };
  }, [invitation, secret]);

  const question = deepDive?.question;
  const progress = deepDive?.invitation?.progress;

  async function submit(event) {
    event.preventDefault();
    if (!question || !answer.trim()) return;
    setBusy(true);
    setError('');
    try {
      const result = await answerDeepDive(invitation, secret, question.key, answer);
      setDeepDive(result);
      setAnswer('');
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setBusy(false);
    }
  }

  function continueToAccount() {
    sessionStorage.setItem(continuationKey, `${invitation}.${secret}`);
    const business = deepDive?.invitation?.business_name || '';
    navigate(`/login?mode=register&source=deep_dive&business=${encodeURIComponent(business)}`);
  }

  return (
    <main className="portal-main" style={{ maxWidth: 760, margin: '0 auto', padding: 'clamp(1rem, 4vw, 3rem) 1rem 4rem' }}>
      <section className="portal-panel" style={{ padding: 'clamp(1.25rem, 4vw, 2.5rem)', borderRadius: 20 }}>
        <header style={{ borderBottom: '1px solid rgba(255,255,255,.1)', paddingBottom: '1.25rem', marginBottom: '1.5rem' }}>
          <span className="portal-eyebrow">Private planning interview</span>
          <h1 style={{ margin: '.35rem 0 .7rem', fontSize: 'clamp(1.8rem, 6vw, 3rem)' }}>{deepDive?.invitation?.business_name || 'Your website'} deserves a deeper look.</h1>
          <p style={{ maxWidth: 620, color: '#b9c4b9', lineHeight: 1.6, margin: 0 }}>One question at a time. Your answers are private, saved as you go, and used only to shape an owner-reviewed website, booking, and growth plan.</p>
        </header>

        {progress && <div aria-label={`Interview progress: ${progress.complete} of ${progress.total}`} style={{ marginBottom: '1.75rem' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', gap: '1rem', color: '#aeb9ae', fontSize: '.82rem', marginBottom: '.5rem' }}><span>Planning progress</span><span>{progress.complete} of {progress.total}</span></div>
          <div style={{ height: 7, borderRadius: 99, background: '#252b25', overflow: 'hidden' }}><div style={{ height: '100%', width: `${Math.round((progress.complete / progress.total) * 100)}%`, background: '#7cfc00', transition: 'width .25s ease' }} /></div>
        </div>}

        {error && <p role="alert" className="portal-alert portal-alert--error">{error}</p>}
        {!deepDive && !error && <p role="status">Opening your private interview…</p>}

        {question && (
          <form onSubmit={submit}>
            <label htmlFor="deep-dive-answer" style={{ display: 'block', color: '#e6eee6', fontSize: 'clamp(1.2rem, 4vw, 1.55rem)', fontWeight: 800, lineHeight: 1.3 }}>{question.title}</label>
            {question.help && <p id="deep-dive-help" style={{ color: '#aeb9ae', lineHeight: 1.5, margin: '.7rem 0 1.1rem' }}>{question.help}</p>}
            {question.type === 'choice' ? (
              <div role="radiogroup" aria-describedby={question.help ? 'deep-dive-help' : undefined} style={{ display: 'grid', gap: '.65rem', marginTop: '1rem' }}>
                {Object.entries(question.options || {}).map(([value, label]) => (
                  <button key={value} type="button" role="radio" aria-checked={answer === value} onClick={() => setAnswer(value)} style={{ minHeight: 52, padding: '.85rem 1rem', textAlign: 'left', borderRadius: 12, cursor: 'pointer', border: `1px solid ${answer === value ? '#7cfc00' : '#303930'}`, color: answer === value ? '#d8ffd0' : '#e5ece5', background: answer === value ? 'rgba(124,252,0,.12)' : 'rgba(255,255,255,.025)' }}>{label}</button>
                ))}
              </div>
            ) : question.type === 'textarea' ? (
              <textarea id="deep-dive-answer" aria-describedby={question.help ? 'deep-dive-help' : undefined} value={answer} onChange={(event) => setAnswer(event.target.value)} required rows="6" maxLength="3000" style={{ width: '100%', boxSizing: 'border-box', marginTop: '1rem', borderRadius: 12, border: '1px solid #303930', background: '#0a0d0a', color: '#fff', padding: '.9rem 1rem', font: 'inherit', lineHeight: 1.5 }} />
            ) : (
              <input id="deep-dive-answer" aria-describedby={question.help ? 'deep-dive-help' : undefined} type="text" inputMode={question.type === 'url' ? 'url' : 'text'} autoCapitalize="none" autoCorrect="off" value={answer} onChange={(event) => setAnswer(event.target.value)} onBlur={() => { if (question.type === 'url') setAnswer((current) => normalizePublicUrl(current)); }} required maxLength="3000" style={{ width: '100%', boxSizing: 'border-box', minHeight: 52, marginTop: '1rem', borderRadius: 12, border: '1px solid #303930', background: '#0a0d0a', color: '#fff', padding: '.75rem 1rem', font: 'inherit' }} />
            )}
            <button className="btn btn--lime" type="submit" disabled={busy || !answer.trim()} style={{ marginTop: '1.25rem', minHeight: 50 }}>{busy ? 'Saving…' : 'Save and continue →'}</button>
          </form>
        )}

        {deepDive?.invitation?.completed && !question && (
          <section aria-labelledby="deep-dive-complete">
            <span className="portal-eyebrow">Interview saved</span>
            <h2 id="deep-dive-complete" style={{ margin: '.35rem 0 .75rem' }}>Your private project brief is ready for account connection.</h2>
            <p style={{ color: '#b9c4b9', lineHeight: 1.6 }}>Create and verify your free account with this same email address. Your answers will appear in your private workspace as a draft—not as an order, live booking system, payment setup, or published site. FAMtastic reviews the complete brief before any six-direction proof work begins.</p>
            <button type="button" className="btn btn--lime" onClick={continueToAccount} style={{ minHeight: 50, marginTop: '.5rem' }}>Create my private workspace →</button>
          </section>
        )}
      </section>
    </main>
  );
}
