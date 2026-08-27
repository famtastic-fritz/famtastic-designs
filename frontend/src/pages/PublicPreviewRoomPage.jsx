import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router';
import '../proof-share.css';
import '../public-preview-room.css';

const WEB_PREFIX = import.meta.env.DEV ? '' : '/web';
const DIRECTION_COPY = [
  'A clarity-first route that makes the next action easy to find.',
  'A more expressive route that puts identity and exploration forward.',
  'A higher-impact route built to make the experience more memorable.',
];

export default function PublicPreviewRoomPage() {
  const { previewDelivery = '', signature = '' } = useParams();
  const [state, setState] = useState({ status: 'loading', room: null });

  useEffect(() => {
    const controller = new AbortController();
    fetch(`${WEB_PREFIX}/api/public-preview/${encodeURIComponent(previewDelivery)}/${encodeURIComponent(signature)}`, {
      credentials: 'omit', headers: { Accept: 'application/json' }, signal: controller.signal,
    }).then(async (response) => {
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.preview_delivery) throw new Error('not_found');
      setState({ status: 'ready', room: payload.preview_delivery });
    }).catch((error) => {
      if (error.name !== 'AbortError') setState({ status: 'error', room: null });
    });
    return () => controller.abort();
  }, [previewDelivery, signature]);

  if (state.status === 'loading') return <main className="proof-share-state"><i aria-hidden="true" /><p>Opening your private concept room…</p></main>;
  if (state.status === 'error') return <main className="proof-share-state proof-share-state--error"><Link className="proof-share-logo" to="/">FAM<span>tastic</span></Link><h1>This concept room is unavailable.</h1><p>It may have expired, been replaced, or not yet been approved for review. Ask FAMtastic Designs for the current link.</p><Link className="proof-share-button" to="/">Visit FAMtastic Designs</Link></main>;

  const room = state.room;
  const proofCount = Number(room.proof_count || room.variants?.length || 0);
  return <main className="proof-share-page public-preview-room">
    <header><Link className="proof-share-logo" to="/">FAM<span>tastic</span></Link><div><span>Private concept room</span><b>Review-only</b></div></header>
    <section className="proof-share-hero">
      <span>{room.private_label}</span>
      <h1>{proofCount} exploratory direction{proofCount === 1 ? '' : 's'} for {room.business_name}</h1>
      <p>{room.public_context || 'Built only from the general information we could responsibly use—not a final site scope. Your guidance turns the strongest direction into a tailored build.'}</p>
      <aside><b>What this room is for</b><span>Compare real working concepts</span><span>See {proofCount} distinct creative path{proofCount === 1 ? '' : 's'}</span><em>No selection, price, checkout, or publishing happens here</em></aside>
    </section>
    <section className="public-preview-context" aria-label="How to use these concepts">
      <span>01 · What we used</span><p>General details and safe public context only. We intentionally left unconfirmed facts, services, schedules, outcomes, partners, availability, and offers out of these early concepts.</p>
      {room.research_teaser && <><span>02 · Research note</span><p>{room.research_teaser}</p></>}
      <span>{room.research_teaser ? '03' : '02'} · What changes the next round</span><p>Your current services, audiences, pages, assets, integrations, references, accessibility needs, and the action you want visitors to take.</p>
    </section>
    <section className="proof-share-grid" aria-label={`${proofCount} website concept${proofCount === 1 ? '' : 's'}`}>
      {room.variants.map((proof, index) => {
        return <article key={proof.direction_id} style={{ '--proof-index': index }}><span>Direction {String(index + 1).padStart(2, '0')}</span><b>{String(index + 1).padStart(2, '0')}</b><h2>{proof.direction_name}</h2><p>{DIRECTION_COPY[index] || DIRECTION_COPY[0]}</p><small><strong>Next round:</strong> Your confirmed priorities turn this early concept into a tailored build.</small><a href={proof.preview_url} target="_blank" rel="noreferrer">Open working concept <span aria-hidden="true">↗</span></a></article>;
      })}
    </section>
    <section className="proof-share-cta">
      <div><span>{room.research_teaser ? '04' : '03'} · Make a direction yours</span><h2>Give the studio the details that make the next version unmistakably yours.</h2><p>Create a free FAMtastic Designs workspace with the same email to save this work, verify what is current, add the pages and assets that matter, and request a better-informed refinement.</p></div>
      <a href={room.registration_url}>Create your free workspace</a>
    </section>
    <footer><p>Private, revocable concept review · Nothing here is published or purchased.</p><Link to="/">famtasticdesigns.com</Link></footer>
  </main>;
}
