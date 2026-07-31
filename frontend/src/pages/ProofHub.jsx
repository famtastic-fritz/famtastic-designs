import { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import {
  createProofCampaign,
  getProofCampaign,
  getSession,
  selectProofVariant,
  startCheckout,
} from '../api/pipeline.js';
import PipelineShell from '../components/PipelineShell.jsx';
import { applySeo } from '../components/SEO.jsx';
import { FadeUp, Item, Stagger } from '../components/v1/motion.jsx';
import ProspectLandingPage from './ProspectLandingPage.jsx';
import { proofSeo } from '../seo.js';
import '../pipeline.css';

// Mirror of ProspectLandingPage's paid-status list: prospects who already paid
// never see the proof hub — they fall through to the existing landing flow.
const PAID_STATUSES = ['paid', 'intake_started', 'intake_complete', 'submitted_to_studio', 'proof_ready', 'revision_requested', 'approved', 'launched'];

const GENERATION_STEPS = [
  'Analyzing your business…',
  'Sketching three design directions…',
  'Choosing fonts, colors, and layouts…',
  'Polishing your previews…',
];

// Packages from the Proof Campaign spec. Ids match the shared API contract.
const PACKAGES = [
  {
    id: 'essential_199',
    name: 'Essential Launch',
    price: '$199',
    tagline: 'A sharp one-page site, live fast.',
    features: [
      'One-page website built on your chosen design',
      '5 content sections',
      'Mobile responsive design',
      'SSL security included',
    ],
  },
  {
    id: 'business_499',
    name: 'Business Launch',
    price: '$499',
    tagline: 'Everything you need to start winning customers.',
    highlighted: true,
    badge: 'Most popular',
    features: [
      'Everything in Essential Launch',
      'Contact & lead capture forms',
      'SEO setup',
      'Analytics dashboard',
      'Custom domain connection',
      '2 revision rounds',
    ],
  },
];

// Static proof assets live on the Drupal host (/web/proofs/...), which the
// Vite dev proxy does not forward — so resolve relative paths against the
// Drupal base URL (dev default matches the proxy target in vite.config.js).
const DRUPAL_BASE = (import.meta.env.VITE_DRUPAL_BASE_URL ?? 'http://localhost:8080').replace(/\/+$/, '');

function resolveAssetUrl(path) {
  if (!path) return null;
  if (/^https?:\/\//i.test(path)) return path;
  return `${DRUPAL_BASE}${path.startsWith('/') ? '' : '/'}${path}`;
}

// GET /proof-campaign may nest the campaign or return it flat; both are
// normalized to { ...campaign, variants: [] }.
function normalizeCampaign(body) {
  if (!body || typeof body !== 'object') return null;
  const campaign = body.campaign ?? body;
  if (!campaign || typeof campaign !== 'object' || !campaign.campaign_id) return null;
  const variants = body.variants ?? campaign.variants ?? [];
  return { ...campaign, variants: Array.isArray(variants) ? variants : [] };
}

// expires_at may arrive as a unix timestamp (seconds or ms) or an ISO string.
function parseExpiry(expiresAt) {
  if (expiresAt === null || expiresAt === undefined || expiresAt === '') return null;
  const numeric = Number(expiresAt);
  if (!Number.isNaN(numeric) && String(expiresAt).trim() !== '') {
    return new Date(numeric < 1e12 ? numeric * 1000 : numeric);
  }
  const date = new Date(expiresAt);
  return Number.isNaN(date.getTime()) ? null : date;
}

function formatExpiry(date) {
  return new Intl.DateTimeFormat('en-US', { dateStyle: 'full' }).format(date);
}

export default function ProofHub() {
  const { token } = useParams();
  const navigate = useNavigate();
  // loading → (generating) → active | expired | error | passthrough
  const [phase, setPhase] = useState('loading');
  const [error, setError] = useState(null);
  const [campaign, setCampaign] = useState(null);
  const [genStep, setGenStep] = useState(0);
  const [selectedVariant, setSelectedVariant] = useState(null);
  const [selectedPackage, setSelectedPackage] = useState(null);
  const [notice, setNotice] = useState(null);
  const [confirming, setConfirming] = useState(false);
  const [terms, setTerms] = useState(null);
  const [termsAccepted, setTermsAccepted] = useState(false);

  function applyCampaign(next) {
    setCampaign(next);
    if (next.generation_status === 'waiting_callback' || next.generation_status === 'dispatching') {
      setPhase('generating');
      return;
    }
    if (next.status === 'converted') {
      // Paid via the proof flow — hand off to the existing landing/intake flow.
      setPhase('passthrough');
      return;
    }
    const expiry = parseExpiry(next.expires_at);
    const clientExpired = next.status === 'active' && expiry && expiry.getTime() < Date.now();
    if (next.status === 'expired' || next.status === 'archived' || clientExpired) {
      setPhase('expired');
      return;
    }
    setPhase('active');
  }

  async function generate() {
    setPhase('generating');
    setGenStep(0);
    setError(null);
    try {
      let next = normalizeCampaign(await createProofCampaign(token, {}));
      if (!next || next.variants.length === 0) {
        next = normalizeCampaign(await getProofCampaign(token));
      }
      if (!next) throw new Error('Your design previews could not be loaded.');
      applyCampaign(next);
    } catch (err) {
      setError(err.message);
      setPhase('error');
    }
  }

  const load = useCallback(async () => {
    setPhase('loading');
    setError(null);
    try {
      const session = await getSession(token);
      setTerms(session?.terms ?? null);
      if (PAID_STATUSES.includes(session?.prospect?.status)) {
        setPhase('passthrough');
        return;
      }
      try {
        const existing = normalizeCampaign(await getProofCampaign(token));
        if (existing) {
          applyCampaign(existing);
        } else {
          await generate();
        }
      } catch (err) {
        if (err.status === 404) {
          // First visit: no campaign yet — create one (idempotent on the server).
          await generate();
        } else {
          throw err;
        }
      }
    } catch (err) {
      setError(err.message);
      setPhase('error');
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token]);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    if (campaign) applySeo(proofSeo(campaign, token));
  }, [campaign, token]);

  // Cycle the staged "generating" messages while the server builds variants.
  useEffect(() => {
    if (phase !== 'generating') return undefined;
    const id = setInterval(() => setGenStep((s) => (s + 1) % GENERATION_STEPS.length), 2600);
    return () => clearInterval(id);
  }, [phase]);

  useEffect(() => {
    if (phase !== 'generating' || campaign?.generation_status !== 'waiting_callback') return undefined;
    const id = setInterval(async () => {
      try {
        const next = normalizeCampaign(await getProofCampaign(token));
        if (next?.generation_status === 'ready' && next.variants.length === 3) {
          applyCampaign(next);
        }
      } catch {
        // The main generation state remains visible; the next poll retries.
      }
    }, 3000);
    return () => clearInterval(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [phase, campaign?.generation_status, token]);

  async function handleConfirmSelection() {
    if (!selectedVariant || !selectedPackage || confirming) return;
    setConfirming(true);
    setNotice(null);
    try {
      await selectProofVariant(token, { variant_id: selectedVariant, package: selectedPackage });
      // Hand off to the EXISTING checkout flow, honoring its response shape.
      const res = await startCheckout(token, {
        package: selectedPackage,
        terms_accepted: termsAccepted,
        terms_checksum: terms?.checksum,
      });
      if (res.already_paid) {
        navigate(`/p/${token}/return`);
        return;
      }
      if (res.gateway_mode === 'stub') {
        throw new Error('Secure checkout is temporarily unavailable. Please try again later.');
      }
      if (res.url) {
        window.location.href = res.url;
        return;
      }
      throw new Error('Checkout did not return a payment URL.');
    } catch (err) {
      setNotice({ type: 'error', text: err.message });
      setConfirming(false);
    }
  }

  if (phase === 'passthrough') {
    return <ProspectLandingPage />;
  }

  if (phase === 'loading') {
    return (
      <PipelineShell>
        <div className="fp-card fp-center">
          <div className="fp-spinner" />
          <p className="fp-muted">Loading your design previews…</p>
        </div>
      </PipelineShell>
    );
  }

  if (phase === 'generating') {
    return (
      <PipelineShell>
        <div className="fp-card fp-center fp-generating">
          <div className="fp-spinner" />
          <h2>Generating 3 design directions{campaign?.business_name ? ` for ${campaign.business_name}` : ' for your business'}…</h2>
          <p className="fp-muted fp-generating__step" aria-live="polite">{GENERATION_STEPS[genStep]}</p>
          <div className="fp-generating__bar">
            <div className="fp-generating__bar-fill" />
          </div>
          <p className="fp-fineprint">This usually takes under a minute — please don’t close this page.</p>
        </div>
      </PipelineShell>
    );
  }

  if (phase === 'error') {
    return (
      <PipelineShell>
        <div className="fp-card fp-card--error fp-center">
          <h2>Something went wrong</h2>
          <p className="fp-muted">{error || 'We couldn’t load your design previews.'}</p>
          <div className="fp-actions fp-actions--center">
            <button className="fp-btn fp-btn--lime" onClick={load}>Try again</button>
            <Link className="fp-btn" to="/contact">Contact us</Link>
          </div>
        </div>
      </PipelineShell>
    );
  }

  if (phase === 'expired') {
    return (
      <PipelineShell>
        <div className="fp-card fp-center">
          <span className="fp-eyebrow">Preview expired</span>
          <h2>These design previews have expired</h2>
          <p className="fp-muted">
            Free previews are available for 7 days. We’d still love to build your site —
            reach out and we’ll put together fresh design directions for you.
          </p>
          <div className="fp-actions fp-actions--center">
            <Link className="fp-btn fp-btn--lime" to="/contact">Contact us to restart →</Link>
          </div>
        </div>
      </PipelineShell>
    );
  }

  // phase === 'active'
  const expiry = parseExpiry(campaign?.expires_at);
  const variants = [...(campaign?.variants ?? [])]
    .sort((x, y) => String(x.direction_id).localeCompare(String(y.direction_id)));
  const chosen = variants.find((v) => v.direction_id === selectedVariant);

  return (
    <PipelineShell step={1}>
      <FadeUp className="fp-hero">
        <span className="fp-eyebrow">Your free website preview</span>
        <h1>
          3 design directions for <span className="fp-lime">{campaign?.business_name || 'your business'}</span>
        </h1>
        <p className="fp-muted">
          We built three real homepage previews for you. Open each one, pick your favorite,
          and choose a launch package — we’ll take it from there.
        </p>
        {expiry && (
          <p className="fp-expiry">
            This preview expires on <strong>{formatExpiry(expiry)}</strong>
          </p>
        )}
      </FadeUp>

      {notice && <div className={`fp-notice fp-notice--${notice.type}`}>{notice.text}</div>}

      <Stagger className="fp-variants" stagger={0.12}>
        {variants.map((variant) => {
          const isChosen = selectedVariant === variant.direction_id;
          const thumb = resolveAssetUrl(variant.thumbnail_path);
          return (
            <Item key={variant.direction_id} className={`fp-variant${isChosen ? ' fp-variant--chosen' : ''}`}>
              <div className="fp-variant__thumb">
                {thumb ? (
                  <img src={thumb} alt={`${variant.direction_name} homepage preview`} loading="lazy" />
                ) : (
                  <div className="fp-variant__placeholder" aria-hidden="true">
                    <span className="fp-variant__placeholder-letter">{String(variant.direction_id).toUpperCase()}</span>
                  </div>
                )}
              </div>
              <div className="fp-variant__body">
                <h3>{variant.direction_name}</h3>
                <a
                  className="fp-variant__preview"
                  href={resolveAssetUrl(variant.preview_url)}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  View Full Preview →
                </a>
                <button
                  className={`fp-btn ${isChosen ? 'fp-btn--lime' : ''} fp-variant__choose`}
                  onClick={() => setSelectedVariant(isChosen ? null : variant.direction_id)}
                >
                  {isChosen ? '✓ Selected' : 'Choose This'}
                </button>
              </div>
            </Item>
          );
        })}
      </Stagger>

      {selectedVariant && (
        <FadeUp className="fp-packages" as="section">
          <h2>Now pick your launch package</h2>
          <p className="fp-muted">
            You chose <strong>{chosen?.direction_name || `Direction ${String(selectedVariant).toUpperCase()}`}</strong> —
            both packages include building your site on that design.
          </p>
          <div className="fp-packages__grid">
            {PACKAGES.map((pkg) => {
              const isPicked = selectedPackage === pkg.id;
              return (
                <button
                  key={pkg.id}
                  type="button"
                  className={`fp-package${pkg.highlighted ? ' fp-package--highlight' : ''}${isPicked ? ' fp-package--picked' : ''}`}
                  onClick={() => setSelectedPackage(pkg.id)}
                >
                  {pkg.badge && <span className="fp-package__badge">{pkg.badge}</span>}
                  <span className="fp-package__name">{pkg.name}</span>
                  <span className="fp-package__price">{pkg.price}</span>
                  <span className="fp-package__tagline">{pkg.tagline}</span>
                  <ul className="fp-list fp-package__features">
                    {pkg.features.map((feature) => <li key={feature}>{feature}</li>)}
                  </ul>
                  <span className={`fp-btn fp-package__cta ${isPicked ? 'fp-btn--lime' : ''}`}>
                    {isPicked ? '✓ Selected' : `Select ${pkg.name}`}
                  </span>
                </button>
              );
            })}
          </div>
          <button
            className="fp-btn fp-btn--lime fp-btn--lg"
            disabled={!selectedPackage || !termsAccepted || confirming}
            onClick={handleConfirmSelection}
          >
            {confirming
              ? 'Confirming & starting secure checkout…'
              : selectedPackage
                ? `Confirm Selection — ${PACKAGES.find((p) => p.id === selectedPackage)?.name}`
                : 'Select a package to continue'}
          </button>
          <label className="fp-check">
            <input
              type="checkbox"
              checked={termsAccepted}
              onChange={(event) => setTermsAccepted(event.target.checked)}
            />
            <span>
              I accept Website Service Terms v{terms?.version}. The selected package includes 12 months of hosting;
              recurring hosting requires separate authorization before month 13.
            </span>
          </label>
          <p className="fp-fineprint">Secure payment via Stripe. You’ll be redirected to Stripe Checkout.</p>
        </FadeUp>
      )}

      <FadeUp className="fp-alt">
        <p className="fp-muted">
          None of these feel right? <Link to="/contact" className="fp-lime">Tell us what you’re looking for →</Link>
        </p>
      </FadeUp>
    </PipelineShell>
  );
}
