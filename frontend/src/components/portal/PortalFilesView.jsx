import { Panel, Empty, date } from './PortalShared.jsx';

export default function PortalFilesView({
  workspace,
  busy,
  onUploadAsset,
  activeRequestId,
  go,
}) {
  const requests = workspace.website_requests || [];
  const allAssets = requests.flatMap((req) =>
    (req.assets || []).map((asset) => ({
      ...asset,
      projectName: req.project_name,
      requestId: req.public_id,
    }))
  );

  return (
    <section className="portal-grid two">
      <Panel
        eyebrow="Asset Management"
        title="Project Files &amp; Assets"
        className="portal-files-panel"
      >
        <p>
          Uploaded logos, imagery, flyers, and reference documents. Files stay securely encrypted and
          attached to your workspace organization.
        </p>

        {allAssets.length > 0 ? (
          <ul className="portal-file-list" style={{ marginTop: '1rem' }}>
            {allAssets.map((asset) => (
              <li
                key={asset.public_id || asset.id || asset.name}
                style={{
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                  padding: '0.85rem 0',
                  borderBottom: '1px solid var(--p-line)',
                }}
              >
                <div>
                  <strong style={{ display: 'block', color: '#fff' }}>{asset.name}</strong>
                  <small style={{ color: '#8e998e', fontSize: '0.75rem' }}>
                    {Math.ceil((asset.size_bytes || 0) / 1024)} KB · {asset.projectName}
                  </small>
                </div>
                <span
                  style={{
                    padding: '0.25rem 0.6rem',
                    borderRadius: '6px',
                    background: 'rgba(124,252,0,0.1)',
                    color: '#7cfc00',
                    fontSize: '0.72rem',
                    fontWeight: '700',
                  }}
                >
                  Secured
                </span>
              </li>
            ))}
          </ul>
        ) : (
          <Empty>
            No files uploaded yet. Add flyers, brand assets, or references during your website intake.
          </Empty>
        )}
      </Panel>

      <Panel
        eyebrow="Deliverables &amp; Artifacts"
        title="Generated Brand Assets &amp; Proofs"
      >
        <p>
          Final vector assets, typography sheets, generated concepts, and verified Build DNA packages.
        </p>

        <div style={{ marginTop: '1.25rem', display: 'grid', gap: '0.75rem' }}>
          <div
            style={{
              padding: '0.9rem',
              borderRadius: '12px',
              border: '1px solid rgba(255,255,255,0.06)',
              background: 'rgba(255,255,255,0.02)',
            }}
          >
            <strong style={{ display: 'block', color: '#fff', fontSize: '0.92rem' }}>
              Interactive Visual Proofs
            </strong>
            <span style={{ fontSize: '0.8rem', color: '#8e998e' }}>
              Full working web concepts rendered in private staging rooms.
            </span>
            <div style={{ marginTop: '0.6rem' }}>
              <button
                type="button"
                className="secondary"
                style={{ padding: '0.4rem 0.8rem', fontSize: '0.8rem' }}
                onClick={() => go('projects')}
              >
                Open Proof Room →
              </button>
            </div>
          </div>

          <div
            style={{
              padding: '0.9rem',
              borderRadius: '12px',
              border: '1px solid rgba(255,255,255,0.06)',
              background: 'rgba(255,255,255,0.02)',
            }}
          >
            <strong style={{ display: 'block', color: '#fff', fontSize: '0.92rem' }}>
              Build DNA Ledger (`famtastic.build-dna.v1`)
            </strong>
            <span style={{ fontSize: '0.8rem', color: '#8e998e' }}>
              Cryptographic SHA-256 verification and provider receipts for every build stage.
            </span>
            <div style={{ marginTop: '0.6rem' }}>
              <button
                type="button"
                className="secondary"
                style={{ padding: '0.4rem 0.8rem', fontSize: '0.8rem' }}
                onClick={() => go('projects')}
              >
                Inspect Lineage →
              </button>
            </div>
          </div>
        </div>
      </Panel>
    </section>
  );
}
