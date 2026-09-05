import { useSearchParams } from 'react-router';
import { Section } from '../components/v1/index.js';
import SolutionFinder from '../components/SolutionFinder.jsx';

/**
 * /start — full-page SolutionFinder intake experience.
 * The dedicated landing route for ads, emails, and QR codes: one job only,
 * walk the prospect from "what do you need?" to a submitted, estimated lead.
 */
export default function StartPage() {
  const [searchParams] = useSearchParams();
  const option = searchParams.get('option');
  const initialBranch = option === 'business-website' ? 'site-rebuild' : option === 'web-basics' ? 'web-basics' : null;

  return (
    <Section className="v1-section--flush-top">
      <div style={{ paddingTop: '3rem', paddingBottom: '2rem' }}>
        <SolutionFinder initialBranch={initialBranch} />
      </div>
    </Section>
  );
}
