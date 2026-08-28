import { Panel } from './PortalShared.jsx';

export default function PortalSettingsView({
  workspace,
  onSaveSettings,
  busy,
}) {
  const preferences = workspace.preferences || {};
  const topics = workspace.topics || {};

  return (
    <Panel
      eyebrow="Settings"
      title="Notifications, Education, and Promotions"
    >
      <form onSubmit={onSaveSettings} className="portal-settings">
        <fieldset>
          <legend>Essential account messages</legend>
          <label className="portal-check">
            <input
              type="checkbox"
              name="project_email"
              value="1"
              defaultChecked={preferences.project_email}
            />
            Project updates and approvals by email
          </label>
          <label className="portal-check">
            <input
              type="checkbox"
              name="support_email"
              value="1"
              defaultChecked={preferences.support_email}
            />
            Support replies by email
          </label>
          <label className="portal-check">
            <input
              type="checkbox"
              name="billing_email"
              value="1"
              defaultChecked={preferences.billing_email}
            />
            Billing and renewal notices by email
          </label>
          <small>
            Critical security and account notices may still be sent when required to operate your
            services.
          </small>
        </fieldset>

        <fieldset>
          <legend>Education and offers</legend>
          <label className="portal-check">
            <input
              type="checkbox"
              name="product_education"
              value="1"
              defaultChecked={preferences.product_education}
            />
            Helpful product education
          </label>
          <label className="portal-check">
            <input
              type="checkbox"
              name="deals_promotions"
              value="1"
              defaultChecked={preferences.deals_promotions}
            />
            Deals, promotions, and relevant service offers
          </label>
          <label>
            Analytics summary
            <select
              name="analytics_digest"
              defaultValue={preferences.analytics_digest || 'off'}
            >
              <option value="off">Off</option>
              <option value="weekly">Weekly</option>
              <option value="monthly">Monthly</option>
            </select>
          </label>
        </fieldset>

        <fieldset>
          <legend>Topics I follow</legend>
          <div className="portal-topic-grid">
            {Object.entries(topics).map(([key, label]) => (
              <label className="portal-check" key={key}>
                <input
                  type="checkbox"
                  name="topics"
                  value={key}
                  defaultChecked={(preferences.topics || []).includes(key)}
                />
                {label}
              </label>
            ))}
          </div>
        </fieldset>

        <button disabled={busy}>{busy ? 'Saving…' : 'Save settings'}</button>
      </form>
    </Panel>
  );
}
