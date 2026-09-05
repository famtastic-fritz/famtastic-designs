import { Panel } from './PortalShared.jsx';
import PaymentHandoffSettings from './PaymentHandoffSettings.jsx';

export default function PortalGrowthView({ workspace, go, organization }) {
  const offers = workspace.offers || [];

  return (
    <section className="portal-grid three">
      {offers.map((offer) => (
        <Panel
          key={offer.key || offer.title}
          eyebrow="Recommended next step"
          title={offer.title}
          className="offer"
        >
          <p>{offer.description}</p>
          <small>Recommended from your current services and project stage.</small>
          <button type="button" onClick={() => go('services')}>
            Explore options →
          </button>
        </Panel>
      ))}
      <Panel
        eyebrow="Not sure what you need?"
        title="Have FAMtastic handle it"
        className="lime"
      >
        <p>Tell us the outcome you want. We’ll recommend the smallest useful next step.</p>
        <button type="button" onClick={() => go('support')}>
          Describe my goal
        </button>
      </Panel>
      <PaymentHandoffSettings organization={organization} />
    </section>
  );
}
