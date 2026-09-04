import { Section, FadeUp } from '../components/v1/index.js';

const CONTACT_EMAIL = 'hello@famtasticdesigns.com';
const LAST_UPDATED = 'September 4, 2026';

/**
 * /terms-of-service — static legal page. No Drupal fetch; content lives here
 * so it always resolves even if the CMS or API is unreachable.
 */
export default function TermsOfServicePage() {
  return (
    <Section className="v1-section--flush-top" id="terms-of-service">
      <div style={{ paddingTop: '3rem', paddingBottom: '4rem', maxWidth: '760px', margin: '0 auto' }}>
        <FadeUp>
          <p className="v1-eyebrow">Terms of Service</p>
          <h1 className="v1-hero__title" style={{ fontSize: 'clamp(1.9rem, 4vw, 3rem)' }}>
            Terms for working with FAMtastic Designs
          </h1>
          <p className="v1-hero__lede">Last updated: {LAST_UPDATED}</p>

          <div className="v1-card" style={{ marginTop: '2rem' }}>
            <h2 className="v1-card__title">Who these terms cover</h2>
            <p className="v1-card__text">
              These terms apply whenever you use famtasticdesigns.com, our customer portal, or purchase a
              package, service, or add-on from FAMtastic Designs ("we," "us"). By submitting an intake,
              creating an account, or completing a purchase, you agree to these terms.
            </p>
          </div>

          <div className="v1-card" style={{ marginTop: '1.5rem' }}>
            <h2 className="v1-card__title">Our offers and pricing</h2>
            <ul className="v1-dot-list">
              <li>Published packages (such as our $199 Web Basics launch) describe scope, pricing, and what's included on the relevant package page at time of purchase.</li>
              <li>Work outside a published package's scope — ecommerce, custom integrations, regulated industries, or more than the listed number of pages — is scoped and priced separately before any commitment.</li>
              <li>Any private offer, discount, or grant code we issue is tied to your specific account and request; it is not transferable or resellable.</li>
              <li>Prices, renewal terms, and included services are as stated at checkout for your order and remain in effect for the term described there.</li>
            </ul>
          </div>

          <div className="v1-card" style={{ marginTop: '1.5rem' }}>
            <h2 className="v1-card__title">Payments and renewals</h2>
            <ul className="v1-dot-list">
              <li>Payments are processed securely by Stripe. We do not store your full card details.</li>
              <li>Recurring services (such as ongoing hosting after year one, or a maintenance plan) renew automatically at the rate disclosed at signup unless you cancel before the renewal date.</li>
              <li>Refunds are handled case by case — contact us and we'll work it out directly rather than pointing you to fine print.</li>
            </ul>
          </div>

          <div className="v1-card" style={{ marginTop: '1.5rem' }}>
            <h2 className="v1-card__title">What we deliver, and what we need from you</h2>
            <ul className="v1-dot-list">
              <li>We deliver the scope described in your package, proof, or a signed-off private offer — not open-ended, unscoped work.</li>
              <li>You're responsible for the accuracy of the content, brand assets, and account access you provide us, and for having the rights to any material you ask us to use.</li>
              <li>Once a design direction, domain, or deliverable is approved, further changes outside the original scope may be quoted as additional work.</li>
            </ul>
          </div>

          <div className="v1-card" style={{ marginTop: '1.5rem' }}>
            <h2 className="v1-card__title">Acceptable use</h2>
            <p className="v1-card__text">
              You agree not to use our site, portal, or services to submit unlawful content, attempt to
              access another customer's account or data, or interfere with the normal operation of our
              systems. We may suspend access for accounts that violate this.
            </p>
          </div>

          <div className="v1-card" style={{ marginTop: '1.5rem' }}>
            <h2 className="v1-card__title">AI-assisted work</h2>
            <p className="v1-card__text">
              Some of our design, drafting, and support workflows use AI tools alongside our own staff
              review. Every customer-facing claim, price, and deliverable is reviewed by a person before
              it reaches you; an AI assistant may draft or explain, but it does not have final authority
              over your billing, entitlements, or what gets published or deployed to your project.
            </p>
          </div>

          <div className="v1-card" style={{ marginTop: '1.5rem' }}>
            <h2 className="v1-card__title">Limitation of liability</h2>
            <p className="v1-card__text">
              Our services are provided on an "as available" basis. To the extent permitted by law, our
              liability for any claim arising from your use of our site or services is limited to the
              amount you actually paid us for the service giving rise to the claim.
            </p>
          </div>

          <div className="v1-card" style={{ marginTop: '1.5rem' }}>
            <h2 className="v1-card__title">Changes to these terms</h2>
            <p className="v1-card__text">
              We may update these terms as our services evolve. Material changes will be reflected by the
              "last updated" date above; continued use of our services after an update means you accept
              the revised terms.
            </p>
          </div>

          <div className="v1-card" style={{ marginTop: '1.5rem' }}>
            <h2 className="v1-card__title">Contact us</h2>
            <p className="v1-card__text">
              Questions about these terms can be sent to <a href={`mailto:${CONTACT_EMAIL}`}>{CONTACT_EMAIL}</a>.
              See also our <a href="/privacy-policy">Privacy Policy</a>.
            </p>
          </div>
        </FadeUp>
      </div>
    </Section>
  );
}
