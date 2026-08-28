import { Panel, Empty, title } from './PortalShared.jsx';

export default function PortalReferralsView({ workspace, onRefer, busy }) {
  const referrals = workspace.referrals || [];

  return (
    <section className="portal-grid two">
      <Panel eyebrow="Refer a friend" title="Help another business get online">
        <p>
          Share FAMtastic only with someone who has agreed to hear from us. We’ll track the
          introduction without displaying their private activity.
        </p>
        <form onSubmit={onRefer}>
          <label>
            Friend’s name
            <input name="friend_name" required placeholder="Full name" />
          </label>
          <label>
            Friend’s email
            <input name="friend_email" type="email" inputMode="email" required placeholder="colleague@example.com" />
          </label>
          <label className="portal-check">
            <input name="permission_confirmed" type="checkbox" value="1" required />
            They gave me permission to share their information.
          </label>
          <button disabled={busy}>{busy ? 'Recording…' : 'Record referral'}</button>
        </form>
        <div className="portal-share">
          <a href="sms:?&body=FAMtastic%20Designs%20can%20help%20you%20get%20online:%20https://famtasticdesigns.com">
            Text a friend
          </a>
          <a href="mailto:?subject=FAMtastic%20Designs&body=Take%20a%20look:%20https://famtasticdesigns.com">
            Email a friend
          </a>
        </div>
      </Panel>

      <Panel eyebrow="Your referrals" title={`${referrals.length} shared`}>
        {referrals.length ? (
          <ul>
            {referrals.map((referral) => (
              <li key={referral.public_id || referral.id || referral.friend_name}>
                <strong>{referral.friend_name}</strong>
                <small>
                  {title(referral.status)} · {title(referral.reward_status)}
                </small>
              </li>
            ))}
          </ul>
        ) : (
          <Empty>Your referral history and earned rewards will appear here.</Empty>
        )}
      </Panel>
    </section>
  );
}
