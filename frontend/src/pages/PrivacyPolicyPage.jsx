import { Section, FadeUp } from '../components/v1/index.js';

const CONTACT_EMAIL = 'hello@famtasticdesigns.com';
const LAST_UPDATED = 'September 4, 2026';

/**
 * /privacy-policy — static legal page. No Drupal fetch; content lives here
 * so it always resolves even if the CMS or API is unreachable.
 */
export default function PrivacyPolicyPage() {
  return (
    <Section className="v1-section--flush-top" id="privacy-policy">
      <div style={{ paddingTop: '3rem', paddingBottom: '4rem', maxWidth: '760px', margin: '0 auto' }}>
        <FadeUp>
          <p className="v1-eyebrow">Privacy Policy</p>
          <h1 className="v1-hero__title" style={{ fontSize: 'clamp(1.9rem, 4vw, 3rem)' }}>
            How FAMtastic Designs handles your information
          </h1>
          <p className="v1-hero__lede">Last updated: {LAST_UPDATED}</p>

          <div className="v1-card" style={{ marginTop: '2rem' }}>
            <h2 className="v1-card__title">What we collect</h2>
            <ul className="v1-dot-list">
              <li>
                <strong>Contact and intake details</strong> — name, email, and the answers you give us
                through our contact form, discovery intake, or Solution Finder (goals, pages, brand
                references, integrations, and similar project details).
              </li>
              <li>
                <strong>Account and portal data</strong> — your login email and the projects, requests,
                proofs, and support messages tied to your account once you have an active project with us.
              </li>
              <li>
                <strong>Payment information</strong> — processed directly by Stripe. We never see or store
                your full card number; our systems keep only order, entitlement, and receipt records.
              </li>
              <li>
                <strong>Usage and attribution data</strong> — aggregate analytics through Google Analytics
                4, and campaign attribution data (such as UTM parameters or ad click identifiers) captured
                when you arrive from a specific link, email, or social post.
              </li>
              <li>
                <strong>Cookies</strong> — used for session/login state and for Google Analytics. We do not
                use cookies to sell your data or build cross-site advertising profiles.
              </li>
            </ul>
          </div>

          <div className="v1-card" style={{ marginTop: '1.5rem' }}>
            <h2 className="v1-card__title">How we use it</h2>
            <ul className="v1-dot-list">
              <li>To respond to your inquiry and scope, build, and deliver the project you asked for.</li>
              <li>To operate your account, portal, entitlements, renewals, and support history.</li>
              <li>To process payments and issue receipts through Stripe and Drupal Commerce.</li>
              <li>
                To understand which pages, campaigns, and channels are useful to visitors, using aggregate
                analytics — never to identify an individual visitor for advertising purposes.
              </li>
              <li>
                To send you service-related communications (project updates, receipts, support replies).
                Marketing communications are opt-in and every send includes a way to stop receiving them.
              </li>
            </ul>
          </div>

          <div className="v1-card" style={{ marginTop: '1.5rem' }}>
            <h2 className="v1-card__title">AI features (Shay)</h2>
            <p className="v1-card__text">
              Our portal and support tooling may use an AI assistant to explain, summarize, draft, and
              route requests. The assistant can prepare a draft reply or recommendation, but it does not
              autonomously change your billing, entitlements, or deployment, and it does not send a message
              on our behalf without a human reviewing it first.
            </p>
          </div>

          <div className="v1-card" style={{ marginTop: '1.5rem' }}>
            <h2 className="v1-card__title">Who we share information with</h2>
            <ul className="v1-dot-list">
              <li>
                Service providers who process data on our behalf under their own privacy and security
                terms — for example Stripe (payments), Google (analytics), and our hosting providers.
              </li>
              <li>We do not sell your personal information to third parties.</li>
              <li>
                We do not post your private project details, messages, or files to any public or social
                channel. Content we publish publicly (case studies, testimonials) is only ever published
                with your knowledge and, where it identifies you or your business, your consent.
              </li>
            </ul>
          </div>

          <div className="v1-card" style={{ marginTop: '1.5rem' }}>
            <h2 className="v1-card__title">Your choices</h2>
            <ul className="v1-dot-list">
              <li>You can ask us what information we hold about you, and ask us to correct or delete it.</li>
              <li>You can opt out of marketing email at any time using the unsubscribe link, or by contacting us.</li>
              <li>You can close your account and request deletion of associated data, subject to records we are legally required to keep (such as payment/tax records).</li>
            </ul>
          </div>

          <div className="v1-card" style={{ marginTop: '1.5rem' }}>
            <h2 className="v1-card__title">Contact us</h2>
            <p className="v1-card__text">
              Questions about this policy or your data can be sent to{' '}
              <a href={`mailto:${CONTACT_EMAIL}`}>{CONTACT_EMAIL}</a>.
            </p>
          </div>
        </FadeUp>
      </div>
    </Section>
  );
}
