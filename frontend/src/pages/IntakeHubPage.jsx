import { useState } from 'react';
import { Link } from 'react-router';
import { INTAKE_CONFIGS } from './SpecializedIntakePage.jsx';
import SEO from '../components/SEO.jsx';
import '../portal.css';

export default function IntakeHubPage() {
  const [copiedSlug, setCopiedSlug] = useState('');

  const copyLink = (slug) => {
    const url = `${window.location.origin}/intake/${slug}`;
    navigator.clipboard.writeText(url).then(() => {
      setCopiedSlug(slug);
      setTimeout(() => setCopiedSlug(''), 2500);
    });
  };

  const services = Object.values(INTAKE_CONFIGS);

  return (
    <main className="portal-main" style={{ maxWidth: '1040px', margin: '0 auto', padding: '2.5rem 1.25rem 5rem' }}>
      <SEO
        title="Direct Project Intake Forms | FAMtastic Solutions Studio"
        description="Shareable, specialized project intake forms for Hosting & Domain Setup, AI Chatbots, Custom Client Portals, Website Maintenance, and New Website Launches."
      />

      <header style={{ marginBottom: '2.5rem', textAlign: 'center' }}>
        <span style={{ color: '#7cfc00', fontSize: '0.8rem', fontWeight: '800', textTransform: 'uppercase', letterSpacing: '0.12em' }}>
          ⚡ Dedicated Solution Workflows
        </span>
        <h1 style={{ margin: '0.5rem 0 0.8rem', fontSize: 'clamp(2rem, 3.5vw, 2.75rem)' }}>
          Direct Project Intake Forms
        </h1>
        <p style={{ maxWidth: '640px', margin: '0 auto', color: '#a0aaa0', fontSize: '1.05rem', lineHeight: '1.5' }}>
          Choose a tailored intake workflow below. Copy the direct link to text or email a client, or start filling out the specifications directly.
        </p>
      </header>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: '1.25rem' }}>
        {services.map((svc) => (
          <article
            key={svc.slug}
            style={{
              display: 'flex',
              flexDirection: 'column',
              padding: '1.5rem',
              borderRadius: '16px',
              border: '1px solid rgba(255,255,255,0.09)',
              background: 'linear-gradient(145deg, #101410, #080a08)',
              position: 'relative',
            }}
          >
            <span style={{ color: '#7cfc00', fontSize: '0.75rem', fontWeight: '800', textTransform: 'uppercase', letterSpacing: '0.08em', marginBottom: '0.5rem' }}>
              {svc.badge}
            </span>
            <h2 style={{ margin: '0 0 0.5rem', fontSize: '1.3rem', color: '#fff' }}>
              {svc.title.replace(' Intake', '')}
            </h2>
            <p style={{ margin: '0 0 1.25rem', color: '#9da79d', fontSize: '0.88rem', lineHeight: '1.45', flexGrow: 1 }}>
              {svc.subtitle}
            </p>

            <div style={{ padding: '0.75rem', marginBottom: '1.25rem', borderRadius: '10px', background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.06)' }}>
              <small style={{ display: 'block', color: '#8e988e', fontSize: '0.72rem', textTransform: 'uppercase' }}>Included Package Reference</small>
              <strong style={{ color: '#7cfc00', fontSize: '0.92rem' }}>{svc.packageTitle} · ${svc.price}</strong>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr auto', gap: '0.5rem' }}>
              <Link
                to={`/intake/${svc.slug}`}
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  padding: '0.65rem 1rem',
                  borderRadius: '9px',
                  background: '#7cfc00',
                  color: '#000',
                  fontWeight: '800',
                  fontSize: '0.88rem',
                  textDecoration: 'none',
                  textAlign: 'center',
                }}
              >
                Open Form →
              </Link>
              <button
                type="button"
                onClick={() => copyLink(svc.slug)}
                title="Copy direct shareable link"
                style={{
                  padding: '0.65rem 0.85rem',
                  borderRadius: '9px',
                  border: copiedSlug === svc.slug ? '1px solid #7cfc00' : '1px solid #465046',
                  background: copiedSlug === svc.slug ? 'rgba(124,252,0,0.12)' : 'transparent',
                  color: copiedSlug === svc.slug ? '#7cfc00' : '#fff',
                  cursor: 'pointer',
                  fontWeight: '700',
                  fontSize: '0.82rem',
                  whiteSpace: 'nowrap',
                }}
              >
                {copiedSlug === svc.slug ? 'Copied ✓' : 'Copy Link 🔗'}
              </button>
            </div>
          </article>
        ))}
      </div>

      <section style={{ marginTop: '3.5rem', padding: '2rem', borderRadius: '18px', border: '1px solid rgba(124,252,0,0.15)', background: 'linear-gradient(135deg, rgba(124,252,0,0.04), #080a08)', textAlign: 'center' }}>
        <h3 style={{ margin: '0 0 0.5rem', fontSize: '1.4rem' }}>Need an Interactive Multi-Question Brief?</h3>
        <p style={{ maxWidth: '560px', margin: '0 auto 1.25rem', color: '#9da79d', fontSize: '0.92rem', lineHeight: '1.5' }}>
          Explore our dynamic Solution Finder on the homepage to explore custom architecture specifications and package calculations in real time.
        </p>
        <Link to="/" style={{ display: 'inline-flex', alignItems: 'center', padding: '0.7rem 1.5rem', borderRadius: '9px', border: '1px solid #7cfc00', color: '#7cfc00', textDecoration: 'none', fontWeight: '800', fontSize: '0.9rem' }}>
          Go to Solution Finder →
        </Link>
      </section>
    </main>
  );
}
