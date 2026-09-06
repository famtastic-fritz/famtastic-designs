import { useEffect } from 'react';
import { Link } from 'react-router';
import { applySeo } from '../components/SEO.jsx';
import { Section, CTABanner, FadeUp, Stagger, Item } from '../components/v1/index.js';
import { WEB_BASICS } from '../lib/webBasicsOffer.js';
import { seoForPath } from '../seo.js';

export default function FiftyFiveCentWebsitePage() {
  useEffect(() => applySeo(seoForPath('/55-cents-a-day-website')), []);

  return (
    <article className="campaign-page">
      <header className="campaign-hero">
        <img className="campaign-hero__image" src="/campaigns/55-cent-website-hero.webp" alt="A small-business owner stepping toward a polished professional website" width="1600" height="900" />
        <div className="campaign-hero__shade" />
        <div className="v1-container campaign-hero__content">
          <img className="campaign-brand-mark" src="/brand/famtastic-mark.svg" alt="FAMtastic Designs" width="52" height="52" />
          <p className="v1-eyebrow">{WEB_BASICS.hero.eyebrow}</p>
          <h1>{WEB_BASICS.hero.title}</h1>
          <p className="campaign-hero__declaration">{WEB_BASICS.hero.declaration}</p>
          <p className="campaign-hero__lede">{WEB_BASICS.hero.lede}</p>
          <div className="v1-hero__actions">
            <Link className="v1-btn v1-btn--primary" to="/start?option=web-basics">Research the $199 starting point</Link>
            <Link className="v1-btn v1-btn--secondary" to="/website-options">Compare it with the $499 option</Link>
          </div>
          <p className="campaign-disclosure">{WEB_BASICS.hero.disclosure}</p>
        </div>
      </header>

      <Section eyebrow="The offer" title="A complete business foundation—not a mystery price.">
        <Stagger className="v1-grid v1-grid--3">
          {WEB_BASICS.facts.map(([title, body]) => <Item key={title} className="v1-card"><h2 className="v1-card__title">{title}</h2><p className="v1-card__text">{body}</p></Item>)}
        </Stagger>
      </Section>

      <Section eyebrow="Website basics" title="Domain, hosting, and website: three different jobs.">
        <div className="campaign-explainer-grid">
          <FadeUp className="campaign-explainer">
            <img src="/campaigns/domain-explained.webp" alt="A business connected through a digital address to its website" width="1600" height="900" loading="lazy" />
            <div><h2>Domain</h2><p>Your domain is the memorable address customers use to reach you online. You own the registered domain. If you need one, first-year registration of an available domain is included; if you already have one, we connect it instead.</p><Link to="/blog/what-is-a-domain-name">Learn how domains work →</Link></div>
          </FadeUp>
          <FadeUp className="campaign-explainer">
            <img src="/campaigns/hosting-explained.webp" alt="Protected managed hosting infrastructure supporting a business website" width="1600" height="900" loading="lazy" />
            <div><h2>Hosting</h2><p>Hosting is the managed infrastructure that keeps your website available online. The first year of basic FAMtastic-managed hosting is included. After that term it currently renews at $9.99 per month unless canceled.</p><Link to="/blog/what-is-website-hosting">Learn how hosting works →</Link></div>
          </FadeUp>
        </div>
      </Section>

      <Section eyebrow="See it in motion" title="Short videos that explain the offer visually.">
        <Stagger className="v1-grid v1-grid--3">
          {WEB_BASICS.videoExamples.map((video) => (
            <Item key={video.slug} className="v1-panel v1-panel--soft">
              <video
                controls
                playsInline
                preload="metadata"
                poster={video.poster}
                style={{ width: '100%', borderRadius: '18px', border: '1px solid var(--v1-border)', background: '#070907' }}
              >
                <source src={`/video/${video.slug}.mp4`} type="video/mp4" />
              </video>
              <p className="v1-eyebrow" style={{ marginTop: '1rem' }}>{video.title}</p>
              <h2 className="v1-card__title">{video.description}</h2>
              <p className="v1-card__text">Watch the full film in the public library, then use the research conversation if the scope needs to grow.</p>
              <Link to={`/watch/${video.slug}`}>Open the film →</Link>
            </Item>
          ))}
        </Stagger>
      </Section>

      <Section eyebrow="What gets built" title="One focused website can still do a complete job.">
        <div className="campaign-anatomy">
          <img src="/campaigns/one-page-anatomy.webp" alt="Connected sections and systems of a one-page business website" width="1600" height="900" loading="lazy" />
          <div className="v1-panel"><p>A focused business page can establish identity, explain the offer, build trust, answer common questions, show contact details, and move a visitor toward one clear action. It is designed for mobile and connected to a real inquiry path—not treated as a digital flyer.</p><Link to="/blog/parts-of-a-one-page-business-website">See the parts of a one-page website →</Link></div>
        </div>
      </Section>

      <Section eyebrow="Fit" title="The right offer for the right job.">
        <div className="v1-grid v1-grid--2">
          <div className="v1-panel"><h2 className="v1-card__title">{WEB_BASICS.title} is a strong fit when…</h2><ul className="v1-dot-list"><li>You need a credible first owned business foundation.</li><li>One focused website can explain the core business and action.</li><li>You need a domain or want to connect one you own.</li><li>You want research and a growth plan before deciding what to build next.</li></ul></div>
          <div className="v1-panel v1-panel--soft"><h2 className="v1-card__title">A broader scope may fit when…</h2><ul className="v1-dot-list"><li>You need ecommerce or a product catalog.</li><li>Several services need separate search pages.</li><li>You need customer accounts, integrations, or automation.</li><li>Your content or workflow cannot fit one focused page.</li></ul><Link to="/start">Let the intake shape the recommendation →</Link></div>
        </div>
      </Section>

      <Section eyebrow="Process" title="From yes to live.">
        <Stagger className="v1-process v1-process--4">
          {WEB_BASICS.steps.map(([title, body], index) => <Item key={title} className="v1-process__step"><span className="v1-process__number">{index + 1}</span><h2 className="v1-process__title">{title}</h2><p className="v1-process__body">{body}</p></Item>)}
        </Stagger>
      </Section>

      <Section eyebrow="Clear terms" title="Know what happens after the included year.">
        <div className="v1-panel"><ul className="v1-dot-list">{WEB_BASICS.terms.map((item) => <li key={item}>{item}</li>)}</ul></div>
      </Section>

      <CTABanner title="A mobile business foundation can start at $199." body="Read the scope, compare the $499 Business Website option when you need more standard pages, and let research confirm the appropriate path." primaryCta={{ label: 'Research the $199 foundation', href: '/start?option=web-basics' }} secondaryCta={{ label: 'Compare website options', href: '/website-options' }} />
    </article>
  );
}
