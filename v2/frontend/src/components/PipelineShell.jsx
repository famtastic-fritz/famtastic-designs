import '../pipeline.css';

const STEPS = ['Confirm', 'Pay', 'Intake', 'Proof'];

export default function PipelineShell({ children, step = 0 }) {
  return (
    <div className="fp-page">
      <header className="fp-topbar">
        <span className="fp-brand">FAM<span className="fp-lime">tastic</span> Designs</span>
        {step > 0 && (
          <ol className="fp-steps">
            {STEPS.map((label, i) => (
              <li key={label} className={i + 1 <= step ? 'is-done' : i + 1 === step + 1 ? 'is-next' : ''}>
                <span className="fp-steps__dot">{i + 1}</span>
                <span className="fp-steps__label">{label}</span>
              </li>
            ))}
          </ol>
        )}
      </header>
      <main className="fp-container">{children}</main>
      <footer className="fp-footer">
        <span>© FAMtastic Designs · support@famtasticdesigns.com</span>
      </footer>
    </div>
  );
}
