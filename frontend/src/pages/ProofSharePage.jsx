import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router';
import '../proof-share.css';

const WEB_PREFIX = import.meta.env.DEV ? '' : '/web';

export default function ProofSharePage() {
  const { requestId = '', signature = '' } = useParams();
  const [state, setState] = useState({ status: 'loading', share: null });

  useEffect(() => {
    const controller = new AbortController();
    fetch(`${WEB_PREFIX}/api/proof-shares/${encodeURIComponent(requestId)}/${encodeURIComponent(signature)}`, {
      credentials: 'omit', headers: { Accept: 'application/json' }, signal: controller.signal,
    }).then(async (response) => {
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.proof_share) throw new Error('not_found');
      setState({ status: 'ready', share: payload.proof_share });
    }).catch((error) => {
      if (error.name !== 'AbortError') setState({ status: 'error', share: null });
    });
    return () => controller.abort();
  }, [requestId, signature]);

  if (state.status === 'loading') return <main className="proof-share-state"><i aria-hidden="true" /><p>Opening the unlisted proof room…</p></main>;
  if (state.status === 'error') return <main className="proof-share-state proof-share-state--error"><Link className="proof-share-logo" to="/">FAM<span>tastic</span></Link><h1>This proof link is unavailable.</h1><p>It may have been turned off or replaced with a new link. Ask the person who shared it for the current one.</p><Link className="proof-share-button" to="/">Visit FAMtastic Designs</Link></main>;

  const share = state.share;
  return <main className="proof-share-page">
    <header><Link className="proof-share-logo" to="/">FAM<span>tastic</span></Link><div><span>Unlisted concept room</span><b>View-only access</b></div></header>
    <section className="proof-share-hero"><span>Website concept review</span><h1>{share.business_name}</h1><p>{share.proof_count} working directions are ready to compare. Open every concept on desktop and mobile before discussing which visual direction feels strongest.</p><aside><b>What this link allows</b><span>View concepts</span><span>Share feedback outside the portal</span><em>No account, pricing, selection, or revision access</em></aside></section>
    <section className="proof-share-grid" aria-label={`${share.proof_count} website concepts`}>
      {share.variants.map((proof, index) => <article key={proof.direction_id} style={{ '--proof-index': index }}><span>Direction {String(index + 1).padStart(2, '0')}</span><b>{proof.direction_id.toUpperCase()}</b><h2>{proof.direction_name}</h2><p>Open the complete responsive webpage in a new tab.</p><a href={proof.preview_url} target="_blank" rel="noreferrer">Open working concept <span aria-hidden="true">↗</span></a></article>)}
    </section>
    <section className="proof-share-cta"><div><span>Built by an AI solutions studio</span><h2>Want a proof set built around your business?</h2><p>Tell FAMtastic what you sell, who you serve, and how bold the design should be. The studio turns that brief into distinct working directions.</p></div><Link to="/start">Start a website request</Link></section>
    <footer><p>This is an unlisted client concept review. Access can be revoked at any time.</p><Link to="/">famtasticdesigns.com</Link></footer>
  </main>;
}
