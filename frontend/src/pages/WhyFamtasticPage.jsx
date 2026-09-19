import { useEffect } from 'react';
import { Link } from 'react-router';
import Hero from '../components/v1/Hero.jsx';
import { Section } from '../components/v1/Section.jsx';
import CTABanner from '../components/v1/CTABanner.jsx';
import { applySeo } from '../components/SEO.jsx';
import { seoForPath } from '../seo.js';
import { WEB_BASICS } from '../lib/webBasicsOffer.js';
import narration from '../../../marketing/brands/famtastic/video-studio/whats-the-catch/user-script.txt?raw';
import './why-famtastic-page.css';

const FILM = {
  video: '/media/films/whats-the-catch-20260919.mp4',
  poster: '/media/films/whats-the-catch-20260919.jpg',
  captions: '/media/films/whats-the-catch-20260919.vtt',
};

const transcriptLines = narration.trim().split(/\r?\n/).filter(Boolean);

export default function WhyFamtasticPage() {
  useEffect(() => applySeo(seoForPath('/why-famtastic')), []);

  return (
    <article className="why-famtastic-page">
      <Hero
        eyebrow="The belief behind the $199 foundation"
        title="You grow. We grow."
        lede="See why we believe in your vision, invest in your first steps, and grow alongside your business."
        primaryCta={{ href: '/start?option=web-basics', label: 'Explore the $199 foundation' }}
      >
        <p className="why-famtastic-page__hero-note">
          Want the complete comparison first? <Link to="/55-cents-a-day-website">Read the $199 offer details</Link>.
        </p>
      </Hero>

      <section className="why-famtastic-page__film" aria-label="What’s the Catch? film">
        <div className="v1-container">
          <figure className="why-famtastic-page__film-frame">
            <video
              className="why-famtastic-page__video"
              controls
              playsInline
              preload="metadata"
              poster={FILM.poster}
              aria-label="What’s the Catch? — a FAMtastic Designs film"
              aria-describedby="why-famtastic-film-caption"
            >
              <source src={FILM.video} type="video/mp4" />
              <track kind="captions" src={FILM.captions} srcLang="en-US" label="English captions" />
              Your browser cannot play this film. <a href={FILM.video}>Open the MP4 video</a> or read the complete transcript below.
            </video>
            <figcaption id="why-famtastic-film-caption">
              “What’s the Catch?” · A FAMtastic Designs film with captions. Read the complete transcript below.
            </figcaption>
          </figure>
        </div>
      </section>

      <Section
        eyebrow="The offer, in plain language"
        title="Everything included, clearly defined."
        intro="The film explains why we invest in a business’s first steps. Here are the essentials and the costs that stay separate."
        className="why-famtastic-page__offer"
      >
        <div className="why-famtastic-page__offer-grid">
          <div className="why-famtastic-page__price-panel">
            <p className="v1-eyebrow">{WEB_BASICS.title}</p>
            <p className="why-famtastic-page__price">{WEB_BASICS.priceLabel}<span> one time</span></p>
            <p className="why-famtastic-page__price-note">We begin with research to confirm the right fit for your business.</p>
            <Link className="v1-btn v1-btn--primary why-famtastic-page__offer-cta" to="/start?option=web-basics">Start with research</Link>
          </div>

          <div className="why-famtastic-page__scope">
            <h3>Included in the $199 foundation</h3>
            <ul>
              <li>One responsive, focused page built around your business content, brand, and one researched customer action.</li>
              <li>One owner-approved research snapshot and a customer-safe 90-day growth plan.</li>
              <li>Twelve months of basic managed hosting and SSL.</li>
              <li>One standard available new domain for its first year when needed, or connection of a domain you already control. Any new-domain renewal is separate, with the registrar price disclosed before payment.</li>
              <li>Baseline analytics, customer-approved social setup, and email forwarding when the provider path supports it. Mailboxes are separate subscriptions.</li>
              <li>Optional display of a customer-owned payment link or QR; FAMtastic does not process those customer payments.</li>
            </ul>
            <p className="why-famtastic-page__scope-detail">Proof selection, the included direction reset, edit rounds, and the verified-purchase Owner Desk are spelled out on the <Link to="/55-cents-a-day-website">complete $199 offer page</Link>.</p>
          </div>
        </div>
      </Section>

      <Section
        eyebrow="No surprises in year two"
        title="Hosting and domain renewals stay separate."
        className="why-famtastic-page__renewal"
      >
        <div className="why-famtastic-page__renewal-grid">
          <article className="why-famtastic-page__renewal-card">
            <h3>Managed hosting</h3>
            <p>The first 12 months of basic managed hosting are included. After that, hosting is $9.99 per month only if you give separate recurring-payment authorization.</p>
          </article>
          <article className="why-famtastic-page__renewal-card">
            <h3>Domain registration</h3>
            <p>If you choose a new domain, its annual renewal is a separate prepaid registrar charge. The actual price is disclosed before payment. Connecting a domain you already own does not create a FAMtastic domain-renewal charge.</p>
          </article>
        </div>
        <p className="why-famtastic-page__separate-note">
          Extra pages, ecommerce, custom applications, live calendar synchronization, payment processing, paid advertising, ongoing copywriting, maintenance, advanced analytics, and third-party subscriptions are outside this starter scope unless separately listed and priced. The research plan and website do not guarantee traffic, rankings, leads, bookings, sales, or business results.
        </p>
        <p className="why-famtastic-page__offer-link"><Link to="/55-cents-a-day-website">See the full offer, process, and comparison →</Link></p>
      </Section>

      <Section
        eyebrow="Read along"
        title="Complete film transcript"
        intro="This is the narration as supplied for the film. Captions are also available in the video player."
        className="why-famtastic-page__transcript-section"
      >
        <div className="why-famtastic-page__transcript v1-prose" lang="en-US">
          {transcriptLines.map((line, index) => <p key={`${index}-${line}`}>{line}</p>)}
        </div>
      </Section>

      <CTABanner
        title="Build your next step."
        body={`Explore the ${WEB_BASICS.priceLabel} ${WEB_BASICS.title} scope, then use research to confirm whether it fits your business.`}
        primaryCta={{ label: 'Research the $199 foundation', href: '/start?option=web-basics' }}
        secondaryCta={{ label: 'Read all offer details', href: '/55-cents-a-day-website' }}
      />
    </article>
  );
}
