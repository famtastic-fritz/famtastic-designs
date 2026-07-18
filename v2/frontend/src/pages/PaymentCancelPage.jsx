import { useNavigate, useParams } from 'react-router-dom';
import PipelineShell from '../components/PipelineShell.jsx';

export default function PaymentCancelPage() {
  const { token } = useParams();
  const navigate = useNavigate();
  return (
    <PipelineShell step={1}>
      <div className="fp-card fp-center">
        <h2>Checkout canceled</h2>
        <p className="fp-muted">No payment was taken. You can pick up where you left off whenever you’re ready.</p>
        <button className="fp-btn fp-btn--lime" onClick={() => navigate(`/p/${token}`)}>
          Back to my offer
        </button>
      </div>
    </PipelineShell>
  );
}
