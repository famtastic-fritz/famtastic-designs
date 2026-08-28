import { Panel, title } from './PortalShared.jsx';

export default function PortalAccountView({
  session,
  workspace,
  onSaveProfile,
  busy,
}) {
  const members = workspace.members || [];
  const customer = session?.customer || {};

  return (
    <section className="portal-grid two">
      <Panel eyebrow="Profile" title="Contact Information">
        <form onSubmit={onSaveProfile}>
          <label>
            Name
            <input
              name="display_name"
              defaultValue={customer.display_name || ''}
              placeholder="Your full name"
            />
          </label>
          <label>
            Phone
            <input
              name="phone"
              defaultValue={customer.phone || ''}
              inputMode="tel"
              placeholder="(555) 000-0000"
            />
          </label>
          <button disabled={busy}>{busy ? 'Saving…' : 'Save profile'}</button>
        </form>
      </Panel>

      <Panel eyebrow="Workspace Access" title="Team Members">
        <p style={{ fontSize: '0.85rem', color: '#aab2aa', marginBottom: '0.8rem' }}>
          Authorized organization contacts with access to view and manage this project workspace.
        </p>
        <ul>
          {members.map((member) => (
            <li key={member.public_id || member.email}>
              <div>
                <strong style={{ color: '#fff', display: 'block' }}>{member.display_name}</strong>
                <small style={{ color: '#8e998e' }}>{member.email}</small>
              </div>
              <span
                style={{
                  padding: '0.2rem 0.6rem',
                  borderRadius: '6px',
                  background: 'rgba(124,252,0,0.1)',
                  color: '#7cfc00',
                  fontSize: '0.75rem',
                  fontWeight: '700',
                  alignSelf: 'center',
                }}
              >
                {title(member.role)}
              </span>
            </li>
          ))}
        </ul>
      </Panel>
    </section>
  );
}
