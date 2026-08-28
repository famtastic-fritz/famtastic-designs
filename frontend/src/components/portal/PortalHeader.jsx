import { LABELS } from './PortalShared.jsx';

export default function PortalHeader({ section, customer, org }) {
  const initial = (customer?.display_name || customer?.email || 'U').slice(0, 1).toUpperCase();

  return (
    <header className="portal-main-header">
      <div>
        <span>FAMtastic Customer Portal</span>
        <h1>{LABELS[section] || 'Command Center'}</h1>
      </div>
      <div className="portal-user">
        <i>{initial}</i>
        <span>
          {customer?.display_name || customer?.email}
          <small>{org?.name ? `${org.name} · ${customer?.email}` : customer?.email}</small>
        </span>
      </div>
    </header>
  );
}
