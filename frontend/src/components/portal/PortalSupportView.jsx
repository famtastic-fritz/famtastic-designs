import { Panel } from './PortalShared.jsx';

export default function PortalSupportView({ workspace, go }) {
  const openThreadsCount = (workspace.threads || []).filter(
    (t) => t.status === 'open'
  ).length;

  return (
    <>
      <section className="portal-support-choices">
        <button type="button" onClick={() => go('messages')}>
          <b>Website or service issue</b>
          <span>Report something incorrect, unavailable, or broken.</span>
        </button>
        <button type="button" onClick={() => go('messages')}>
          <b>Request a change</b>
          <span>Update content, hours, services, or business information.</span>
        </button>
        <button type="button" onClick={() => go('faq')}>
          <b>Find an answer</b>
          <span>Search FAQs before waiting for a response.</span>
        </button>
        <a href="mailto:hello@famtasticdesigns.com?subject=Urgent%20customer%20support">
          <b>Urgent business impact</b>
          <span>Email the team when your website, checkout, domain, or leads are unavailable.</span>
        </a>
      </section>

      <Panel
        eyebrow="Support record"
        title={`${openThreadsCount} open request${openThreadsCount === 1 ? '' : 's'}`}
      >
        <p>Your requests and replies stay attached to your customer workspace.</p>
        <button type="button" onClick={() => go('messages')}>
          View conversations
        </button>
      </Panel>
    </>
  );
}
