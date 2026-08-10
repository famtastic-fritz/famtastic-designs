export default function DealDisclosure({ snapshot }) {
  const item = snapshot?.items?.[0];
  if (!item?.deal) return null;
  const { deal, product } = item;
  const renewal = deal.renewal;
  return (
    <details className="fp-deal">
      <summary>See exactly what this purchase includes and renews</summary>
      <div className="fp-deal__body">
        <p><strong>{product.title}:</strong> {deal.promise}</p>
        <h3>Included deliverables</h3>
        <ul>{deal.deliverables.map((value) => <li key={value}>{value}</li>)}</ul>
        {deal.included?.length > 0 && <><h3>Also included</h3><ul>{deal.included.map((value) => <li key={value}>{value}</li>)}</ul></>}
        <h3>Not included</h3>
        <ul>{deal.not_included.map((value) => <li key={value}>{value}</li>)}</ul>
        {deal.ownership && <p><strong>Ownership:</strong> {deal.ownership}</p>}
        {renewal && <p><strong>Renewal:</strong> {typeof renewal === 'string' ? renewal : Object.values(renewal).join(' · ')}</p>}
        <p><strong>Cancellation:</strong> {deal.cancellation}</p>
        <p><strong>Refunds:</strong> {deal.refund}</p>
        <p className="fp-muted">Deal scope v{deal.scope_version} · Terms policy {snapshot.policy?.version} · Evidence {snapshot.checksum?.slice(0, 12)}</p>
      </div>
    </details>
  );
}
