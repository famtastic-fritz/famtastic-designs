import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router';
import '../proof-share.css';
import '../public-preview-room.css';

const WEB_PREFIX = import.meta.env.DEV ? '' : '/web';
const DIRECTION_COPY = {
  a: { label: 'Safe', best: 'Best when clarity, credibility, and quick family or partner confidence matter most.', needs: 'Current programs, audiences, approved facts, and the clearest next step.' },
  b: { label: 'Medium FAMtastic', best: 'Best when the experience needs energy, identity, and a stronger reason to explore.', needs: 'The real creative lanes, priority pages, references, and the voice that feels like you.' },
  c: { label: 'Ultra FAMtastic', best: 'Best when the site should feel like a flagship and make the organization hard to forget.', needs: 'Brand assets, consent-cleared stories, accessibility requirements, and the ambition for the build.' },
};

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
  return <main className="proof-share-page public-preview-room">
    <header><Link className="proof-share-logo" to="/">FAM<span>tastic</span></Link><div><span>Private concept room</span><b>Review-only</b></div></header>
    <section className="proof-share-hero">
      <span>{room.private_label}</span>
      <h1>Three exploratory directions for {room.business_name}</h1>
      <p>Built from the general information we could responsibly use—not a final site scope. Your guidance turns the strongest direction into a tailored build.</p>
      <aside><b>What this room is for</b><span>Compare real working concepts</span><span>See three different creative paths</span><em>No selection, price, checkout, or publishing happens here</em></aside>
    </section>
    <section className="public-preview-context" aria-label="How to use these concepts">
      <span>01 · What we used</span><p>General details from the first request and safe public context. We intentionally left unconfirmed facts, schedules, outcomes, partners, and offers out of these early concepts.</p>
      <span>02 · What changes the next round</span><p>Your current services or programs, audiences, pages, assets, integrations, references, and the action you want visitors to take.</p>
    </section>
    <section className="proof-share-grid" aria-label="Three website concepts">
      {room.variants.map((proof, index) => {
        const detail = DIRECTION_COPY[proof.direction_id] || DIRECTION_COPY.a;
        return <article key={proof.direction_id} style={{ '--proof-index': index }}><span>{detail.label} direction</span><b>{String(index + 1).padStart(2, '0')}</b><h2>{proof.direction_name}</h2><p>{detail.best}</p><small><strong>Needs from you:</strong> {detail.needs}</small><a href={proof.preview_url} target="_blank" rel="noreferrer">Open working concept <span aria-hidden="true">↗</span></a></article>;
      })}
    </section>
    <section className="proof-share-cta">
      <div><span>03 · Make a direction yours</span><h2>Give the studio the details that let it really wow you.</h2><p>Create a free FAMtastic Designs workspace with the same email to save this work, verify what is current, add the pages and assets that matter, and receive up to six better-informed refined directions.</p></div>
      <a href={room.registration_url}>Create your free workspace</a>
    </section>
    <footer><p>Private, revocable concept review · Nothing here is published or purchased.</p><Link to="/">famtasticdesigns.com</Link></footer>
  </main>;
}
